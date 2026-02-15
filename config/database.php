<?php
/**
 * VKS ATTENDANCE SYSTEM - DATABASE CONFIGURATION
 * 
 * Secure PDO connection with error handling
 * Instructions: Update the credentials below with your Hostinger database details
 * 
 * @package VKS_Attendance
 * @version 1.0
 */

// ============================================================
// DATABASE CREDENTIALS (UPDATE THESE)
// ============================================================
define('DB_HOST', 'localhost');           // Usually 'localhost' on Hostinger
define('DB_NAME', 'your_database_name');  // Your database name
define('DB_USER', 'your_database_user');  // Your database username
define('DB_PASS', 'your_database_pass');  // Your database password
define('DB_CHARSET', 'utf8mb4');

// ============================================================
// APPLICATION CONFIGURATION
// ============================================================
define('BASE_PATH', '/vks/');  // Subfolder path (IMPORTANT: Keep the trailing slash)
define('TIMEZONE', 'Asia/Kolkata');  // Default timezone
define('SESSION_LIFETIME', 28800);  // 8 hours in seconds

// Set timezone
date_default_timezone_set(TIMEZONE);

// ============================================================
// DATABASE CONNECTION CLASS
// ============================================================
class Database {
    private static $instance = null;
    private $connection;
    
    /**
     * Private constructor to prevent direct instantiation
     */
    private function __construct() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_PERSISTENT         => true,  // Connection pooling
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET
            ];
            
            $this->connection = new PDO($dsn, DB_USER, DB_PASS, $options);
            
        } catch (PDOException $e) {
            // Log error to file (don't expose to users)
            error_log("Database Connection Error: " . $e->getMessage());
            
            // Show user-friendly error
            die("Unable to connect to database. Please check your configuration or contact support.");
        }
    }
    
    /**
     * Get singleton instance of database connection
     * 
     * @return Database
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Get PDO connection object
     * 
     * @return PDO
     */
    public function getConnection() {
        return $this->connection;
    }
    
    /**
     * Prevent cloning of the instance
     */
    private function __clone() {}
    
    /**
     * Prevent unserializing of the instance
     */
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
}

// ============================================================
// HELPER FUNCTIONS
// ============================================================

/**
 * Get database connection instance
 * 
 * @return PDO
 */
function getDB() {
    return Database::getInstance()->getConnection();
}

/**
 * Execute a prepared statement safely
 * 
 * @param string $sql SQL query with placeholders
 * @param array $params Parameters to bind
 * @return PDOStatement|false
 */
function executeQuery($sql, $params = []) {
    try {
        $db = getDB();
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    } catch (PDOException $e) {
        error_log("Query Error: " . $e->getMessage() . " | SQL: " . $sql);
        return false;
    }
}

/**
 * Fetch single row from database
 * 
 * @param string $sql SQL query
 * @param array $params Parameters to bind
 * @return array|false
 */
function fetchOne($sql, $params = []) {
    $stmt = executeQuery($sql, $params);
    return $stmt ? $stmt->fetch() : false;
}

/**
 * Fetch all rows from database
 * 
 * @param string $sql SQL query
 * @param array $params Parameters to bind
 * @return array
 */
function fetchAll($sql, $params = []) {
    $stmt = executeQuery($sql, $params);
    return $stmt ? $stmt->fetchAll() : [];
}

/**
 * Get last inserted ID
 * 
 * @return string
 */
function lastInsertId() {
    return getDB()->lastInsertId();
}

/**
 * Begin database transaction
 */
function beginTransaction() {
    getDB()->beginTransaction();
}

/**
 * Commit database transaction
 */
function commit() {
    getDB()->commit();
}

/**
 * Rollback database transaction
 */
function rollback() {
    getDB()->rollBack();
}

// ============================================================
// SECURITY FUNCTIONS
// ============================================================

/**
 * Generate CSRF token
 * 
 * @return string
 */
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 * 
 * @param string $token Token to verify
 * @return bool
 */
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Sanitize user input
 * 
 * @param mixed $data Data to sanitize
 * @return mixed
 */
