<?php
// session_start();
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
?>

<header class="dashboard-header">
    <button class="menu-toggle" id="menuToggle">
        <i class="fas fa-bars"></i>
    </button>
    
    <div class="logo-container">
        <?php if ($logoPath && file_exists($logoPath)): ?>
            <img src="<?= htmlspecialchars($logoPath) ?>" class="header-logo" alt="Logo" style="border-radius:50%;object-fit:cover;width:42px;height:42px;">
        <?php else: ?>
            <div class="header-logo placeholder">
                <i class="fas fa-building"></i>
            </div>
        <?php endif; ?>
        <span class="company-name">Facture Proforma</spansp
    </div>
    
    <div class="user-info">
        <span><?= htmlspecialchars($_SESSION['user_name'] ?? 'Invité') ?></span>
    </div>
</header>


<!-- Inclusion safe du script -->
<script src="assets/js/script.js"></script>
