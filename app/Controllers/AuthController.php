<?php
/**
 * VKS ATTENDANCE SYSTEM - AUTH CONTROLLER
 * 
 * Handles authentication, login, logout with geolocation capture
 * 
 * @package VKS_Attendance
 * @version 1.0
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../Models/User.php';

class AuthController {
    
    /**
     * Show login page
     */
    public function login() {
        // Redirect if already logged in
        if (isLoggedIn()) {
            $this->redirectToDashboard();
            return;
        }
        
        require_once __DIR__ . '/../Views/auth/login.php';
    }
    
    /**
     * Process login
     */
    public function processLogin() {
        try {
            // Verify CSRF token
            if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
                echo json_encode(['success' => false, 'message' => 'Invalid request']);
                return;
            }
            
            // Get input data
            $email = sanitize($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $location = $_POST['location'] ?? null;
            
            // Validate geolocation requirement
            if (getSetting('enable_geolocation', 1) == 1 && empty($location)) {
                echo json_encode([
                    'success' => false, 
                    'message' => 'Geolocation is required. Please allow location access and try again.',
                    'require_location' => true
                ]);
                return;
            }
            
            // Parse location
            $locationData = null;
            if ($location) {
                $locationData = json_decode($location, true);
                if ($locationData && isset($locationData['latitude'], $locationData['longitude'])) {
                    $locationData = $locationData['latitude'] . ',' . $locationData['longitude'];
                }
            }
            
            // Authenticate
            $result = User::authenticate($email, $password, $locationData);
            
            if ($result['success']) {
                // Return dashboard URL based on role
                $dashboardUrl = BASE_PATH . $this->getDashboardUrl($result['user']['role']);
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Login successful',
                    'redirect' => $dashboardUrl
                ]);
            } else {
                echo json_encode($result);
            }
            
        } catch (Exception $e) {
            error_log("Login Process Error: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'System error occurred']);
        }
    }
    
    /**
     * Logout user
     */
    public function logout() {
        logAudit('logout', 'users', $_SESSION['user_id'] ?? null, null, null);
        destroySession();
        redirect('auth/login');
    }
    
    /**
     * Get dashboard URL based on role
     * 
     * @param string $role User role
     * @return string Dashboard URL
     */
    private function getDashboardUrl($role) {
        switch ($role) {
            case 'admin':
                return 'admin/dashboard';
            case 'manager':
                return 'manager/dashboard';
            default:
                return 'user/dashboard';
        }
    }
    
    /**
     * Redirect to appropriate dashboard
     */
    private function redirectToDashboard() {
        $user = getCurrentUser();
        if ($user) {
            redirect($this->getDashboardUrl($user['role']));
        }
    }
    
    /**
     * Show forgot password page
     */
    public function forgotPassword() {
        require_once __DIR__ . '/../Views/auth/forgot-password.php';
    }
    
    /**
     * Process password reset request
     */
    public function processForgotPassword() {
        try {
            if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
                echo json_encode(['success' => false, 'message' => 'Invalid request']);
                return;
            }
            
            $email = sanitize($_POST['email'] ?? '');
            
            if (!isValidEmail($email)) {
                echo json_encode(['success' => false, 'message' => 'Invalid email address']);
                return;
            }
            
            // Check if user exists
            $user = fetchOne("SELECT id FROM users WHERE email = ? AND is_active = 1", [$email]);
            
            if (!$user) {
                // Don't reveal if user exists or not (security)
                echo json_encode([
                    'success' => true, 
                    'message' => 'If an account exists with this email, you will receive reset instructions.'
                ]);
                return;
            }
            
            // Generate reset token
            $token = bin2hex(random_bytes(32));
            $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            // Store token in database
            $sql = "INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)
                    ON DUPLICATE KEY UPDATE token = ?, expires_at = ?";
            executeQuery($sql, [$user['id'], $token, $expiry, $token, $expiry]);
            
            // TODO: Send email with reset link (requires email configuration)
            // For now, log the token
            error_log("Password reset token for user {$user['id']}: {$token}");
            
            echo json_encode([
                'success' => true,
                'message' => 'If an account exists with this email, you will receive reset instructions.',
                'debug_token' => $token  // Remove in production
            ]);
            
        } catch (Exception $e) {
            error_log("Forgot Password Error: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'System error occurred']);
        }
    }
    
    /**
     * Check authentication status (API endpoint for PWA)
     */
    public function checkAuth() {
        if (isLoggedIn()) {
            echo json_encode([
                'authenticated' => true,
                'user' => getCurrentUser()
            ]);
        } else {
            echo json_encode(['authenticated' => false]);
        }
    }
    
    /**
     * Get current user info (API endpoint)
     */
    public function getCurrentUser() {
        if (!isLoggedIn()) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized']);
            return;
        }
        
        echo json_encode(['user' => getCurrentUser()]);
    }
}

// Handle direct requests
if (php_sapi_name() !== 'cli') {
    $controller = new AuthController();
    $action = $_GET['action'] ?? 'login';
    
    // Route to appropriate method
    if (method_exists($controller, $action)) {
        $controller->$action();
    } else {
        $controller->login();
    }
}
