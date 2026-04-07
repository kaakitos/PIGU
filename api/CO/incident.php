<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../db_connect.php';

$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {
        $stmt = $pdo->query("SELECT * FROM incident ORDER BY date_heure DESC");
        $incidents = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $result = [];
        foreach ($incidents as $row) {
            $result[] = [
                'id'    => 'INC-' . str_pad($row['id'], 3, '0', STR_PAD_LEFT),
                'rawId' => $row['id'],
                'desc'  => $row['description'],
                'lat'   => isset($row['latitude']) ? (float)$row['latitude'] : null,
                'lng'   => isset($row['longitude']) ? (float)$row['longitude'] : null,
                'addr'  => $row['adresse_texte'],
                'prio'  => $row['priorite'],
                'statut'=> $row['statut'],
                'time'  => date('H:i', strtotime($row['date_heure'])),
                'date'  => $row['date_heure'],
            ];
        }

        echo json_encode($result);
        exit;
    }

    if ($method === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);

        $desc = trim($data['desc'] ?? '');
        $addr = trim($data['addr'] ?? '');
        $prio = trim($data['prio'] ?? 'NON_URGENT');
        $lat  = isset($data['lat']) ? (float)$data['lat'] : null;
        $lng  = isset($data['lng']) ? (float)$data['lng'] : null;

        if (!$desc || !$addr) {
            echo json_encode(['success' => false, 'error' => 'Description et localisation requises']);
            exit;
        }

        $stmt = $pdo->prepare("
            INSERT INTO incident (description, adresse_texte, priorite, latitude, longitude, statut, date_heure)
            VALUES (:desc, :addr, :prio, :lat, :lng, 'NOUVEAU', NOW())
        ");

        $stmt->execute([
            ':desc' => $desc,
            ':addr' => $addr,
            ':prio' => $prio,
            ':lat'  => $lat,
            ':lng'  => $lng
        ]);

        $incidentId = $pdo->lastInsertId();
        echo json_encode(['success' => true, 'id' => $incidentId]);
        exit;
    }

    echo json_encode(['success' => false, 'error' => 'Méthode non supportée']);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}