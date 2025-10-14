<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/login.php';

// Vérifier si l'utilisateur est connecté
function requireAuth() {
    if (!isLoggedIn()) {
        header("Location: ../View/templates/login.php");
        exit;
    }
    return getCurrentUser();
}