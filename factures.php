<?php
session_start();
require_once 'config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}
$logoPath = null;

$stmt = $pdo->prepare("SELECT logo FROM entreprise WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$logoPath = $stmt->fetchColumn();

// Récupérer les informations de l'utilisateur
$stmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Récupérer les informations de l'entreprise
$entreprise = $pdo->prepare("SELECT * FROM entreprise WHERE user_id = ?");
$entreprise->execute([$_SESSION['user_id']]);
$entreprise = $entreprise->fetch();

if (!$entreprise) {
    die("Aucune entreprise associée à cet utilisateur");
}

$entreprise_id = $entreprise['id'];


// Initialisation
$is_edit = false;
$facture_to_edit = null;
$items_to_edit = [];
$client_to_edit = null;

if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $facture_id = (int) $_GET['edit'];

    try {
        // 1. Récupérer la facture
        $stmt = $pdo->prepare("SELECT * FROM facture_pro WHERE id = ? AND entreprise_id = ?");
        $stmt->execute([$facture_id, $entreprise_id]);
        $facture_to_edit = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($facture_to_edit) {
            $is_edit = true;

            // 2. Récupérer les items
            $stmt = $pdo->prepare("SELECT * FROM item_facture_pro WHERE facture_pro_id = ?");
            $stmt->execute([$facture_id]);
            $items_to_edit = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // 3. Récupérer le client
            $stmt = $pdo->prepare("SELECT * FROM client WHERE id = ? AND entreprise_id = ?");
            $stmt->execute([$facture_to_edit['client_id'], $entreprise_id]);
            $client_to_edit = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) {
        die("Erreur base de données : " . $e->getMessage());
    }
}

// Génération du numéro
$newNumber = 'PRO-' . date('Ymd') . '-001';
if ($is_edit) {
    $newNumber = $facture_to_edit['numero'];
} else {
    $stmt = $pdo->prepare("SELECT numero FROM facture_pro WHERE entreprise_id = ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$entreprise_id]);
    $last = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($last && preg_match('/PRO-(\d{8})-(\d{3})/', $last['numero'], $matches)) {
        $newNumber = 'PRO-' . date('Ymd') . '-' . str_pad($matches[2] + 1, 3, '0', STR_PAD_LEFT);
    }
}

// Récupérer les 4 produits les plus récents (par ID décroissant)
$recentProduits = $pdo->prepare("SELECT * FROM produit WHERE entreprise_id = ? ORDER BY id DESC LIMIT 4");
$recentProduits->execute([$entreprise_id]);
$recentProduits = $recentProduits->fetchAll();

// Récupérer tous les produits pour la recherche
$allProduits = $pdo->prepare("SELECT * FROM produit WHERE entreprise_id = ? ORDER BY designation ASC");
$allProduits->execute([$entreprise_id]);
$allProduits = $allProduits->fetchAll();

// Récupérer les 4 clients les plus récents en utilisant l'ID (supposant que les IDs sont auto-incrémentés)
$recentClients = $pdo->prepare("SELECT * FROM client WHERE entreprise_id = ? ORDER BY id DESC LIMIT 4");
$recentClients->execute([$entreprise_id]);
$recentClients = $recentClients->fetchAll();

// Récupérer tous les clients pour la recherche
$allClients = $pdo->prepare("SELECT * FROM client WHERE entreprise_id = ? ORDER BY nom ASC");
$allClients->execute([$entreprise_id]);
$allClients = $allClients->fetchAll();

$produits = $pdo->prepare("SELECT * FROM produit WHERE entreprise_id = ? ORDER BY designation ASC");
$produits->execute([$entreprise_id]);
$produits = $produits->fetchAll();
?>


<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nouvelle Facture Proforma</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <style>
        :root {
            --primary-color: #6c5ce7;
            --secondary-color: #5649d6;
            --accent-color: #00cec9;
            --light-color: #f8f9fa;
            --dark-color: #2d3436;
            --success-color: #00b894;
            --warning-color: #fdcb6e;
            --danger-color: #d63031;
            --gray-color: #b2bec3;
            --white-color: #ffffff;
            --sidebar-bg: rgb(224, 145, 49);
            --sidebar-text: #dfe6e9;
            --sidebar-active: #6c5ce7;
            --header-bg: #ffffff;
            --card-bg: #ffffff;
            --table-bg: #ffffff;
            --sidebar-width: 250px;
            --header-height: 70px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background-color: #f1f3f6;
            color: var(--dark-color);
            overflow-x: hidden;
        }

        .dashboard-container {
            display: flex;
            min-height: 100vh;
        }

        /* Header Styles */
        .dashboard-header {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: var(--header-height);
            background-color: var(--header-bg);
            box-shadow: 0 2px 15px rgba(0, 0, 0, 0.1);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 25px;
            z-index: 1000;
        }

        .header-left {
            display: flex;
            align-items: center;
        }

        #menuToggle {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            margin-right: 20px;
            color: var(--primary-color);
        }

        .logo {
            font-weight: 700;
            font-size: 1.4rem;
            color: var(--primary-color);
            background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
            background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .user-menu {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .user-menu img {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            border: 2px solid var(--primary-color);
        }

        .user-menu span {
            font-weight: 500;
            color: var(--dark-color);
        }

        /* Sidebar Styles */
        .dashboard-sidebar {
            width: var(--sidebar-width);
            background-color: var(--sidebar-bg);
            position: fixed;
            top: var(--header-height);
            left: 0;
            bottom: 0;
            transition: transform 0.3s ease;
            z-index: 999;
            border-right: 1px solid rgba(255, 255, 255, 0.1);
        }

        .sidebar-menu {
            list-style: none;
            padding: 15px 0;
        }

        .sidebar-menu li {
            margin-bottom: 5px;
        }

        .sidebar-menu a {
            display: flex;
            align-items: center;
            padding: 14px 25px;
            color: var(--sidebar-text);
            text-decoration: none;
            transition: all 0.3s;
            font-size: 0.95rem;
            border-left: 3px solid transparent;
        }

        .sidebar-menu a:hover {
            background-color: rgba(255, 255, 255, 0.05);
            color: var(--white-color);
            border-left-color: var(--accent-color);
        }

        .sidebar-menu a.active {
            background-color: rgba(108, 92, 231, 0.2);
            color: var(--white-color);
            border-left-color: var(--primary-color);
            font-weight: 500;
        }

        .sidebar-menu a i {
            margin-right: 12px;
            width: 20px;
            text-align: center;
            font-size: 1.1rem;
        }

        /* Main Content Styles */
        .dashboard-main {
            flex: 1;
            margin-left: var(--sidebar-width);
            margin-top: var(--header-height);
            padding: 30px;
            transition: margin-left 0.3s ease;
        }

        .page-title {
            font-size: 2rem;
            margin-bottom: 25px;
            color: var(--dark-color);
            position: relative;
            display: inline-block;
        }

        .page-title::after {
            content: '';
            position: absolute;
            bottom: -8px;
            left: 0;
            width: 60px;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-color), var(--accent-color));
            border-radius: 2px;
        }

        /* Invoice Container */
        .invoice-container {
            background-color: var(--card-bg);
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            border: 1px solid rgba(0, 0, 0, 0.03);
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        /* Form Styles */
        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--dark-color);
        }

        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(108, 92, 231, 0.1);
        }

        textarea.form-control {
            min-height: 100px;
            resize: vertical;
        }

        /* Client Section */
        .client-section {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
        }

        .client-section h3 {
            font-size: 1.3rem;
            margin-bottom: 15px;
            color: var(--dark-color);
        }

        .client-info-display {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-top: 10px;
            border-left: 4px solid var(--primary-color);
        }

        /* Products Section */
        .products-section {
            margin-bottom: 30px;
        }

        .products-section h3 {
            font-size: 1.3rem;
            margin-bottom: 15px;
            color: var(--dark-color);
        }

        .table-actions {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 15px;
            gap: 10px;
        }

        /* Table Styles */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        .data-table th,
        .data-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #f0f0f0;
        }

        .data-table th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: var(--dark-color);
            text-transform: uppercase;
            font-size: 0.8rem;
            letter-spacing: 0.5px;
        }

        .data-table tr:hover {
            background-color: #f8fafc;
        }

        .data-table input[type="text"],
        .data-table input[type="number"] {
            width: 100%;
            padding: 8px 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        .tva-badge {
            display: inline-block;
            padding: 3px 8px;
            background-color: #e3f2fd;
            color: #1976d2;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        /* Totals Section */
        .totals-section {
            margin-bottom: 30px;
        }

        .totals-grid {
            display: flex;
            justify-content: flex-end;
        }

        .totals-table {
            width: 300px;
            border: 1px solid #eee;
            border-radius: 8px;
            overflow: hidden;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 15px;
            border-bottom: 1px solid #eee;
        }

        .total-row:last-child {
            border-bottom: none;
        }

        .grand-total {
            background-color: #f8f9fa;
            font-weight: 600;
        }

        /* Invoice Actions */
        .invoice-actions {
            display: flex;
            justify-content: flex-end;
            gap: 15px;
            margin-top: 30px;
        }

        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            padding: 10px 18px;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            border: none;
            font-size: 0.9rem;
        }

        .btn i {
            margin-right: 8px;
        }

        .btn-primary {
            background: var(--primary-color);
            color: white;
        }

        .btn-primary:hover {
            background: var(--secondary-color);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .btn-secondary {
            background: var(--gray-color);
            color: white;
        }

        .btn-secondary:hover {
            background: #a8a8a8;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .btn-success {
            background: var(--success-color);
            color: white;
        }

        .btn-success:hover {
            background: #00a884;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .btn-danger {
            background: var(--danger-color);
            color: white;
        }

        .btn-danger:hover {
            background: #c62828;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .btn-small {
            padding: 6px 12px;
            font-size: 0.8rem;
        }

        /* Search Modal */
        .search-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1100;
            overflow-y: auto;
            padding: 20px;
        }

        .search-container {
            background-color: white;
            border-radius: 12px;
            padding: 25px;
            max-width: 600px;
            margin: 50px auto;
            box-shadow: 0 5px 30px rgba(0, 0, 0, 0.2);
        }

        .search-box {
            display: flex;
            margin-bottom: 20px;
        }

        .search-box input {
            flex: 1;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 8px 0 0 8px;
            font-size: 1rem;
        }

        .search-box button {
            border-radius: 0 8px 8px 0;
        }

        .search-results {
            max-height: 400px;
            overflow-y: auto;
        }

        .product-item,
        .client-item {
            padding: 12px 15px;
            border-bottom: 1px solid #eee;
            cursor: pointer;
            transition: background-color 0.2s;
        }

        .recent-clients-section h4,
        .all-clients-section h4 {
            margin: 10px 0 5px;
            padding-bottom: 5px;
            border-bottom: 1px solid #eee;
            color: #666;
            font-size: 0.9em;
        }

        .client-item {
            padding: 8px 12px;
            border-bottom: 1px solid #f0f0f0;
            cursor: pointer;
            transition: background 0.2s;
        }

        .client-item:hover {
            background: #f8f9fa;
        }

        .client-item strong {
            display: block;
            margin-bottom: 3px;
        }

        .client-item div {
            font-size: 0.8em;
            color: #666;
        }

        .recent-products-section h4,
        .all-products-section h4 {
            margin: 10px 0 5px;
            padding-bottom: 5px;
            border-bottom: 1px solid #eee;
            color: #666;
            font-size: 0.9em;
        }

        .product-item {
            padding: 8px 12px;
            border-bottom: 1px solid #f0f0f0;
            cursor: pointer;
            transition: background 0.2s;
        }

        .product-item:hover {
            background: #f8f9fa;
        }

        .product-item label {
            display: block;
            cursor: pointer;
            margin-bottom: 0;
        }

        .product-item strong {
            display: inline-block;
            margin-bottom: 3px;
        }


        .product-item:hover,
        .client-item:hover {
            background-color: #f5f5f5;
        }

        .product-item label {
            display: block;
            cursor: pointer;
        }

        .modal-actions {
            display: flex;
            justify-content: flex-end;
            margin-top: 20px;
        }

        /* Preview Modal */
        #invoicePreview {
            background-color: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

        .invoice-preview {
            font-family: Arial, sans-serif;
        }

        .invoice-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
        }

        .invoice-title h1 {
            font-size: 1.5rem;
            color: var(--primary-color);
            margin-bottom: 10px;
        }

        .client-info {
            margin-bottom: 30px;
        }

        .products-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }

        .products-table th,
        .products-table td {
            padding: 10px;
            border-bottom: 1px solid #eee;
            text-align: left;
        }

        .products-table th {
            background-color: #f5f5f5;
        }

        .text-right {
            text-align: right;
        }

        .totals-preview {
            margin-bottom: 30px;
        }

        .invoice-notes {
            padding-top: 20px;
            border-top: 1px solid #eee;
        }

        /* Responsive Styles */
        @media (max-width: 992px) {
            .dashboard-sidebar {
                transform: translateX(-100%);
            }

            .dashboard-sidebar.active {
                transform: translateX(0);
            }

            .dashboard-main {
                margin-left: 0;
            }

            body.sidebar-active {
                overflow: hidden;
            }

            body.sidebar-active::after {
                content: '';
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background-color: rgba(0, 0, 0, 0.5);
                z-index: 998;
            }
        }

        @media (max-width: 768px) {
            .invoice-header {
                flex-direction: column;
            }

            .company-info,
            .invoice-meta {
                margin-bottom: 20px;
            }

            .data-table {
                display: block;
                overflow-x: auto;
            }

            .invoice-actions {
                flex-direction: column;
                gap: 10px;
            }

            .btn {
                width: 100%;
                justify-content: center;
            }
        }

        #selectionLimitMessage {
            padding: 8px 12px;
            border-radius: 4px;
            font-size: 13px;
            margin-bottom: 10px;
            background-color: #fff3cd;
            color: #856404;
            border: 1px solid #ffeeba;
        }

        .notification-center {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            max-width: 350px;
        }

        .notification {
            padding: 15px 20px;
            margin-bottom: 15px;
            border-radius: 5px;
            color: white;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            animation: slideIn 0.3s ease-out forwards;
            transform: translateX(120%);
        }

        .notification.fade-out {
            animation: fadeOut 0.4s ease-out forwards;
        }

        .notification-success {
            background-color: #28a745;
            border-left: 5px solid #218838;
        }

        .notification-warning {
            background-color: #ffc107;
            color: #343a40;
            border-left: 5px solid #e0a800;
        }

        .notification-error {
            background-color: #dc3545;
            border-left: 5px solid #c82333;
        }

        .notification i {
            margin-right: 10px;
        }

        .notification-close {
            background: none;
            border: none;
            color: inherit;
            cursor: pointer;
            margin-left: 15px;
            font-size: 18px;
        }

        @keyframes slideIn {
            to {
                transform: translateX(0);
            }
        }

        @keyframes fadeOut {
            to {
                opacity: 0;
                transform: translateY(-20px);
            }
        }

        /* Nouveau système de messages - Version améliorée */
        .message-container {
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 1200;
            /* Plus élevé que l'overlay (1100) */
            max-width: 500px;
            width: 90%;
            display: flex;
            flex-direction: column;
            align-items: center;
            pointer-events: none;
        }

        .message {
            padding: 15px 25px;
            border-radius: 10px;
            margin-bottom: 15px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.15);
            display: flex;
            align-items: center;
            justify-content: space-between;
            animation: fadeInUp 0.4s ease-out forwards;
            opacity: 0;
            transform: translateY(-20px);
            pointer-events: auto;
            width: 100%;
            backdrop-filter: blur(5px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .message.hide {
            animation: fadeOutUp 0.3s ease-in forwards;
        }

        .message-content {
            display: flex;
            align-items: center;
            flex-grow: 1;
        }

        .message-icon {
            font-size: 1.5rem;
            margin-right: 15px;
        }

        .message-text {
            font-size: 0.95rem;
            font-weight: 500;
        }

        .message-success {
            background: linear-gradient(135deg, rgba(40, 167, 69, 0.9), rgba(52, 195, 89, 0.9));
            color: white;
        }

        .message-error {
            background: linear-gradient(135deg, rgba(220, 53, 69, 0.9), rgba(232, 86, 100, 0.9));
            color: white;
        }

        .message-warning {
            background: linear-gradient(135deg, rgba(255, 193, 7, 0.9), rgba(255, 205, 57, 0.9));
            color: #343a40;
        }

        .message-info {
            background: linear-gradient(135deg, rgba(23, 162, 184, 0.9), rgba(58, 196, 215, 0.9));
            color: white;
        }

        .message-confirm {
            background: linear-gradient(135deg, rgba(108, 117, 125, 0.9), rgba(134, 142, 150, 0.9));
            color: white;
        }

        .message-actions {
            display: flex;
            gap: 10px;
            margin-left: 20px;
        }

        .message-actions button {
            padding: 6px 15px;
            border: none;
            border-radius: 6px;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            min-width: 70px;
        }

        .message-actions .btn-confirm {
            background-color: white;
            color: #dc3545;
        }

        .message-actions .btn-confirm:hover {
            background-color: #f8f9fa;
            transform: translateY(-2px);
        }

        .message-actions .btn-cancel {
            background-color: rgba(255, 255, 255, 0.2);
            color: white;
        }

        .message-actions .btn-cancel:hover {
            background-color: rgba(255, 255, 255, 0.3);
            transform: translateY(-2px);
        }

        .btn-close {
            background: none;
            border: none;
            color: inherit;
            cursor: pointer;
            font-size: 1rem;
            margin-left: 15px;
            opacity: 0.8;
            transition: opacity 0.2s;
        }

        .btn-close:hover {
            opacity: 1;
        }

        @keyframes fadeInUp {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes fadeOutUp {
            to {
                opacity: 0;
                transform: translateY(-30px);
            }
        }
    </style>
</head>

<body>
    <div class="dashboard-container">
        <header class="dashboard-header">
            <div class="header-left">
                <button id="menuToggle">
                    <i class="fas fa-bars"></i>
                </button>
                <div class="logo">Facture Pro</div>
            </div>
            <div class="user-menu">
                <?php if ($logoPath && file_exists($logoPath)): ?>
                    <img src="<?= htmlspecialchars($logoPath) ?>" class="header-logo" alt="Logo"
                        style="border-radius:50%;object-fit:cover;width:42px;height:42px;">
                <?php else: ?>
                    <div class="header-logo placeholder">
                        <i class="fas fa-building"></i>
                    </div>
                <?php endif; ?>
                <?php echo htmlspecialchars($user['name']); ?></span>

            </div>
        </header>


        <aside class="dashboard-sidebar">
            <ul class="sidebar-menu">
            <li><a href="dashboard.php"><i class="fas fa-home"></i> Tableau de bord</a></li>
                <li><a href="parametres.php"><i class="fas fa-cog"></i> Paramètres</a></li>
                <li><a href="produits.php"><i class="fas fa-boxes"></i> Produits</a></li>
                <li><a href="clients.php"><i class="fas fa-users"></i> Clients</a></li>
                <li><a href="factures.php" class="active"><i class="fas fa-file-invoice"></i> Factures ProForma</a></li>
                <li><a href="historique.php"><i class="fas fa-history"></i> Historique</a></li>
                <li><a href="factures_definitives.php" ><i class="fas fa-file-invoice-dollar"></i>
                        Facture</a></li>
                <li><a href="editorial.php"><i class="fas fa-chart-line me-2"></i> Statistiques</a></li>
                <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Déconnexion</a></li>
            </ul>
        </aside>

        <main class="dashboard-main">
            <div class="page-header">
                <h1 class="page-title">Nouvelle Facture Proforma</h1>
                <div class="header-actions">
                    <button id="previewInvoice" class="btn btn-primary">
                        <i class="fas fa-eye"></i> Prévisualiser
                    </button>
                    <button id="saveDraft" class="btn btn-secondary">
                        <i class="fas fa-save"></i> Enregistrer brouillon
                    </button>
                </div>
            </div>



            <div class="invoice-container">
                <!-- En-tête de la facture -->
                <div class="invoice-header">
                    <div class="company-info">
                        <?php if ($logoPath && file_exists($logoPath)): ?>
                            <img src="<?= htmlspecialchars($logoPath) ?>" class="header-logo" alt="Logo"
                                style="border-radius:50%;object-fit:cover;width:42px;height:42px;">
                        <?php else: ?>
                            <div class="header-logo placeholder">
                                <i class="fas fa-building"></i>
                            </div>
                        <?php endif; ?>
                        <h2><?= htmlspecialchars($entreprise['nom']) ?></h2>
                        <p><?= htmlspecialchars($entreprise['adresse']) ?></p>
                        <p>Tél: <?= htmlspecialchars($entreprise['telephone']) ?></p>
                        <p>Email: <?= htmlspecialchars($entreprise['email']) ?></p>
                        <p>TVA: <?= htmlspecialchars($entreprise['tva']) ?>%</p>
                    </div>

                    <div class="invoice-meta">
                        <div class="form-group">
                            <label for="invoice_number">N° Facture</label>
                            <input type="text" id="invoice_number" class="form-control" value="<?= $newNumber ?>"
                                readonly>
                        </div>
                        <div class="form-group">
                            <label for="invoice_date">Date</label>
                            <input type="date" id="invoice_date" class="form-control" value="<?= date('Y-m-d') ?>">
                        </div>
                    </div>
                </div>

                <!-- Sélection du client -->
                <div class="client-section">
                    <h3>Client</h3>
                    <div class="form-row">
                        <div class="form-group" style="flex-grow: 1;">
                            <div class="search-wrapper">
                                <input type="text" id="client_search" class="form-control"
                                    placeholder="Rechercher un client...">
                            </div>
                            <input type="hidden" id="client_id">
                            <div id="client_info" class="client-info-display" style="display:none;">
                                <h4 id="client_name"></h4>
                                <p id="client_address"></p>
                                <p id="client_contact"></p>
                            </div>
                        </div>
                    </div>
                </div>
                <div id="notificationCenter" class="notification-center">
                    <!-- Les notifications apparaîtront ici -->
                </div>
                <!-- Lignes de produits -->
                <div class="products-section">
                    <h3>Produits</h3>
                    <div class="table-actions">
                        <button id="searchProductBtn" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Ajouter produit
                        </button>
                    </div>

                    <table class="data-table" id="productsTable">
                        <thead>
                            <tr>
                                <th width="40%">Désignation</th>
                                <th width="10%">Qté</th>
                                <th width="15%">Prix unitaire</th>
                                <th width="10%">TVA</th>
                                <th width="15%">Total HT</th>
                                <th width="10%">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Rempli dynamiquement -->
                        </tbody>
                    </table>
                </div>

                <!-- Totaux -->
                <div class="totals-section">
                    <div class="totals-grid">
                        <div></div>
                        <div class="totals-table">
                            <div class="total-row">
                                <span>Total HT:</span>
                                <span id="subtotal">0
                                    <?= htmlspecialchars($entreprise['devise_symbole'] ?? 'XOF') ?></span>
                            </div>
                            <div class="total-row">
                                <span>TVA (<?= htmlspecialchars($entreprise['tva']) ?>%):</span>
                                <span id="tva_amount">0
                                    <?= htmlspecialchars($entreprise['devise_symbole'] ?? 'XOF') ?></span>
                            </div>
                            <div class="total-row grand-total">
                                <span>Total TTC:</span>
                                <span id="total_ttc">0
                                    <?= htmlspecialchars($entreprise['devise_symbole'] ?? 'XOF') ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Notes -->
                <div class="form-group">
                    <label for="invoice_notes">Notes</label>
                    <textarea id="invoice_notes" class="form-control"
                        rows="3">Facture proforma - ne constitue pas une facture définitive</textarea>
                </div>

                <!-- Actions -->
                <div class="invoice-actions">
                    <button id="saveInvoice" class="btn btn-success">
                        <i class="fas fa-check"></i> Valider la facture
                    </button>
                    <!-- <button id="printInvoice" class="btn btn-primary">
                        <i class="fas fa-print"></i> Imprimer
                    </button> -->
                    <button id="cancelInvoice" class="btn btn-danger">
                        <i class="fas fa-times"></i> Annuler
                    </button>
                </div>
            </div>
        </main>
    </div>
    <div class="message-container" id="messageContainer"></div>

    <!-- Modal de recherche de produits -->
    <div id="productSearchModal" class="search-modal">
        <div class="search-container">
            <h3>Rechercher un produit</h3>
            <div class="search-box">
                <input type="text" id="productSearchInput" class="form-control" placeholder="Nom du produit...">
                <button id="closeProductSearch" class="btn btn-small btn-danger">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div id="productSearchResults" class="search-results">
                <div class="recent-products-section">
                    <h4>Produits récents</h4>
                    <?php foreach ($recentProduits as $produit): ?>
                        <div class="product-item" data-id="<?= $produit['id'] ?>"
                            data-designation="<?= htmlspecialchars($produit['designation']) ?>"
                            data-prix="<?= $produit['prix_unitaire'] ?>">
                            <input type="checkbox" class="product-checkbox" id="prod_<?= $produit['id'] ?>">
                            <label for="prod_<?= $produit['id'] ?>">
                                <strong><?= htmlspecialchars($produit['designation']) ?></strong>
                                <span class="tva-badge"><?= htmlspecialchars($entreprise['tva']) ?>% TVA</span>
                                <div style="float: right;">
                                    <?= number_format($produit['prix_unitaire'], 2, ',', ' ') ?>
                                    <?= htmlspecialchars($entreprise['devise_symbole'] ?? 'XOF') ?>
                                </div>
                                <div style="clear: both;"></div>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="all-products-section" style="display:none;">
                    <h4>Résultats de recherche</h4>
                    <?php foreach ($allProduits as $produit): ?>
                        <div class="product-item" data-id="<?= $produit['id'] ?>"
                            data-designation="<?= htmlspecialchars($produit['designation']) ?>"
                            data-prix="<?= $produit['prix_unitaire'] ?>">
                            <input type="checkbox" class="product-checkbox" id="prod_<?= $produit['id'] ?>">
                            <label for="prod_<?= $produit['id'] ?>">
                                <strong><?= htmlspecialchars($produit['designation']) ?></strong>
                                <span class="tva-badge"><?= htmlspecialchars($entreprise['tva']) ?>% TVA</span>
                                <div style="float: right;">
                                    <?= number_format($produit['prix_unitaire'], 2, ',', ' ') ?>
                                    <?= htmlspecialchars($entreprise['devise_symbole'] ?? 'XOF') ?>
                                </div>
                                <div style="clear: both;"></div>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="modal-actions">
                <button id="addSelectedProducts" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Ajouter les produits sélectionnés (<span id="selectedCount">0</span>)
                </button>
            </div>
        </div>
    </div>

    <div id="clientSearchModal" class="search-modal">
        <div class="search-container">
            <h3>Rechercher un client</h3>
            <div class="search-box">
                <input type="text" id="clientSearchInput" class="form-control" placeholder="Nom du client...">
                <button id="closeClientSearch" class="btn btn-small btn-danger">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div id="clientSearchResults" class="search-results">
                <div class="recent-clients-section">
                    <h4>Clients récents</h4>
                    <?php foreach ($recentClients as $client): ?>
                        <div class="client-item" data-id="<?= $client['id'] ?>"
                            data-nom="<?= htmlspecialchars($client['nom']) ?>"
                            data-email="<?= htmlspecialchars($client['email']) ?>"
                            data-telephone="<?= htmlspecialchars($client['telephone']) ?>"
                            data-adresse="<?= htmlspecialchars($client['adresse']) ?>">
                            <strong><?= htmlspecialchars($client['nom']) ?></strong>
                            <div><?= htmlspecialchars($client['email']) ?></div>
                            <div><?= htmlspecialchars($client['telephone']) ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="all-clients-section" style="display:none;">
                    <h4>Résultats de recherche</h4>
                    <?php foreach ($allClients as $client): ?>
                        <div class="client-item" data-id="<?= $client['id'] ?>"
                            data-nom="<?= htmlspecialchars($client['nom']) ?>"
                            data-email="<?= htmlspecialchars($client['email']) ?>"
                            data-telephone="<?= htmlspecialchars($client['telephone']) ?>"
                            data-adresse="<?= htmlspecialchars($client['adresse']) ?>">
                            <strong><?= htmlspecialchars($client['nom']) ?></strong>
                            <div><?= htmlspecialchars($client['email']) ?></div>
                            <div><?= htmlspecialchars($client['telephone']) ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de prévisualisation -->
    <!-- Modal de prévisualisation -->
<div id="previewModal" class="search-modal" style="display: none;">
     <!-- Bouton de fermeture avec style inline -->
     <button id="closePreview" style="
            position: absolute;
            top: 15px;
            right: 15px;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: #ff4757;
            color: white;
            border: none;
            display: flex;
            justify-content: center;
            align-items: center;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        ">
            <i class="fas fa-times" style="font-size: 14px;"></i>
        </button>
    <div class="search-container" style="width: 90%; max-width: 800px; position: relative;">
       
        
        <div id="invoicePreview" style="padding: 20px; background: white;">
            <!-- Rempli dynamiquement -->
        </div>
        
        <div class="modal-actions">
            <button id="editFromPreview" class="btn btn-primary">
                <i class="fas fa-edit"></i> Modifier
            </button>
            <!-- <button id="printFromPreview" class="btn btn-success">
                <i class="fas fa-print"></i> Imprimer
            </button> -->
        </div>
    </div>
</div>

<script>
// Effet hover (optionnel - à ajouter si vous voulez l'interactivité)
document.getElementById('closePreview').onmouseover = function() {
    this.style.background = '#ff6b81';
    this.style.transform = 'scale(1.1)';
};
document.getElementById('closePreview').onmouseout = function() {
    this.style.background = '#ff4757';
    this.style.transform = 'scale(1)';
};
</script>


    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script>


        // Taux de TVA de l'entreprise (à définir au chargement)
        const entrepriseTva = <?= json_encode($entreprise['tva'] ?? 18) ?>;
        $(document).ready(function () {
            // Variables globales

            let currentAction = 'add';
            let currentProducts = [];
            let currentClient = null;
            let currentInvoiceId = null;
            let tvaRate = <?= $entreprise['tva'] ?? 18 ?>;
            let currency = '<?= $entreprise['devise_symbole'] ?? 'XOF' ?>';


            // Initialisation
            updateTotals();

            function generateNextInvoiceNumber(lastNumber) {
                if (!lastNumber) return 'PRO-' + new Date().toISOString().slice(0, 10).replace(/-/g, '') + '-001';

                const parts = lastNumber.split('-');
                const prefix = parts[0];
                const datePart = parts[1];
                const seqPart = parts[2];

                let newSeq = parseInt(seqPart) + 1;
                return `${prefix}-${datePart}-${newSeq.toString().padStart(3, '0')}`;
            }


            <?php if ($is_edit && $facture_to_edit && $client_to_edit): ?>

                // Déclaration des variables globales

                currentAction = 'edit';
                currentInvoiceId = <?= $facture_to_edit['id'] ?>;


                $(document).ready(function () {
                    // 1. Préremplir les données client
                    currentClient = {
                        id: <?= $client_to_edit['id'] ?>,
                        nom: <?= json_encode($client_to_edit['nom'], JSON_UNESCAPED_UNICODE) ?>,
                        adresse: <?= json_encode($client_to_edit['adresse'], JSON_UNESCAPED_UNICODE) ?>,
                        telephone: <?= json_encode($client_to_edit['telephone'], JSON_UNESCAPED_UNICODE) ?>,
                        email: <?= json_encode($client_to_edit['email'], JSON_UNESCAPED_UNICODE) ?>
                    };

                    // Afficher les infos client dans l'UI
                    $('#client_id').val(currentClient.id);
                    $('#client_name').text(currentClient.nom);
                    $('#client_address').text(currentClient.adresse);
                    $('#client_contact').text(currentClient.telephone + ' | ' + currentClient.email);
                    $('#client_info').show();
                    $('#client_search').val(currentClient.nom);

                    // 2. Préremplir les produits
                    currentProducts = [
                        <?php foreach ($items_to_edit as $item): ?>
                {
                                id: <?= $item['produit_id'] ?>,
                                designation: <?= json_encode($item['designation'], JSON_UNESCAPED_UNICODE) ?>,
                                quantite: <?= $item['quantite'] ?>,
                                prix_unitaire: <?= $item['prix_unitaire'] ?>,
                                tva: <?= $item['tva'] ?? $entreprise['tva'] ?>
                            },
                        <?php endforeach; ?>
                    ];
                    renderProductsTable(); // Cette fonction doit exister

                    // 3. Préremplir les autres champs
                    $('#invoice_number').val(<?= json_encode($facture_to_edit['numero'], JSON_UNESCAPED_UNICODE) ?>);
                    $('#invoice_date').val(<?= json_encode($facture_to_edit['date_facture'], JSON_UNESCAPED_UNICODE) ?>);
                    $('#invoice_notes').val(<?= json_encode($facture_to_edit['notes'], JSON_UNESCAPED_UNICODE) ?>);

                    // Debug
                    console.log('Mode édition activé pour facture ID:', currentInvoiceId);
                    console.log('Client:', currentClient);
                    console.log('Produits:', currentProducts);
                });

            <?php endif; ?>
            // Gestion des clients
            $('#client_search').on('click', function () {
                $('#clientSearchModal').show();
                $('#clientSearchInput').val('').focus();
                $('.recent-clients-section').show();
                $('.all-clients-section').hide();
            });

            $('#clientSearchInput').on('input', function () {
                const search = $(this).val().toLowerCase();

                if (search.length > 0) {
                    // Afficher la section recherche et masquer les récents
                    $('.recent-clients-section').hide();
                    $('.all-clients-section').show();

                    // Filtrer les résultats
                    $('.all-clients-section .client-item').each(function () {
                        const text = $(this).text().toLowerCase();
                        $(this).toggle(text.includes(search));
                    });
                } else {
                    // Si la recherche est vide, afficher à nouveau les clients récents
                    $('.recent-clients-section').show();
                    $('.all-clients-section').hide();
                }
            });

            // Gestion du clic sur un client (identique pour les deux sections)
            $('#clientSearchResults').on('click', '.client-item', function () {
                const id = $(this).data('id');
                const nom = $(this).data('nom');
                const email = $(this).data('email');
                const telephone = $(this).data('telephone');
                const adresse = $(this).data('adresse');

                currentClient = {
                    id: id,
                    nom: nom,
                    email: email,
                    telephone: telephone,
                    adresse: adresse
                };

                $('#client_id').val(id);
                $('#client_name').text(nom);
                $('#client_address').text(adresse);
                $('#client_contact').text(`${telephone} | ${email}`);
                $('#client_info').show();
                $('#client_search').val(nom);

                $('#clientSearchModal').hide();
            });

            $('#closeClientSearch').on('click', function () {
                $('#clientSearchModal').hide();
            });




            $('.product-checkbox').on('change', function () {
                updateSelectedCount();
            });

            // Fonction pour afficher les notifications stylées
            function showNotification(message, type = 'success', duration = 3000) {
                const icons = {
                    success: 'fa-check-circle',
                    warning: 'fa-exclamation-triangle',
                    error: 'fa-times-circle'
                };

                const notification = $(`
        <div class="notification notification-${type}">
            <div>
                <i class="fas ${icons[type]}"></i>
                ${message}
            </div>
            <button class="notification-close">&times;</button>
        </div>
    `);

                $('#notificationCenter').append(notification);

                // Animation d'entrée
                setTimeout(() => notification.css('transform', 'translateX(0)'), 10);

                // Fermeture au clic
                notification.find('.notification-close').on('click', () => {
                    notification.addClass('fade-out');
                    setTimeout(() => notification.remove(), 400);
                });

                // Fermeture automatique
                if (duration) {
                    setTimeout(() => {
                        notification.addClass('fade-out');
                        setTimeout(() => notification.remove(), 400);
                    }, duration);
                }
            }

            // Gestion des produits

            const maxProducts = 10;

            // Fonction pour mettre à jour le compteur
            function updateSelectedCount() {
                const selectedCount = $('.product-checkbox:checked').length;
                const remainingSlots = maxProducts - currentProducts.length;
                const canAddMore = selectedCount <= remainingSlots;

                $('#selectedCount').text(selectedCount);
                $('#addSelectedProducts').prop('disabled', selectedCount === 0 || !canAddMore);

                if (selectedCount > remainingSlots) {
                    showNotification(
                        `Limite : vous ne pouvez ajouter que ${remainingSlots} produit(s) supplémentaire(s)`,
                        'warning'
                    );
                }
            }

            // Ouverture du modal de recherche
            $('#searchProductBtn').on('click', function () {
                if (currentProducts.length >= maxProducts) {
                    showNotification(
                        'Vous avez déjà atteint la limite de 10 produits',
                        'error'
                    );
                    return;
                }

                $('#productSearchModal').show();
                $('#productSearchInput').val('').focus();
                $('.recent-products-section').show();
                $('.all-products-section').hide();
                updateSelectedCount();
            });

            // Recherche de produits
            $('#productSearchInput').on('input', function () {
                const search = $(this).val().toLowerCase();

                if (search.length > 0) {
                    $('.recent-products-section').hide();
                    $('.all-products-section').show();

                    $('.all-products-section .product-item').each(function () {
                        const text = $(this).text().toLowerCase();
                        $(this).toggle(text.includes(search));
                    });
                } else {
                    $('.recent-products-section').show();
                    $('.all-products-section').hide();
                }

                updateSelectedCount();
            });

            // Ajout des produits sélectionnés
            $('#addSelectedProducts').on('click', function () {
                const selectedProducts = [];
                const remainingSlots = maxProducts - currentProducts.length;

                $('.product-checkbox:checked').each(function (index) {
                    if (index < remainingSlots) {
                        const productItem = $(this).closest('.product-item');
                        const productId = productItem.data('id');
                        const designation = productItem.data('designation');
                        const prix = parseFloat(productItem.data('prix'));

                        const existingIndex = currentProducts.findIndex(p => p.id === productId);

                        if (existingIndex !== -1) {
                            currentProducts[existingIndex].quantite += 1;
                        } else {
                            selectedProducts.push({
                                id: productId,
                                designation: designation,
                                quantite: 1,
                                prix_unitaire: prix,
                                tva: tvaRate
                            });
                        }
                    }
                });

                if (selectedProducts.length > 0) {
                    currentProducts = [...currentProducts, ...selectedProducts];
                    renderProductsTable();

                    showNotification(
                        `${selectedProducts.length} produit(s) ajouté(s)`,
                        'success'
                    );
                }

                // Afficher un message si limite atteinte
                if (selectedCount > remainingSlots) {
                    $('#selectionLimitMessage').remove();
                    $('.modal-actions').prepend(
                        `<div id="selectionLimitMessage" class="alert alert-warning" style="margin-bottom: 10px;">
                Vous ne pouvez ajouter que ${remainingSlots} produit(s) supplémentaire(s)
            </div>`
                    );
                } else {
                    $('#selectionLimitMessage').remove();
                }

                $('#productSearchModal').hide();
                $('.product-checkbox').prop('checked', false);
                updateSelectedCount();
            });

            // Gestion des changements de sélection
            $('.product-checkbox').on('change', updateSelectedCount);

            // Fermeture du modal
            $('#closeProductSearch').on('click', function () {
                $('#productSearchModal').hide();
            });



            function renderProductsTable() {
                const tbody = $('#productsTable tbody');
                tbody.empty();

                if (currentProducts.length === 0) {
                    tbody.append('<tr><td colspan="6">Aucun produit ajouté</td></tr>');
                    return;
                }

                currentProducts.forEach((product) => {
                    const prix = parseFloat(product.prix_unitaire) || 0;
                    const quantite = parseInt(product.quantite) || 1;
                    const totalHT = prix * quantite;
                    const tvaRate = product.tva || 0;

                    tbody.append(`
               <tr data-id="${product.id}">
                <td>${product.designation}</td>
                <td><input type="number" class="form-control quantity" 
                     data-product="${product.id}" value="${quantite}" min="1"></td>
                <td>
                    <input type="number" class="form-control price-input" 
                           data-product="${product.id}" 
                           value="${prix.toFixed(2)}" 
                           step="0.01" 
                           min="0">
                </td>
                <td>
                    <select class="form-control tva-select" data-product="${product.id}">
                        
                        <option value="${entrepriseTva}" ${tvaRate === entrepriseTva ? 'selected' : ''}>
                            ${entrepriseTva}% (Standard)
                        </option>
                        <option value="0" ${tvaRate === 0 ? 'selected' : ''}>0%</option>
                    </select>
                </td>
                <td class="total-row">${formatMoney(totalHT)}</td>
                <td>
                    <button class="btn btn-danger btn-sm delete-product" 
                            data-product="${product.id}">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            </tr>
        `);
                });

                updateTotals();
            }
            $(document).on('input', '.price-input', function () {
                const productId = $(this).data('product');
                const newPrice = parseFloat($(this).val()) || 0;

                // Trouver le produit dans le tableau currentProducts
                const product = currentProducts.find(p => p.id === productId);
                if (product) {
                    // Mettre à jour le prix unitaire
                    product.prix_unitaire = newPrice;

                    // Mettre à jour le total HT pour cette ligne
                    const row = $(this).closest('tr');
                    const quantity = parseInt(row.find('.quantity').val()) || 1;
                    const totalHT = newPrice * quantity;

                    row.find('.total-row').text(formatMoney(totalHT));

                    // Recalculer les totaux globaux
                    updateTotals();
                }
            });
            // Événements dynamiques
            $(document).on('change', '.quantity', function () {
                const productId = $(this).data('product');
                const product = currentProducts.find(p => p.id === productId);

                if (product) {
                    product.quantite = parseInt($(this).val()) || 1;
                    updateTotals();
                }
            });

            $(document).on('change', '.price', function () {
                const productId = $(this).data('product');
                const product = currentProducts.find(p => p.id === productId);

                if (product) {
                    product.prix_unitaire = parseFloat($(this).val()) || 0;
                    updateTotals();
                }
            });

            $(document).on('change', 'input[id*="_designation"]', function () {
                const id = $(this).attr('id').replace('product_', '').replace('_designation', '');
                const product = currentProducts.find(p => p.id === id);

                if (product) {
                    product.designation = $(this).val();
                }
            });

            $(document).on('click', '.delete-product', function () {
                const productId = $(this).data('product');
                currentProducts = currentProducts.filter(p => p.id !== productId);
                renderProductsTable();
            });

            $(document).on('change', '.tva-select', function () {
                const productId = $(this).data('product');
                const tvaRate = parseFloat($(this).val()) || 0;

                const product = currentProducts.find(p => p.id === productId);
                if (product) {
                    product.tva = tvaRate;
                    updateTotals();
                }
            });

            // Calcul des totaux
            function updateTotals() {
                let subtotal = 0;
                let totalTva = 0;

                // Parcourir toutes les lignes du tableau pour recalculer
                $('#productsTable tbody tr').each(function () {
                    const productId = $(this).data('id');
                    const product = currentProducts.find(p => p.id === productId);

                    if (product) {
                        const prix = product.prix_unitaire;
                        const quantite = product.quantite;
                        const tvaRate = product.tva;
                        const totalHT = prix * quantite;

                        subtotal += totalHT;
                        totalTva += totalHT * (tvaRate / 100);
                    }
                });

                const totalTTC = subtotal + totalTva;

                // Mettre à jour l'affichage des totaux
                $('#subtotal').text(formatMoney(subtotal));
                $('#tva_amount').text(formatMoney(totalTva));
                $('#total_ttc').text(formatMoney(totalTTC));
            }

            function formatMoney(amount) {
                return amount.toLocaleString('fr-FR', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                }) + ' ' + currency;
            }

            // Prévisualisation
         // Prévisualisation
$('#previewInvoice').on('click', function () {
    if (!currentClient) {
        showMessage('Veuillez sélectionner un client', 'error', {
            duration: 3000
        });
        return;
    }

    if (currentProducts.length === 0) {
        showMessage('Veuillez ajouter au moins un produit', 'error', {
            duration: 3000
        });
        return;
    }

    generatePreview();
    $('#previewModal').show();
});

            $('#closePreview').on('click', function () {
                $('#previewModal').hide();
            });

            function generatePreview() {
                let subtotal = 0;
                let productsHtml = '';

                currentProducts.forEach(product => {
                    const totalHT = product.quantite * product.prix_unitaire;
                    subtotal += totalHT;

                    productsHtml += `
                    <tr>
                        <td>${product.designation}</td>
                        <td class="text-right">${product.quantite}</td>
                        <td class="text-right">${formatMoney(product.prix_unitaire)}</td>
                        <td class="text-right">${product.tva}%</td>
                        <td class="text-right">${formatMoney(totalHT)}</td>
                    </tr>
                `;
                });

                const tvaAmount = subtotal * (tvaRate / 100);
                const totalTTC = subtotal + tvaAmount;

                const previewHtml = `
                <div class="invoice-preview">
                    <div class="invoice-header">
                        <div class="company-info">
                         <?php if ($logoPath && file_exists($logoPath)): ?>
            <img src="<?= htmlspecialchars($logoPath) ?>" class="header-logo" alt="Logo" style="border-radius:50%;object-fit:cover;width:42px;height:42px;">
        <?php else: ?>
            <div class="header-logo placeholder">
                <i class="fas fa-building"></i>
            </div>
        <?php endif; ?>
                            <h2><?= htmlspecialchars($entreprise['nom']) ?></h2>
                            <p><?= htmlspecialchars($entreprise['adresse']) ?></p>
                            <p>Tél: <?= htmlspecialchars($entreprise['telephone']) ?></p>
                            <p>Email: <?= htmlspecialchars($entreprise['email']) ?></p>
                        </div>
                        
                        <div class="invoice-title">
                            <h1>FACTURE PROFORMA</h1>
                            <p>N°: ${$('#invoice_number').val()}</p>
                            <p>Date: ${formatDate($('#invoice_date').val())}</p>
                        </div>
                    </div>
                    
                    <div class="client-info">
                        <h3>Client</h3>
                        <p><strong>${currentClient.nom}</strong></p>
                        <p>${currentClient.adresse}</p>
                        <p>Tél: ${currentClient.telephone}</p>
                        <p>Email: ${currentClient.email}</p>
                    </div>
                    
                    <table class="products-table">
                        <thead>
                            <tr>
                                <th>Désignation</th>
                                <th>Qté</th>
                                <th>Prix unitaire</th>
                                <th>TVA</th>
                                <th>Total HT</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${productsHtml}
                        </tbody>
                    </table>
                    
                    <div class="totals-preview">
                        <div class="totals-grid">
                            <div></div>
                            <div class="totals-table">
                                <div class="total-row">
                                    <span>Total HT:</span>
                                    <span>${formatMoney(subtotal)}</span>
                                </div>
                                <div class="total-row">
                                    <span>TVA (${tvaRate}%):</span>
                                    <span>${formatMoney(tvaAmount)}</span>
                                </div>
                                <div class="total-row grand-total">
                                    <span>Total TTC:</span>
                                    <span>${formatMoney(totalTTC)}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="invoice-notes">
                        <p><strong>Notes:</strong></p>
                        <p>${$('#invoice_notes').val()}</p>
                    </div>
                </div>
            `;

                $('#invoicePreview').html(previewHtml);
            }

            function formatDate(dateString) {
                const date = new Date(dateString);
                return date.toLocaleDateString('fr-FR');
            }

            //       

            // Modification des gestionnaires d'événements
            $('#saveInvoice').on('click', function () {
                saveInvoice.call(this, false); // Préserve le contexte
            });

            $('#saveDraft').on('click', function () {
                saveInvoice.call(this, true); // Préserve le contexte
            });
            function saveInvoice(isDraft) {

                // currentAction = 'add'; // Valeur par défaut

                // Validation des données
                if (!currentClient) {
                    showNotification('Veuillez sélectionner un client', 'error');
                    return;
                }

                if (currentProducts.length === 0) {
                    showNotification('Veuillez ajouter au moins un produit', 'error');
                    return;
                }

                // Préparation des données
                const invoiceData = {
                    // Inclure l'ID seulement en mode édition
                    invoice_id: currentAction === 'edit' ? currentInvoiceId : null,
                    numero: $('#invoice_number').val(),
                    date_facture: $('#invoice_date').val(),
                    client_id: currentClient.id,
                    entreprise_id: <?= $entreprise['id'] ?>,
                    notes: $('#invoice_notes').val(),
                    is_draft: isDraft,
                    items: currentProducts.map(product => ({
                        id: product.id,
                        designation: product.designation,
                        quantite: parseInt(product.quantite) || 1,
                        prix_unitaire: parseFloat(product.prix_unitaire) || 0,
                        tva: parseFloat(product.tva) || 0
                    })),
                    totals: {
                        subtotal: parseFloat($('#subtotal').text().replace(/[^\d,.-]/g, '').replace(',', '.')) || 0,
                        tva: parseFloat($('#tva_amount').text().replace(/[^\d,.-]/g, '').replace(',', '.')) || 0,
                        total: parseFloat($('#total_ttc').text().replace(/[^\d,.-]/g, '').replace(',', '.')) || 0
                    }
                };

                // Gestion du bouton
                const btn = event.target.closest('button');
                const originalText = btn.innerHTML;
                btn.disabled = true;
                btn.innerHTML = `<i class="fas fa-spinner fa-spin"></i> ${isDraft ? 'Sauvegarde...' : 'Validation...'}`;

                // Déterminer l'URL en fonction du mode
                const url = currentAction === 'edit' ? 'api/update_invoice.php' : 'api/save_invoice.php';

                // Envoi des données
                fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(invoiceData)
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            showNotification(
                                isDraft ? 'Brouillon sauvegardé' : (currentAction === 'edit' ? 'Facture mise à jour' : 'Facture créée'),
                                'success'
                            );


                            if (!isDraft) {
                                resetFormAfterSubmit();

                                // Générer un nouveau numéro après 1,5s
                                setTimeout(() => {
                                    generateNextInvoiceNumber();
                                }, 1500);
                            }
                        } else {
                            showNotification(data.message || 'Erreur lors de la sauvegarde', 'error');
                        }
                    })
                    .catch(error => {
                        showNotification('Erreur réseau: ' + error.message, 'error');
                    })
                    .finally(() => {
                        btn.disabled = false;
                        btn.innerHTML = originalText;
                    });
            }

            function resetFormAfterSubmit() {
                // 1. Réinitialiser le mode
                currentAction = 'add';


                // Réinitialiser le client
                $('#client_id').val('');
                $('#client_name, #client_address, #client_contact').text('');
                $('#client_info').hide();
                $('#client_search').val('');
                currentClient = null;

                // Réinitialiser les produits
                currentProducts = [];
                renderProductsTable();

                // Réinitialiser les autres champs
                $('#invoice_date').val('');
                $('#invoice_notes').val('');

                // 5. Réinitialiser les totaux
                $('#subtotal, #tva_amount, #total_ttc').text('0.00');



                // Générer un nouveau numéro si nécessaire
                // if (!currentInvoiceId) {
                //     generateInvoiceNumber();
                // }
            }


            // Fonction helper pour afficher les erreurs (peut être remplacée par un système de notification)
            function showError(message) {
                console.error('Erreur:', message);
                alert(`Erreur: ${message}`);
            }

            // Téléchargement PDF
            $('#printInvoice, #printFromPreview').on('click', function () {
                // Créer un clone de la prévisualisation pour la conversion en PDF
                const element = document.getElementById('invoicePreview');
                const tempElement = element.cloneNode(true);
                tempElement.style.width = '800px';
                tempElement.style.padding = '20px';
                tempElement.style.background = 'white';
                document.body.appendChild(tempElement);

                // Options pour html2canvas
                const options = {
                    scale: 2, // Augmente la qualité
                    useCORS: true,
                    allowTaint: true,
                    scrollX: 0,
                    scrollY: 0
                };

                // Convertir en canvas puis en PDF
                html2canvas(tempElement, options).then(canvas => {
                    // Supprimer l'élément temporaire
                    document.body.removeChild(tempElement);

                    // Créer le PDF
                    const { jsPDF } = window.jspdf;
                    const pdf = new jsPDF('p', 'mm', 'a4');
                    const imgData = canvas.toDataURL('image/png');

                    // Calculer les dimensions pour ajuster à la page A4
                    const pdfWidth = pdf.internal.pageSize.getWidth();
                    const pdfHeight = (canvas.height * pdfWidth) / canvas.width;

                    // Ajouter l'image au PDF
                    pdf.addImage(imgData, 'PNG', 0, 0, pdfWidth, pdfHeight);

                    // Télécharger le PDF
                    const fileName = `Facture_${$('#invoice_number').val()}.pdf`;
                    pdf.save(fileName);
                }).catch(err => {
                    console.error('Erreur lors de la génération du PDF:', err);
                    alert('Une erreur est survenue lors de la génération du PDF');
                });
            });


            function showMessage(text, type = 'info', options = {}) {
                const container = document.getElementById('messageContainer');
                const message = document.createElement('div');
                message.className = `message message-${type}`;

                const icons = {
                    success: 'fa-check-circle',
                    error: 'fa-times-circle',
                    warning: 'fa-exclamation-triangle',
                    info: 'fa-info-circle',
                    confirm: 'fa-question-circle'
                };

                let html = `
        <div class="message-content">
            <div class="message-icon"><i class="fas ${icons[type] || 'fa-info-circle'}"></i></div>
            <div class="message-text">${text}</div>
        </div>
        <div class="message-actions">
    `;

                if (options.actions) {
                    options.actions.forEach(action => {
                        html += `<button class="${action.class || ''}">${action.text}</button>`;
                    });
                } else {
                    html += `<button class="btn-close"><i class="fas fa-times"></i></button>`;
                }

                html += `</div>`;
                message.innerHTML = html;
                container.appendChild(message);

                // Gestion des événements
                if (options.actions) {
                    const buttons = message.querySelectorAll('.message-actions button');
                    options.actions.forEach((action, index) => {
                        if (action.clickHandler) {
                            buttons[index].addEventListener('click', action.clickHandler);
                        }
                        buttons[index].addEventListener('click', () => closeMessage(message));
                    });
                } else {
                    message.querySelector('.btn-close').addEventListener('click', () => closeMessage(message));
                    if (options.timeout !== false) {
                        setTimeout(() => closeMessage(message), options.duration || 4000);
                    }
                }

                return message;
            }

            function closeMessage(message) {
                message.classList.add('hide');
                setTimeout(() => message.remove(), 300);
            }

            $('#cancelInvoice').on('click', function () {
                showMessage('Voulez-vous vraiment annuler cette facture ? Toutes les modifications seront perdues.', 'confirm', {
                    actions: [
                        {
                            text: 'Oui',
                            class: 'btn-confirm',
                            clickHandler: function () {
                                window.location.href = 'factures.php';
                            }
                        },
                        {
                            text: 'Non',
                            class: 'btn-cancel'
                        }
                    ],
                    timeout: false
                });
            });

            // Retour à l'édition depuis la prévisualisation
            $('#editFromPreview').on('click', function () {
                $('#previewModal').hide();
            });

            // Gestion du menu toggle
            $('#menuToggle').on('click', function () {
                $('.dashboard-sidebar').toggleClass('active');
                $('body').toggleClass('sidebar-active');
            });
        });
    </script>
</body>

</html>