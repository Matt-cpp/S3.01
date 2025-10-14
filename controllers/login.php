<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../Model/database.php';

// Traitement du formulaire de connexion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email']) && isset($_POST['password'])) {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $errors = [];
    
    // Validation basique
    if (empty($email)) {
        $errors[] = "L'email est requis.";
    }
    if (empty($password)) {
        $errors[] = "Le mot de passe est requis.";
    }
    
    // Si pas d'erreurs, tenter la connexion
    if (empty($errors)) {
        try {
            $db = getDatabase();
            
            // Test de la connexion d'abord
            if (!$db->testConnection()) {
                $errors[] = "Impossible de se connecter à la base de données.";
            } else {
                $query = "SELECT id, identifier, email, password_hash, first_name, last_name, role::text as role
                        FROM users WHERE email = :email";
                
                $user = $db->selectOne($query, [':email' => $email]);
                
                if ($user && password_verify($password, $user['password_hash'])) {
                    // Connexion réussie
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_identifier'] = $user['identifier'];
                    $_SESSION['user_email'] = $user['email'];
                    $_SESSION['user_first_name'] = $user['first_name'];
                    $_SESSION['user_last_name'] = $user['last_name'];
                    $_SESSION['user_role'] = $user['role'];
                    
                    // Redirection vers la page principale
                    if ($user['role'] === 'student') {
                        header("Location: ../View/templates/historique.php");
                    } else {
                        header("Location: ../View/templates/admin_dashboard.php");
                    }
                    exit;
                } else {
                    $errors[] = "Email ou mot de passe incorrect.";
                }
            }
        } catch (Exception $e) {
            printf("adresse mail ou mot de passe incorrecte");
        }   
    }
    
    // En cas d'erreur, sauvegarder pour affichage
    $_SESSION['login_errors'] = $errors;
    $_SESSION['form_data'] = $_POST;
    header("Location: ../View/templates/login.php");
    exit;
}

// Fonctions utilitaires
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function getCurrentUser() {
    if (isLoggedIn()) {
        return [
            'id' => $_SESSION['user_id'],
            'identifier' => $_SESSION['user_identifier'],
            'email' => $_SESSION['user_email'],
            'first_name' => $_SESSION['user_first_name'],
            'last_name' => $_SESSION['user_last_name'],
            'role' => $_SESSION['user_role']
        ];
    }
    return null;
}

function logout() {
    session_unset();
    session_destroy();
    header("Location: ../View/templates/login.php");
    exit;
}
