<?php
// ================================================================
// GESTION DES INDICATEURS - EPENCIA SGI
// ================================================================

// ================================================================
// 1. SESSION & AUTHENTIFICATION
// ================================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['utilisateur_id'])) {
    header('Location: ../utilisateur/connexion.php');
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
        'ACTIF' => ['class' => 'success', 'label' => 'Actif'],
        'INACTIF' => ['class' => 'danger', 'label' => 'Inactif'],
        'en attente' => ['class' => 'warning', 'label' => 'En attente'],
        'payé' => ['class' => 'success', 'label' => 'Payé'],
        'impayé' => ['class' => 'danger', 'label' => 'Impayé'],
        'annulé' => ['class' => 'secondary', 'label' => 'Annulé'],
        'partiel' => ['class' => 'warning', 'label' => 'Partiel'],
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

// ================================================================
// 3. HANDLERS AJAX
// ================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');
    
    if ($_POST['ajax_action'] === 'generate_id') {
        $year = date('y');
        $stmt = $pdo->prepare("SELECT id_indicateur FROM indicateur WHERE id_indicateur LIKE ? ORDER BY id_indicateur DESC LIMIT 1");
        $stmt->execute(['IND' . $year . '%']);
        $last = $stmt->fetchColumn();
        $num = $last ? str_pad(intval(substr($last, -4)) + 1, 4, '0', STR_PAD_LEFT) : '0001';
        $id_indicateur = 'IND' . $year . $num;
        echo json_encode(['success' => true, 'id_indicateur' => $id_indicateur]);
        exit;
    }
    
    if ($_POST['ajax_action'] === 'load_indicateur') {
        $id = $_POST['id'];
        $stmt = $pdo->prepare("SELECT i.*, d.titre_domaine FROM indicateur i LEFT JOIN domaine d ON i.id_domaine = d.id_domaine WHERE i.id_indicateur = ?");
        $stmt->execute([$id]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'data' => $data]);
        exit;
    }
}

