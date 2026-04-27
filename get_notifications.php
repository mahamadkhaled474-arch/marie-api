<?php
require_once 'config.php';

$citoyen_id = $_GET['citoyen_id'] ?? 0;

if (!$citoyen_id) {
    sendResponse(false, 'ID manquant');
}

// Récupérer toutes les notifications du citoyen
$stmt = $pdo->prepare("
    SELECT 
        id,
        titre,
        message,
        type,
        lu,
        date_envoi,
        signalement_id
    FROM notifications_citoyens
    WHERE citoyen_id = ?
    ORDER BY date_envoi DESC
    LIMIT 50
");
$stmt->execute([$citoyen_id]);
$notifications = $stmt->fetchAll();

// Compter les non lues
$stmtCount = $pdo->prepare("
    SELECT COUNT(*) as total
    FROM notifications_citoyens
    WHERE citoyen_id = ? AND lu = 0
");
$stmtCount->execute([$citoyen_id]);
$count = $stmtCount->fetch();

sendResponse(true, 'OK', [
    'notifications' => $notifications,
    'non_lues'      => (int)$count['total'],
]);
?>