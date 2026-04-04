<?php
require_once 'db_connect.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

// Récupérer l'action depuis l'URL ou le body
$method = $_SERVER['REQUEST_METHOD'];
$action = isset($_GET['action']) ? $_GET['action'] : '';

// Pour POST, on peut aussi lire l'action dans le body
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
            echo json_encode(['success' => false, 'message' => 'Action non reconnue. Utilisez action=login ou action=register']);
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
        
        echo json_encode(['success' => true, 'user' => $user]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Identifiants incorrects ou rôle invalide']);
    }
}

function handleRegister($pdo, $data) {
    if (!$data) {
        echo json_encode(['success' => false, 'message' => 'Données invalides']);
        return;
    }
    
    if (!isset($data['nom']) || !isset($data['prenom']) || !isset($data['login']) || 
        !isset($data['mot_de_passe']) || !isset($data['telephone'])) {
        echo json_encode(['success' => false, 'message' => 'Tous les champs sont requis']);
        return;
    }
    
    $stmt = $pdo->prepare("SELECT id FROM utilisateur WHERE login = ?");
    $stmt->execute([$data['login']]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Ce login est déjà utilisé']);
        return;
    }
    
    if (strlen($data['mot_de_passe']) < 6) {
        echo json_encode(['success' => false, 'message' => 'Le mot de passe doit contenir au moins 6 caractères']);
        return;
    }
    
    try {
        $pdo->beginTransaction();
        
        $role = isset($data['role']) ? $data['role'] : 'GESTIONNAIRE_HOPITAL';
        
        $stmt = $pdo->prepare("
            INSERT INTO utilisateur (nom, prenom, login, mot_de_passe, telephone, role) 
            VALUES (?, ?, ?, MD5(?), ?, ?)
        ");
        $stmt->execute([
            $data['nom'],
            $data['prenom'],
            $data['login'],
            $data['mot_de_passe'],
            $data['telephone'],
            $role
        ]);
        $userId = $pdo->lastInsertId();
        
        if ($role === 'GESTIONNAIRE_HOPITAL') {
            if (!isset($data['hopital'])) {
                throw new Exception('Données hôpital requises pour un gestionnaire');
            }
            
            $hopital = $data['hopital'];
            $stmt = $pdo->prepare("
                INSERT INTO hopital (utilisateur_id, nom, adresse, latitude, longitude, telephone) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $userId,
                $hopital['nom'],
                $hopital['adresse'] ?? null,
                $hopital['latitude'] ?? null,
                $hopital['longitude'] ?? null,
                $hopital['telephone'] ?? null
            ]);
        }
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true, 
            'message' => 'Inscription réussie',
            'user_id' => $userId
        ]);
        
    } catch(Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function handleLogout() {
    session_start();
    session_destroy();
    echo json_encode(['success' => true, 'message' => 'Déconnexion réussie']);
}
function handleGetCurrentUser($pdo, $data) {
    session_start();
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
        echo json_encode(['success' => true, 'user' => $user]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Utilisateur non trouvé']);
    }
}

session_start();
?>