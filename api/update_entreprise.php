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

    if ($data['email'] === false) {
        throw new Exception("Email invalide");
    }

    // Gestion du logo
    if (!empty($_FILES['logo']['name'])) {
        $uploadDir = '../assets/uploads/logos/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $fileName = 'logo_' . uniqid() . '_' . basename($_FILES['logo']['name']);
        $targetPath = $uploadDir . $fileName;

        if (move_uploaded_file($_FILES['logo']['tmp_name'], $targetPath)) {
            $data['logo'] = $targetPath;
            
            // Supprimer l'ancien logo si il existe
            try {
                $stmt = $pdo->prepare("SELECT logo FROM entreprise WHERE user_id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $oldLogo = $stmt->fetchColumn();
                
                if ($oldLogo && file_exists($oldLogo)) {
                    @unlink($oldLogo);
                }
            } catch (PDOException $e) {
                error_log("Erreur lors de la récupération de l'ancien logo: " . $e->getMessage());
            }
        }
    } elseif (isset($_POST['remove_logo']) && $_POST['remove_logo'] == 'on') {
        $data['logo'] = null;
        try {
            $stmt = $pdo->prepare("SELECT logo FROM entreprise WHERE user_id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $oldLogo = $stmt->fetchColumn();
            
            if ($oldLogo && file_exists($oldLogo)) {
                @unlink($oldLogo);
            }
        } catch (PDOException $e) {
            error_log("Erreur lors de la suppression du logo: " . $e->getMessage());
        }
    }

    // Mise à jour en base
    $query = "UPDATE entreprise SET 
              nom = :nom,
              adresse = :adresse,
              telephone = :telephone,
              email = :email,
              tva = :tva,
              devise = :devise,
              devise_symbole = :devise_symbole"
              . (isset($data['logo']) ? ", logo = :logo" : "") . "
              WHERE user_id = :user_id";

    $stmt = $pdo->prepare($query);
    $success = $stmt->execute($data);

    if ($success) {
        $response = [
            'success' => true,
            'message' => 'Entreprise mise à jour avec succès',
            'logo_url' => $data['logo'] ?? null
        ];
    } else {
        throw new Exception("Erreur lors de la mise à jour en base de données");
    }
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
    error_log("Erreur dans update_entreprise.php: " . $e->getMessage());
}

echo json_encode($response);
?>