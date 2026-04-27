<?php
require_once 'config.php';

$data       = json_decode(file_get_contents('php://input'), true);
$citoyen_id = $data['citoyen_id'] ?? 0;
$notif_id   = $data['notif_id']   ?? null;

if (!$citoyen_id) {
    sendResponse(false, 'ID manquant');
}

if ($notif_id) {
    // Marquer une seule notification comme lue
    $stmt = $pdo->prepare("
        UPDATE notifications_citoyens 
        SET lu = 1 
        WHERE id = ? AND citoyen_id = ?
    ");
    $stmt->execute([$notif_id, $citoyen_id]);
} else {
    // Marquer toutes comme lues
    $stmt = $pdo->prepare("
        UPDATE notifications_citoyens 
        SET lu = 1 
        WHERE citoyen_id = ?
    ");
    $stmt->execute([$citoyen_id]);
}

sendResponse(true, 'Notifications marquées comme lues');
?>