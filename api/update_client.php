

<?php
session_start();
require_once '../config/db.php';

// Vérifications similaires à add_client.php
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    die(json_encode(['success' => false, 'message' => 'Token CSRF invalide']));
}

if (!isset($_SESSION['entreprise_id'])) {
    die(json_encode(['success' => false, 'message' => 'Non autorisé']));
}

$id = $_POST['client_id'] ?? 0;
$nom = trim($_POST['nom'] ?? '');
$adresse = trim($_POST['adresse'] ?? '');
$telephone= trim($_POST['telephone'] ?? '');
$email=trim($_POST['email'] ?? '') ;

// Vérifier que le client appartient à l'entreprise
$stmt = $pdo->prepare("SELECT id FROM client WHERE id = ? AND entreprise_id = ?");
$stmt->execute([$id, $_SESSION['entreprise_id']]);
if (!$stmt->fetch()) {
    die(json_encode(['success' => false, 'message' => 'Client non trouvé']));
}

// Mettre à jour
$stmt = $pdo->prepare("UPDATE client SET nom = ?, adresse = ?, telephone = ?, email = ? 
                      WHERE id = ? AND entreprise_id = ?");
// ... [le reste du code avant l'update]

$stmt->execute([$nom, $adresse, $telephone, $email, $id, $_SESSION['entreprise_id']]);

// Retourner le client mis à jour
$updatedClient = [
    'id' => $id,
    'nom' => $nom,
    'adresse' => $adresse,
    'telephone' => $telephone,
    'email' => $email
];

echo json_encode([
    'success' => true,
    'client' => $updatedClient, // Ajout de l'objet client
    'message' => 'Client mis à jour avec succès'
]);

// ... [le reste du code]
?>