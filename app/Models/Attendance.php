<?php
/**
 * VKS ATTENDANCE SYSTEM - ATTENDANCE MODEL
 * 
 * Implements the core 6/8/10 hour business logic:
 * - < 6 hours = half_day
 * - 6-8 hours = short_day
 * - >= 8 hours = full_day
 * - Auto-logout at 10 hours
 * 
 * @package VKS_Attendance
 * @version 1.0
 */

class Attendance {
    
    /**
     * Constants for business logic
     */
    const HALF_DAY_THRESHOLD = 6.0;
    const SHORT_DAY_THRESHOLD = 8.0;
    const AUTO_LOGOUT_HOURS = 10.0;
    const MAX_BREAK_MINUTES = 75;
    
    /**
     * Punch in user for the day
     * 
     * @param int $userId User ID
     * @param string $location Lat,Lon coordinates
     * @return array Result with success status and message
     */
    public static function punchIn($userId, $location) {
        try {
            $today = date('Y-m-d');
            
            // Check if already punched in today
            $existing = self::getTodayAttendance($userId);
            if ($existing && $existing['punch_in'] && !$existing['punch_out']) {
                return ['success' => false, 'message' => 'Already punched in today'];
            }
            
            // Check if already completed attendance for today
            if ($existing && $existing['punch_out']) {
                return ['success' => false, 'message' => 'Attendance already completed for today'];
            }
            
            // Handle midnight crossing - if punch-in is after midnight of an open session
            if ($existing && !$existing['punch_out']) {
                // Force punch-out the previous session at midnight
                self::forcePunchOut($existing['id'], $existing['attendance_date'] . ' 23:59:59', 'System: Midnight crossing', true);
            }
            
            // Create new attendance record
            $sql = "INSERT INTO attendance (user_id, attendance_date, punch_in, punch_in_location, status) 
                    VALUES (?, ?, NOW(), ?, 'pending')";
            
            $result = executeQuery($sql, [$userId, $today, $location]);
            
            if ($result) {
                $attendanceId = lastInsertId();
                logAudit('punch_in', 'attendance', $attendanceId, null, ['user_id' => $userId, 'location' => $location]);
                
                return [
                    'success' => true, 
                    'message' => 'Punched in successfully',
                    'attendance_id' => $attendanceId,
                    'punch_in_time' => date('Y-m-d H:i:s')
                ];
            }
            
            return ['success' => false, 'message' => 'Failed to punch in'];
            
        } catch (Exception $e) {
            error_log("Punch In Error: " . $e->getMessage());
            return ['success' => false, 'message' => 'System error occurred'];
        }
    }
    
    /**
     * Punch out user for the day
     * Implements 6/8/10 hour business logic
     * 
     * @param int $userId User ID
     * @param string $location Lat,Lon coordinates
     * @return array Result with success status and message
     */
    public static function punchOut($userId, $location) {
        try {
            $today = date('Y-m-d');
            
            // Get today's attendance
            $attendance = self::getTodayAttendance($userId);
            
            if (!$attendance) {
                return ['success' => false, 'message' => 'No punch-in record found'];
            }
            
            if ($attendance['punch_out']) {
                return ['success' => false, 'message' => 'Already punched out'];
            }
            
            // Calculate total hours
            $punchInTime = strtotime($attendance['punch_in']);
            $punchOutTime = time();
            $totalHours = ($punchOutTime - $punchInTime) / 3600;
            
            // Determine status based on 6/8/10 rule
            $status = self::calculateStatus($totalHours);
            
            // Update attendance record
            $sql = "UPDATE attendance 
                    SET punch_out = NOW(), 
                        punch_out_location = ?,
                        total_hours = ?,
                        status = ?
                    WHERE id = ?";
            
            $result = executeQuery($sql, [$location, round($totalHours, 2), $status, $attendance['id']]);
            
            if ($result) {
                logAudit('punch_out', 'attendance', $attendance['id'], 
                    ['status' => 'pending'], 
                    ['status' => $status, 'total_hours' => round($totalHours, 2)]
                );
                
                // Check for break violations
                self::checkBreakViolations($attendance['id'], $userId);
                
                return [
                    'success' => true,
                    'message' => 'Punched out successfully',
                    'total_hours' => round($totalHours, 2),
                    'status' => $status
                ];
            }
            
            return ['success' => false, 'message' => 'Failed to punch out'];
            
        } catch (Exception $e) {
            error_log("Punch Out Error: " . $e->getMessage());
            return ['success' => false, 'message' => 'System error occurred'];
        }
    }
    
