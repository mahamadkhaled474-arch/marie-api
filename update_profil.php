<?php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Méthode non autorisée');
}

$data = json_decode(file_get_contents('php://input'), true);

$citoyen_id = $data['citoyen_id'] ?? '';
$nom = $data['nom'] ?? '';
$prenom = $data['prenom'] ?? '';
$telephone = $data['telephone'] ?? '';
$adresse = $data['adresse'] ?? '';

if (empty($citoyen_id) || empty($nom) || empty($prenom)) {
    sendResponse(false, 'Nom et prénom sont obligatoires');
}

try {
    $stmt = $pdo->prepare("UPDATE citoyens SET nom = ?, prenom = ?, telephone = ?, adresse = ? WHERE id = ?");
    $stmt->execute([$nom, $prenom, $telephone, $adresse, $citoyen_id]);
    sendResponse(true, 'Profil mis à jour avec succès');
} catch (PDOException $e) {
    sendResponse(false, 'Erreur: ' . $e->getMessage());
}
?>