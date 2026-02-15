<?php
/**
 * VKS ATTENDANCE SYSTEM - USER MODEL
 * 
 * Handles user authentication, profile management, and role-based access
 * 
 * @package VKS_Attendance
 * @version 1.0
 */

class User {
    
    /**
     * Authenticate user login
     * 
     * @param string $email User email
     * @param string $password Plain text password
     * @param array $location Geolocation data
     * @return array Result with success status and user data
     */
    public static function authenticate($email, $password, $location = null) {
        try {
            // Fetch user by email
            $sql = "SELECT * FROM users WHERE email = ? AND is_active = 1";
            $user = fetchOne($sql, [$email]);
            
            if (!$user) {
                logAudit('login_failed', 'users', null, null, ['email' => $email, 'reason' => 'user_not_found']);
                return ['success' => false, 'message' => 'Invalid credentials'];
            }
            
            // Verify password
            if (!verifyPassword($password, $user['password'])) {
                logAudit('login_failed', 'users', $user['id'], null, ['reason' => 'invalid_password']);
                return ['success' => false, 'message' => 'Invalid credentials'];
            }
            
            // Set session data
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user'] = [
                'id' => $user['id'],
                'employee_id' => $user['employee_id'],
                'email' => $user['email'],
                'full_name' => $user['full_name'],
                'role' => $user['role'],
                'is_manager' => $user['is_manager'],
                'profile_image' => $user['profile_image']
            ];
            
            // Regenerate session ID for security
            session_regenerate_id(true);
            
            // Log successful login with location
            logAudit('login_success', 'users', $user['id'], null, [
                'location' => $location,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? null
            ]);
            
            return [
                'success' => true,
                'message' => 'Login successful',
                'user' => $_SESSION['user']
            ];
            
        } catch (Exception $e) {
            error_log("Authentication Error: " . $e->getMessage());
            return ['success' => false, 'message' => 'System error occurred'];
        }
    }
    
    /**
     * Create new user
     * 
     * @param array $data User data
     * @return array Result with success status
     */
    public static function create($data) {
        try {
            // Validate required fields
            $required = ['employee_id', 'email', 'password', 'full_name', 'role'];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    return ['success' => false, 'message' => "Missing required field: {$field}"];
                }
            }
            
            // Validate email format
            if (!isValidEmail($data['email'])) {
                return ['success' => false, 'message' => 'Invalid email format'];
            }
            
            // Check for duplicate email or employee_id
            $exists = fetchOne(
                "SELECT id FROM users WHERE email = ? OR employee_id = ?",
                [$data['email'], $data['employee_id']]
            );
            
            if ($exists) {
                return ['success' => false, 'message' => 'Email or Employee ID already exists'];
            }
            
            // Hash password
            $hashedPassword = hashPassword($data['password']);
            
            // Insert user
            $sql = "INSERT INTO users (employee_id, email, password, full_name, role, is_manager, manager_id, phone, is_active) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $result = executeQuery($sql, [
                $data['employee_id'],
                $data['email'],
                $hashedPassword,
                $data['full_name'],
                $data['role'],
                $data['is_manager'] ?? 0,
                $data['manager_id'] ?? null,
                $data['phone'] ?? null,
                $data['is_active'] ?? 1
            ]);
            
            if ($result) {
                $userId = lastInsertId();
                
                // Initialize leave balances for all active leave categories
                self::initializeLeaveBalances($userId);
                
                logAudit('user_created', 'users', $userId, null, $data);
                
                return [
                    'success' => true,
                    'message' => 'User created successfully',
                    'user_id' => $userId
                ];
            }
            
            return ['success' => false, 'message' => 'Failed to create user'];
            
        } catch (Exception $e) {
            error_log("User Creation Error: " . $e->getMessage());
            return ['success' => false, 'message' => 'System error occurred'];
        }
    }
    
    /**
     * Initialize leave balances for a new user
     * 
     * @param int $userId User ID
     */
    private static function initializeLeaveBalances($userId) {
        $categories = fetchAll("SELECT * FROM leave_categories WHERE is_active = 1");
        
        foreach ($categories as $category) {
            $sql = "INSERT INTO leave_balances (user_id, leave_category_id, monthly_balance, quarterly_balance, annual_balance, last_reset_date) 
                    VALUES (?, ?, ?, ?, ?, ?)";
            
            executeQuery($sql, [
                $userId,
                $category['id'],
                $category['has_monthly_quota'] ? $category['monthly_quota_days'] : null,
                $category['has_quarterly_quota'] ? $category['quarterly_quota_days'] : null,
                $category['has_annual_quota'] ? $category['annual_quota_days'] : null,
                date('Y-12-31')  // Last reset on December 31
            ]);
        }
    }
    
    /**
     * Update user profile
     * 
     * @param int $userId User ID
     * @param array $data Update data
     * @return array Result with success status
     */
    public static function update($userId, $data) {
        try {
            $user = self::getById($userId);
            if (!$user) {
                return ['success' => false, 'message' => 'User not found'];
            }
            
            $updates = [];
            $params = [];
            
            // Build dynamic update query
            $allowedFields = ['full_name', 'phone', 'email', 'role', 'is_manager', 'manager_id', 'is_active', 'profile_image'];
            
            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $updates[] = "{$field} = ?";
                    $params[] = $data[$field];
                }
            }
            
            // Handle password update separately
            if (!empty($data['password'])) {
                $updates[] = "password = ?";
                $params[] = hashPassword($data['password']);
            }
            
            if (empty($updates)) {
                return ['success' => false, 'message' => 'No fields to update'];
            }
            
            $params[] = $userId;
            $sql = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = ?";
            
            $result = executeQuery($sql, $params);
            
            if ($result) {
                logAudit('user_updated', 'users', $userId, $user, $data);
                
                // Update session if updating current user
                if ($userId == ($_SESSION['user_id'] ?? null)) {
                    $updatedUser = self::getById($userId);
                    $_SESSION['user'] = [
                        'id' => $updatedUser['id'],
                        'employee_id' => $updatedUser['employee_id'],
                        'email' => $updatedUser['email'],
                        'full_name' => $updatedUser['full_name'],
                        'role' => $updatedUser['role'],
                        'is_manager' => $updatedUser['is_manager'],
                        'profile_image' => $updatedUser['profile_image']
                    ];
                }
                
                return ['success' => true, 'message' => 'User updated successfully'];
            }
            
            return ['success' => false, 'message' => 'Failed to update user'];
            
        } catch (Exception $e) {
            error_log("User Update Error: " . $e->getMessage());
            return ['success' => false, 'message' => 'System error occurred'];
        }
    }
    
    /**
     * Get user by ID
     * 
     * @param int $userId User ID
     * @return array|false User data or false
     */
    public static function getById($userId) {
        $sql = "SELECT u.*, m.full_name as manager_name 
                FROM users u
                LEFT JOIN users m ON u.manager_id = m.id
                WHERE u.id = ?";
        return fetchOne($sql, [$userId]);
    }
    
    /**
     * Get all users with optional filters
     * 
     * @param array $filters Filters (role, is_active, manager_id)
     * @param int $limit Limit
     * @param int $offset Offset
     * @return array Array of users
     */
    public static function getAll($filters = [], $limit = 100, $offset = 0) {
        $where = [];
        $params = [];
        
        if (isset($filters['role'])) {
            $where[] = "u.role = ?";
            $params[] = $filters['role'];
        }
        
        if (isset($filters['is_active'])) {
            $where[] = "u.is_active = ?";
            $params[] = $filters['is_active'];
        }
        
        if (isset($filters['manager_id'])) {
            $where[] = "u.manager_id = ?";
            $params[] = $filters['manager_id'];
        }
        
        if (isset($filters['search'])) {
            $where[] = "(u.full_name LIKE ? OR u.email LIKE ? OR u.employee_id LIKE ?)";
            $searchTerm = "%{$filters['search']}%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        $whereClause = !empty($where) ? "WHERE " . implode(' AND ', $where) : "";
        
        $sql = "SELECT u.*, m.full_name as manager_name 
                FROM users u
                LEFT JOIN users m ON u.manager_id = m.id
                {$whereClause}
                ORDER BY u.created_at DESC
                LIMIT ? OFFSET ?";
        
        $params[] = $limit;
        $params[] = $offset;
        
        return fetchAll($sql, $params);
    }
    
    /**
     * Get users managed by a specific manager
     * 
     * @param int $managerId Manager user ID
     * @return array Array of team members
     */
    public static function getTeamMembers($managerId) {
        $sql = "SELECT * FROM users WHERE manager_id = ? AND is_active = 1 ORDER BY full_name ASC";
        return fetchAll($sql, [$managerId]);
    }
    
    /**
     * Get all managers (users with is_manager = 1 or role = manager/admin)
     * 
     * @return array Array of managers
     */
    public static function getAllManagers() {
        $sql = "SELECT * FROM users 
                WHERE (is_manager = 1 OR role IN ('manager', 'admin')) 
                AND is_active = 1 
                ORDER BY full_name ASC";
        return fetchAll($sql);
    }
    
    /**
     * Delete user (soft delete by setting is_active = 0)
     * 
     * @param int $userId User ID
     * @return array Result with success status
     */
    public static function delete($userId) {
        try {
            // Don't allow deleting self
            if ($userId == ($_SESSION['user_id'] ?? null)) {
                return ['success' => false, 'message' => 'Cannot delete your own account'];
            }
            
            $user = self::getById($userId);
            if (!$user) {
                return ['success' => false, 'message' => 'User not found'];
            }
            
            // Soft delete
            $sql = "UPDATE users SET is_active = 0 WHERE id = ?";
            $result = executeQuery($sql, [$userId]);
            
            if ($result) {
                logAudit('user_deleted', 'users', $userId, $user, ['is_active' => 0]);
                return ['success' => true, 'message' => 'User deactivated successfully'];
            }
            
            return ['success' => false, 'message' => 'Failed to deactivate user'];
            
        } catch (Exception $e) {
            error_log("User Delete Error: " . $e->getMessage());
            return ['success' => false, 'message' => 'System error occurred'];
        }
    }
    
    /**
     * Upload profile image
     * 
     * @param int $userId User ID
     * @param array $file $_FILES array element
     * @return array Result with success status and file path
     */
    public static function uploadProfileImage($userId, $file) {
        try {
            // Validate file
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $maxSize = 2 * 1024 * 1024; // 2MB
            
            if (!in_array($file['type'], $allowedTypes)) {
                return ['success' => false, 'message' => 'Invalid file type. Only JPG, PNG, GIF, WEBP allowed'];
            }
            
            if ($file['size'] > $maxSize) {
                return ['success' => false, 'message' => 'File too large. Maximum size is 2MB'];
            }
            
            // Generate unique filename
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'profile_' . $userId . '_' . time() . '.' . $extension;
            $uploadPath = __DIR__ . '/../../public/assets/uploads/' . $filename;
            
            // Move uploaded file
            if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
                // Delete old profile image if exists
                $user = self::getById($userId);
                if ($user && $user['profile_image']) {
                    $oldImagePath = __DIR__ . '/../../public/assets/uploads/' . $user['profile_image'];
                    if (file_exists($oldImagePath)) {
                        unlink($oldImagePath);
                    }
                }
                
                // Update database
                $sql = "UPDATE users SET profile_image = ? WHERE id = ?";
                executeQuery($sql, [$filename, $userId]);
                
                return [
                    'success' => true,
                    'message' => 'Profile image uploaded successfully',
                    'filename' => $filename
                ];
            }
            
            return ['success' => false, 'message' => 'Failed to upload file'];
            
        } catch (Exception $e) {
            error_log("Profile Image Upload Error: " . $e->getMessage());
            return ['success' => false, 'message' => 'System error occurred'];
        }
    }
    
    /**
     * Get user statistics
     * 
     * @param int $userId User ID
     * @return array Statistics
     */
    public static function getUserStats($userId) {
        // Current month attendance
        $currentMonth = date('n');
        $currentYear = date('Y');
        
        $sql = "SELECT 
                    COUNT(*) as days_present,
                    SUM(CASE WHEN status = 'full_day' THEN 1 ELSE 0 END) as full_days,
                    COALESCE(SUM(total_hours), 0) as total_hours
                FROM attendance
                WHERE user_id = ? 
                AND MONTH(attendance_date) = ? 
                AND YEAR(attendance_date) = ?
                AND punch_out IS NOT NULL";
        
        $attendance = fetchOne($sql, [$userId, $currentMonth, $currentYear]);
        
        // Leave balances
        $sql = "SELECT lc.category_name, lb.annual_balance, lb.comp_off_balance
                FROM leave_balances lb
                JOIN leave_categories lc ON lb.leave_category_id = lc.id
                WHERE lb.user_id = ?";
        
        $leaveBalances = fetchAll($sql, [$userId]);
        
        // Pending leave requests
        $pendingLeaves = fetchOne(
            "SELECT COUNT(*) as count FROM leave_requests WHERE user_id = ? AND status = 'pending'",
            [$userId]
        );
        
        return [
            'attendance' => $attendance,
            'leave_balances' => $leaveBalances,
            'pending_leaves' => $pendingLeaves['count'] ?? 0
        ];
    }
}
