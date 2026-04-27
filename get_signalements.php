<?php
require_once 'config.php';

// Récupérer les paramètres de requête
$citoyen_id = $_GET['citoyen_id'] ?? null;
$statut = $_GET['statut'] ?? null;
$limit = $_GET['limit'] ?? 200;

try {
    // Construction de la requête
    $sql = "
        SELECT 
            s.*,
            c.nom as categorie_nom,
            c.couleur as categorie_couleur,
            c.icon as categorie_icon,
            cit.nom as citoyen_nom,
            cit.prenom as citoyen_prenom,
            (SELECT COUNT(*) FROM votes WHERE signalement_id = s.id) as nb_votes
        FROM signalements s
        INNER JOIN categories_signalements c ON s.categorie_id = c.id
        INNER JOIN citoyens cit ON s.citoyen_id = cit.id
        WHERE 1=1
    ";
    
    $params = [];
    
    // Filtrer par citoyen
    if ($citoyen_id) {
        $sql .= " AND s.citoyen_id = ?";
        $params[] = $citoyen_id;
    }
    
    // Filtrer par statut
    if ($statut) {
        $sql .= " AND s.statut = ?";
        $params[] = $statut;
    }
    
    $sql .= " ORDER BY s.date_signalement DESC LIMIT ?";
    $params[] = (int)$limit;
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    $signalements = $stmt->fetchAll();
    
    sendResponse(true, 'Signalements récupérés', $signalements);
    
} catch (PDOException $e) {
    sendResponse(false, 'Erreur lors de la récupération des signalements: ' . $e->getMessage());
}
?>