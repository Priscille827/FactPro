<?php
session_start();
require_once 'config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

// Récupérer les informations de l'utilisateur
$stmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

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

// Récupération des informations de l'entreprise
try {
    $entreprise = $pdo->prepare("SELECT * FROM entreprise WHERE user_id = ?");
    $entreprise->execute([$_SESSION['user_id']]);
    $entreprise = $entreprise->fetch();
    
    if (!$entreprise) {
        throw new Exception("Aucune entreprise associée à cet utilisateur");
    }
    
    $devise = htmlspecialchars($entreprise['devise_symbole'] ?? 'XOF');
    
} catch (PDOException $e) {
    die("Erreur de base de données : " . $e->getMessage());
}

// Gestion des filtres de date
$endDate = new DateTime();
$defaultPeriod = isset($_GET['period']) ? $_GET['period'] : '30days';

switch ($defaultPeriod) {
    case '7days':
        $startDate = (clone $endDate)->sub(new DateInterval('P7D'));
        break;
    case '90days':
        $startDate = (clone $endDate)->sub(new DateInterval('P90D'));
        break;
    case 'custom':
        $startDate = isset($_GET['start_date']) ? new DateTime($_GET['start_date']) : (clone $endDate)->sub(new DateInterval('P30D'));
        $endDate = isset($_GET['end_date']) ? new DateTime($_GET['end_date']) : new DateTime();
        break;
    default: // 30days
        $startDate = (clone $endDate)->sub(new DateInterval('P30D'));
}

