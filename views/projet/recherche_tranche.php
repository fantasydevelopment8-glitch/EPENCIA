<?php
// ============================================================
// ASSOCIATIONS PROJET/TRANCHE D'ÂGE - EPENCIA SGI
// ============================================================

require_once 'database/database.php';

// ============================================================
// VÉRIFICATION AUTHENTIFICATION
// ============================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['utilisateur_id'])) {
    header('Location: ../utilisateur/connexion.php');
    exit();
}

$user_role = $_SESSION['role'] ?? '';
$user_nom = $_SESSION['nom_prenom'] ?? 'Utilisateur';
$is_admin = in_array($user_role, ['Administrateur', 'Superviseur']);

if (!$is_admin) {
    header('Location: ../utilisateur/dashboard.php?error=Accès non autorisé');
    exit();
}

// ============================================================
// HANDLERS AJAX
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');
    
    if ($_POST['ajax_action'] === 'load_affectation') {
        $id = $_POST['id'];
        $stmt = $pdo->prepare("SELECT pt.*, p.titre_projet, 
                                      t.titre_debut, t.titre_fin, t.age_debut, t.age_fin 
                               FROM projet_tranche pt 
                               LEFT JOIN projet p ON pt.id_projet = p.id_projet 
                               LEFT JOIN tranche_age t ON pt.id_tranche = t.id_tranche 
                               WHERE pt.id = ?");
        $stmt->execute([$id]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'data' => $data]);
        exit;
    }
}

