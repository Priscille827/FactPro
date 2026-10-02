<?php
session_start();
require_once 'config/db.php';
require_once 'config/helpers.php';

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

// Récupérer les informations de l'utilisateur et son entreprise
$stmt = $pdo->prepare("SELECT u.*, e.id as entreprise_id FROM users u 
                      LEFT JOIN entreprise e ON u.id = e.user_id 
                      WHERE u.id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if (!$user || !isset($user['entreprise_id'])) {
    header('Location: parametres.php');
    exit;
}

// Récupérer les statistiques
$stats = [];

// Factures ce mois
$stmt = $pdo->prepare("SELECT COUNT(*) FROM facture_pro 
                      WHERE entreprise_id = ? 
                      AND MONTH(date_facture) = MONTH(CURRENT_DATE())");
$stmt->execute([$user['entreprise_id']]);
$stats['factures_mois'] = $stmt->fetchColumn();

// Factures ce mois
$stmt = $pdo->prepare("SELECT COUNT(*) FROM facture 
                      WHERE entreprise_id = ? 
                      AND MONTH(date_facture) = MONTH(CURRENT_DATE())");
$stmt->execute([$user['entreprise_id']]);
$stats['factures'] = $stmt->fetchColumn();


// Nombre de clients
$stmt = $pdo->prepare("SELECT COUNT(*) FROM client 
                      WHERE entreprise_id = ?");
$stmt->execute([$user['entreprise_id']]);
$stats['clients'] = $stmt->fetchColumn();

// Nombre de produits
$stmt = $pdo->prepare("SELECT COUNT(*) FROM produit 
                      WHERE entreprise_id = ?");
$stmt->execute([$user['entreprise_id']]);
$stats['produits'] = $stmt->fetchColumn();

// Récupérer les 5 dernières factures
$factures = $pdo->prepare("SELECT f.*, c.nom as client_nom 
                          FROM facture_pro f 
                          JOIN client c ON f.client_id = c.id 
                          WHERE f.entreprise_id = ? 
                          ORDER BY f.date_facture DESC 
                          LIMIT 5");
$factures->execute([$user['entreprise_id']]);
$factures = $factures->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de bord - Facture Proforma</title>
    
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
            --sidebar-bg:rgb(224, 145, 49);
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

        .stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: white;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 4px;
    height: 100%;
    background: linear-gradient(to bottom, var(--primary-color), var(--accent-color));
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
}

.stat-card h3 {
    font-size: 1rem;
    color: var(--dark-color);
    margin-bottom: 15px;
    font-weight: 500;
}

.stat-card .value {
    font-size: 2.2rem;
    font-weight: 700;
    background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
    -webkit-background-clip: text;
    background-clip: text;
    -webkit-text-fill-color: transparent;
    line-height: 1;
}

/* Responsive */
@media (max-width: 1200px) {
    .stat-card .value {
        font-size: 1.8rem;
    }
}

@media (max-width: 992px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 576px) {
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .stat-card {
        padding: 15px;
    }
    
    .stat-card .value {
        font-size: 1.5rem;
    }
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

        .data-table th, .data-table td {
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
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .dashboard-header {
                padding: 0 15px;
            }
            
            .user-menu span {
                display: none;
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
            <li><a href="dashboard.php" class="active"><i class="fas fa-home"></i> Tableau de bord</a></li>
                <li><a href="parametres.php"><i class="fas fa-cog"></i> Paramètres</a></li>
                <li><a href="produits.php"><i class="fas fa-boxes"></i> Produits</a></li>
                <li><a href="clients.php"><i class="fas fa-users"></i> Clients</a></li>
                <li><a href="factures.php"><i class="fas fa-file-invoice"></i> Factures ProForma</a></li>
                <li><a href="historique.php"><i class="fas fa-history"></i> Historique</a></li>
                <li><a href="factures_definitives.php" ><i class="fas fa-file-invoice-dollar"></i>
                        Facture</a></li>
                <li><a href="editorial.php"><i class="fas fa-chart-line me-2"></i> Statistiques</a></li>
                <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Déconnexion</a></li>
            </ul>
        </aside>
        
        <main class="dashboard-main">
            <h1 class="page-title">Bienvenue, <?php echo htmlspecialchars($user['name']); ?></h1>
            
            <div class="stats-grid">
                <div class="stat-card">
                    <h3>Factures Proforma enregistrées</h3>
                    <div class="value"><?php echo $stats['factures_mois']; ?></div>
                </div>
                <div class="stat-card">
                    <h3>Factures de ce mois</h3>
                    <div class="value"><?php echo $stats['factures']; ?></div>
                </div>
                <div class="stat-card">
                    <h3>Clients</h3>
                    <div class="value"><?php echo $stats['clients']; ?></div>
                </div>
                <div class="stat-card">
                    <h3>Produits</h3>
                    <div class="value"><?php echo $stats['produits']; ?></div>
                </div>
            </div>

            <div class="table-container">
                <div class="table-title-container">
                    <h2 class="table-title">Dernières factures</h2>
                    <a href="historique.php" class="btn btn-primary">
                        <i class="fas fa-history"></i> Voir l'historique complet
                    </a>
                </div>
                
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>N° Facture</th>
                            <th>Date</th>
                            <th>Client</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($factures as $facture): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($facture['numero']); ?></td>
                            <td><?php echo date('d/m/Y', strtotime($facture['date_facture'])); ?></td>
                            <td><?php echo htmlspecialchars($facture['client_nom']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Gestion du menu toggle
            const menuToggle = document.getElementById('menuToggle');
            const sidebar = document.querySelector('.dashboard-sidebar');
            const body = document.body;
            
            if (menuToggle && sidebar) {
                menuToggle.addEventListener('click', function() {
                    sidebar.classList.toggle('active');
                    body.classList.toggle('sidebar-active');
                });
                
                // Fermeture en cliquant à l'extérieur
                document.addEventListener('click', function(event) {
                    if (window.innerWidth <= 992 && 
                        !sidebar.contains(event.target) && 
                        event.target !== menuToggle && 
                        !menuToggle.contains(event.target)) {
                        sidebar.classList.remove('active');
                        body.classList.remove('sidebar-active');
                    }
                });
            }
            
            // Gestion de la connexion
            if (document.getElementById('loginForm')) {
                document.getElementById('loginForm').addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    const email = document.getElementById('email').value;
                    const password = document.getElementById('password').value;
                    
                    fetch('auth.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `action=login&email=${encodeURIComponent(email)}&password=${encodeURIComponent(password)}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        const messageDiv = document.getElementById('loginMessage');
                        messageDiv.textContent = data.message;
                        messageDiv.className = data.success ? 'success' : 'error';
                        
                        if (data.success) {
                            window.location.href = 'dashboard.php';
                        }
                    });
                });
            }
            
            // Gestion de l'inscription
            if (document.getElementById('registerForm')) {
                document.getElementById('registerForm').addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    if (document.getElementById('password').value !== document.getElementById('confirm_password').value) {
                        document.getElementById('registerMessage').textContent = 'Les mots de passe ne correspondent pas';
                        document.getElementById('registerMessage').className = 'error';
                        return;
                    }
                    
                    const name = document.getElementById('name').value;
                    const email = document.getElementById('email').value;
                    const password = document.getElementById('password').value;
                    
                    fetch('auth.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `action=register&name=${encodeURIComponent(name)}&email=${encodeURIComponent(email)}&password=${encodeURIComponent(password)}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        const messageDiv = document.getElementById('registerMessage');
                        messageDiv.textContent = data.message;
                        messageDiv.className = data.success ? 'success' : 'error';
                        
                        if (data.success) {
                            window.location.href = 'index.php';
                        }
                    });
                });
            }
        });
    </script>
</body>
</html>