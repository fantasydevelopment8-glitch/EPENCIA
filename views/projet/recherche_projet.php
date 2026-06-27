<?php
// ================================================================
// GESTION DES PROJETS - EPENCIA SGI
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

if (!$is_admin) {
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
        $stmt = $pdo->prepare("SELECT id_projet FROM projet WHERE id_projet LIKE ? ORDER BY id_projet DESC LIMIT 1");
        $stmt->execute(['PRJ' . $year . '%']);
        $last = $stmt->fetchColumn();
        $num = $last ? str_pad(intval(substr($last, -4)) + 1, 4, '0', STR_PAD_LEFT) : '0001';
        $id_projet = 'PRJ' . $year . $num;
        echo json_encode(['success' => true, 'id_projet' => $id_projet]);
        exit;
    }
    
    if ($_POST['ajax_action'] === 'load_projet') {
        $id_projet = $_POST['id_projet'];
        $stmt = $pdo->prepare("SELECT * FROM projet WHERE id_projet = ?");
        $stmt->execute([$id_projet]);
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
            $id_projet = trim($_POST['sai_id_projet']);
            $titre_projet = trim($_POST['sai_titre_projet']);
            $type_projet = trim($_POST['sai_type_projet']);
            $details_projet = trim($_POST['sai_details_projet']);
            $bailleur = trim($_POST['sai_bailleur']);
            $etat_projet = trim($_POST['sai_etat_projet']);
            
            if (empty($id_projet) || empty($titre_projet) || empty($type_projet)) {
                throw new Exception('Les champs obligatoires doivent être remplis.');
            }
            
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM projet WHERE id_projet = ?');
            $stmt->execute([$id_projet]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception('Cet ID de projet est déjà utilisé.');
            }
            
            $req = $pdo->prepare("INSERT INTO projet (id_projet, titre_projet, type_projet, details_projet, bailleur, etat_projet) VALUES (?, ?, ?, ?, ?, ?)");
            $req->execute([$id_projet, $titre_projet, $type_projet, $details_projet, $bailleur, $etat_projet]);
            $message = "Projet <strong>$titre_projet</strong> créé avec succès !";
        }
        
        // MODIFIER
        if (isset($_POST['btn_modifier'])) {
            $id_projet = $_POST['sai_id_projet'];
            $titre_projet = trim($_POST['sai_titre_projet']);
            $type_projet = trim($_POST['sai_type_projet']);
            $details_projet = trim($_POST['sai_details_projet']);
            $bailleur = trim($_POST['sai_bailleur']);
            $etat_projet = trim($_POST['sai_etat_projet']);
            
            if (empty($id_projet) || empty($titre_projet) || empty($type_projet)) {
                throw new Exception('Les champs obligatoires doivent être remplis.');
            }
            
            $req = $pdo->prepare("UPDATE projet SET titre_projet = ?, type_projet = ?, details_projet = ?, bailleur = ?, etat_projet = ? WHERE id_projet = ?");
            $req->execute([$titre_projet, $type_projet, $details_projet, $bailleur, $etat_projet, $id_projet]);
            $message = 'Projet modifié avec succès.';
        }
        
        // SUPPRIMER
        if (isset($_POST['btn_supprimer'])) {
            $id_projet = $_POST['sai_id_projet'];
            
            $tables = ['projet_district', 'projet_domaine', 'projet_tranche', 'donnee', 'utilisateur_projet'];
            $labels = ['districts', 'domaines', 'tranches d\'âge', 'données', 'utilisateurs'];
            
            foreach ($tables as $i => $table) {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM $table WHERE id_projet = ?");
                $stmt->execute([$id_projet]);
                if ($stmt->fetchColumn() > 0) {
                    throw new Exception("Ce projet est associé à des {$labels[$i]}.");
                }
            }
            
            $req = $pdo->prepare("DELETE FROM projet WHERE id_projet = ?");
            $req->execute([$id_projet]);
            $message = 'Projet supprimé avec succès.';
        }
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// ================================================================
// 5. DONNÉES
// ================================================================
$year = date('y');
$stmt = $pdo->prepare("SELECT id_projet FROM projet WHERE id_projet LIKE ? ORDER BY id_projet DESC LIMIT 1");
$stmt->execute(['PRJ' . $year . '%']);
$last = $stmt->fetchColumn();
$num = $last ? str_pad(intval(substr($last, -4)) + 1, 4, '0', STR_PAD_LEFT) : '0001';
$generated_id = 'PRJ' . $year . $num;

