<?php
session_start();
require_once '../config/db.php';

// Vérifier CSRF token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    die(json_encode(['success' => false, 'message' => 'Token CSRF invalide']));
}

// Vérifier que l'utilisateur a une entreprise
if (!isset($_SESSION['entreprise_id'])) {
    die(json_encode(['success' => false, 'message' => 'Non autorisé']));
}

// Récupérer et filtrer les données
$nom = trim(filter_input(INPUT_POST, 'nom', FILTER_SANITIZE_STRING) ?? '');
$adresse = trim(filter_input(INPUT_POST, 'adresse', FILTER_SANITIZE_STRING) ?? '');
$telephone = trim(filter_input(INPUT_POST, 'telephone', FILTER_SANITIZE_STRING) ?? '');
$email = trim(filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL) ?? '');

// Validation des données
if (empty($nom)) {
    die(json_encode(['success' => false, 'message' => 'Le nom est obligatoire']));
}

if (strlen($nom) > 100) {
    die(json_encode(['success' => false, 'message' => 'Le nom ne doit pas dépasser 100 caractères']));
}

if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    die(json_encode(['success' => false, 'message' => 'Email invalide']));
}

if (!empty($telephone) && !preg_match('/^[\d\s\+\-\(\)]{8,20}$/', $telephone)) {
    die(json_encode(['success' => false, 'message' => 'Format téléphone invalide']));
}

try {
    $pdo->beginTransaction();

    // Vérifier si le client existe déjà (même nom pour la même entreprise)
    $check = $pdo->prepare("SELECT id FROM client 
                           WHERE entreprise_id = ? 
                           AND LOWER(nom) = LOWER(?)");
    $check->execute([$_SESSION['entreprise_id'], $nom]);
    
    if ($check->fetch()) {
        $pdo->rollBack();
        die(json_encode([
            'success' => false, 
            'message' => 'Un client avec ce nom existe déjà'
        ]));
    }

    // Insérer le client
    $stmt = $pdo->prepare("INSERT INTO client 
                          (nom, adresse, telephone, email, entreprise_id) 
                          VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([
        $nom,
        strlen($adresse) > 0 ? $adresse : null,
        strlen($telephone) > 0 ? $telephone : null,
        strlen($email) > 0 ? $email : null,
        $_SESSION['entreprise_id']
    ]);

    // Récupérer la liste mise à jour
    $clients = $pdo->prepare("SELECT * FROM client 
                             WHERE entreprise_id = ? 
                             ORDER BY nom ASC"); // Tri par nom ASC
    $clients->execute([$_SESSION['entreprise_id']]);
    $listeclient = $clients->fetchAll(PDO::FETCH_ASSOC);

  // ... [le reste du code avant le commit]

$pdo->commit();

// Retourner le nouveau client créé
$newClient = [
    'id' => $pdo->lastInsertId(),
    'nom' => $nom,
    'adresse' => $adresse,
    'telephone' => $telephone,
    'email' => $email
];

echo json_encode([
    'success' => true,
    'client' => $newClient, // Changé de 'data' à 'client'
    'message' => 'Client ajouté avec succès'
]);

// ... [le reste du code]

} catch (PDOException $e) {
    $pdo->rollBack();
    
    // Gestion spécifique des violations de contrainte unique
    if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
        die(json_encode([
            'success' => false, 
            'message' => 'Ce client existe déjà dans notre système'
        ]));
    }
    
    error_log("Erreur base de données: " . $e->getMessage());
    die(json_encode([
        'success' => false, 
        'message' => 'Une erreur technique est survenue'
    ]));
}