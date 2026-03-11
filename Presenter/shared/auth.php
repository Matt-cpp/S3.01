<?php

declare(strict_types=1);

/**
 * File: auth.php
 *
 * Authentication controller — verifies user authentication.
 * Provides a requireAuth() function to protect pages that need a logged-in user.
 * Automatically redirects to the login page if the user is not authenticated.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/login_presenter.php';

// Check whether the user is logged in
function requireAuth(): ?array
{
    if (!isLoggedIn()) {
        header('Location: ../View/templates/shared/login.php');
        exit;
    }
    return getCurrentUser();
}
