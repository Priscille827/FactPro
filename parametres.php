<?php
require_once 'config/db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$logoPath = null;

        $stmt = $pdo->prepare("SELECT logo FROM entreprise WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $logoPath = $stmt->fetchColumn();
 

// Récupérer les informations de l'utilisateur
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Récupérer les informations de l'entreprise
$stmt = $pdo->prepare("SELECT * FROM entreprise WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$entreprise = $stmt->fetch();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paramètres - Facture Proforma</title>
    
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

        /* Settings Container */
        .settings-container {
            display: grid;
            grid-template-columns: 1fr;
            gap: 30px;
        }

        /* Card Styles */
        .entreprise-card, .account-card, .form-card {
            background-color: var(--card-bg);
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            transition: transform 0.3s, box-shadow 0.3s;
            border: 1px solid rgba(0, 0, 0, 0.03);
            position: relative;
            overflow: hidden;
        }

        .entreprise-card::before, .account-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: linear-gradient(to bottom, var(--primary-color), var(--accent-color));
        }

        .entreprise-card:hover, .account-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }

        /* Entreprise Header */
        .entreprise-header {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
        }

        .entreprise-logo {
            width: 80px;
            height: 80px;
            border-radius: 8px;
            object-fit: cover;
            margin-right: 20px;
            border: 1px solid #eee;
        }

        .entreprise-logo.placeholder {
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #f0f0f0;
            color: var(--gray-color);
            font-size: 2rem;
        }

        .entreprise-header h2 {
            font-size: 1.5rem;
            color: var(--dark-color);
        }

        /* Entreprise Info */
        .entreprise-info {
            margin-bottom: 20px;
        }

        .entreprise-info p {
            margin-bottom: 10px;
            display: flex;
            align-items: center;
        }

        .entreprise-info i {
            margin-right: 10px;
            color: var(--primary-color);
            width: 20px;
            text-align: center;
        }

        /* Account Section */
        .account-card h2 {
            font-size: 1.5rem;
            margin-bottom: 20px;
            color: var(--dark-color);
            display: flex;
            align-items: center;
        }

        .account-card h2 i {
            margin-right: 10px;
            color: var(--primary-color);
        }

        .account-info {
            margin-bottom: 20px;
        }

        .account-info p {
            margin-bottom: 10px;
            font-size: 1rem;
        }

        .account-info strong {
            color: var(--dark-color);
        }

        /* Form Styles */
        .form-card h2 {
            font-size: 1.5rem;
            margin-bottom: 20px;
            color: var(--dark-color);
            display: flex;
            align-items: center;
        }

        .form-card h2 i {
            margin-right: 10px;
            color: var(--primary-color);
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--dark-color);
        }

        .form-group input[type="text"],
        .form-group input[type="email"],
        .form-group input[type="number"],
        .form-group input[type="password"],
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            border-color: var(--primary-color);
            outline: none;
        }

        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            padding: 12px 20px;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            border: none;
            font-size: 0.95rem;
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

        .btn-secondary {
            background-color: var(--gray-color);
            color: white;
        }

        .btn-secondary:hover {
            background-color: #a7aeb3;
            transform: translateY(-2px);
        }

        .btn-cancel {
            background-color: var(--danger-color);
            color: white;
        }

        .btn-cancel:hover {
            background-color: #c0392b;
            transform: translateY(-2px);
        }

        .form-actions {
            display: flex;
            gap: 15px;
            margin-top: 25px;
        }

        /* Modal Overlay */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1001;
        }

        .modal-container {
            background-color: var(--card-bg);
            border-radius: 12px;
            padding: 30px;
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        }

        .modal-container h2 {
            font-size: 1.5rem;
            margin-bottom: 25px;
            color: var(--dark-color);
            display: flex;
            align-items: center;
        }

        .modal-container h2 i {
            margin-right: 10px;
            color: var(--primary-color);
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
            .form-row {
                grid-template-columns: 1fr;
                gap: 15px;
            }
            
            .dashboard-header {
                padding: 0 15px;
            }
            
            .user-menu span {
                display: none;
            }
            
            .entreprise-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .entreprise-logo {
                margin-right: 0;
                margin-bottom: 15px;
            }
        }
        /* Système de messages */
