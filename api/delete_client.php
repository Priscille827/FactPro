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
        throw new Exception("ID client manquant");
    }

    $clientId = (int)$_GET['id'];

    // Vérifier si le client est utilisé dans des factures
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM facture_pro WHERE client_id = ?");
    $stmt->execute([$clientId]);
    $count = $stmt->fetchColumn();

    if ($count > 0) {
        throw new Exception("Ce client est associé à des factures et ne peut pas être supprimé");
    }

    // Supprimer le client
    $stmt = $pdo->prepare("DELETE FROM client WHERE id = ?");
    $success = $stmt->execute([$clientId]);

    if ($success) {
        $response = ['success' => true, 'message' => 'Client supprimé'];
    } else {
        throw new Exception("Erreur lors de la suppression");
    }
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
?>