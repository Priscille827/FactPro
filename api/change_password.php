<?php
session_start();
require_once '../config/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit;
}

$response = ['success' => false, 'message' => ''];

try {
    // Validation
    if (empty($_POST['current_password'])) {
        throw new Exception("L'ancien mot de passe est requis");
    }
    if (empty($_POST['new_password'])) {
        throw new Exception("Le nouveau mot de passe est requis");
    }
    if ($_POST['new_password'] !== $_POST['confirm_password']) {
        throw new Exception("Les mots de passe ne correspondent pas");
    }

    // Vérifier l'ancien mot de passe
    $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    if (!password_verify($_POST['current_password'], $user['password'])) {
        throw new Exception("L'ancien mot de passe est incorrect");
    }

    // Mettre à jour le mot de passe
    $newPassword = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
    $success = $stmt->execute([$newPassword, $_SESSION['user_id']]);

    if ($success) {
        $response = [
            'success' => true,
            'message' => 'Mot de passe changé avec succès'
        ];
    }
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
?>