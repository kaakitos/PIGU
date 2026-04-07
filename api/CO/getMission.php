<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../db_connect.php';

try {
    $sql = "
        SELECT m.id, m.incident_id, m.ambulance_id, m.hopital_dest_id, m.statut, m.date_debut,
               i.description AS inc_desc,
               a.immatriculation AS amb_immat,
               u.nom AS amb_driver,
               h.nom AS hop_nom
        FROM mission m
        JOIN incident i ON m.incident_id = i.id
        JOIN ambulance a ON m.ambulance_id = a.id
        JOIN utilisateur u ON a.utilisateur_id = u.id
        JOIN hopital h ON m.hopital_dest_id = h.id
        ORDER BY m.date_debut DESC
    ";
    $stmt = $pdo->query($sql);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $missions = [];
    foreach ($rows as $row) {
        $missions[] = [
            'id'       => 'MIS-' . str_pad($row['id'], 3, '0', STR_PAD_LEFT),
            'incId'    => 'INC-' . str_pad($row['incident_id'], 3, '0', STR_PAD_LEFT),
            'incDesc'  => $row['inc_desc'],
            'ambId'    => 'AMB-' . str_pad($row['ambulance_id'], 3, '0', STR_PAD_LEFT),
            'ambImmat' => $row['amb_immat'],
            'ambDriver'=> $row['amb_driver'],
            'hopId'    => 'HOP-' . str_pad($row['hopital_dest_id'], 3, '0', STR_PAD_LEFT),
            'hopNom'   => $row['hop_nom'],
            'statut'   => $row['statut'],
            'time'     => date('H:i', strtotime($row['date_debut'])),
        ];
    }

    echo json_encode($missions);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}