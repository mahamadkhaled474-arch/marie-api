<?php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Méthode non autorisée');
}

$data = json_decode(file_get_contents('php://input'), true);

$nom       = $data['nom']       ?? '';
$prenom    = $data['prenom']    ?? '';
$email     = $data['email']     ?? '';
$password  = $data['password']  ?? '';
$telephone = $data['telephone'] ?? '';
$adresse   = $data['adresse']   ?? '';

// Validation
if (empty($nom) || empty($prenom) || empty($email) || empty($password)) {
    sendResponse(false, 'Tous les champs sont requis');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    sendResponse(false, 'Email invalide');
}

// Vérifier si l'email existe déjà
$stmt = $pdo->prepare("SELECT id FROM citoyens WHERE email = ?");
$stmt->execute([$email]);
if ($stmt->fetch()) {
    sendResponse(false, 'Cet email est déjà utilisé');
}

// Insérer SANS hasher le mot de passe
try {
    $stmt = $pdo->prepare("
        INSERT INTO citoyens (nom, prenom, email, password, telephone, adresse) 
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$nom, $prenom, $email, $password, $telephone, $adresse]);
    
    $userId = $pdo->lastInsertId();
    
    sendResponse(true, 'Inscription réussie', [
        'id'     => $userId,
        'nom'    => $nom,
        'prenom' => $prenom,
        'email'  => $email
    ]);
} catch (PDOException $e) {
    sendResponse(false, 'Erreur: ' . $e->getMessage());
}
?>