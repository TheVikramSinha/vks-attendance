<?php
/**
 * VKS ATTENDANCE SYSTEM - LEAVE MODEL
 * 
 * Handles leave requests, approvals, quota management, and comp-offs
 * Quotas reset on December 31st annually
 * 
 * @package VKS_Attendance
 * @version 1.0
 */

class Leave {
    
    /**
     * Create leave request
     * 
     * @param array $data Leave request data
     * @return array Result with success status
     */
    public static function createRequest($data) {
        try {
            // Validate required fields
            $required = ['user_id', 'leave_category_id', 'start_date', 'end_date', 'reason'];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    return ['success' => false, 'message' => "Missing required field: {$field}"];
                }
            }
            
            // Validate dates
            if (strtotime($data['start_date']) > strtotime($data['end_date'])) {
                return ['success' => false, 'message' => 'End date cannot be before start date'];
            }
            
            // Check for overlapping attendance
            $overlap = fetchOne(
                "SELECT COUNT(*) as count FROM attendance 
                 WHERE user_id = ? AND attendance_date BETWEEN ? AND ? AND punch_in IS NOT NULL",
                [$data['user_id'], $data['start_date'], $data['end_date']]
            );
            
            if ($overlap['count'] > 0) {
                return ['success' => false, 'message' => 'Cannot request leave for dates with existing attendance'];
            }
            
            // Calculate total days
            $totalDays = self::calculateLeaveDays($data['start_date'], $data['end_date'], $data['is_half_day'] ?? false);
            
            // Check quota availability
            $balanceCheck = self::checkQuotaAvailability($data['user_id'], $data['leave_category_id'], $totalDays);
            if (!$balanceCheck['available']) {
                return ['success' => false, 'message' => $balanceCheck['message']];
            }
            
            // Get user's manager for approval
            $user = fetchOne("SELECT manager_id FROM users WHERE id = ?", [$data['user_id']]);
            
            // Insert leave request
            $sql = "INSERT INTO leave_requests (user_id, leave_category_id, start_date, end_date, is_half_day, total_days, reason, status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')";
            
            $result = executeQuery($sql, [
                $data['user_id'],
                $data['leave_category_id'],
                $data['start_date'],
                $data['end_date'],
                $data['is_half_day'] ?? 0,
                $totalDays,
                $data['reason']
            ]);
            
            if ($result) {
                $requestId = lastInsertId();
                
                // Send notification to manager
                if ($user && $user['manager_id']) {
                    self::notifyManager($user['manager_id'], $data['user_id'], $requestId);
                }
                
                logAudit('leave_request_created', 'leave_requests', $requestId, null, $data);
                
                return [
                    'success' => true,
                    'message' => 'Leave request submitted successfully',
                    'request_id' => $requestId
                ];
            }
            
