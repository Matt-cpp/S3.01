<?php
/**
 * Redirect to the user's dashboard if already authenticated
 */
function redirectIfAuthenticated()
{
    if (isset($_SESSION['user_id']) && isset($_SESSION['user_role'])) {
        redirectToDashboard($_SESSION['user_role']);
        exit;
    }
}
/**
 * Authentication Guard
 * Include this file at the top of any page that requires authentication
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/login.php';

/**
 * Require authentication - redirect to login if not logged in
 */
function requireAuth()
{
    if (!isLoggedIn()) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        header("Location: /View/templates/login.php");
        exit;
    }
    return getCurrentUser();
}

/**
 * Require specific role - redirect to appropriate page if unauthorized
 */
function requireRole($allowedRoles)
{
    $user = requireAuth();

    // Convert single role to array
    if (!is_array($allowedRoles)) {
        $allowedRoles = [$allowedRoles];
    }

    // Check if user has required role
    if (!in_array($user['role'], $allowedRoles)) {
        // Redirect to appropriate dashboard based on user's actual role
        redirectToDashboard($user['role']);
        exit;
    }

    return $user;
}

/**
 * Redirect user to their appropriate dashboard
 */
function redirectToDashboard($role)
{
    switch ($role) {
        case 'student':
            header("Location: /View/templates/student_home_page.php");
            break;
        case 'teacher':
            header("Location: /View/templates/teacher_home.php");
            break;
        case 'academic_manager':
            header("Location: /View/templates/academic_manager_home.php");
            break;
        case 'secretary':
            header("Location: /View/templates/secretary_home.php");
            break;
        default:
            header("Location: /View/templates/login.php");
    }
}

/**
 * Get user's home page URL based on role
 */
function getUserHomePage($role)
{
    switch ($role) {
        case 'student':
            return '/View/templates/student_home_page.php';
        case 'teacher':
            return '/View/templates/teacher_home.php';
        case 'academic_manager':
            return '/View/templates/academic_manager_home.php';
        case 'secretary':
            return '/View/templates/secretary_home.php';
        default:
            return '/View/templates/login.php';
    }
}
