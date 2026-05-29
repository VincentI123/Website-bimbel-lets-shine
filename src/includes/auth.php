<?php
require_once __DIR__ . '/../config/database.php';

/**
 * Initializes the session if it hasn't been started yet.
 */
function initAuth() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

/**
 * Logs in a user after verifying their credentials.
 * Regenerates the session ID for security.
 *
 * @param string $username
 * @param string $password
 * @return array
 */
function loginUser($username, $password) {
    $db = Database::getInstance()->getConnection();
    
    $stmt = $db->prepare("SELECT * FROM users WHERE username = :username");
    $stmt->execute(['username' => $username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        // Check user status
        if ($user['status'] === 'pending') {
            return ['success' => false, 'message' => 'login_pending_approval'];
        }

        // Regenerate session ID to prevent session fixation
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        return ['success' => true, 'user' => $user];
    }
    
    return ['success' => false, 'message' => 'login_error_invalid_credentials'];
}

/**
 * Logs out the current user and destroys the session.
 */
function logoutUser() {
    initAuth();
    session_unset();
    session_destroy();
}

/**
 * Checks if a user is currently logged in.
 *
 * @return bool
 */
function isLoggedIn() {
    initAuth();
    return isset($_SESSION['user_id']);
}

/**
 * Retrieves the currently logged-in user's data from the database.
 *
 * @return mixed|null
 */
function getCurrentUser() {
    if (!isLoggedIn()) return null;
    
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("SELECT * FROM users WHERE id = :id");
    $stmt->execute(['id' => $_SESSION['user_id']]);
    return $stmt->fetch();
}

/**
 * Restricts page access to specified roles.
 * If the user is not logged in, they are redirected to the login page.
 * If the user does not have the required role, they are logged out.
 *
 * @param string|array $requiredRoles The role(s) required to access the page.
 */
function requireRole($requiredRoles) {
    initAuth();
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit();
    }

    $user = getCurrentUser();
    
    // Ensure $requiredRoles is an array for consistent checking
    if (!is_array($requiredRoles)) {
        $requiredRoles = [$requiredRoles];
    }

    // Redirect if user role is not in the allowed roles
    if (!$user || !in_array($user['role'], $requiredRoles)) {
        header('Location: logout.php'); // Or a 403 "Forbidden" page
        exit();
    }
}
