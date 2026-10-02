<?php
session_start();
require_once '../config/db.php';
header('Content-Type: application/json'); // Ajout de l'en-tête JSON

// Vérifier CSRF token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    http_response_code(403);
    die(json_encode(['success' => false, 'message' => 'Token CSRF invalide']));
}

// Vérifier que l'utilisateur a une entreprise
if (!isset($_SESSION['entreprise_id'])) {
    http_response_code(401);
    die(json_encode(['success' => false, 'message' => 'Non autorisé']));
}

// Récupérer et valider les données
$designation = trim($_POST['designation'] ?? '');
$prix_unitaire = filter_input(INPUT_POST, 'prix_unitaire', FILTER_VALIDATE_FLOAT);

if (empty($designation)) {
    http_response_code(400);
    die(json_encode(['success' => false, 'message' => 'La désignation est obligatoire']));
}

if ($prix_unitaire === false || $prix_unitaire <= 0) {
    http_response_code(400);
    die(json_encode(['success' => false, 'message' => 'Le prix doit être supérieur à 0']));
}

try {
    // Commencer une transaction
    $pdo->beginTransaction();

    // 1. Vérifier si le produit existe déjà
    $check_stmt = $pdo->prepare("SELECT id FROM produit 
                               WHERE entreprise_id = ? 
                               AND designation = ?");
    $check_stmt->execute([$_SESSION['entreprise_id'], $designation]);
    
    if ($check_stmt->fetch()) {
        $pdo->rollBack();
        http_response_code(409); // Conflict
        die(json_encode([
            'success' => false, 
            'message' => 'Un produit avec ce nom existe déjà'
        ]));
    }

    // 2. Insérer le nouveau produit
    $stmt = $pdo->prepare("INSERT INTO produit (designation, prix_unitaire, entreprise_id) 
                          VALUES (?, ?, ?)");
    $stmt->execute([$designation, $prix_unitaire, $_SESSION['entreprise_id']]);
    
    // Récupérer l'ID du nouveau produit
    $produitId = $pdo->lastInsertId();

    // Valider la transaction
    $pdo->commit();

    // Retourner les infos du nouveau produit seulement (pas besoin de toute la liste)
    echo json_encode([
        'success' => true,
        'produit' => [
            'id' => $produitId,
            'designation' => $designation,
            'prix_unitaire' => $prix_unitaire
        ],
        'message' => 'Produit ajouté avec succès'
    ]);

} catch (PDOException $e) {
    $pdo->rollBack();
    error_log('Erreur add_produit: ' . $e->getMessage());
    
    $message = 'Erreur technique';
    if (strpos($e->getMessage(), 'unique_produit') !== false) {
        $message = 'Ce produit existe déjà';
        http_response_code(409);
    } else {
        http_response_code(500);
    }
    
    die(json_encode(['success' => false, 'message' => $message]));
}