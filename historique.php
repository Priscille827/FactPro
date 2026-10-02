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
$entreprise = $pdo->prepare("SELECT id FROM entreprise WHERE user_id = ?");
$entreprise->execute([$_SESSION['user_id']]);
$entreprise = $entreprise->fetch();

if (!$entreprise) {
    die("Aucune entreprise associée à cet utilisateur");
}

//Récupérer toutes les factures de l'entreprise
$factures = $pdo->prepare("
    SELECT f.id, f.numero, f.date_facture, c.nom as client_nom,
           f.converted
    FROM facture_pro f
    LEFT JOIN client c ON f.client_id = c.id
    WHERE f.entreprise_id = ?
    ORDER BY f.created_at DESC
");
$factures->execute([$entreprise['id']]);
$factures = $factures->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historique des Factures</title>
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

.badge {
    padding: 5px 10px;
    border-radius: 4px;
    font-size: 0.8rem;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.bg-info {
    background-color: #17a2b8;
    color: white;
}

.badge i {
    font-size: 0.9em;
}

/* Nouveau système de messages - Version améliorée */
.message-container {
    position: fixed;
    top: 20px;
    left: 50%;
    transform: translateX(-50%);
    z-index: 1200; /* Plus élevé que l'overlay (1100) */
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
.scrollable-table-wrapper {
    overflow-y: auto;
    max-height: 300px; /* Ajustez cette valeur selon vos besoins */
    position: relative;
}

.scrollable-table-wrapper thead th {
    position: sticky;
    top: 0;
    background-color: #f8f9fa; /* Couleur de fond pour l'en-tête */
    z-index: 10;
}

/* Style pour assurer que les colonnes s'alignent entre l'en-tête et le corps */
.data-table {
    width: 100%;
    border-collapse: collapse;
}

.data-table th, 
.data-table td {
    padding: 8px 12px;
    text-align: left;
    border-bottom: 1px solid #dee2e6;
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
                <li><a href="historique.php" class="active"><i class="fas fa-history"></i> Historique</a></li>
                <li><a href="factures_definitives.php" ><i class="fas fa-file-invoice-dollar"></i>
                        Facture</a></li>
                <li><a href="editorial.php"><i class="fas fa-chart-line me-2"></i> Éditorial</a></li>
                <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Déconnexion</a></li>
            </ul>
        </aside>
        
        <main class="dashboard-main">
            <h1 class="page-title">Historique des Factures</h1>
           
            <div class="search-box">
    <i class="fas fa-search"></i>
    <input type="text" id="factureSearch" placeholder="Rechercher par n°, client ou date...">
    
</div>
<div class="table-container">
    <div class="table-title-container">
        <h2 class="table-title">Toutes les factures</h2>
        <a href="factures.php" class="btn btn-primary">
            <i class="fas fa-plus"></i> Nouvelle facture
        </a>
    </div>
    
    <div class="scrollable-table-wrapper">
        <table class="data-table">
            <thead>
                <tr>
                    <th>N° Facture</th>
                    <th>Date</th>
                    <th>Client</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($factures)): ?>
                    <tr>
                        <td colspan="4" class="text-center">Aucune facture trouvée</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($factures as $facture): ?>
                        <tr>
                            <td><?= htmlspecialchars($facture['numero']) ?></td>
                            <td><?= date('d/m/Y', strtotime($facture['date_facture'])) ?></td>
                            <td><?= htmlspecialchars($facture['client_nom'] ?? 'Non spécifié') ?></td>
                            <td class="invoice-actions">
                                <button class="btn btn-primary btn-sm view-invoice" 
                                        data-id="<?= $facture['id'] ?>">
                                    <i class="fas fa-eye"></i> 
                                </button>
                                
                                <?php if (!isset($facture['converted']) || !$facture['converted']): ?>
                                    <a href="factures.php?edit=<?= $facture['id'] ?>" 
                                       class="btn btn-warning">
                                        <i class="fas fa-edit"></i> 
                                    </a>
                                    <button class="btn btn-danger btn-sm delete-invoice" 
                                    data-id="<?= $facture['id'] ?>">
                                        <i class="fas fa-trash"></i> 
                                    </button>
                                    <button class="btn btn-success btn-sm convert-to-final" 
                                            data-id="<?= $facture['id'] ?>">
                                        <i class="fas fa-file-invoice-dollar"></i> Convertir
                                    </button>
                                <?php else: ?>
                                    <span class="badge bg-info">
                                        <i class="fas fa-check-circle"></i> Déjà convertie
                                    </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
        </main>
    </div>

    <!-- Modal de visualisation -->
    <div id="invoicePreviewModal" class="invoice-preview-modal">
        <span class="close-preview">&times;</span>
        <div class="invoice-preview-container" id="invoicePreviewContent">
            <!-- Contenu chargé dynamiquement -->
        </div>
        <div class="modal-actions" style="text-align:center;margin-top:20px;">
        <button id="downloadPdf" class="btn btn-primary">
            <i class="fas fa-file-pdf"></i> Télécharger en PDF
        </button>
    </div>

    </div>
    <div class="message-container" id="messageContainer"></div>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script>

    $(document).ready(function() {
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
// Fonction de normalisation améliorée pour la recherche
const normalizeSearchTerm = (term) => {
    return term.toString()
        .normalize("NFD").replace(/[\u0300-\u036f]/g, "") // Enlève les accents
        .toLowerCase()
        .replace(/[^\w\s]/g, ''); // Enlève la ponctuation
};

// Recherche des factures
const factureSearch = document.getElementById('factureSearch');


factureSearch.addEventListener('input', function(e) {
    const searchTerm = normalizeSearchTerm(e.target.value);
    
    document.querySelectorAll('.data-table tbody tr').forEach(row => {
        if (row.querySelector('td[colspan]')) return;
        
        const numero = normalizeSearchTerm(row.querySelector('td:nth-child(1)').textContent);
        const date = normalizeSearchTerm(row.querySelector('td:nth-child(2)').textContent);
        const client = normalizeSearchTerm(row.querySelector('td:nth-child(3)').textContent);
        
        row.style.display = (numero.includes(searchTerm)) || 
                          (date.includes(searchTerm)) || 
                          (client.includes(searchTerm)) ? '' : 'none';
    });
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
            html += `<button class="${action.class || ''}">${action.text}</button>`;
        });
        html += `</div>`;
    } else {
        // Bouton de fermeture pour les messages normaux
        html += `<button class="btn-close"><i class="fas fa-times"></i></button>`;
    }
    
    message.innerHTML = html;
    container.appendChild(message);
    
    // Gestion des événements pour les boutons dans le message
    if (options.actions) {
        message.querySelector('.btn-confirm').addEventListener('click', options.confirmAction);
        message.querySelector('.btn-cancel').addEventListener('click', () => closeMessage(message));
    } else {
        message.querySelector('.btn-close').addEventListener('click', () => closeMessage(message));
    }
    
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

       
    // Visualiser une facture
    let currentInvoiceId = null;
    $('.view-invoice').on('click', function() {
        currentInvoiceId = $(this).data('id');
        
        $.ajax({
            url: 'api/get_invoice.php',
            method: 'GET',
            data: { id: currentInvoiceId },
            success: function(response) {
                $('#invoicePreviewContent').html(response);
                $('#invoicePreviewModal').show();
            },
            error: function() {
                showMessage('Erreur lors du chargement de la facture', 'error');
            }
        });
    });

    // Télécharger la facture en PDF
    $('#downloadPdf').on('click', function() {
        if (!currentInvoiceId) return;
        
        const btn = $(this);
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Génération...');
        
        $.ajax({
            url: 'api/get_invoice.php',
            method: 'GET',
            data: { id: currentInvoiceId },
            success: function(response) {
                generatePdf(response, 'FacturePro_' + currentInvoiceId);
            },
            error: function() {
                showMessage('Erreur lors de la génération du PDF', 'error');
            },
            complete: function() {
                btn.prop('disabled', false).html('<i class="fas fa-file-pdf"></i> Télécharger en PDF');
            }
        });
    });

   function generatePdf(htmlContent, fileName) {
    const { jsPDF } = window.jspdf;
    
    // Créer un élément temporaire pour le contenu
    const element = document.createElement('div');
    element.style.position = 'absolute';
    element.style.left = '-9999px';
    element.style.width = '800px'; // Largeur fixe pour le PDF
    element.innerHTML = htmlContent;
    document.body.appendChild(element);

    const options = {
        scale: 2,
        useCORS: true,
        allowTaint: true,
        windowWidth: 800, // Même largeur que l'élément
        scrollX: 0,
        scrollY: 0,
        letterRendering: true,
        logging: false
    };

    html2canvas(element, options).then(canvas => {
        const pdf = new jsPDF({
            orientation: 'portrait',
            unit: 'mm',
            format: 'a4'
        });

        // Dimensions de l'image et du PDF
        const imgWidth = pdf.internal.pageSize.getWidth() - 20; // Largeur avec marges
        const imgHeight = (canvas.height * imgWidth) / canvas.width;
        
        // Hauteur maximale d'une page A4 en mm (moins les marges)
        const pageHeight = pdf.internal.pageSize.getHeight() - 20;
        
        // Si le contenu est plus grand qu'une page, on découpe
        if (imgHeight > pageHeight) {
            // Ratio pour calculer la position de découpe
            const ratio = imgWidth / canvas.width;
            let position = 0;
            let remainingHeight = imgHeight;
            
            while (remainingHeight > 0) {
                // Hauteur de la partie à ajouter (ne dépasse pas pageHeight)
                const sectionHeight = Math.min(remainingHeight, pageHeight);
                
                // Ajouter une nouvelle page si ce n'est pas la première
                if (position > 0) {
                    pdf.addPage();
                }
                
                // Ajouter la section courante
                pdf.addImage(
                    canvas.toDataURL('image/png'),
                    'PNG',
                    10, // marge gauche
                    10, // marge haut
                    imgWidth,
                    sectionHeight,
                    undefined,
                    'FAST',
                    -position // décalage vertical pour montrer la bonne section
                );
                
                position += sectionHeight;
                remainingHeight -= sectionHeight;
            }
        } else {
            // Cas simple - une seule page
            pdf.addImage(
                canvas.toDataURL('image/png'),
                'PNG',
                10,
                10,
                imgWidth,
                imgHeight
            );
        }

        // Sauvegarder le PDF
        pdf.save(fileName + '.pdf');
        document.body.removeChild(element);
    }).catch(err => {
        console.error('Erreur:', err);
        showMessage('Erreur lors de la génération du PDF', 'error');
        document.body.removeChild(element);
    });
}

        // Fermer le modal
        $('.close-preview').on('click', function() {
            $('#invoicePreviewModal').hide();
        });

        
   
// Gestion de la suppression de facture
$(document).on('click', '.delete-invoice', function() {
    const invoiceId = $(this).data('id');
    const button = this;
    
    showMessage('Voulez-vous vraiment supprimer cette facture ?', 'confirm', {
        actions: [
            {
                text: 'Oui',
                class: 'btn-confirm'
            },
            {
                text: 'Non',
                class: 'btn-cancel'
            }
        ],
        confirmAction: function() {
            closeMessage(this.closest('.message'));
            
            $.ajax({
                url: 'api/delete_invoice.php',
                method: 'POST',
                data: { id: invoiceId },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        showMessage('Facture supprimée avec succès', 'success');
                        $(button).closest('tr').fadeOut(300, function() {
                            $(this).remove();
                        });
                    } else {
                        showMessage('Erreur: ' + (response.message || 'Échec de la suppression'), 'error');
                    }
                },
                error: function() {
                    showMessage('Une erreur est survenue lors de la suppression', 'error');
                }
            });
        }
    });
});
   // Gestion de la conversion de facture
$(document).on('click', '.convert-to-final', function() {
    const invoiceId = $(this).data('id');
    const button = $(this);
    
    showMessage('Voulez-vous convertir cette facture proforma en facture définitive ?', 'confirm', {
        actions: [
            {
                text: 'Oui',
                class: 'btn-confirm'
            },
            {
                text: 'Non',
                class: 'btn-cancel'
            }
        ],
        confirmAction: function() {
            closeMessage(this.closest('.message'));
            
            button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Conversion...');
            
            $.ajax({
                url: 'api/convert_invoice.php',
                method: 'POST',
                data: { id: invoiceId },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        showMessage('Facture convertie avec succès ! Numéro: ' + response.facture_id, 'success', {
                            duration: 5000
                        });
                        // Rediriger vers la facture définitive
                        window.location.href = 'factures_definitives.php?view=' + response.facture_id;
                    } else {
                        showMessage('Erreur: ' + (response.message || 'Échec de la conversion'), 'error');
                        button.prop('disabled', false).html('<i class="fas fa-file-invoice-dollar"></i> Convertir');
                    }
                },
                error: function() {
                    showMessage('Une erreur est survenue lors de la conversion', 'error');
                    button.prop('disabled', false).html('<i class="fas fa-file-invoice-dollar"></i> Convertir');
                }
            });
        }
    });
});
  
    });
    </script>
</body>
</html>