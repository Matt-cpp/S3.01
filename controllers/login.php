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
                $query = "SELECT id, email, password_hash, first_name, last_name, role::text as role
                        FROM users WHERE email = :email";
                
                $user = $db->selectOne($query, [':email' => $email]);
                
                // DEBUG - À retirer après
                error_log("Email recherché: " . $email);
                error_log("Utilisateur trouvé: " . ($user ? "OUI" : "NON"));
                if ($user) {
                    error_log("Hash en BDD: " . $user['password_hash']);
                    error_log("Mot de passe saisi: " . $password);
                    error_log("Vérification password: " . (password_verify($password, $user['password_hash']) ? "OK" : "ECHEC"));
                }
                
                if ($user && password_verify($password, $user['password_hash'])) {
                    // Connexion réussie
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_email'] = $user['email'];
                    $_SESSION['user_first_name'] = $user['first_name'];
                    $_SESSION['user_last_name'] = $user['last_name'];
                    $_SESSION['user_role'] = $user['role'];
                    
                    // Redirection vers la page principale
                    if ($user['role'] === 'student') {
                        header("Location: ../View/templates/student_home_page.php");
                    } elseif ($user['role'] === 'academic_manager') {
                        header("Location: ../View/templates/accueil.php");
                    } elseif ($user['role'] === 'teacher') {
                        header("Location: ../View/templates/teacher_dashboard.php");
                    }
                    exit;
                } else {
                    $errors[] = "Email ou mot de passe incorrect.";
                }
            }
        } catch (Exception $e) {
            $errors[] = "Email ou mot de passe incorrect.";
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
