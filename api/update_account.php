<?php
require_once '../config/db.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit;
}

$response = ['success' => false, 'message' => 'Erreur inconnue'];

try {
    //Vérifier que la requête est de type POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Méthode non autorisée");
    }

    // Récupérer les données avec des valeurs par défaut
    $user_id = $_SESSION['user_id'];
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    // Validation basique
    if (empty($name) || empty($email)) {
        throw new Exception("Tous les champs obligatoires doivent être remplis");
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception("L'email n'est pas valide");
    }

    // Vérifier si l'email existe déjà pour un autre utilisateur
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $stmt->execute([$email, $user_id]);
    if ($stmt->fetch()) {
        throw new Exception("Cet email est déjà utilisé par un autre compte");
    }

    // Vérification du mot de passe si changement demandé
    if (!empty($newPassword)) {
        if (empty($currentPassword)) {
            throw new Exception("Le mot de passe actuel est requis pour effectuer des changements");
        }

        if ($newPassword !== $confirmPassword) {
            throw new Exception("Les nouveaux mots de passe ne correspondent pas");
        }

        // Vérifier le mot de passe actuel
        $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();

        if (!password_verify($currentPassword, $user['password'])) {
            throw new Exception("Le mot de passe actuel est incorrect");
        }

        // Hasher le nouveau mot de passe
        $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
    }

    // Mise à jour des informations
    if (isset($passwordHash)) {
        $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, password = ? WHERE id = ?");
        $stmt->execute([$name, $email, $passwordHash, $user_id]);
    } else {
        $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ? WHERE id = ?");
        $stmt->execute([$name, $email, $user_id]);
    }

    $response = [
        'success' => true,
        'message' => 'Informations mises à jour avec succès',
        'name' => $name,
        'email' => $email
    ];

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response);

?>