    /**
     * Calculate attendance status based on total hours (6/8/10 rule)
     * 
     * @param float $totalHours Total hours worked
     * @return string Status (half_day, short_day, full_day)
     */
    private static function calculateStatus($totalHours) {
        if ($totalHours < self::HALF_DAY_THRESHOLD) {
            return 'half_day';
        } elseif ($totalHours >= self::HALF_DAY_THRESHOLD && $totalHours < self::SHORT_DAY_THRESHOLD) {
            return 'short_day';
        } else {
            return 'full_day';
        }
    }
    
    /**
     * Start a break
     * 
     * @param int $attendanceId Attendance record ID
     * @return array Result with success status
     */
    public static function startBreak($attendanceId) {
        try {
            // Check if there's an active break
            $activeBreak = self::getActiveBreak($attendanceId);
            if ($activeBreak) {
                return ['success' => false, 'message' => 'Break already in progress'];
            }
            
            $sql = "INSERT INTO breaks (attendance_id, break_start) VALUES (?, NOW())";
            $result = executeQuery($sql, [$attendanceId]);
            
            if ($result) {
                $breakId = lastInsertId();
                return [
                    'success' => true,
                    'message' => 'Break started',
                    'break_id' => $breakId,
                    'break_start' => date('Y-m-d H:i:s')
                ];
            }
            
            return ['success' => false, 'message' => 'Failed to start break'];
            
        } catch (Exception $e) {
            error_log("Start Break Error: " . $e->getMessage());
            return ['success' => false, 'message' => 'System error occurred'];
        }
    }
    
    /**
     * End a break
     * 
     * @param int $attendanceId Attendance record ID
     * @return array Result with success status
     */
    public static function endBreak($attendanceId) {
        try {
            // Get active break
            $activeBreak = self::getActiveBreak($attendanceId);
            if (!$activeBreak) {
                return ['success' => false, 'message' => 'No active break found'];
            }
            
            // Calculate duration
            $startTime = strtotime($activeBreak['break_start']);
            $endTime = time();
            $durationMinutes = round(($endTime - $startTime) / 60);
            
            $sql = "UPDATE breaks 
                    SET break_end = NOW(), 
                        duration_minutes = ?
                    WHERE id = ?";
            
            $result = executeQuery($sql, [$durationMinutes, $activeBreak['id']]);
            
            if ($result) {
                return [
                    'success' => true,
                    'message' => 'Break ended',
                    'duration_minutes' => $durationMinutes
                ];
            }
            
            return ['success' => false, 'message' => 'Failed to end break'];
            
        } catch (Exception $e) {
            error_log("End Break Error: " . $e->getMessage());
            return ['success' => false, 'message' => 'System error occurred'];
        }
    }
    
    /**
     * Check for break violations (>75 minutes total)
     * 
     * @param int $attendanceId Attendance record ID
     * @param int $userId User ID
     */
    private static function checkBreakViolations($attendanceId, $userId) {
        // Get total break time for the day
        $sql = "SELECT COALESCE(SUM(duration_minutes), 0) as total_break_minutes 
                FROM breaks 
                WHERE attendance_id = ? AND break_end IS NOT NULL";
        
        $result = fetchOne($sql, [$attendanceId]);
        $totalBreakMinutes = $result['total_break_minutes'] ?? 0;
        
        if ($totalBreakMinutes > self::MAX_BREAK_MINUTES) {
            // Get user's manager
            $user = fetchOne("SELECT manager_id, full_name FROM users WHERE id = ?", [$userId]);
            
            if ($user && $user['manager_id']) {
                // Create notification for manager
                self::createBreakViolationNotification(
                    $user['manager_id'], 
                    $userId, 
                    $user['full_name'], 
                    $totalBreakMinutes,
                    $attendanceId
                );
                
                // Flag for daily report
                self::flagForDailyReport($user['manager_id'], $userId, $totalBreakMinutes, $attendanceId);
            }
        }
    }
    