            return ['success' => false, 'message' => 'Failed to create leave request'];
            
        } catch (Exception $e) {
            error_log("Leave Request Error: " . $e->getMessage());
            return ['success' => false, 'message' => 'System error occurred'];
        }
    }
    
    /**
     * Calculate leave days (handles half-day logic)
     * 
     * @param string $startDate Start date
     * @param string $endDate End date
     * @param bool $isHalfDay Is half day leave
     * @return float Total days (0.5 for half day)
     */
    private static function calculateLeaveDays($startDate, $endDate, $isHalfDay = false) {
        if ($isHalfDay) {
            return 0.5;
        }
        
        $start = new DateTime($startDate);
        $end = new DateTime($endDate);
        $interval = $start->diff($end);
        
        return $interval->days + 1; // Include both start and end dates
    }
    
    /**
     * Check if user has sufficient quota
     * 
     * @param int $userId User ID
     * @param int $categoryId Leave category ID
     * @param float $requestedDays Days requested
     * @return array Result with availability status
     */
    private static function checkQuotaAvailability($userId, $categoryId, $requestedDays) {
        // Get category settings
        $category = fetchOne("SELECT * FROM leave_categories WHERE id = ?", [$categoryId]);
        
        if (!$category || !$category['is_active']) {
            return ['available' => false, 'message' => 'Invalid leave category'];
        }
        
        // If no quota required (like some comp-off categories), approve
        if (!$category['has_monthly_quota'] && !$category['has_quarterly_quota'] && !$category['has_annual_quota']) {
            return ['available' => true];
        }
        
        // Get user's balance
        $balance = fetchOne(
            "SELECT * FROM leave_balances WHERE user_id = ? AND leave_category_id = ?",
            [$userId, $categoryId]
        );
        
        if (!$balance) {
            return ['available' => false, 'message' => 'No leave balance found'];
        }
        
        // Check comp-off balance first
        if ($balance['comp_off_balance'] >= $requestedDays) {
            return ['available' => true, 'use_comp_off' => true];
        }
        
        // Check monthly quota
        if ($category['has_monthly_quota'] && $balance['monthly_balance'] < $requestedDays) {
            return ['available' => false, 'message' => 'Insufficient monthly quota'];
        }
        
        // Check quarterly quota
        if ($category['has_quarterly_quota'] && $balance['quarterly_balance'] < $requestedDays) {
            return ['available' => false, 'message' => 'Insufficient quarterly quota'];
        }
        
        // Check annual quota
        if ($category['has_annual_quota'] && $balance['annual_balance'] < $requestedDays) {
            return ['available' => false, 'message' => 'Insufficient annual quota'];
        }
        
        return ['available' => true];
    }
    
    /**
     * Approve leave request
     * 
     * @param int $requestId Leave request ID
     * @param int $reviewerId Reviewer user ID
     * @param string $notes Review notes
     * @return array Result with success status
     */
    public static function approve($requestId, $reviewerId, $notes = null) {
        try {
            $request = fetchOne("SELECT * FROM leave_requests WHERE id = ?", [$requestId]);
            
            if (!$request) {
                return ['success' => false, 'message' => 'Leave request not found'];
            }
            
            if ($request['status'] !== 'pending') {
                return ['success' => false, 'message' => 'Request already processed'];
            }
            
            beginTransaction();
            
            try {
                // Update request status
                $sql = "UPDATE leave_requests 
                        SET status = 'approved', reviewed_by = ?, reviewed_at = NOW(), review_notes = ?
                        WHERE id = ?";
                
                executeQuery($sql, [$reviewerId, $notes, $requestId]);
                
                // Deduct from quota
                self::deductQuota($request['user_id'], $request['leave_category_id'], $request['total_days']);
                
                // Send notification to user
                self::notifyUser($request['user_id'], 'leave_approved', 'Leave Approved', 
                    'Your leave request from ' . formatDate($request['start_date']) . ' to ' . formatDate($request['end_date']) . ' has been approved.');
                
                commit();
                
                logAudit('leave_approved', 'leave_requests', $requestId, 
                    ['status' => 'pending'], 
                    ['status' => 'approved', 'reviewer_id' => $reviewerId]
                );
                
                return ['success' => true, 'message' => 'Leave request approved'];
                
            } catch (Exception $e) {
                rollback();
                throw $e;
            }
            
        } catch (Exception $e) {
            error_log("Leave Approval Error: " . $e->getMessage());
            return ['success' => false, 'message' => 'System error occurred'];
        }
    }
    
    /**
     * Reject leave request
     * 
     * @param int $requestId Leave request ID
     * @param int $reviewerId Reviewer user ID
     * @param string $notes Review notes
     * @return array Result with success status
     */
    public static function reject($requestId, $reviewerId, $notes = null) {
        try {
            $request = fetchOne("SELECT * FROM leave_requests WHERE id = ?", [$requestId]);
            
            if (!$request) {
                return ['success' => false, 'message' => 'Leave request not found'];
            }
            
            if ($request['status'] !== 'pending') {
                return ['success' => false, 'message' => 'Request already processed'];
            }
            
            $sql = "UPDATE leave_requests 
                    SET status = 'rejected', reviewed_by = ?, reviewed_at = NOW(), review_notes = ?
                    WHERE id = ?";
            
            executeQuery($sql, [$reviewerId, $notes, $requestId]);
            
            // Send notification to user
            self::notifyUser($request['user_id'], 'leave_rejected', 'Leave Rejected', 
                'Your leave request from ' . formatDate($request['start_date']) . ' to ' . formatDate($request['end_date']) . ' has been rejected.' . 
                ($notes ? " Reason: {$notes}" : ''));
            
            logAudit('leave_rejected', 'leave_requests', $requestId, 
                ['status' => 'pending'], 
                ['status' => 'rejected', 'reviewer_id' => $reviewerId, 'notes' => $notes]
            );
            
            return ['success' => true, 'message' => 'Leave request rejected'];
            
        } catch (Exception $e) {
            error_log("Leave Rejection Error: " . $e->getMessage());
            return ['success' => false, 'message' => 'System error occurred'];
        }
    }
    
    /**
     * Deduct quota from user's balance
     * 
     * @param int $userId User ID
     * @param int $categoryId Leave category ID
     * @param float $days Days to deduct
     */
    private static function deductQuota($userId, $categoryId, $days) {
        // Get balance and category
        $balance = fetchOne(
            "SELECT * FROM leave_balances WHERE user_id = ? AND leave_category_id = ?",
            [$userId, $categoryId]
        );
        
        $category = fetchOne("SELECT * FROM leave_categories WHERE id = ?", [$categoryId]);
        
        // Try to deduct from comp-off first
        if ($balance['comp_off_balance'] >= $days) {
            $sql = "UPDATE leave_balances SET comp_off_balance = comp_off_balance - ? 
                    WHERE user_id = ? AND leave_category_id = ?";
            executeQuery($sql, [$days, $userId, $categoryId]);
            return;
        }
        
        // Deduct from comp-off partially
        $remainingDays = $days;
        if ($balance['comp_off_balance'] > 0) {
            $sql = "UPDATE leave_balances SET comp_off_balance = 0 
                    WHERE user_id = ? AND leave_category_id = ?";
            executeQuery($sql, [$userId, $categoryId]);
            $remainingDays -= $balance['comp_off_balance'];
        }
        
        // Deduct from appropriate quota
        $updates = [];
        
        if ($category['has_monthly_quota'] && $balance['monthly_balance'] >= $remainingDays) {
            $updates[] = "monthly_balance = monthly_balance - {$remainingDays}";
        }
        
        if ($category['has_quarterly_quota'] && $balance['quarterly_balance'] >= $remainingDays) {
            $updates[] = "quarterly_balance = quarterly_balance - {$remainingDays}";
        }
        
        if ($category['has_annual_quota'] && $balance['annual_balance'] >= $remainingDays) {
            $updates[] = "annual_balance = annual_balance - {$remainingDays}";
        }
        
        if (!empty($updates)) {
            $sql = "UPDATE leave_balances SET " . implode(', ', $updates) . " 
                    WHERE user_id = ? AND leave_category_id = ?";
            executeQuery($sql, [$userId, $categoryId]);
        }
    }
    
    /**
     * Add comp-off to user (Manager action)
     * 
     * @param int $userId User ID
     * @param int $categoryId Leave category ID
     * @param float $days Comp-off days to add
     * @param string $reason Reason for comp-off
     * @return array Result with success status
     */
    public static function addCompOff($userId, $categoryId, $days, $reason) {
        try {
            $sql = "UPDATE leave_balances 
                    SET comp_off_balance = comp_off_balance + ? 
                    WHERE user_id = ? AND leave_category_id = ?";
            
            $result = executeQuery($sql, [$days, $userId, $categoryId]);
            
            if ($result) {
                // Notify user
                self::notifyUser($userId, 'comp_off_added', 'Comp-Off Added', 
                    "{$days} day(s) comp-off has been added to your account. Reason: {$reason}");
                
                logAudit('comp_off_added', 'leave_balances', null, null, [
                    'user_id' => $userId,
                    'category_id' => $categoryId,
                    'days' => $days,
                    'reason' => $reason
                ]);
                
                return ['success' => true, 'message' => 'Comp-off added successfully'];
            }
            
            return ['success' => false, 'message' => 'Failed to add comp-off'];
            
        } catch (Exception $e) {
            error_log("Add Comp-Off Error: " . $e->getMessage());
            return ['success' => false, 'message' => 'System error occurred'];
        }
    }
    
    /**
     * Reset quotas on December 31st (CRON job)
     * Resets monthly, quarterly, and annual quotas
     */
    public static function resetAnnualQuotas() {
        try {
            $today = date('Y-m-d');
            $isDecember31 = date('m-d') === '12-31';
            
            if (!$isDecember31) {
                return ['success' => false, 'message' => 'Quota reset only runs on December 31'];
            }
            
            // Get all active leave categories
            $categories = fetchAll("SELECT * FROM leave_categories WHERE is_active = 1");
            
            foreach ($categories as $category) {
                $updates = [];
                
                if ($category['has_monthly_quota']) {
                    $updates[] = "monthly_balance = {$category['monthly_quota_days']}";
                }
                
                if ($category['has_quarterly_quota']) {
                    $updates[] = "quarterly_balance = {$category['quarterly_quota_days']}";
                }
                
                if ($category['has_annual_quota']) {
                    $updates[] = "annual_balance = {$category['annual_quota_days']}";
                }
                
                if (!empty($updates)) {
                    $sql = "UPDATE leave_balances 
                            SET " . implode(', ', $updates) . ", last_reset_date = ? 
                            WHERE leave_category_id = ?";
                    executeQuery($sql, [$today, $category['id']]);
                }
            }
            
            logAudit('quota_reset', 'leave_balances', null, null, ['reset_date' => $today]);
            
            return ['success' => true, 'message' => 'Annual quotas reset successfully'];
            
        } catch (Exception $e) {
            error_log("Quota Reset Error: " . $e->getMessage());
            return ['success' => false, 'message' => 'System error occurred'];
        }
    }
    
    /**
     * Get leave requests for a user
     * 
     * @param int $userId User ID
     * @param string $status Filter by status
     * @param int $limit Limit
     * @return array Array of leave requests
     */
    public static function getUserRequests($userId, $status = null, $limit = 50) {
        $where = "user_id = ?";
        $params = [$userId];
        
        if ($status) {
            $where .= " AND status = ?";
            $params[] = $status;
        }
        
        $sql = "SELECT lr.*, lc.category_name, u.full_name as reviewer_name
                FROM leave_requests lr
                JOIN leave_categories lc ON lr.leave_category_id = lc.id
                LEFT JOIN users u ON lr.reviewed_by = u.id
                WHERE {$where}
                ORDER BY lr.created_at DESC
                LIMIT ?";
        
        $params[] = $limit;
        
        return fetchAll($sql, $params);
    }
    
    /**
     * Get pending leave requests for a manager
     * 
     * @param int $managerId Manager ID
     * @return array Array of pending requests
     */
    public static function getPendingRequestsForManager($managerId) {
        $sql = "SELECT lr.*, lc.category_name, u.full_name as user_name, u.employee_id
                FROM leave_requests lr
                JOIN leave_categories lc ON lr.leave_category_id = lc.id
                JOIN users u ON lr.user_id = u.id
                WHERE u.manager_id = ? AND lr.status = 'pending'
                ORDER BY lr.created_at ASC";
        
        return fetchAll($sql, [$managerId]);
    }
    
    /**
     * Get leave balances for a user
     * 
     * @param int $userId User ID
     * @return array Array of balances
     */
    public static function getUserBalances($userId) {
        $sql = "SELECT lb.*, lc.category_name, lc.category_code
                FROM leave_balances lb
                JOIN leave_categories lc ON lb.leave_category_id = lc.id
                WHERE lb.user_id = ? AND lc.is_active = 1
                ORDER BY lc.category_name ASC";
        
        return fetchAll($sql, [$userId]);
    }
    
    /**
     * Send notification to manager
     * 
     * @param int $managerId Manager ID
     * @param int $userId User ID who made request
     * @param int $requestId Leave request ID
     */
    private static function notifyManager($managerId, $userId, $requestId) {
        $user = fetchOne("SELECT full_name FROM users WHERE id = ?", [$userId]);
        
        $sql = "INSERT INTO notifications (user_id, type, title, message, action_url) 
                VALUES (?, 'general', 'New Leave Request', ?, ?)";
        
        executeQuery($sql, [
            $managerId,
            "{$user['full_name']} has submitted a new leave request awaiting your approval.",
            "manager/leave-approvals?request_id={$requestId}"
        ]);
    }
    
    /**
     * Send notification to user
     * 
     * @param int $userId User ID
     * @param string $type Notification type
     * @param string $title Title
     * @param string $message Message
     */
    private static function notifyUser($userId, $type, $title, $message) {
        $sql = "INSERT INTO notifications (user_id, type, title, message) 
                VALUES (?, ?, ?, ?)";
        
        executeQuery($sql, [$userId, $type, $title, $message]);
    }
    
    /**
     * Get all leave categories
     * 
     * @param bool $activeOnly Only active categories
     * @return array Array of categories
     */
    public static function getCategories($activeOnly = true) {
        $where = $activeOnly ? "WHERE is_active = 1" : "";
        $sql = "SELECT * FROM leave_categories {$where} ORDER BY category_name ASC";
        return fetchAll($sql);
    }
    
    /**
     * Create leave category (Admin function)
     * 
     * @param array $data Category data
     * @return array Result with success status
     */
    public static function createCategory($data) {
        try {
            $sql = "INSERT INTO leave_categories 
                    (category_name, category_code, has_monthly_quota, monthly_quota_days, 
                     has_quarterly_quota, quarterly_quota_days, has_annual_quota, annual_quota_days, 
                     requires_approval, is_paid, description) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $result = executeQuery($sql, [
                $data['category_name'],
                $data['category_code'],
                $data['has_monthly_quota'] ?? 0,
                $data['monthly_quota_days'] ?? null,
                $data['has_quarterly_quota'] ?? 0,
                $data['quarterly_quota_days'] ?? null,
                $data['has_annual_quota'] ?? 0,
                $data['annual_quota_days'] ?? null,
                $data['requires_approval'] ?? 1,
                $data['is_paid'] ?? 1,
                $data['description'] ?? null
            ]);
            
            if ($result) {
                $categoryId = lastInsertId();
                
                // Initialize balances for all users
                self::initializeCategoryForAllUsers($categoryId);
                
                return ['success' => true, 'message' => 'Leave category created successfully'];
            }
            
            return ['success' => false, 'message' => 'Failed to create category'];
            
        } catch (Exception $e) {
            error_log("Create Leave Category Error: " . $e->getMessage());
            return ['success' => false, 'message' => 'System error occurred'];
        }
    }
    
    /**
     * Initialize new category balances for all users
     * 
     * @param int $categoryId Category ID
     */
    private static function initializeCategoryForAllUsers($categoryId) {
        $category = fetchOne("SELECT * FROM leave_categories WHERE id = ?", [$categoryId]);
        $users = fetchAll("SELECT id FROM users WHERE is_active = 1");
        
        foreach ($users as $user) {
            $sql = "INSERT INTO leave_balances (user_id, leave_category_id, monthly_balance, quarterly_balance, annual_balance, last_reset_date) 
                    VALUES (?, ?, ?, ?, ?, ?)";
            
            executeQuery($sql, [
                $user['id'],
                $categoryId,
                $category['has_monthly_quota'] ? $category['monthly_quota_days'] : null,
                $category['has_quarterly_quota'] ? $category['quarterly_quota_days'] : null,
                $category['has_annual_quota'] ? $category['annual_quota_days'] : null,
                date('Y-12-31')
            ]);
        }
    }
}
