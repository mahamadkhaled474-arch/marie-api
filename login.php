<?php
ob_start();
require_once 'config.php';
ob_clean();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_clean();
    sendResponse(false, 'Méthode non autorisée');
}

$data = json_decode(file_get_contents('php://input'), true);

$email    = $data['email']    ?? '';
$password = $data['password'] ?? '';

if (empty($email) || empty($password)) {
    ob_clean();
    sendResponse(false, 'Email et mot de passe requis');
}

$stmt = $pdo->prepare("SELECT * FROM citoyens WHERE email = ? AND statut = 'actif'");
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user) {
    ob_clean();
    sendResponse(false, 'Email ou mot de passe incorrect');
}

if ($password !== $user['password']) {
    ob_clean();
    sendResponse(false, 'Email ou mot de passe incorrect');
}

unset($user['password']);

ob_clean();
sendResponse(true, 'Connexion réussie', $user);
?>