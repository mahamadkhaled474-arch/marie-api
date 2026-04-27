<?php
// api/export.php
require_once '../includes/config.php';

if (!isset($pdo)) {
    die('Erreur de connexion à la base de données');
}

redirigerSiNonConnecte();

$format = $_GET['format'] ?? 'csv';
$source = $_GET['source'] ?? '';  // 'service' si vient de signalements.php

// ── Paramètres de filtre transmis depuis signalements.php ─────
$type       = $_GET['type']       ?? '';
$statut     = $_GET['statut']     ?? '';
$priorite   = $_GET['priorite']   ?? '';
$categorie  = $_GET['categorie']  ?? '';
$date_debut = $_GET['date_debut'] ?? '';
$date_fin   = $_GET['date_fin']   ?? '';
$search     = $_GET['search']     ?? '';

// ── Labels lisibles ───────────────────────────────────────────
$type_labels = [
    'signalement' => 'Signalement',
    'suggestion' => 'Suggestion',
    'felicitation' => 'Félicitation'
];
$statut_labels = [
    'en_attente' => 'En attente',
    'en_traitement' => 'En traitement',
    'resolu' => 'Résolu',
    'rejete' => 'Rejeté'
];
$prio_labels = [
    'urgente' => 'Urgente',
    'haute' => 'Haute',
    'moyenne' => 'Moyenne',
    'basse' => 'Basse'
];

// ── Nom du fichier export ─────────────────────────────────────
$type_slug = $type ?: 'tous';
$filename  = 'export_' . $type_slug . '_' . date('Y-m-d_H-i-s');

