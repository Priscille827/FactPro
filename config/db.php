
<?php


$host = 'localhost';
$dbname = 'facture_proforma';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erreur de connexion : " . $e->getMessage());
}

// Vérifier si l'utilisateur est connecté et a une entreprise
// if (isset($_SESSION['user_id'])) {
//     $entreprise = $pdo->prepare("SELECT id FROM entreprise WHERE user_id = ?");
//     $entreprise->execute([$_SESSION['user_id']]);
//     $entreprise = $entreprise->fetch();
    
//     if ($entreprise) {
//         $_SESSION['entreprise_id'] = $entreprise['id'];
//     } else {
//         // Rediriger vers la création d'entreprise si aucune n'existe
//         header('Location: parametres.php');
//         exit;
//     }
// }

?>