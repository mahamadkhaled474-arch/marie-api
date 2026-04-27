<?php
require_once 'config.php';

try {
    $stmt = $pdo->query("
        SELECT id, nom, description, icon, couleur 
        FROM categories_signalements 
        WHERE actif = 1 
        ORDER BY nom
    ");
    
    $categories = $stmt->fetchAll();
    
    sendResponse(true, 'Catégories récupérées', $categories);
} catch (PDOException $e) {
    sendResponse(false, 'Erreur lors de la récupération des catégories');
}
?>