    /**
     * Create break violation notification
     * 
     * @param int $managerId Manager user ID
     * @param int $userId User ID who violated
     * @param string $userName User's full name
     * @param int $totalBreakMinutes Total break minutes
     * @param int $attendanceId Attendance record ID
     */
    private static function createBreakViolationNotification($managerId, $userId, $userName, $totalBreakMinutes, $attendanceId) {
        $title = "Break Time Violation";
        $message = "{$userName} exceeded the break time limit. Total break time: {$totalBreakMinutes} minutes (Limit: " . self::MAX_BREAK_MINUTES . " minutes)";
        
        $sql = "INSERT INTO notifications (user_id, type, title, message, action_url) 
                VALUES (?, 'break_violation', ?, ?, ?)";
        
        executeQuery($sql, [
            $managerId,
            $title,
            $message,
            "manager/attendance-details?id={$attendanceId}"
        ]);
    }
    
    /**
     * Flag violation for daily report
     * 
     * @param int $managerId Manager user ID
     * @param int $userId User ID
     * @param int $totalBreakMinutes Total break minutes
     * @param int $attendanceId Attendance record ID
     */
    private static function flagForDailyReport($managerId, $userId, $totalBreakMinutes, $attendanceId) {
        $reportDate = date('Y-m-d');
        
        // Check if report exists for today
        $existingReport = fetchOne(
            "SELECT id, report_data FROM daily_reports WHERE manager_id = ? AND report_date = ?",
            [$managerId, $reportDate]
        );
        
        $violationData = [
            'user_id' => $userId,
            'attendance_id' => $attendanceId,
            'total_break_minutes' => $totalBreakMinutes,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        if ($existingReport) {
            // Update existing report
            $reportData = json_decode($existingReport['report_data'], true) ?: [];
            $reportData['violations'][] = $violationData;
            
            $sql = "UPDATE daily_reports SET report_data = ? WHERE id = ?";
            executeQuery($sql, [json_encode($reportData), $existingReport['id']]);
        } else {
            // Create new report
            $reportData = ['violations' => [$violationData]];
            
            $sql = "INSERT INTO daily_reports (report_date, manager_id, report_data) VALUES (?, ?, ?)";
            executeQuery($sql, [$reportDate, $managerId, json_encode($reportData)]);
        }
    }
    
    /**
     * Auto-logout users who have been logged in for > 10 hours
     * Should be run via CRON job every 15 minutes
     */
    public static function autoLogoutLongSessions() {
        $sql = "SELECT a.id, a.user_id, a.punch_in, a.attendance_date, u.full_name
                FROM attendance a
                JOIN users u ON a.user_id = u.id
                WHERE a.punch_out IS NULL 
                AND TIMESTAMPDIFF(HOUR, a.punch_in, NOW()) >= ?";
        
        $longSessions = fetchAll($sql, [self::AUTO_LOGOUT_HOURS]);
        
        foreach ($longSessions as $session) {
            // Calculate 10-hour mark
            $autoLogoutTime = date('Y-m-d H:i:s', strtotime($session['punch_in']) + (self::AUTO_LOGOUT_HOURS * 3600));
            
            // Force punch out at exactly 10 hours
            self::forcePunchOut($session['id'], $autoLogoutTime, 'Auto-logout: 10 hour limit reached', true);
            
            // Send notification to user
            $sql = "INSERT INTO notifications (user_id, type, title, message) 
                    VALUES (?, 'auto_logout', 'Auto Logout', 'You were automatically logged out after 10 hours of active session.')";
            executeQuery($sql, [$session['user_id']]);
            
            error_log("Auto-logged out user {$session['user_id']} - Session started at {$session['punch_in']}");
        }
        
        return count($longSessions);
    }
    
    /**
     * Force punch out (used for auto-logout and midnight crossing)
     * 
     * @param int $attendanceId Attendance record ID
     * @param string $punchOutTime Specific punch out time
     * @param string $notes Notes to add
     * @param bool $autoLoggedOut Flag as auto-logged out
     */
    private static function forcePunchOut($attendanceId, $punchOutTime, $notes = null, $autoLoggedOut = false) {
        $attendance = fetchOne("SELECT * FROM attendance WHERE id = ?", [$attendanceId]);
        
        if (!$attendance) return;
        
        $punchInTime = strtotime($attendance['punch_in']);
        $punchOutTimestamp = strtotime($punchOutTime);
        $totalHours = ($punchOutTimestamp - $punchInTime) / 3600;
        $status = self::calculateStatus($totalHours);
        
        $sql = "UPDATE attendance 
                SET punch_out = ?, 
                    total_hours = ?,
                    status = ?,
                    auto_logged_out = ?,
                    notes = ?
                WHERE id = ?";
        
        executeQuery($sql, [
            $punchOutTime,
            round($totalHours, 2),
            $status,
            $autoLoggedOut ? 1 : 0,
            $notes,
            $attendanceId
        ]);
        
        logAudit('force_punch_out', 'attendance', $attendanceId, null, [
            'punch_out' => $punchOutTime,
            'total_hours' => round($totalHours, 2),
            'status' => $status,
            'notes' => $notes
        ]);
    }
    
    /**
     * Get today's attendance for a user
     * 
     * @param int $userId User ID
     * @return array|false Attendance record or false
     */
    public static function getTodayAttendance($userId) {
        $today = date('Y-m-d');
        $sql = "SELECT * FROM attendance WHERE user_id = ? AND attendance_date = ?";
        return fetchOne($sql, [$userId, $today]);
    }
    
    /**
     * Get active break for attendance record
     * 
     * @param int $attendanceId Attendance record ID
     * @return array|false Active break or false
     */
    private static function getActiveBreak($attendanceId) {
        $sql = "SELECT * FROM breaks WHERE attendance_id = ? AND break_end IS NULL";
        return fetchOne($sql, [$attendanceId]);
    }
    
    /**
     * Get all breaks for an attendance record
     * 
     * @param int $attendanceId Attendance record ID
     * @return array Array of breaks
     */
    public static function getBreaks($attendanceId) {
        $sql = "SELECT * FROM breaks WHERE attendance_id = ? ORDER BY break_start ASC";
        return fetchAll($sql, [$attendanceId]);
    }
    
    /**
     * Get attendance history for a user
     * 
     * @param int $userId User ID
     * @param int $limit Number of records to fetch
     * @param int $offset Offset for pagination
     * @return array Array of attendance records
     */
    public static function getUserAttendanceHistory($userId, $limit = 30, $offset = 0) {
        $sql = "SELECT a.*, 
                       COALESCE(SUM(b.duration_minutes), 0) as total_break_minutes
                FROM attendance a
                LEFT JOIN breaks b ON a.id = b.attendance_id AND b.break_end IS NOT NULL
                WHERE a.user_id = ?
                GROUP BY a.id
                ORDER BY a.attendance_date DESC, a.punch_in DESC
                LIMIT ? OFFSET ?";
        
        return fetchAll($sql, [$userId, $limit, $offset]);
    }
    
    /**
     * Get monthly attendance summary
     * 
     * @param int $userId User ID
     * @param int $month Month (1-12)
     * @param int $year Year
     * @return array Summary statistics
     */
    public static function getMonthlyAttendanceSummary($userId, $month, $year) {
        $sql = "SELECT 
                    COUNT(*) as total_days,
                    SUM(CASE WHEN status = 'full_day' THEN 1 ELSE 0 END) as full_days,
                    SUM(CASE WHEN status = 'short_day' THEN 1 ELSE 0 END) as short_days,
                    SUM(CASE WHEN status = 'half_day' THEN 1 ELSE 0 END) as half_days,
                    SUM(CASE WHEN auto_logged_out = 1 THEN 1 ELSE 0 END) as auto_logouts,
                    COALESCE(SUM(total_hours), 0) as total_hours_worked,
                    COALESCE(AVG(total_hours), 0) as avg_hours_per_day
                FROM attendance
                WHERE user_id = ? 
                AND MONTH(attendance_date) = ? 
                AND YEAR(attendance_date) = ?
                AND punch_out IS NOT NULL";
        
        return fetchOne($sql, [$userId, $month, $year]);
    }
}
