<?php
session_start();
require_once '../config/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit;
}

if (empty($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'ID facture manquant']);
    exit;
}

try {
    $pdo->beginTransaction();
    
    // Supprimer les items de la facture
    $stmt = $pdo->prepare("DELETE FROM item_facture_pro WHERE facture_pro_id = ?");
    $stmt->execute([$_GET['id']]);
    
    // Supprimer la facture
    $stmt = $pdo->prepare("DELETE FROM facture_pro WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Facture supprimée'
    ]);
    
} catch (PDOException $e) {
    $pdo->rollBack();
    echo json_encode([
        'success' => false,
        'message' => 'Erreur: ' . $e->getMessage()
    ]);
}