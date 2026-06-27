<?php
// ================================================================
// LISTE DES TRANSACTIONS - EPENCIA SGI
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
        'succes' => ['class' => 'success', 'label' => 'Succès'],
        'echec' => ['class' => 'danger', 'label' => 'Échec'],
        'en attente' => ['class' => 'warning', 'label' => 'En attente'],
        'actif' => ['class' => 'success', 'label' => 'Actif'],
        'inactif' => ['class' => 'danger', 'label' => 'Inactif'],
        'payé' => ['class' => 'success', 'label' => 'Payé'],
        'impayé' => ['class' => 'danger', 'label' => 'Impayé'],
        'annulé' => ['class' => 'secondary', 'label' => 'Annulé'],
        'partiel' => ['class' => 'warning', 'label' => 'Partiel'],
    ];
    $status = strtolower($status ?? 'en attente');
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

function getTypeBadge($type) {
    $typeMap = [
        'entrée' => ['class' => 'success', 'icon' => 'bi-arrow-down-circle'],
        'sortie' => ['class' => 'danger', 'icon' => 'bi-arrow-up-circle']
    ];
    $info = $typeMap[$type] ?? ['class' => 'secondary', 'icon' => 'bi-circle'];
    return '<span class="badge bg-' . $info['class'] . '"><i class="bi ' . $info['icon'] . ' me-1"></i>' . htmlspecialchars($type) . '</span>';
}

// ================================================================
// 3. RÉCUPÉRATION DES DONNÉES
// ================================================================
$sql = "SELECT t.*, u.nom_prenom
        FROM transactions t
        LEFT JOIN utilisateurs u ON t.utilisateur_id = u.utilisateur_id
        ORDER BY t.date DESC, t.heure DESC";

$stmt = $pdo->query($sql);
$transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calcul du total
$total = 0;
foreach ($transactions as $t) {
    $total += (float)($t['montant_total'] ?? 0);
}

// Statistiques
$stmt = $pdo->prepare('SELECT COUNT(*) as total FROM transactions');
$stmt->execute();
$row = $stmt->fetch(PDO::FETCH_ASSOC);
$total_transactions = (int)($row['total'] ?? 0);

$stmt = $pdo->prepare('SELECT COUNT(*) as total FROM transactions WHERE statut = "succes"');
$stmt->execute();
$row = $stmt->fetch(PDO::FETCH_ASSOC);
$total_succes = (int)($row['total'] ?? 0);

$stmt = $pdo->prepare('SELECT COUNT(*) as total FROM transactions WHERE statut = "echec"');
$stmt->execute();
$row = $stmt->fetch(PDO::FETCH_ASSOC);
$total_echec = (int)($row['total'] ?? 0);

$stmt = $pdo->prepare('SELECT COUNT(*) as total FROM transactions WHERE statut = "en attente"');
$stmt->execute();
$row = $stmt->fetch(PDO::FETCH_ASSOC);
$total_attente = (int)($row['total'] ?? 0);

$stmt = $pdo->prepare('SELECT COALESCE(SUM(montant), 0) as total_entrees FROM transactions WHERE type = "entrée" AND statut = "succes"');
$stmt->execute();
$row = $stmt->fetch(PDO::FETCH_ASSOC);
$total_entrees = (float)($row['total_entrees'] ?? 0);

$stmt = $pdo->prepare('SELECT COALESCE(SUM(montant), 0) as total_sorties FROM transactions WHERE type = "sortie" AND statut = "succes"');
$stmt->execute();
$row = $stmt->fetch(PDO::FETCH_ASSOC);
$total_sorties = (float)($row['total_sorties'] ?? 0);