// Statistiques des 30 derniers jours
try {
    $stmt = $pdo->prepare("
        SELECT 
            DATE(date_facture) as jour,
            COUNT(*) as nombre_factures,
            SUM(montant_ttc) as chiffre_affaires,
            AVG(montant_ttc) as panier_moyen
        FROM facture
        WHERE entreprise_id = ? 
        AND date_facture BETWEEN ? AND ?
        GROUP BY DATE(date_facture)
        ORDER BY jour DESC
    ");
    $stmt->execute([$entreprise['id'], $startDate->format('Y-m-d'), $endDate->format('Y-m-d')]);
    $stats = $stmt->fetchAll();

    // Préparation des données pour les graphiques
    $labels = [];
    $caData = [];
    $countData = [];
    $avgData = [];

    foreach ($stats as $stat) {
        $labels[] = date('d/m', strtotime($stat['jour']));
        $caData[] = $stat['chiffre_affaires'];
        $countData[] = $stat['nombre_factures'];
        $avgData[] = $stat['panier_moyen'];
    }

} catch (PDOException $e) {
    die("Erreur lors de la récupération des statistiques journalières");
}

// Statistiques mensuelles (6 derniers mois)
try {
    $stmt = $pdo->prepare("
        SELECT 
            DATE_FORMAT(date_facture, '%Y-%m') as mois,
            SUM(montant_ttc) as chiffre_affaires,
            COUNT(*) as nombre_factures
        FROM facture
        WHERE entreprise_id = ?
        GROUP BY DATE_FORMAT(date_facture, '%Y-%m')
        ORDER BY mois DESC
        LIMIT 6
    ");
    $stmt->execute([$entreprise['id']]);
    $monthlyStats = $stmt->fetchAll();

} catch (PDOException $e) {
    die("Erreur lors de la récupération des statistiques mensuelles");
}


// Récupérer le produit phare (le plus vendu)
$produit_phare = $pdo->prepare("
    SELECT p.designation, SUM(ifp.quantite) as total_quantite, SUM(ifp.montant_ht) as total_ht
    FROM item_facture ifp
    JOIN produit p ON ifp.produit_id = p.id
    JOIN facture f ON ifp.facture_id = f.id
    WHERE f.entreprise_id = ?
    GROUP BY p.id
    ORDER BY total_quantite DESC
    LIMIT 1
");
$produit_phare->execute([$entreprise['id']]);
$produit_phare = $produit_phare->fetch();

// Récupérer le client phare (qui a le plus dépensé)
$client_phare = $pdo->prepare("
    SELECT c.nom, COUNT(f.id) as nombre_factures, SUM(f.montant_ttc) as total_achats
    FROM facture f
    JOIN client c ON f.client_id = c.id
    WHERE f.entreprise_id = ?
    GROUP BY c.id
    ORDER BY total_achats DESC
    LIMIT 1
");
$client_phare->execute([$entreprise['id']]);
$client_phare = $client_phare->fetch();

// Récupérer le top 5 des produits
$top5_produits = $pdo->prepare("
    SELECT p.designation, SUM(ifp.quantite) as total_quantite, SUM(ifp.montant_ht) as total_ht
    FROM item_facture ifp
    JOIN produit p ON ifp.produit_id = p.id
    JOIN facture f ON ifp.facture_id = f.id
    WHERE f.entreprise_id = ?
    GROUP BY p.id
    ORDER BY total_quantite DESC
    LIMIT 5
");
$top5_produits->execute([$entreprise['id']]);
$top5_produits = $top5_produits->fetchAll();

// Récupérer le top 5 des clients
$top5_clients = $pdo->prepare("
    SELECT c.nom, COUNT(f.id) as nombre_factures, SUM(f.montant_ttc) as total_achats
    FROM facture f
    JOIN client c ON f.client_id = c.id
    WHERE f.entreprise_id = ?
    GROUP BY c.id
    ORDER BY total_achats DESC
    LIMIT 5
");
$top5_clients->execute([$entreprise['id']]);
$top5_clients = $top5_clients->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de Bord Éditorial</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        }
        
        .dashboard-container {
            display: flex;
            min-height: 100vh;
        }
        
        .dashboard-main {
            flex: 1;
            padding: 2rem;
            margin-left: 250px;
        }
        
        .stat-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 15px rgba(0,0,0,0.1);
        }
        
        .stat-value {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            color: #6c757d;
            font-size: 1rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .chart-container {
            position: relative;
            height: 300px;
            width: 100%;
            margin-top: 1rem;
        }
        
        .table-analytics {
            font-size: 0.9rem;
        }
        
        .table-analytics th {
            background-color: var(--light-color);
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.8rem;
            letter-spacing: 0.5px;
        }
        
        .badge-trend {
            padding: 0.35em 0.65em;
            font-size: 0.75em;
            font-weight: 700;
            border-radius: 50rem;
        }
        
        /* Style supplémentaire pour les filtres */
        .filter-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .filter-card h5 {
            color: var(--dark-color);
            margin-bottom: 1.5rem;
            font-size: 1.1rem;
            font-weight: 600;
        }

        .form-label {
            font-weight: 500;
            color: var(--dark-color);
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }

        .form-select, .form-control {
            border: 1px solid #dee2e6;
            border-radius: 0.375rem;
            padding: 0.5rem 1rem;
            font-size: 0.9rem;
        }

        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            padding: 0.5rem 1.5rem;
        }

        .btn-primary:hover {
            background-color: var(--secondary-color);
            border-color: var(--secondary-color);
        }
        .badge-trend {
    cursor: help; /* Change le curseur pour indiquer qu'il y a une info-bulle */
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
            <img src="<?= htmlspecialchars($logoPath) ?>" class="header-logo" alt="Logo" style="border-radius:50%;object-fit:cover;width:42px;height:42px;">
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
                <li><a href="factures_definitives.php" ><i class="fas fa-file-invoice-dollar"></i>
                        Facture</a></li>
                <li><a href="editorial.php" class="active"><i class="fas fa-chart-line me-2"></i> Statistiques</a></li>
                <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Déconnexion</a></li>
            </ul>
        </aside>
    
    <main class="dashboard-main">
        <h1 class="page-title">
            <i class="fas fa-chart-line me-2"></i>Tableau de Bord Éditorial
        </h1>
        
        <!-- Filtres -->
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="stat-card">
                    <h5 class="mb-3"><i class="fas fa-filter me-2"></i>Filtres d'analyse</h5>
                    <form method="get" class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Période prédéfinie</label>
                            <select name="period" class="form-select" onchange="this.form.submit()">
                                <option value="7days" <?= $defaultPeriod === '7days' ? 'selected' : '' ?>>7 derniers jours</option>
                                <option value="30days" <?= $defaultPeriod === '30days' ? 'selected' : '' ?>>30 derniers jours</option>
                                <option value="90days" <?= $defaultPeriod === '90days' ? 'selected' : '' ?>>90 derniers jours</option>
                                <option value="custom" <?= $defaultPeriod === 'custom' ? 'selected' : '' ?>>Personnalisée</option>
                            </select>
                        </div>
                        
                        <?php if ($defaultPeriod === 'custom'): ?>
                        <div class="col-md-3">
                            <label class="form-label">Date de début</label>
                            <input type="date" name="start_date" class="form-control" 
                                   value="<?= isset($_GET['start_date']) ? $_GET['start_date'] : $startDate->format('Y-m-d') ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Date de fin</label>
                            <input type="date" name="end_date" class="form-control" 
                                   value="<?= isset($_GET['end_date']) ? $_GET['end_date'] : $endDate->format('Y-m-d') ?>">
                        </div>
                        <div class="col-md-3 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary">Appliquer</button>
                        </div>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- KPI Cards -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="stat-card text-center">
                    <div class="stat-value"><?= count($stats) ?></div>
                    <div class="stat-label">Jours d'activité</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card text-center">
                    <div class="stat-value"><?= array_sum($countData) ?></div>
                    <div class="stat-label">Factures</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card text-center">
                    <div class="stat-value"><?= number_format(array_sum($caData), 0, ',', ' ') ?> <?= $devise ?></div>
                    <div class="stat-label">Chiffre d'affaires</div>
                </div>
            </div>
        </div>
        <div id="chartsContainer" #b2bec3class="table-container" style="margin-bottom: 20px;">
    <div class="table-title-container">
         <h2 class="table-title">Statistiques des Ventes - Période: 
            <?= $defaultPeriod === 'custom' ? 
                $startDate->format('d/m/Y').' au '.$endDate->format('d/m/Y') : 
                str_replace('days', ' jours', $defaultPeriod) ?>
        </h2>
        <button id="downloadChartsReport" class="btn btn-primary">
            <i class="fas fa-file-pdf"></i> Télécharger
        </button>
    </div>
        <!-- Charts Row -->
        <div class="row mb-4">
            <div class="col-lg-12">
                <div class="stat-card">
                    <h5 class="mb-3">
                        <i class="fas fa-chart-bar me-2"></i>Évolution du chiffre d'affaires (<?= $defaultPeriod === 'custom' ? $startDate->format('d/m/Y').' - '.$endDate->format('d/m/Y') : $defaultPeriod ?>)
                    </h5>
                    <div class="chart-container">
                        <canvas id="caChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <div class="row mb-4">
    <div class="col-12">
        <div class="stat-card">
            <div class="row">
                <div class="col-md-6 border-end">
                    <h5 class="mb-0"><i class="fas fa-calendar-alt me-2"></i>Statistiques mensuelles</h5>
                </div>
                <div class="col-md-6">
                    <h5 class="mb-0 text-end"><i class="fas fa-clock me-2"></i>Statistiques journalières</h5>
                </div>
            </div>
        </div>
    </div>
</div>  
        <!-- Second Charts Row -->
        <div class="row mb-4">
            <div class="col-lg-6">
                <div class="stat-card">
                    
                    <h5 class="mb-3">
                        <i class="fas fa-chart-pie me-2"></i>Répartition mensuelle
                    </h5>
                    <div class="chart-container">
                        <canvas id="monthlyChart"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="stat-card">
                    <h5 class="mb-3">
                        <i class="fas fa-shopping-basket me-2"></i>Panier moyen
                    </h5>
                    <div class="chart-container">
                        <canvas id="avgChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Third Row -->
        <div class="row mb-4">
            <div class="col-lg-6" id="monthlyTable">
                <div class="stat-card">
                    <h5 class="mb-3">
                        <i class="fas fa-calendar-alt me-2"></i>Synthèse mensuelle
                    </h5>
                    <div class="table-responsive">
                        <table class="table table-analytics">
                            <thead>
                                <tr>
                                    <th>Mois</th>
                                    <th>Factures</th>
                                    <th>CA (<?= $devise ?>)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($monthlyStats as $mois): ?>
                                <tr>
                                    <td><?= date('F Y', strtotime($mois['mois'].'-01')) ?></td>
                                    <td><?= $mois['nombre_factures'] ?></td>
                                    <td class="fw-bold"><?= number_format($mois['chiffre_affaires'], 0, ',', ' ') ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-lg-6" id="dailyTable">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="mb-0"><i class="fas fa-table me-2"></i>Détails par jour</h5>
                        <div class="dropdown">
                            <!-- <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="sortDropdown" data-bs-toggle="dropdown">
                                <i class="fas fa-sort me-1"></i>Trier par
                            </button> -->
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item sort-table" href="#" data-sort="0">Date</a></li>
                                <li><a class="dropdown-item sort-table" href="#" data-sort="1">Factures</a></li>
                                <li><a class="dropdown-item sort-table" href="#" data-sort="2">Chiffre d'affaires</a></li>
                                <li><a class="dropdown-item sort-table" href="#" data-sort="3">Panier moyen</a></li>
                            </ul>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-analytics" id="dailyStatsTable">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Factures</th>
                                    <th>CA (<?= $devise ?>)</th>
                                    <th>Panier moyen</th>
                                    <th>Tendance</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php 
// Calcul du seuil (25% du meilleur jour)
$maxCA = max(array_column($stats, 'chiffre_affaires'));
$seuilFort = $maxCA * 0.5;  // 50% du max = fort
$seuilMoyen = $maxCA * 0.25; // 25% du max = moyen
?>

<!-- Dans le tableau -->
<?php foreach ($stats as $stat): 
    $ca = $stat['chiffre_affaires'];
    
    if ($ca >= $seuilFort) {
        $trendClass = 'bg-success';
        $trendIcon = 'fa-arrow-up';
        $trendText = 'Forte';
    } elseif ($ca >= $seuilMoyen) {
        $trendClass = 'bg-warning text-dark';
        $trendIcon = 'fa-arrow-right';
        $trendText = 'Moyenne';
    } else {
        $trendClass = 'bg-danger';
        $trendIcon = 'fa-arrow-down';
        $trendText = 'Basse';
    }
?>
<tr>
    <td><?= date('d/m/Y', strtotime($stat['jour'])) ?></td>
    <td><?= $stat['nombre_factures'] ?></td>
    <td><?= number_format($stat['chiffre_affaires'], 0, ',', ' ') ?></td>
    <td><?= number_format($stat['panier_moyen'], 0, ',', ' ') ?></td>
    <td>
        <span class="badge badge-trend <?= $trendClass ?>" 
              title="Seuil: <?= number_format($ca >= $seuilFort ? $seuilFort : $seuilMoyen, 0, ',', ' ') ?> <?= $devise ?>">
            <i class="fas <?= $trendIcon ?> me-1"></i> <?= $trendText ?>
        </span>
    </td>
</tr>
<?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            </div>
        </div>
         <!-- Section Phares et Top 5 -->
                  <div class="table-container" style="margin-bottom: 20px; margin-top: 20px;">
                  <div class="table-title-container">
        <h2 class="table-title">Performances Commerciales</h2>
        <button id="downloadPerformanceReport" class="btn btn-primary">
            <i class="fas fa-file-pdf"></i> Télécharger
        </button>
        </div>
                    <div style="display: flex; flex-wrap: wrap; gap: 20px; margin-bottom: 30px; margin-top: 20px;">
                        <!-- Produit Phare -->
                        <div
                            style="flex: 1; min-width: 300px; background: #fff8e1; border-radius: 10px; padding: 20px; box-shadow: 0 3px 10px rgba(0,0,0,0.1);">
                            <h3 style="color: #ff9800; margin-bottom: 15px; display: flex; align-items: center;">
                                <i class="fas fa-star" style="margin-right: 10px;"></i> Produit Phare
                            </h3>
                            <?php if ($produit_phare): ?>
                                <div class="product-name" style="font-size: 1.2rem; font-weight: bold; margin-bottom: 5px;">
                                    <?= htmlspecialchars($produit_phare['designation']) ?>
                                </div>
                                <div style="display: flex; justify-content: space-between; margin-top: 15px;">
                                    <div>
                                        <div style="font-size: 0.9rem; color: #666;">Quantité vendue</div>
                                        <div class="product-qty" style="font-size: 1.5rem; font-weight: bold;">
                                            <?= $produit_phare['total_quantite'] ?></div>
                                    </div>
                                    <div>
                                        <div style="font-size: 0.9rem; color: #666;">Chiffre d'affaires</div>
                                        <div style="font-size: 1.5rem; font-weight: bold;" class="product-ca">
                                            <?= number_format($produit_phare['total_ht'], 2, ',', ' ') ?>
                                            <?= htmlspecialchars($entreprise['devise_symbole'] ?? 'XOF') ?>
                                        </div>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div style="color: #666;">Aucun produit vendu</div>
                            <?php endif; ?>
                        </div>

                        <!-- Client Phare -->
                        <div
                            style="flex: 1; min-width: 300px; background: #e8f5e9; border-radius: 10px; padding: 20px; box-shadow: 0 3px 10px rgba(0,0,0,0.1);">
                            <h3 style="color: #4caf50; margin-bottom: 15px; display: flex; align-items: center;">
                                <i class="fas fa-crown" style="margin-right: 10px;"></i> Client Phare
                            </h3>
                            <?php if ($client_phare): ?>
                                <div style="font-size: 1.2rem; font-weight: bold; margin-bottom: 5px;" class="client-name">
                                    <?= htmlspecialchars($client_phare['nom']) ?>
                                </div>
                                <div style="display: flex; justify-content: space-between; margin-top: 15px;">
                                    <div>
                                        <div style="font-size: 0.9rem; color: #666;">Factures</div>
                                        <div style="font-size: 1.5rem; font-weight: bold;" class="client-invoices">
                                            <?= $client_phare['nombre_factures'] ?></div>
                                    </div>
                                    <div>
                                        <div style="font-size: 0.9rem; color: #666;">Total dépensé</div>
                                        <div style="font-size: 1.5rem; font-weight: bold;" class="client-total">
                                            <?= number_format($client_phare['total_achats'], 2, ',', ' ') ?>
                                            <?= htmlspecialchars($entreprise['devise_symbole'] ?? 'XOF') ?>
                                        </div>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div style="color: #666;">Aucun client</div>
                            <?php endif; ?>
                        </div>
                    </div>
 
                    <!-- Top 5 Produits -->
                    <h3 style="margin: 20px 0 15px; color: var(--primary-color);">Top 5 des Produits</h3>
                    <table class="data-table" style="margin-bottom: 30px;">
                        <thead>
                            <tr>
                                <th>Produit</th>
                                <th>Quantité vendue</th>
                                <th>Chiffre d'affaires HT</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($top5_produits as $index => $produit): ?>
                                <tr <?= $index === 0 ? 'style="background-color: #fff8e1;"' : '' ?>>
                                    <td>
                                        <?= htmlspecialchars($produit['designation']) ?>
                                        <?php if ($index === 0): ?>
                                            <span style="color: #ff9800; margin-left: 10px;">
                                                <i class="fas fa-trophy"></i>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= $produit['total_quantite'] ?></td>
                                    <td><?= number_format($produit['total_ht'], 2, ',', ' ') ?>
                                        <?= htmlspecialchars($entreprise['devise_symbole'] ?? 'XOF') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <!-- Top 5 Clients -->
                    <h3 style="margin: 20px 0 15px; color: var(--primary-color);">Top 5 des Clients</h3>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Client</th>
                                <th>Nombre de factures</th>
                                <th>Total dépensé</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($top5_clients as $index => $client): ?>
                                <tr <?= $index === 0 ? 'style="background-color: #e8f5e9;"' : '' ?>>
                                    <td>
                                        <?= htmlspecialchars($client['nom']) ?>
                                        <?php if ($index === 0): ?>
                                            <span style="color: #4caf50; margin-left: 10px;">
                                                <i class="fas fa-crown"></i>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= $client['nombre_factures'] ?></td>
                                    <td><?= number_format($client['total_achats'], 2, ',', ' ') ?>
                                        <?= htmlspecialchars($entreprise['devise_symbole'] ?? 'XOF') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
      <div id="pdfExportContent" style="display:none; padding: 10px; font-size: 9px;">
    <h2 class="text-center mb-3">Rapport Complet</h2>

    <div style="margin-bottom: 10px;">
        <canvas id="caChartClone" width="500" height="150"></canvas>
    </div>

    <div style="margin-bottom:10px;">
        <canvas id="monthlyChartClone" width="500" height="150"></canvas>
    </div>

    <div style="margin-bottom: 10px;">
        <canvas id="avgChartClone" width="500" height="150"></canvas>
    </div>

    <div style="margin-bottom: 10px;">
        <div id="monthlyTableClone"></div>
    </div>

    <div>
        <div id="dailyTableClone"></div>
    </div>
</div>


    </main>
    
</div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

 <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
 <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // CA Daily Chart
        const caCtx = document.getElementById('caChart').getContext('2d');
        const caChart = new Chart(caCtx, {
            type: 'bar',
            data: {
                labels: <?= json_encode(array_reverse($labels)) ?>,
                datasets: [
                    {
                        label: 'Chiffre d\'affaires (<?= $devise ?>)',
                        data: <?= json_encode(array_reverse($caData)) ?>,
                        backgroundColor: 'rgba(108, 92, 231, 0.7)',
                        borderColor: 'rgba(108, 92, 231, 1)',
                        borderWidth: 1,
                        yAxisID: 'y'
                    },
                    {
                        label: 'Nombre de factures',
                        data: <?= json_encode(array_reverse($countData)) ?>,
                        backgroundColor: 'rgba(54, 162, 235, 0.7)',
                        borderColor: 'rgba(54, 162, 235, 1)',
                        borderWidth: 1,
                        type: 'line',
                        yAxisID: 'y1'
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                let label = context.dataset.label || '';
                                if (label) label += ': ';
                                if (context.datasetIndex === 0) {
                                    label += context.raw.toLocaleString('fr-FR') + ' <?= $devise ?>';
                                } else {
                                    label += context.raw;
                                }
                                return label;
                            }
                        }
                    },
                    legend: {
                        position: 'top',
                    },
                    title: {
                        display: true,
                        text: 'Performance journalière',
                        font: {
                            size: 16
                        }
                    }
                },
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: {
                            display: true,
                            text: 'Chiffre d\'affaires (<?= $devise ?>)'
                        },
                        grid: {
                            drawOnChartArea: true
                        },
                        ticks: {
                            callback: function(value) {
                                return value.toLocaleString('fr-FR');
                            }
                        }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        title: {
                            display: true,
                            text: 'Nombre de factures'
                        },
                        grid: {
                            drawOnChartArea: false
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });

        // Monthly Chart
        const monthlyCtx = document.getElementById('monthlyChart').getContext('2d');
        const monthlyChart = new Chart(monthlyCtx, {
            type: 'bar',
            data: {
                labels: <?= json_encode(array_map(function($m) { 
                    return DateTime::createFromFormat('Y-m', $m['mois'])->format('M Y'); 
                }, $monthlyStats)) ?>,
                datasets: [{
                    label: 'Chiffre d\'affaires (<?= $devise ?>)',
                    data: <?= json_encode(array_column($monthlyStats, 'chiffre_affaires')) ?>,
                    backgroundColor: [
                        'rgba(255, 99, 132, 0.7)',
                        'rgba(54, 162, 235, 0.7)',
                        'rgba(255, 206, 86, 0.7)',
                        'rgba(75, 192, 192, 0.7)',
                        'rgba(153, 102, 255, 0.7)',
                        'rgba(255, 159, 64, 0.7)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.dataset.label + ': ' + 
                                    context.raw.toLocaleString('fr-FR') + ' <?= $devise ?>';
                            }
                        }
                    },
                    legend: {
                        display: false
                    },
                    title: {
                        display: true,
                        text: 'Performance mensuelle',
                        font: {
                            size: 16
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return value.toLocaleString('fr-FR') + ' <?= $devise ?>';
                            }
                        }
                    }
                }
            }
        });

        // Average Basket Chart
        const avgCtx = document.getElementById('avgChart').getContext('2d');
        const avgChart = new Chart(avgCtx, {
            type: 'line',
            data: {
                labels: <?= json_encode(array_reverse($labels)) ?>,
                datasets: [{
                    label: 'Panier moyen (<?= $devise ?>)',
                    data: <?= json_encode(array_reverse($avgData)) ?>,
                    backgroundColor: 'rgba(75, 192, 192, 0.2)',
                    borderColor: 'rgba(75, 192, 192, 1)',
                    borderWidth: 2,
                    tension: 0.3,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.dataset.label + ': ' + 
                                    context.raw.toLocaleString('fr-FR') + ' <?= $devise ?>';
                            }
                        }
                    },
                    title: {
                        display: true,
                        text: 'Évolution du panier moyen',
                        font: {
                            size: 16
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: false,
                        ticks: {
                            callback: function(value) {
                                return value.toLocaleString('fr-FR') + ' <?= $devise ?>';
                            }
                        }
                    }
                }
            }
        });
        // Activer les tooltips Bootstrap
const tooltipTriggerList = [].slice.call(document.querySelectorAll('[title]'));
tooltipTriggerList.map(function (tooltipTriggerEl) {
    return new bootstrap.Tooltip(tooltipTriggerEl);
});
        // Gestion du tri de la table
        document.querySelectorAll('.sort-table').forEach(item => {
            item.addEventListener('click', function(e) {
                e.preventDefault();
                const column = parseInt(this.getAttribute('data-sort'));
                const table = document.getElementById('dailyStatsTable');
                const tbody = table.querySelector('tbody');
                const rows = Array.from(tbody.querySelectorAll('tr'));
                
                rows.sort((a, b) => {
                    const aVal = a.cells[column].textContent;
                    const bVal = b.cells[column].textContent;
                    
                    // Gestion des nombres
                    if (!isNaN(parseFloat(aVal))) {
                        return parseFloat(bVal.replace(/\s/g, '')) - parseFloat(aVal.replace(/\s/g, ''));
                    }
                    
                    // Gestion des dates
                    if (column === 0) {
                        const dateA = aVal.split('/').reverse().join('-');
                        const dateB = bVal.split('/').reverse().join('-');
                        return new Date(dateB) - new Date(dateA);
                    }
                    
                    // Tri alphabétique
                    return aVal.localeCompare(bVal);
                });
                
                // Réinsérer les lignes triées
                rows.forEach(row => tbody.appendChild(row));
            });
        });

        // Menu toggle pour mobile
        document.getElementById('menuToggle').addEventListener('click', function() {
            document.querySelector('.dashboard-sidebar').classList.toggle('active');
            document.body.classList.toggle('sidebar-active');
        });

        $(document).ready(function() {
          $('#downloadPerformanceReport').click(function() {
    const btn = $(this);
    const btnText = btn.html();
    btn.html('<i class="fas fa-spinner fa-spin"></i>').prop('disabled', true);

    // 1. Trouver le bon conteneur avec une sélection précise
    const perfContainer = $('.table-container').has('h2.table-title:contains("Performances Commerciales")').first();
    
    if (perfContainer.length === 0) {
        alert("Section des performances non trouvée");
        btn.html(btnText).prop('disabled', false);
        return;
    }

    // 2. Créer une copie sans le bouton
    const content = perfContainer.clone();
    content.find('#downloadPerformanceReport').remove();

    // 3. Créer un conteneur temporaire
    const tempDiv = $('<div>').css({
        position: 'absolute',
        left: '-9999px',
        top: 0,
        width: perfContainer.width() + 'px',
        padding: '20px',
        backgroundColor: 'white'
    }).append(content).appendTo('body');

    // 4. Générer le PDF
    html2canvas(tempDiv[0], {
        scale: 4,
        logging: true,
        useCORS: true,
        scrollX: 0,
        scrollY: 0,
        windowWidth: tempDiv[0].scrollWidth,
        windowHeight: tempDiv[0].scrollHeight,
        backgroundColor: '#ffffff'
    }).then(canvas => {
        const { jsPDF } = window.jspdf;
        const pdf = new jsPDF('p', 'mm', 'a4');
        const imgData = canvas.toDataURL('image/png');
        
        // Calcul des dimensions
        const imgWidth = pdf.internal.pageSize.getWidth() - 20;
        const imgHeight = (canvas.height * imgWidth) / canvas.width;
        
        pdf.addImage(imgData, 'PNG', 10, 10, imgWidth, imgHeight);
        pdf.save(`Performances_Commerciales_${new Date().toISOString().slice(0,10)}.pdf`);
        
    }).catch(err => {
        console.error("Erreur:", err);
        alert("Erreur lors de la génération du PDF");
    }).finally(() => {
        tempDiv.remove();
        btn.html(btnText).prop('disabled', false);
    });
});
});
document.getElementById('downloadChartsReport').addEventListener('click', async () => {
    const exportContainer = document.getElementById('pdfExportContent');

    // Copier les tableaux
    document.getElementById('monthlyTableClone').innerHTML =
        document.getElementById('monthlyTable').innerHTML;
    document.getElementById('dailyTableClone').innerHTML =
        document.getElementById('dailyTable').innerHTML;

    // Copier les graphiques
    const charts = [
        { srcId: 'caChart', destId: 'caChartClone' },
        { srcId: 'monthlyChart', destId: 'monthlyChartClone' },
        { srcId: 'avgChart', destId: 'avgChartClone' }
    ];

    charts.forEach(({ srcId, destId }) => {
        const srcCanvas = document.getElementById(srcId);
        const destCanvas = document.getElementById(destId);
        const destCtx = destCanvas.getContext('2d');
        destCtx.drawImage(srcCanvas, 0, 0, destCanvas.width, destCanvas.height);
    });

    exportContainer.style.display = 'block'; // Afficher temporairement

    await html2pdf().set({
        margin: 5,
        filename: 'rapport_complet.pdf',
        html2canvas: { scale: 2 },
        jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
    }).from(exportContainer).save();

    exportContainer.style.display = 'none'; // Cacher après export
});


    </script>
</body>
</html>