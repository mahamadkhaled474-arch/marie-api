<?php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Méthode non autorisée');
}

// Vérifier si un fichier a été uploadé
if (!isset($_FILES['image'])) {
    sendResponse(false, 'Aucune image fournie');
}

$file = $_FILES['image'];

// Vérifier les erreurs
if ($file['error'] !== UPLOAD_ERR_OK) {
    sendResponse(false, 'Erreur lors de l\'upload');
}

// Vérifier le type de fichier
$allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!in_array($mimeType, $allowedTypes)) {
    sendResponse(false, 'Type de fichier non autorisé. Utilisez JPG, PNG ou WEBP');
}

// Vérifier la taille (max 5MB)
if ($file['size'] > 5 * 1024 * 1024) {
    sendResponse(false, 'Fichier trop volumineux (max 5MB)');
}

// Créer le dossier uploads s'il n'existe pas
$uploadDir = '../uploads/signalements/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

// Générer un nom unique
$extension = pathinfo($file['name'], PATHINFO_EXTENSION);
$fileName = uniqid('img_', true) . '.' . $extension;
$destination = $uploadDir . $fileName;

// Déplacer le fichier
if (move_uploaded_file($file['tmp_name'], $destination)) {
    sendResponse(true, 'Image uploadée avec succès', [
        'filename' => $fileName,
        'url' => 'uploads/signalements/' . $fileName
    ]);
} else {
    sendResponse(false, 'Erreur lors de l\'enregistrement du fichier');
}
?>