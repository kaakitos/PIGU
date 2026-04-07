<?php
require_once 'PIGU/api/db_connect.php';


header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT');
header('Access-Control-Allow-Headers: Content-Type');

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch($method) {
        case 'GET':
            $utilisateur_id = isset($_GET['utilisateur_id']) ? intval($_GET['utilisateur_id']) : 0;
            
            if ($utilisateur_id > 0) {
                $stmt = $pdo->prepare("SELECT * FROM ambulance WHERE utilisateur_id = ?");
                $stmt->execute([$utilisateur_id]);
                echo json_encode($stmt->fetch());
            } else {
                $stmt = $pdo->query("SELECT a.*, u.nom, u.prenom FROM ambulance a JOIN utilisateur u ON a.utilisateur_id = u.id");
                echo json_encode($stmt->fetchAll());
            }
            break;
            
        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!$data || !isset($data['utilisateur_id']) || !isset($data['immatriculation'])) {
                echo json_encode(['success' => false, 'message' => 'Données invalides']);
                exit;
            }
            
            // Vérifier si l'ambulance existe déjà
            $stmt = $pdo->prepare("SELECT id FROM ambulance WHERE utilisateur_id = ?");
            $stmt->execute([$data['utilisateur_id']]);
            if ($stmt->fetch()) {
                echo json_encode(['success' => false, 'message' => 'Vous avez déjà une ambulance']);
                exit;
            }
            
            $stmt = $pdo->prepare("
                INSERT INTO ambulance (utilisateur_id, immatriculation, statut) 
                VALUES (?, ?, 'DISPONIBLE')
            ");
            $stmt->execute([$data['utilisateur_id'], $data['immatriculation']]);
            
            echo json_encode([
                'success' => true, 
                'id' => $pdo->lastInsertId(),
                'message' => 'Ambulance créée avec succès'
            ]);
            break;
            
        case 'PUT':
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!$data || !isset($data['id'])) {
                echo json_encode(['success' => false, 'message' => 'ID requis']);
                exit;
            }
            
            $stmt = $pdo->prepare("UPDATE ambulance SET immatriculation = ? WHERE id = ?");
            $stmt->execute([$data['immatriculation'], $data['id']]);
            
            echo json_encode(['success' => true, 'message' => 'Ambulance mise à jour']);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Méthode non supportée']);
            break;
    }
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur base de données: ' . $e->getMessage()]);
}
?>