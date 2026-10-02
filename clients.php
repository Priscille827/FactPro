<?php
session_start();
require_once 'config/db.php';
require_once 'config/helpers.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['entreprise_id'])) {
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

// Récupérer l'entreprise de l'utilisateur
$entreprise = $pdo->prepare("SELECT id FROM entreprise WHERE user_id = ?");
$entreprise->execute([$_SESSION['user_id']]);
$entreprise = $entreprise->fetch();

if (!$entreprise) {
    die("Aucune entreprise associée à cet utilisateur");
}

// Récupérer tous les clients de l'entreprise
$clients = $pdo->prepare("SELECT * FROM client WHERE entreprise_id = ? ORDER BY nom ASC ");
$clients->execute([$entreprise['id']]);
$clients = $clients->fetchAll();

// Générer un token CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Clients - Facture Proforma</title>

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

        .header-actions {
            display: flex;
            gap: 15px;
            align-items: center;
        }

        .search-box {
            position: relative;
            width: 300px;
        }

        .search-box i {
            position: absolute;
            left: 10px;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
        }

        .search-box input {
            padding-left: 35px;
            width: 100%;
            height: 38px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        .search-box input:focus {
            outline: none;
            border-color: #80bdff;
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, .25);
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

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }

        .page-title {
            font-size: 2rem;
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

        /* Alert Styles */
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid transparent;
        }

        .alert-info {
            background-color: #e7f5ff;
            border-color: #d0ebff;
            color: #1864ab;
        }

        /* Table Styles */
        .table-container {
            background-color: var(--table-bg);
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            border: 1px solid rgba(0, 0, 0, 0.03);
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

        .btn-edit {
            background-color: var(--warning-color);
            color: var(--dark-color);
        }

        .btn-delete {
            background-color: var(--danger-color);
            color: white;
        }

        .btn-cancel {
            background-color: var(--gray-color);
            color: white;
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 0.8rem;
        }

        /* Form Overlay */
        .form-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0, 0, 0, 0.7);
            z-index: 1100;
            justify-content: center;
            align-items: center;
        }

        .form-container {
            background-color: white;
            width: 100%;
            max-width: 600px;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 5px 30px rgba(0, 0, 0, 0.3);
        }

        .form-container h2 {
            margin-bottom: 25px;
            color: var(--dark-color);
            font-size: 1.5rem;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
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
        }

        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 15px;
            margin-top: 30px;
        }

        /* Tooltip for address */
        [data-tooltip] {
            position: relative;
            cursor: pointer;
        }

        [data-tooltip]:hover::after {
            content: attr(data-tooltip);
            position: absolute;
            bottom: 100%;
            left: 50%;
            transform: translateX(-50%);
            background-color: var(--dark-color);
            color: white;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 0.8rem;
            white-space: nowrap;
            z-index: 100;
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
            .page-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }

            .data-table th,
            .data-table td {
                padding: 10px 12px;
                font-size: 0.9rem;
            }

            .btn {
                padding: 8px 12px;
                font-size: 0.8rem;
            }

            .form-container {
                padding: 20px;
                margin: 0 15px;
            }
        }

/* Conteneur principal - positionnement fixe au centre */
#client_message {
    position: fixed;
    top: 20px;
    left: 0;
    right: 0;
    display: flex;
    justify-content: center;
    z-index: 1000;
    pointer-events: none; /* Permet de cliquer à travers */
}

/* Style de la boîte de notification */
#client_message span {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 12px 24px;
    border-radius: 6px;
    font-size: 14px;
    font-weight: 500;
    animation: fadeIn 0.3s ease-out;
    
    /* Style visuel */
    background-color: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    
    /* Effet de profondeur */
    transform: translateZ(0);
    backdrop-filter: blur(4px);
}

/* Animations */
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-30px) scale(0.95); }
    to { opacity: 1; transform: translateY(0) scale(1); }
}

.fade-out {
    animation: fadeOut 0.4s cubic-bezier(0.4, 0, 1, 1) forwards;
}

