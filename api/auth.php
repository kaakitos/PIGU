<?php
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

// ══════════════════════════════════════════════
// LOGIN
// ══════════════════════════════════════════════
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
            $stmt2 = $pdo->prepare("
                SELECT id, nom, adresse, latitude, longitude, telephone 
                FROM hopital 
                WHERE utilisateur_id = ?
            ");
            $stmt2->execute([$user['id']]);
            $hopital = $stmt2->fetch();
            $user['hopital_id']      = $hopital ? $hopital['id']        : null;
            $user['hopital_nom']     = $hopital ? $hopital['nom']       : null;
            $user['hopital_adresse'] = $hopital ? $hopital['adresse']   : null;
        }

        if ($user['role'] === 'AMBULANCIER') {
            $stmt2 = $pdo->prepare("
                SELECT id, immatriculation, statut 
                FROM ambulance 
                WHERE utilisateur_id = ?
            ");
            $stmt2->execute([$user['id']]);
            $ambulance = $stmt2->fetch();
            $user['ambulance_id']     = $ambulance ? $ambulance['id']             : null;
            $user['immatriculation']  = $ambulance ? $ambulance['immatriculation'] : null;
            $user['ambulance_statut'] = $ambulance ? $ambulance['statut']          : null;
        }

        if ($user['role'] === 'ADMIN_SAMU') {
            $user['is_admin'] = true;
        }

        echo json_encode(['success' => true, 'user' => $user]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Identifiants incorrects ou rôle invalide']);
    }
}

// ══════════════════════════════════════════════
// REGISTER
// ══════════════════════════════════════════════
function handleRegister($pdo, $data) {
    if (!$data) {
        echo json_encode(['success' => false, 'message' => 'Données invalides']);
        return;
    }

    // Champs utilisateur obligatoires
    if (!isset($data['nom']) || !isset($data['prenom']) || !isset($data['login']) ||
        !isset($data['mot_de_passe']) || !isset($data['telephone'])) {
        echo json_encode(['success' => false, 'message' => 'Tous les champs sont requis']);
        return;
    }

    $role = isset($data['role']) ? strtoupper($data['role']) : 'AMBULANCIER';

    // Pour un gestionnaire, le nom de l'hôpital est obligatoire
    if ($role === 'GESTIONNAIRE_HOPITAL') {
        if (!isset($data['hopital']) || empty($data['hopital']['nom'])) {
            echo json_encode(['success' => false, 'message' => 'Le nom de l\'hôpital est requis']);
            return;
        }
    }

    // Vérifier login unique
    $stmt = $pdo->prepare("SELECT id FROM utilisateur WHERE login = ?");
    $stmt->execute([$data['login']]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Ce login est déjà utilisé']);
        return;
    }

    // Vérifier longueur mot de passe
    if (strlen($data['mot_de_passe']) < 6) {
        echo json_encode(['success' => false, 'message' => 'Le mot de passe doit contenir au moins 6 caractères']);
        return;
    }

    try {
        $pdo->beginTransaction();

        // 1. Créer l'utilisateur
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

        $hopitalId = null;

        // 2. Si gestionnaire → créer l'hôpital
        if ($role === 'GESTIONNAIRE_HOPITAL') {
            $hopital     = $data['hopital'];
            $nomHopital  = trim($hopital['nom']);
            $adresse     = isset($hopital['adresse'])   ? trim($hopital['adresse'])   : null;
            $latitude    = isset($hopital['latitude'])  ? $hopital['latitude']        : null;
            $longitude   = isset($hopital['longitude']) ? $hopital['longitude']       : null;
            $telHopital  = isset($hopital['telephone']) ? trim($hopital['telephone']) : null;

            // Adapter les colonnes à votre table hopital
            $stmt2 = $pdo->prepare("
                INSERT INTO hopital (nom, adresse, latitude, longitude, telephone, utilisateur_id) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt2->execute([
                $nomHopital,
                $adresse,
                $latitude,
                $longitude,
                $telHopital,
                $userId
            ]);
            $hopitalId = $pdo->lastInsertId();
        }

        $pdo->commit();

        echo json_encode([
            'success'    => true,
            'message'    => 'Inscription réussie',
            'user_id'    => $userId,
            'hopital_id' => $hopitalId
        ]);

    } catch(Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

// ══════════════════════════════════════════════
// LOGOUT
// ══════════════════════════════════════════════
function handleLogout() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    session_destroy();
    echo json_encode(['success' => true, 'message' => 'Déconnexion réussie']);
}

// ══════════════════════════════════════════════
// GET CURRENT USER
// ══════════════════════════════════════════════
function handleGetCurrentUser($pdo, $data) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
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
            $stmt2 = $pdo->prepare("
                SELECT id, nom, adresse, latitude, longitude, telephone 
                FROM hopital 
                WHERE utilisateur_id = ?
            ");
            $stmt2->execute([$user['id']]);
            $hopital = $stmt2->fetch();
            $user['hopital_id']      = $hopital ? $hopital['id']      : null;
            $user['hopital_nom']     = $hopital ? $hopital['nom']     : null;
            $user['hopital_adresse'] = $hopital ? $hopital['adresse'] : null;
        }

        if ($user['role'] === 'AMBULANCIER') {
            $stmt2 = $pdo->prepare("
                SELECT id, immatriculation, statut 
                FROM ambulance 
                WHERE utilisateur_id = ?
            ");
            $stmt2->execute([$user['id']]);
            $ambulance = $stmt2->fetch();
            $user['ambulance_id']    = $ambulance ? $ambulance['id']             : null;
            $user['immatriculation'] = $ambulance ? $ambulance['immatriculation'] : null;
        }

        echo json_encode(['success' => true, 'user' => $user]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Utilisateur non trouvé']);
    }
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>