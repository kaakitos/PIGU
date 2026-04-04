<?php
require_once 'db_connect.php';

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch($method) {
        case 'GET':
            $hopital_id = isset($_GET['hopital_id']) ? intval($_GET['hopital_id']) : 0;
            
            if ($hopital_id <= 0) {
                echo json_encode([]);
                exit;
            }
            
            $stmt = $pdo->prepare("
                SELECT id, numero, type, specialite, statut, updated_at 
                FROM place 
                WHERE hopital_id = ? 
                ORDER BY numero
            ");
            $stmt->execute([$hopital_id]);
            $places = $stmt->fetchAll();
            
            foreach ($places as &$p) {
                $p['updated_at'] = date('Y-m-d H:i', strtotime($p['updated_at']));
            }
            
            echo json_encode($places);
            break;
            
        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!$data || !isset($data['hopital_id']) || !isset($data['numero'])) {
                echo json_encode(['success' => false, 'message' => 'Données invalides']);
                exit;
            }
            
            $stmt = $pdo->prepare("
                INSERT INTO place (hopital_id, numero, type, specialite, statut) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $data['hopital_id'],
                $data['numero'],
                $data['type'],
                $data['specialite'] ?? null,
                $data['statut'] ?? 'DISPONIBLE'
            ]);
            
            echo json_encode([
                'success' => true, 
                'id' => $pdo->lastInsertId(),
                'message' => 'Place ajoutée avec succès'
            ]);
            break;
            
        case 'PUT':
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!$data || !isset($data['id'])) {
                echo json_encode(['success' => false, 'message' => 'ID requis']);
                exit;
            }
            
            $fields = [];
            $params = [];
            
            if (isset($data['numero'])) {
                $fields[] = "numero = ?";
                $params[] = $data['numero'];
            }
            if (isset($data['type'])) {
                $fields[] = "type = ?";
                $params[] = $data['type'];
            }
            if (isset($data['specialite'])) {
                $fields[] = "specialite = ?";
                $params[] = $data['specialite'];
            }
            if (isset($data['statut'])) {
                $fields[] = "statut = ?";
                $params[] = $data['statut'];
            }
            
            if (empty($fields)) {
                echo json_encode(['success' => false, 'message' => 'Aucune donnée à modifier']);
                exit;
            }
            
            $fields[] = "updated_at = NOW()";
            $params[] = $data['id'];
            
            $sql = "UPDATE place SET " . implode(', ', $fields) . " WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            echo json_encode(['success' => true, 'message' => 'Place modifiée avec succès']);
            break;
            
        case 'DELETE':
            $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
            
            if ($id <= 0) {
                $data = json_decode(file_get_contents('php://input'), true);
                $id = isset($data['id']) ? intval($data['id']) : 0;
            }
            
            if ($id <= 0) {
                echo json_encode(['success' => false, 'message' => 'ID requis']);
                exit;
            }
            
            $stmt = $pdo->prepare("DELETE FROM place WHERE id = ?");
            $stmt->execute([$id]);
            
            echo json_encode(['success' => true, 'message' => 'Place supprimée avec succès']);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Méthode non supportée']);
            break;
    }
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur base de données: ' . $e->getMessage()]);
}
?>