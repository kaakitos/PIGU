<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../db_connect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Méthode non supportée']);
    exit;
}

try {
    $data        = json_decode(file_get_contents('php://input'), true);
    $incidentId  = (int)($data['incident']  ?? 0);
    $ambulanceId = (int)($data['ambulance'] ?? 0);
    $hopitalId   = (int)($data['hopital']   ?? 0);

    // 🔍 Log pour déboguer — à retirer après
    error_log("Mission reçue: inc=$incidentId amb=$ambulanceId hop=$hopitalId");

    if (!$incidentId || !$ambulanceId || !$hopitalId) {
        echo json_encode(['success' => false, 'error' => 'Champs obligatoires manquants']);
        exit;
    }

    // Vérifier ambulance DISPONIBLE
    $stmt = $pdo->prepare("SELECT statut FROM ambulance WHERE id = ?");
    $stmt->execute([$ambulanceId]);
    $ambulance = $stmt->fetch();
    if (!$ambulance || $ambulance['statut'] !== 'DISPONIBLE') {
        echo json_encode(['success' => false, 'error' => 'Ambulance non disponible — statut: ' . ($ambulance['statut'] ?? 'introuvable')]);
        exit;
    }

    // Vérifier incident NOUVEAU
    $stmt = $pdo->prepare("SELECT statut FROM incident WHERE id = ?");
    $stmt->execute([$incidentId]);
    $incident = $stmt->fetch();
    if (!$incident || $incident['statut'] !== 'NOUVEAU') {
        echo json_encode(['success' => false, 'error' => 'Incident déjà en traitement — statut: ' . ($incident['statut'] ?? 'introuvable')]);
        exit;
    }

  
    // Sinon on prend le premier admin en base :
    $stmtAdmin = $pdo->query("SELECT id FROM utilisateur WHERE role = 'ADMIN_SAMU' LIMIT 1");
    $admin = $stmtAdmin->fetch();
    $adminId = $admin ? (int)$admin['id'] : 1; // fallback à 1

    // Insérer la mission avec admin_id
    $stmt = $pdo->prepare("
        INSERT INTO mission (incident_id, ambulance_id, hopital_dest_id, admin_id, statut, date_debut,date_fin, distance_km )
        VALUES (?, ?, ?, ?, 'EN_COURS', NOW(),NOW(), 89.09)
    ");
    $stmt->execute([$incidentId, $ambulanceId, $hopitalId, $adminId]);
    $missionId = $pdo->lastInsertId();

    // Mettre à jour les statuts
    $pdo->prepare("UPDATE ambulance SET statut = 'EN_MISSION' WHERE id = ?")
        ->execute([$ambulanceId]);
    $pdo->prepare("UPDATE incident SET statut = 'EN_TRAITEMENT' WHERE id = ?")
        ->execute([$incidentId]);

    echo json_encode(['success' => true, 'id' => $missionId]);

} catch (PDOException $e) {
    // ✅ Retourne l'erreur SQL réelle pour déboguer
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}