$stmt = $pdo->query("SELECT * FROM projet ORDER BY titre_projet ASC");
$projets = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des projets - Epencia SGI</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=DM+Serif+Display:ital@0;1&display=swap" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        /* ============================================================ */
        /* DESIGN MÉDICAL - IDENTIQUE AU DASHBOARD                     */
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
        /* HEADER - IDENTIQUE AU DASHBOARD                             */
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
        /* BOUTON RETOUR ADAPTATIF - IDENTIQUE AU DASHBOARD            */
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

        @media (max-width: 820px) {
            .btn-back-adaptive { padding: 10px 0; width: 42px; height: 42px; border-radius: 50%; }
            .btn-back-adaptive .btn-label { display: none; }
            .btn-back-adaptive .btn-icon { font-size: 18px; }
        }

        @media (min-width: 821px) and (max-width: 1024px) {
            .btn-back-adaptive { padding: 9px 14px; font-size: 0.8rem; gap: 6px; }
            .btn-back-adaptive .btn-label.short { display: inline; }
            .btn-back-adaptive .btn-label.long { display: none; }
        }

        @media (min-width: 1025px) and (max-width: 1366px) {
            .btn-back-adaptive { padding: 10px 18px; font-size: 0.85rem; }
            .btn-back-adaptive .btn-label.short { display: none; }
            .btn-back-adaptive .btn-label.long { display: inline; }
        }

        @media (min-width: 1367px) {
            .btn-back-adaptive { padding: 10px 24px; font-size: 0.9rem; }
            .btn-back-adaptive .btn-label.short { display: none; }
            .btn-back-adaptive .btn-label.long { display: inline; }
        }

        @media (max-height: 700px) and (max-width: 820px) {
            .page-header { padding: 10px 14px; margin-bottom: 12px; }
            .page-header .medical-badge { display: none; }
            .page-header h1 { font-size: 17px; }
            .page-header .subtitle { font-size: 10px; }
            .btn-back-adaptive { width: 36px; height: 36px; }
            .btn-back-adaptive .btn-icon { font-size: 15px; }
        }

        /* ============================================================ */
        /* CARD - IDENTIQUE AU DASHBOARD                               */
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
        .header-icon.warning { background: var(--warning-light); color: var(--warning); }
        .header-icon.success { background: var(--success-light); color: var(--success); }
        .header-icon.danger { background: var(--danger-light); color: var(--danger); }

        .card-modern .card-body { padding: 20px; }

        /* ============================================================ */
        /* TABLE - IDENTIQUE AU DASHBOARD                              */
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
        /* BADGES - IDENTIQUES AU DASHBOARD                            */
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
        /* BOUTONS - IDENTIQUES AU DASHBOARD                           */
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
        }

        .btn-medical-outline:hover {
            background: var(--medical-gray-light);
            border-color: var(--medical-blue);
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
        }

        .btn-generate-id:hover {
            background: var(--medical-blue-dark);
            color: white;
        }

        /* ============================================================ */
        /* ACTIONS - IDENTIQUES AU DASHBOARD                           */
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
        /* ALERTS - IDENTIQUES AU DASHBOARD                            */
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
        /* MODALS - IDENTIQUES AU DASHBOARD                            */
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

        .modal .form-control[readonly] {
            background: var(--medical-gray);
            cursor: not-allowed;
        }

        .modal .form-label {
            font-weight: 700;
            font-size: clamp(11px, 0.75vw, 12px);
            color: var(--medical-text-secondary);
            margin-bottom: 4px;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }

        .form-section-title {
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

        .form-section-title i { color: var(--medical-blue); }

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

        .modal .text-muted {
            font-size: 11px;
            color: var(--medical-text-muted) !important;
        }

        /* ============================================================ */
        /* SPINNER - IDENTIQUE AU DASHBOARD                            */
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
        /* ANIMATIONS - IDENTIQUES AU DASHBOARD                        */
        /* ============================================================ */
        @keyframes fadeSlideUp {
            from { opacity: 0; transform: translateY(16px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .fade-in { animation: fadeSlideUp 0.5s ease forwards; }
        .fade-in-d1 { animation-delay: 0.05s; }
        .fade-in-d2 { animation-delay: 0.10s; }

        /* ============================================================ */
        /* SCROLLBAR - IDENTIQUE AU DASHBOARD                          */
        /* ============================================================ */
        .table-wrapper::-webkit-scrollbar { height: 5px; }
        .table-wrapper::-webkit-scrollbar-track { background: var(--medical-gray-light); border-radius: 10px; }
        .table-wrapper::-webkit-scrollbar-thumb { background: var(--medical-border); border-radius: 10px; }
        .table-wrapper::-webkit-scrollbar-thumb:hover { background: var(--medical-text-muted); }

        ::-webkit-scrollbar { width: 5px; }
        ::-webkit-scrollbar-track { background: var(--medical-gray-light); border-radius: 10px; }
        ::-webkit-scrollbar-thumb { background: var(--medical-border); border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: var(--medical-text-muted); }

        /* ============================================================ */
        /* RESPONSIVE - IDENTIQUE AU DASHBOARD                         */
        /* ============================================================ */
        @media (max-width: 1024px) { body { padding: 14px; } }

        @media (max-width: 820px) {
            body { padding: 10px; }
            .page-header h1 { font-size: 20px; }
            .card-modern .card-body { padding: 16px; }
            .table-dashboard thead th, .table-dashboard tbody td { padding: 8px 10px; }
        }

        @media (max-width: 576px) {
            body { padding: 6px; }
            .page-header h1 { font-size: 18px; }
            .btn-medical-primary, .btn-medical-secondary, .btn-medical-danger, .btn-medical-outline {
                padding: 8px 16px;
                font-size: 0.8rem;
                width: 100%;
                justify-content: center;
            }
            .card-modern .card-header h5 { font-size: 13px; }
            .modal-body { padding: 16px; }
            .code-input-group { flex-wrap: wrap; }
            .btn-generate-id { width: 100%; justify-content: center; }
            .header-actions .btn span { display: none; }
            .header-actions .btn i { font-size: 1.1rem; }
        }

        @media (max-height: 700px) and (max-width: 820px) {
            body { padding: 6px; }
            .page-header { padding: 10px 14px; margin-bottom: 12px; }
            .page-header .medical-badge { display: none; }
            .page-header h1 { font-size: 17px; }
            .page-header .subtitle { font-size: 10px; }
        }
    </style>
</head>
<body>

<div class="app-container">

    <!-- ============================================================ -->
    <!-- HEADER - IDENTIQUE AU DASHBOARD                              -->
    <!-- ============================================================ -->
    <header class="page-header">
        <div class="header-left">
            <div class="medical-badge">
                <i class="bi bi-heart-pulse-fill"></i> Epencia SGI
            </div>
            <h1>Gestion des <span class="highlight">projets</span></h1>
            <div class="subtitle">
                <i class="bi bi-folder2-open"></i>
                Créer, modifier et gérer les projets
                <span class="dot"></span>
                <i class="bi bi-person-circle"></i> <?php echo htmlspecialchars($user_nom); ?>
                <span class="dot"></span>
                <?php echo getRoleBadge($user_role); ?>
            </div>
        </div>
        <div class="header-actions">
            <button class="btn-medical-primary" data-bs-toggle="modal" data-bs-target="#addModal">
                <i class="bi bi-plus-circle"></i> <span>Nouveau projet</span>
            </button>
        </div>
    </header>

    <!-- ============================================================ -->
    <!-- MESSAGES -->
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
    <!-- LISTE DES PROJETS - CARD MODERN                              -->
    <!-- ============================================================ -->
    <div class="card-modern fade-in">
        <div class="card-header">
            <h5>
                <span class="header-icon blue"><i class="bi bi-list-ul"></i></span>
                Liste des projets
                <span class="badge bg-secondary ms-2"><?= count($projets) ?></span>
            </h5>
            <a href="#" class="card-action" style="font-size:12px;color:var(--medical-blue);font-weight:600;cursor:pointer;text-decoration:none;">
                <i class="bi bi-arrow-clockwise"></i> Rafraîchir
            </a>
        </div>
        <div class="card-body p-0">
            <div class="table-wrapper">
                <table class="table table-dashboard">
                    <thead>
                        <tr>
                            <th style="width:120px;">ID</th>
                            <th>Titre</th>
                            <th style="width:120px;">Type</th>
                            <th style="width:140px;">Bailleur</th>
                            <th style="width:80px;">Statut</th>
                            <th class="text-center" style="width:90px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($projets)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-5" style="color:var(--medical-text-muted);">
                                    <i class="bi bi-inbox" style="font-size:3rem;display:block;margin-bottom:12px;opacity:0.3;"></i>
                                    Aucun projet trouvé.
                                </td>
                            </tr>
                        <?php else: foreach ($projets as $p): ?>
                            <tr>
                                <td>
                                    <code style="font-size:clamp(11px,0.75vw,13px);color:var(--medical-blue);font-weight:600;background:var(--medical-blue-light);padding:2px 8px;border-radius:4px;">
                                        <?= escapeHtml($p['id_projet']) ?>
                                    </code>
                                </td>
                                <td>
                                    <strong><?= escapeHtml($p['titre_projet']) ?></strong>
                                    <?php if (!empty($p['details_projet'])): ?>
                                        <br><small style="color:var(--medical-text-muted);font-size:clamp(10px,0.7vw,12px);"><?= escapeHtml(substr($p['details_projet'], 0, 60)) . (strlen($p['details_projet']) > 60 ? '...' : '') ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><span class="badge bg-info"><?= escapeHtml($p['type_projet']) ?></span></td>
                                <td><?= escapeHtml($p['bailleur'] ?: '-') ?></td>
                                <td><?= getStatusBadge($p['etat_projet']) ?></td>
                                <td>
                                    <div class="actions-group">
                                        <button type="button" class="btn-action edit" data-bs-toggle="modal" data-bs-target="#editModal" 
                                            data-id="<?= escapeHtml($p['id_projet']) ?>" title="Modifier">
                                            <i class="bi bi-pencil-square"></i>
                                        </button>
                                        <button type="button" class="btn-action delete" data-bs-toggle="modal" data-bs-target="#deleteModal" 
                                            data-id="<?= escapeHtml($p['id_projet']) ?>" 
                                            data-nom="<?= escapeHtml($p['titre_projet']) ?>" 
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
                <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>Nouveau projet</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="form-section-title">
                        <i class="bi bi-hash"></i> Identification
                    </div>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label">ID Projet <span class="text-danger">*</span></label>
                            <div class="code-input-group">
                                <input type="text" class="form-control" id="add_id_projet" name="sai_id_projet" 
                                       value="<?= $generated_id ?>" required maxlength="20">
                                <button type="button" id="generateIdBtn" class="btn-generate-id">
                                    <i class="bi bi-arrow-repeat"></i>
                                </button>
                            </div>
                            <small class="text-muted">Format: PRJ + Année + 4 chiffres</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Titre du projet <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="sai_titre_projet" required maxlength="100" placeholder="Ex: Projet Santé Maternelle">
                        </div>
                    </div>

                    <div class="form-section-title">
                        <i class="bi bi-info-circle"></i> Informations générales
                    </div>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label">Type de projet <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="sai_type_projet" required maxlength="50" placeholder="Ex: Santé, Éducation, Infrastructure...">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Bailleur</label>
                            <input type="text" class="form-control" name="sai_bailleur" maxlength="100" placeholder="Ex: UNICEF, Banque Mondiale...">
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Détails du projet</label>
                            <textarea class="form-control" name="sai_details_projet" rows="3" placeholder="Description détaillée du projet..." style="height:auto;min-height:80px;resize:vertical;"></textarea>
                        </div>
                    </div>

                    <div class="form-section-title">
                        <i class="bi bi-toggle-on"></i> Statut
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">État du projet <span class="text-danger">*</span></label>
                            <select name="sai_etat_projet" class="form-select" required>
                                <option value="ACTIF">ACTIF</option>
                                <option value="INACTIF">INACTIF</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-medical-outline" data-bs-dismiss="modal">
                        <i class="bi bi-x-lg"></i> Annuler
                    </button>
                    <button type="submit" name="btn_ajouter" class="btn-medical-secondary">
                        <i class="bi bi-check-lg"></i> Ajouter
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
                <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i>Modifier le projet</h5>
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
                    <button type="button" class="btn-medical-outline" data-bs-dismiss="modal">
                        <i class="bi bi-x-lg"></i> Annuler
                    </button>
                    <button type="submit" name="btn_modifier" class="btn-medical-primary">
                        <i class="bi bi-check-lg"></i> Enregistrer
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
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header modal-header-medical danger">
                <h5 class="modal-title"><i class="bi bi-trash me-2"></i>Confirmer</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body text-center" style="padding:24px;">
                    <input type="hidden" name="sai_id_projet" id="delete_id">

                    <div style="width:64px;height:64px;border-radius:50%;background:var(--danger-light);color:var(--danger);display:flex;align-items:center;justify-content:center;font-size:28px;margin:0 auto 16px;">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                    </div>
                    <p style="font-weight:600;font-size:15px;color:var(--medical-text);margin-bottom:8px;">Supprimer ce projet ?</p>
                    <p style="font-size:13px;color:var(--medical-text-muted);">
                        <strong id="delete_projet_nom" style="color:var(--medical-text);"></strong>
                        <br><span id="delete_projet_id" style="font-family:monospace;font-size:12px;color:var(--medical-text-muted);"></span>
                    </p>
                    <div class="alert-medical alert-info mt-3" style="font-size:12px;text-align:left;">
                        <i class="bi bi-exclamation-triangle me-1"></i>
                        Cette action est irréversible. Toutes les données liées seront supprimées.
                    </div>
                </div>
                <div class="modal-footer justify-content-center">
                    <button type="button" class="btn-medical-outline" data-bs-dismiss="modal" style="padding:8px 20px;font-size:13px;">
                        <i class="bi bi-x-lg"></i> Annuler
                    </button>
                    <button type="submit" name="btn_supprimer" class="btn-medical-danger" style="padding:8px 20px;font-size:13px;">
                        <i class="bi bi-trash"></i> Supprimer
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
    // GÉNÉRATION D'ID
    // ============================================================
    $('#generateIdBtn').click(function() {
        const btn = $(this);
        btn.html('<span class="loading-spinner"></span>').prop('disabled', true);
        
        $.post(window.location.href, { ajax_action: 'generate_id' }, function(data) {
            if (data.success) {
                $('#add_id_projet').val(data.id_projet);
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
        
        body.html(`
            <div class="text-center py-4">
                <div class="loading-spinner"></div>
                <p class="mt-2 text-muted">Chargement du projet...</p>
            </div>
        `);
        
        $.post(window.location.href, { 
            ajax_action: 'load_projet', 
            id_projet: id 
        }, function(data) {
            if (data.success) {
                const p = data.data;
                body.html(`
                    <input type="hidden" name="sai_id_projet" value="${escapeHtml(p.id_projet)}">
                    
                    <div class="form-section-title">
                        <i class="bi bi-hash"></i> Identification
                    </div>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label">ID Projet</label>
                            <input type="text" class="form-control" value="${escapeHtml(p.id_projet)}" readonly style="font-family:monospace;font-weight:600;">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Titre du projet <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="sai_titre_projet" value="${escapeHtml(p.titre_projet)}" required maxlength="100">
                        </div>
                    </div>

                    <div class="form-section-title">
                        <i class="bi bi-info-circle"></i> Informations générales
                    </div>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label">Type de projet <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="sai_type_projet" value="${escapeHtml(p.type_projet)}" required maxlength="50">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Bailleur</label>
                            <input type="text" class="form-control" name="sai_bailleur" value="${escapeHtml(p.bailleur || '')}" maxlength="100">
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Détails du projet</label>
                            <textarea class="form-control" name="sai_details_projet" rows="3" style="height:auto;min-height:80px;resize:vertical;">${escapeHtml(p.details_projet || '')}</textarea>
                        </div>
                    </div>

                    <div class="form-section-title">
                        <i class="bi bi-toggle-on"></i> Statut
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">État du projet <span class="text-danger">*</span></label>
                            <select name="sai_etat_projet" class="form-select" required>
                                <option value="ACTIF" ${p.etat_projet === 'ACTIF' ? 'selected' : ''}>ACTIF</option>
                                <option value="INACTIF" ${p.etat_projet === 'INACTIF' ? 'selected' : ''}>INACTIF</option>
                            </select>
                        </div>
                    </div>
                `);
            } else {
                body.html(`
                    <div class="alert-medical alert-danger">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        Erreur lors du chargement du projet.
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
        $('#delete_projet_id').text(id);
        $('#delete_projet_nom').text(nom);
    });

    // ============================================================
    // UTILITAIRE ESCAPE HTML
    // ============================================================
    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.appendChild(document.createTextNode(text));
        return div.innerHTML;
    }

    // ============================================================
    // RAFRAÎCHIR
    // ============================================================
    $('.card-action').click(function(e) {
        e.preventDefault();
        location.reload();
    });
});
</script>

</body>
</html>