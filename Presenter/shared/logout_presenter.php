<?php

declare(strict_types=1);

/**
 * File: logout_presenter.php
 *
 * Logout controller — handles user logout.
 * Destroys the current session, removes session cookies and redirects to the login page.
 */

session_start();

// Remove all session variables
$_SESSION = array();

// Destroy the session cookie if it exists
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

// Destroy the session
session_destroy();

// Redirect to the login page
header("Location: ../../View/templates/shared/login.php");
exit;
