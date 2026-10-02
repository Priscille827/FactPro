<?php
session_start();
require_once '../config/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit;
}

try {
    // Récupérer l'entreprise de l'utilisateur connecté
    $stmt = $pdo->prepare("SELECT * FROM entreprise WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $entreprise = $stmt->fetch();

    if ($entreprise) {
        echo json_encode([
            'success' => true,
            'entreprise' => [
                'id' => $entreprise['id'],
                'nom' => $entreprise['nom'],
                'adresse' => $entreprise['adresse'],
                'telephone' => $entreprise['telephone'],
                'email' => $entreprise['email'],
                'tva' => $entreprise['tva'],
                'devise' => $entreprise['devise'],
                'logo' => $entreprise['logo'] ? $entreprise['logo'] : null
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Aucune entreprise trouvée']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur de base de données']);
}
?>