@keyframes fadeOut {
    from { opacity: 1; transform: translateY(0); }
    to { opacity: 0; transform: translateY(-20px); }
}

.message-container {
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 1100;
            max-width: 500px;
            width: 90%;
            display: flex;
            flex-direction: column;
            align-items: center;
            pointer-events: none;
            z-index: 1200; 
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
            background: linear-gradient(135deg, rgba(57, 80, 100, 0.9), rgba(57, 102, 148, 0.9));
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
                <?php echo htmlspecialchars($user['name']); ?></span> 
                

        </header>

        <aside class="dashboard-sidebar">
            <ul class="sidebar-menu">
            <li><a href="dashboard.php"><i class="fas fa-home"></i> Tableau de bord</a></li>
                <li><a href="parametres.php"><i class="fas fa-cog"></i> Paramètres</a></li>
                <li><a href="produits.php"><i class="fas fa-boxes"></i> Produits</a></li>
                <li><a href="clients.php" class="active"><i class="fas fa-users"></i> Clients</a></li>
                <li><a href="factures.php"><i class="fas fa-file-invoice"></i> Factures ProForma</a></li>
                <li><a href="historique.php"><i class="fas fa-history"></i> Historique</a></li>
                <li><a href="factures_definitives.php" ><i class="fas fa-file-invoice-dollar"></i>
                        Facture</a></li>
                <li><a href="editorial.php"><i class="fas fa-chart-line me-2"></i>Statistiques</a></li>
                <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Déconnexion</a></li>
            </ul>
        </aside>

        <main class="dashboard-main">
            <div class="page-header">
                <h1 class="page-title">Gestion des Clients</h1>
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" id="clientSearch" placeholder="Rechercher un client...">
                    <button id="resetSearch" class="btn btn-sm" style="display:none; position:absolute; right:5px; top:5px;">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <button id="addClientBtn" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Nouveau Client
                </button>
            </div>

            <?php if (empty($clients)): ?>
                <div class="alert alert-info">
                    Aucun client enregistré pour votre entreprise.
                </div>
            <?php else: ?>
                <div class="table-container" style="max-height: 400px; overflow-y: auto; overflow-x: hidden;">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Nom</th>
                                <th>Adresse</th>
                                <th>Téléphone</th>
                                <th>Email</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id='listeclient'>
                            <?php foreach ($clients as $client): ?>
                                <tr id="client-<?= $client['id'] ?>">
                                    <td><?= htmlspecialchars($client['nom']) ?></td>
                                    <td data-tooltip="<?= htmlspecialchars($client['adresse']) ?>">
                                        <?= htmlspecialchars(substr($client['adresse'], 0, 30)) ?>
                                        <?= strlen($client['adresse']) > 30 ? '...' : '' ?>
                                    </td>
                                    <td><?= htmlspecialchars($client['telephone']) ?></td>
                                    <td><?= htmlspecialchars($client['email']) ?></td>
                                    <td>
                                        <button class="btn btn-edit btn-sm" onclick="openEditForm(
                                            <?= $client['id'] ?>,
                                            '<?= htmlspecialchars($client['nom'], ENT_QUOTES) ?>',
                                            '<?= htmlspecialchars($client['adresse'], ENT_QUOTES) ?>',
                                            '<?= htmlspecialchars($client['telephone'], ENT_QUOTES) ?>',
                                            '<?= htmlspecialchars($client['email'], ENT_QUOTES) ?>'
                                        )">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-delete btn-sm" onclick="deleteClient(<?= $client['id'] ?>)">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </main>
    </div>
    <div class="message-container" id="messageContainer"></div>

       <!-- Formulaire overlay -->
       <div id="formOverlay" class="form-overlay">
        <div class="form-container">
            <h2 id="formTitle"><i class="fas fa-user"></i> Nouveau Client</h2>
            <div id='client_message'></div>
            <form id="clientForm">
                <input type="hidden" id="client_id" name="client_id">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="entreprise_id" value="<?= $entreprise['id'] ?>">

                <div class="form-group">
                    <label for="nom">Nom *</label>
                    <input type="text" id="nom" name="nom" required class="form-control">
                </div>

                <div class="form-group">
                    <label for="adresse">Adresse</label>
                    <textarea id="adresse" name="adresse" rows="3" class="form-control"></textarea>
                </div>

                <div class="form-group">
                    <label for="telephone">Téléphone</label>
                    <input type="text" id="telephone" name="telephone" class="form-control">
                </div>

                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" class="form-control">
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> <span id="submitText">Enregistrer</span>
                    </button>
                    <button type="button" id="cancelBtn" class="btn btn-cancel">
                        <i class="fas fa-times"></i> Annuler
                    </button>
                </div>
            </form>
        </div>
    </div>


    <script>
       
        // Gestion du menu toggle
        const menuToggle = document.getElementById('menuToggle');
        const sidebar = document.querySelector('.dashboard-sidebar');
        const body = document.body;

        if (menuToggle && sidebar) {
            menuToggle.addEventListener('click', function() {
                sidebar.classList.toggle('active');
                body.classList.toggle('sidebar-active');
            });

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

        // Gestion du formulaire
        const formOverlay = document.getElementById('formOverlay');
        const clientForm = document.getElementById('clientForm');
        let currentAction = 'add';

        // Vérifier que tous les éléments existent avant de les utiliser
        document.addEventListener('DOMContentLoaded', function() {
            // Ouvrir formulaire d'ajout
            const addClientBtn = document.getElementById('addClientBtn');
            if (addClientBtn) {
                addClientBtn.addEventListener('click', () => {
                    currentAction = 'add';
                    const formTitle = document.getElementById('formTitle');
                    const submitText = document.getElementById('submitText');
                    
                    if (formTitle) formTitle.innerHTML = '<i class="fas fa-user"></i> Nouveau Client';
                    if (submitText) submitText.textContent = 'Enregistrer';
                    
                    clientForm.reset();
                    if (formOverlay) formOverlay.style.display = 'flex';
                });
            }

            // Fermer formulaire
            const cancelBtn = document.getElementById('cancelBtn');
            if (cancelBtn) {
                cancelBtn.addEventListener('click', () => {
                    if (formOverlay) formOverlay.style.display = 'none';
                });
            }
        });

        // Fonction pour ouvrir le formulaire d'édition avec vérifications
        function openEditForm(id, nom, adresse, telephone, email) {
            if (!formOverlay) {
                console.error("Le formulaire n'est pas disponible");
                return;
            }

            currentAction = 'edit';
            
            // Mettre à jour seulement si les éléments existent
            const formTitle = document.getElementById('formTitle');
            const submitText = document.getElementById('submitText');
            
            if (formTitle) {
                formTitle.innerHTML = '<i class="fas fa-user"></i> Modifier Client';
            }
            
            if (submitText) {
                submitText.textContent = 'Mettre à jour';
            }

            // Remplir les champs du formulaire avec vérification
            const clientIdField = document.getElementById('client_id');
            const nomField = document.getElementById('nom');
            const adresseField = document.getElementById('adresse');
            const telephoneField = document.getElementById('telephone');
            const emailField = document.getElementById('email');
            
            if (clientIdField) clientIdField.value = id;
            if (nomField) nomField.value = nom;
            if (adresseField) adresseField.value = adresse;
            if (telephoneField) telephoneField.value = telephone;
            if (emailField) emailField.value = email;
            
            // Afficher le formulaire
            formOverlay.style.display = 'flex';
        }

        // Fermer formulaire
        document.getElementById('cancelBtn').addEventListener('click', () => {
            formOverlay.style.display = 'none';
        });

        const searchInput = document.getElementById('clientSearch');
        const resetBtn = document.getElementById('resetSearch');

        // Fonction de normalisation améliorée
        const normalizeString = (str) => str.normalize("NFD").replace(/[\u0300-\u036f\s\-\.\(\)]/g, "").toLowerCase();

        searchInput.addEventListener('input', function(e) {
            const searchTerm = normalizeString(e.target.value);
            const hasSearchTerm = searchTerm.length > 0;

            resetBtn.style.display = hasSearchTerm ? '' : 'none';

            document.querySelectorAll('#listeclient tr').forEach(row => {
                const name = normalizeString(row.querySelector('td:nth-child(1)').textContent);
                const phone = normalizeString(row.querySelector('td:nth-child(3)').textContent);
                const email = normalizeString(row.querySelector('td:nth-child(4)').textContent);

                const matchFound = name.includes(searchTerm) ||
                    phone.includes(searchTerm) ||
                    email.includes(searchTerm);

                row.style.display = matchFound ? '' : 'none';
            });
        });

        resetBtn.addEventListener('click', function() {
            searchInput.value = '';
            resetBtn.style.display = 'none';
            document.querySelectorAll('#listeclient tr').forEach(row => {
                row.style.display = '';
            });
        });

        // Modifier la soumission du formulaire pour utiliser les nouveaux messages
        clientForm.addEventListener('submit', async (e) => {
            e.preventDefault();

            const submitBtn = clientForm.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Traitement...';

            try {
                const formData = new FormData(clientForm);
                const url = currentAction === 'add' ? 'api/add_client.php' : 'api/update_client.php';

                const response = await fetch(url, {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    showMessage('success', 
                        currentAction === 'add' ? 'Client ajouté avec succès!' : 'Client modifié avec succès!', {
                        duration: 3000,
                        replacePrevious: true
                    });

                    if (currentAction === 'edit') {
                        formOverlay.style.display = 'none';
                        updateClientRow(data.client);
                    } else {
                        clientForm.reset();
                        addClientToTable(data.client);
                    }
                } else {
                    showMessage('error', 'Erreur: ' + (data.message || 'Action non autorisée'), {
                        duration: 4000,
                        replacePrevious: true
                    });
                }
            } catch (error) {
                console.error('Error:', error);
                showMessage('error', 'Une erreur est survenue', {
                    duration: 4000,
                    replacePrevious: true
                });
            } finally {
                submitBtn.disabled = false;
                submitBtn.innerHTML = `<i class="fas fa-save"></i> ${currentAction === 'add' ? 'Enregistrer' : 'Mettre à jour'}`;
            }
        });
        // Fonction pour ajouter un nouveau client au tableau
        function addClientToTable(client) {
            const tbody = document.getElementById('listeclient');
            if (!tbody) return;

            const row = document.createElement('tr');
            row.id = 'client-' + client.id;

            row.innerHTML = `
                <td>${escapeHtml(client.nom)}</td>
                <td data-tooltip="${escapeHtml(client.adresse)}">
                    ${escapeHtml(client.adresse.substring(0, 20))}${client.adresse.length > 20 ? '...' : ''}
                </td>
                <td>${escapeHtml(client.telephone)}</td>
                <td>${escapeHtml(client.email)}</td>
                <td>
                    <button class="btn btn-edit btn-sm" onclick="openEditForm(${client.id}, '${escapeHtml(client.nom)}', '${escapeHtml(client.adresse)}', '${escapeHtml(client.telephone)}', '${escapeHtml(client.email)}')">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="btn btn-delete btn-sm" onclick="deleteClient(${client.id})">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            `;

            tbody.insertBefore(row, tbody.firstChild);
        }

        // Fonction pour mettre à jour une ligne existante
        function updateClientRow(client) {
            const row = document.getElementById('client-' + client.id);
            if (!row) return;

            row.innerHTML = `
                <td>${escapeHtml(client.nom)}</td>
                <td data-tooltip="${escapeHtml(client.adresse)}">
                    ${escapeHtml(client.adresse.substring(0, 20))}${client.adresse.length > 20 ? '...' : ''}
                </td>
                <td>${escapeHtml(client.telephone)}</td>
                <td>${escapeHtml(client.email)}</td>
                <td>
                    <button class="btn btn-edit btn-sm" onclick="openEditForm(${client.id}, '${escapeHtml(client.nom)}', '${escapeHtml(client.adresse)}', '${escapeHtml(client.telephone)}', '${escapeHtml(client.email)}')">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="btn btn-delete btn-sm" onclick="deleteClient(${client.id})">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            `;
        }

        // Fonction pour échapper les caractères HTML (sécurité)
        function escapeHtml(unsafe) {
            if (!unsafe) return '';
            return unsafe
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }

                    // Fonction pour afficher un message - Version améliorée
        function showMessage(type, text, options = {}) {
            const container = document.getElementById('messageContainer');
            
            // Fermer les messages précédents du même type si nécessaire
            if (options.replacePrevious) {
                const existingMessages = container.querySelectorAll('.message');
                existingMessages.forEach(msg => {
                    if (msg.classList.contains(`message-${type}`)) {
                        msg.classList.add('hide');
                        setTimeout(() => msg.remove(), 300);
                    }
                });
            }
            
            const message = document.createElement('div');
            message.className = `message message-${type}`;
            
            // Contenu du message
            let html = `
                <div class="message-content">
                    <div class="message-icon"><i class="fas ${getIconForType(type)}"></i></div>
                    <div class="message-text">${text}</div>
                </div>
            `;
            
            // Actions si nécessaire (pour les confirmations)
            if (options.actions) {
                html += `<div class="message-actions">`;
                options.actions.forEach(action => {
                    html += `<button onclick="${action.onclick}" class="${action.class || ''}">${action.text}</button>`;
                });
                html += `</div>`;
            } else {
                // Bouton de fermeture pour les messages normaux
                html += `<button class="btn-close" onclick="this.parentElement.classList.add('hide')">
                            <i class="fas fa-times"></i>
                         </button>`;
            }
            
            message.innerHTML = html;
            container.appendChild(message);
            
            // Délai avant disparition (sauf pour les confirmations)
            if (!options.actions && options.timeout !== false) {
                setTimeout(() => {
                    message.classList.add('hide');
                    setTimeout(() => message.remove(), 300);
                }, options.duration || 4000);
            }
            
            return message;
        }

        function getIconForType(type) {
            const icons = {
                'success': 'fa-check-circle',
                'error': 'fa-times-circle',
                'warning': 'fa-exclamation-triangle',
                'info': 'fa-info-circle',
                'confirm': 'fa-question-circle'
            };
            return icons[type] || 'fa-info-circle';
        }

        // Suppression d'un client avec confirmation visuelle améliorée
        function deleteClient(id) {
            const confirmMessage = showMessage('confirm', 'Voulez-vous vraiment supprimer ce client ?', {
                actions: [
                    {
                        text: 'Oui',
                        class: 'btn-confirm',
                        onclick: `confirmDelete(${id}, this)`
                    },
                    {
                        text: 'Non',
                        class: 'btn-cancel',
                        onclick: "closeMessage(this)"
                    }
                ],
                timeout: false,
                replacePrevious: true
            });
        }

        function closeMessage(button) {
            const message = button.closest('.message');
            message.classList.add('hide');
            setTimeout(() => message.remove(), 300);
        }

        function confirmDelete(id, button) {
            const message = button.closest('.message');
            message.classList.add('hide');
            setTimeout(() => message.remove(), 300);
            
            fetch(`api/delete_client.php?id=${id}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById(`client-${id}`).remove();
                        showMessage('success', 'Client supprimé avec succès', {
                            duration: 3000,
                            replacePrevious: true
                        });
                    } else {
                        showMessage('error', 'Erreur: ' + (data.message || 'Échec de la suppression'), {
                            duration: 4000,
                            replacePrevious: true
                        });
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showMessage('error', 'Une erreur est survenue', {
                        duration: 4000,
                        replacePrevious: true
                    });
                });
        }


    </script>
</body>
</html>