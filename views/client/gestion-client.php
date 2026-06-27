<?php
// ================================================================
// GESTION DES CLIENTS - EPENCIA SGI
// ================================================================

// ================================================================
// 1. SESSION & AUTHENTIFICATION
// ================================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['utilisateur_id'])) {
    header('Location: /utilisateur/connexion');
    exit();
}

require_once 'database/database.php';

$user_role = $_SESSION['role'] ?? '';
$user_nom = $_SESSION['nom_prenom'] ?? 'Utilisateur';
$is_admin = in_array($user_role, ['Administrateur', 'Superviseur']);
$is_medecin = ($user_role === 'Medecin');
$is_pharmacien = ($user_role === 'Pharmacien');

if (!$is_admin && !$is_medecin && !$is_pharmacien) {
    header('Location: ../utilisateur/dashboard.php?error=Accès non autorisé');
    exit();
}

// ================================================================
// 2. FONCTIONS
// ================================================================
function getStatusBadge($status) {
    $statusMap = [
        'actif' => ['class' => 'success', 'label' => 'Actif'],
        'inactif' => ['class' => 'danger', 'label' => 'Inactif'],
        'payé' => ['class' => 'success', 'label' => 'Payé'],
        'payee' => ['class' => 'success', 'label' => 'Payé'],
        'impayé' => ['class' => 'danger', 'label' => 'Impayé'],
        'impayee' => ['class' => 'danger', 'label' => 'Impayé'],
        'en attente' => ['class' => 'warning', 'label' => 'En attente'],
        'en_attente' => ['class' => 'warning', 'label' => 'En attente'],
        'annulé' => ['class' => 'secondary', 'label' => 'Annulé'],
        'annulee' => ['class' => 'secondary', 'label' => 'Annulé'],
        'partiel' => ['class' => 'info', 'label' => 'Partiel'],
        'partielle' => ['class' => 'info', 'label' => 'Partiel']
    ];
    $status = strtolower($status ?? 'inactif');
    $info = $statusMap[$status] ?? ['class' => 'secondary', 'label' => ucfirst($status)];
    return '<span class="badge bg-' . $info['class'] . '">' . $info['label'] . '</span>';
}

function getRoleBadge($role) {
    $roleMap = [
        'Superviseur' => ['class' => 'danger', 'icon' => 'bi-shield-check'],
        'Administrateur' => ['class' => 'primary', 'icon' => 'bi-shield-fill'],
        'Pharmacien' => ['class' => 'info', 'icon' => 'bi-capsule'],
        'Medecin' => ['class' => 'success', 'icon' => 'bi-heart-pulse']
    ];
    $info = $roleMap[$role] ?? ['class' => 'secondary', 'icon' => 'bi-person'];
    return '<span class="badge bg-' . $info['class'] . '"><i class="bi ' . $info['icon'] . ' me-1"></i>' . htmlspecialchars($role) . '</span>';
}

