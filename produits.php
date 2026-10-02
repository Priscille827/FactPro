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

// Récupérer la devise de l'entreprise
$deviseSymbole = 'XOF';
$stmt = $pdo->prepare("SELECT devise_symbole FROM entreprise WHERE id = ?");
$stmt->execute([$_SESSION['entreprise_id']]);
$entreprise = $stmt->fetch();
if ($entreprise && !empty($entreprise['devise_symbole'])) {
    $deviseSymbole = $entreprise['devise_symbole'];
}

// Récupérer les produits de l'entreprise
$produits = $pdo->prepare("SELECT * FROM produit WHERE entreprise_id = ? ORDER BY designation ASC ");
$produits->execute([$_SESSION['entreprise_id']]);
$produits = $produits->fetchAll();

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
    <title>Gestion des Produits - Facture Proforma</title>

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

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
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

        .btn-edit:hover {
            background-color: #f7b731;
            transform: translateY(-2px);
        }

        .btn-delete {
            background-color: var(--danger-color);
            color: white;
        }

        .btn-delete:hover {
            background-color: #c23616;
            transform: translateY(-2px);
        }

        .btn-cancel {
            background-color: var(--gray-color);
            color: white;
        }

        .btn-cancel:hover {
            background-color: #7f8c8d;
            transform: translateY(-2px);
        }

        /* Form Overlay */
        .form-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0, 0, 0, 0.5);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 1100;
        }

        .form-container {
            background-color: white;
            border-radius: 12px;
            width: 100%;
            max-width: 500px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        }

        .form-container h2 {
            margin-bottom: 25px;
            color: var(--dark-color);
            display: flex;
            align-items: center;
            gap: 10px;
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

        /* Alert */
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 25px;
        }

        .alert-info {
            background-color: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
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

            .dashboard-header {
                padding: 0 15px;
            }

            .user-menu span {
                display: none;
            }
        }

        /* Styles pour la recherche produits */
        .header-actions {
            display: flex;
            gap: 15px;
            align-items: center;
        }

        .search-box {
            position: relative;
            width: 100%;
            max-width: 350px;
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
            padding-right: 35px;
            width: 100%;
            height: 38px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        .reset-search {
            position: absolute;
            right: 5px;
            top: 5px;
            background: transparent;
            border: none;
            color: #6c757d;
            cursor: pointer;
            display: none;
        }

        .reset-search:hover {
            color: #dc3545;
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
                <li><a href="produits.php" class="active"><i class="fas fa-boxes"></i> Produits</a></li>
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
            <div class="page-header">
                <h1 class="page-title">Gestion des Produits</h1>

                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" id="produitSearch" placeholder="Rechercher par désignation ou prix...">
                    <button id="resetSearchProduit" class="btn btn-sm reset-search">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <button id="addProduitBtn" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Nouveau Produit
                </button>
            </div>

            <?php if (empty($produits)): ?>
                <div class="alert alert-info">
                    Aucun produit enregistré pour votre entreprise.
                </div>
            <?php else: ?>
                <div class="table-container"
                    style="display: block; max-height: 400px; overflow-y: auto; overflow-x: hidden;">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Désignation</th>
                                <th>Prix Unitaire</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="listeProduits">
                            <?php foreach ($produits as $produit): ?>
                                <tr id="produit-<?= htmlspecialchars($produit['id']) ?>">
                                    <td><?= htmlspecialchars($produit['designation']) ?></td>
                                    <td><?= number_format($produit['prix_unitaire'], 2, ',', ' ') ?>
                                        <?= htmlspecialchars($deviseSymbole) ?></td>
                                    <td>
                                        <button class="btn btn-edit" onclick="openEditForm(
                                        <?= (int) $produit['id'] ?>,
                                        '<?= htmlspecialchars($produit['designation'], ENT_QUOTES) ?>',
                                        <?= number_format($produit['prix_unitaire'], 2, '.', '') ?>
                                    )">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-delete" onclick="confirmDelete(<?= (int) $produit['id'] ?>)">
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

    <!-- Formulaire overlay -->
    <div id="formOverlay" class="form-overlay">
        <div class="form-container">
            <h2 id="formTitle"><i class="fas fa-box"></i> Nouveau Produit</h2>
            <div id='client_message'></div>
            <form id="produitForm">
                <input type="hidden" id="produit_id" name="produit_id">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="entreprise_id" value="<?= (int) $_SESSION['entreprise_id'] ?>">

                <div class="form-group">
                    <label for="designation">Désignation *</label>
                    <input type="text" id="designation" name="designation" required class="form-control">
                </div>

                <div class="form-group">
                    <label for="prix_unitaire">Prix Unitaire (<?= htmlspecialchars($deviseSymbole) ?>) *</label>
                    <input type="number" id="prix_unitaire" name="prix_unitaire" step="0.01" min="0" required
                        class="form-control">
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
    <div class="message-container" id="messageContainer"></div>

    <script>
        // Gestion du formulaire
        const formOverlay = document.getElementById('formOverlay');
        const produitForm = document.getElementById('produitForm');
        let currentAction = 'add';

        // Ouvrir formulaire d'ajout
        document.getElementById('addProduitBtn').addEventListener('click', () => {
            currentAction = 'add';
            document.getElementById('formTitle').innerHTML = '<i class="fas fa-box"></i> Nouveau Produit';
            document.getElementById('submitText').textContent = 'Enregistrer';
            produitForm.reset();
            document.getElementById('produit_id').value = '';
            formOverlay.style.display = 'flex';
        });

        // Ouvrir formulaire d'édition
        function openEditForm(id, designation, prix) {
            currentAction = 'edit';
            document.getElementById('formTitle').innerHTML = '<i class="fas fa-box"></i> Modifier Produit';
            document.getElementById('submitText').textContent = 'Mettre à jour';
            document.getElementById('produit_id').value = id;
            document.getElementById('designation').value = designation;
            document.getElementById('prix_unitaire').value = prix;
            formOverlay.style.display = 'flex';
        }

        // Fermer formulaire
        document.getElementById('cancelBtn').addEventListener('click', () => {
            formOverlay.style.display = 'none';
        });

        // Remplacer la partie soumission du formulaire par ce code :
        // Modifier la soumission du formulaire pour utiliser le nouveau système
        produitForm.addEventListener('submit', async (e) => {
            e.preventDefault();

            const submitBtn = produitForm.querySelector('button[type="submit"]');
            const originalBtnContent = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Traitement...';

            try {
                const formData = new FormData(produitForm);
                const url = currentAction === 'add' ? 'api/add_produit.php' : 'api/update_produit.php';

                const response = await fetch(url, {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    showMessage('Produit ' + (currentAction === 'add' ? 'ajouté' : 'mis à jour') + ' avec succès!', 'success');

                    if (currentAction === 'add') {
                        produitForm.reset();

                        const tbody = document.getElementById('listeProduits');
                        if (tbody) {
                            const newRow = document.createElement('tr');
                            newRow.id = `produit-${data.produit.id}`;
                            newRow.innerHTML = `
                            <td>${data.produit.designation}</td>
                            <td>${parseFloat(data.produit.prix_unitaire).toFixed(2)} <?= htmlspecialchars($deviseSymbole) ?></td>
                            <td>
                                <button class="btn btn-edit" onclick="openEditForm(
                                    ${data.produit.id},
                                    '${data.produit.designation.replace(/'/g, "\\'")}',
                                    ${parseFloat(data.produit.prix_unitaire).toFixed(2)}
                                )">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-delete" onclick="confirmDelete(${data.produit.id})">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        `;
                            tbody.appendChild(newRow);
                        }
                    } else {
                        formOverlay.style.display = 'none';

                        const row = document.getElementById(`produit-${data.produit.id}`);
                        if (row) {
                            row.querySelector('td:nth-child(1)').textContent = data.produit.designation;
                            row.querySelector('td:nth-child(2)').textContent =
                                `${parseFloat(data.produit.prix_unitaire).toFixed(2)} <?= htmlspecialchars($deviseSymbole) ?>`;
                        }
                    }
                } else {
                    showMessage('Erreur: ' + (data.message || 'Action non autorisée'), 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                showMessage('Une erreur est survenue', 'error');
            } finally {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtnContent;
            }
        });


        // Fonction pour afficher un message amélioré
        function showMessage(text, type = 'info', options = {}) {
            const container = document.getElementById('messageContainer');
            const message = document.createElement('div');
            message.className = `message message-${type}`;

            // Icône selon le type de message
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
        `;

            // Ajouter des actions si nécessaire (pour les confirmations)
            if (options.actions) {
                html += `<div class="message-actions">`;
                options.actions.forEach(action => {
                    html += `<button onclick="${action.onclick}" class="${action.class || ''}">${action.text}</button>`;
                });
                html += `</div>`;
            } else {
                // Bouton de fermeture pour les messages normaux
                html += `<button class="btn-close" onclick="closeMessage(this.parentElement)">
                        <i class="fas fa-times"></i>
                    </button>`;
            }

            message.innerHTML = html;
            container.appendChild(message);

            // Fermeture automatique après délai (sauf pour les confirmations)
            if (!options.actions && options.timeout !== false) {
                setTimeout(() => {
                    closeMessage(message);
                }, options.duration || 4000);
            }

            return message;
        }

        function closeMessage(message) {
            message.classList.add('hide');
            setTimeout(() => message.remove(), 300);
        }

        // Modifier la fonction confirmDelete pour utiliser le nouveau système
        function confirmDelete(id) {
            showMessage('Voulez-vous vraiment supprimer ce produit ?', 'confirm', {
                actions: [
                    {
                        text: 'Oui',
                        class: 'btn-confirm',
                        onclick: `deleteProduit(${id}, this)`
                    },
                    {
                        text: 'Non',
                        class: 'btn-cancel',
                        onclick: "closeMessage(this.parentElement.parentElement)"
                    }
                ],
                timeout: false
            });
        }

        // Modifier la fonction deleteProduit
        function deleteProduit(id, button) {
            const message = button.closest('.message');
            closeMessage(message);

            fetch(`api/delete_produit.php?id=${id}`, {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': '<?= htmlspecialchars($_SESSION['csrf_token']) ?>'
                }
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showMessage('Produit supprimé avec succès', 'success');
                        document.getElementById(`produit-${id}`).remove();
                    } else {
                        showMessage('Erreur: ' + (data.message || 'Échec de la suppression'), 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showMessage('Une erreur est survenue lors de la suppression', 'error');
                });
        }


        // Fonction de normalisation pour la recherche
        const normalizeSearch = (str) => {
            return str.toString()
                .normalize("NFD").replace(/[\u0300-\u036f]/g, "") // Supprime les accents
                .toLowerCase()
                .replace(/[^\w\s]/g, ''); // Supprime la ponctuation
        };

        // Recherche de produits
        const produitSearch = document.getElementById('produitSearch');
        const resetSearchProduit = document.getElementById('resetSearchProduit');

        produitSearch.addEventListener('input', function (e) {
            const searchTerm = normalizeSearch(e.target.value);
            const hasSearchTerm = searchTerm.length > 0;

            resetSearchProduit.style.display = hasSearchTerm ? 'block' : 'none';

            document.querySelectorAll('#listeProduits tr').forEach(row => {
                const designation = normalizeSearch(row.querySelector('td:nth-child(1)').textContent);
                const prix = normalizeSearch(row.querySelector('td:nth-child(2)').textContent);

                const matchFound = designation.includes(searchTerm) ||
                    prix.includes(searchTerm);

                row.style.display = matchFound ? '' : 'none';
            });
        });

        resetSearchProduit.addEventListener('click', function () {
            produitSearch.value = '';
            resetSearchProduit.style.display = 'none';
            document.querySelectorAll('#listeProduits tr').forEach(row => {
                row.style.display = '';
            });
        });




    </script>
</body>

</html>