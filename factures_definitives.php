<?php
session_start();
require_once 'config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$logoPath = null;

if (isset($_SESSION['user_id'])) {
    require_once 'config/db.php';

    try {
        $stmt = $pdo->prepare("SELECT logo FROM entreprise WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $logoPath = $stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log("Erreur lors de la récupération du logo: " . $e->getMessage());
        $logoPath = null;
    }
}

// Récupérer les informations de l'utilisateur
$stmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Récupérer l'entreprise de l'utilisateur
$entreprise = $pdo->prepare("SELECT * FROM entreprise WHERE user_id = ?");
$entreprise->execute([$_SESSION['user_id']]);
$entreprise = $entreprise->fetch();

if (!$entreprise) {
    die("Aucune entreprise associée à cet utilisateur");
}

// Récupérer les paramètres de filtre
$date_debut = isset($_GET['date_debut']) ? $_GET['date_debut'] : '';
$date_fin = isset($_GET['date_fin']) ? $_GET['date_fin'] : '';
$produit_id = isset($_GET['produit_id']) ? $_GET['produit_id'] : '';

// Construire la requête avec les filtres
$query = "
    SELECT f.id, f.numero, f.date_facture, c.nom as client_nom, f.montant_ttc
    FROM facture f
    LEFT JOIN client c ON f.client_id = c.id
    WHERE f.entreprise_id = ?
";

$params = [$entreprise['id']];

// Ajouter les filtres si spécifiés
if (!empty($date_debut)) {
    $query .= " AND f.date_facture >= ?";
    $params[] = $date_debut;
}

if (!empty($date_fin)) {
    $query .= " AND f.date_facture <= ?";
    $params[] = $date_fin;
}

if (!empty($produit_id)) {
    $query .= " AND f.id IN (SELECT facture_id FROM item_facture WHERE produit_id = ?)";
    $params[] = $produit_id;
}

$query .= " ORDER BY f.created_at DESC";

// Exécuter la requête
$factures = $pdo->prepare($query);
$factures->execute($params);
$factures = $factures->fetchAll();

// Récupérer le total des factures filtrées
$total_query = "SELECT SUM(f.montant_ttc) as total FROM facture f WHERE f.entreprise_id = ?";
$total_params = [$entreprise['id']];

if (!empty($date_debut)) {
    $total_query .= " AND f.date_facture >= ?";
    $total_params[] = $date_debut;
}

if (!empty($date_fin)) {
    $total_query .= " AND f.date_facture <= ?";
    $total_params[] = $date_fin;
}

if (!empty($produit_id)) {
    $total_query .= " AND f.id IN (SELECT facture_id FROM item_facture WHERE produit_id = ?)";
    $total_params[] = $produit_id;
}

$total_stmt = $pdo->prepare($total_query);
$total_stmt->execute($total_params);
$total = $total_stmt->fetchColumn();

// Récupérer les clients qui achètent le plus (pour le rapport)
$top_clients_query = "
    SELECT c.nom, COUNT(f.id) as nombre_factures, SUM(f.montant_ttc) as total_achats
    FROM facture f
    JOIN client c ON f.client_id = c.id
    WHERE f.entreprise_id = ?
";

$top_clients_params = [$entreprise['id']];

if (!empty($date_debut)) {
    $top_clients_query .= " AND f.date_facture >= ?";
    $top_clients_params[] = $date_debut;
}

if (!empty($date_fin)) {
    $top_clients_query .= " AND f.date_facture <= ?";
    $top_clients_params[] = $date_fin;
}

$top_clients_query .= " GROUP BY c.id ORDER BY total_achats DESC LIMIT 5";

$top_clients_stmt = $pdo->prepare($top_clients_query);
$top_clients_stmt->execute($top_clients_params);
$top_clients = $top_clients_stmt->fetchAll();

// Récupérer tous les produits vendus (pas seulement les plus vendus)
$all_produits_query = "
    SELECT p.designation, SUM(ifp.quantite) as total_quantite, SUM(ifp.montant_ht) as total_ht
    FROM item_facture ifp
    JOIN produit p ON ifp.produit_id = p.id
    JOIN facture f ON ifp.facture_id = f.id
    WHERE f.entreprise_id = ?
";

$all_produits_params = [$entreprise['id']];

if (!empty($date_debut)) {
    $all_produits_query .= " AND f.date_facture >= ?";
    $all_produits_params[] = $date_debut;
}

if (!empty($date_fin)) {
    $all_produits_query .= " AND f.date_facture <= ?";
    $all_produits_params[] = $date_fin;
}

$all_produits_query .= " GROUP BY p.id ORDER BY p.designation";

$all_produits_stmt = $pdo->prepare($all_produits_query);
$all_produits_stmt->execute($all_produits_params);
$all_produits = $all_produits_stmt->fetchAll();


// Récupérer la liste des produits pour le filtre
$produits = $pdo->query("SELECT id, designation FROM produit ORDER BY designation")->fetchAll();

// Récupérer les produits les plus vendus (pour le rapport)
$top_produits_query = "
    SELECT p.designation, SUM(ifp.quantite) as total_quantite, SUM(ifp.montant_ht) as total_ht
    FROM item_facture ifp
    JOIN produit p ON ifp.produit_id = p.id
    JOIN facture f ON ifp.facture_id = f.id
    WHERE f.entreprise_id = ?
";

$top_produits_params = [$entreprise['id']];

if (!empty($date_debut)) {
    $top_produits_query .= " AND f.date_facture >= ?";
    $top_produits_params[] = $date_debut;
}

if (!empty($date_fin)) {
    $top_produits_query .= " AND f.date_facture <= ?";
    $top_produits_params[] = $date_fin;
}

$top_produits_query .= " GROUP BY p.id ORDER BY total_quantite DESC LIMIT 10";

$top_produits_stmt = $pdo->prepare($top_produits_query);
$top_produits_stmt->execute($top_produits_params);
$top_produits = $top_produits_stmt->fetchAll();
// Dans la requête initiale des factures, remplacez par :
$query = "
    SELECT f.id, f.numero, f.date_facture, c.nom as client_nom, c.telephone as client_tel, f.montant_ttc
    FROM facture f
    LEFT JOIN client c ON f.client_id = c.id
    WHERE f.entreprise_id = ?
";
?>



<!DOCTYPE html>
<html lang="fr">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historique des Factures</title>
    <!-- Ajoutez ces lignes dans le <head> -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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

        /* Table Styles */
        .table-container {
            background-color: var(--table-bg);
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            border: 1px solid rgba(0, 0, 0, 0.03);
        }

        .table-title-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }

        .table-title {
            font-size: 1.4rem;
            color: var(--dark-color);
            position: relative;
        }

        .table-title::after {
            content: '';
            position: absolute;
            bottom: -8px;
            left: 0;
            width: 40px;
            height: 3px;
            background: linear-gradient(90deg, var(--primary-color), var(--accent-color));
            border-radius: 2px;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
        }

        .data-table th,
        .data-table td {
            padding: 15px 20px;
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

        .data-table tr:last-child td {
            border-bottom: none;
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
            background: rgb(224, 145, 49);
            color: white;
            box-shadow: 0 4px 10px rgba(209, 132, 16, 0.95);
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, var(--secondary-color), var(--primary-color));
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(163, 147, 5, 0.9);
        }

        .btn-warning {
            background-color: var(--warning-color);
            color: var(--dark-color);
        }

        .btn-danger {
            background-color: var(--danger-color);
            color: white;
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 0.8rem;
        }

        /* Invoice Actions */
        .invoice-actions {
            display: flex;
            gap: 8px;
        }

        /* Modal Styles */
        .invoice-preview-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0, 0, 0, 0.7);
            z-index: 1100;
            overflow-y: auto;
            padding: 40px 20px;
        }

        .invoice-preview-container {
            background-color: white;
            max-width: 900px;
            margin: 0 auto;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 5px 30px rgba(0, 0, 0, 0.3);
            position: relative;
        }

        .close-preview {
            position: absolute;
            top: 20px;
            right: 30px;
            font-size: 30px;
            color: var(--gray-color);
            cursor: pointer;
            transition: color 0.3s;
        }

        .close-preview:hover {
            color: var(--danger-color);
        }

        /* Responsive Styles */
        @media (max-width: 992px) {
            .dashboard-sidebar {
                transform: translateX(-100%);
            }

            .dashboard-sidebar.active {
                transform: translateX(0);
                a
            }

            .dashboard-main {
                margin-left: 0;
                padding: 20px;
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
            .dashboard-header {
                padding: 0 15px;
            }

            .user-menu span {
                display: none;
            }

            .invoice-actions {
                flex-direction: column;
                gap: 5px;
            }
        }



        .search-box {
            position: relative;
            width: 100%;
            max-width: 400px;
            margin-bottom: 10px;
        }

        .search-box i.fas.fa-search {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
        }

        .search-box input {
            width: 100%;
            padding: 8px 15px 8px 35px;
            border: 1px solid #ced4da;
            border-radius: 4px;
            font-size: 14px;
        }

        .search-box input:focus {
            outline: none;
            border-color: #80bdff;
        }

        /* Styles pour le tableau scrollable */
        .scrollable-table-wrapper {
            overflow-y: auto;
            max-height: 300px;
            /* Ajustez cette valeur selon vos besoins */
            position: relative;
            border: 1px solid #dee2e6;
            /* Optionnel - pour une meilleure visibilité */
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
        }

        .data-table thead th {
            position: sticky;
            top: 0;
            background-color: #f8f9fa;
            /* Couleur de fond de l'en-tête */
            z-index: 10;
            padding: 12px 15px;
            text-align: left;
            font-weight: 600;
            border-bottom: 2px solid #dee2e6;
        }

        .data-table tbody td {
            padding: 10px 15px;
            border-bottom: 1px solid #eee;
        }

        .data-table tbody tr:hover {
            background-color: #f5f5f5;
            /* Effet de survol */
        }

        /* Styles pour la modal de confirmation */
        .confirmation-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0, 0, 0, 0.7);
            z-index: 1100;
            align-items: center;
            justify-content: center;
        }

        .confirmation-content {
            background-color: white;
            padding: 30px;
            border-radius: 10px;
            width: 400px;
            max-width: 90%;
            text-align: center;
        }

        .confirmation-buttons {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-top: 25px;
        }

        .confirmation-btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .confirmation-btn.facture {
            background-color: #6c5ce7;
            color: white;
        }

        .confirmation-btn.recu {
            background-color: #00b894;
            color: white;
        }

        .confirmation-btn.cancel {
            background-color: #d63031;
            color: white;
        }

        /* Style pour le reçu */
        /* .receipt-container {
    font-family: Arial, sans-serif;
    max-width: 500px;
    margin: 0 auto;
    padding: 20px;
    border: 1px solid #ddd;
    border-radius: 5px;
} */

        .receipt-header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 1px dashed #ddd;
            padding-bottom: 15px;
        }

        .receipt-header h2 {
            color: #2d3436;
            margin-bottom: 5px;
        }

        .receipt-details {
            margin: 20px 0;
        }

        .receipt-details p {
            margin: 8px 0;
            display: flex;
            justify-content: space-between;
        }

        .receipt-details p span:first-child {
            font-weight: bold;
        }

        .receipt-footer {
            text-align: center;
            margin-top: 30px;
            font-size: 12px;
            color: #636e72;
        }

        .receipt-container {
            background-color: white;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

        .receipt-container h2,
        .receipt-container h3 {
            color: #2c3e50;
        }

        .receipt-container table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }

        .receipt-container th {
            background-color: #f8f9fa;
            text-align: left;
            padding: 8px;
            border-bottom: 2px solid #ddd;
        }

        .receipt-container td {
            padding: 8px;
            border-bottom: 1px solid #eee;
        }

        .receipt-container .total-row {
            font-weight: bold;
            border-top: 2px solid #ddd;
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
                <li><a href="factures.php"><i class="fas fa-file-invoice"></i> Factures ProForma</a></li>
                <li><a href="historique.php"><i class="fas fa-history"></i> Historique</a></li>
                <li><a href="factures_definitives.php" class="active"><i class="fas fa-file-invoice-dollar"></i>
                        Facture</a></li>
                <li><a href="editorial.php"><i class="fas fa-chart-line me-2"></i> Statistiques</a></li>
                <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Déconnexion</a></li>
            </ul>
        </aside>

        <main class="dashboard-main">
            <h1 class="page-title">Factures Définitives</h1>
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" id="factureSearch" placeholder="Rechercher par n°, client ou ...">
            </div>
            <!-- Formulaire de filtres -->
            <div class="filter-container"
                style="background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
                <form id="filterForm" method="GET">
                    <div style="display: flex; flex-wrap: wrap; gap: 15px; align-items: flex-end;">
                        <div>
                            <label for="date_debut" style="display: block; margin-bottom: 5px; font-weight: 500;">Date
                                début</label>
                            <input type="date" id="date_debut" name="date_debut"
                                value="<?= htmlspecialchars($date_debut) ?>" class="form-control"
                                style="padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                        </div>
                        <div>
                            <label for="date_fin" style="display: block; margin-bottom: 5px; font-weight: 500;">Date
                                fin</label>
                            <input type="date" id="date_fin" name="date_fin" value="<?= htmlspecialchars($date_fin) ?>"
                                class="form-control" style="padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                        </div>
                        <div>
                            <label for="produit_id"
                                style="display: block; margin-bottom: 5px; font-weight: 500;">Produit</label>
                            <select id="produit_id" name="produit_id" class="form-control"
                                style="padding: 8px; border: 1px solid #ddd; border-radius: 4px; min-width: 200px;">
                                <option value="">Tous les produits</option>
                                <?php foreach ($produits as $produit): ?>
                                    <option value="<?= $produit['id'] ?>" <?= $produit_id == $produit['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($produit['designation']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <button type="submit" class="btn btn-primary" style="padding: 8px 15px;">
                                <i class="fas fa-filter"></i> Filtrer
                            </button>
                            <button type="button" id="resetFilters" class="btn"
                                style="padding: 8px 15px; background: #f0f0f0; border: 1px solid #ddd;">
                                <i class="fas fa-times"></i> Réinitialiser
                            </button>
                        </div>
                    </div>
                </form>

                <!-- Rapport complet (visible seulement quand on filtre) -->
                <?php if ($date_debut || $date_fin): ?>
                    <div class="table-container" style="margin-bottom: 20px;">
                        <div class="table-title-container">
                            <h2 class="table-title">Rapport complet</h2>
                            <button id="downloadFullReport" class="btn btn-primary">
                                <i class="fas fa-file-pdf"></i> Télécharger rapport complet
                            </button>
                        </div>

                        <!-- Section Produits vendus -->
                        <h3 style="margin: 20px 0 15px; color: var(--primary-color);">Produits vendus</h3>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Produit</th>
                                    <th>Quantité vendue</th>
                                    <th>Montant HT</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($all_produits as $produit): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($produit['designation']) ?></td>
                                        <td><?= $produit['total_quantite'] ?></td>
                                        <td><?= number_format($produit['total_ht'], 2, ',', ' ') ?>
                                            <?= htmlspecialchars($entreprise['devise_symbole'] ?? 'XOF') ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>



                    </div>
                <?php endif; ?>


                <div class="table-container" style="margin-top: 20px;">
                    <div class="table-title-container">
                        <h2 class="table-title">Toutes les factures</h2>
                    </div>

                    <div class="scrollable-table-wrapper"> <!-- Nouveau conteneur pour le scroll -->
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>N° Facture</th>
                                    <th>Date</th>
                                    <th>Client</th>
                                    <th>Montant</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($factures as $facture): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($facture['numero']) ?></td>
                                        <td><?= date('d/m/Y', strtotime($facture['date_facture'])) ?></td>
                                        <td><?= htmlspecialchars($facture['client_nom'] ?? 'Non spécifié') ?></td>
                                        <td><?= number_format($facture['montant_ttc'], 2, ',', ' ') ?>
                                            <?= htmlspecialchars($entreprise['devise_symbole'] ?? 'XOF') ?>
                                        </td>
                                        <td class="invoice-actions">
                                            <button class="btn btn-primary btn-sm view-invoice"
                                                data-id="<?= $facture['id'] ?>">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <!-- Modal de confirmation Facture/Reçu -->
                <div id="confirmationModal" class="confirmation-modal">
                    <div class="confirmation-content">
                        <h3>Que souhaitez-vous visualiser ?</h3>
                        <div class="confirmation-buttons">
                            <button class="confirmation-btn facture" id="viewFactureBtn">
                                <i class="fas fa-file-invoice"></i> Facture
                            </button>
                            <button class="confirmation-btn recu" id="viewRecuBtn">
                                <i class="fas fa-receipt"></i> Reçu
                            </button>
                            <button class="confirmation-btn cancel" id="cancelViewBtn">
                                <i class="fas fa-times"></i> Annuler
                            </button>
                        </div>
                    </div>
                </div>

        </main>


        <!-- Modal pour le rapport -->
        <div id="reportModal"
            style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 2000; align-items: center; justify-content: center;">
            <div
                style="background: white; padding: 20px; border-radius: 8px; max-width: 800px; width: 90%; max-height: 90vh; overflow-y: auto;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h3 id="reportTitle">Rapport des factures</h3>
                    <span style="cursor: pointer; font-size: 24px;"
                        onclick="document.getElementById('reportModal').style.display = 'none'">&times;</span>
                </div>
                <div id="reportContent">
                    <!-- Le contenu du rapport sera généré ici -->
                </div>
                <div style="text-align: center; margin-top: 20px;">
                    <button id="generatePdf" class="btn btn-primary">
                        <i class="fas fa-file-pdf"></i> Générer PDF
                    </button>
                </div>
            </div>
        </div>

    </div>

    <!-- Ajoutez ce script avant la fermeture du body -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>

    <script>
        const totalFactures = <?= json_encode(number_format($total, 2, ',', ' ')) ?>;
        const devise = <?= json_encode($entreprise['devise_symbole'] ?? 'XOF') ?>;
        $(document).ready(function () {


            // Gestion du menu toggle
            const menuToggle = document.getElementById('menuToggle');
            const sidebar = document.querySelector('.dashboard-sidebar');
            const body = document.body;

            if (menuToggle && sidebar) {
                menuToggle.addEventListener('click', function () {
                    sidebar.classList.toggle('active');
                    body.classList.toggle('sidebar-active');
                });

                // Fermeture en cliquant à l'extérieur
                document.addEventListener('click', function (event) {
                    if (window.innerWidth <= 992 &&
                        !sidebar.contains(event.target) &&
                        event.target !== menuToggle &&
                        !menuToggle.contains(event.target)) {
                        sidebar.classList.remove('active');
                        body.classList.remove('sidebar-active');
                    }
                });
            }

            // Recherche de factures
            $('#factureSearch').on('input', function () {
                const searchTerm = $(this).val().toLowerCase();
                $('.data-table tbody tr').each(function () {
                    const rowText = $(this).text().toLowerCase();
                    $(this).toggle(rowText.includes(searchTerm));
                });
            });
            // Variables globales
            let currentInvoiceId = null;
            let currentInvoiceNumber = '';
            let currentInvoiceData = null;

            // Visualisation d'une facture - ouverture de la modal de confirmation
            $(document).on('click', '.view-invoice', function () {
                const invoiceId = $(this).data('id');
                currentInvoiceId = invoiceId;
                const btn = $(this);

                btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');

                // Récupérer les données de la facture
                $.ajax({
                    url: 'api/get_factures.php',
                    method: 'GET',
                    data: { id: invoiceId },
                    success: function (response) {
                        currentInvoiceData = response;
                        currentInvoiceNumber = $(response).filter('#invoice_number').val() ||
                            $(response).find('#invoice_number').text() ||
                            'FACT_' + invoiceId;

                        // Afficher la modal de confirmation
                        $('#confirmationModal').css('display', 'flex');
                    },
                    error: function (xhr) {
                        alert('Erreur: ' + xhr.responseText);
                    },
                    complete: function () {
                        btn.prop('disabled', false).html('<i class="fas fa-eye"></i>');
                    }
                });
            });

            // Gestion des boutons de la modal de confirmation
            $('#viewFactureBtn').click(function () {
                $('#confirmationModal').hide();
                showInvoicePreview(currentInvoiceData);
            });

            $('#viewRecuBtn').click(function () {
                $('#confirmationModal').hide();
                showReceiptPreview(currentInvoiceData);
            });

            $('#cancelViewBtn, #confirmationModal').click(function (e) {
                if ($(e.target).is('#cancelViewBtn') || $(e.target).is('#confirmationModal')) {
                    $('#confirmationModal').hide();
                }
            });

            // Fonction pour afficher la prévisualisation de la facture
            function showInvoicePreview(data) {
                // Créer le modal s'il n'existe pas
                if ($('#invoicePreviewModal').length === 0) {
                    $('body').append(`
            <div id="invoicePreviewModal" class="invoice-preview-modal">
                <span class="close-preview">&times;</span>
                <div class="invoice-preview-container" id="invoicePreviewContent"></div>
                <div class="modal-actions" style="text-align:center;margin-top:20px;">
                    <button id="printFromPreview" class="btn btn-primary">
                        <i class="fas fa-file-pdf"></i> Télécharger PDF
                    </button>
                </div>
            </div>
        `);

                    // Gestion de la fermeture
                    $('.close-preview, #invoicePreviewModal').click(function (e) {
                        if ($(e.target).is('.close-preview') || $(e.target).is('#invoicePreviewModal')) {
                            $('#invoicePreviewModal').hide();
                        }
                    });
                }

                $('#invoicePreviewContent').html(data);
                $('#invoicePreviewModal').show();
            }

            // Fonction pour afficher le reçu
            function showReceiptPreview(data) {
                // Extraire les données nécessaires du HTML de la facture
                const $data = $(data);

                // Récupérer les informations essentielles
                const invoiceNumber = currentInvoiceNumber;
                const clientName = $data.find('.client-info p strong').first().text().trim();
                const clientPhone = $data.find('.client-info p:contains("Tél:")').text().replace('Tél:', '').trim() || 'Non spécifié';
                const totalAmount = $data.find('.grand-total span:last-child').text().trim();

                // Récupérer les articles de la facture
                const items = [];
                $data.find('.products-table tbody tr').each(function () {
                    const cols = $(this).find('td');
                    items.push({
                        designation: cols.eq(0).text().trim(),
                        quantite: cols.eq(1).text().trim(),
                        prixUnitaire: cols.eq(2).text().trim(), // Prix unitaire TTC
                        total: cols.eq(3).text().trim() // Total TTC par article
                    });
                });

                // Récupérer les informations de l'entreprise
                const companyName = $data.find('.company-info h2').text().trim();
                const companyPhone = $data.find('.company-info p:contains("Tél:")').text().replace('Tél:', '').trim();
                const companyAddress = $data.find('.company-info p:not(:has(strong))').first().text().trim();

                // Créer le HTML du reçu 
                const receiptHTML = `
        <div class="receipt-container" id="receiptContent">
            <!-- En-tête avec informations entreprise et client -->
            <div style="display: flex; justify-content: space-between; margin-bottom: 20px;padding: 10px;
             border-bottom: 1px solid #ddd; padding-bottom: 15px;">
                <div style="text-align: left;">
                    <h2 style="margin: 0 0 5px 0; color: #333; font-size: 1.2em;">${companyName}</h2>
                    <p style="margin: 3px 0; font-size: 0.9em;">${companyAddress}</p>
                    <p style="margin: 3px 0; font-size: 0.9em;">Tél: ${companyPhone}</p>
                </div>
                <div style="text-align: right;">
                    <h3 style="margin: 0 0 10px 0; color: #2c3e50; font-size: 1.1em;">REÇU DE PAIEMENT</h3>
                    <p style="margin: 3px 0; font-size: 0.9em;"><strong>N°:</strong> RC-${invoiceNumber}</p>
                    <p style="margin: 3px 0; font-size: 0.9em;"><strong>Date:</strong> ${new Date().toLocaleDateString('fr-FR')}</p>
                </div>
            </div>
            
            <!-- Informations client -->
            <div style="display: flex; justify-content: space-between; margin-bottom: 15px;">
                <div>
                    <p style="margin: 5px 0;"><strong>Client:</strong> ${clientName}</p>
                    <p style="margin: 5px 0;"><strong>Tél:</strong> ${clientPhone}</p>
                </div>
            </div>
            
            <!-- Tableau des articles -->
            <table style="width: 100%; border-collapse: collapse; margin-bottom: 20px; font-size: 0.9em;">
                <thead>
                    <tr style="background-color: #f5f5f5;">
                        <th style="padding: 8px; text-align: left; border-bottom: 1px solid #ddd;">Désignation</th>
                        <th style="padding: 8px; text-align: center; border-bottom: 1px solid #ddd; width: 15%;">Qté</th>
                        <th style="padding: 8px; text-align: right; border-bottom: 1px solid #ddd; width: 25%;">Prix U. TTC</th>
                        <th style="padding: 8px; text-align: right; border-bottom: 1px solid #ddd; width: 25%;">Total</th>
                    </tr>
                </thead>
                <tbody>
                    ${items.map(item => `
                        <tr>
                            <td style="padding: 8px; text-align: left; border-bottom: 1px solid #eee;">${item.designation}</td>
                            <td style="padding: 8px; text-align: center; border-bottom: 1px solid #eee;">${item.quantite}</td>
                            <td style="padding: 8px; text-align: right; border-bottom: 1px solid #eee;">${item.prixUnitaire}</td>
                            <td style="padding: 8px; text-align: right; border-bottom: 1px solid #eee;">${item.total}</td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
            
            <!-- Total -->
            <div style="text-align: right; margin-top: 10px; padding-top: 10px; border-top: 1px solid #ddd;">
                <p style="font-size: 1.1em; font-weight: bold; margin: 10px 0;">
                    <span style="margin-right: 20px;">Total TTC:</span>
                    <span>${totalAmount}</span>
                </p>
            </div>
            
            <!-- Pied de page -->
            <div style="text-align: center; margin-top: 30px; padding-top: 15px; border-top: 1px dashed #ddd; color: #777; font-size: 0.8em;">
                <p>Merci pour votre confiance</p>
                <p>Ce reçu fait foi de paiement</p>
            </div>
        </div>
    `;

                // Afficher le reçu dans la modal
                if ($('#receiptPreviewModal').length === 0) {
                    $('body').append(`
            <div id="receiptPreviewModal" class="invoice-preview-modal">
                <span class="close-preview">&times;</span>
                <div class="invoice-preview-container" id="receiptPreviewContent" style="max-width: 600px;"></div>
                <div class="modal-actions" style="text-align:center;margin-top:20px;">
                    <button id="printReceipt" class="btn btn-primary">
                        <i class="fas fa-file-pdf"></i> Télécharger Reçu
                    </button>
                </div>
            </div>
        `);

                    // Gestion de la fermeture
                    $('.close-preview, #receiptPreviewModal').click(function (e) {
                        if ($(e.target).is('.close-preview') || $(e.target).is('#receiptPreviewModal')) {
                            $('#receiptPreviewModal').hide();
                        }
                    });
                }

                $('#receiptPreviewContent').html(receiptHTML);
                $('#receiptPreviewModal').show();
            }
            // Téléchargement du reçu
            $(document).on('click', '#printReceipt', function () {
                const { jsPDF } = window.jspdf;
                const element = document.getElementById('receiptContent');

                const options = {
                    scale: 2,
                    useCORS: true,
                    allowTaint: true,
                    scrollX: 0,
                    scrollY: 0
                };

                const btn = $(this);
                const btnText = btn.html();
                btn.html('<i class="fas fa-spinner fa-spin"></i> Génération...');

                html2canvas(element, options).then(canvas => {
                    const pdf = new jsPDF('p', 'mm', 'a4');
                    const imgData = canvas.toDataURL('image/png');

                    // Calculer les dimensions pour centrer le reçu
                    const pdfWidth = pdf.internal.pageSize.getWidth() - 40;
                    const pdfHeight = (canvas.height * pdfWidth) / canvas.width;
                    const position = (pdf.internal.pageSize.getHeight() - pdfHeight) / 2;

                    pdf.addImage(imgData, 'PNG', 20, position, pdfWidth, pdfHeight);

                    const fileName = `Recu_${currentInvoiceNumber}.pdf`;
                    pdf.save(fileName);

                    btn.html(btnText);
                }).catch(err => {
                    console.error('Erreur:', err);
                    alert('Erreur lors de la génération du PDF');
                    btn.html(btnText);
                });
            });



            // Téléchargement PDF
            $(document).on('click', '#printFromPreview, #printInvoice', function () {
                const { jsPDF } = window.jspdf;

                // Options pour html2canvas
                const options = {
                    scale: 2,
                    useCORS: true,
                    allowTaint: true,
                    scrollX: 0,
                    scrollY: 0,
                    windowWidth: 800
                };

                // Ciblez le contenu de la facture
                const element = document.getElementById('invoicePreviewContent');

                // Afficher un indicateur de chargement
                const loadingText = $('#printFromPreview').html();
                $('#printFromPreview').html('<i class="fas fa-spinner fa-spin"></i> Génération...');

                html2canvas(element, options).then(canvas => {
                    // Créer le PDF
                    const pdf = new jsPDF('p', 'mm', 'a4');
                    const imgData = canvas.toDataURL('image/png');

                    // Calculer les dimensions
                    const pdfWidth = pdf.internal.pageSize.getWidth() - 20; // Marges
                    const pdfHeight = (canvas.height * pdfWidth) / canvas.width;

                    // Position Y centrée
                    const position = 10;

                    // Ajouter l'image au PDF
                    pdf.addImage(imgData, 'PNG', 10, position, pdfWidth, pdfHeight);

                    // Télécharger le PDF
                    const fileName = `Facture_${currentInvoiceNumber}.pdf`;
                    pdf.save(fileName);

                    // Restaurer le texte du bouton
                    $('#printFromPreview').html(loadingText);
                }).catch(err => {
                    console.error('Erreur:', err);
                    alert('Erreur lors de la génération du PDF');
                    $('#printFromPreview').html(loadingText);
                });
            });

        });
        $(document).ready(function () {
            // Réinitialiser les filtres
            $('#resetFilters').click(function () {
                $('#date_debut').val('');
                $('#date_fin').val('');
                $('#produit_id').val('');
                $('#filterForm').submit();
            });



            // Télécharger le rapport complet
            $('#downloadFullReport').click(function () {
                const dateDebut = $('#date_debut').val();
                const dateFin = $('#date_fin').val();
                const produitId = $('#produit_id').val();
                const produitNom = $('#produit_id option:selected').text();

                let reportContent = `
        <h4 style="text-align: center; margin-bottom: 20px;">Rapport complet des ventes</h4>
        <p style="margin-bottom: 10px;"><strong>Période :</strong> ${dateDebut ? dateDebut : 'Début'} - ${dateFin ? dateFin : 'Aujourd\'hui'}</p>
        ${produitId ? `<p style="margin-bottom: 20px;"><strong>Produit filtré :</strong> ${produitNom}</p>` : ''}
        
        <h5 style="border-bottom: 2px solid #eee; padding-bottom: 5px; margin: 30px 0 15px;">Produits vendus</h5>
        <table style="width: 100%; border-collapse: collapse; margin-bottom: 30px;">
            <thead>
                <tr style="background: #f5f5f5;">
                    <th style="padding: 10px; border: 1px solid #ddd; text-align: left;">Produit</th>
                    <th style="padding: 10px; border: 1px solid #ddd; text-align: right;">Quantité</th>
                    <th style="padding: 10px; border: 1px solid #ddd; text-align: right;">Montant HT</th>
                </tr>
            </thead>
            <tbody>
    `;

                // Cibler spécifiquement le tableau des produits
                $('.table-container:first .data-table tbody tr').each(function () {
                    const cells = $(this).find('td');
                    const isHighlighted = $(this).css('background-color') === 'rgb(255, 248, 225)';

                    reportContent += `
            <tr style="${isHighlighted ? 'background-color: #fff8e1; font-weight: bold;' : ''}">
                <td style="padding: 8px; border: 1px solid #ddd;">${cells.eq(0).text()}</td>
                <td style="padding: 8px; border: 1px solid #ddd; text-align: right;">${cells.eq(1).text()}</td>
                <td style="padding: 8px; border: 1px solid #ddd; text-align: right;">${cells.eq(2).text()}</td>
            </tr>
        `;
                });




                reportContent += `
            </tbody>
        </table>
        
        <h5 style="border-bottom: 2px solid #eee; padding-bottom: 5px; margin: 30px 0 15px;">Liste des factures</h5>
        <table style="width: 100%; border-collapse: collapse; margin-bottom: 30px;">
            <thead>
                <tr style="background: #f5f5f5;">
                    <th style="padding: 10px; border: 1px solid #ddd; text-align: left;">N° Facture</th>
                    <th style="padding: 10px; border: 1px solid #ddd; text-align: left;">Date</th>
                    <th style="padding: 10px; border: 1px solid #ddd; text-align: left;">Client</th>
                    <th style="padding: 10px; border: 1px solid #ddd; text-align: right;">Montant TTC</th>
                </tr>
            </thead>
            <tbody>
    `;

                // Ajouter les factures
                $('.table-container:has(h2.table-title:contains("Toutes les factures")) table.data-table tbody tr').each(function () {
                    const cells = $(this).find('td');
                    reportContent += `
            <tr>
                <td style="padding: 8px; border: 1px solid #ddd;">${cells.eq(0).text()}</td>
                <td style="padding: 8px; border: 1px solid #ddd;">${cells.eq(1).text()}</td>
                <td style="padding: 8px; border: 1px solid #ddd;">${cells.eq(2).text()}</td>
                <td style="padding: 8px; border: 1px solid #ddd; text-align: right;">${cells.eq(3).text()}</td>
            </tr>
        `;
                });

                reportContent += `
            </tbody>
            <tfoot>
                <tr style="background: #f5f5f5; font-weight: bold;">
                    <td colspan="3" style="padding: 10px; border: 1px solid #ddd; text-align: right;">Total :</td>
                    <td style="padding: 10px; border: 1px solid #ddd; text-align: right;">${totalFactures}</td>
                </tr>
            </tfoot>
        </table>
        
        <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; text-align: center; color: #666; font-size: 12px;">
            Généré le ${new Date().toLocaleDateString()} à ${new Date().toLocaleTimeString()}
        </div>
    `;

                $('#reportContent').html(reportContent);
                $('#reportModal').css('display', 'flex');
                $('#reportTitle').text('Rapport complet du ' + (dateDebut || 'début') + ' au ' + (dateFin || "aujourd'hui"));
            });
            // Générer le PDF
            $('#generatePdf').click(function () {
                const { jsPDF } = window.jspdf;
                const element = document.getElementById('reportContent');

                const options = {
                    scale: 2,
                    useCORS: true,
                    allowTaint: true,
                    scrollX: 0,
                    scrollY: 0
                };

                const btn = $(this);
                const btnText = btn.html();
                btn.html('<i class="fas fa-spinner fa-spin"></i> Génération...');

                html2canvas(element, options).then(canvas => {
                    const pdf = new jsPDF('p', 'mm', 'a4');
                    const imgData = canvas.toDataURL('image/png');
                    const pdfWidth = pdf.internal.pageSize.getWidth() - 20;
                    const pdfHeight = (canvas.height * pdfWidth) / canvas.width;

                    pdf.addImage(imgData, 'PNG', 10, 10, pdfWidth, pdfHeight);

                    const fileName = $('#reportTitle').text().replace(/ /g, '_') + '.pdf';
                    pdf.save(fileName);

                    btn.html(btnText);
                }).catch(err => {
                    console.error('Erreur:', err);
                    alert('Erreur lors de la génération du PDF');
                    btn.html(btnText);
                });
            });
        });

    </script>


</body>

</html>