// ============================================================
// TRAITEMENT DES ACTIONS CRUD
// ============================================================
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // === AJOUTER ===
        if (isset($_POST['btn_ajouter'])) {
            $id_projet = trim($_POST['sai_id_projet']);
            $id_tranche = trim($_POST['sai_id_tranche']);
            
            if (empty($id_projet)) throw new Exception('Le projet est obligatoire.');
            if (empty($id_tranche)) throw new Exception('La tranche d\'âge est obligatoire.');
            
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM projet_tranche WHERE id_projet = ? AND id_tranche = ?');
            $stmt->execute([$id_projet, $id_tranche]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception('Cette association projet/tranche existe déjà.');
            }
            
            $req = $pdo->prepare("INSERT INTO projet_tranche (id_projet, id_tranche) VALUES (?, ?)");
            $req->execute([$id_projet, $id_tranche]);
            
            $message = "Association projet/tranche d'âge créée avec succès !";
        }
        
        // === MODIFIER ===
        if (isset($_POST['btn_modifier'])) {
            $id = $_POST['sai_id'];
            $id_projet = trim($_POST['sai_id_projet']);
            $id_tranche = trim($_POST['sai_id_tranche']);
            
            if (empty($id) || empty($id_projet) || empty($id_tranche)) {
                throw new Exception('Les champs obligatoires doivent être remplis.');
            }
            
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM projet_tranche WHERE id_projet = ? AND id_tranche = ? AND id != ?');
            $stmt->execute([$id_projet, $id_tranche, $id]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception('Cette association projet/tranche existe déjà.');
            }
            
            $req = $pdo->prepare("UPDATE projet_tranche SET id_projet = ?, id_tranche = ? WHERE id = ?");
            $req->execute([$id_projet, $id_tranche, $id]);
            
            $message = 'Association projet/tranche d\'âge modifiée avec succès.';
        }
        
        // === SUPPRIMER ===
        if (isset($_POST['btn_supprimer'])) {
            $id = $_POST['sai_id'];
            
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM donnee WHERE id_projet = (SELECT id_projet FROM projet_tranche WHERE id = ?) AND id_tranche = (SELECT id_tranche FROM projet_tranche WHERE id = ?)');
            $stmt->execute([$id, $id]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception("Cette association est utilisée dans des données.");
            }
            
            $req = $pdo->prepare("DELETE FROM projet_tranche WHERE id = ?");
            $req->execute([$id]);
            
            $message = 'Association projet/tranche d\'âge supprimée avec succès.';
        }

        // === RÉINITIALISER LES FILTRES ===
        if (isset($_POST['reset_filters'])) {
            unset($_SESSION['projet_tranche_filters']);
        }
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// ============================================================
// GESTION DES FILTRES ET PAGINATION
// ============================================================
$limit = 10;
$page = max(1, (int)($_POST['page'] ?? $_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;
$search = trim($_POST['search'] ?? $_SESSION['projet_tranche_filters']['search'] ?? '');
$filter_projet = trim($_POST['projet'] ?? $_SESSION['projet_tranche_filters']['projet'] ?? '');
$filter_tranche = trim($_POST['tranche'] ?? $_SESSION['projet_tranche_filters']['tranche'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['apply_filters'])) {
    $_SESSION['projet_tranche_filters'] = [
        'search' => $search,
        'projet' => $filter_projet,
        'tranche' => $filter_tranche
    ];
}

$offset = ($page - 1) * $limit;

// === LISTES POUR LES FILTRES ===
$projets = $pdo->query("SELECT id_projet, titre_projet FROM projet ORDER BY titre_projet")->fetchAll(PDO::FETCH_ASSOC);
$tranches = $pdo->query("SELECT id_tranche, titre_debut, titre_fin, age_debut, age_fin FROM tranche_age ORDER BY age_debut")->fetchAll(PDO::FETCH_ASSOC);

// === COMPTER TOTAL ===
$count_sql = 'SELECT COUNT(*) FROM projet_tranche pt 
              LEFT JOIN projet p ON pt.id_projet = p.id_projet 
              LEFT JOIN tranche_age t ON pt.id_tranche = t.id_tranche';
$count_params = [];
$where_clauses = [];

if (!empty($search)) {
    $where_clauses[] = '(p.titre_projet LIKE ? OR t.titre_debut LIKE ? OR t.titre_fin LIKE ?)';
    $search_term = "%$search%";
    $count_params = array_merge($count_params, [$search_term, $search_term, $search_term]);
}
if (!empty($filter_projet)) {
    $where_clauses[] = 'pt.id_projet = ?';
    $count_params[] = $filter_projet;
}
if (!empty($filter_tranche)) {
    $where_clauses[] = 'pt.id_tranche = ?';
    $count_params[] = $filter_tranche;
}
if (!empty($where_clauses)) {
    $count_sql .= ' WHERE ' . implode(' AND ', $where_clauses);
}

$stmt = $pdo->prepare($count_sql);
$stmt->execute($count_params);
$total_affectations = $stmt->fetchColumn();
$total_pages = max(1, ceil($total_affectations / $limit));

// === RÉCUPÉRER LES AFFECTATIONS ===
$sql = 'SELECT pt.id, pt.id_projet, pt.id_tranche, 
               p.titre_projet, 
               t.titre_debut, t.titre_fin, t.age_debut, t.age_fin 
        FROM projet_tranche pt 
        LEFT JOIN projet p ON pt.id_projet = p.id_projet 
        LEFT JOIN tranche_age t ON pt.id_tranche = t.id_tranche';
$params = [];
$where_clauses = [];

if (!empty($search)) {
    $where_clauses[] = '(p.titre_projet LIKE ? OR t.titre_debut LIKE ? OR t.titre_fin LIKE ?)';
    $search_term = "%$search%";
    $params = array_merge($params, [$search_term, $search_term, $search_term]);
}
if (!empty($filter_projet)) {
    $where_clauses[] = 'pt.id_projet = ?';
    $params[] = $filter_projet;
}
if (!empty($filter_tranche)) {
    $where_clauses[] = 'pt.id_tranche = ?';
    $params[] = $filter_tranche;
}
if (!empty($where_clauses)) {
    $sql .= ' WHERE ' . implode(' AND ', $where_clauses);
}
$sql .= ' ORDER BY p.titre_projet ASC, t.age_debut ASC LIMIT ? OFFSET ?';

$stmt = $pdo->prepare($sql);
$paramIndex = 1;
foreach ($params as $param) {
    $stmt->bindValue($paramIndex++, $param, PDO::PARAM_STR);
}
$stmt->bindValue($paramIndex++, $limit, PDO::PARAM_INT);
$stmt->bindValue($paramIndex++, $offset, PDO::PARAM_INT);
$stmt->execute();
$affectations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// === STATISTIQUES ===
$stmt = $pdo->query("SELECT COUNT(*) FROM projet_tranche");
$total = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(DISTINCT id_projet) FROM projet_tranche");
$total_projets = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(DISTINCT id_tranche) FROM projet_tranche");
$total_tranches = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT p.titre_projet, COUNT(*) as nb 
                     FROM projet_tranche pt 
                     LEFT JOIN projet p ON pt.id_projet = p.id_projet 
                     GROUP BY pt.id_projet 
                     ORDER BY nb DESC LIMIT 1");
$projet_plus_associe = $stmt->fetch(PDO::FETCH_ASSOC);

// ============================================================
// FONCTIONS DE BADGES
// ============================================================
function getStatusBadge($status) {
    $statusMap = [
        'actif' => ['class' => 'success', 'label' => 'Actif'],
        'inactif' => ['class' => 'danger', 'label' => 'Inactif'],
        'en attente' => ['class' => 'warning', 'label' => 'En attente']
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
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Associations Projet/Tranche d'âge - Epencia SGI</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=DM+Serif+Display:ital@0;1&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <style>
        /* ============================================================ */
        /* VARIABLES - DESIGN MÉDICAL */
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

        /* ============================================================ */
        /* RESET & BASE */
        /* ============================================================ */
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
            -moz-osx-font-smoothing: grayscale;
        }

        .app-container {
            max-width: 1920px;
            margin: 0 auto;
        }

        /* ============================================================ */
        /* COMPOSANT: PAGE HEADER */
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
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--medical-blue), var(--medical-teal), var(--medical-blue));
            background-size: 200% 100%;
            animation: gradientMove 4s ease infinite;
        }

        @keyframes gradientMove {
            0%, 100% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
        }

        .page-header .header-left {
            flex: 1 1 auto;
            min-width: 200px;
        }

        .page-header .header-left .medical-badge {
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
            font-family: var(--font-primary);
        }

        .page-header .header-left .medical-badge i {
            font-size: 12px;
        }

        .page-header h1 {
            font-family: var(--font-serif);
            font-size: clamp(22px, 2.8vw, 34px);
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
            bottom: 2px;
            left: 0;
            right: 0;
            height: 3px;
            background: var(--medical-teal);
            border-radius: 2px;
            opacity: 0.4;
        }

        .page-header .subtitle {
            font-family: var(--font-primary);
            color: var(--medical-text-secondary);
            font-size: clamp(12px, 1vw, 14px);
            font-weight: 400;
            margin-top: 2px;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 6px;
        }

        .page-header .subtitle .dot {
            display: inline-block;
            width: 4px;
            height: 4px;
            border-radius: 50%;
            background: var(--medical-text-muted);
            margin: 0 6px;
        }

        .header-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            align-items: center;
            flex-shrink: 0;
            margin-left: auto;
        }

        /* ============================================================ */
        /* COMPOSANT: BOUTONS */
        /* ============================================================ */
        .btn-medical-primary {
            background: var(--medical-blue);
            color: #fff;
            font-family: var(--font-primary);
            font-weight: 700;
            font-size: clamp(0.75rem, 1vw, 0.9rem);
            padding: 9px 18px;
            border-radius: var(--medical-radius-sm);
            border: none;
            transition: var(--medical-transition);
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 16px rgba(26, 107, 138, 0.3);
        }
        .btn-medical-primary:hover {
            background: var(--medical-blue-dark);
            transform: translateY(-2px);
            box-shadow: 0 8px 28px rgba(26, 107, 138, 0.4);
            color: #fff;
        }

        .btn-medical-secondary {
            background: var(--medical-teal);
            color: #fff;
            font-family: var(--font-primary);
            font-weight: 700;
            font-size: clamp(0.75rem, 1vw, 0.9rem);
            padding: 9px 18px;
            border-radius: var(--medical-radius-sm);
            border: none;
            transition: var(--medical-transition);
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 16px rgba(45, 155, 142, 0.3);
        }
        .btn-medical-secondary:hover {
            background: #23837a;
            transform: translateY(-2px);
            box-shadow: 0 8px 28px rgba(45, 155, 142, 0.4);
            color: #fff;
        }

        .btn-medical-danger {
            background: var(--danger);
            color: #fff;
            font-family: var(--font-primary);
            font-weight: 700;
            font-size: clamp(0.75rem, 1vw, 0.9rem);
            padding: 9px 18px;
            border-radius: var(--medical-radius-sm);
            border: none;
            transition: var(--medical-transition);
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-medical-danger:hover {
            background: #a93226;
            transform: translateY(-2px);
            color: #fff;
        }

        .btn-medical-outline {
            background: transparent;
            border: 1.5px solid var(--medical-border);
            color: var(--medical-text-secondary);
            font-family: var(--font-primary);
            font-weight: 600;
            font-size: clamp(0.75rem, 1vw, 0.9rem);
            padding: 9px 18px;
            border-radius: var(--medical-radius-sm);
            transition: var(--medical-transition);
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-medical-outline:hover {
            background: var(--medical-gray-light);
            border-color: var(--medical-blue);
            color: var(--medical-blue);
        }

        /* ============================================================ */
        /* COMPOSANT: STATS CARDS */
        /* ============================================================ */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
            gap: 14px;
            margin-bottom: 24px;
        }

        .stat-card {
            background: var(--medical-white);
            padding: 16px 20px;
            border-radius: var(--medical-radius);
            box-shadow: var(--medical-shadow);
            border: 1px solid var(--medical-border);
            display: flex;
            align-items: center;
            gap: 14px;
            transition: var(--medical-transition);
            font-family: var(--font-primary);
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--medical-shadow-hover);
        }

        .stat-card .stat-icon {
            width: 44px;
            height: 44px;
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
        .stat-icon.danger { background: var(--danger-light); color: var(--danger); }
        .stat-icon.info { background: var(--info-light); color: var(--info); }

        .stat-card .stat-value {
            font-size: clamp(20px, 2vw, 26px);
            font-weight: 800;
            color: var(--medical-text);
            line-height: 1.1;
            font-family: var(--font-primary);
        }

        .stat-card .stat-label {
            font-size: clamp(11px, 0.8vw, 13px);
            color: var(--medical-text-secondary);
            font-weight: 600;
            margin-top: 2px;
            font-family: var(--font-primary);
            letter-spacing: 0.02em;
        }

        .stat-card .stat-sub {
            font-size: clamp(10px, 0.7vw, 12px);
            color: var(--medical-text-muted);
            margin-top: 2px;
            font-weight: 500;
            font-family: var(--font-primary);
        }

        /* ============================================================ */
        /* COMPOSANT: FILTER SECTION */
        /* ============================================================ */
        .filter-section {
            background: var(--medical-white);
            border-radius: var(--medical-radius);
            padding: 18px 22px 22px;
            margin-bottom: 24px;
            box-shadow: var(--medical-shadow);
            border: 1px solid var(--medical-border);
            font-family: var(--font-primary);
        }

        .filter-section .filter-title {
            font-weight: 700;
            color: var(--medical-text-secondary);
            font-size: clamp(13px, 0.9vw, 14px);
            margin-bottom: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
            font-family: var(--font-primary);
            letter-spacing: 0.02em;
        }

        .filter-section .filter-title i {
            color: var(--medical-blue);
        }

        .filter-section .form-control,
        .filter-section .form-select {
            font-family: var(--font-primary);
            background: var(--medical-gray-light);
            border: 1.5px solid var(--medical-border);
            border-radius: var(--medical-radius-sm);
            padding: 9px 14px;
            font-size: clamp(0.8rem, 0.9vw, 0.9rem);
            color: var(--medical-text);
            transition: var(--medical-transition);
            height: 42px;
            font-weight: 500;
        }

        .filter-section .form-control:focus,
        .filter-section .form-select:focus {
            border-color: var(--medical-blue);
            box-shadow: 0 0 0 4px rgba(26, 107, 138, 0.1);
            background: #fff;
        }

        .filter-section .form-label {
            font-family: var(--font-primary);
            font-weight: 700;
            font-size: clamp(10px, 0.7vw, 12px);
            color: var(--medical-text-secondary);
            margin-bottom: 4px;
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }

        /* ============================================================ */
        /* COMPOSANT: CARD MODERN */
        /* ============================================================ */
        .card-modern {
            background: var(--medical-white);
            border: none;
            border-radius: var(--medical-radius);
            box-shadow: var(--medical-shadow);
            overflow: hidden;
            border: 1px solid var(--medical-border);
            font-family: var(--font-primary);
        }

        .card-modern .card-header {
            background: var(--medical-gray-light);
            border-bottom: 1px solid var(--medical-border);
            padding: 14px 22px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }

        .card-modern .card-header h5 {
            font-family: var(--font-primary);
            font-size: clamp(14px, 1.1vw, 16px);
            font-weight: 700;
            margin: 0;
            color: var(--medical-text);
            display: flex;
            align-items: center;
            gap: 10px;
            letter-spacing: 0.01em;
        }

        .card-modern .card-header h5 .badge-count {
            background: var(--medical-blue-light);
            color: var(--medical-blue);
            font-weight: 800;
            padding: 2px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
        }

        .card-modern .card-body {
            padding: 0;
        }

        /* ============================================================ */
        /* TABLE */
        /* ============================================================ */
        .table-wrapper {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        .table-modern {
            font-family: var(--font-primary);
            font-size: clamp(0.75rem, 0.85vw, 0.9rem);
            margin-bottom: 0;
            min-width: 500px;
        }

        .table-modern thead th {
            font-family: var(--font-primary);
            padding: 12px 16px;
            font-weight: 700;
            color: var(--medical-text-secondary);
            font-size: clamp(10px, 0.7vw, 11px);
            text-transform: uppercase;
            letter-spacing: 0.06em;
            border-bottom: 2px solid var(--medical-border);
            background: var(--medical-gray-light);
            white-space: nowrap;
        }

        .table-modern tbody td {
            font-family: var(--font-primary);
            padding: 12px 16px;
            border-bottom: 1px solid var(--medical-border);
            color: var(--medical-text);
            vertical-align: middle;
        }

        .table-modern tbody tr:last-child td { border-bottom: none; }
        .table-modern tbody tr:hover { background: var(--medical-gray-light); }

        /* ============================================================ */
        /* BADGES SPÉCIFIQUES */
        /* ============================================================ */
        .badge-medical {
            font-family: var(--font-primary);
            font-weight: 700;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: clamp(10px, 0.65vw, 12px);
            letter-spacing: 0.03em;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            border: none;
        }

        .badge-medical.primary { background: var(--medical-blue-light); color: var(--medical-blue); }
        .badge-medical.success { background: var(--medical-teal-light); color: var(--medical-teal); }
        .badge-medical.info { background: var(--info-light); color: var(--info); }
        .badge-medical.warning { background: var(--warning-light); color: var(--warning); }
        .badge-medical.danger { background: var(--danger-light); color: var(--danger); }
        .badge-medical.secondary { background: var(--medical-gray); color: var(--medical-text-secondary); }

        .badge-medical i { font-size: 0.85em; }

        /* ============================================================ */
        /* ACTIONS */
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

        .btn-action:hover {
            transform: scale(1.1);
            background: var(--medical-gray-light);
            color: var(--medical-text);
        }

        .btn-action.edit:hover { background: var(--medical-blue-light); color: var(--medical-blue); }
        .btn-action.delete:hover { background: var(--danger-light); color: var(--danger); }

        /* ============================================================ */
        /* PAGINATION */
        /* ============================================================ */
        .pagination-wrapper {
            padding: 14px 22px;
            display: flex;
            justify-content: center;
            border-top: 1px solid var(--medical-border);
        }

        .pagination .page-link {
            font-family: var(--font-primary);
            font-weight: 600;
            color: var(--medical-text-secondary);
            border: 1.5px solid var(--medical-border);
            border-radius: 8px;
            margin: 0 3px;
            font-size: clamp(0.75rem, 0.8vw, 0.9rem);
            padding: 6px 14px;
            transition: var(--medical-transition);
            background: transparent;
            cursor: pointer;
        }

        .pagination .page-item.active .page-link {
            background: var(--medical-blue);
            border-color: var(--medical-blue);
            color: #fff;
            box-shadow: 0 4px 12px rgba(26, 107, 138, 0.3);
        }

        .pagination .page-link:hover:not(.active) {
            background: var(--medical-gray-light);
            border-color: var(--medical-blue);
            color: var(--medical-blue);
        }

        .pagination .page-item.disabled .page-link {
            opacity: 0.4;
            pointer-events: none;
        }

        /* ============================================================ */
        /* MODALS */
        /* ============================================================ */
        .modal-content {
            border: none;
            border-radius: var(--medical-radius);
            box-shadow: 0 24px 60px rgba(0, 0, 0, 0.15);
            overflow: hidden;
            font-family: var(--font-primary);
        }

        .modal-header-custom {
            padding: 16px 24px;
            border: none;
        }

        .modal-header-custom.primary {
            background: var(--medical-blue);
            color: #fff;
        }

        .modal-header-custom.success {
            background: var(--medical-teal);
            color: #fff;
        }

        .modal-header-custom.danger {
            background: var(--danger);
            color: #fff;
        }

        .modal-header-custom .btn-close {
            filter: brightness(0) invert(1);
        }

        .modal-header-custom h5 {
            font-family: var(--font-primary);
            font-weight: 700;
            font-size: clamp(16px, 1.2vw, 20px);
            letter-spacing: 0.01em;
        }

        .modal-header-custom h5 i {
            margin-right: 10px;
        }

        .modal-body {
            padding: 24px;
        }

        .modal-footer {
            padding: 14px 24px;
            border-top: 1px solid var(--medical-border);
            background: var(--medical-gray-light);
        }

        .modal-body .form-control,
        .modal-body .form-select {
            font-family: var(--font-primary);
            background: var(--medical-gray-light);
            border: 1.5px solid var(--medical-border);
            border-radius: var(--medical-radius-sm);
            padding: 9px 14px;
            font-size: clamp(0.8rem, 0.9vw, 0.9rem);
            transition: var(--medical-transition);
            height: 42px;
            font-weight: 500;
        }

        .modal-body .form-control:focus,
        .modal-body .form-select:focus {
            border-color: var(--medical-blue);
            box-shadow: 0 0 0 4px rgba(26, 107, 138, 0.1);
            background: #fff;
        }

        .modal-body .form-label {
            font-family: var(--font-primary);
            font-weight: 700;
            font-size: clamp(11px, 0.75vw, 13px);
            color: var(--medical-text-secondary);
            margin-bottom: 4px;
            letter-spacing: 0.02em;
        }

        .modal-body .section-title {
            font-family: var(--font-primary);
            font-weight: 700;
            font-size: clamp(13px, 0.9vw, 14px);
            color: var(--medical-text-secondary);
            margin-bottom: 12px;
            letter-spacing: 0.02em;
        }

        .modal-body .section-title i {
            color: var(--medical-blue);
            margin-right: 8px;
        }

        .delete-confirm-box {
            background: var(--medical-gray-light);
            padding: 14px 18px;
            border-radius: var(--medical-radius-sm);
            border-left: 4px solid var(--danger);
            font-family: var(--font-primary);
        }

        .delete-confirm-box strong {
            color: var(--medical-text);
        }

        /* ============================================================ */
        /* ALERTS */
        /* ============================================================ */
        .alert-medical {
            font-family: var(--font-primary);
            border: none;
            border-radius: var(--medical-radius-sm);
            padding: 12px 18px;
            font-weight: 500;
        }
        .alert-medical.alert-success { background: var(--medical-teal-light); color: var(--medical-teal); }
        .alert-medical.alert-danger { background: var(--danger-light); color: var(--danger); }
        .alert-medical.alert-info { background: var(--medical-blue-light); color: var(--medical-blue); }
        .alert-medical.alert-warning { background: var(--warning-light); color: var(--warning); }

        /* ============================================================ */
        /* SELECT2 OVERRIDE */
        /* ============================================================ */
        .select2-container--default .select2-selection--single {
            border-color: var(--medical-border) !important;
            border-radius: var(--medical-radius-sm) !important;
            height: 42px !important;
            background: var(--medical-gray-light) !important;
            font-family: var(--font-primary) !important;
        }

        .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 42px !important;
            color: var(--medical-text) !important;
            font-weight: 500 !important;
        }

        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 42px !important;
        }

        .select2-container--default.select2-container--open .select2-selection--single {
            border-color: var(--medical-blue) !important;
            box-shadow: 0 0 0 4px rgba(26, 107, 138, 0.1) !important;
        }

        .select2-dropdown {
            border-color: var(--medical-border) !important;
            border-radius: var(--medical-radius-sm) !important;
            box-shadow: var(--medical-shadow-hover) !important;
            font-family: var(--font-primary) !important;
        }

        .select2-results__option {
            padding: 8px 14px !important;
            font-weight: 500 !important;
        }

        .select2-results__option--highlighted {
            background: var(--medical-blue) !important;
            color: #fff !important;
        }

        .select2-container--default .select2-results__option[aria-selected="true"] {
            background: var(--medical-blue-light) !important;
            color: var(--medical-blue) !important;
        }

        /* ============================================================ */
        /* LOADING SPINNER */
        /* ============================================================ */
        .loading-spinner {
            display: inline-block;
            width: 24px;
            height: 24px;
            border: 3px solid var(--medical-border);
            border-top: 3px solid var(--medical-blue);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* ============================================================ */
        /* ANIMATIONS */
        /* ============================================================ */
        @keyframes fadeSlideUp {
            from { opacity: 0; transform: translateY(16px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .fade-in { animation: fadeSlideUp 0.5s ease forwards; }

        /* ============================================================ */
        /* RESPONSIVE */
        /* ============================================================ */
        @media (max-width: 992px) {
            body { padding: 14px; }
            .page-header { flex-direction: column; align-items: stretch; gap: 10px; padding: 16px 18px; }
            .header-actions { margin-left: 0; justify-content: flex-start; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
        }

        @media (max-width: 768px) {
            body { padding: 10px; }
            .page-header h1 { font-size: 20px; }
            .stats-grid { gap: 10px; }
            .stat-card { padding: 12px 16px; }
            .stat-card .stat-icon { width: 36px; height: 36px; font-size: 15px; }
            .stat-card .stat-value { font-size: 18px; }
            .filter-section { padding: 14px 16px 18px; }
            .card-modern .card-header { padding: 12px 16px; }
            .table-modern thead th, .table-modern tbody td { padding: 8px 12px; }
            .btn-action { width: 30px; height: 30px; font-size: 13px; }
        }

        @media (max-width: 576px) {
            body { padding: 6px; }
            .stats-grid { grid-template-columns: 1fr 1fr; gap: 6px; }
            .stat-card { padding: 10px 12px; gap: 10px; }
            .stat-card .stat-icon { width: 30px; height: 30px; font-size: 13px; border-radius: 8px; }
            .stat-card .stat-value { font-size: 16px; }
            .stat-card .stat-label { font-size: 10px; }
            .filter-section { padding: 10px 12px 14px; }
            .filter-section .form-control, .filter-section .form-select { font-size: 0.75rem; padding: 6px 10px; height: 36px; }
            .table-modern { font-size: 0.7rem; min-width: 400px; }
            .table-modern thead th, .table-modern tbody td { padding: 6px 8px; }
            .btn-action { width: 26px; height: 26px; font-size: 11px; border-radius: 6px; }
            .pagination .page-link { padding: 4px 8px; font-size: 0.7rem; }
            .modal-dialog { margin: 8px; }
            .modal-body { padding: 12px; }
        }
    </style>
</head>
<body>

<div class="app-container">

    <!-- ============================================================ -->
    <!-- HEADER -->
    <!-- ============================================================ -->
    <header class="page-header">
        <div class="header-left">
            <div class="medical-badge">
                <i class="bi bi-heart-pulse-fill"></i> Epencia SGI
            </div>
            <h1>Associations <span class="highlight">Projet / Tranche d'âge</span></h1>
            <div class="subtitle">
                <i class="bi bi-link-45deg"></i>
                Gérer les associations entre projets et tranches d'âge
                <span class="dot"></span>
                <i class="bi bi-person-circle"></i> <?php echo htmlspecialchars($user_nom); ?>
                <span class="dot"></span>
                <?php echo getRoleBadge($user_role); ?>
            </div>
        </div>
        <div class="header-actions">
            <button class="btn-medical-primary" data-bs-toggle="modal" data-bs-target="#addModal">
                <i class="bi bi-plus-circle"></i> <span>Nouvelle association</span>
            </button>
        </div>
    </header>

    <!-- ============================================================ -->
    <!-- MESSAGES -->
    <!-- ============================================================ -->
    <?php if ($message): ?>
        <div class="alert alert-medical alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i> <?= htmlspecialchars($message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-medical alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- ============================================================ -->
    <!-- STATISTIQUES -->
    <!-- ============================================================ -->
    <div class="stats-grid fade-in">
        <div class="stat-card">
            <div class="stat-icon info"><i class="bi bi-link-45deg"></i></div>
            <div>
                <div class="stat-value"><?= $total ?></div>
                <div class="stat-label">Total associations</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon blue"><i class="bi bi-folder"></i></div>
            <div>
                <div class="stat-value"><?= $total_projets ?></div>
                <div class="stat-label">Projets associés</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon teal"><i class="bi bi-calendar-range"></i></div>
            <div>
                <div class="stat-value"><?= $total_tranches ?></div>
                <div class="stat-label">Tranches associées</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon warning"><i class="bi bi-trophy"></i></div>
            <div>
                <div class="stat-value"><?= $projet_plus_associe ? htmlspecialchars($projet_plus_associe['nb']) : '0' ?></div>
                <div class="stat-label">Max associations</div>
                <div class="stat-sub"><?= $projet_plus_associe ? htmlspecialchars($projet_plus_associe['titre_projet']) : '-' ?></div>
            </div>
        </div>
    </div>

    <!-- ============================================================ -->
    <!-- FILTRES -->
    <!-- ============================================================ -->
    <form method="POST" action="" id="filterForm" class="filter-section fade-in">
        <div class="filter-title">
            <i class="bi bi-funnel"></i> Filtres avancés
        </div>
        <div class="row g-2 g-md-3 align-items-end">
            <div class="col-12 col-sm-6 col-md-4">
                <label class="form-label">Recherche</label>
                <input type="text" class="form-control" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Projet, tranche...">
            </div>
            <div class="col-6 col-sm-3 col-md-3">
                <label class="form-label">Projet</label>
                <select name="projet" class="form-select" style="width:100%;">
                    <option value="">Tous</option>
                    <?php foreach ($projets as $p): ?>
                        <option value="<?= $p['id_projet'] ?>" <?= $filter_projet == $p['id_projet'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($p['titre_projet']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-sm-3 col-md-3">
                <label class="form-label">Tranche d'âge</label>
                <select name="tranche" class="form-select" style="width:100%;">
                    <option value="">Toutes</option>
                    <?php foreach ($tranches as $t): ?>
                        <option value="<?= $t['id_tranche'] ?>" <?= $filter_tranche == $t['id_tranche'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($t['titre_debut'] . ' - ' . $t['titre_fin']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-sm-6 col-md-2">
                <div class="d-flex gap-2">
                    <button type="submit" name="apply_filters" class="btn-medical-primary w-100" style="justify-content:center;height:42px;">
                        <i class="bi bi-search"></i> Filtrer
                    </button>
                    <button type="submit" name="reset_filters" class="btn-medical-outline" style="padding:0 14px;height:42px;" title="Réinitialiser">
                        <i class="bi bi-arrow-repeat"></i>
                    </button>
                </div>
            </div>
        </div>
    </form>

    <!-- ============================================================ -->
    <!-- LISTE DES ASSOCIATIONS -->
    <!-- ============================================================ -->
    <div class="card-modern fade-in">
        <div class="card-header">
            <h5>
                <i class="bi bi-list-ul" style="color:var(--medical-blue);"></i>
                Liste des associations
                <span class="badge-count"><?= $total_affectations ?></span>
            </h5>
        </div>
        <div class="card-body">
            <div class="table-wrapper">
                <table class="table table-modern">
                    <thead>
                        <tr>
                            <th style="width:80px;">ID</th>
                            <th>Projet</th>
                            <th>Tranche d'âge</th>
                            <th class="text-center" style="min-width:100px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($affectations)): ?>
                            <tr>
                                <td colspan="4" class="text-center py-5" style="color:var(--medical-text-muted);">
                                    <i class="bi bi-inbox" style="font-size:3rem;display:block;margin-bottom:12px;opacity:0.3;"></i>
                                    Aucune association trouvée
                                </td>
                            </tr>
                        <?php else: foreach ($affectations as $a): ?>
                            <tr>
                                <td>
                                    <span style="font-weight:700;color:var(--medical-text-muted);font-family:var(--font-primary);">#<?= $a['id'] ?></span>
                                </td>
                                <td>
                                    <span class="badge-medical primary">
                                        <i class="bi bi-folder me-1"></i>
                                        <?= htmlspecialchars($a['titre_projet'] ?: 'N/A') ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge-medical info">
                                        <i class="bi bi-calendar-range me-1"></i>
                                        <?= htmlspecialchars($a['titre_debut'] ?? '') ?> - <?= htmlspecialchars($a['titre_fin'] ?? '') ?>
                                        <span style="font-weight:400;opacity:0.7;">
                                            (<?= $a['age_debut'] ?? '' ?>-<?= $a['age_fin'] ?? '' ?> ans)
                                        </span>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <div class="actions-group">
                                        <button type="button" class="btn-action edit" data-bs-toggle="modal" data-bs-target="#editModal" 
                                            data-id="<?= $a['id'] ?>" 
                                            title="Modifier">
                                            <i class="bi bi-pencil-square"></i>
                                        </button>
                                        <button type="button" class="btn-action delete" data-bs-toggle="modal" data-bs-target="#deleteModal" 
                                            data-id="<?= $a['id'] ?>" 
                                            data-projet="<?= htmlspecialchars($a['titre_projet'] ?: 'N/A') ?>" 
                                            data-tranche="<?= htmlspecialchars($a['titre_debut'] ?? '') ?> - <?= htmlspecialchars($a['titre_fin'] ?? '') ?>" 
                                            title="Supprimer">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="pagination-wrapper">
                    <ul class="pagination mb-0 flex-wrap justify-content-center">
                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="page" value="<?= $page - 1 ?>">
                                <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
                                <input type="hidden" name="projet" value="<?= htmlspecialchars($filter_projet) ?>">
                                <input type="hidden" name="tranche" value="<?= htmlspecialchars($filter_tranche) ?>">
                                <button type="submit" class="page-link" <?= $page <= 1 ? 'disabled' : '' ?>>
                                    <i class="bi bi-chevron-left"></i>
                                </button>
                            </form>
                        </li>
                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="page" value="<?= $i ?>">
                                    <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
                                    <input type="hidden" name="projet" value="<?= htmlspecialchars($filter_projet) ?>">
                                    <input type="hidden" name="tranche" value="<?= htmlspecialchars($filter_tranche) ?>">
                                    <button type="submit" class="page-link"><?= $i ?></button>
                                </form>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="page" value="<?= $page + 1 ?>">
                                <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
                                <input type="hidden" name="projet" value="<?= htmlspecialchars($filter_projet) ?>">
                                <input type="hidden" name="tranche" value="<?= htmlspecialchars($filter_tranche) ?>">
                                <button type="submit" class="page-link" <?= $page >= $total_pages ? 'disabled' : '' ?>>
                                    <i class="bi bi-chevron-right"></i>
                                </button>
                            </form>
                        </li>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ============================================================ -->
<!-- MODAL AJOUTER -->
<!-- ============================================================ -->
<div class="modal fade" id="addModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header-custom success">
                <h5><i class="bi bi-plus-circle"></i>Nouvelle association</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="section-title"><i class="bi bi-link-45deg"></i>Association Projet / Tranche d'âge</div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Projet <span class="text-danger">*</span></label>
                            <select name="sai_id_projet" id="sai_id_projet" class="form-select" required style="width:100%;">
                                <option value="">-- Sélectionnez un projet --</option>
                                <?php foreach ($projets as $p): ?>
                                    <option value="<?= $p['id_projet'] ?>"><?= htmlspecialchars($p['titre_projet']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Tranche d'âge <span class="text-danger">*</span></label>
                            <select name="sai_id_tranche" id="sai_id_tranche" class="form-select" required style="width:100%;">
                                <option value="">-- Sélectionnez une tranche --</option>
                                <?php foreach ($tranches as $t): ?>
                                    <option value="<?= $t['id_tranche'] ?>">
                                        <?= htmlspecialchars($t['titre_debut'] . ' - ' . $t['titre_fin'] . ' (' . $t['age_debut'] . '-' . $t['age_fin'] . ' ans)') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-medical-outline" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" name="btn_ajouter" class="btn-medical-secondary">
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
            <div class="modal-header-custom primary">
                <h5><i class="bi bi-pencil-square"></i>Modifier l'association</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="editForm">
                <div class="modal-body" id="editModalBody">
                    <div class="text-center py-4">
                        <div class="loading-spinner"></div>
                        <p class="mt-2 text-muted" style="font-family:var(--font-primary);font-weight:500;">Chargement...</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-medical-outline" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" name="btn_modifier" class="btn-medical-primary">
                        <i class="bi bi-save me-1"></i>Modifier
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
            <div class="modal-header-custom danger">
                <h5><i class="bi bi-exclamation-triangle-fill"></i>Confirmer la suppression</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="sai_id" id="delete_id">
                    
                    <p style="font-weight:500;font-family:var(--font-primary);">Voulez-vous vraiment supprimer cette association ?</p>
                    
                    <div class="delete-confirm-box">
                        <strong>Projet :</strong> <span id="delete_projet"></span><br>
                        <strong>Tranche :</strong> <span id="delete_tranche"></span>
                    </div>
                    
                    <p class="text-danger mb-0 mt-3" style="font-size:clamp(12px,0.8vw,13px);font-weight:700;font-family:var(--font-primary);">
                        <i class="bi bi-exclamation-circle me-1"></i>
                        Cette action est irréversible.
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-medical-outline" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" name="btn_supprimer" class="btn-medical-danger">
                        <i class="bi bi-trash me-1"></i>Supprimer
                    </button>
                </div>
            </form>
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
    // INITIALISATION SELECT2
    // ============================================================
    
    // Sélecteurs dans le modal d'ajout
    $('#sai_id_projet').select2({
        theme: 'default',
        width: '100%',
        placeholder: 'Sélectionnez un projet',
        allowClear: true,
        dropdownParent: $('#addModal')
    });
    
    $('#sai_id_tranche').select2({
        theme: 'default',
        width: '100%',
        placeholder: 'Sélectionnez une tranche d\'âge',
        allowClear: true,
        dropdownParent: $('#addModal')
    });

    // Sélecteurs dans les filtres
    $('select[name="projet"]').select2({
        theme: 'default',
        width: '100%',
        placeholder: 'Tous',
        allowClear: true,
        dropdownParent: $('body')
    });

    $('select[name="tranche"]').select2({
        theme: 'default',
        width: '100%',
        placeholder: 'Toutes',
        allowClear: true,
        dropdownParent: $('body')
    });

    // ============================================================
    // CHARGEMENT POUR MODIFICATION
    // ============================================================
    $('#editModal').on('show.bs.modal', function(e) {
        const button = $(e.relatedTarget);
        const id = button.data('id');
        const modal = $(this);
        const body = modal.find('#editModalBody');
        
        body.html(`
            <div class="text-center py-4">
                <div class="loading-spinner"></div>
                <p class="mt-2 text-muted" style="font-family:var(--font-primary);font-weight:500;">Chargement de l'association...</p>
            </div>
        `);
        
        $.post(window.location.href, { 
            ajax_action: 'load_affectation', 
            id: id 
        }, function(data) {
            if (data.success) {
                const a = data.data;
                body.html(`
                    <input type="hidden" name="sai_id" value="${a.id}">
                    
                    <div class="section-title"><i class="bi bi-link-45deg"></i>Association Projet / Tranche d'âge</div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Projet <span class="text-danger">*</span></label>
                            <select name="sai_id_projet" id="edit_sai_id_projet" class="form-select" required style="width:100%;">
                                <option value="">-- Sélectionnez un projet --</option>
                                <?php foreach ($projets as $p): ?>
                                    <option value="<?= $p['id_projet'] ?>" ${a.id_projet === '<?= $p['id_projet'] ?>' ? 'selected' : ''}>
                                        <?= htmlspecialchars($p['titre_projet']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Tranche d'âge <span class="text-danger">*</span></label>
                            <select name="sai_id_tranche" id="edit_sai_id_tranche" class="form-select" required style="width:100%;">
                                <option value="">-- Sélectionnez une tranche --</option>
                                <?php foreach ($tranches as $t): ?>
                                    <option value="<?= $t['id_tranche'] ?>" ${a.id_tranche === '<?= $t['id_tranche'] ?>' ? 'selected' : ''}>
                                        <?= htmlspecialchars($t['titre_debut'] . ' - ' . $t['titre_fin'] . ' (' . $t['age_debut'] . '-' . $t['age_fin'] . ' ans)') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                `);
                
                // Réinitialiser Select2 dans le modal d'édition
                $('#edit_sai_id_projet').select2({
                    theme: 'default',
                    width: '100%',
                    placeholder: 'Sélectionnez un projet',
                    allowClear: true,
                    dropdownParent: $('#editModal')
                });
                
                $('#edit_sai_id_tranche').select2({
                    theme: 'default',
                    width: '100%',
                    placeholder: 'Sélectionnez une tranche d\'âge',
                    allowClear: true,
                    dropdownParent: $('#editModal')
                });
                
            } else {
                body.html(`
                    <div class="alert alert-medical alert-danger">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        Erreur lors du chargement de l'association.
                    </div>
                `);
            }
        }, 'json');
    });

    // ============================================================
    // SUPPRESSION
    // ============================================================
    $('#deleteModal').on('show.bs.modal', function(e) {
        const button = $(e.relatedTarget);
        const id = button.data('id');
        const projet = button.data('projet');
        const tranche = button.data('tranche');
        
        $('#delete_id').val(id);
        $('#delete_projet').text(projet);
        $('#delete_tranche').text(tranche);
    });

    // ============================================================
    // RÉINITIALISATION DES FILTRES (correction)
    // ============================================================
    $('#filterForm').on('submit', function() {
        // Les filtres sont gérés par le PHP via POST
        return true;
    });
});
</script>

</body>
</html>