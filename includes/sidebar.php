<aside class="dashboard-sidebar">
    <nav class="nav-menu">
        <ul>
            <li>
                <a href="dashboard.php" class="<?php echo basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : ''; ?>">
                    <i class="fas fa-tachometer-alt"></i> Tableau de bord
                </a>
            </li>
            <li>
                <a href="factures.php" class="<?php echo basename($_SERVER['PHP_SELF']) === 'factures.php' ? 'active' : ''; ?>">
                    <i class="fas fa-file-invoice"></i> Factures
                </a>
            </li>
            <li>
                <a href="historique.php" class="<?php echo basename($_SERVER['PHP_SELF']) === 'historique.php' ? 'active' : ''; ?>">
                    <i class="fas fa-history"></i> Historique
                </a>
            </li>
            <li>
                <a href="clients.php" class="<?php echo basename($_SERVER['PHP_SELF']) === 'clients.php' ? 'active' : ''; ?>">
                    <i class="fas fa-users"></i> Clients
                </a>
            </li>
            <li>
                <a href="produits.php" class="<?php echo basename($_SERVER['PHP_SELF']) === 'produits.php' ? 'active' : ''; ?>">
                    <i class="fas fa-boxes"></i> Produits
                </a>
            </li>
            <li>
                <a href="parametres.php" class="<?php echo basename($_SERVER['PHP_SELF']) === 'parametres.php' ? 'active' : ''; ?>">
                    <i class="fas fa-cog"></i> Paramètres
                </a>
            </li>
        </ul>
    </nav>
    
    <div class="logout-container">
        <button class="btn-logout" onclick="window.location.href='logout.php'">
            <i class="fas fa-sign-out-alt"></i> Déconnexion
        </button>
    </div>
</aside>
