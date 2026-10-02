<?php
session_start();
require_once '../config/db.php';

$data = json_decode(file_get_contents('php://input'), true);

// Vérification sécurité
if (!isset($_SESSION['entreprise_id'])) {
    die(json_encode(['success' => false, 'message' => 'Non autorisé']));
}

try {
    // 1. Mettre à jour la facture principale
    $stmt = $pdo->prepare("
        UPDATE facture_pro SET
            date_facture = ?,
            client_id = ?,
            notes = ?,
            is_draft = ?
        WHERE id = ? AND entreprise_id = ?
    ");
    $stmt->execute([
        $data['date_facture'],
        $data['client_id'],
        $data['notes'],
        $data['is_draft'],
        $data['invoice_id'],
        $_SESSION['entreprise_id']
    ]);

    // 2. Mettre à jour les items (supprimer anciens, ajouter nouveaux)
    $pdo->prepare("DELETE FROM item_facture_pro WHERE facture_pro_id = ?")
       ->execute([$data['invoice_id']]);

    foreach ($data['items'] as $item) {
        $stmt = $pdo->prepare("
            INSERT INTO item_facture_pro 
            (facture_pro_id, produit_id, designation, quantite, prix_unitaire)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $data['invoice_id'],
            $item['id'],
            $item['designation'],
            $item['quantite'],
            $item['prix_unitaire'],
           
        ]);
    }

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur BD: ' . $e->getMessage()]);
}