function sanitize($data) {
    if (is_array($data)) {
        return array_map('sanitize', $data);
    }
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

/**
 * Validate email address
 * 
 * @param string $email Email to validate
 * @return bool
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Hash password securely
 * 
 * @param string $password Plain text password
 * @return string
 */
function hashPassword($password) {
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
}

/**
 * Verify password against hash
 * 
 * @param string $password Plain text password
 * @param string $hash Hashed password
 * @return bool
 */
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

// ============================================================
// UTILITY FUNCTIONS
// ============================================================

/**
 * Redirect to a URL
 * 
 * @param string $path Path to redirect to
 */
function redirect($path) {
    header("Location: " . BASE_PATH . ltrim($path, '/'));
    exit;
}

/**
 * Get current user from session
 * 
 * @return array|null
 */
function getCurrentUser() {
    return $_SESSION['user'] ?? null;
}

/**
 * Check if user is logged in
 * 
 * @return bool
 */
function isLoggedIn() {
    return isset($_SESSION['user']) && isset($_SESSION['user_id']);
}

/**
 * Check if user has specific role
 * 
 * @param string|array $roles Role(s) to check
 * @return bool
 */
function hasRole($roles) {
    if (!isLoggedIn()) return false;
    
    $userRole = $_SESSION['user']['role'] ?? null;
    
    if (is_array($roles)) {
        return in_array($userRole, $roles);
    }
    
    return $userRole === $roles;
}

/**
 * Format date for display
 * 
 * @param string $date Date string
 * @param string $format Format string
 * @return string
 */
function formatDate($date, $format = 'd M Y') {
    return date($format, strtotime($date));
}

/**
 * Format datetime for display
 * 
 * @param string $datetime Datetime string
 * @param string $format Format string
 * @return string
 */
function formatDateTime($datetime, $format = 'd M Y h:i A') {
    return date($format, strtotime($datetime));
}

/**
 * Calculate time difference in hours
 * 
 * @param string $start Start datetime
 * @param string $end End datetime
 * @return float
 */
function calculateHours($start, $end) {
    $startTime = strtotime($start);
    $endTime = strtotime($end);
    return round(($endTime - $startTime) / 3600, 2);
}

/**
 * Get system setting value
 * 
 * @param string $key Setting key
 * @param mixed $default Default value if not found
 * @return mixed
 */
function getSetting($key, $default = null) {
    $sql = "SELECT setting_value FROM system_settings WHERE setting_key = ?";
    $result = fetchOne($sql, [$key]);
    return $result ? $result['setting_value'] : $default;
}

/**
 * Log action to audit trail
 * 
 * @param string $action Action performed
 * @param string $table Table affected
 * @param int $recordId Record ID
 * @param array $oldValues Old values
 * @param array $newValues New values
 */
function logAudit($action, $table = null, $recordId = null, $oldValues = null, $newValues = null) {
    $userId = $_SESSION['user_id'] ?? null;
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
    
    $sql = "INSERT INTO audit_logs (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    
    executeQuery($sql, [
        $userId,
        $action,
        $table,
        $recordId,
        $oldValues ? json_encode($oldValues) : null,
        $newValues ? json_encode($newValues) : null,
        $ip,
        $userAgent
    ]);
}

// ============================================================
// SESSION MANAGEMENT
// ============================================================

/**
 * Start secure session
 */
function startSession() {
    if (session_status() === PHP_SESSION_NONE) {
        ini_set('session.cookie_httponly', 1);
        ini_set('session.cookie_secure', 1);
        ini_set('session.cookie_samesite', 'Strict');
        ini_set('session.gc_maxlifetime', SESSION_LIFETIME);
        
        session_start();
        
        // Regenerate session ID periodically
        if (!isset($_SESSION['created'])) {
            $_SESSION['created'] = time();
        } else if (time() - $_SESSION['created'] > 1800) {  // 30 minutes
            session_regenerate_id(true);
            $_SESSION['created'] = time();
        }
    }
}

/**
 * Destroy session and logout
 */
function destroySession() {
    if (session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION = [];
        session_destroy();
        session_write_close();
        setcookie(session_name(), '', time() - 3600, '/');
    }
}

// Initialize session
startSession();
