<?php
header('Content-Type: application/json');

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../../Model/UserModel.php';

try {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);

    // Validate input
    if (!isset($input['current_password']) || !isset($input['new_password'])) {
        throw new Exception('Données manquantes');
    }

    $currentPassword = $input['current_password'];
    $newPassword = $input['new_password'];

    // Validate password length
    if (strlen($newPassword) < 8) {
        throw new Exception('Le nouveau mot de passe doit contenir au moins 8 caractères');
    }

    // Get current user ID from session (default to 1 for testing)
    $userId = $_SESSION['id_student'] ?? 1;

    $userModel = new UserModel();

    // Verify current password
    if (!$userModel->verifyPassword($userId, $currentPassword)) {
        throw new Exception('Le mot de passe actuel est incorrect');
    }

    // Hash new password
    $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);

    // Update password
    if (!$userModel->updatePassword($userId, $newPasswordHash)) {
        throw new Exception('Erreur lors de la mise à jour du mot de passe');
    }

    echo json_encode([
        'success' => true,
        'message' => 'Mot de passe modifié avec succès'
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
