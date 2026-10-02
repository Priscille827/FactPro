<?php
session_start();
require_once '../config/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit;
}

$response = ['success' => false, 'message' => ''];

try {
    // Validation des données
    $required = ['nom', 'adresse', 'tva', 'devise'];
    foreach ($required as $field) {
        if (empty($_POST[$field])) {
            throw new Exception("Le champ $field est requis");
        }
    }

    // Préparation des données
    $data = [
        'user_id' => $_SESSION['user_id'],
        'nom' => htmlspecialchars(trim($_POST['nom'])),
        'adresse' => htmlspecialchars(trim($_POST['adresse'])),
        'telephone' => htmlspecialchars(trim($_POST['telephone'] ?? '')),
        'email' => filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL),
        'tva' => (float)$_POST['tva'],
        'devise' => $_POST['devise'],
        'devise_symbole' => [
            'XOF' => 'FCFA', 
            'EUR' => '€', 
            'USD' => '$'
        ][$_POST['devise']] ?? 'FCFA'
    ];

     // Gestion du logo
     if (!empty($_FILES['logo']['name'])) {
        // Vérification que c'est bien une image
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
        $detectedType = mime_content_type($_FILES['logo']['tmp_name']);
        
        if (!in_array($detectedType, $allowedTypes)) {
            throw new Exception("Le fichier doit être une image (JPEG, PNG ou GIF)");
        }

        // Vérification de la taille (max 2MB)
        if ($_FILES['logo']['size'] > 2000000) {
            throw new Exception("L'image ne doit pas dépasser 2MB");
        }

        $uploadDir = '../assets/uploads/logos/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $fileName = 'logo_' . uniqid() . '.' . pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION);
        $targetPath = $uploadDir . $fileName;

        if (move_uploaded_file($_FILES['logo']['tmp_name'], $targetPath)) {
            // Stocker le chemin relatif seulement
            $data['logo'] = 'assets/uploads/logos/' . $fileName;
        } else {
            throw new Exception("Erreur lors de l'upload du fichier");
        }
    }

    // Insertion en base
    $query = "INSERT INTO entreprise 
              (user_id, nom, adresse, telephone, email, tva, devise, devise_symbole" 
              . (isset($data['logo']) ? ", logo" : "") . ")
              VALUES 
              (:user_id, :nom, :adresse, :telephone, :email, :tva, :devise, :devise_symbole" 
              . (isset($data['logo']) ? ", :logo" : "") . ")";

    $stmt = $pdo->prepare($query);
    $success = $stmt->execute($data);

    if ($success) {
        // Mettre à jour l'utilisateur avec l'ID de l'entreprise
        $entrepriseId = $pdo->lastInsertId();
        $pdo->prepare("UPDATE users SET entreprise_id = ? WHERE id = ?")
            ->execute([$entrepriseId, $_SESSION['user_id']]);

        $response = [
            'success' => true,
            'message' => 'Entreprise créée avec succès'
        ];
    }
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
?>