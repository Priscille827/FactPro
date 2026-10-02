<?php

if (isset($_SESSION['user_id'])) {
    $entreprise = $pdo->prepare("SELECT id FROM entreprise WHERE user_id = ?");
    $entreprise->execute([$_SESSION['user_id']]);
    $entreprise = $entreprise->fetch();
    
    if ($entreprise) {
        $_SESSION['entreprise_id'] = $entreprise['id'];
    } else {
        // Rediriger vers la création d'entreprise si aucune n'existe
        header('Location: parametres.php');
        exit;
    }
}
?>