.message-container {
    position: fixed;
    top: 20px;
    left: 50%;
    transform: translateX(-50%);
    z-index: 1200;
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
    box-shadow: 0 5px 20px rgba(0,0,0,0.15);
    display: flex;
    align-items: center;
    justify-content: space-between;
    animation: fadeInUp 0.4s ease-out forwards;
    opacity: 0;
    transform: translateY(-20px);
    pointer-events: auto;
    width: 100%;
    backdrop-filter: blur(5px);
    border: 1px solid rgba(255,255,255,0.2);
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
            <img src="<?= htmlspecialchars($logoPath) ?>" class="header-logo" alt="Logo" style="border-radius:50%;object-fit:cover;width:42px;height:42px;">
        <?php else: ?>
            <div class="header-logo placeholder">
                <i class="fas fa-building"></i>
            </div>
        <?php endif; ?>
                <span><?php echo htmlspecialchars($user['name']); ?></span>
            </div>
        </header>
        
        <aside class="dashboard-sidebar">
            <ul class="sidebar-menu">
            <li><a href="dashboard.php"><i class="fas fa-home"></i> Tableau de bord</a></li>
                <li><a href="parametres.php" class="active"><i class="fas fa-cog"></i> Paramètres</a></li>
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
            <h1 class="page-title">Paramètres</h1>
            
            <div class="settings-container">
                <!-- Section Entreprise -->
                <div id="entrepriseSection">
                    <?php if ($entreprise): ?>
                        <!-- Affichage des infos entreprise -->
                        <div class="entreprise-card">
                            <div class="entreprise-header">
                                <?php if (!empty($entreprise['logo'])): ?>
                                    <img src="<?= htmlspecialchars($entreprise['logo']) ?>" class="entreprise-logo" alt="Logo">
                                <?php else: ?>
                                    <div class="entreprise-logo placeholder">
                                        <i class="fas fa-building"></i>
                                    </div>
                                <?php endif; ?>
                                <h2><?= htmlspecialchars($entreprise['nom']) ?></h2>
                            </div>
                            
                            <div class="entreprise-info">
                                <p><i class="fas fa-map-marker-alt"></i> <?= nl2br(htmlspecialchars($entreprise['adresse'])) ?></p>
                                <p><i class="fas fa-phone"></i> <?= htmlspecialchars($entreprise['telephone']) ?></p>
                                <p><i class="fas fa-envelope"></i> <?= htmlspecialchars($entreprise['email']) ?></p>
                                <p><i class="fas fa-percentage"></i> TVA: <?= htmlspecialchars($entreprise['tva']) ?>%</p>
                                <p><i class="fas fa-dollar-sign"></i> Devise: <?= htmlspecialchars($entreprise['devise']) ?></p>
                            </div>
                            
                            <button id="editEntrepriseBtn" class="btn btn-primary">
                                <i class="fas fa-edit"></i> Modifier
                            </button>
                        </div>
                    <?php else: ?>
                        <!-- Formulaire de création entreprise -->
                        <div class="form-card">
                            <h2><i class="fas fa-building"></i> Créer votre entreprise</h2>
                            <form id="createEntrepriseForm" enctype="multipart/form-data">
                                <div class="form-group">
                                    <label>Nom de l'entreprise *</label>
                                    <input type="text" name="nom" required>
                                </div>
                                
                                <div class="form-group">
                                    <label>Adresse *</label>
                                    <textarea name="adresse" rows="3" required></textarea>
                                </div>
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label>Téléphone</label>
                                        <input type="text" name="telephone">
                                    </div>
                                    <div class="form-group">
                                        <label>Email</label>
                                        <input type="email" name="email">
                                    </div>
                                </div>
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label>TVA (%)</label>
                                        <input type="number" step="0.01" name="tva" value="18.00">
                                    </div>
                                    <div class="form-group">
                                        <label>Devise</label>
                                        <select name="devise" required>
                                            <option value="XOF">Franc CFA (XOF)</option>
                                            <option value="EUR">Euro (EUR)</option>
                                            <option value="USD">Dollar (USD)</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label>Logo</label>
                                    <input type="file" name="logo" accept="image/*">
                                </div>
                                
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Enregistrer
                                </button>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Section Compte -->
                <div id="accountSection">
                    <div class="account-card">
                        <h2><i class="fas fa-user"></i> Mon Compte</h2>
                        
                        <div class="account-info">
                            <p><strong>Nom :</strong> <span id="userNameDisplay"><?= htmlspecialchars($user['name']) ?></span></p>
                            <p><strong>Email :</strong> <span id="userEmailDisplay"><?= htmlspecialchars($user['email']) ?></span></p>
                            
                            <button id="editAccountBtn" class="btn btn-secondary">
                                <i class="fas fa-edit"></i> Modifier mes informations
                            </button>
                        </div>
                        
                        <!-- Formulaire caché pour les infos du compte -->
                        <div id="accountFormContainer" style="display: none;">
                            <form id="editAccountForm">
                                <div class="form-group">
                                    <label>Nom complet *</label>
                                    <input type="text" name="name" value="<?= htmlspecialchars($user['name']) ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label>Email *</label>
                                    <input type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label>Ancien mot de passe (pour confirmer les changements)</label>
                                    <input type="password" name="current_password">
                                </div>
                                
                                <div class="form-group">
                                    <label>Nouveau mot de passe (laisser vide si inchangé)</label>
                                    <input type="password" name="new_password">
                                </div>
                                
                                <div class="form-group">
                                    <label>Confirmer le nouveau mot de passe</label>
                                    <input type="password" name="confirm_password">
                                </div>
                                
                                <div class="form-actions">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save"></i> Enregistrer
                                    </button>
                                    <button type="button" id="cancelAccountBtn" class="btn btn-cancel">
                                        <i class="fas fa-times"></i> Annuler
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
    <div class="message-container" id="messageContainer"></div>
    
    <!-- Overlay pour modification entreprise -->
    <div id="editEntrepriseOverlay" class="modal-overlay" style="display: none;">
        <div class="modal-container">
            <h2><i class="fas fa-building"></i> Modifier l'entreprise</h2>
            <form id="editEntrepriseForm" enctype="multipart/form-data">
                <!-- Les champs seront remplis dynamiquement par JS -->
            </form>
        </div>
    </div>
        <script src="assets/js/settings.js"></script>
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
        });
    </script>
</body>
</html>