$solde = $total_entrees - $total_sorties;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Liste des transactions - Epencia SGI</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=DM+Serif+Display:ital@0;1&display=swap" rel="stylesheet">
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
            min-width: 1000px;
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

        .montant-entree { color: var(--medical-teal); font-weight: 700; }
        .montant-sortie { color: var(--danger); font-weight: 700; }

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
        .table-wrapper::-webkit-scrollbar { height: 5px; }
        .table-wrapper::-webkit-scrollbar-track { background: var(--medical-gray-light); border-radius: 10px; }
        .table-wrapper::-webkit-scrollbar-thumb { background: var(--medical-border); border-radius: 10px; }
        .table-wrapper::-webkit-scrollbar-thumb:hover { background: var(--medical-text-muted); }

        ::-webkit-scrollbar { width: 5px; }
        ::-webkit-scrollbar-track { background: var(--medical-gray-light); border-radius: 10px; }
        ::-webkit-scrollbar-thumb { background: var(--medical-border); border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: var(--medical-text-muted); }

        /* ============================================================ */
        /* FOOTER TABLE                                                 */
        /* ============================================================ */
        .table-footer-total {
            background: var(--medical-gray-light);
            font-weight: 700;
        }

        .table-footer-total td {
            padding: 12px 16px;
            border-top: 2px solid var(--medical-border);
        }

        .table-footer-total .total-label {
            text-align: right;
            color: var(--medical-text-secondary);
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .table-footer-total .total-value {
            font-size: 16px;
            color: var(--medical-blue);
        }

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
            .btn-medical-primary, .btn-medical-secondary, .btn-medical-outline {
                padding: 6px 12px !important;
                font-size: 0.75rem !important;
                min-height: 34px !important;
                gap: 4px !important;
            }
            .btn-sm-medical { padding: 4px 8px !important; font-size: 0.7rem !important; min-height: 28px !important; }
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
            .header-actions { flex-direction: column; width: 100%; gap: 6px; }
            .header-actions .btn-medical-primary, .header-actions .btn-back-adaptive {
                width: 100% !important; justify-content: center !important; font-size: 0.75rem !important; min-height: 32px !important; padding: 4px 10px !important;
            }
            .header-actions .btn-medical-primary span { display: none !important; }
            .header-actions .btn-back-adaptive .btn-label { display: none !important; }
            .btn-back-adaptive { border-radius: var(--medical-radius-sm) !important; width: 100% !important; height: 32px !important; padding: 4px 10px !important; }
            .btn-medical-primary, .btn-medical-secondary, .btn-medical-outline {
                padding: 4px 8px !important; font-size: 0.65rem !important; min-height: 28px !important; gap: 3px !important;
            }
            .btn-medical-primary i, .btn-medical-secondary i, .btn-medical-outline i { font-size: 0.75rem !important; }
            .btn-sm-medical { padding: 3px 6px !important; font-size: 0.6rem !important; min-height: 24px !important; }
            .card-modern .card-header { padding: 10px 12px; }
            .card-modern .card-header h5 { font-size: 12px; }
            .table-dashboard { font-size: 0.65rem; min-width: 750px; }
            .table-dashboard thead th, .table-dashboard tbody td { padding: 6px 8px; }
            .table-footer-total td { padding: 8px 10px; }
            .table-footer-total .total-value { font-size: 13px; }
        }

        @media (max-width: 400px) {
            .page-header h1 { font-size: 16px; }
            .stats-grid { gap: 6px; }
            .stat-card { padding: 8px 10px; gap: 6px; }
            .stat-card .stat-icon { width: 28px; height: 28px; font-size: 12px; }
            .stat-card .stat-value { font-size: 13px; }
            .btn-medical-primary, .btn-medical-secondary, .btn-medical-outline {
                padding: 3px 6px !important; font-size: 0.55rem !important; min-height: 24px !important;
            }
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
            <h1>Liste des <span class="highlight">transactions</span></h1>
            <div class="subtitle">
                <i class="bi bi-cash-stack"></i>
                Consulter toutes les transactions financières
                <span class="dot"></span>
                <i class="bi bi-person-circle"></i> <?php echo htmlspecialchars($user_nom); ?>
                <span class="dot"></span>
                <?php echo getRoleBadge($user_role); ?>
            </div>
        </div>
        <div class="header-actions">
        </div>
    </header>

    <!-- ============================================================ -->
    <!-- STATISTIQUES                                                 -->
    <!-- ============================================================ -->
    <div class="stats-grid">
        <div class="stat-card fade-in fade-in-d1">
            <div class="stat-icon info"><i class="bi bi-list-check"></i></div>
            <div class="stat-content">
                <div class="stat-value"><?= number_format($total_transactions) ?></div>
                <div class="stat-label">Total transactions</div>
            </div>
        </div>
        <div class="stat-card fade-in fade-in-d2">
            <div class="stat-icon teal"><i class="bi bi-check-circle"></i></div>
            <div class="stat-content">
                <div class="stat-value"><?= number_format($total_succes) ?></div>
                <div class="stat-label">Succès</div>
            </div>
        </div>
        <div class="stat-card fade-in fade-in-d3">
            <div class="stat-icon warning"><i class="bi bi-clock-history"></i></div>
            <div class="stat-content">
                <div class="stat-value"><?= number_format($total_attente) ?></div>
                <div class="stat-label">En attente</div>
            </div>
        </div>
        <div class="stat-card fade-in fade-in-d4">
            <div class="stat-icon <?= $solde >= 0 ? 'blue' : 'warning' ?>"><i class="bi bi-wallet2"></i></div>
            <div class="stat-content">
                <div class="stat-value"><?= number_format($solde, 0, ',', ' ') ?></div>
                <div class="stat-label">Solde</div>
                <div class="stat-sub">FCFA</div>
            </div>
        </div>
    </div>

    <!-- ============================================================ -->
    <!-- LISTE DES TRANSACTIONS                                      -->
    <!-- ============================================================ -->
    <div class="card-modern fade-in">
        <div class="card-header">
            <h5>
                <span class="header-icon blue"><i class="bi bi-list-ul"></i></span>
                Liste des transactions
                <span class="badge bg-secondary ms-2"><?= number_format($total_transactions) ?></span>
            </h5>
        </div>
        <div class="card-body p-0">
            <div class="table-wrapper">
                <table class="table table-dashboard">
                    <thead>
                        <tr>
                            <th>N°</th>
                            <th>Date</th>
                            <th>Heure</th>
                            <th>Type</th>
                            <th>Montant</th>
                            <th>Frais</th>
                            <th>Total</th>
                            <th>Motif</th>
                            <th>Mode</th>
                            <th>Référence</th>
                            <th>Facture</th>
                            <th>Utilisateur</th>
                            <th>Statut</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($transactions)): ?>
                            <tr>
                                <td colspan="13" class="text-center py-5" style="color:var(--medical-text-muted);">
                                    <i class="bi bi-inbox" style="font-size:3rem;display:block;margin-bottom:12px;opacity:0.3;"></i>
                                    Aucune transaction trouvée.
                                </td>
                            </tr>
                        <?php else: foreach ($transactions as $row): 
                            $montant_class = ($row['type'] == 'entrée') ? 'montant-entree' : 'montant-sortie';
                        ?>
                            <tr>
                                <td>
                                    <code style="font-size:clamp(11px,0.75vw,13px);color:var(--medical-blue);font-weight:600;background:var(--medical-blue-light);padding:2px 8px;border-radius:4px;">
                                        <?= htmlspecialchars(substr($row['transaction_id'] ?? '', 0, 15)) ?>
                                    </code>
                                </td>
                                <td><?= !empty($row['date']) ? date('d/m/Y', strtotime($row['date'])) : '-' ?></td>
                                <td><?= htmlspecialchars($row['heure'] ?? '-') ?></td>
                                <td><?= getTypeBadge($row['type'] ?? '') ?></td>
                                <td class="fw-bold <?= $montant_class ?>"><?= number_format($row['montant'] ?? 0, 0, ',', ' ') ?> F</td>
                                <td><?= number_format($row['frais'] ?? 0, 0, ',', ' ') ?> F</td>
                                <td class="fw-bold" style="color:var(--medical-text);"><?= number_format($row['montant_total'] ?? 0, 0, ',', ' ') ?> F</td>
                                <td><?= htmlspecialchars($row['motif'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($row['mode_reglement'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($row['reference_reglement'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($row['facture_id'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($row['nom_prenom'] ?? '-') ?></td>
                                <td><?= getStatusBadge($row['statut'] ?? 'en attente') ?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                    <?php if (!empty($transactions)): ?>
                        <tfoot>
                            <tr class="table-footer-total">
                                <td colspan="6" class="total-label">Montant total</td>
                                <td class="total-value"><?= number_format($total, 0, ',', ' ') ?> F</td>
                                <td colspan="6"></td>
                            </tr>
                        </tfoot>
                    <?php endif; ?>
                </table>
            </div>
        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>