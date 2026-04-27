<?php
require_once 'config.php';

$data   = json_decode(file_get_contents('php://input'), true);
$action = $data['action'] ?? '';

// ── Noter un signalement résolu ──────────────────────────
if ($action === 'noter') {
    $signalement_id = $data['signalement_id'] ?? 0;
    $note           = $data['note']           ?? 0;
    $citoyen_id     = $data['citoyen_id']     ?? 0;

    if (!$signalement_id || !$note || !$citoyen_id) {
        sendResponse(false, 'Données manquantes');
    }

    // Vérifier que le signalement appartient au citoyen et est résolu
    $stmt = $pdo->prepare("SELECT id FROM signalements WHERE id = ? AND citoyen_id = ? AND statut = 'resolu'");
    $stmt->execute([$signalement_id, $citoyen_id]);
    if (!$stmt->fetch()) {
        sendResponse(false, 'Signalement introuvable ou non résolu');
    }

    $stmt = $pdo->prepare("UPDATE signalements SET note_citoyen = ? WHERE id = ?");
    $stmt->execute([$note, $signalement_id]);
    sendResponse(true, 'Note enregistrée');
}

// ── Récupérer le motif de rejet ──────────────────────────
if ($action === 'motif_rejet') {
    $signalement_id = $data['signalement_id'] ?? 0;

    $stmt = $pdo->prepare("
        SELECT message FROM notifications_citoyens 
        WHERE signalement_id = ? AND type = 'changement_statut' AND message LIKE '%rejeté%'
        ORDER BY date_envoi DESC LIMIT 1
    ");
    $stmt->execute([$signalement_id]);
    $row = $stmt->fetch();

    if ($row) {
        // Extraire le motif après "Motif :"
        $message = $row['message'];
        $motif   = '';
        if (strpos($message, 'Motif :') !== false) {
            $motif = trim(substr($message, strpos($message, 'Motif :') + 7));
        } else {
            $motif = $message;
        }
        sendResponse(true, 'Motif trouvé', ['motif' => $motif]);
    } else {
        // Chercher dans motif_rejet directement
        $stmt = $pdo->prepare("SELECT motif_rejet FROM signalements WHERE id = ?");
        $stmt->execute([$signalement_id]);
        $row2 = $stmt->fetch();
        sendResponse(true, 'Motif trouvé', ['motif' => $row2['motif_rejet'] ?? 'Aucun motif fourni']);
    }
}

sendResponse(false, 'Action invalide');
?>