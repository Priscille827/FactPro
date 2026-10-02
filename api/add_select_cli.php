<?php
session_start();
require_once '../config/db.php';

header('Content-Type: application/json');

// Vérification CSRF
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    die(json_encode(['success' => false, 'message' => 'Token invalide']));
}

// Validation des données
$required = ['nom', 'entreprise_id'];
foreach ($required as $field) {
    if (empty($_POST[$field])) {
        die(json_encode(['success' => false, 'message' => "Le champ $field est requis"]));
    }
}

try {
    $pdo->beginTransaction();
    
    $stmt = $pdo->prepare("INSERT INTO client (nom, adresse, telephone, email, entreprise_id) 
                          VALUES (:nom, :adresse, :telephone, :email, :entreprise_id)");
    
    $stmt->execute([
        ':nom' => $_POST['nom'],
        ':adresse' => $_POST['adresse'] ?? null,
        ':telephone' => $_POST['telephone'] ?? null,
        ':email' => $_POST['email'] ?? null,
        ':entreprise_id' => $_POST['entreprise_id']
    ]);
    
    $clientId = $pdo->lastInsertId();
    
    // Récupération des données complètes
    $stmt = $pdo->prepare("SELECT * FROM client WHERE id = ?");
    $stmt->execute([$clientId]);
    $client = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'client' => $client
    ]);
    
} catch (PDOException $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Erreur DB: ' . $e->getMessage()]);
}
?>