<?php
session_start();
require_once '../config/db.php';
header('Content-Type: text/html');

if (!isset($_SESSION['user_id']) || !isset($_GET['id'])) {
    die('Accès non autorisé');
}

$invoiceId = $_GET['id'];

// Récupérer les infos de la facture
$invoice = $pdo->prepare("
    SELECT f.*, c.nom as client_nom, c.adresse as client_adresse, 
           c.telephone as client_telephone, c.email as client_email,
           e.nom as entreprise_nom, e.adresse as entreprise_adresse,
           e.telephone as entreprise_telephone, e.email as entreprise_email,
           e.tva, e.devise_symbole, e.logo as entreprise_logo
    FROM facture f
    LEFT JOIN client c ON f.client_id = c.id
    LEFT JOIN entreprise e ON f.entreprise_id = e.id
    WHERE f.id = ? AND f.entreprise_id = ?
");
$invoice->execute([$invoiceId, $_SESSION['entreprise_id']]);
$invoice = $invoice->fetch();

if (!$invoice) {
    die('Facture non trouvée');
}

// Récupérer les items de la facture
$items = $pdo->prepare("
    SELECT * FROM item_facture 
    WHERE facture_id = ? 
    ORDER BY id
");
$items->execute([$invoiceId]);
$items = $items->fetchAll();

// Calcul des totaux
$subtotal = 0;
foreach ($items as $item) {
    $subtotal += $item['quantite'] * $item['prix_unitaire'];
}
$tva = $subtotal * ($invoice['tva'] / 100);
$total = $subtotal + $tva;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
    <style>
        <style>
    /* Styles généraux */
    body {
        font-family: 'Arial', sans-serif;
        line-height: 1.6;
        color: #333;
        background-color: #f9f9f9;
        margin: 0;
        padding: 20px;
    }
    
    .invoice-preview {
        max-width: 800px;
        margin: 0 auto;
        background: white;
        padding: 30px;
        box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
        border-radius: 5px;
    }
    
    /* En-tête */
    .invoice-header {
        display: flex;
        justify-content: space-between;
        margin-bottom: 30px;
        padding-bottom: 20px;
        border-bottom: 1px solid #eee;
    }
    
    .company-info h2 {
        color: #2c3e50;
        margin: 0 0 10px 0;
        font-size: 22px;
    }
    
    .invoice-title {
        text-align: right;
    }
    
    .invoice-title h1 {
        color: #3498db;
        margin: 0 0 10px 0;
        font-size: 24px;
    }
    
    /* Informations client */
    .client-info {
        background: #f5f9fc;
        padding: 15px;
        margin-bottom: 30px;
        border-radius: 5px;
        border-left: 4px solid #3498db;
    }
    
    .client-info h3 {
        margin-top: 0;
        color: #2c3e50;
    }
    
    /* Tableau des produits */
    .products-table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 30px;
    }
    
    .products-table th {
        background: #3498db;
        color: white;
        padding: 12px;
        text-align: left;
    }
    
    .products-table td {
        padding: 12px;
        border-bottom: 1px solid #eee;
    }
    
    .products-table tr:nth-child(even) {
        background-color: #f9f9f9;
    }
    
    .products-table tr:hover {
        background-color: #f1f7fd;
    }
    
    /* Totaux */
    .totals-preview {
        margin-bottom: 30px;
    }
    
    .totals-grid {
        display: flex;
        justify-content: flex-end;
    }
    
    .totals-table {
        width: 300px;
    }
    
    .total-row {
        display: flex;
        justify-content: space-between;
        padding: 10px;
        border-bottom: 1px solid #eee;
    }
    
    .total-row span:first-child {
        font-weight: bold;
    }
    
    .grand-total {
        background: #f5f9fc;
        font-weight: bold;
        font-size: 18px;
        color: #2c3e50;
        border-bottom: none !important;
    }
    
    /* Notes */
    .invoice-notes {
        padding: 15px;
        background: #fff9e6;
        border-left: 4px solid #f1c40f;
        border-radius: 5px;
    }
    
    .invoice-notes p {
        margin: 5px 0;
    }
    
    /* Responsive */
    @media (max-width: 768px) {
        .invoice-header {
            flex-direction: column;
        }
        
        .invoice-title {
            text-align: left;
            margin-top: 20px;
        }
        
        .totals-table {
            width: 100%;
        }
    }
</style>
    </style>
</head>
<body>
<div class="invoice-preview" id="invoicePreview">
    <div class="invoice-header">
        <div class="company-info">
        <img src="<?= htmlspecialchars($invoice['entreprise_logo']) ?>" class="header-logo" alt="Logo" style="border-radius:50%;object-fit:cover;width:42px;height:42px;">
            <h2><?= htmlspecialchars($invoice['entreprise_nom']) ?></h2>
            <p><?= htmlspecialchars($invoice['entreprise_adresse']) ?></p>
            <p>Tél: <?= htmlspecialchars($invoice['entreprise_telephone']) ?></p>
            <p>Email: <?= htmlspecialchars($invoice['entreprise_email']) ?></p>
        </div>
        
        <div class="invoice-title">
            <h1>FACTURE</h1>
            <p>N°: <?= htmlspecialchars($invoice['numero']) ?></p>
            <p>Date: <?= date('d/m/Y', strtotime($invoice['date_facture'])) ?></p>
        </div>
    </div>
    
    <div class="client-info">
        <h3>Client</h3>
        <p><strong><?= htmlspecialchars($invoice['client_nom']) ?></strong></p>
        <p><?= htmlspecialchars($invoice['client_adresse']) ?></p>
        <p>Tél: <?= htmlspecialchars($invoice['client_telephone']) ?></p>
        <p>Email: <?= htmlspecialchars($invoice['client_email']) ?></p>
    </div>
    
    <table class="products-table">
        <thead>
            <tr>
                <th>Désignation</th>
                <th>Qté</th>
                <th>Prix unitaire</th>
                <th>Total HT</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $item): ?>
                <tr>
                    <td><?= htmlspecialchars($item['designation']) ?></td>
                    <td><?= $item['quantite'] ?></td>
                    <td><?= number_format($item['prix_unitaire'], 2, ',', ' ') ?> <?= htmlspecialchars($invoice['devise_symbole']) ?></td>
                    <td><?= number_format($item['quantite'] * $item['prix_unitaire'], 2, ',', ' ') ?> <?= htmlspecialchars($invoice['devise_symbole']) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    
    <div class="totals-preview">
        <div class="totals-grid">
            <div></div>
            <div class="totals-table">
                <div class="total-row">
                    <span>Total HT:</span>
                    <span><?= number_format($subtotal, 2, ',', ' ') ?> <?= htmlspecialchars($invoice['devise_symbole']) ?></span>
                </div>
                <div class="total-row">
                    <span>TVA (<?= $invoice['tva'] ?>%):</span>
                    <span><?= number_format($tva, 2, ',', ' ') ?> <?= htmlspecialchars($invoice['devise_symbole']) ?></span>
                </div>
                <div class="total-row grand-total">
                    <span>Total TTC:</span>
                    <span><?= number_format($total, 2, ',', ' ') ?> <?= htmlspecialchars($invoice['devise_symbole']) ?></span>
                </div>
            </div>
        </div>
    </div>
    
    
    <!-- <?php if (!empty($invoice['notes'])): ?>
        <div class="invoice-notes">
            <p><strong>Notes:</strong></p>
            <p><?= nl2br(htmlspecialchars($invoice['notes'])) ?></p>
        </div>
    <?php endif; ?> -->
</div>
</body>
</html>

