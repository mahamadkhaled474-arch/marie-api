<?php
ob_start();
require_once 'config.php';
ob_clean();

error_reporting(E_ALL);
ini_set('display_errors', 0);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_clean();
    sendResponse(false, 'Méthode non autorisée');
}

if (empty($_POST) && empty($_FILES)) {
    ob_clean();
    sendResponse(false, 'Aucune donnée reçue');
}

$citoyen_id   = $_POST['citoyen_id']   ?? '';
$categorie_id = $_POST['categorie_id'] ?? '';
$titre        = $_POST['titre']        ?? '';
$description  = $_POST['description']  ?? '';
$latitude     = $_POST['latitude']     ?? 0;
$longitude    = $_POST['longitude']    ?? 0;
$adresse      = $_POST['adresse']      ?? '';

if (stripos($titre, 'suggestion') !== false) {
    $type = 'suggestion';
} elseif (stripos($titre, 'felicitation') !== false || stripos($titre, 'félicitation') !== false) {
    $type = 'felicitation';
} else {
    $type = 'signalement';
}

if (empty($citoyen_id) || empty($categorie_id) || empty($titre) || empty($description)) {
    ob_clean();
    sendResponse(false, 'Champs obligatoires manquants');
}

// ── Traitement des photos ─────────────────────────────────────────────────────
$uploadDir = 'C:/wamp64/www/mairie-signalements/photo-citoyen/';
$uploadUrl = 'photo-citoyen/';

if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$photos     = [null, null, null];
$filesArray = $_FILES['photos'] ?? null;

if ($filesArray && isset($filesArray['tmp_name'])) {
    $count = is_array($filesArray['tmp_name']) ? count($filesArray['tmp_name']) : 1;

    for ($i = 0; $i < min($count, 3); $i++) {
        $tmpName  = is_array($filesArray['tmp_name']) ? $filesArray['tmp_name'][$i] : $filesArray['tmp_name'];
        $origName = is_array($filesArray['name'])     ? $filesArray['name'][$i]     : $filesArray['name'];
        $error    = is_array($filesArray['error'])    ? $filesArray['error'][$i]    : $filesArray['error'];

        // Vérifier erreur upload
        if ($error !== UPLOAD_ERR_OK) continue;

        // Vérifier que le fichier existe
        if (empty($tmpName) || !file_exists($tmpName)) continue;

        // Extension
        $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
        if (empty($ext)) $ext = 'jpg';
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        if (!in_array($ext, $allowed)) $ext = 'jpg';

        // Nom unique
        $newName  = 'signal_' . $citoyen_id . '_' . time() . '_' . $i . '_' . uniqid() . '.' . $ext;
        $destPath = $uploadDir . $newName;

        // Essayer move_uploaded_file puis copy
        $moved = false;
        if (is_uploaded_file($tmpName)) {
            $moved = move_uploaded_file($tmpName, $destPath);
        }
        if (!$moved && file_exists($tmpName)) {
            $moved = copy($tmpName, $destPath);
        }

        if ($moved && file_exists($destPath)) {
            $photos[$i] = $uploadUrl . $newName;
        }
    }
}

// ── Insertion en base ─────────────────────────────────────────────────────────
try {
    $stmtService = $pdo->prepare("SELECT service_id FROM categorie_service WHERE categorie_id = ? LIMIT 1");
    $stmtService->execute([$categorie_id]);
    $serviceRow = $stmtService->fetch();
    $service_id = $serviceRow ? $serviceRow['service_id'] : null;

    $stmt = $pdo->prepare("
        INSERT INTO signalements 
        (citoyen_id, categorie_id, type, service_id, titre, description, latitude, longitude, adresse, photo1, photo2, photo3, statut, priorite) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'en_attente', 'moyenne')
    ");
    $stmt->execute([
        $citoyen_id, $categorie_id, $type, $service_id,
        $titre, $description, $latitude, $longitude, $adresse,
        $photos[0], $photos[1], $photos[2],
    ]);

    $signalementId = $pdo->lastInsertId();

    if ($service_id) {
        $pdo->prepare("
            INSERT INTO notifications (service_id, signalement_id, type, titre, message)
            VALUES (?, ?, 'nouveau_signalement', 'Nouveau signalement', ?)
        ")->execute([$service_id, $signalementId, "Nouveau signalement : $titre"]);

        $pdo->prepare("
            INSERT INTO signalement_services (signalement_id, service_id) VALUES (?, ?)
        ")->execute([$signalementId, $service_id]);
    }

    $pdo->prepare("
        INSERT INTO notifications_citoyens (citoyen_id, signalement_id, type, titre, message)
        VALUES (?, ?, 'info', 'Signalement reçu', 'Votre signalement a bien été enregistré.')
    ")->execute([$citoyen_id, $signalementId]);

    ob_clean();
    sendResponse(true, 'Signalement créé avec succès', [
        'id'     => $signalementId,
        'statut' => 'en_attente',
        'photos' => array_values(array_filter($photos)),
    ]);

} catch (Exception $e) {
    ob_clean();
    sendResponse(false, 'Erreur : ' . $e->getMessage());
}
?>