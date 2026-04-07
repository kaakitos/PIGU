<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../db_connect.php';

try {
    $sql = "
        SELECT h.id, h.nom, h.latitude, h.longitude,
               COUNT(p.id) AS places,
               GROUP_CONCAT(DISTINCT p.specialite) AS spec
        FROM hopital h
        LEFT JOIN place p ON h.id = p.hopital_id AND p.statut='DISPONIBLE'
        GROUP BY h.id
        ORDER BY h.nom
    ";
    $stmt = $pdo->query($sql);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $hopitaux = [];
    foreach ($rows as $row) {
        $hopitaux[] = [
            'id'     => 'HOP-' . str_pad($row['id'], 3, '0', STR_PAD_LEFT),
            'rawId'  => $row['id'],
            'nom'    => $row['nom'],
            'lat'    => (float)$row['latitude'],
            'lng'    => (float)$row['longitude'],
            'places' => (int)$row['places'],
            'spec'   => $row['spec'] ?? '',
        ];
    }

    echo json_encode($hopitaux);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}