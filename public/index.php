<?php
/**
 * VKS ATTENDANCE SYSTEM - MAIN ROUTER
 * 
 * MVC Router for handling all application requests
 * 
 * @package VKS_Attendance
 * @version 1.0
 */

// Load configuration and database
require_once __DIR__ . '/../../config/database.php';

// Error handling
error_reporting(E_ALL);
ini_set('display_errors', 0);  // Don't display errors to users
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../logs/php_errors.log');

// Parse URL
$url = $_GET['url'] ?? 'auth/login';
$url = rtrim($url, '/');
$url = filter_var($url, FILTER_SANITIZE_URL);
$urlParts = explode('/', $url);

// Get controller, action, and parameters
$controllerName = ucfirst($urlParts[0] ?? 'auth') . 'Controller';
$action = $urlParts[1] ?? 'index';
$params = array_slice($urlParts, 2);

// Controller file path
$controllerFile = __DIR__ . '/../../app/Controllers/' . $controllerName . '.php';

// Check if controller exists
if (!file_exists($controllerFile)) {
    // Default to auth controller
    $controllerName = 'AuthController';
    $controllerFile = __DIR__ . '/../../app/Controllers/AuthController.php';
    $action = 'login';
}

// Load controller
require_once $controllerFile;

// Instantiate controller
if (class_exists($controllerName)) {
    $controller = new $controllerName();
    
    // Check if action exists
    if (method_exists($controller, $action)) {
        // Call action with parameters
        call_user_func_array([$controller, $action], $params);
    } else {
        // Default to index or show 404
        if (method_exists($controller, 'index')) {
            $controller->index();
        } else {
            http_response_code(404);
            echo "404 - Page Not Found";
        }
    }
} else {
    http_response_code(500);
    echo "500 - Server Error";
}
