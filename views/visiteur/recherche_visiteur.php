<?php
// ========================================
// GESTION DES VISITEURS - EPENCIA SGI
// ========================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['utilisateur_id'])) {
    header('Location: ../utilisateur/connexion.php');
    exit();
}

require_once 'database/database.php';

 $message = '';
 $error = '';

 $user_role = $_SESSION['role'] ?? '';
 $user_nom = $_SESSION['nom_prenom'] ?? 'Utilisateur';
 $is_admin = in_array($user_role, ['Administrateur', 'Superviseur']);

if (!$is_admin) {
    header('Location: ../utilisateur/dashboard.php?error=Accès non autorisé');
    exit();
}

// ========================================
// HANDLERS AJAX
// ========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    header('Content-Type: application/json; charset=utf-8');
    
    if ($_POST['ajax_action'] === 'load_visiteur') {
        $id = (int)$_POST['id'];
        $stmt = $pdo->prepare("SELECT * FROM visiteur WHERE id = ?");
        $stmt->execute([$id]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($data) {
            echo json_encode(['success' => true, 'data' => $data]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Session introuvable']);
        }
        exit;
    }
    
    if (isset($_POST['btn_ajouter'])) {
        try {
            $date_connexion = trim($_POST['date_connexion']);
            $heure_connexion = trim($_POST['heure_connexion']);
            $date_deconnexion = !empty($_POST['date_deconnexion']) ? trim($_POST['date_deconnexion']) : null;
            $heure_deconnexion = !empty($_POST['heure_deconnexion']) ? trim($_POST['heure_deconnexion']) : null;
            $reference = trim($_POST['reference']);
            $etat_connexion = trim($_POST['etat_connexion']);
            
            if (empty($date_connexion) || empty($heure_connexion) || empty($reference) || empty($etat_connexion)) {
                throw new Exception('Tous les champs obligatoires doivent être remplis.');
            }
            
            $duree_date = 0;
            $duree_heure = 0;
            if ($date_deconnexion && $heure_deconnexion) {
                $debut = strtotime($date_connexion . ' ' . $heure_connexion);
                $fin = strtotime($date_deconnexion . ' ' . $heure_deconnexion);
                $diff = ($fin - $debut) / 3600;
                $duree_date = floor($diff / 24);
                $duree_heure = round($diff % 24, 2);
                if ($duree_date < 0 || $duree_heure < 0) {
                    throw new Exception('La date de déconnexion doit être postérieure à la date de connexion.');
                }
            }
            
            $req = $pdo->prepare("INSERT INTO visiteur (date_connexion, heure_connexion, date_deconnexion, heure_deconnexion, duree_date, duree_heure, reference, etat_connexion) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $req->execute([$date_connexion, $heure_connexion, $date_deconnexion, $heure_deconnexion, $duree_date, $duree_heure, $reference, $etat_connexion]);
            echo json_encode(['success' => true, 'message' => 'Session créée avec succès']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }
    
    if (isset($_POST['btn_modifier'])) {
        try {
            $id = (int)$_POST['id'];
            $date_connexion = trim($_POST['date_connexion']);
            $heure_connexion = trim($_POST['heure_connexion']);
            $date_deconnexion = !empty($_POST['date_deconnexion']) ? trim($_POST['date_deconnexion']) : null;
            $heure_deconnexion = !empty($_POST['heure_deconnexion']) ? trim($_POST['heure_deconnexion']) : null;
            $reference = trim($_POST['reference']);
            $etat_connexion = trim($_POST['etat_connexion']);
            
            if (empty($date_connexion) || empty($heure_connexion) || empty($reference) || empty($etat_connexion)) {
                throw new Exception('Tous les champs obligatoires doivent être remplis.');
            }
            
            $duree_date = 0;
            $duree_heure = 0;
            if ($date_deconnexion && $heure_deconnexion) {
                $debut = strtotime($date_connexion . ' ' . $heure_connexion);
                $fin = strtotime($date_deconnexion . ' ' . $heure_deconnexion);
                $diff = ($fin - $debut) / 3600;
                $duree_date = floor($diff / 24);
                $duree_heure = round($diff % 24, 2);
                if ($duree_date < 0 || $duree_heure < 0) {
                    throw new Exception('La date de déconnexion doit être postérieure à la date de connexion.');
                }
            }
            
            $req = $pdo->prepare("UPDATE visiteur SET date_connexion=?, heure_connexion=?, date_deconnexion=?, heure_deconnexion=?, duree_date=?, duree_heure=?, reference=?, etat_connexion=? WHERE id=?");
            $req->execute([$date_connexion, $heure_connexion, $date_deconnexion, $heure_deconnexion, $duree_date, $duree_heure, $reference, $etat_connexion, $id]);
            echo json_encode(['success' => true, 'message' => 'Session modifiée avec succès']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }
    
    if (isset($_POST['btn_supprimer'])) {
        try {
            $id = (int)$_POST['id'];
            $req = $pdo->prepare("DELETE FROM visiteur WHERE id = ?");
            $req->execute([$id]);
            echo json_encode(['success' => true, 'message' => 'Session supprimée avec succès']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }
}

// ========================================
// FILTRES ET PAGINATION
// ========================================
 $limit = 15;
 $page = max(1, (int)($_POST['page'] ?? $_GET['page'] ?? 1));
 $offset = ($page - 1) * $limit;
 $search = trim($_POST['search'] ?? $_SESSION['visiteur_filters']['search'] ?? '');
 $filter_date_debut = trim($_POST['date_debut'] ?? $_SESSION['visiteur_filters']['date_debut'] ?? '');
 $filter_date_fin = trim($_POST['date_fin'] ?? $_SESSION['visiteur_filters']['date_fin'] ?? '');
 $filter_etat = trim($_POST['etat'] ?? $_SESSION['visiteur_filters']['etat'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['apply_filters'])) {
    $_SESSION['visiteur_filters'] = [
        'search' => $search,
        'date_debut' => $filter_date_debut,
        'date_fin' => $filter_date_fin,
        'etat' => $filter_etat
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_filters'])) {
    unset($_SESSION['visiteur_filters']);
    $search = '';
    $filter_date_debut = '';
    $filter_date_fin = '';
    $filter_etat = '';
    $page = 1;
}

 $offset = ($page - 1) * $limit;
 $etats = ['ACTIF', 'INACTIF', 'TERMINE'];

// Compter total
 $count_sql = 'SELECT COUNT(*) FROM visiteur';
 $count_params = [];
 $where_clauses = [];

if (!empty($search)) {
    $where_clauses[] = '(id LIKE ? OR reference LIKE ? OR etat_connexion LIKE ?)';
    $t = "%$search%";
    $count_params = array_merge($count_params, [$t, $t, $t]);
}
if (!empty($filter_date_debut)) { $where_clauses[] = 'date_connexion >= ?'; $count_params[] = $filter_date_debut; }
if (!empty($filter_date_fin)) { $where_clauses[] = 'date_connexion <= ?'; $count_params[] = $filter_date_fin; }
if (!empty($filter_etat)) { $where_clauses[] = 'etat_connexion = ?'; $count_params[] = $filter_etat; }
if (!empty($where_clauses)) $count_sql .= ' WHERE ' . implode(' AND ', $where_clauses);

 $stmt = $pdo->prepare($count_sql);
 $stmt->execute($count_params);
 $total_visiteurs = $stmt->fetchColumn();
 $total_pages = max(1, ceil($total_visiteurs / $limit));

// Récupérer
 $sql = 'SELECT * FROM visiteur';
 $params = [];
 $where_clauses = [];

if (!empty($search)) {
    $where_clauses[] = '(id LIKE ? OR reference LIKE ? OR etat_connexion LIKE ?)';
    $t = "%$search%";
    $params = array_merge($params, [$t, $t, $t]);
}
if (!empty($filter_date_debut)) { $where_clauses[] = 'date_connexion >= ?'; $params[] = $filter_date_debut; }
if (!empty($filter_date_fin)) { $where_clauses[] = 'date_connexion <= ?'; $params[] = $filter_date_fin; }
if (!empty($filter_etat)) { $where_clauses[] = 'etat_connexion = ?'; $params[] = $filter_etat; }
if (!empty($where_clauses)) $sql .= ' WHERE ' . implode(' AND ', $where_clauses);
 $sql .= ' ORDER BY id DESC LIMIT ? OFFSET ?';

 $stmt = $pdo->prepare($sql);
 $pi = 1;
foreach ($params as $p) $stmt->bindValue($pi++, $p, PDO::PARAM_STR);
 $stmt->bindValue($pi++, $limit, PDO::PARAM_INT);
 $stmt->bindValue($pi++, $offset, PDO::PARAM_INT);
 $stmt->execute();
 $visiteurs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Statistiques
 $stmt = $pdo->query("SELECT COUNT(*) FROM visiteur"); $total = $stmt->fetchColumn();
 $stmt = $pdo->query("SELECT COUNT(*) FROM visiteur WHERE etat_connexion = 'ACTIF'"); $actifs = $stmt->fetchColumn();
 $stmt = $pdo->query("SELECT COUNT(*) FROM visiteur WHERE etat_connexion = 'INACTIF'"); $inactifs = $stmt->fetchColumn();
 $stmt = $pdo->query("SELECT COUNT(*) FROM visiteur WHERE etat_connexion = 'TERMINE'"); $termines = $stmt->fetchColumn();

 $stmt = $pdo->query("SELECT AVG(duree_date) as avg_jours, AVG(duree_heure) as avg_heures FROM visiteur");
 $moyennes = $stmt->fetch(PDO::FETCH_ASSOC);

 $stmt = $pdo->query("SELECT *, (duree_date * 24 + duree_heure) as total_heures FROM visiteur ORDER BY total_heures DESC LIMIT 1");
 $session_plus_longue = $stmt->fetch(PDO::FETCH_ASSOC);

 $stmt = $pdo->query("SELECT DATE(date_connexion) as jour, COUNT(*) as nb FROM visiteur WHERE date_connexion >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) GROUP BY DATE(date_connexion) ORDER BY jour DESC");
 $connexions_par_jour = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Filtres actifs
 $active_filters = [];
if (!empty($search)) $active_filters[] = ['label' => 'Recherche', 'value' => $search];
if (!empty($filter_date_debut) || !empty($filter_date_fin)) {
    $period = '';
    if (!empty($filter_date_debut)) $period .= 'du ' . date('d/m/Y', strtotime($filter_date_debut));
    if (!empty($filter_date_fin)) $period .= ' au ' . date('d/m/Y', strtotime($filter_date_fin));
    if ($period) $active_filters[] = ['label' => 'Période', 'value' => $period];
}
if (!empty($filter_etat)) $active_filters[] = ['label' => 'État', 'value' => $filter_etat];

function getStatusBadge($status) {
    $statusMap = [
        'ACTIF' => ['class' => 'success', 'label' => 'Actif'],
        'INACTIF' => ['class' => 'danger', 'label' => 'Inactif'],
        'TERMINE' => ['class' => 'warning', 'label' => 'Terminé']
    ];
    $status = strtoupper($status ?? 'INACTIF');
    $info = $statusMap[$status] ?? ['class' => 'secondary', 'label' => $status];
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
    <title>Gestion des Visiteurs - Epencia SGI</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=DM+Serif+Display:ital@0;1&display=swap" rel="stylesheet">
    <style>
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
            --success: #2d9b8e; --success-light: #e6f5f3;
            --warning: #d4a843; --warning-light: #fdf5e6;
            --danger: #c0392b; --danger-light: #fde8e6;
            --info: #3498db; --info-light: #e8f4fd;
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

        .app-container { max-width: 1920px; margin: 0 auto; }

        /* ============================================================ */
        /* HEADER ADAPTATIF */
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

        .page-header .header-left {
            flex: 1 1 280px;
            min-width: 0;
        }

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

        /* ============================================================ */
        /* BOUTONS HEADER ADAPTATIFS */
        /* ============================================================ */
        .header-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            align-items: center;
            flex-shrink: 0;
        }

        .btn-header {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            border: none;
            border-radius: var(--medical-radius-sm);
            font-family: var(--font-primary);
            font-weight: 700;
            white-space: nowrap;
            cursor: pointer;
            transition: var(--medical-transition);
            text-decoration: none;
            line-height: 1;
        }

        .btn-header .btn-icon {
            font-size: 16px;
            flex-shrink: 0;
            display: inline-flex;
            align-items: center;
        }

        .btn-header .btn-label { flex-shrink: 0; }

        .btn-header-primary {
            background: var(--medical-blue);
            color: #fff;
            padding: 11px 22px;
            font-size: 0.88rem;
        }
        .btn-header-primary:hover {
            background: var(--medical-blue-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 20px rgba(26, 107, 138, 0.3);
            color: #fff;
        }

        .btn-header-outline {
            background: transparent;
            border: 1.5px solid var(--medical-border);
            color: var(--medical-text-secondary);
            padding: 10px 22px;
            font-size: 0.88rem;
        }
        .btn-header-outline:hover {
            background: var(--medical-gray-light);
            border-color: var(--medical-blue);
            color: var(--medical-blue);
            text-decoration: none;
        }

        /* PALIER 1 : ≤ 820px */
        @media (max-width: 820px) {
            .page-header { padding: 12px 14px; gap: 10px; }
            .page-header .header-left { flex: 1 1 100%; }
            .header-actions { width: 100%; justify-content: stretch; }
            .btn-header {
                flex: 1 1 0;
                padding: 10px 0 !important;
                border-radius: 8px;
                min-height: 40px;
            }
            .btn-header .btn-label { display: none; }
            .btn-header .btn-icon { font-size: 18px; }
        }

        /* PALIER 2 : 821 → 1024px */
        @media (min-width: 821px) and (max-width: 1024px) {
            .page-header { padding: 14px 18px; }
            .btn-header { padding: 9px 14px; font-size: 0.8rem; gap: 6px; }
            .btn-header .btn-label.short { display: inline; }
            .btn-header .btn-label.long { display: none; }
        }

        /* PALIER 3 : 1025 → 1366px */
        @media (min-width: 1025px) and (max-width: 1366px) {
            .btn-header { padding: 10px 18px; font-size: 0.85rem; }
            .btn-header .btn-label.short { display: none; }
            .btn-header .btn-label.long { display: inline; }
        }

        /* PALIER 4 : 1367 → 1960px */
        @media (min-width: 1367px) {
            .btn-header { padding: 11px 24px; font-size: 0.9rem; }
            .btn-header .btn-label.short { display: none; }
            .btn-header .btn-label.long { display: inline; }
        }

        /* HAUTEUR < 700px */
        @media (max-height: 700px) and (max-width: 820px) {
            .page-header { padding: 8px 12px; margin-bottom: 12px; }
            .page-header .medical-badge { display: none; }
            .page-header h1 { font-size: 17px; }
            .page-header .subtitle { font-size: 10px; }
            .btn-header { min-height: 36px; padding: 8px 0 !important; }
            .btn-header .btn-icon { font-size: 16px; }
        }

        /* ============================================================ */
        /* STATS */
        /* ============================================================ */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 14px;
            margin-bottom: 20px;
        }

        .stat-card {
            background: var(--medical-white);
            padding: 14px 18px;
            border-radius: var(--medical-radius);
            box-shadow: var(--medical-shadow);
            border: 1px solid var(--medical-border);
            display: flex;
            align-items: center;
            gap: 12px;
            transition: var(--medical-transition);
            cursor: default;
        }

        .stat-card:hover { transform: translateY(-3px); box-shadow: var(--medical-shadow-hover); }

        .stat-card .stat-icon {
            width: 42px; height: 42px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 17px;
            flex-shrink: 0;
        }

        .stat-icon.blue { background: var(--medical-blue-light); color: var(--medical-blue); }
        .stat-icon.teal { background: var(--medical-teal-light); color: var(--medical-teal); }
        .stat-icon.warning { background: var(--warning-light); color: var(--warning); }
        .stat-icon.danger { background: var(--danger-light); color: var(--danger); }
        .stat-icon.info { background: var(--info-light); color: var(--info); }

        .stat-card .stat-value {
            font-size: clamp(18px, 1.8vw, 24px);
            font-weight: 800;
            color: var(--medical-text);
            line-height: 1.1;
        }

        .stat-card .stat-label {
            font-size: clamp(10px, 0.75vw, 12px);
            color: var(--medical-text-secondary);
            font-weight: 600;
            margin-top: 2px;
        }

        .stat-card .stat-sub {
            font-size: 10px;
            color: var(--medical-text-muted);
            font-weight: 500;
            margin-top: 1px;
        }

        @media (max-width: 1024px) { .stats-grid { grid-template-columns: repeat(3, 1fr); } }
        @media (max-width: 820px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); gap: 8px; }
            .stat-card { padding: 10px 12px; gap: 10px; }
            .stat-card .stat-icon { width: 34px; height: 34px; font-size: 14px; }
            .stat-card .stat-value { font-size: 16px; }
            .stat-card .stat-label { font-size: 10px; }
            .stat-card .stat-sub { display: none; }
        }

        @media (max-height: 700px) and (max-width: 820px) {
            .stats-grid { margin-bottom: 10px; gap: 6px; }
            .stat-card { padding: 8px 10px; gap: 8px; }
            .stat-card .stat-icon { width: 28px; height: 28px; font-size: 12px; border-radius: 8px; }
            .stat-card .stat-value { font-size: 14px; }
            .stat-card .stat-label { font-size: 9px; }
        }

        /* ============================================================ */
        /* MINI STATS */
        /* ============================================================ */
        .mini-stats {
            background: var(--medical-white);
            border-radius: var(--medical-radius);
            padding: 14px 18px;
            margin-bottom: 20px;
            box-shadow: var(--medical-shadow);
            border: 1px solid var(--medical-border);
        }

        .mini-stats .mini-title {
            font-weight: 700;
            color: var(--medical-text-secondary);
            font-size: 12px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .mini-stats .mini-title i { color: var(--medical-blue); }

        .mini-stats .mini-items { display: flex; gap: 8px; flex-wrap: wrap; }

        .mini-stats .mini-item {
            background: var(--medical-gray-light);
            padding: 6px 14px;
            border-radius: var(--medical-radius-sm);
            border: 1px solid var(--medical-border);
            text-align: center;
            min-width: 55px;
            transition: var(--medical-transition);
        }

        .mini-stats .mini-item:hover { border-color: var(--medical-blue); background: var(--medical-white); }
        .mini-stats .mini-item .number { font-size: clamp(16px, 1.4vw, 20px); font-weight: 800; color: var(--medical-text); display: block; }
        .mini-stats .mini-item .label { font-size: 9px; color: var(--medical-text-muted); font-weight: 600; text-transform: uppercase; letter-spacing: 0.03em; }

        @media (max-width: 820px) {
            .mini-stats { padding: 10px 14px; margin-bottom: 14px; }
            .mini-stats .mini-item { padding: 4px 10px; min-width: 45px; }
            .mini-stats .mini-item .number { font-size: 14px; }
            .mini-stats .mini-item .label { font-size: 8px; }
        }

        @media (max-height: 700px) and (max-width: 820px) {
            .mini-stats { padding: 8px 10px; margin-bottom: 8px; }
            .mini-stats .mini-items { gap: 4px; }
            .mini-stats .mini-item { padding: 3px 8px; min-width: 36px; }
            .mini-stats .mini-item .number { font-size: 12px; }
            .mini-stats .mini-item .label { font-size: 7px; }
        }

        /* ============================================================ */
        /* FILTRES */
        /* ============================================================ */
        .filter-section {
            background: var(--medical-white);
            border-radius: var(--medical-radius);
            padding: 16px 20px;
            margin-bottom: 20px;
            box-shadow: var(--medical-shadow);
            border: 1px solid var(--medical-border);
            border-left: 4px solid var(--medical-blue);
        }

        .filter-section .filter-title {
            font-weight: 700;
            color: var(--medical-text-secondary);
            font-size: 12px;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .filter-section .filter-title i { color: var(--medical-blue); }

        .filter-section .form-control,
        .filter-section .form-select {
            border: 1.5px solid var(--medical-border);
            border-radius: var(--medical-radius-sm);
            padding: 8px 12px;
            font-size: 0.85rem;
            background: var(--medical-gray-light);
            color: var(--medical-text);
            transition: var(--medical-transition);
            height: 40px;
            font-weight: 500;
        }

        .filter-section .form-control:focus,
        .filter-section .form-select:focus {
            border-color: var(--medical-blue);
            box-shadow: 0 0 0 3px rgba(26, 107, 138, 0.1);
            background: #fff;
        }

        .filter-section .form-label {
            font-weight: 700;
            font-size: 10px;
            color: var(--medical-text-secondary);
            margin-bottom: 3px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .filter-actions { display: flex; gap: 6px; align-items: flex-end; height: 40px; }

        .filter-actions .btn {
            font-weight: 700;
            height: 40px;
            padding: 0 16px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            font-size: 0.8rem;
            border-radius: var(--medical-radius-sm);
            transition: var(--medical-transition);
            border: none;
            white-space: nowrap;
        }

        .btn-filter { background: var(--medical-blue); color: #fff; }
        .btn-filter:hover { background: var(--medical-blue-dark); color: #fff; }
        .btn-reset { background: var(--medical-gray); color: var(--medical-text-secondary); }
        .btn-reset:hover { background: var(--medical-border); color: var(--medical-text); }

        .filter-pills { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 12px; }

        .filter-pill {
            background: var(--medical-blue-light);
            color: var(--medical-blue);
            padding: 3px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        @media (max-width: 820px) {
            .filter-section { padding: 12px 14px; margin-bottom: 14px; }
            .filter-section .form-control, .filter-section .form-select { font-size: 0.8rem; padding: 7px 10px; height: 36px; }
            .filter-section .form-label { font-size: 9px; }
            .filter-actions { height: 36px; }
            .filter-actions .btn { height: 36px; padding: 0 12px; font-size: 0.75rem; }
        }

        @media (max-height: 700px) and (max-width: 820px) {
            .filter-section { padding: 8px 10px; margin-bottom: 8px; }
            .filter-section .form-control, .filter-section .form-select { height: 32px; padding: 5px 8px; font-size: 0.75rem; }
            .filter-actions { height: 32px; }
            .filter-actions .btn { height: 32px; padding: 0 10px; font-size: 0.7rem; }
            .filter-pills { margin-top: 6px; }
        }

        /* ============================================================ */
        /* CARD & TABLE */
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
            gap: 8px;
        }

        .card-modern .card-header h5 {
            font-size: clamp(13px, 1vw, 15px);
            font-weight: 700;
            margin: 0;
            color: var(--medical-text);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .card-modern .card-header .badge-count {
            background: var(--medical-blue-light);
            color: var(--medical-blue);
            font-weight: 800;
            padding: 2px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
        }

        .table-wrapper { overflow-x: auto; -webkit-overflow-scrolling: touch; }

        .table-modern {
            font-size: clamp(0.73rem, 0.8vw, 0.88rem);
            margin-bottom: 0;
            min-width: 680px;
        }

        .table-modern thead th {
            padding: 10px 14px;
            font-weight: 700;
            color: var(--medical-text-secondary);
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            border-bottom: 2px solid var(--medical-border);
            background: var(--medical-gray-light);
            white-space: nowrap;
        }

        .table-modern tbody td {
            padding: 10px 14px;
            border-bottom: 1px solid var(--medical-border);
            color: var(--medical-text);
            vertical-align: middle;
        }

        .table-modern tbody tr:last-child td { border-bottom: none; }
        .table-modern tbody tr:hover { background: var(--medical-gray-light); }

        @media (max-width: 820px) {
            .card-modern .card-header { padding: 12px 14px; }
            .table-modern thead th, .table-modern tbody td { padding: 8px 10px; }
        }

        /* ============================================================ */
        /* BADGES */
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
        .badge.bg-secondary { background: var(--medical-gray) !important; color: var(--medical-text-secondary) !important; }

        /* ============================================================ */
        /* ACTIONS */
        /* ============================================================ */
        .actions-group { display: flex; gap: 4px; justify-content: center; }

        .btn-action {
            width: 32px; height: 32px;
            padding: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            font-size: 14px;
            border: none;
            cursor: pointer;
            transition: var(--medical-transition);
            background: transparent;
            color: var(--medical-text-muted);
        }

        .btn-action:hover { transform: scale(1.1); }
        .btn-action.edit:hover { background: var(--medical-blue-light); color: var(--medical-blue); }
        .btn-action.delete:hover { background: var(--danger-light); color: var(--danger); }

        @media (max-width: 820px) {
            .btn-action { width: 28px; height: 28px; font-size: 12px; }
        }

        /* ============================================================ */
        /* PAGINATION */
        /* ============================================================ */
        .pagination-wrapper { padding: 12px 20px; display: flex; justify-content: center; border-top: 1px solid var(--medical-border); }

        .pagination .page-link {
            font-weight: 600;
            color: var(--medical-text-secondary);
            border: 1.5px solid var(--medical-border);
            border-radius: 8px;
            margin: 0 2px;
            font-size: 0.82rem;
            padding: 5px 12px;
            transition: var(--medical-transition);
            background: transparent;
        }

        .pagination .page-item.active .page-link { background: var(--medical-blue); border-color: var(--medical-blue); color: #fff; }
        .pagination .page-link:hover:not(.active) { background: var(--medical-gray-light); border-color: var(--medical-blue); color: var(--medical-blue); }
        .pagination .page-item.disabled .page-link { opacity: 0.4; pointer-events: none; }

        @media (max-width: 820px) {
            .pagination .page-link { padding: 4px 8px; font-size: 0.75rem; }
        }

        /* ============================================================ */
        /* MODALS */
        /* ============================================================ */
        .modal-content { border: none; border-radius: var(--medical-radius); box-shadow: 0 24px 60px rgba(0,0,0,0.15); overflow: hidden; }

        .modal-header-custom { padding: 14px 22px; border: none; border-bottom: 1px solid var(--medical-border); }
        .modal-header-custom.success { background: var(--medical-teal); color: #fff; }
        .modal-header-custom.danger { background: var(--danger); color: #fff; }
        .modal-header-custom.primary { background: var(--medical-blue); color: #fff; }
        .modal-header-custom h5 { font-weight: 700; font-size: clamp(15px, 1.1vw, 18px); margin: 0; }
        .modal-header-custom .btn-close { filter: brightness(0) invert(1); }
        .modal-body { padding: 22px; }
        .modal-footer { padding: 14px 22px; border-top: 1px solid var(--medical-border); background: var(--medical-gray-light); }

        .modal-body .form-control,
        .modal-body .form-select {
            border: 1.5px solid var(--medical-border);
            border-radius: var(--medical-radius-sm);
            padding: 8px 12px;
            font-size: 0.88rem;
            background: var(--medical-gray-light);
            color: var(--medical-text);
            transition: var(--medical-transition);
            height: 40px;
            font-weight: 500;
        }

        .modal-body .form-control:focus,
        .modal-body .form-select:focus {
            border-color: var(--medical-blue);
            box-shadow: 0 0 0 3px rgba(26, 107, 138, 0.1);
            background: #fff;
        }

        .modal-body .form-label { font-weight: 700; font-size: 11px; color: var(--medical-text-secondary); margin-bottom: 3px; }
        .modal-body .form-text { color: var(--medical-text-muted); font-size: 0.78rem; }

        .modal-body .section-title { font-weight: 700; font-size: 12px; color: var(--medical-text-secondary); margin-bottom: 10px; }
        .modal-body .section-title i { color: var(--medical-blue); margin-right: 6px; }

        .alert-info-custom { background: var(--medical-blue-light); color: var(--medical-blue); border: none; border-radius: var(--medical-radius-sm); padding: 10px 14px; font-weight: 500; font-size: 0.82rem; }

        @media (max-width: 820px) {
            .modal-body { padding: 16px; }
            .modal-body .form-control, .modal-body .form-select { height: 38px; font-size: 0.84rem; }
        }

        /* ============================================================ */
        /* BOUTONS MODALS & GENERIQUES */
        /* ============================================================ */
        .btn-medical-primary {
            background: var(--medical-blue); color: #fff; border: none;
            padding: 10px 24px; border-radius: var(--medical-radius-sm);
            font-family: var(--font-primary); font-weight: 700; font-size: 0.88rem;
            transition: var(--medical-transition);
            display: inline-flex; align-items: center; gap: 8px;
        }
        .btn-medical-primary:hover { background: var(--medical-blue-dark); transform: translateY(-2px); box-shadow: 0 4px 20px rgba(26,107,138,0.3); color: #fff; }

        .btn-medical-outline {
            background: transparent; border: 1.5px solid var(--medical-border); color: var(--medical-text-secondary);
            padding: 10px 24px; border-radius: var(--medical-radius-sm);
            font-family: var(--font-primary); font-weight: 700; font-size: 0.88rem;
            transition: var(--medical-transition);
            display: inline-flex; align-items: center; gap: 8px;
        }
        .btn-medical-outline:hover { background: var(--medical-gray-light); border-color: var(--medical-blue); color: var(--medical-blue); }

        .btn-medical-danger {
            background: var(--danger); color: #fff; border: none;
            padding: 10px 24px; border-radius: var(--medical-radius-sm);
            font-family: var(--font-primary); font-weight: 700; font-size: 0.88rem;
            transition: var(--medical-transition);
            display: inline-flex; align-items: center; gap: 8px;
        }
        .btn-medical-danger:hover { background: #a93226; transform: translateY(-2px); box-shadow: 0 4px 20px rgba(192,57,43,0.3); color: #fff; }

        /* ============================================================ */
        /* ALERTS */
        /* ============================================================ */
        #alertContainer { margin-bottom: 16px; }
        #alertContainer .alert { border: none; border-radius: var(--medical-radius-sm); padding: 12px 18px; font-weight: 500; border-left: 4px solid transparent; font-size: 0.85rem; }
        #alertContainer .alert-success { background: var(--success-light); color: var(--success); border-left-color: var(--success); }
        #alertContainer .alert-danger { background: var(--danger-light); color: var(--danger); border-left-color: var(--danger); }
        #alertContainer .alert i { font-size: 1.1rem; }

        /* ============================================================ */
        /* RESPONSIVE GLOBAL */
        /* ============================================================ */
        @media (max-width: 1024px) { body { padding: 14px; } }
        @media (max-width: 820px) { body { padding: 10px; } }
        @media (max-height: 700px) and (max-width: 820px) { body { padding: 6px; } }

        /* ============================================================ */
        /* ANIMATIONS & SPINNER & SCROLLBAR */
        /* ============================================================ */
        @keyframes fadeSlideUp {
            from { opacity: 0; transform: translateY(16px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .fade-in { animation: fadeSlideUp 0.5s ease forwards; }
        .fade-in-d1 { animation-delay: 0.04s; }
        .fade-in-d2 { animation-delay: 0.08s; }
        .fade-in-d3 { animation-delay: 0.12s; }

        .loading-spinner { display: inline-block; width: 18px; height: 18px; border: 2.5px solid var(--medical-border); border-top-color: var(--medical-blue); border-radius: 50%; animation: spin 0.8s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }

        .table-wrapper::-webkit-scrollbar { height: 5px; }
        .table-wrapper::-webkit-scrollbar-track { background: var(--medical-gray-light); border-radius: 10px; }
        .table-wrapper::-webkit-scrollbar-thumb { background: var(--medical-border); border-radius: 10px; }
        ::-webkit-scrollbar { width: 5px; }
        ::-webkit-scrollbar-track { background: var(--medical-gray-light); }
        ::-webkit-scrollbar-thumb { background: var(--medical-border); border-radius: 10px; }
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
            <h1>Gestion des <span class="highlight">visiteurs</span></h1>
            <div class="subtitle">
                <i class="bi bi-people"></i>
                Suivi des sessions de connexion
                <span class="dot"></span>
                <i class="bi bi-person-circle"></i> <?php echo htmlspecialchars($user_nom); ?>
                <span class="dot"></span>
                <?php echo getRoleBadge($user_role); ?>
            </div>
        </div>
        <div class="header-actions">
            <button class="btn-header btn-header-primary" data-bs-toggle="modal" data-bs-target="#addModal" title="Nouvelle session">
                <span class="btn-icon"><i class="bi bi-plus-circle"></i></span>
                <span class="btn-label long">Nouvelle session</span>
                <span class="btn-label short">Nouveau</span>
            </button>
        </div>
    </header>

    <!-- Alert Container -->
    <div id="alertContainer"></div>

    <!-- ============================================================ -->
    <!-- STATS -->
    <!-- ============================================================ -->
    <div class="stats-grid">
        <div class="stat-card fade-in">
            <div class="stat-icon blue"><i class="bi bi-people"></i></div>
            <div>
                <div class="stat-value"><?php echo number_format($total); ?></div>
                <div class="stat-label">Total sessions</div>
            </div>
        </div>
        <div class="stat-card fade-in fade-in-d1">
            <div class="stat-icon teal"><i class="bi bi-check-circle"></i></div>
            <div>
                <div class="stat-value"><?php echo number_format($actifs); ?></div>
                <div class="stat-label">Actives</div>
            </div>
        </div>
        <div class="stat-card fade-in fade-in-d2">
            <div class="stat-icon danger"><i class="bi bi-x-circle"></i></div>
            <div>
                <div class="stat-value"><?php echo number_format($inactifs); ?></div>
                <div class="stat-label">Inactives</div>
            </div>
        </div>
        <div class="stat-card fade-in fade-in-d3">
            <div class="stat-icon warning"><i class="bi bi-flag"></i></div>
            <div>
                <div class="stat-value"><?php echo number_format($termines); ?></div>
                <div class="stat-label">Terminées</div>
            </div>
        </div>
        <div class="stat-card fade-in">
            <div class="stat-icon info"><i class="bi bi-clock-history"></i></div>
            <div>
                <div class="stat-value"><?php echo round($moyennes['avg_heures'] ?? 0, 1); ?>h</div>
                <div class="stat-label">Durée moyenne</div>
                <div class="stat-sub"><?php echo round($moyennes['avg_jours'] ?? 0, 1); ?>j en moyenne</div>
            </div>
        </div>
    </div>

    <!-- ============================================================ -->
    <!-- CONNEXIONS PAR JOUR -->
    <!-- ============================================================ -->
    <?php if (!empty($connexions_par_jour)): ?>
    <div class="mini-stats fade-in fade-in-d1">
        <div class="mini-title"><i class="bi bi-bar-chart"></i> Connexions des 7 derniers jours</div>
        <div class="mini-items">
            <?php foreach ($connexions_par_jour as $cj): ?>
                <div class="mini-item">
                    <span class="number"><?php echo $cj['nb']; ?></span>
                    <span class="label"><?php echo date('d/m', strtotime($cj['jour'])); ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- ============================================================ -->
    <!-- FILTRES -->
    <!-- ============================================================ -->
    <form method="POST" action="" class="filter-section fade-in fade-in-d2">
        <div class="filter-title"><i class="bi bi-funnel-fill"></i> Filtres avancés</div>
        <div class="row g-2">
            <div class="col-md-3 col-6">
                <label class="form-label">Recherche</label>
                <input type="text" class="form-control" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="ID, référence...">
            </div>
            <div class="col-md-2 col-3">
                <label class="form-label">Date début</label>
                <input type="date" class="form-control" name="date_debut" value="<?= htmlspecialchars($filter_date_debut) ?>">
            </div>
            <div class="col-md-2 col-3">
                <label class="form-label">Date fin</label>
                <input type="date" class="form-control" name="date_fin" value="<?= htmlspecialchars($filter_date_fin) ?>">
            </div>
            <div class="col-md-2 col-6">
                <label class="form-label">État</label>
                <select name="etat" class="form-select">
                    <option value="">Tous</option>
                    <?php foreach ($etats as $e): ?>
                        <option value="<?= $e ?>" <?= $filter_etat == $e ? 'selected' : '' ?>><?= ucfirst(strtolower($e)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3 col-6">
                <label class="form-label" style="visibility:hidden;">Actions</label>
                <div class="filter-actions">
                    <button type="submit" name="apply_filters" class="btn btn-filter"><i class="bi bi-search"></i> Filtrer</button>
                    <button type="submit" name="reset_filters" class="btn btn-reset"><i class="bi bi-arrow-clockwise"></i> Réinitialiser</button>
                </div>
            </div>
        </div>
        <?php if (!empty($active_filters)): ?>
            <div class="filter-pills">
                <span style="font-size:11px;color:var(--medical-text-muted);font-weight:600;"><i class="bi bi-funnel-fill"></i> Actifs :</span>
                <?php foreach ($active_filters as $f): ?>
                    <span class="filter-pill"><strong><?= $f['label'] ?>:</strong> <?= htmlspecialchars($f['value']) ?></span>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </form>

    <!-- ============================================================ -->
    <!-- TABLEAU -->
    <!-- ============================================================ -->
    <div class="card-modern fade-in fade-in-d3">
        <div class="card-header">
            <h5>
                <i class="bi bi-list-ul" style="color:var(--medical-blue);"></i>
                Sessions
                <span class="badge-count"><?= number_format($total_visiteurs) ?></span>
            </h5>
        </div>
        <div class="card-body p-0">
            <div class="table-wrapper">
                <table class="table table-modern">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Référence</th>
                            <th>Connexion</th>
                            <th>Déconnexion</th>
                            <th>Durée</th>
                            <th>État</th>
                            <th class="text-center" style="width:80px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($visiteurs)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-4" style="color:var(--medical-text-muted);">
                                    <i class="bi bi-inbox d-block mb-1" style="font-size:1.8rem;"></i>
                                    Aucune session trouvée.
                                </td>
                            </tr>
                        <?php else: foreach ($visiteurs as $v):
                            $duree = '';
                            if ($v['duree_date'] > 0) $duree .= $v['duree_date'] . 'j ';
                            if ($v['duree_heure'] > 0) $duree .= $v['duree_heure'] . 'h';
                            if (empty($duree)) $duree = '-';
                        ?>
                            <tr>
                                <td><code style="font-size:12px;color:var(--medical-blue);font-weight:600;"><?= $v['id'] ?></code></td>
                                <td><strong style="font-size:12px;"><?= htmlspecialchars($v['reference']) ?></strong></td>
                                <td style="font-size:12px;">
                                    <div><?= htmlspecialchars($v['date_connexion']) ?></div>
                                    <small style="color:var(--medical-text-muted);"><?= htmlspecialchars($v['heure_connexion']) ?></small>
                                </td>
                                <td style="font-size:12px;">
                                    <?php if (!empty($v['date_deconnexion'])): ?>
                                        <div><?= htmlspecialchars($v['date_deconnexion']) ?></div>
                                        <small style="color:var(--medical-text-muted);"><?= htmlspecialchars($v['heure_deconnexion']) ?></small>
                                    <?php else: ?>
                                        <span style="color:var(--medical-text-muted);">—</span>
                                    <?php endif; ?>
                                </td>
                                <td><span style="font-weight:700;font-size:12px;"><?= $duree ?></span></td>
                                <td><?= getStatusBadge($v['etat_connexion']) ?></td>
                                <td>
                                    <div class="actions-group">
                                        <button class="btn-action edit" onclick="editVisiteur(<?= $v['id'] ?>)" title="Modifier"><i class="bi bi-pencil"></i></button>
                                        <button class="btn-action delete" onclick="deleteVisiteur(<?= $v['id'] ?>, '<?= htmlspecialchars($v['reference']) ?>')" title="Supprimer"><i class="bi bi-trash"></i></button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($total_pages > 1): ?>
            <div class="pagination-wrapper">
                <ul class="pagination mb-0">
                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="page" value="<?= $page - 1 ?>">
                            <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
                            <input type="hidden" name="date_debut" value="<?= htmlspecialchars($filter_date_debut) ?>">
                            <input type="hidden" name="date_fin" value="<?= htmlspecialchars($filter_date_fin) ?>">
                            <input type="hidden" name="etat" value="<?= htmlspecialchars($filter_etat) ?>">
                            <button type="submit" class="page-link" <?= $page <= 1 ? 'disabled' : '' ?>><i class="bi bi-chevron-left"></i></button>
                        </form>
                    </li>
                    <?php for ($pg = max(1, $page - 2); $pg <= min($total_pages, $page + 2); $pg++): ?>
                    <li class="page-item <?= $pg === $page ? 'active' : '' ?>">
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="page" value="<?= $pg ?>">
                            <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
                            <input type="hidden" name="date_debut" value="<?= htmlspecialchars($filter_date_debut) ?>">
                            <input type="hidden" name="date_fin" value="<?= htmlspecialchars($filter_date_fin) ?>">
                            <input type="hidden" name="etat" value="<?= htmlspecialchars($filter_etat) ?>">
                            <button type="submit" class="page-link"><?= $pg ?></button>
                        </form>
                    </li>
                    <?php endfor; ?>
                    <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="page" value="<?= $page + 1 ?>">
                            <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
                            <input type="hidden" name="date_debut" value="<?= htmlspecialchars($filter_date_debut) ?>">
                            <input type="hidden" name="date_fin" value="<?= htmlspecialchars($filter_date_fin) ?>">
                            <input type="hidden" name="etat" value="<?= htmlspecialchars($filter_etat) ?>">
                            <button type="submit" class="page-link" <?= $page >= $total_pages ? 'disabled' : '' ?>><i class="bi bi-chevron-right"></i></button>
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
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header-custom success">
                <h5><i class="bi bi-plus-circle me-2"></i>Nouvelle session</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="addForm">
                <div class="modal-body">
                    <div class="section-title"><i class="bi bi-fingerprint"></i> Connexion</div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Date connexion <span style="color:var(--danger);">*</span></label>
                            <input type="date" class="form-control" name="date_connexion" id="add_date_connexion" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Heure connexion <span style="color:var(--danger);">*</span></label>
                            <input type="time" class="form-control" name="heure_connexion" id="add_heure_connexion" required>
                        </div>
                    </div>

                    <div class="section-title"><i class="bi bi-box-arrow-right"></i> Déconnexion</div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Date déconnexion</label>
                            <input type="date" class="form-control" name="date_deconnexion" id="add_date_deconnexion">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Heure déconnexion</label>
                            <input type="time" class="form-control" name="heure_deconnexion" id="add_heure_deconnexion">
                        </div>
                    </div>
                    <div class="alert-info-custom">
                        <i class="bi bi-info-circle me-1"></i> La durée sera calculée automatiquement si la déconnexion est renseignée.
                    </div>

                    <div class="section-title mt-3"><i class="bi bi-tag"></i> Détails</div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Référence <span style="color:var(--danger);">*</span></label>
                            <input type="text" class="form-control" name="reference" id="add_reference" required placeholder="Ex: REF-001">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">État <span style="color:var(--danger);">*</span></label>
                            <select class="form-select" name="etat_connexion" id="add_etat_connexion" required>
                                <option value="">Choisir...</option>
                                <?php foreach ($etats as $e): ?>
                                    <option value="<?= $e ?>"><?= ucfirst(strtolower($e)) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-medical-outline" data-bs-dismiss="modal"><i class="bi bi-x-lg"></i> Annuler</button>
                    <button type="submit" class="btn-medical-primary"><i class="bi bi-check-lg"></i> Créer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ============================================================ -->
<!-- MODAL MODIFIER -->
<!-- ============================================================ -->
<div class="modal fade" id="editModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header-custom primary">
                <h5><i class="bi bi-pencil-square me-2"></i>Modifier la session</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="editForm">
                <input type="hidden" name="id" id="edit_id">
                <div class="modal-body">
                    <div class="section-title"><i class="bi bi-fingerprint"></i> Connexion</div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Date connexion <span style="color:var(--danger);">*</span></label>
                            <input type="date" class="form-control" name="date_connexion" id="edit_date_connexion" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Heure connexion <span style="color:var(--danger);">*</span></label>
                            <input type="time" class="form-control" name="heure_connexion" id="edit_heure_connexion" required>
                        </div>
                    </div>

                    <div class="section-title"><i class="bi bi-box-arrow-right"></i> Déconnexion</div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Date déconnexion</label>
                            <input type="date" class="form-control" name="date_deconnexion" id="edit_date_deconnexion">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Heure déconnexion</label>
                            <input type="time" class="form-control" name="heure_deconnexion" id="edit_heure_deconnexion">
                        </div>
                    </div>

                    <div class="section-title mt-3"><i class="bi bi-tag"></i> Détails</div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Référence <span style="color:var(--danger);">*</span></label>
                            <input type="text" class="form-control" name="reference" id="edit_reference" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">État <span style="color:var(--danger);">*</span></label>
                            <select class="form-select" name="etat_connexion" id="edit_etat_connexion" required>
                                <?php foreach ($etats as $e): ?>
                                    <option value="<?= $e ?>"><?= ucfirst(strtolower($e)) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-medical-outline" data-bs-dismiss="modal"><i class="bi bi-x-lg"></i> Annuler</button>
                    <button type="submit" class="btn-medical-primary"><i class="bi bi-check-lg"></i> Enregistrer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ============================================================ -->
<!-- MODAL SUPPRIMER -->
<!-- ============================================================ -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header-custom danger">
                <h5><i class="bi bi-trash me-2"></i>Confirmer</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="deleteForm">
                <input type="hidden" name="id" id="delete_id">
                <div class="modal-body text-center" style="padding:24px;">
                    <div style="width:56px;height:56px;border-radius:50%;background:var(--danger-light);color:var(--danger);display:flex;align-items:center;justify-content:center;font-size:24px;margin:0 auto 14px;">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                    </div>
                    <p style="font-weight:700;font-size:14px;color:var(--medical-text);margin-bottom:6px;">Supprimer cette session ?</p>
                    <p style="font-size:12px;color:var(--medical-text-muted);">
                        <strong id="delete_ref" style="color:var(--medical-text);"></strong>
                        <br>Action irréversible.
                    </p>
                </div>
                <div class="modal-footer justify-content-center">
                    <button type="button" class="btn-medical-outline" data-bs-dismiss="modal" style="padding:8px 18px;font-size:0.82rem;"><i class="bi bi-x-lg"></i> Annuler</button>
                    <button type="submit" class="btn-medical-danger" style="padding:8px 18px;font-size:0.82rem;"><i class="bi bi-trash"></i> Supprimer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
    function showAlert(type, message) {
        const container = document.getElementById('alertContainer');
        const icon = type === 'success' ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill';
        container.innerHTML = `
            <div class="alert alert-${type} alert-dismissible fade show">
                <i class="bi ${icon} me-2"></i>${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>`;
        setTimeout(() => { if (container.firstElementChild) container.firstElementChild.remove(); }, 5000);
    }

    // AJOUTER
    document.getElementById('addForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const btn = this.querySelector('button[type="submit"]');
        const original = btn.innerHTML;
        btn.innerHTML = '<span class="loading-spinner"></span>';
        btn.disabled = true;

        const fd = new FormData(this);
        fd.append('ajax_action', '');
        fd.append('btn_ajouter', '1');

        fetch(window.location.href, { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                btn.innerHTML = original;
                btn.disabled = false;
                if (data.success) {
                    showAlert('success', data.message);
                    bootstrap.Modal.getInstance(document.getElementById('addModal')).hide();
                    this.reset();
                    setTimeout(() => location.reload(), 800);
                } else {
                    showAlert('danger', data.message);
                }
            })
            .catch(() => { btn.innerHTML = original; btn.disabled = false; showAlert('danger', 'Erreur réseau.'); });
    });

    // MODIFIER
    function editVisiteur(id) {
        const btn = event.currentTarget;
        btn.innerHTML = '<span class="loading-spinner" style="width:14px;height:14px;border-width:2px;"></span>';

        const fd = new FormData();
        fd.append('ajax_action', 'load_visiteur');
        fd.append('id', id);

        fetch(window.location.href, { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                btn.innerHTML = '<i class="bi bi-pencil"></i>';
                if (data.success) {
                    const d = data.data;
                    document.getElementById('edit_id').value = d.id;
                    document.getElementById('edit_date_connexion').value = d.date_connexion || '';
                    document.getElementById('edit_heure_connexion').value = d.heure_connexion || '';
                    document.getElementById('edit_date_deconnexion').value = d.date_deconnexion || '';
                    document.getElementById('edit_heure_deconnexion').value = d.heure_deconnexion || '';
                    document.getElementById('edit_reference').value = d.reference || '';
                    document.getElementById('edit_etat_connexion').value = d.etat_connexion || '';
                    new bootstrap.Modal(document.getElementById('editModal')).show();
                } else {
                    showAlert('danger', data.message);
                }
            })
            .catch(() => { btn.innerHTML = '<i class="bi bi-pencil"></i>'; showAlert('danger', 'Erreur réseau.'); });
    }

    document.getElementById('editForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const btn = this.querySelector('button[type="submit"]');
        const original = btn.innerHTML;
        btn.innerHTML = '<span class="loading-spinner"></span>';
        btn.disabled = true;

        const fd = new FormData(this);
        fd.append('ajax_action', '');
        fd.append('btn_modifier', '1');

        fetch(window.location.href, { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                btn.innerHTML = original;
                btn.disabled = false;
                if (data.success) {
                    showAlert('success', data.message);
                    bootstrap.Modal.getInstance(document.getElementById('editModal')).hide();
                    setTimeout(() => location.reload(), 800);
                } else {
                    showAlert('danger', data.message);
                }
            })
            .catch(() => { btn.innerHTML = original; btn.disabled = false; showAlert('danger', 'Erreur réseau.'); });
    });

    // SUPPRIMER
    function deleteVisiteur(id, ref) {
        document.getElementById('delete_id').value = id;
        document.getElementById('delete_ref').textContent = ref;
        new bootstrap.Modal(document.getElementById('deleteModal')).show();
    }

    document.getElementById('deleteForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const btn = this.querySelector('button[type="submit"]');
        const original = btn.innerHTML;
        btn.innerHTML = '<span class="loading-spinner" style="width:14px;height:14px;border-width:2px;"></span>';
        btn.disabled = true;

        const fd = new FormData(this);
        fd.append('ajax_action', '');
        fd.append('btn_supprimer', '1');

        fetch(window.location.href, { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                btn.innerHTML = original;
                btn.disabled = false;
                if (data.success) {
                    showAlert('success', data.message);
                    bootstrap.Modal.getInstance(document.getElementById('deleteModal')).hide();
                    setTimeout(() => location.reload(), 800);
                } else {
                    showAlert('danger', data.message);
                }
            })
            .catch(() => { btn.innerHTML = original; btn.disabled = false; showAlert('danger', 'Erreur réseau.'); });
    });
</script>
</body>
</html>