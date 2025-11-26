<?php

/**
 * Fichier: auth.php
 * 
 * Contrôleur d'authentification - Gère la vérification de l'authentification des utilisateurs.
 * Fournit une fonction requireAuth() pour protéger les pages nécessitant une connexion.
 * Redirige automatiquement vers la page de connexion si l'utilisateur n'est pas authentifié.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/login.php';

// Vérifier si l'utilisateur est connecté
function requireAuth()
{
    if (!isLoggedIn()) {
        header("Location: ../View/templates/login.php");
        exit;
    }
    return getCurrentUser();
}
