<?php
session_start();
require_once 'db_connect.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

$method = $_SERVER['REQUEST_METHOD'];
$action = isset($_GET['action']) ? $_GET['action'] : '';

$data = json_decode(file_get_contents('php://input'), true);
if ($data && isset($data['action'])) {
    $action = $data['action'];
}

try {
    switch($action) {
        case 'login':
            handleLogin($pdo, $data);
            break;
        case 'register':
            handleRegister($pdo, $data);
            break;
        case 'logout':
            handleLogout();
            break;
        case 'me':
            handleGetCurrentUser($pdo, $data);
            break;
        default:
            echo json_encode(['success' => false, 'message' => 'Action non reconnue']);
            break;
    }
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur base de données: ' . $e->getMessage()]);
} catch(Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
}

function handleLogin($pdo, $data) {
    if (!$data || !isset($data['login']) || !isset($data['password'])) {
        echo json_encode(['success' => false, 'message' => 'Identifiants requis']);
        return;
    }
    
    $roleCondition = "";
    $params = [$data['login'], $data['password']];
    
    if (isset($data['role']) && !empty($data['role'])) {
        $roleCondition = " AND role = ?";
        $params[] = strtoupper($data['role']);
    }
    
    $stmt = $pdo->prepare("
        SELECT id, nom, prenom, login, telephone, role 
        FROM utilisateur 
        WHERE login = ? AND mot_de_passe = MD5(?)
        $roleCondition
    ");
    $stmt->execute($params);
    $user = $stmt->fetch();
    
    if ($user) {
        if ($user['role'] === 'GESTIONNAIRE_HOPITAL') {
            $stmt2 = $pdo->prepare("SELECT id FROM hopital WHERE utilisateur_id = ?");
            $stmt2->execute([$user['id']]);
            $hopital = $stmt2->fetch();
            $user['hopital_id'] = $hopital ? $hopital['id'] : null;
        }
        
        if ($user['role'] === 'AMBULANCIER') {
            $stmt2 = $pdo->prepare("SELECT id, immatriculation, statut FROM ambulance WHERE utilisateur_id = ?");
            $stmt2->execute([$user['id']]);
            $ambulance = $stmt2->fetch();
            $user['ambulance_id'] = $ambulance ? $ambulance['id'] : null;
            $user['immatriculation'] = $ambulance ? $ambulance['immatriculation'] : null;
            $user['ambulance_statut'] = $ambulance ? $ambulance['statut'] : null;
        }
        if ($user['role'] === 'ADMIN_SAMU') {
            $user['is_admin'] = true;
        }
        
        echo json_encode(['success' => true, 'user' => $user]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Identifiants incorrects ou rôle invalide']);
    }
}

function handleRegister($pdo, $data) {
    try {
        $pdo->beginTransaction();

        // Insertion Utilisateur
        $stmt = $pdo->prepare("INSERT INTO utilisateur (nom, prenom, login, mot_de_passe, telephone, role) VALUES (?, ?, ?, MD5(?), ?, ?)");
        $success = $stmt->execute([
            $data['nom'], 
            $data['prenom'], 
            $data['login'], 
            $data['mot_de_passe'], 
            $data['telephone'], 
            $data['role']
        ]);

        if (!$success) {
            // Si l'insertion utilisateur échoue, on récupère l'erreur
            throw new Exception("Erreur insertion Utilisateur : " . implode(", ", $stmt->errorInfo()));
        }

        $userId = $pdo->lastInsertId();

        // Insertion Hôpital
        if ($data['role'] === 'GESTIONNAIRE_HOPITAL' && isset($data['hopital'])) {
            $h = $data['hopital'];
            $stmtH = $pdo->prepare("INSERT INTO hopital (nom, adresse, latitude, longitude, telephone, utilisateur_id) VALUES (?, ?, ?, ?, ?, ?)");
            $successH = $stmtH->execute([
                $h['nom'], 
                $h['adresse'], 
                $h['latitude'], 
                $h['longitude'], 
                $h['telephone'], 
                $userId
            ]);

            if (!$successH) {
                throw new Exception("Erreur insertion Hôpital : " . implode(", ", $stmtH->errorInfo()));
            }
        }

        $pdo->commit();
        echo json_encode(['success' => true]);

    } catch (Exception $e) {
        $pdo->rollBack();
        // C'est ce message qui va nous donner la clé du mystère !
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function handleLogout() {
    session_destroy();
    echo json_encode(['success' => true, 'message' => 'Déconnexion réussie']);
}

function handleGetCurrentUser($pdo, $data) {
    
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Non authentifié']);
        return;
    }
    
    $stmt = $pdo->prepare("
        SELECT id, nom, prenom, login, telephone, role 
        FROM utilisateur 
        WHERE id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    
    if ($user) {
        if ($user['role'] === 'GESTIONNAIRE_HOPITAL') {
            $stmt2 = $pdo->prepare("SELECT id as hopital_id FROM hopital WHERE utilisateur_id = ?");
            $stmt2->execute([$user['id']]);
            $hopital = $stmt2->fetch();
            $user['hopital_id'] = $hopital ? $hopital['hopital_id'] : null;
        }
        if ($user['role'] === 'AMBULANCIER') {
            $stmt2 = $pdo->prepare("SELECT id as ambulance_id, immatriculation, statut FROM ambulance WHERE utilisateur_id = ?");
            $stmt2->execute([$user['id']]);
            $ambulance = $stmt2->fetch();
            $user['ambulance_id'] = $ambulance ? $ambulance['ambulance_id'] : null;
            $user['immatriculation'] = $ambulance ? $ambulance['immatriculation'] : null;
        }
        echo json_encode(['success' => true, 'user' => $user]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Utilisateur non trouvé']);
    }
}

?>