// ================================================================
// 4. TRAITEMENT CRUD
// ================================================================
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // AJOUTER
        if (isset($_POST['btn_ajouter'])) {
            $id_indicateur = trim($_POST['sai_id_indicateur']);
            $numero = trim($_POST['sai_numero']);
            $titre_indicateur = trim($_POST['sai_titre_indicateur']);
            $id_domaine = trim($_POST['sai_id_domaine']);
            $etat_indicateur = trim($_POST['sai_etat_indicateur']);
            
            if (empty($id_indicateur)) throw new Exception('L\'ID indicateur est obligatoire.');
            if (empty($numero)) throw new Exception('Le numéro est obligatoire.');
            if (empty($titre_indicateur)) throw new Exception('Le titre indicateur est obligatoire.');
            if (empty($id_domaine)) throw new Exception('Le domaine est obligatoire.');
            
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM indicateur WHERE id_indicateur = ?');
            $stmt->execute([$id_indicateur]);
            if ($stmt->fetchColumn() > 0) throw new Exception('Cet ID d\'indicateur est déjà utilisé.');
            
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM indicateur WHERE numero = ?');
            $stmt->execute([$numero]);
            if ($stmt->fetchColumn() > 0) throw new Exception('Ce numéro d\'indicateur est déjà utilisé.');
            
            $req = $pdo->prepare("INSERT INTO indicateur (id_indicateur, numero, titre_indicateur, id_domaine, etat_indicateur) VALUES (?, ?, ?, ?, ?)");
            $req->execute([$id_indicateur, $numero, $titre_indicateur, $id_domaine, $etat_indicateur]);
            
            $message = "Indicateur <strong>$titre_indicateur</strong> créé avec succès !";
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        }
        
        // MODIFIER
        if (isset($_POST['btn_modifier'])) {
            $id_indicateur = $_POST['sai_id_indicateur'];
            $numero = trim($_POST['sai_numero']);
            $titre_indicateur = trim($_POST['sai_titre_indicateur']);
            $id_domaine = trim($_POST['sai_id_domaine']);
            $etat_indicateur = trim($_POST['sai_etat_indicateur']);
            
            if (empty($id_indicateur) || empty($numero) || empty($titre_indicateur) || empty($id_domaine)) {
                throw new Exception('Les champs obligatoires doivent être remplis.');
            }
            
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM indicateur WHERE numero = ? AND id_indicateur != ?');
            $stmt->execute([$numero, $id_indicateur]);
            if ($stmt->fetchColumn() > 0) throw new Exception('Ce numéro d\'indicateur est déjà utilisé.');
            
            $req = $pdo->prepare("UPDATE indicateur SET numero = ?, titre_indicateur = ?, id_domaine = ?, etat_indicateur = ? WHERE id_indicateur = ?");
            $req->execute([$numero, $titre_indicateur, $id_domaine, $etat_indicateur, $id_indicateur]);
            
            $message = 'Indicateur modifié avec succès.';
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        }
        
        // SUPPRIMER
        if (isset($_POST['btn_supprimer'])) {
            $id_indicateur = $_POST['sai_id_indicateur'];
            
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM donnee WHERE id_indicateur = ?');
            $stmt->execute([$id_indicateur]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception("Cet indicateur est utilisé dans des données.");
            }
            
            $req = $pdo->prepare("DELETE FROM indicateur WHERE id_indicateur = ?");
            $req->execute([$id_indicateur]);
            
            $message = 'Indicateur supprimé avec succès.';
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        }

        // RÉINITIALISER LES FILTRES
        if (isset($_POST['reset_filters'])) {
            unset($_SESSION['indicateur_filters']);
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        }
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// ================================================================
// 5. GESTION DES FILTRES ET PAGINATION
// ================================================================
$limit = 10;
$page = max(1, (int)($_POST['page'] ?? $_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;
$search = trim($_POST['search'] ?? $_GET['search'] ?? '');
$filter_etat = trim($_POST['etat'] ?? $_GET['etat'] ?? '');
$filter_domaine = trim($_POST['domaine'] ?? $_GET['domaine'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['apply_filters'])) {
    $_SESSION['indicateur_filters'] = [
        'search' => $search,
        'etat' => $filter_etat,
        'domaine' => $filter_domaine
    ];
} elseif (isset($_SESSION['indicateur_filters']) && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $search = $_SESSION['indicateur_filters']['search'] ?? '';
    $filter_etat = $_SESSION['indicateur_filters']['etat'] ?? '';
    $filter_domaine = $_SESSION['indicateur_filters']['domaine'] ?? '';
    $page = $_SESSION['indicateur_filters']['page'] ?? 1;
    $offset = ($page - 1) * $limit;
}

// LISTES POUR LES FILTRES
$domaines = $pdo->query("SELECT * FROM domaine ORDER BY titre_domaine")->fetchAll(PDO::FETCH_ASSOC);

// COMPTER TOTAL
$count_sql = 'SELECT COUNT(*) FROM indicateur i LEFT JOIN domaine d ON i.id_domaine = d.id_domaine';
$count_params = [];
$where_clauses = [];

if (!empty($search)) {
    $where_clauses[] = '(i.id_indicateur LIKE ? OR i.numero LIKE ? OR i.titre_indicateur LIKE ? OR d.titre_domaine LIKE ?)';
    $search_term = "%$search%";
    $count_params = array_merge($count_params, [$search_term, $search_term, $search_term, $search_term]);
}
if (!empty($filter_etat)) {
    $where_clauses[] = 'i.etat_indicateur = ?';
    $count_params[] = $filter_etat;
}
if (!empty($filter_domaine)) {
    $where_clauses[] = 'i.id_domaine = ?';
    $count_params[] = $filter_domaine;
}
if (!empty($where_clauses)) {
    $count_sql .= ' WHERE ' . implode(' AND ', $where_clauses);
}

$stmt = $pdo->prepare($count_sql);
$stmt->execute($count_params);
$total_indicateurs = $stmt->fetchColumn();
$total_pages = max(1, ceil($total_indicateurs / $limit));

// RÉCUPÉRER LES INDICATEURS
$sql = 'SELECT i.*, d.titre_domaine FROM indicateur i LEFT JOIN domaine d ON i.id_domaine = d.id_domaine';
$params = [];
$where_clauses = [];

if (!empty($search)) {
    $where_clauses[] = '(i.id_indicateur LIKE ? OR i.numero LIKE ? OR i.titre_indicateur LIKE ? OR d.titre_domaine LIKE ?)';
    $search_term = "%$search%";
    $params = array_merge($params, [$search_term, $search_term, $search_term, $search_term]);
}
if (!empty($filter_etat)) {
    $where_clauses[] = 'i.etat_indicateur = ?';
    $params[] = $filter_etat;
}
if (!empty($filter_domaine)) {
    $where_clauses[] = 'i.id_domaine = ?';
    $params[] = $filter_domaine;
}
if (!empty($where_clauses)) {
    $sql .= ' WHERE ' . implode(' AND ', $where_clauses);
}
$sql .= ' ORDER BY i.numero ASC LIMIT ' . (int)$limit . ' OFFSET ' . (int)$offset;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$indicateurs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// STATISTIQUES
$stmt = $pdo->query("SELECT COUNT(*) FROM indicateur");
$total = $stmt->fetchColumn();
$stmt = $pdo->query("SELECT COUNT(*) FROM indicateur WHERE etat_indicateur = 'ACTIF'");
$actifs = $stmt->fetchColumn();
$stmt = $pdo->query("SELECT COUNT(*) FROM indicateur WHERE etat_indicateur = 'INACTIF'");
$inactifs = $stmt->fetchColumn();
$stmt = $pdo->query("SELECT COUNT(DISTINCT id_domaine) FROM indicateur");
$domaines_utilises = $stmt->fetchColumn();
$stmt = $pdo->query("SELECT COUNT(*) FROM donnee");
$total_donnees = $stmt->fetchColumn();

// Générer ID par défaut
$year = date('y');
$stmt = $pdo->prepare("SELECT id_indicateur FROM indicateur WHERE id_indicateur LIKE ? ORDER BY id_indicateur DESC LIMIT 1");
$stmt->execute(['IND' . $year . '%']);
$last = $stmt->fetchColumn();
$num = $last ? str_pad(intval(substr($last, -4)) + 1, 4, '0', STR_PAD_LEFT) : '0001';
$generated_id = 'IND' . $year . $num;

// Filtres actifs
$active_filters = [];
if (!empty($search)) $active_filters[] = ['label' => 'Recherche', 'value' => $search];
if (!empty($filter_etat)) $active_filters[] = ['label' => 'État', 'value' => $filter_etat];
if (!empty($filter_domaine)) {
    $domaine_label = '';
    foreach ($domaines as $d) {
        if ($d['id_domaine'] == $filter_domaine) {
            $domaine_label = $d['titre_domaine'];
            break;
        }
    }
    $active_filters[] = ['label' => 'Domaine', 'value' => $domaine_label ?: $filter_domaine];
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Indicateurs - Epencia SGI</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=DM+Serif+Display:ital@0;1&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
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

        /* ============================================================ */
        /* BOUTON RETOUR                                                */
        /* ============================================================ */
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

        .btn-back-adaptive .btn-icon {
            font-size: 16px;
            flex-shrink: 0;
            display: inline-flex;
            align-items: center;
        }

        .btn-back-adaptive .btn-label { flex-shrink: 0; }

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

        .stat-card .stat-sub {
            font-size: 11px;
            color: var(--medical-text-muted);
            margin-top: 2px;
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
        /* FILTER PILLS                                                */
        /* ============================================================ */
        .filter-pills {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-top: 12px;
        }

        .filter-pill {
            background: var(--medical-blue-light);
            color: var(--medical-blue);
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
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
        /* CODE INPUT - GÉNÉRATION ID                                  */
        /* ============================================================ */
        .code-input-group {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .code-input-group .form-control {
            flex: 1;
            font-family: 'Courier New', monospace;
            font-weight: 600;
            color: var(--medical-blue);
        }

        .btn-generate-id {
            white-space: nowrap;
            background: var(--medical-blue);
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 0.85rem;
            transition: var(--medical-transition);
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-family: var(--font-primary);
            font-weight: 700;
            min-height: 42px;
        }

        .btn-generate-id:hover {
            background: var(--medical-blue-dark);
            color: white;
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

        .modal .text-muted {
            font-size: 11px;
            color: var(--medical-text-muted) !important;
        }

        /* ============================================================ */
        /* SELECT2 OVERRIDE                                            */
        /* ============================================================ */
        .select2-container--default .select2-selection--single {
            border-color: var(--medical-border) !important;
            border-radius: var(--medical-radius-sm) !important;
            height: 42px !important;
            background: var(--medical-gray-light) !important;
            border-width: 1.5px !important;
        }

        .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 42px !important;
            color: var(--medical-text) !important;
            font-weight: 500 !important;
            font-size: 0.9rem !important;
        }

        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 42px !important;
        }

        .select2-container--default.select2-container--open .select2-selection--single {
            border-color: var(--medical-blue) !important;
            box-shadow: 0 0 0 4px rgba(26, 107, 138, 0.1) !important;
            background: #fff !important;
        }

        .select2-dropdown {
            border-color: var(--medical-blue) !important;
            border-radius: var(--medical-radius-sm) !important;
            box-shadow: var(--medical-shadow-hover) !important;
        }

        .select2-results__option--highlighted {
            background: var(--medical-blue) !important;
        }

        /* ============================================================ */
        /* PAGINATION                                                   */
        /* ============================================================ */
        .pagination .page-link {
            color: var(--medical-text-secondary);
            border-color: var(--medical-border);
            cursor: pointer;
            font-weight: 600;
            font-family: var(--font-primary);
            padding: 6px 12px;
        }

        .pagination .page-item.active .page-link {
            background-color: var(--medical-blue);
            border-color: var(--medical-blue);
            color: white;
        }

        .pagination .page-link:hover {
            background-color: var(--medical-gray-light);
            color: var(--medical-blue);
        }

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
            .btn-generate-id { padding: 4px 10px !important; font-size: 0.75rem !important; min-height: 34px !important; }
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
            .pagination .page-link { padding: 4px 8px; font-size: 11px; }
            .code-input-group { flex-wrap: wrap; }
            .btn-generate-id { width: 100%; justify-content: center; min-height: 30px; padding: 4px 8px; font-size: 0.7rem; }
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
            <h1>Gestion des <span class="highlight">indicateurs</span></h1>
            <div class="subtitle">
                <i class="bi bi-bar-chart"></i>
                Consulter, ajouter, modifier et supprimer les indicateurs
                <span class="dot"></span>
                <i class="bi bi-person-circle"></i> <?php echo htmlspecialchars($user_nom); ?>
                <span class="dot"></span>
                <?php echo getRoleBadge($user_role); ?>
            </div>
        </div>
        <div class="header-actions">
            <button class="btn-medical-primary" data-bs-toggle="modal" data-bs-target="#addModal">
                <i class="bi bi-plus-circle"></i> <span>Nouvel indicateur</span>
            </button>
        </div>
    </header>

    <!-- ============================================================ -->
    <!-- MESSAGES                                                     -->
    <!-- ============================================================ -->
    <?php if ($message): ?>
        <div class="alert-medical alert-success alert-dismissible fade show">
            <i class="bi bi-check-circle-fill me-2"></i> <?= $message ?>
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
    <!-- STATISTIQUES                                                 -->
    <!-- ============================================================ -->
    <div class="stats-grid">
        <div class="stat-card fade-in fade-in-d1">
            <div class="stat-icon info"><i class="bi bi-bar-chart"></i></div>
            <div class="stat-content">
                <div class="stat-value"><?= number_format($total) ?></div>
                <div class="stat-label">Total indicateurs</div>
            </div>
        </div>
        <div class="stat-card fade-in fade-in-d2">
            <div class="stat-icon teal"><i class="bi bi-check-circle"></i></div>
            <div class="stat-content">
                <div class="stat-value"><?= number_format($actifs) ?></div>
                <div class="stat-label">Indicateurs actifs</div>
            </div>
        </div>
        <div class="stat-card fade-in fade-in-d3">
            <div class="stat-icon warning"><i class="bi bi-x-circle"></i></div>
            <div class="stat-content">
                <div class="stat-value"><?= number_format($inactifs) ?></div>
                <div class="stat-label">Indicateurs inactifs</div>
            </div>
        </div>
        <div class="stat-card fade-in fade-in-d4">
            <div class="stat-icon blue"><i class="bi bi-tags"></i></div>
            <div class="stat-content">
                <div class="stat-value"><?= number_format($domaines_utilises) ?></div>
                <div class="stat-label">Domaines utilisés</div>
                <div class="stat-sub"><?= $total_donnees ?> données associées</div>
            </div>
        </div>
    </div>

    <!-- ============================================================ -->
    <!-- FILTRES                                                      -->
    <!-- ============================================================ -->
    <form method="POST" action="" id="filterForm" class="filter-section fade-in">
        <div class="filter-title"><i class="bi bi-funnel"></i> Filtres</div>
        <div class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label">Recherche</label>
                <input type="text" class="form-control" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="ID, numéro, titre, domaine...">
            </div>
            <div class="col-md-3">
                <label class="form-label">État</label>
                <select name="etat" class="form-select">
                    <option value="">Tous</option>
                    <option value="ACTIF" <?= $filter_etat == 'ACTIF' ? 'selected' : '' ?>>ACTIF</option>
                    <option value="INACTIF" <?= $filter_etat == 'INACTIF' ? 'selected' : '' ?>>INACTIF</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Domaine</label>
                <select name="domaine" id="filterDomaine" class="form-select">
                    <option value="">Tous</option>
                    <?php foreach ($domaines as $d): ?>
                        <option value="<?= $d['id_domaine'] ?>" <?= $filter_domaine == $d['id_domaine'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($d['titre_domaine']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <div class="d-flex gap-2">
                    <button type="submit" name="apply_filters" class="btn-medical-primary btn-sm-medical" style="width:100%;">
                        <i class="bi bi-search"></i> Filtrer
                    </button>
                    <button type="submit" name="reset_filters" class="btn-medical-outline btn-sm-medical" title="Réinitialiser">
                        <i class="bi bi-arrow-repeat"></i>
                    </button>
                </div>
            </div>
        </div>
        <?php if (!empty($active_filters)): ?>
            <div class="filter-pills">
                <span class="text-muted me-2" style="font-size:12px;"><i class="bi bi-funnel-fill"></i> Filtres actifs :</span>
                <?php foreach ($active_filters as $f): ?>
                    <span class="filter-pill"><strong><?= $f['label'] ?>:</strong> <?= htmlspecialchars($f['value']) ?></span>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </form>

    <!-- ============================================================ -->
    <!-- LISTE DES INDICATEURS                                       -->
    <!-- ============================================================ -->
    <div class="card-modern fade-in">
        <div class="card-header">
            <h5>
                <span class="header-icon blue"><i class="bi bi-list-ul"></i></span>
                Liste des indicateurs
                <span class="badge bg-secondary ms-2"><?= number_format($total_indicateurs) ?></span>
            </h5>
            <?php if (!empty($active_filters)): ?>
                <form method="POST" class="d-inline">
                    <button type="submit" name="reset_filters" class="btn-medical-outline" style="padding:4px 12px;font-size:12px;min-height:28px;">
                        <i class="bi bi-x-circle"></i> Effacer
                    </button>
                </form>
            <?php endif; ?>
        </div>
        <div class="card-body p-0">
            <div class="table-wrapper">
                <table class="table table-dashboard">
                    <thead>
                        <tr>
                            <th style="width:120px;">ID</th>
                            <th style="width:100px;">Numéro</th>
                            <th>Indicateur</th>
                            <th>Domaine</th>
                            <th style="width:100px;">État</th>
                            <th class="text-center" style="width:100px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($indicateurs)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-5" style="color:var(--medical-text-muted);">
                                    <i class="bi bi-inbox" style="font-size:3rem;display:block;margin-bottom:12px;opacity:0.3;"></i>
                                    <?php if ($total == 0): ?>
                                        <strong>Aucun indicateur enregistré.</strong>
                                        <br>
                                        <span style="font-size:13px;">Cliquez sur "Nouvel indicateur" pour en créer un.</span>
                                    <?php else: ?>
                                        <strong>Aucun indicateur trouvé avec ces filtres.</strong>
                                        <br>
                                        <span style="font-size:13px;">Essayez de modifier vos filtres de recherche.</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php else: foreach ($indicateurs as $i): ?>
                            <tr>
                                <td>
                                    <code style="font-size:clamp(11px,0.75vw,13px);color:var(--medical-blue);font-weight:600;background:var(--medical-blue-light);padding:2px 8px;border-radius:4px;">
                                        <?= htmlspecialchars($i['id_indicateur']) ?>
                                    </code>
                                </td>
                                <td><span class="badge bg-info"><?= htmlspecialchars($i['numero']) ?></span></td>
                                <td><strong><?= htmlspecialchars($i['titre_indicateur']) ?></strong></td>
                                <td><?= htmlspecialchars($i['titre_domaine'] ?? '-') ?></td>
                                <td><?= getStatusBadge($i['etat_indicateur']) ?></td>
                                <td>
                                    <div class="actions-group">
                                        <button type="button" class="btn-action edit" data-bs-toggle="modal" data-bs-target="#editModal" 
                                            data-id="<?= $i['id_indicateur'] ?>" title="Modifier">
                                            <i class="bi bi-pencil-square"></i>
                                        </button>
                                        <button type="button" class="btn-action delete" data-bs-toggle="modal" data-bs-target="#deleteModal" 
                                            data-id="<?= $i['id_indicateur'] ?>" 
                                            data-nom="<?= htmlspecialchars($i['titre_indicateur']) ?>" 
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
                <div class="d-flex justify-content-center p-3">
                    <ul class="pagination mb-0">
                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&etat=<?= urlencode($filter_etat) ?>&domaine=<?= urlencode($filter_domaine) ?>">
                                <i class="bi bi-chevron-left"></i>
                            </a>
                        </li>
                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&etat=<?= urlencode($filter_etat) ?>&domaine=<?= urlencode($filter_domaine) ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&etat=<?= urlencode($filter_etat) ?>&domaine=<?= urlencode($filter_domaine) ?>">
                                <i class="bi bi-chevron-right"></i>
                            </a>
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
            <div class="modal-header modal-header-medical success">
                <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>Nouvel indicateur</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="section-title"><i class="bi bi-hash"></i> Identification</div>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label">ID Indicateur <span class="text-danger">*</span></label>
                            <div class="code-input-group">
                                <input type="text" class="form-control" id="add_id_indicateur" name="sai_id_indicateur" 
                                       value="<?= htmlspecialchars($generated_id) ?>" required maxlength="20">
                                <button type="button" id="generateIdBtn" class="btn-generate-id">
                                    <i class="bi bi-arrow-repeat"></i>
                                </button>
                            </div>
                            <small class="text-muted">Format: IND + Année + 4 chiffres</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Numéro <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="sai_numero" required maxlength="20" placeholder="Ex: IND-001">
                        </div>
                    </div>

                    <div class="section-title"><i class="bi bi-tag"></i> Informations</div>
                    <div class="row g-3 mb-4">
                        <div class="col-md-12">
                            <label class="form-label">Titre de l'indicateur <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="sai_titre_indicateur" required maxlength="200" placeholder="Ex: Taux de scolarisation">
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Domaine <span class="text-danger">*</span></label>
                            <select name="sai_id_domaine" id="add_id_domaine" class="form-select" required style="width:100%;">
                                <option value="">-- Sélectionnez un domaine --</option>
                                <?php foreach ($domaines as $d): ?>
                                    <option value="<?= $d['id_domaine'] ?>"><?= htmlspecialchars($d['titre_domaine']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="section-title"><i class="bi bi-toggle-on"></i> Statut</div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">État de l'indicateur <span class="text-danger">*</span></label>
                            <select name="sai_etat_indicateur" class="form-select" required>
                                <option value="ACTIF" selected>ACTIF</option>
                                <option value="INACTIF">INACTIF</option>
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
            <div class="modal-header modal-header-medical">
                <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i>Modifier l'indicateur</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="editForm">
                <div class="modal-body" id="editModalBody">
                    <div class="text-center py-4">
                        <div class="loading-spinner"></div>
                        <p class="mt-2 text-muted">Chargement...</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-medical-outline" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn-medical-primary" style="display:none;" id="editSubmitBtn">
                        <i class="bi bi-check-circle me-1"></i>Enregistrer
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
                    <input type="hidden" name="sai_id_indicateur" id="delete_id">
                    
                    <p style="font-weight:500;color:var(--medical-text);">Voulez-vous vraiment supprimer cet indicateur ?</p>
                    
                    <div style="background:var(--medical-gray-light);padding:14px 18px;border-radius:var(--medical-radius-sm);border-left:4px solid var(--danger);margin-top:12px;">
                        <div style="display:flex;align-items:center;gap:12px;margin-bottom:6px;">
                            <span style="font-weight:600;color:var(--medical-text);">ID :</span>
                            <code style="font-size:13px;color:var(--medical-blue);background:var(--medical-blue-light);padding:2px 8px;border-radius:4px;" id="delete_indicateur_id"></code>
                            <span style="color:var(--medical-text-muted);">|</span>
                            <span style="font-weight:600;color:var(--medical-text);">Nom :</span>
                            <span style="font-weight:500;" id="delete_indicateur_nom"></span>
                        </div>
                    </div>
                    
                    <div class="alert-medical alert-info mt-3" style="font-size:12px;text-align:left;">
                        <i class="bi bi-exclamation-triangle me-1"></i>
                        <strong>Attention :</strong> Cette action est irréversible. Si l'indicateur est utilisé dans des données, la suppression sera bloquée.
                    </div>
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
    $('#add_id_domaine').select2({
        theme: 'default',
        width: '100%',
        placeholder: 'Sélectionnez un domaine',
        allowClear: true,
        dropdownParent: $('#addModal')
    });

    $('#filterDomaine').select2({
        theme: 'default',
        width: '100%',
        placeholder: 'Tous',
        allowClear: true
    });

    // ============================================================
    // GÉNÉRATION D'ID
    // ============================================================
    $('#generateIdBtn').click(function() {
        const btn = $(this);
        btn.html('<span class="loading-spinner"></span>').prop('disabled', true);
        
        $.post(window.location.href, { ajax_action: 'generate_id' }, function(data) {
            if (data.success) {
                $('#add_id_indicateur').val(data.id_indicateur);
            } else {
                alert('Erreur: ' + (data.message || 'Impossible de générer l\'ID'));
            }
        }, 'json').always(function() {
            btn.html('<i class="bi bi-arrow-repeat"></i>').prop('disabled', false);
        });
    });

    // ============================================================
    // CHARGEMENT POUR MODIFICATION
    // ============================================================
    $('#editModal').on('show.bs.modal', function(e) {
        const button = $(e.relatedTarget);
        const id = button.data('id');
        const modal = $(this);
        const body = modal.find('#editModalBody');
        const submitBtn = modal.find('#editSubmitBtn');
        
        body.html(`
            <div class="text-center py-4">
                <div class="loading-spinner"></div>
                <p class="mt-2 text-muted">Chargement de l'indicateur...</p>
            </div>
        `);
        submitBtn.hide();
        
        $.post(window.location.href, { 
            ajax_action: 'load_indicateur', 
            id: id 
        }, function(data) {
            if (data.success) {
                const i = data.data;
                body.html(`
                    <input type="hidden" name="sai_id_indicateur" value="${i.id_indicateur}">
                    
                    <div class="section-title"><i class="bi bi-hash"></i> Identification</div>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label">ID Indicateur</label>
                            <input type="text" class="form-control bg-light" value="${i.id_indicateur}" readonly style="font-family:monospace;">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Numéro <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="sai_numero" value="${i.numero}" required maxlength="20">
                        </div>
                    </div>

                    <div class="section-title"><i class="bi bi-tag"></i> Informations</div>
                    <div class="row g-3 mb-4">
                        <div class="col-md-12">
                            <label class="form-label">Titre de l'indicateur <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="sai_titre_indicateur" value="${i.titre_indicateur}" required maxlength="200">
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Domaine <span class="text-danger">*</span></label>
                            <select name="sai_id_domaine" id="edit_id_domaine" class="form-select" required style="width:100%;">
                                <option value="">-- Sélectionnez un domaine --</option>
                                <?php foreach ($domaines as $d): ?>
                                    <option value="<?= $d['id_domaine'] ?>" ${i.id_domaine === '<?= $d['id_domaine'] ?>' ? 'selected' : ''}>
                                        <?= htmlspecialchars($d['titre_domaine']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="section-title"><i class="bi bi-toggle-on"></i> Statut</div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">État de l'indicateur <span class="text-danger">*</span></label>
                            <select name="sai_etat_indicateur" class="form-select" required>
                                <option value="ACTIF" ${i.etat_indicateur === 'ACTIF' ? 'selected' : ''}>ACTIF</option>
                                <option value="INACTIF" ${i.etat_indicateur === 'INACTIF' ? 'selected' : ''}>INACTIF</option>
                            </select>
                        </div>
                    </div>
                `);
                
                $('#edit_id_domaine').select2({
                    theme: 'default',
                    width: '100%',
                    placeholder: 'Sélectionnez un domaine',
                    allowClear: true,
                    dropdownParent: $('#editModal')
                });
                
                submitBtn.show();
            } else {
                body.html(`
                    <div class="alert-medical alert-danger">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        Erreur lors du chargement de l'indicateur.
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
        const nom = button.data('nom');
        
        $('#delete_id').val(id);
        $('#delete_indicateur_id').text(id);
        $('#delete_indicateur_nom').text(nom);
    });
});
</script>

</body>
</html>