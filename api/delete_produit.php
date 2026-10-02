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
    if (!isset($_GET['id'])) {
        throw new Exception("ID du produit manquant");
    }

    $produitId = (int)$_GET['id'];

    // Vérifier si le produit est utilisé dans des factures
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM item_facture_pro WHERE produit_id = ?");
    $stmt->execute([$produitId]);
    $count = $stmt->fetchColumn();

    if ($count > 0) {
        throw new Exception("Ce produit est utilisé dans des factures et ne peut pas être supprimé");
    }

    // Supprimer le produit
    $stmt = $pdo->prepare("DELETE FROM produit WHERE id = ?");
    $success = $stmt->execute([$produitId]);

    if ($success) {
        $response = ['success' => true, 'message' => 'Produit supprimé'];
    } else {
        throw new Exception("Erreur lors de la suppression");
    }
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
?>