try {
    // ── Construction de la requête avec filtres ───────────────
    $where = [];
    $params = [];

    if (estAdministrateur()) {
        $base_sql = "SELECT s.*, c.nom as categorie_nom,
                            sv.nom_complet as service_nom, 
                            sv.code as service_code,
                            CONCAT(cit.prenom, ' ', cit.nom) as citoyen_nom,
                            cit.telephone as citoyen_telephone
                     FROM signalements s
                     LEFT JOIN categories_signalements c ON s.categorie_id = c.id
                     LEFT JOIN services sv ON s.service_id = sv.id
                     LEFT JOIN citoyens cit ON s.citoyen_id = cit.id";
    } else {
        $service_id = getServiceId();
        if (!$service_id) {
            die('ID service non trouvé');
        }

        $base_sql = "SELECT s.*, c.nom as categorie_nom,
                            CONCAT(cit.prenom, ' ', cit.nom) as citoyen_nom,
                            cit.telephone as citoyen_telephone
                     FROM signalements s
                     INNER JOIN signalement_services ss ON s.id = ss.signalement_id
                     LEFT JOIN categories_signalements c ON s.categorie_id = c.id
                     LEFT JOIN citoyens cit ON s.citoyen_id = cit.id
                     WHERE ss.service_id = :service_id";
        $params[':service_id'] = $service_id;
    }

    // Filtre type (signalement / suggestion / felicitation)
    if (!empty($type)) {
        $where[] = "s.type = :type";
        $params[':type'] = $type;
    }

    // Filtre statut
    if (!empty($statut)) {
        $where[] = "s.statut = :statut";
        $params[':statut'] = $statut;
    }

    // Filtre priorité
    if (!empty($priorite)) {
        $where[] = "s.priorite = :priorite";
        $params[':priorite'] = $priorite;
    }

    // Filtre catégorie
    if (!empty($categorie)) {
        $where[] = "s.categorie_id = :categorie";
        $params[':categorie'] = $categorie;
    }

    // Filtre date début
    if (!empty($date_debut)) {
        $where[] = "DATE(s.date_signalement) >= :date_debut";
        $params[':date_debut'] = $date_debut;
    }

    // Filtre date fin
    if (!empty($date_fin)) {
        $where[] = "DATE(s.date_signalement) <= :date_fin";
        $params[':date_fin'] = $date_fin;
    }

    // Filtre recherche texte
    if (!empty($search)) {
        $where[] = "(s.titre LIKE :search OR s.description LIKE :search OR s.adresse LIKE :search)";
        $params[':search'] = '%' . $search . '%';
    }

    // Assembler le WHERE
    if (!empty($where)) {
        if (estAdministrateur()) {
            $base_sql .= " WHERE " . implode(" AND ", $where);
        } else {
            $base_sql .= " AND " . implode(" AND ", $where);
        }
    }

    $base_sql .= " ORDER BY s.date_signalement DESC";

    $stmt = $pdo->prepare($base_sql);
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v);
    }
    $stmt->execute();
    $signalements = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ── Colonnes selon le type ────────────────────────────────
    $is_admin = estAdministrateur();

    // ── En-têtes colonnes ─────────────────────────────────────
    $headers = ['ID', 'Référence', 'Type', 'Titre', 'Description', 'Catégorie', 'Citoyen', 'Téléphone citoyen'];
    
    if ($is_admin) {
        $headers[] = 'Service';
    }
    
    array_push($headers,
        'Statut', 'Priorité',
        'Date signalement', 'Date prise en charge', 'Date résolution',
        'Adresse', 'Latitude', 'Longitude'
    );
    
    // Ajouter les colonnes spécifiques aux signalements
    if ($type === 'signalement' || empty($type)) {
        $headers[] = 'Motif rejet';
        $headers[] = 'Note citoyen';
    }

    // Fonction pour construire une ligne de données
    $buildRow = function($s) use ($is_admin, $type, $type_labels, $statut_labels, $prio_labels) {
        $row = [
            $s['id'] ?? '',
            'REF' . str_pad($s['id'] ?? '', 5, '0', STR_PAD_LEFT),
            $type_labels[$s['type'] ?? ''] ?? ($s['type'] ?? ''),
            $s['titre'] ?? '',
            $s['description'] ?? '',
            $s['categorie_nom'] ?? '',
            $s['citoyen_nom'] ?? '',
            $s['citoyen_telephone'] ?? '',
        ];
        
        if ($is_admin) {
            $row[] = $s['service_nom'] ?? 'Non assigné';
        }
        
        $row[] = $statut_labels[$s['statut'] ?? ''] ?? ($s['statut'] ?? '');
        $row[] = $prio_labels[$s['priorite'] ?? ''] ?? ($s['priorite'] ?? '');
        $row[] = $s['date_signalement'] ?? '';
        $row[] = $s['date_prise_en_charge'] ?? '';
        $row[] = $s['date_resolution'] ?? '';
        $row[] = $s['adresse'] ?? '';
        $row[] = $s['latitude'] ?? '';
        $row[] = $s['longitude'] ?? '';
        
        if ($type === 'signalement' || empty($type)) {
            $row[] = $s['motif_rejet'] ?? '';
            $row[] = $s['note_citoyen'] ?? '';
        }
        
        return $row;
    };

    // ════════════════════════════════════════════════
    // FORMAT CSV
    // ════════════════════════════════════════════════
    if ($format === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename . '.csv');
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF)); // BOM UTF-8

        fputcsv($output, $headers, ';');

        if (!empty($signalements)) {
            foreach ($signalements as $s) {
                fputcsv($output, $buildRow($s), ';');
            }
        }

        fclose($output);
        exit;

    // ════════════════════════════════════════════════
    // FORMAT EXCEL (HTML table → .xls)
    // ════════════════════════════════════════════════
    } elseif ($format === 'excel') {
        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename . '.xls');
        header('Pragma: no-cache');
        header('Expires: 0');

        $type_colors = [
            'signalement'  => '#bbdefb',
            'suggestion'   => '#ede7f6',
            'felicitation' => '#fff9c4',
        ];
        $header_color = $type_colors[$type] ?? '#e3f2fd';

        $statut_colors = [
            'En attente'    => '#fff3cd',
            'En traitement' => '#d1ecf1',
            'Résolu'        => '#d4edda',
            'Rejeté'        => '#e2e3e5',
        ];

        $type_label_export = $type_labels[$type] ?? 'Tous';
        $col_count = count($headers);

        $info_parts = ['Date : ' . date('d/m/Y à H:i')];
        if (!empty($type))       $info_parts[] = 'Type : ' . $type_label_export;
        if (!empty($statut))     $info_parts[] = 'Statut : ' . ($statut_labels[$statut] ?? $statut);
        if (!empty($priorite))   $info_parts[] = 'Priorité : ' . ($prio_labels[$priorite] ?? $priorite);
        if (!empty($date_debut)) $info_parts[] = 'Du : ' . $date_debut;
        if (!empty($date_fin))   $info_parts[] = 'Au : ' . $date_fin;
        if (!empty($search))     $info_parts[] = 'Recherche : ' . $search;
        $info_parts[] = 'Total : ' . count($signalements) . ' ligne(s)';

        ob_start();
        ?>
