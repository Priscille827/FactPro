<?php
session_start();
require_once '../config/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit;
}

// Récupérer l'entreprise de l'utilisateur
$entreprise_stmt = $pdo->prepare("SELECT id FROM entreprise WHERE user_id = ?");
$entreprise_stmt->execute([$_SESSION['user_id']]);
$entreprise = $entreprise_stmt->fetch();

if (!$entreprise) {
    echo json_encode(['success' => false, 'message' => 'Aucune entreprise associée']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

// Validation des données
if (empty($data['client_id']) || empty($data['items'])) {
    echo json_encode(['success' => false, 'message' => 'Données manquantes']);
    exit;
}

try {
    $pdo->beginTransaction();
    
    // Vérifier que le client appartient bien à l'entreprise
    $client_check = $pdo->prepare("SELECT id FROM client WHERE id = ? AND entreprise_id = ?");
    $client_check->execute([$data['client_id'], $entreprise['id']]);
    
    if (!$client_check->fetch()) {
        throw new Exception("Client non autorisé");
    }
      // Génération du numéro de facture si non fourni
      $numero = $data['numero'] ?? 'PRO-' . date('Ymd') . '-' . str_pad(mt_rand(1, 999), 3, '0', STR_PAD_LEFT);
    // Enregistrement de la facture
    $stmt = $pdo->prepare("
        INSERT INTO facture_pro (numero, date_facture, client_id, entreprise_id, notes, is_draft, created_at) 
        VALUES (?, ?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([
        $data['numero'] ?? 'FAC-'.date('Ymd-His'),
        $data['date_facture'] ?? date('Y-m-d'),
        $data['client_id'],
        $entreprise['id'], // Utiliser l'ID de l'entreprise de la session
        $data['notes'] ?? '',
        $data['is_draft'] ? 1 : 0
    ]);
    $factureId = $pdo->lastInsertId();
    
    // Enregistrement des lignes de facture
    foreach ($data['items'] as $item) {
        // Vérifier que le produit appartient à l'entreprise (s'il n'est pas nouveau)
        if (is_numeric($item['id'])) {
            $produit_check = $pdo->prepare("SELECT id FROM produit WHERE id = ? AND entreprise_id = ?");
            $produit_check->execute([$item['id'], $entreprise['id']]);
            
            if (!$produit_check->fetch()) {
                throw new Exception("Produit non autorisé");
            }
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO item_facture_pro 
            (facture_pro_id, produit_id, designation, quantite, prix_unitaire, entreprise_id) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $factureId,
            is_numeric($item['id']) ? $item['id'] : null,
            $item['designation'],
            $item['quantite'],
            $item['prix_unitaire'],
            $entreprise['id']
            // $item['tva'] ?? $entreprise['tva'] // Utiliser le taux de TVA de l'entreprise par défaut
        ]);
    }
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true, 
        'invoice_id' => $factureId,
        'message' => $data['is_draft'] ? 'Brouillon enregistré' : 'Facture validée'
    ]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode([
        'success' => false, 
        'message' => 'Erreur: ' . $e->getMessage()
    ]);
}