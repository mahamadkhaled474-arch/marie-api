<?php
require_once 'config.php';

$id = $_GET['id'] ?? 0;

if (!$id) {
    sendResponse(false, 'ID manquant');
}

// Récupérer le signalement complet avec service et catégorie
$stmt = $pdo->prepare("
    SELECT 
        s.*,
        c.nom AS categorie_nom,
        c.couleur AS categorie_couleur,
        srv.nom_complet AS service_nom,
        srv.code AS service_code,
        srv.couleur AS service_couleur,
        srv.telephone AS service_telephone
    FROM signalements s
    LEFT JOIN categories_signalements c ON s.categorie_id = c.id
    LEFT JOIN services srv ON s.service_id = srv.id
    WHERE s.id = ?
");
$stmt->execute([$id]);
$signalement = $stmt->fetch();

if (!$signalement) {
    sendResponse(false, 'Signalement introuvable');
}

// Récupérer l'historique des notifications
$stmt2 = $pdo->prepare("
    SELECT titre, message, date_envoi, type
    FROM notifications_citoyens
    WHERE signalement_id = ?
    ORDER BY date_envoi ASC
");
$stmt2->execute([$id]);
$historique = $stmt2->fetchAll();

sendResponse(true, 'OK', [
    'signalement' => $signalement,
    'historique'  => $historique,
]);
?>