<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <style>
        body { font-family: Arial, sans-serif; font-size: 11pt; }
        table { border-collapse: collapse; width: 100%; }
        th { background-color: <?php echo $header_color; ?>; font-weight: bold; border: 1px solid #aaa; padding: 6px 10px; text-align: left; }
        td { border: 1px solid #ddd; padding: 5px 9px; vertical-align: top; }
        .title-row td { background-color: #1a237e; color: white; font-size: 13pt; font-weight: bold; padding: 10px; }
        .info-row td { background-color: #f8f9fa; font-size: 9pt; color: #555; padding: 4px 9px; }
    </style>
</head>
<body>
    <table>
        <tr class="title-row">
            <td colspan="<?php echo $col_count; ?>">
                Export — <?php echo htmlspecialchars($type_label_export); ?>s &nbsp;|&nbsp;
                <?php echo $is_admin ? 'Administration' : htmlspecialchars($_SESSION['service_nom'] ?? ''); ?>
            </td>
        </tr>
        <tr class="info-row">
            <td colspan="<?php echo $col_count; ?>">
                <?php echo implode(' &middot; ', array_map('htmlspecialchars', $info_parts)); ?>
            </td>
        </tr>
        <tr>
            <?php foreach ($headers as $h): ?>
                <th><?php echo htmlspecialchars($h); ?></th>
            <?php endforeach; ?>
        </tr>
        <?php if (!empty($signalements)): ?>
            <?php foreach ($signalements as $s):
                $row = $buildRow($s);
                $statut_fr = $statut_labels[$s['statut'] ?? ''] ?? '';
                $row_bg = $statut_colors[$statut_fr] ?? '#ffffff';
            ?>
            <tr style="background-color:<?php echo $row_bg; ?>;">
                <?php foreach ($row as $cell): ?>
                    <td><?php echo htmlspecialchars((string)($cell ?? '')); ?></td>
                <?php endforeach; ?>
            </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr>
                <td colspan="<?php echo $col_count; ?>" style="text-align:center;color:#888;padding:20px;">
                    Aucune donnée pour ce filtre.
                </td>
            </tr>
        <?php endif; ?>
    </table>
</body>
</html>
        <?php
        echo ob_get_clean();
        exit;
    } else {
        die('Format d\'export non supporté');
    }

} catch (PDOException $e) {
    header('Content-Type: text/html; charset=utf-8');
    echo '<h3>Erreur SQL</h3>';
    echo '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
    echo '<p><a href="javascript:history.back()">Retour</a></p>';
    exit;
} catch (Exception $e) {
    header('Content-Type: text/html; charset=utf-8');
    echo '<h3>Erreur</h3>';
    echo '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
    echo '<p><a href="javascript:history.back()">Retour</a></p>';
    exit;
}
?>