function escapeHtml($text) {
    if (!$text) return '';
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

function generateClientId($pdo) {
    $maxAttempts = 100;
    $attempt = 0;
    
    do {
        $random = str_pad(rand(100000, 999999), 6, '0', STR_PAD_LEFT);
        $client_id = 'CLT_EPENCIA_' . $random;
        $attempt++;
    } while (clientIdExists($pdo, $client_id) && $attempt < $maxAttempts);
    
    return $client_id;
}

function clientIdExists($pdo, $client_id) {
    try {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM clients WHERE client_id = ?');
        $stmt->execute([$client_id]);
        return $stmt->fetchColumn() > 0;
    } catch (PDOException $e) {
        return false;
    }
}

function generateQRCode($pdo, $client_id) {
    $qrlibPath = __DIR__ . '/../../library/phpqrcode/qrlib.php';
    if (!file_exists($qrlibPath)) {
        return false;
    }
    
    require_once($qrlibPath);
    
    $qrcodeDir = __DIR__ . '/../../qrcodes';
    if (!file_exists($qrcodeDir)) {
        mkdir($qrcodeDir, 0777, true);
    }
    
    $qrContent = $client_id;
    $filename = $qrcodeDir . '/qr_' . $client_id . '.png';
    
    try {
        QRcode::png($qrContent, $filename, QR_ECLEVEL_L, 10, 2);
        return 'qrcodes/qr_' . $client_id . '.png';
    } catch (Exception $e) {
        error_log("Erreur génération QR: " . $e->getMessage());
        return false;
    }
}

function getClients($pdo, $filters = []) {
    try {
        $sql = "SELECT * FROM clients";
        $where = [];
        $params = [];
        
        if (!empty($filters['search'])) {
            $where[] = "(client_id LIKE :search OR nom_prenom LIKE :search OR telephone LIKE :search OR email LIKE :search)";
            $params[':search'] = '%' . $filters['search'] . '%';
        }
        
        if (!empty($filters['statut']) && $filters['statut'] != 'tous') {
            $where[] = "statut = :statut";
            $params[':statut'] = $filters['statut'];
        }
        
        if (!empty($filters['sexe']) && $filters['sexe'] != 'tous') {
            $where[] = "sexe = :sexe";
            $params[':sexe'] = $filters['sexe'];
        }
        
        if (!empty($filters['groupe_sanguin']) && $filters['groupe_sanguin'] != 'tous') {
            $where[] = "groupe_sanguin = :groupe_sanguin";
            $params[':groupe_sanguin'] = $filters['groupe_sanguin'];
        }
        
        if (!empty($where)) {
            $sql .= " WHERE " . implode(" AND ", $where);
        }
        
        $sql .= " ORDER BY nom_prenom ASC";
        
        $stmt = $pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        return [];
    }
}

function getClientById($pdo, $client_id) {
    try {
        $stmt = $pdo->prepare('SELECT * FROM clients WHERE client_id = ?');
        $stmt->execute([$client_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return null;
    }
}

function getFacturesByClient($pdo, $client_id) {
    try {
        $stmt = $pdo->prepare('
            SELECT f.* 
            FROM factures f
            WHERE f.client_id = ?
            ORDER BY f.date_creation DESC
        ');
        $stmt->execute([$client_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

function getFactureDetail($pdo, $facture_id) {
    try {
        $stmt = $pdo->prepare('
            SELECT f.*, c.nom_prenom as client_nom
            FROM factures f
            LEFT JOIN clients c ON f.client_id = c.client_id
            WHERE f.facture_id = ?
        ');
        $stmt->execute([$facture_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return null;
    }
}

// ================================================================
// 3. HANDLERS AJAX
// ================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    header('Content-Type: application/json; charset=utf-8');
    
    if ($_POST['ajax_action'] === 'load_client') {
        $id = $_POST['id'];
        $client = getClientById($pdo, $id);
        if ($client) {
            if (isset($client['photo'])) {
                $client['has_photo'] = !empty($client['photo']);
            }
            echo json_encode(['success' => true, 'data' => $client]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Client introuvable']);
        }
        exit;
    }
    
    if ($_POST['ajax_action'] === 'get_factures') {
        $client_id = $_POST['client_id'];
        $factures = getFacturesByClient($pdo, $client_id);
        echo json_encode(['success' => true, 'factures' => $factures]);
        exit;
    }
    
    if ($_POST['ajax_action'] === 'get_facture_detail') {
        $facture_id = $_POST['facture_id'];
        $facture = getFactureDetail($pdo, $facture_id);
        if ($facture) {
            echo json_encode(['success' => true, 'facture' => $facture]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Facture introuvable']);
        }
        exit;
    }
}

// ================================================================
// 4. TRAITEMENT CRUD
// ================================================================
$message = '';
$error = '';
$editClient = null;
$qrFile = null;
$last6Digits = null;

$filters = [
    'search' => '',
    'statut' => '',
    'sexe' => '',
    'groupe_sanguin' => ''
];

if (isset($_SESSION['client_filters']) && is_array($_SESSION['client_filters'])) {
    $filters = array_merge($filters, $_SESSION['client_filters']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['ajax_action'])) {
    
    // SUPPRIMER
    if (isset($_POST['delete']) && !empty($_POST['delete'])) {
        try {
            $qrFile = __DIR__ . '/../../qrcodes/qr_' . $_POST['delete'] . '.png';
            if (file_exists($qrFile)) {
                unlink($qrFile);
            }
            $stmt = $pdo->prepare('DELETE FROM clients WHERE client_id = ?');
            $stmt->execute([$_POST['delete']]);
            $message = 'Client supprimé avec succès';
        } catch (PDOException $e) {
            $error = 'Erreur base de données : ' . $e->getMessage();
        }
    }
    
    // APPLIQUER FILTRES
    if (isset($_POST['apply_filters'])) {
        $filters = [
            'search' => isset($_POST['search']) ? trim($_POST['search']) : '',
            'statut' => isset($_POST['statut']) ? $_POST['statut'] : '',
            'sexe' => isset($_POST['sexe']) ? $_POST['sexe'] : '',
            'groupe_sanguin' => isset($_POST['groupe_sanguin']) ? $_POST['groupe_sanguin'] : ''
        ];
        $_SESSION['client_filters'] = $filters;
    }
    
    // REINITIALISER FILTRES
    if (isset($_POST['reset_filters'])) {
        $filters = [
            'search' => '',
            'statut' => '',
            'sexe' => '',
            'groupe_sanguin' => ''
        ];
        $_SESSION['client_filters'] = $filters;
    }
    
    // AJOUTER OU MODIFIER
    if (isset($_POST['submit'])) {
        $photo_data = null;
        $type_photo = null;
        
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $type_photo = $_FILES['photo']['type'];
            $photo_data = file_get_contents($_FILES['photo']['tmp_name']);
        }
        
        $data = [
            'nom_prenom' => trim($_POST['nom_prenom'] ?? ''),
            'date_naissance' => $_POST['date_naissance'] ?? '',
            'lieu_naissance' => trim($_POST['lieu_naissance'] ?? ''),
            'sexe' => $_POST['sexe'] ?? '',
            'nationalite' => trim($_POST['nationalite'] ?? ''),
            'groupe_sanguin' => $_POST['groupe_sanguin'] ?? '',
            'telephone' => trim($_POST['telephone'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'pays' => trim($_POST['pays'] ?? ''),
            'ville' => trim($_POST['ville'] ?? ''),
            'adresse' => trim($_POST['adresse'] ?? ''),
            'profession' => trim($_POST['profession'] ?? ''),
            'nom_prenom_urgence' => trim($_POST['nom_prenom_urgence'] ?? ''),
            'telephone_urgence' => trim($_POST['telephone_urgence'] ?? ''),
            'email_urgence' => trim($_POST['email_urgence'] ?? ''),
            'description' => trim($_POST['description'] ?? ''),
            'statut' => $_POST['statut'] ?? 'actif',
            'photo' => $photo_data,
            'type_photo' => $type_photo
        ];
        
        // MODIFIER
        if (isset($_POST['client_id']) && !empty($_POST['client_id'])) {
            try {
                $sql = "UPDATE clients SET 
                    nom_prenom = :nom_prenom,
                    date_naissance = :date_naissance,
                    lieu_naissance = :lieu_naissance,
                    sexe = :sexe,
                    nationalite = :nationalite,
                    groupe_sanguin = :groupe_sanguin,
                    telephone = :telephone,
                    email = :email,
                    pays = :pays,
                    ville = :ville,
                    adresse = :adresse,
                    profession = :profession,
                    nom_prenom_urgence = :nom_prenom_urgence,
                    telephone_urgence = :telephone_urgence,
                    email_urgence = :email_urgence,
                    description = :description" .
                    (!empty($data['photo']) ? ", photo = :photo, type_photo = :type_photo" : "") . ",
                    statut = :statut
                WHERE client_id = :client_id";
                
                $stmt = $pdo->prepare($sql);
                $params = [
                    ':client_id' => $_POST['client_id'],
                    ':nom_prenom' => $data['nom_prenom'],
                    ':date_naissance' => !empty($data['date_naissance']) ? $data['date_naissance'] : null,
                    ':lieu_naissance' => $data['lieu_naissance'] ?? null,
                    ':sexe' => $data['sexe'] ?? null,
                    ':nationalite' => $data['nationalite'] ?? null,
                    ':groupe_sanguin' => $data['groupe_sanguin'] ?? null,
                    ':telephone' => $data['telephone'],
                    ':email' => $data['email'] ?? null,
                    ':pays' => $data['pays'] ?? null,
                    ':ville' => $data['ville'] ?? null,
                    ':adresse' => $data['adresse'] ?? null,
                    ':profession' => $data['profession'] ?? null,
                    ':nom_prenom_urgence' => $data['nom_prenom_urgence'] ?? null,
                    ':telephone_urgence' => $data['telephone_urgence'] ?? null,
                    ':email_urgence' => $data['email_urgence'] ?? null,
                    ':description' => $data['description'] ?? null,
                    ':statut' => $data['statut'] ?? 'actif'
                ];
                
                if (!empty($data['photo'])) {
                    $params[':photo'] = $data['photo'];
                    $params[':type_photo'] = $data['type_photo'];
                }
                
                $stmt->execute($params);
                $message = 'Client modifié avec succès';
            } catch (PDOException $e) {
                $error = 'Erreur base de données : ' . $e->getMessage();
            }
        } else {
            // AJOUTER
            try {
                $client_id = generateClientId($pdo);
                $last6 = substr($client_id, -6);
                
                $sql = "INSERT INTO clients (
                    client_id, nom_prenom, date_naissance, lieu_naissance, sexe, 
                    nationalite, groupe_sanguin, telephone, email, solde, 
                    pays, ville, adresse, profession, nom_prenom_urgence, 
                    telephone_urgence, email_urgence, description, photo, type_photo, statut
                ) VALUES (
                    :client_id, :nom_prenom, :date_naissance, :lieu_naissance, :sexe,
                    :nationalite, :groupe_sanguin, :telephone, :email, :solde,
                    :pays, :ville, :adresse, :profession, :nom_prenom_urgence,
                    :telephone_urgence, :email_urgence, :description, :photo, :type_photo, :statut
                )";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':client_id' => $client_id,
                    ':nom_prenom' => $data['nom_prenom'],
                    ':date_naissance' => !empty($data['date_naissance']) ? $data['date_naissance'] : null,
                    ':lieu_naissance' => $data['lieu_naissance'] ?? null,
                    ':sexe' => $data['sexe'] ?? null,
                    ':nationalite' => $data['nationalite'] ?? null,
                    ':groupe_sanguin' => $data['groupe_sanguin'] ?? null,
                    ':telephone' => $data['telephone'],
                    ':email' => $data['email'] ?? null,
                    ':solde' => 0,
                    ':pays' => $data['pays'] ?? null,
                    ':ville' => $data['ville'] ?? null,
                    ':adresse' => $data['adresse'] ?? null,
                    ':profession' => $data['profession'] ?? null,
                    ':nom_prenom_urgence' => $data['nom_prenom_urgence'] ?? null,
                    ':telephone_urgence' => $data['telephone_urgence'] ?? null,
                    ':email_urgence' => $data['email_urgence'] ?? null,
                    ':description' => $data['description'] ?? null,
                    ':photo' => $data['photo'] ?? null,
                    ':type_photo' => $data['type_photo'] ?? null,
                    ':statut' => $data['statut'] ?? 'actif'
                ]);
                
                $qrFile = generateQRCode($pdo, $client_id);
                
                $message = 'Client ajouté avec succès - ID: ' . $client_id;
                $last6Digits = $last6;
                $_SESSION['qr_success'] = [
                    'id' => $client_id, 
                    'last6' => $last6,
                    'qr_file' => $qrFile
                ];
                
            } catch (PDOException $e) {
                $error = 'Erreur base de données : ' . $e->getMessage();
            }
        }
    }
    
    if (isset($_POST['edit']) && !empty($_POST['edit'])) {
        $editClient = getClientById($pdo, $_POST['edit']);
    }
}

// ================================================================
// 5. RÉCUPÉRATION DES DONNÉES
// ================================================================
$clients = getClients($pdo, $filters);

$successId = isset($_SESSION['qr_success']['id']) ? $_SESSION['qr_success']['id'] : null;
$last6Digits = isset($_SESSION['qr_success']['last6']) ? $_SESSION['qr_success']['last6'] : null;
$qrFile = isset($_SESSION['qr_success']['qr_file']) ? $_SESSION['qr_success']['qr_file'] : null;

if ($successId) {
    unset($_SESSION['qr_success']);
}

$totalClients = count($clients);
$actifs = array_filter($clients, function($c) { return ($c['statut'] ?? 'actif') == 'actif'; });
$inactifs = array_filter($clients, function($c) { return ($c['statut'] ?? 'actif') == 'inactif'; });
$avecQr = array_filter($clients, function($c) { 
    return file_exists(__DIR__ . '/../../qrcodes/qr_' . $c['client_id'] . '.png'); 
});

$editData = $editClient ?? [];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Clients - Epencia SGI</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=DM+Serif+Display:ital@0;1&display=swap" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        /* ============================================================ */
        /* DESIGN MÉDICAL - EPENCIA SGI                                */
        /* ============================================================ */
        :root {
            --medical-blue: #1a6b8a;
            --medical-blue-light: #e6f2f7;
            --medical-blue-dark: #0f4a62;
            --medical-teal: #2d9b8e;
            --medical-teal-light: #e6f5f3;
            --medical-white: #ffffff;
            --medical-bg: #f0f5f8;
            --medical-gray: #eef2f6;
            --medical-gray-light: #f7f9fb;
            --medical-text: #1a2a3a;
            --medical-text-secondary: #4a6a7a;
            --medical-text-muted: #8aa0b0;
            --medical-border: #dce4ea;
            --medical-shadow: 0 2px 12px rgba(26, 107, 138, 0.08);
            --medical-shadow-hover: 0 8px 30px rgba(26, 107, 138, 0.12);
            --medical-radius: 16px;
            --medical-radius-sm: 10px;
            --medical-transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            --success: #2d9b8e;
            --success-light: #e6f5f3;
            --warning: #d4a843;
            --warning-light: #fdf5e6;
            --danger: #c0392b;
            --danger-light: #fde8e6;
            --info: #3498db;
            --info-light: #e8f4fd;
            --font-primary: 'Plus Jakarta Sans', -apple-system, system-ui, sans-serif;
            --font-serif: 'DM Serif Display', Georgia, serif;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            background: var(--medical-bg);
            font-family: var(--font-primary);
            color: var(--medical-text);
            min-height: 100vh;
            padding: 20px;
            font-weight: 400;
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
        }

        .app-container { max-width: 1400px; margin: 0 auto; }

        /* ============================================================ */
        /* HEADER                                                       */
        /* ============================================================ */
        .page-header {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin-bottom: 24px;
            padding: 18px 24px;
            background: var(--medical-white);
            border-radius: var(--medical-radius);
            box-shadow: var(--medical-shadow);
            border: 1px solid var(--medical-border);
            position: relative;
            overflow: hidden;
        }

        .page-header::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--medical-blue), var(--medical-teal), var(--medical-blue));
            background-size: 200% 100%;
            animation: gradientMove 4s ease infinite;
        }

        @keyframes gradientMove {
            0%, 100% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
        }

        .page-header .header-left { flex: 1 1 280px; min-width: 0; }
        .page-header .medical-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: var(--medical-blue-light);
            color: var(--medical-blue);
            padding: 3px 14px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            margin-bottom: 6px;
        }
        .page-header h1 {
            font-family: var(--font-serif);
            font-size: clamp(20px, 2.5vw, 34px);
            font-weight: 400;
            letter-spacing: -0.01em;
            color: var(--medical-text);
            line-height: 1.2;
        }
        .page-header h1 .highlight {
            color: var(--medical-blue);
            position: relative;
        }
        .page-header h1 .highlight::after {
            content: '';
            position: absolute;
            bottom: 2px; left: 0; right: 0;
            height: 3px;
            background: var(--medical-teal);
            border-radius: 2px;
            opacity: 0.4;
        }
        .page-header .subtitle {
            color: var(--medical-text-secondary);
            font-size: clamp(11px, 0.9vw, 14px);
            font-weight: 400;
            margin-top: 2px;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 6px;
        }
        .page-header .subtitle .dot {
            display: inline-block;
            width: 4px; height: 4px;
            border-radius: 50%;
            background: var(--medical-text-muted);
            margin: 0 4px;
        }
        .header-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            align-items: center;
        }

        .btn-back-adaptive {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            color: var(--medical-text-secondary);
            text-decoration: none;
            font-weight: 700;
            padding: 10px 22px;
            border-radius: var(--medical-radius-sm);
            transition: var(--medical-transition);
            font-family: var(--font-primary);
            border: 1.5px solid var(--medical-border);
            background: transparent;
            white-space: nowrap;
            flex-shrink: 0;
            min-height: 44px;
        }
        .btn-back-adaptive:hover {
            background: var(--medical-gray-light);
            color: var(--medical-blue);
            border-color: var(--medical-blue);
            text-decoration: none;
        }
        .btn-back-adaptive .btn-icon { font-size: 16px; flex-shrink: 0; display: inline-flex; align-items: center; }
        .btn-back-adaptive .btn-label { flex-shrink: 0; }

        /* ============================================================ */
        /* ID STRUCTURE                                                 */
        /* ============================================================ */
        .id-structure {
            background: var(--medical-white);
            border: 1px solid var(--medical-border);
            border-radius: var(--medical-radius);
            padding: 14px 20px;
            text-align: center;
            margin-bottom: 24px;
            box-shadow: var(--medical-shadow);
            font-family: 'Courier New', monospace;
        }
        .id-structure .prefix { color: var(--medical-blue); font-weight: 700; }
        .id-structure .suffix { color: var(--danger); font-weight: 700; background: var(--danger-light); padding: 2px 12px; border-radius: 4px; }
        .id-structure .subtitle { font-size: 12px; color: var(--medical-text-muted); font-family: var(--font-primary); }
        .id-structure .subtitle .highlight { color: var(--danger); font-weight: 700; }

        /* ============================================================ */
        /* STATS CARDS                                                  */
        /* ============================================================ */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 14px;
            margin-bottom: 24px;
        }
        .stat-card {
            background: var(--medical-white);
            padding: 16px 18px;
            border-radius: var(--medical-radius);
            box-shadow: var(--medical-shadow);
            border: 1px solid var(--medical-border);
            display: flex;
            align-items: center;
            gap: 14px;
            transition: var(--medical-transition);
            cursor: pointer;
        }
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--medical-shadow-hover);
        }
        .stat-card .stat-icon {
            width: 44px; height: 44px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            flex-shrink: 0;
        }
        .stat-icon.blue { background: var(--medical-blue-light); color: var(--medical-blue); }
        .stat-icon.teal { background: var(--medical-teal-light); color: var(--medical-teal); }
        .stat-icon.warning { background: var(--warning-light); color: var(--warning); }
        .stat-icon.info { background: var(--info-light); color: var(--info); }
        .stat-card .stat-content { flex: 1; min-width: 0; }
        .stat-card .stat-value {
            font-size: clamp(18px, 1.8vw, 26px);
            font-weight: 800;
            color: var(--medical-text);
            line-height: 1.1;
        }
        .stat-card .stat-label {
            font-size: clamp(10px, 0.75vw, 12px);
            color: var(--medical-text-secondary);
            font-weight: 600;
            margin-top: 2px;
            letter-spacing: 0.02em;
        }

        /* ============================================================ */
        /* FILTER SECTION                                              */
        /* ============================================================ */
        .filter-section {
            background: var(--medical-white);
            border-radius: var(--medical-radius);
            padding: 20px;
            margin-bottom: 24px;
            box-shadow: var(--medical-shadow);
            border: 1px solid var(--medical-border);
        }
        .filter-section .filter-title {
            font-weight: 700;
            color: var(--medical-text-secondary);
            margin-bottom: 16px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .filter-section .form-label {
            font-weight: 600;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: var(--medical-text-secondary);
        }
        .filter-section .form-control,
        .filter-section .form-select {
            background: var(--medical-gray-light);
            border: 1.5px solid var(--medical-border);
            border-radius: var(--medical-radius-sm);
            padding: 9px 14px;
            font-size: 0.9rem;
            transition: var(--medical-transition);
            height: 42px;
            font-weight: 500;
            color: var(--medical-text);
            width: 100%;
        }
        .filter-section .form-control:focus,
        .filter-section .form-select:focus {
            border-color: var(--medical-blue);
            box-shadow: 0 0 0 4px rgba(26, 107, 138, 0.1);
            background: #fff;
        }

        /* ============================================================ */
        /* CARD                                                         */
        /* ============================================================ */
        .card-modern {
            background: var(--medical-white);
            border: none;
            border-radius: var(--medical-radius);
            box-shadow: var(--medical-shadow);
            overflow: hidden;
            border: 1px solid var(--medical-border);
            transition: var(--medical-transition);
        }
        .card-modern:hover { box-shadow: var(--medical-shadow-hover); }
        .card-modern .card-header {
            background: var(--medical-gray-light);
            border-bottom: 1px solid var(--medical-border);
            padding: 14px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }
        .card-modern .card-header h5 {
            font-size: clamp(13px, 1.1vw, 16px);
            font-weight: 700;
            margin: 0;
            color: var(--medical-text);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .card-modern .card-header .header-icon {
            width: 32px; height: 32px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            flex-shrink: 0;
        }
        .header-icon.blue { background: var(--medical-blue-light); color: var(--medical-blue); }
        .card-modern .card-body { padding: 0; }

        /* ============================================================ */
        /* TABLE                                                        */
        /* ============================================================ */
        .table-wrapper {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        .table-dashboard {
            font-size: clamp(0.72rem, 0.78vw, 0.85rem);
            margin-bottom: 0;
            min-width: 680px;
        }
        .table-dashboard thead th {
            padding: 10px 12px;
            font-weight: 700;
            color: var(--medical-text-secondary);
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            border-bottom: 2px solid var(--medical-border);
            background: var(--medical-gray-light);
            white-space: nowrap;
        }
        .table-dashboard tbody td {
            padding: 9px 12px;
            border-bottom: 1px solid var(--medical-border);
            color: var(--medical-text);
            vertical-align: middle;
        }
        .table-dashboard tbody tr:last-child td { border-bottom: none; }
        .table-dashboard tbody tr:hover { background: var(--medical-gray-light); }

        /* ============================================================ */
        /* AVATAR                                                       */
        /* ============================================================ */
        .avatar {
            width: 36px; height: 36px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 13px;
            color: white;
            flex-shrink: 0;
            background: linear-gradient(135deg, var(--medical-blue), var(--medical-teal));
        }

        /* ============================================================ */
        /* BADGES                                                       */
        /* ============================================================ */
        .badge {
            font-family: var(--font-primary);
            font-weight: 700;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 10px;
            letter-spacing: 0.03em;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            border: none;
        }
        .badge.bg-success { background: var(--success-light) !important; color: var(--success) !important; }
        .badge.bg-danger { background: var(--danger-light) !important; color: var(--danger) !important; }
        .badge.bg-warning { background: var(--warning-light) !important; color: var(--warning) !important; }
        .badge.bg-primary { background: var(--medical-blue-light) !important; color: var(--medical-blue) !important; }
        .badge.bg-info { background: var(--info-light) !important; color: var(--info) !important; }
        .badge.bg-secondary { background: var(--medical-gray) !important; color: var(--medical-text-secondary) !important; }

        /* ============================================================ */
        /* BOUTONS                                                      */
        /* ============================================================ */
        .btn-medical-primary {
            background: var(--medical-blue);
            color: #fff;
            border: none;
            padding: 10px 24px;
            border-radius: var(--medical-radius-sm);
            font-family: var(--font-primary);
            font-weight: 700;
            font-size: 0.9rem;
            transition: var(--medical-transition);
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            min-height: 44px;
        }
        .btn-medical-primary:hover {
            background: var(--medical-blue-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 20px rgba(26, 107, 138, 0.3);
            color: #fff;
        }
        .btn-medical-secondary {
            background: var(--medical-teal);
            color: #fff;
            border: none;
            padding: 10px 24px;
            border-radius: var(--medical-radius-sm);
            font-family: var(--font-primary);
            font-weight: 700;
            font-size: 0.9rem;
            transition: var(--medical-transition);
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            min-height: 44px;
        }
        .btn-medical-secondary:hover {
            background: #23837a;
            transform: translateY(-2px);
            box-shadow: 0 4px 20px rgba(45, 155, 142, 0.3);
            color: #fff;
        }
        .btn-medical-danger {
            background: var(--danger);
            color: #fff;
            border: none;
            padding: 10px 24px;
            border-radius: var(--medical-radius-sm);
            font-family: var(--font-primary);
            font-weight: 700;
            font-size: 0.9rem;
            transition: var(--medical-transition);
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            min-height: 44px;
        }
        .btn-medical-danger:hover {
            background: #a93226;
            transform: translateY(-2px);
            box-shadow: 0 4px 20px rgba(192, 57, 43, 0.3);
            color: #fff;
        }
        .btn-medical-outline {
            background: transparent;
            border: 1.5px solid var(--medical-border);
            color: var(--medical-text-secondary);
            padding: 10px 24px;
            border-radius: var(--medical-radius-sm);
            font-family: var(--font-primary);
            font-weight: 700;
            font-size: 0.9rem;
            transition: var(--medical-transition);
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            min-height: 44px;
        }
        .btn-medical-outline:hover {
            background: var(--medical-gray-light);
            border-color: var(--medical-blue);
            color: var(--medical-blue);
        }
        .btn-sm-medical {
            padding: 6px 14px !important;
            font-size: 0.8rem !important;
            min-height: 34px !important;
            gap: 6px !important;
        }

        /* ============================================================ */
        /* ACTIONS                                                      */
        /* ============================================================ */
        .actions-group {
            display: flex;
            gap: 4px;
            justify-content: center;
            align-items: center;
            flex-wrap: wrap;
        }
        .btn-action {
            width: 34px;
            height: 34px;
            padding: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            font-size: 15px;
            border: none;
            cursor: pointer;
            transition: var(--medical-transition);
            background: transparent;
            color: var(--medical-text-muted);
        }
        .btn-action:hover { transform: scale(1.1); background: var(--medical-gray-light); color: var(--medical-text); }
        .btn-action.edit:hover { background: var(--medical-blue-light); color: var(--medical-blue); }
        .btn-action.delete:hover { background: var(--danger-light); color: var(--danger); }
        .btn-action.qr:hover { background: var(--medical-teal); color: white; }
        .btn-action.facture:hover { background: var(--info); color: white; }
        .btn-action.qr { background: var(--medical-teal-light); color: var(--medical-teal); }
        .btn-action.facture { background: var(--info-light); color: var(--info); }

        /* ============================================================ */
        /* ALERTS                                                       */
        /* ============================================================ */
        .alert-medical {
            border: none;
            border-radius: var(--medical-radius-sm);
            padding: 14px 20px;
            font-weight: 500;
            border-left: 4px solid transparent;
            margin-bottom: 16px;
        }
        .alert-medical.alert-success {
            background: var(--success-light);
            color: var(--success);
            border-left-color: var(--success);
        }
        .alert-medical.alert-danger {
            background: var(--danger-light);
            color: var(--danger);
            border-left-color: var(--danger);
        }
        .alert-medical.alert-info {
            background: var(--medical-blue-light);
            color: var(--medical-blue);
            border-left-color: var(--medical-blue);
        }

        /* ============================================================ */
        /* MODALS                                                       */
        /* ============================================================ */
        .modal-content {
            border: none;
            border-radius: var(--medical-radius);
            overflow: hidden;
            box-shadow: 0 24px 60px rgba(0, 0, 0, 0.15);
        }
        .modal-header-medical {
            background: var(--medical-blue);
            color: white;
            border: none;
            padding: 16px 24px;
        }
        .modal-header-medical .modal-title {
            font-weight: 700;
            font-size: clamp(16px, 1.2vw, 18px);
        }
        .modal-header-medical .btn-close { filter: brightness(0) invert(1); }
        .modal-header-medical.success { background: var(--medical-teal); }
        .modal-header-medical.danger { background: var(--danger); }
        .modal-body { padding: 24px; }
        .modal-footer {
            border-top: 1px solid var(--medical-border);
            padding: 16px 24px;
            background: var(--medical-gray-light);
        }
        .modal .form-control, .modal .form-select {
            background: var(--medical-gray-light);
            border: 1.5px solid var(--medical-border);
            border-radius: var(--medical-radius-sm);
            padding: 9px 14px;
            font-size: 0.9rem;
            transition: var(--medical-transition);
            height: 42px;
            font-weight: 500;
            color: var(--medical-text);
            width: 100%;
        }
        .modal .form-control:focus, .modal .form-select:focus {
            border-color: var(--medical-blue);
            box-shadow: 0 0 0 4px rgba(26, 107, 138, 0.1);
            background: #fff;
        }
        .modal .form-label {
            font-weight: 700;
            font-size: clamp(11px, 0.75vw, 12px);
            color: var(--medical-text-secondary);
            margin-bottom: 4px;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }
        .modal-body .section-title {
            font-weight: 700;
            font-size: clamp(13px, 0.85vw, 14px);
            color: var(--medical-text);
            margin-bottom: 12px;
            padding-bottom: 8px;
            border-bottom: 2px solid var(--medical-border);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .modal-body .section-title i { color: var(--medical-blue); }
        .modal .text-muted { font-size: 11px; color: var(--medical-text-muted) !important; }
        .modal-body .solde-display {
            background: var(--medical-gray-light);
            border: 1px solid var(--medical-border);
            border-radius: var(--medical-radius-sm);
            padding: 10px 14px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .modal-body .solde-display .solde-label { font-size: 13px; color: var(--medical-text-secondary); font-weight: 600; }
        .modal-body .solde-display .solde-value { font-size: 18px; font-weight: 700; color: var(--medical-teal); }

        /* ============================================================ */
        /* QR DISPLAY                                                   */
        /* ============================================================ */
        .qr-display {
            border: 2px solid var(--medical-teal);
            padding: 20px;
            background: var(--medical-teal-light);
            text-align: center;
            max-width: 400px;
            margin: 0 auto 20px;
            border-radius: var(--medical-radius);
        }
        .qr-code-only {
            font-family: 'Courier New', monospace;
            font-size: 42px;
            font-weight: 700;
            color: var(--danger);
            background: var(--danger-light);
            padding: 8px 20px;
            border-radius: 8px;
            display: inline-block;
            margin: 10px 0;
            letter-spacing: 4px;
        }

        /* ============================================================ */
        /* FACTURE DETAIL                                               */
        /* ============================================================ */
        .facture-detail-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }
        .facture-detail-item {
            background: var(--medical-gray-light);
            padding: 10px 14px;
            border-radius: var(--medical-radius-sm);
            border-left: 3px solid var(--medical-blue);
        }
        .facture-detail-item .lbl {
            font-size: 0.65rem;
            color: var(--medical-text-muted);
            text-transform: uppercase;
            font-weight: 700;
            letter-spacing: 0.3px;
        }
        .facture-detail-item .val {
            font-weight: 600;
            color: var(--medical-text);
            font-size: 0.95rem;
        }
        .facture-detail-item.green { border-left-color: var(--success); background: var(--success-light); }
        .facture-detail-item.green .val { color: var(--success); }
        .facture-detail-item.danger { border-left-color: var(--danger); background: var(--danger-light); }
        .facture-detail-item.danger .val { color: var(--danger); }
        .facture-detail-item.warning { border-left-color: var(--warning); background: var(--warning-light); }
        .facture-detail-item.warning .val { color: var(--warning); }

        /* ============================================================ */
        /* SPINNER                                                      */
        /* ============================================================ */
        .loading-spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 2.5px solid var(--medical-border);
            border-top: 2.5px solid var(--medical-blue);
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* ============================================================ */
        /* ANIMATIONS                                                   */
        /* ============================================================ */
        @keyframes fadeSlideUp {
            from { opacity: 0; transform: translateY(16px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .fade-in { animation: fadeSlideUp 0.5s ease forwards; }
        .fade-in-d1 { animation-delay: 0.05s; }
        .fade-in-d2 { animation-delay: 0.10s; }
        .fade-in-d3 { animation-delay: 0.15s; }
        .fade-in-d4 { animation-delay: 0.20s; }

        /* ============================================================ */
        /* SCROLLBAR                                                    */
        /* ============================================================ */
        ::-webkit-scrollbar { width: 5px; height: 5px; }
        ::-webkit-scrollbar-track { background: var(--medical-gray-light); border-radius: 10px; }
        ::-webkit-scrollbar-thumb { background: var(--medical-border); border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: var(--medical-text-muted); }

        /* ============================================================ */
        /* RESPONSIVE                                                   */
        /* ============================================================ */
        @media (max-width: 820px) {
            body { padding: 10px; }
            .page-header h1 { font-size: 20px; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); gap: 10px; }
            .stat-card { padding: 12px 14px; gap: 10px; }
            .stat-card .stat-icon { width: 38px; height: 38px; font-size: 16px; }
            .stat-card .stat-value { font-size: 16px; }
            .filter-section { padding: 16px; }
            .btn-medical-primary, .btn-medical-secondary, .btn-medical-danger, .btn-medical-outline {
                padding: 6px 12px !important;
                font-size: 0.75rem !important;
                min-height: 34px !important;
                gap: 4px !important;
            }
            .btn-sm-medical { padding: 4px 8px !important; font-size: 0.7rem !important; min-height: 28px !important; }
            .btn-action { width: 30px !important; height: 30px !important; font-size: 13px !important; }
            .modal-footer .btn-medical-primary, .modal-footer .btn-medical-secondary, .modal-footer .btn-medical-danger, .modal-footer .btn-medical-outline {
                padding: 5px 10px !important; font-size: 0.7rem !important; min-height: 30px !important;
            }
            .btn-back-adaptive { padding: 10px 0 !important; width: 42px !important; height: 42px !important; border-radius: 50% !important; min-height: 42px !important; }
            .btn-back-adaptive .btn-label { display: none !important; }
            .btn-back-adaptive .btn-icon { font-size: 18px !important; }
        }

        @media (max-width: 576px) {
            body { padding: 6px; }
            .page-header { padding: 12px 14px; }
            .page-header h1 { font-size: 18px; }
            .stats-grid { grid-template-columns: 1fr; gap: 8px; }
            .stat-card { padding: 10px 12px; gap: 8px; }
            .stat-card .stat-icon { width: 32px; height: 32px; font-size: 14px; }
            .stat-card .stat-value { font-size: 14px; }
            .stat-card .stat-label { font-size: 9px; }
            .filter-section { padding: 12px; }
            .filter-section .filter-title { font-size: 12px; margin-bottom: 10px; }
            .filter-section .form-control, .filter-section .form-select { height: 36px; font-size: 0.8rem; padding: 6px 10px; }
            .header-actions { flex-direction: column; width: 100%; gap: 6px; }
            .header-actions .btn-medical-primary, .header-actions .btn-back-adaptive {
                width: 100% !important; justify-content: center !important; font-size: 0.75rem !important; min-height: 32px !important; padding: 4px 10px !important;
            }
            .header-actions .btn-medical-primary span { display: none !important; }
            .header-actions .btn-back-adaptive .btn-label { display: none !important; }
            .btn-back-adaptive { border-radius: var(--medical-radius-sm) !important; width: 100% !important; height: 32px !important; padding: 4px 10px !important; }
            .btn-medical-primary, .btn-medical-secondary, .btn-medical-danger, .btn-medical-outline {
                padding: 4px 8px !important; font-size: 0.65rem !important; min-height: 28px !important; gap: 3px !important;
            }
            .btn-medical-primary i, .btn-medical-secondary i, .btn-medical-danger i, .btn-medical-outline i { font-size: 0.75rem !important; }
            .btn-sm-medical { padding: 3px 6px !important; font-size: 0.6rem !important; min-height: 24px !important; }
            .btn-action { width: 24px !important; height: 24px !important; font-size: 10px !important; border-radius: 4px !important; }
            .modal-footer .btn-medical-primary, .modal-footer .btn-medical-secondary, .modal-footer .btn-medical-danger, .modal-footer .btn-medical-outline {
                padding: 4px 8px !important; font-size: 0.6rem !important; min-height: 26px !important;
            }
            .modal-body { padding: 12px; }
            .modal-footer { padding: 12px 16px; }
            .card-modern .card-header { padding: 10px 12px; }
            .card-modern .card-header h5 { font-size: 12px; }
            .table-dashboard { font-size: 0.65rem; min-width: 480px; }
            .table-dashboard thead th, .table-dashboard tbody td { padding: 6px 8px; }
            .facture-detail-grid { grid-template-columns: 1fr; }
        }

        @media (max-width: 400px) {
            .page-header h1 { font-size: 16px; }
            .stats-grid { gap: 6px; }
            .stat-card { padding: 8px 10px; gap: 6px; }
            .stat-card .stat-icon { width: 28px; height: 28px; font-size: 12px; }
            .stat-card .stat-value { font-size: 13px; }
            .btn-medical-primary, .btn-medical-secondary, .btn-medical-danger, .btn-medical-outline {
                padding: 3px 6px !important; font-size: 0.55rem !important; min-height: 24px !important;
            }
            .btn-action { width: 20px !important; height: 20px !important; font-size: 8px !important; }
        }
    </style>
</head>
<body>

<div class="app-container">

    <!-- ============================================================ -->
    <!-- HEADER                                                       -->
    <!-- ============================================================ -->
    <header class="page-header">
        <div class="header-left">
            <div class="medical-badge">
                <i class="bi bi-heart-pulse-fill"></i> Epencia SGI
            </div>
            <h1>Gestion des <span class="highlight">clients</span></h1>
            <div class="subtitle">
                <i class="bi bi-people"></i>
                Gérez les clients et leurs informations
                <span class="dot"></span>
                <i class="bi bi-person-circle"></i> <?php echo htmlspecialchars($user_nom); ?>
                <span class="dot"></span>
                <?php echo getRoleBadge($user_role); ?>
            </div>
        </div>
        <div class="header-actions">
            <button class="btn-medical-primary" data-bs-toggle="modal" data-bs-target="#addModal">
                <i class="bi bi-plus-circle"></i> <span>Nouveau client</span>
            </button>
        </div>
    </header>

    <!-- ============================================================ -->
    <!-- ID STRUCTURE                                                 -->
    <!-- ============================================================ -->
    <div class="id-structure fade-in">
        <strong>Structure de l'ID :</strong>
        <span class="prefix">CLT_EPENCIA_</span><span class="suffix">******</span>
        <br>
        <span class="subtitle">
            Les <span class="highlight">6 chiffres</span> sont uniques et aléatoires (100000-999999)
            <br>
            Le QR Code contient l'ID complet : <strong>CLT_EPENCIA_******</strong>
        </span>
    </div>

    <!-- ============================================================ -->
    <!-- MESSAGES                                                     -->
    <!-- ============================================================ -->
    <?php if ($message): ?>
        <div class="alert-medical alert-success alert-dismissible fade show">
            <i class="bi bi-check-circle-fill me-2"></i> <?= htmlspecialchars($message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert-medical alert-danger alert-dismissible fade show">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- ============================================================ -->
    <!-- QR CODE SUCCESS                                              -->
    <!-- ============================================================ -->
    <?php if ($successId && $last6Digits): ?>
        <div class="qr-display fade-in">
            <div style="font-weight:700;color:var(--medical-teal);font-size:18px;margin-bottom:10px;">
                <i class="bi bi-qr-code me-2"></i>QR Code du Client
            </div>
            <div style="font-family:monospace;font-size:14px;color:var(--medical-blue);">
                ID: <?= htmlspecialchars($successId) ?>
            </div>
            <div class="qr-code-only"><?= htmlspecialchars($successId) ?></div>
            <div style="font-size:12px;color:var(--medical-text-muted);margin-bottom:10px;">
                <i class="bi bi-info-circle me-1"></i>L'ID complet du client
            </div>
            <?php if ($qrFile && file_exists('../../' . $qrFile)): ?>
                <div class="mt-2">
                    <img src="../../<?= htmlspecialchars($qrFile) ?>" alt="QR Code" style="max-width:180px;border:1px solid var(--medical-border);padding:10px;background:white;border-radius:var(--medical-radius-sm);">
                </div>
            <?php endif; ?>
            <div class="mt-3 d-flex flex-wrap justify-content-center gap-2">
                <button class="btn-medical-primary btn-sm-medical" onclick="alert('Fonctionnalité de téléchargement du QR Code à implémenter')">
                    <i class="bi bi-download"></i> Télécharger
                </button>
                <button class="btn-medical-secondary btn-sm-medical" onclick="alert('Fonctionnalité d\'impression du QR Code à implémenter')">
                    <i class="bi bi-printer"></i> Imprimer
                </button>
            </div>
        </div>
    <?php endif; ?>

    <!-- ============================================================ -->
    <!-- STATISTIQUES                                                 -->
    <!-- ============================================================ -->
    <div class="stats-grid">
        <div class="stat-card fade-in fade-in-d1">
            <div class="stat-icon blue"><i class="bi bi-people"></i></div>
            <div class="stat-content">
                <div class="stat-value"><?= number_format($totalClients) ?></div>
                <div class="stat-label">Total clients</div>
            </div>
        </div>
        <div class="stat-card fade-in fade-in-d2">
            <div class="stat-icon teal"><i class="bi bi-check-circle"></i></div>
            <div class="stat-content">
                <div class="stat-value"><?= number_format(count($actifs)) ?></div>
                <div class="stat-label">Clients actifs</div>
            </div>
        </div>
        <div class="stat-card fade-in fade-in-d3">
            <div class="stat-icon warning"><i class="bi bi-x-circle"></i></div>
            <div class="stat-content">
                <div class="stat-value"><?= number_format(count($inactifs)) ?></div>
                <div class="stat-label">Clients inactifs</div>
            </div>
        </div>
        <div class="stat-card fade-in fade-in-d4">
            <div class="stat-icon info"><i class="bi bi-qr-code"></i></div>
            <div class="stat-content">
                <div class="stat-value"><?= number_format(count($avecQr)) ?></div>
                <div class="stat-label">Avec QR Code</div>
            </div>
        </div>
    </div>

    <!-- ============================================================ -->
    <!-- FILTRES                                                      -->
    <!-- ============================================================ -->
    <form method="POST" action="" id="filterForm" class="filter-section fade-in">
        <div class="filter-title"><i class="bi bi-funnel"></i> Filtres</div>
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label">Recherche</label>
                <input type="text" class="form-control" name="search" value="<?= htmlspecialchars($filters['search'] ?? '') ?>" placeholder="ID, nom, téléphone, email...">
            </div>
            <div class="col-md-3">
                <label class="form-label">Statut</label>
                <select name="statut" class="form-select">
                    <option value="">Tous</option>
                    <option value="actif" <?= ($filters['statut'] ?? '') == 'actif' ? 'selected' : '' ?>>Actif</option>
                    <option value="inactif" <?= ($filters['statut'] ?? '') == 'inactif' ? 'selected' : '' ?>>Inactif</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Sexe</label>
                <select name="sexe" class="form-select">
                    <option value="">Tous</option>
                    <option value="masculin" <?= ($filters['sexe'] ?? '') == 'masculin' ? 'selected' : '' ?>>Masculin</option>
                    <option value="feminin" <?= ($filters['sexe'] ?? '') == 'feminin' ? 'selected' : '' ?>>Féminin</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Groupe sanguin</label>
                <select name="groupe_sanguin" class="form-select">
                    <option value="">Tous</option>
                    <?php $groups = ['AB+', 'AB-', 'A+', 'A-', 'B+', 'B-', 'O+', 'O-']; ?>
                    <?php foreach ($groups as $group): ?>
                        <option value="<?= $group ?>" <?= ($filters['groupe_sanguin'] ?? '') == $group ? 'selected' : '' ?>><?= $group ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12">
                <div class="d-flex gap-2 flex-wrap">
                    <button type="submit" name="apply_filters" class="btn-medical-primary btn-sm-medical">
                        <i class="bi bi-search"></i> Filtrer
                    </button>
                    <button type="submit" name="reset_filters" class="btn-medical-outline btn-sm-medical">
                        <i class="bi bi-arrow-repeat"></i> Réinitialiser
                    </button>
                </div>
            </div>
        </div>
    </form>

    <!-- ============================================================ -->
    <!-- LISTE DES CLIENTS                                           -->
    <!-- ============================================================ -->
    <div class="card-modern fade-in">
        <div class="card-header">
            <h5>
                <span class="header-icon blue"><i class="bi bi-list-ul"></i></span>
                Liste des clients
                <span class="badge bg-secondary ms-2"><?= number_format($totalClients) ?></span>
            </h5>
        </div>
        <div class="card-body p-0">
            <div class="table-wrapper">
                <table class="table table-dashboard">
                    <thead>
                        <tr>
                            <th>ID Client</th>
                            <th style="text-align:center;">#</th>
                            <th>Nom & Prénom</th>
                            <th>Téléphone</th>
                            <th>Email</th>
                            <th>Solde</th>
                            <th>Statut</th>
                            <th class="text-center" style="width:180px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($clients)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-5" style="color:var(--medical-text-muted);">
                                    <i class="bi bi-inbox" style="font-size:3rem;display:block;margin-bottom:12px;opacity:0.3;"></i>
                                    Aucun client trouvé
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($clients as $client): 
                                $last6 = substr($client['client_id'], -6);
                                $hasQr = file_exists(__DIR__ . '/../../qrcodes/qr_' . $client['client_id'] . '.png');
                                $initials = '';
                                if (!empty($client['nom_prenom'])) {
                                    $parts = explode(' ', $client['nom_prenom']);
                                    foreach ($parts as $part) {
                                        $initials .= strtoupper(substr($part, 0, 1));
                                    }
                                    $initials = substr($initials, 0, 2);
                                } else {
                                    $initials = '?';
                                }
                            ?>
                                <tr>
                                    <td>
                                        <code style="font-size:clamp(11px,0.75vw,13px);color:var(--medical-blue);font-weight:600;background:var(--medical-blue-light);padding:2px 8px;border-radius:4px;">
                                            <?= htmlspecialchars($client['client_id']) ?>
                                        </code>
                                    </td>
                                    <td style="font-family:monospace;font-size:20px;color:var(--danger);font-weight:700;text-align:center;letter-spacing:2px;">
                                        <?= htmlspecialchars($last6) ?>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="avatar"><?= $initials ?></span>
                                            <span><?= htmlspecialchars($client['nom_prenom']) ?></span>
                                        </div>
                                    </td>
                                    <td><?= htmlspecialchars($client['telephone'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($client['email'] ?? '-') ?></td>
                                    <td class="fw-bold" style="color:var(--medical-teal);"><?= number_format($client['solde'] ?? 0, 0, ',', ' ') ?> F</td>
                                    <td><?= getStatusBadge($client['statut'] ?? 'actif') ?></td>
                                    <td>
                                        <div class="actions-group">
                                            <button type="button" class="btn-action facture" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#voirFacturesModal"
                                                    data-client-id="<?= htmlspecialchars($client['client_id']) ?>"
                                                    data-client-nom="<?= htmlspecialchars($client['nom_prenom']) ?>"
                                                    title="Voir les factures">
                                                <i class="bi bi-receipt"></i>
                                            </button>
                                            <button type="button" class="btn-action edit" data-bs-toggle="modal" data-bs-target="#editModal" 
                                                data-id="<?= htmlspecialchars($client['client_id']) ?>" title="Modifier">
                                                <i class="bi bi-pencil-square"></i>
                                            </button>
                                            <button type="button" class="btn-action delete" data-bs-toggle="modal" data-bs-target="#deleteModal" 
                                                data-id="<?= htmlspecialchars($client['client_id']) ?>" 
                                                data-nom="<?= htmlspecialchars($client['nom_prenom']) ?>" 
                                                title="Supprimer">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                            <?php if ($hasQr): ?>
                                                <a href="../../qrcodes/qr_<?= urlencode($client['client_id']) ?>.png" download 
                                                   class="btn-action qr" title="QR Code">
                                                    <i class="bi bi-qr-code"></i>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<!-- ============================================================ -->
<!-- MODAL AJOUTER -->
<!-- ============================================================ -->
<div class="modal fade" id="addModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header modal-header-medical success">
                <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>Nouveau client</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <div class="alert-medical alert-info">
                        <i class="bi bi-info-circle me-2"></i>
                        L'ID sera généré automatiquement au format : <strong>CLT_EPENCIA_</strong><span style="color:var(--danger);">******</span>
                        <br>
                        <small>Le solde initial est de <strong>0 FCFA</strong></small>
                    </div>

                    <div class="section-title"><i class="bi bi-person"></i> Identité</div>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label">Nom et Prénom <span class="text-danger">*</span></label>
                            <input type="text" name="nom_prenom" class="form-control" required placeholder="Ex: Jean DUPONT">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Date de naissance</label>
                            <input type="date" name="date_naissance" class="form-control">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Sexe</label>
                            <select name="sexe" class="form-select">
                                <option value="">Sélectionner</option>
                                <option value="masculin">Masculin</option>
                                <option value="feminin">Féminin</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Lieu de naissance</label>
                            <input type="text" name="lieu_naissance" class="form-control" placeholder="Ex: Abidjan">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Nationalité</label>
                            <input type="text" name="nationalite" class="form-control" placeholder="Ex: Ivoirienne">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Groupe sanguin</label>
                            <select name="groupe_sanguin" class="form-select">
                                <option value="">Sélectionner</option>
                                <?php $groups = ['AB+', 'AB-', 'A+', 'A-', 'B+', 'B-', 'O+', 'O-']; ?>
                                <?php foreach ($groups as $group): ?>
                                    <option value="<?= $group ?>"><?= $group ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="section-title"><i class="bi bi-telephone"></i> Contact</div>
                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <label class="form-label">Téléphone <span class="text-danger">*</span></label>
                            <input type="text" name="telephone" class="form-control" required placeholder="+225 XX XX XX XX">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" placeholder="exemple@email.com">
                        </div>
                        <div class="col-md-4">
                            <div class="solde-display">
                                <span class="solde-label"><i class="bi bi-wallet2 me-1"></i>Solde</span>
                                <span class="solde-value">0 FCFA</span>
                            </div>
                            <small class="text-muted">Le solde est initialisé à 0 FCFA à la création</small>
                        </div>
                    </div>

                    <div class="section-title"><i class="bi bi-geo-alt"></i> Localisation</div>
                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <label class="form-label">Pays</label>
                            <input type="text" name="pays" class="form-control" placeholder="Côte d'Ivoire">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Ville</label>
                            <input type="text" name="ville" class="form-control" placeholder="Abidjan">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Adresse</label>
                            <input type="text" name="adresse" class="form-control" placeholder="Adresse complète">
                        </div>
                    </div>

                    <div class="section-title"><i class="bi bi-briefcase"></i> Profession</div>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label">Profession</label>
                            <input type="text" name="profession" class="form-control" placeholder="Ex: Médecin, Ingénieur...">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Statut</label>
                            <select name="statut" class="form-select">
                                <option value="actif" selected>Actif</option>
                                <option value="inactif">Inactif</option>
                            </select>
                        </div>
                    </div>

                    <div class="section-title"><i class="bi bi-exclamation-triangle"></i> Contact d'urgence</div>
                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <label class="form-label">Nom</label>
                            <input type="text" name="nom_prenom_urgence" class="form-control" placeholder="Nom complet">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Téléphone</label>
                            <input type="text" name="telephone_urgence" class="form-control" placeholder="+225 XX XX XX XX">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Email</label>
                            <input type="email" name="email_urgence" class="form-control" placeholder="urgence@email.com">
                        </div>
                    </div>

                    <div class="section-title"><i class="bi bi-image"></i> Photo et description</div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Photo</label>
                            <input type="file" name="photo" class="form-control" accept="image/*">
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="2" placeholder="Informations complémentaires..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-medical-outline" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" name="submit" class="btn-medical-primary">
                        <i class="bi bi-check-circle me-1"></i>Ajouter
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ============================================================ -->
<!-- MODAL MODIFIER -->
<!-- ============================================================ -->
<div class="modal fade" id="editModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header modal-header-medical">
                <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i>Modifier le client</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" enctype="multipart/form-data" id="editForm">
                <div class="modal-body" id="editModalBody">
                    <div class="text-center py-4">
                        <div class="loading-spinner"></div>
                        <p class="mt-2 text-muted">Chargement du client...</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-medical-outline" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" name="submit" class="btn-medical-primary" id="editSubmitBtn" style="display:none;">
                        <i class="bi bi-check-circle me-1"></i>Modifier
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ============================================================ -->
<!-- MODAL SUPPRIMER -->
<!-- ============================================================ -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header modal-header-medical danger">
                <h5 class="modal-title"><i class="bi bi-exclamation-triangle-fill me-2"></i>Confirmer la suppression</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="delete" id="delete_id">
                    
                    <p style="font-weight:500;color:var(--medical-text);">Voulez-vous vraiment supprimer ce client ?</p>
                    
                    <div style="background:var(--medical-gray-light);padding:14px 18px;border-radius:var(--medical-radius-sm);border-left:4px solid var(--danger);margin-top:12px;">
                        <div style="display:flex;align-items:center;gap:12px;margin-bottom:6px;">
                            <span style="font-weight:600;color:var(--medical-text);">Client :</span>
                            <span style="font-weight:500;" id="delete_nom"></span>
                            <span style="color:var(--medical-text-muted);">|</span>
                            <span style="font-weight:600;color:var(--medical-text);">ID :</span>
                            <code style="font-size:13px;color:var(--medical-blue);background:var(--medical-blue-light);padding:2px 8px;border-radius:4px;" id="delete_id_display"></code>
                        </div>
                    </div>
                    
                    <div class="alert-medical alert-info mt-3" style="font-size:12px;text-align:left;">
                        <i class="bi bi-exclamation-triangle me-1"></i>
                        <strong>Attention :</strong> Cette action est irréversible.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-medical-outline" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn-medical-danger">
                        <i class="bi bi-trash me-1"></i>Supprimer
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ============================================================ -->
<!-- MODAL VOIR FACTURES -->
<!-- ============================================================ -->
<div class="modal fade" id="voirFacturesModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header modal-header-medical">
                <h5 class="modal-title">
                    <i class="bi bi-receipt me-2"></i>
                    Factures de <span id="clientNomFactures"></span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="facturesBody">
                <div class="text-center py-4">
                    <div class="loading-spinner"></div>
                    <p class="mt-2 text-muted">Chargement des factures...</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-medical-outline" data-bs-dismiss="modal">Fermer</button>
            </div>
        </div>
    </div>
</div>

<!-- ============================================================ -->
<!-- MODAL VOIR DÉTAIL FACTURE -->
<!-- ============================================================ -->
<div class="modal fade" id="voirDetailFactureModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header modal-header-medical">
                <h5 class="modal-title">
                    <i class="bi bi-file-text me-2"></i>
                    Détail de la facture
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="detailFactureBody">
                <div class="text-center py-4">
                    <div class="loading-spinner"></div>
                    <p class="mt-2 text-muted">Chargement des détails...</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-medical-outline" data-bs-dismiss="modal">Fermer</button>
            </div>
        </div>
    </div>
</div>

<!-- ============================================================ -->
<!-- SCRIPTS -->
<!-- ============================================================ -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
$(document).ready(function() {
    // ============================================================
    // CHARGEMENT POUR MODIFICATION
    // ============================================================
    $('#editModal').on('show.bs.modal', function(event) {
        const button = $(event.relatedTarget);
        const id = button.data('id');
        const modal = $(this);
        const body = modal.find('#editModalBody');
        const submitBtn = modal.find('#editSubmitBtn');
        
        body.html(`
            <div class="text-center py-4">
                <div class="loading-spinner"></div>
                <p class="mt-2 text-muted">Chargement du client...</p>
            </div>
        `);
        submitBtn.hide();
        
        $.ajax({
            url: window.location.href,
            method: 'POST',
            data: { 
                ajax_action: 'load_client', 
                id: id 
            },
            dataType: 'json',
            success: function(data) {
                if (data.success) {
                    const c = data.data;
                    let html = `
                        <input type="hidden" name="client_id" value="${escapeHtml(c.client_id || '')}">
                        
                        <div class="alert-medical alert-info">
                            <i class="bi bi-info-circle me-2"></i>
                            Modification du client : <strong>${escapeHtml(c.client_id || '')}</strong>
                        </div>

                        <div class="section-title"><i class="bi bi-person"></i> Identité</div>
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="form-label">Nom et Prénom <span class="text-danger">*</span></label>
                                <input type="text" name="nom_prenom" class="form-control" value="${escapeHtml(c.nom_prenom || '')}" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Date de naissance</label>
                                <input type="date" name="date_naissance" class="form-control" value="${c.date_naissance || ''}">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Sexe</label>
                                <select name="sexe" class="form-select">
                                    <option value="">Sélectionner</option>
                                    <option value="masculin" ${c.sexe === 'masculin' ? 'selected' : ''}>Masculin</option>
                                    <option value="feminin" ${c.sexe === 'feminin' ? 'selected' : ''}>Féminin</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Lieu de naissance</label>
                                <input type="text" name="lieu_naissance" class="form-control" value="${escapeHtml(c.lieu_naissance || '')}">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Nationalité</label>
                                <input type="text" name="nationalite" class="form-control" value="${escapeHtml(c.nationalite || '')}">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Groupe sanguin</label>
                                <select name="groupe_sanguin" class="form-select">
                                    <option value="">Sélectionner</option>
                                    <?php $groups = ['AB+', 'AB-', 'A+', 'A-', 'B+', 'B-', 'O+', 'O-']; ?>
                                    <?php foreach ($groups as $group): ?>
                                        <option value="<?= $group ?>" ${c.groupe_sanguin === '<?= $group ?>' ? 'selected' : ''}>${escapeHtml('<?= $group ?>')}</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="section-title"><i class="bi bi-telephone"></i> Contact</div>
                        <div class="row g-3 mb-4">
                            <div class="col-md-4">
                                <label class="form-label">Téléphone <span class="text-danger">*</span></label>
                                <input type="text" name="telephone" class="form-control" value="${escapeHtml(c.telephone || '')}" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Email</label>
                                <input type="email" name="email" class="form-control" value="${escapeHtml(c.email || '')}">
                            </div>
                            <div class="col-md-4">
                                <div class="solde-display">
                                    <span class="solde-label"><i class="bi bi-wallet2 me-1"></i>Solde</span>
                                    <span class="solde-value">${Number(c.solde || 0).toLocaleString('fr-FR')} FCFA</span>
                                </div>
                                <small class="text-muted">Le solde est mis à jour automatiquement par les transactions</small>
                            </div>
                        </div>

                        <div class="section-title"><i class="bi bi-geo-alt"></i> Localisation</div>
                        <div class="row g-3 mb-4">
                            <div class="col-md-4">
                                <label class="form-label">Pays</label>
                                <input type="text" name="pays" class="form-control" value="${escapeHtml(c.pays || '')}">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Ville</label>
                                <input type="text" name="ville" class="form-control" value="${escapeHtml(c.ville || '')}">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Adresse</label>
                                <input type="text" name="adresse" class="form-control" value="${escapeHtml(c.adresse || '')}">
                            </div>
                        </div>

                        <div class="section-title"><i class="bi bi-briefcase"></i> Profession</div>
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="form-label">Profession</label>
                                <input type="text" name="profession" class="form-control" value="${escapeHtml(c.profession || '')}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Statut</label>
                                <select name="statut" class="form-select">
                                    <option value="actif" ${c.statut === 'actif' ? 'selected' : ''}>Actif</option>
                                    <option value="inactif" ${c.statut === 'inactif' ? 'selected' : ''}>Inactif</option>
                                </select>
                            </div>
                        </div>

                        <div class="section-title"><i class="bi bi-exclamation-triangle"></i> Contact d'urgence</div>
                        <div class="row g-3 mb-4">
                            <div class="col-md-4">
                                <label class="form-label">Nom</label>
                                <input type="text" name="nom_prenom_urgence" class="form-control" value="${escapeHtml(c.nom_prenom_urgence || '')}">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Téléphone</label>
                                <input type="text" name="telephone_urgence" class="form-control" value="${escapeHtml(c.telephone_urgence || '')}">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Email</label>
                                <input type="email" name="email_urgence" class="form-control" value="${escapeHtml(c.email_urgence || '')}">
                            </div>
                        </div>

                        <div class="section-title"><i class="bi bi-image"></i> Photo et description</div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Photo</label>
                                <input type="file" name="photo" class="form-control" accept="image/*">
                                ${c.has_photo ? '<small class="text-muted">Photo actuelle conservée</small>' : ''}
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">Description</label>
                                <textarea name="description" class="form-control" rows="2">${escapeHtml(c.description || '')}</textarea>
                            </div>
                        </div>
                    `;
                    
                    body.html(html);
                    submitBtn.show();
                } else {
                    body.html(`
                        <div class="alert-medical alert-danger">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            ${escapeHtml(data.message || 'Erreur lors du chargement du client.')}
                        </div>
                    `);
                }
            },
            error: function(xhr, status, error) {
                body.html(`
                    <div class="alert-medical alert-danger">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        Erreur de connexion : ${escapeHtml(error)}
                    </div>
                `);
            }
        });
    });

    // ============================================================
    // SUPPRESSION
    // ============================================================
    $('#deleteModal').on('show.bs.modal', function(e) {
        const button = $(e.relatedTarget);
        const id = button.data('id');
        const nom = button.data('nom');
        
        $('#delete_id').val(id);
        $('#delete_id_display').text(id);
        $('#delete_nom').text(nom);
    });

    // ============================================================
    // VOIR FACTURES DU CLIENT
    // ============================================================
    $('#voirFacturesModal').on('show.bs.modal', function(event) {
        const button = $(event.relatedTarget);
        const clientId = button.data('client-id');
        const clientNom = button.data('client-nom');
        const body = $('#facturesBody');
        
        $('#clientNomFactures').text(clientNom);
        
        body.html(`
            <div class="text-center py-4">
                <div class="loading-spinner"></div>
                <p class="mt-2 text-muted">Chargement des factures...</p>
            </div>
        `);
        
        $.ajax({
            url: window.location.href,
            method: 'POST',
            data: { 
                ajax_action: 'get_factures', 
                client_id: clientId 
            },
            dataType: 'json',
            success: function(data) {
                if (data.success && data.factures && data.factures.length > 0) {
                    let html = `
                        <div class="table-responsive">
                            <table class="table table-dashboard">
                                <thead>
                                    <tr>
                                        <th>ID Facture</th>
                                        <th>Date</th>
                                        <th>Montant</th>
                                        <th class="text-center">Statut</th>
                                        <th class="text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                    `;
                    
                    data.factures.forEach(function(f) {
                        const montant = f.montant || 0;
                        html += `
                            <tr>
                                <td><code style="font-size:0.8rem;color:var(--medical-blue);">${escapeHtml(f.facture_id)}</code></td>
                                <td>${f.date_creation ? new Date(f.date_creation).toLocaleDateString('fr-FR') : '-'}</td>
                                <td class="text-end fw-bold" style="color:var(--medical-teal);">${Number(montant).toLocaleString('fr-FR')} FCFA</td>
                                <td class="text-center">${getStatusBadge(f.statut)}</td>
                                <td class="text-center">
                                    <button class="btn-action facture" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#voirDetailFactureModal"
                                            data-facture-id="${escapeHtml(f.facture_id)}"
                                            title="Voir les détails">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </td>
                            </tr>
                        `;
                    });
                    
                    html += `
                                </tbody>
                            </table>
                        </div>
                    `;
                    
                    body.html(html);
                } else {
                    body.html(`
                        <div class="text-center py-4">
                            <i class="bi bi-receipt" style="font-size:3rem;color:var(--medical-text-muted);"></i>
                            <p class="text-muted mt-2">Aucune facture trouvée pour ce client</p>
                        </div>
                    `);
                }
            },
            error: function(xhr, status, error) {
                body.html(`
                    <div class="alert-medical alert-danger">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        Erreur de connexion : ${escapeHtml(error)}
                    </div>
                `);
            }
        });
    });

    // ============================================================
    // VOIR DÉTAIL FACTURE
    // ============================================================
    $('#voirDetailFactureModal').on('show.bs.modal', function(event) {
        const button = $(event.relatedTarget);
        const factureId = button.data('facture-id');
        const body = $('#detailFactureBody');
        
        body.html(`
            <div class="text-center py-4">
                <div class="loading-spinner"></div>
                <p class="mt-2 text-muted">Chargement des détails...</p>
            </div>
        `);
        
        $.ajax({
            url: window.location.href,
            method: 'POST',
            data: { 
                ajax_action: 'get_facture_detail', 
                facture_id: factureId 
            },
            dataType: 'json',
            success: function(data) {
                if (data.success && data.facture) {
                    const f = data.facture;
                    
                    const statusClassMap = {
                        'payé': 'green',
                        'payee': 'green',
                        'impayé': 'danger',
                        'impayee': 'danger',
                        'en attente': 'warning',
                        'en_attente': 'warning',
                        'annulé': 'secondary',
                        'annulee': 'secondary',
                        'partiel': 'info',
                        'partielle': 'info'
                    };
                    const statusColor = statusClassMap[f.statut] || 'secondary';
                    
                    body.html(`
                        <div class="facture-detail-grid">
                            <div class="facture-detail-item">
                                <div class="lbl">ID Facture</div>
                                <div class="val" style="font-family:monospace;">${escapeHtml(f.facture_id)}</div>
                            </div>
                            <div class="facture-detail-item ${statusColor}">
                                <div class="lbl">Statut</div>
                                <div class="val">${escapeHtml(f.statut || 'En attente')}</div>
                            </div>
                            <div class="facture-detail-item">
                                <div class="lbl">Date de création</div>
                                <div class="val">${f.date_creation ? new Date(f.date_creation).toLocaleDateString('fr-FR') : '-'}</div>
                            </div>
                            <div class="facture-detail-item">
                                <div class="lbl">Date facture</div>
                                <div class="val">${f.date_facture ? new Date(f.date_facture).toLocaleDateString('fr-FR') : '-'}</div>
                            </div>
                            <div class="facture-detail-item green">
                                <div class="lbl">Montant</div>
                                <div class="val">${Number(f.montant || 0).toLocaleString('fr-FR')} FCFA</div>
                            </div>
                            <div class="facture-detail-item">
                                <div class="lbl">Client</div>
                                <div class="val">${escapeHtml(f.client_nom || '-')}</div>
                            </div>
                            ${f.description ? `
                            <div class="facture-detail-item" style="grid-column:1/-1;">
                                <div class="lbl">Description</div>
                                <div class="val">${escapeHtml(f.description)}</div>
                            </div>` : ''}
                        </div>
                        <div class="mt-3">
                            <small class="text-muted">
                                <i class="bi bi-info-circle me-1"></i>
                                Facture créée le ${f.date_creation ? new Date(f.date_creation).toLocaleDateString('fr-FR') : '-'}
                            </small>
                        </div>
                    `);
                } else {
                    body.html(`
                        <div class="alert-medical alert-danger">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            ${escapeHtml(data.message || 'Erreur lors du chargement')}
                        </div>
                    `);
                }
            },
            error: function(xhr, status, error) {
                body.html(`
                    <div class="alert-medical alert-danger">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        Erreur de connexion : ${escapeHtml(error)}
                    </div>
                `);
            }
        });
    });
});

function escapeHtml(t) {
    const d = document.createElement('div');
    d.textContent = t;
    return d.innerHTML;
}

function getStatusBadge(status) {
    const statusMap = {
        'payé': 'success',
        'payee': 'success',
        'impayé': 'danger',
        'impayee': 'danger',
        'en attente': 'warning',
        'en_attente': 'warning',
        'annulé': 'secondary',
        'annulee': 'secondary',
        'partiel': 'info',
        'partielle': 'info'
    };
    const labelMap = {
        'payé': 'Payé',
        'payee': 'Payé',
        'impayé': 'Impayé',
        'impayee': 'Impayé',
        'en attente': 'En attente',
        'en_attente': 'En attente',
        'annulé': 'Annulé',
        'annulee': 'Annulé',
        'partiel': 'Partiel',
        'partielle': 'Partiel'
    };
    const cls = statusMap[status] || 'secondary';
    const label = labelMap[status] || status;
    return `<span class="badge bg-${cls}">${label}</span>";
}
</script>

</body>
</html>