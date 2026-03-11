<?php

declare(strict_types=1);

/**
 * File: login_presenter.php
 *
 * Login controller — handles user authentication.
 * Processes the login form, verifies credentials in the database,
 * creates the user session and redirects to the appropriate page based on role.
 * Also provides utility functions (isLoggedIn, getCurrentUser, logout).
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../Model/database.php';

// Login form processing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email']) && isset($_POST['password'])) {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $errors = [];

    // Basic validation
    if (empty($email)) {
        $errors[] = "L'email est requis.";
    }
    if (empty($password)) {
        $errors[] = 'Le mot de passe est requis.';
    }

    // If no errors, attempt login
    if (empty($errors)) {
        try {
            $db = getDatabase();

            // Test the connection first
            if (!$db->testConnection()) {
                $errors[] = 'Impossible de se connecter à la base de données.';
            } else {
                $query = "SELECT id, email, password_hash, first_name, last_name, role::text as role
                        FROM users WHERE email = :email";

                $user = $db->selectOne($query, [':email' => strtolower($email)]);

                if ($user && password_verify($password, $user['password_hash'])) {
                    // Login successful
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_email'] = $user['email'];
                    $_SESSION['user_first_name'] = $user['first_name'];
                    $_SESSION['user_last_name'] = $user['last_name'];
                    $_SESSION['user_role'] = $user['role'];

                    // Redirect to the main page based on role
                    $redirectUrl = $_SESSION['redirect_after_login'] ?? null;
                    unset($_SESSION['redirect_after_login']);

                    if ($redirectUrl) {
                        header('Location: ' . $redirectUrl);
                    } else {
                        // Default redirect based on role
                        if ($user['role'] === 'student') {
                            header('Location: ../../View/templates/student/home.php');
                        } elseif ($user['role'] === 'academic_manager') {
                            header('Location: ../../View/templates/academic_manager/home.php');
                        } elseif ($user['role'] === 'teacher') {
                            header('Location: ../../View/templates/teacher/home.php');
                        } elseif ($user['role'] === 'secretary') {
                            header('Location: ../../View/templates/secretary/home.php');
                        }
                    }
                    exit;
                } else {
                    $errors[] = 'Email ou mot de passe incorrect.';
                }
            }
        } catch (Exception $e) {
            $errors[] = 'Email ou mot de passe incorrect.';
        }
    }

    // On error, save for display
    $_SESSION['login_errors'] = $errors;
    $_SESSION['form_data'] = $_POST;
    header('Location: ../../View/templates/shared/login.php');
    exit;
}

// Utility functions
function isLoggedIn(): bool
{
    return isset($_SESSION['user_id']);
}

function getCurrentUser(): ?array
{
    if (isLoggedIn()) {
        return [
            'id' => $_SESSION['user_id'],
            'email' => $_SESSION['user_email'],
            'first_name' => $_SESSION['user_first_name'],
            'last_name' => $_SESSION['user_last_name'],
            'role' => $_SESSION['user_role']
        ];
    }
    return null;
}

function logout(): void
{
    session_unset();
    session_destroy();
    header('Location: ../../View/templates/shared/login.php');
    exit;
}
