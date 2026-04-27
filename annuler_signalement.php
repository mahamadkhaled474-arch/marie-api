<?php
require_once 'config.php';

$data           = json_decode(file_get_contents('php://input'), true);
$signalement_id = $data['signalement_id'] ?? 0;
$citoyen_id     = $data['citoyen_id']     ?? 0;

if (!$signalement_id || !$citoyen_id) {
    sendResponse(false, 'Données manquantes');
}

// Vérifier que le signalement appartient au citoyen et est en attente
$stmt = $pdo->prepare("
    SELECT id FROM signalements 
    WHERE id = ? AND citoyen_id = ? AND statut = 'en_attente'
");
$stmt->execute([$signalement_id, $citoyen_id]);

if (!$stmt->fetch()) {
    sendResponse(false, 'Signalement introuvable ou déjà traité');
}

// Supprimer le signalement
$stmt = $pdo->prepare("DELETE FROM signalements WHERE id = ?");
$stmt->execute([$signalement_id]);

// Supprimer aussi les notifications liées
$stmt = $pdo->prepare("DELETE FROM notifications_citoyens WHERE signalement_id = ?");
$stmt->execute([$signalement_id]);

$stmt = $pdo->prepare("DELETE FROM notifications WHERE signalement_id = ?");
$stmt->execute([$signalement_id]);

sendResponse(true, 'Signalement annulé avec succès');
?>