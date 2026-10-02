<?php
session_start();
require_once '../config/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit;
}

if (!isset($_POST['id']) || !is_numeric($_POST['id'])) {
    echo json_encode(['success' => false, 'message' => 'ID facture invalide']);
    exit;
}

try {
    $pdo->beginTransaction();

    // 1. Récupérer l'entreprise de l'utilisateur
    $stmt = $pdo->prepare("SELECT id, tva, devise FROM entreprise WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $entreprise = $stmt->fetch();

    if (!$entreprise) {
        throw new Exception("Aucune entreprise trouvée pour cet utilisateur");
    }

    // 2. Récupérer la facture proforma
    $stmt = $pdo->prepare("
        SELECT f.*, c.nom as client_nom 
        FROM facture_pro f
        LEFT JOIN client c ON f.client_id = c.id
        WHERE f.id = ? AND f.entreprise_id = ?
    ");
    $stmt->execute([$_POST['id'], $entreprise['id']]);
    $proforma = $stmt->fetch();

    if (!$proforma) {
        throw new Exception("Facture proforma non trouvée");
    }

    // 3. Calculer les montants à partir des items
    $stmt = $pdo->prepare("
        SELECT SUM(quantite * prix_unitaire) as montant_ht
        FROM item_facture_pro
        WHERE facture_pro_id = ?
    ");
    $stmt->execute([$proforma['id']]);
    $montants = $stmt->fetch();
    
    $montant_ht = $montants['montant_ht'] ?? 0;
    $montant_tva = $montant_ht * ($entreprise['tva'] / 100);
    $montant_ttc = $montant_ht + $montant_tva;

    // 4. Générer le numéro de facture
    $numeroFacture = 'FAC-' . date('Ymd') . '-' . str_pad($proforma['id'], 5, '0', STR_PAD_LEFT);

    // 5. Créer la facture définitive
    $stmt = $pdo->prepare("
        INSERT INTO facture (
            numero, date_facture, date_echeance, client_id, entreprise_id,
            conditions_paiement,  montant_ht, montant_tva, montant_ttc, notes
        ) VALUES (?, ?, ?, ?, ?,  ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $numeroFacture,
        $proforma['date_facture'],
        date('Y-m-d', strtotime($proforma['date_facture'] . ' + ' . ($proforma['validite'] ?? 30) . ' days')),
        $proforma['client_id'],
        $entreprise['id'],
        $proforma['conditions'] ?? 'Paiement à 30 jours',
       
        $montant_ht,
        $montant_tva,
        $montant_ttc,
        'Convertie depuis proforma #' . $proforma['numero']
    ]);
    $factureId = $pdo->lastInsertId();

    // 6. Copier les lignes de la facture
  // 6. Copier les lignes de la facture avec entreprise_id
$stmt = $pdo->prepare("
INSERT INTO item_facture (
    facture_id, entreprise_id, produit_id, designation, quantite, prix_unitaire, 
    tva, montant_ht, montant_tva
)
SELECT ?, ?, p.id, COALESCE(p.designation, 'Produit divers'), i.quantite, i.prix_unitaire, 
       ?, 
       (i.quantite * i.prix_unitaire),
       (i.quantite * i.prix_unitaire * ? / 100)
FROM item_facture_pro i
LEFT JOIN produit p ON i.produit_id = p.id
WHERE i.facture_pro_id = ?
");
$stmt->execute([
$factureId,
$entreprise['id'],
$entreprise['tva'],
$entreprise['tva'],
$proforma['id']
]);

    // 6. Marquer la proforma comme convertie
$stmt = $pdo->prepare("
UPDATE facture_pro 
SET converted = 1
WHERE id = ?
");
$stmt->execute([$proforma['id']]);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Facture convertie avec succès',
        'facture_id' => $factureId,
        'numero_facture' => $numeroFacture
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode([
        'success' => false,
        'message' => 'Erreur: ' . $e->getMessage()
    ]);
}
?>
