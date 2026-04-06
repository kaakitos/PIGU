<?php
require_once 'db_connect.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, PUT');
header('Access-Control-Allow-Headers: Content-Type');

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch($method) {
        case 'GET':
            $ambulance_id = isset($_GET['ambulance_id']) ? intval($_GET['ambulance_id']) : 0;
            
            if ($ambulance_id > 0) {
                $stmt = $pdo->prepare("
                    SELECT m.*, 
                           i.description, i.adresse_texte, i.priorite, i.date_heure,
                           h.nom as hopital_nom, h.adresse as hopital_adresse,
                           u.nom as admin_nom, u.prenom as admin_prenom
                    FROM mission m
                    JOIN incident i ON m.incident_id = i.id
                    JOIN hopital h ON m.hopital_dest_id = h.id
                    JOIN utilisateur u ON m.admin_id = u.id
                    WHERE m.ambulance_id = ?
                    ORDER BY m.date_debut DESC
                ");
                $stmt->execute([$ambulance_id]);
                echo json_encode($stmt->fetchAll());
            } else {
                echo json_encode([]);
            }
            break;
            
        case 'PUT':
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!$data || !isset($data['id']) || !isset($data['statut'])) {
                echo json_encode(['success' => false, 'message' => 'Données invalides']);
                exit;
            }
            
            $pdo->beginTransaction();
            
            // Mettre à jour la mission
            $date_fin = $data['statut'] === 'TERMINEE' ? 'NOW()' : 'NULL';
            $stmt = $pdo->prepare("
                UPDATE mission 
                SET statut = ?, date_fin = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$data['statut'], $data['id']]);
            
            // Si mission terminée, libérer l'ambulance
            if ($data['statut'] === 'TERMINEE') {
                $stmt = $pdo->prepare("
                    UPDATE ambulance SET statut = 'DISPONIBLE' 
                    WHERE id = (SELECT ambulance_id FROM mission WHERE id = ?)
                ");
                $stmt->execute([$data['id']]);
            }
            
            // Si mission en cours, mettre à jour statut ambulance
            if ($data['statut'] === 'EN_COURS') {
                $stmt = $pdo->prepare("
                    UPDATE ambulance SET statut = 'EN_MISSION' 
                    WHERE id = (SELECT ambulance_id FROM mission WHERE id = ?)
                ");
                $stmt->execute([$data['id']]);
            }
            
            $pdo->commit();
            
            echo json_encode(['success' => true, 'message' => 'Statut mis à jour']);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Méthode non supportée']);
            break;
    }
} catch(PDOException $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Erreur base de données: ' . $e->getMessage()]);
}
?>