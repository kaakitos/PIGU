<?php

 
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

//CONNEXION BASE DE DONNEES
define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: 'root');
define('DB_NAME', getenv('DB_NAME') ?: 'pigu');
define('DB_PORT', getenv('DB_PORT') ?: '3307');

function getDB() {
    try {
        $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $options = [];
        if (getenv('DB_HOST')) {
            $options[PDO::MYSQL_ATTR_SSL_CA] = true;
            $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
        }
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return $pdo;
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Connexion BD impossible: ' . $e->getMessage()]);
        exit;
    }
}

//  ROUTER
$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

// Lire le body JSON pour les POST
$body = [];
if ($method === 'POST') {
    $raw = file_get_contents('php://input');
    $body = json_decode($raw, true) ?? [];
}
 
switch ($action) {
 
    // ----------------------------------------------------------
    // GET /api.php?action=all
    // Charge toutes les données pour le dashboard en une seule requête
    // ----------------------------------------------------------
    case 'all':
        $db = getDB();
 
        // Incidents actifs (pas encore résolus)
        $incidents = $db->query("
            SELECT id, description, adresse_texte, latitude, longitude,
                   priorite, statut, date_heure
            FROM incident
            WHERE statut != 'RESOLU'
            ORDER BY date_heure DESC
        ")->fetchAll();
 
        // Ambulances avec le nom de l'ambulancier
        $ambulances = $db->query("
            SELECT a.id, a.immatriculation, a.latitude, a.longitude, a.statut,
                   u.nom, u.prenom
            FROM ambulance a
            JOIN utilisateur u ON u.id = a.utilisateur_id
            ORDER BY a.statut ASC, a.id ASC
        ")->fetchAll();
 
        // Hôpitaux avec nombre de places disponibles
        $hopitaux = $db->query("
            SELECT h.id, h.nom, h.adresse, h.latitude, h.longitude,
                   COUNT(CASE WHEN p.statut = 'DISPONIBLE' THEN 1 END) AS places_dispo,
                   GROUP_CONCAT(DISTINCT p.specialite ORDER BY p.specialite SEPARATOR ', ') AS spec
            FROM hopital h
            LEFT JOIN place p ON p.hopital_id = h.id
            GROUP BY h.id, h.nom, h.adresse, h.latitude, h.longitude
            ORDER BY places_dispo DESC
        ")->fetchAll();
 
        // Missions en cours ou en attente
        $missions = $db->query("
            SELECT m.id, m.incident_id, m.ambulance_id, m.hopital_dest_id,
                   m.statut, m.date_debut,
                   a.immatriculation,
                   h.nom AS hop_nom,
                   u.nom, u.prenom
            FROM mission m
            JOIN ambulance a ON a.id = m.ambulance_id
            JOIN hopital h ON h.id = m.hopital_dest_id
            JOIN utilisateur u ON u.id = a.utilisateur_id
            WHERE m.statut IN ('EN_ATTENTE', 'EN_COURS')
            ORDER BY m.date_debut DESC
        ")->fetchAll();
 
        echo json_encode([
            'incidents'  => $incidents,
            'ambulances' => $ambulances,
            'hopitaux'   => $hopitaux,
            'missions'   => $missions,
        ]);
        break;
 
    // ----------------------------------------------------------
    // POST /api.php?action=ajouterIncident
    // Enregistre un nouvel incident (appel entrant)
    // ----------------------------------------------------------
    case 'ajouterIncident':
        if ($method !== 'POST') { echo json_encode(['error' => 'Méthode invalide']); break; }
 
        $description   = trim($body['description']   ?? '');
        $adresse_texte = trim($body['adresse_texte'] ?? '');
        $priorite      = $body['priorite']  ?? 'URGENT';
        $latitude      = $body['latitude']  ?? null;
        $longitude     = $body['longitude'] ?? null;
 
        if (!$description) {
            echo json_encode(['error' => 'Description obligatoire']);
            break;
        }
 
        // Valider la priorité
        $priorites_valides = ['NON_URGENT', 'URGENT', 'TRES_URGENT'];
        if (!in_array($priorite, $priorites_valides)) $priorite = 'URGENT';
 
        $db = getDB();
        $stmt = $db->prepare("
            INSERT INTO incident (description, adresse_texte, latitude, longitude, priorite, statut)
            VALUES (:desc, :addr, :lat, :lng, :prio, 'NOUVEAU')
        ");
        $stmt->execute([
            ':desc' => $description,
            ':addr' => $adresse_texte,
            ':lat'  => $latitude,
            ':lng'  => $longitude,
            ':prio' => $priorite,
        ]);
 
        echo json_encode(['success' => true, 'id' => $db->lastInsertId()]);
        break;
 
    // ----------------------------------------------------------
    // POST /api.php?action=creerMission
    // Crée une mission et met à jour les statuts
    // ----------------------------------------------------------
    case 'creerMission':
        if ($method !== 'POST') { echo json_encode(['error' => 'Méthode invalide']); break; }
 
        $incident_id    = intval($body['incident_id']    ?? 0);
        $ambulance_id   = intval($body['ambulance_id']   ?? 0);
        $hopital_dest_id = intval($body['hopital_dest_id'] ?? 0);
        $admin_id       = intval($body['admin_id']       ?? 1); // à remplacer par session PHP
        $notes          = trim($body['notes'] ?? '');
 
        if (!$incident_id || !$ambulance_id || !$hopital_dest_id) {
            echo json_encode(['error' => 'Champs incident, ambulance et hôpital obligatoires']);
            break;
        }
 
        $db = getDB();
 
        // Vérifier que l'ambulance est disponible
        $amb = $db->prepare("SELECT statut FROM ambulance WHERE id = ?");
        $amb->execute([$ambulance_id]);
        $ambData = $amb->fetch();
        if (!$ambData || $ambData['statut'] !== 'DISPONIBLE') {
            echo json_encode(['error' => 'Ambulance non disponible']);
            break;
        }
 
        // Transaction : créer mission + changer statuts
        $db->beginTransaction();
        try {
            // Insérer la mission
            $stmt = $db->prepare("
                INSERT INTO mission (incident_id, ambulance_id, hopital_dest_id, admin_id, statut)
                VALUES (:inc, :amb, :hop, :admin, 'EN_COURS')
            ");
            $stmt->execute([
                ':inc'   => $incident_id,
                ':amb'   => $ambulance_id,
                ':hop'   => $hopital_dest_id,
                ':admin' => $admin_id,
            ]);
            $missionId = $db->lastInsertId();
 
            // Mettre ambulance EN_MISSION
            $db->prepare("UPDATE ambulance SET statut = 'EN_MISSION' WHERE id = ?")
               ->execute([$ambulance_id]);
 
            // Mettre incident EN_TRAITEMENT
            $db->prepare("UPDATE incident SET statut = 'EN_TRAITEMENT' WHERE id = ?")
               ->execute([$incident_id]);
 
            $db->commit();
            echo json_encode(['success' => true, 'id' => $missionId]);
 
        } catch (Exception $e) {
            $db->rollBack();
            echo json_encode(['error' => 'Erreur création mission: ' . $e->getMessage()]);
        }
        break;
 
    // ----------------------------------------------------------
    // GET /api.php?action=incidents
    // Incidents uniquement (pour rafraîchir la liste)
    // ----------------------------------------------------------
    case 'incidents':
        $db = getDB();
        $rows = $db->query("
            SELECT id, description, adresse_texte, latitude, longitude,
                   priorite, statut, date_heure
            FROM incident WHERE statut != 'RESOLU'
            ORDER BY date_heure DESC
        ")->fetchAll();
        echo json_encode($rows);
        break;
 
    // ----------------------------------------------------------
    // Cas par défaut
    // ----------------------------------------------------------
    default:
        http_response_code(404);
        echo json_encode(['error' => 'Action inconnue: ' . $action]);
        break;
}
?>
 