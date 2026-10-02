<?php
session_start();
require_once '../config/db.php';
header('Content-Type: application/json'); // Ajout de l'en-tête JSON

// Vérification de la méthode HTTP
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    die(json_encode(['success' => false, 'message' => 'Méthode non autorisée']));
}

// Vérification CSRF token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    http_response_code(403); // Forbidden
    die(json_encode(['success' => false, 'message' => 'Token CSRF invalide']));
}

// Vérification de l'autorisation
if (!isset($_SESSION['entreprise_id']) || !isset($_SESSION['user_id'])) {
    http_response_code(401); // Unauthorized
    die(json_encode(['success' => false, 'message' => 'Non autorisé']));
}

// Validation des données
$id = filter_input(INPUT_POST, 'produit_id', FILTER_VALIDATE_INT);
$designation = trim($_POST['designation'] ?? '');
$prix_unitaire = filter_input(INPUT_POST, 'prix_unitaire', FILTER_VALIDATE_FLOAT);

if (!$id || $id <= 0) {
    http_response_code(400); // Bad Request
    die(json_encode(['success' => false, 'message' => 'ID produit invalide']));
}

if (empty($designation)) {
    http_response_code(400);
    die(json_encode(['success' => false, 'message' => 'La désignation est obligatoire']));
}

if ($prix_unitaire === false || $prix_unitaire <= 0) {
    http_response_code(400);
    die(json_encode(['success' => false, 'message' => 'Prix invalide (doit être > 0)']));
}

try {
    $pdo->beginTransaction();

    // 1. Vérifier que le produit appartient à l'entreprise
    $check_stmt = $pdo->prepare("SELECT id FROM produit WHERE id = ? AND entreprise_id = ?");
    $check_stmt->execute([$id, $_SESSION['entreprise_id']]);
    
    if (!$check_stmt->fetch()) {
        http_response_code(404); // Not Found
        throw new Exception('Produit non trouvé');
    }

    // 2. Vérifier qu'un autre produit n'a pas déjà ce nom
    $check_unique = $pdo->prepare("SELECT id FROM produit 
                                  WHERE entreprise_id = ? 
                                  AND designation = ? 
                                  AND id != ?");
    $check_unique->execute([$_SESSION['entreprise_id'], $designation, $id]);
    
    if ($check_unique->fetch()) {
        http_response_code(409); // Conflict
        throw new Exception('Un autre produit avec ce nom existe déjà');
    }

    // 3. Mettre à jour le produit
    $stmt = $pdo->prepare("UPDATE produit 
                          SET designation = ?, prix_unitaire = ? 
                          WHERE id = ? AND entreprise_id = ?");
    $stmt->execute([$designation, $prix_unitaire, $id, $_SESSION['entreprise_id']]);

    $pdo->commit();

    // Retourner les données mises à jour
    echo json_encode([
        'success' => true,
        'produit' => [
            'id' => $id,
            'designation' => $designation,
            'prix_unitaire' => $prix_unitaire
        ],
        'message' => 'Produit mis à jour avec succès'
    ]);

} catch (PDOException $e) {
    $pdo->rollBack();
    error_log('Erreur update_produit: ' . $e->getMessage());
    http_response_code(500);
    die(json_encode(['success' => false, 'message' => 'Erreur technique']));
} catch (Exception $e) {
    $pdo->rollBack();
    die(json_encode(['success' => false, 'message' => $e->getMessage()]));
}