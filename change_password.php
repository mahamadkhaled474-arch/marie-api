<?php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Méthode non autorisée');
}

$data = json_decode(file_get_contents('php://input'), true);

$citoyen_id = $data['citoyen_id'] ?? '';
$current_password = $data['current_password'] ?? '';
$new_password = $data['new_password'] ?? '';

if (empty($citoyen_id) || empty($current_password) || empty($new_password)) {
    sendResponse(false, 'Tous les champs sont obligatoires');
}

if (strlen($new_password) < 6) {
    sendResponse(false, 'Le mot de passe doit contenir au moins 6 caractères');
}

try {
    // Vérifier le mot de passe actuel
    $stmt = $pdo->prepare("SELECT password FROM citoyens WHERE id = ?");
    $stmt->execute([$citoyen_id]);
    $user = $stmt->fetch();

    if (!$user) {
        sendResponse(false, 'Utilisateur introuvable');
    }

    if ($user['password'] !== $current_password) {
        sendResponse(false, 'Mot de passe actuel incorrect');
    }

    // Mettre à jour
    $stmt = $pdo->prepare("UPDATE citoyens SET password = ? WHERE id = ?");
    $stmt->execute([$new_password, $citoyen_id]);

    sendResponse(true, 'Mot de passe modifié avec succès');

} catch (PDOException $e) {
    sendResponse(false, 'Erreur: ' . $e->getMessage());
}
?>