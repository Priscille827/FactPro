<?php
session_start();
require_once '../config/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !isset($_POST['id'])) {
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit;
}

try {
    $pdo->beginTransaction();
    
    // Vérifier que la facture appartient à l'entreprise de l'utilisateur
    $check = $pdo->prepare("
        SELECT f.id FROM facture_pro f
        JOIN entreprise e ON f.entreprise_id = e.id
        WHERE f.id = ? AND e.user_id = ?
    ");
    $check->execute([$_POST['id'], $_SESSION['user_id']]);
    
    if (!$check->fetch()) {
        throw new Exception("Facture non trouvée ou non autorisée");
    }
    
    // Supprimer les items de la facture
    $deleteItems = $pdo->prepare("DELETE FROM item_facture_pro WHERE facture_pro_id = ?");
    $deleteItems->execute([$_POST['id']]);
    
    // Supprimer la facture
    $deleteInvoice = $pdo->prepare("DELETE FROM facture_pro WHERE id = ?");
    $deleteInvoice->execute([$_POST['id']]);
    
    $pdo->commit();
    
    echo json_encode(['success' => true, 'message' => 'Facture supprimée']);
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}