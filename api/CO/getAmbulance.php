<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../db_connect.php'; // $pdo

try {
    $sql = "SELECT a.id, a.immatriculation, a.statut, a.latitude, a.longitude, u.nom AS chauffeur
            FROM ambulance a
            JOIN utilisateur u ON a.utilisateur_id = u.id
            ORDER BY a.id";
    
    $stmt = $pdo->query($sql);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $ambulances = [];
    foreach ($rows as $row) {
        $ambulances[] = [
            'id' => 'AMB-' . str_pad($row['id'], 3, '0', STR_PAD_LEFT),
            'rawId' => $row['id'],
            'immatriculation' => $row['immatriculation'],
            'driver' => $row['chauffeur'],
            'statut' => $row['statut'],
            'lat' => (float)$row['latitude'],
            'lng' => (float)$row['longitude'],
        ];
    }

    echo json_encode($ambulances);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}