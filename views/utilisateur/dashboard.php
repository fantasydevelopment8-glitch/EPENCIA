<?php
// ============================================================
// dashboard.php - Tableau de bord Epencia SGI
// ============================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['utilisateur_id'])) {
    header('Location: connexion.php');
    exit;
}

require_once 'database/database.php';

 $user_role = $_SESSION['role'] ?? '';
 $user_nom = $_SESSION['nom_prenom'] ?? 'Utilisateur';
 $user_id = $_SESSION['utilisateur_id'] ?? '';
 $organisme_id_user = $_SESSION['organisme_id'] ?? '';

// ============================================================
// STATISTIQUES GLOBALES
// ============================================================

try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM clients WHERE statut = 'actif'");
    $total_clients_actifs = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM clients WHERE statut = 'inactif'");
    $total_clients_inactifs = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM clients");
    $total_clients = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT sexe, COUNT(*) as total FROM clients WHERE statut = 'actif' GROUP BY sexe");
    $clients_sexe = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $clients_sexe = array_column($clients_sexe, 'total', 'sexe');
    $clients_hommes = $clients_sexe['masculin'] ?? 0;
    $clients_femmes = $clients_sexe['feminin'] ?? 0;
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM factures");
    $total_factures = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT statut, COUNT(*) as count FROM factures GROUP BY statut");
    $factures_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $factures_stats = array_column($factures_stats, 'count', 'statut');
    
    $factures_payees = $factures_stats['payé'] ?? 0;
    $factures_impayees = $factures_stats['impayé'] ?? 0;
    $factures_attente = $factures_stats['en attente'] ?? 0;
    $factures_annulees = $factures_stats['annulé'] ?? 0;
    $factures_partiel = $factures_stats['partiel'] ?? 0;
    
    $stmt = $pdo->query("SELECT COALESCE(SUM(CAST(montant_total AS DECIMAL(10,2))), 0) FROM factures WHERE statut = 'payé'");
    $montant_factures_payees = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COALESCE(SUM(CAST(montant_total AS DECIMAL(10,2))), 0) FROM factures WHERE statut = 'impayé' OR statut = 'en attente'");
    $montant_factures_impayees = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM produits WHERE statut = 'actif'");
    $total_produits = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT type, COUNT(*) as total FROM produits WHERE statut = 'actif' GROUP BY type");
    $produits_types = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $produits_types = array_column($produits_types, 'total', 'type');
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM organismes WHERE statut = 'actif'");
    $total_organismes = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT type, COUNT(*) as total FROM organismes WHERE statut = 'actif' GROUP BY type");
    $organismes_types = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $organismes_types = array_column($organismes_types, 'total', 'type');
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM commandes WHERE statut = 'actif'");
    $total_commandes = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COALESCE(SUM(CAST(montant AS DECIMAL(10,2))), 0) FROM commandes WHERE statut = 'actif'");
    $ca_total = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COALESCE(SUM(CAST(montant AS DECIMAL(10,2))), 0) FROM commandes WHERE statut = 'actif' AND MONTH(date_creation) = MONTH(CURDATE()) AND YEAR(date_creation) = YEAR(CURDATE())");
    $ca_mois = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COALESCE(SUM(CAST(montant AS DECIMAL(10,2))), 0) FROM commandes WHERE statut = 'actif' AND DATE(date_creation) = CURDATE()");
    $ca_jour = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM transactions WHERE statut = 'succes'");
    $total_transactions = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM transactions WHERE statut = 'succes' AND DATE(date) = CURDATE()");
    $transactions_jour = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM utilisateurs WHERE etat = 'actif'");
    $total_utilisateurs = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT role, COUNT(*) as total FROM utilisateurs WHERE etat = 'actif' GROUP BY role");
    $utilisateurs_roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $utilisateurs_roles = array_column($utilisateurs_roles, 'total', 'role');
    
    $stmt = $pdo->query("
        SELECT f.*, c.nom_prenom as client_nom 
        FROM factures f 
        LEFT JOIN clients c ON f.client_id = c.client_id 
        ORDER BY f.date DESC, f.heure DESC 
        LIMIT 8
    ");
    $dernieres_factures = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $stmt = $pdo->query("
        SELECT c.client_id, c.nom_prenom, c.telephone, 
               COALESCE(SUM(CAST(f.montant_total AS DECIMAL(10,2))), 0) as total_achats,
               COUNT(f.facture_id) as nb_factures
        FROM clients c
        LEFT JOIN factures f ON c.client_id = f.client_id AND f.statut = 'payé'
        WHERE c.statut = 'actif'
        GROUP BY c.client_id
        ORDER BY total_achats DESC
        LIMIT 5
    ");
    $top_clients = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $stmt = $pdo->query("
        SELECT log_id, client_id, date_connexion, type, status, ip_address
        FROM connexions_log 
        ORDER BY date_connexion DESC 
        LIMIT 8
    ");
    $activites_recentes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $stmt = $pdo->query("
        SELECT client_id, nom_prenom, telephone, statut, date_naissance
        FROM clients 
        ORDER BY date_naissance DESC 
        LIMIT 6
    ");
    $derniers_clients = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $total_clients_actifs = $total_clients_inactifs = $total_clients = 0;
    $clients_hommes = $clients_femmes = 0;
    $total_factures = $factures_payees = $factures_impayees = $factures_attente = $factures_annulees = $factures_partiel = 0;
    $montant_factures_payees = $montant_factures_impayees = 0;
    $total_produits = 0;
    $produits_types = [];
    $total_organismes = 0;
    $organismes_types = [];
    $total_commandes = $ca_total = $ca_mois = $ca_jour = 0;
    $total_transactions = $transactions_jour = 0;
    $total_utilisateurs = 0;
    $utilisateurs_roles = [];
    $dernieres_factures = $top_clients = $activites_recentes = $derniers_clients = [];
}

 $taux_paiement = $total_factures > 0 ? round(($factures_payees / $total_factures) * 100) : 0;

function getStatusBadge($status) {
    $statusMap = [
        'actif' => ['class' => 'success', 'label' => 'Actif'],
        'inactif' => ['class' => 'danger', 'label' => 'Inactif'],
        'en attente' => ['class' => 'warning', 'label' => 'En attente'],
        'payé' => ['class' => 'success', 'label' => 'Payé'],
        'payee' => ['class' => 'success', 'label' => 'Payé'],
        'impayé' => ['class' => 'danger', 'label' => 'Impayé'],
        'impayee' => ['class' => 'danger', 'label' => 'Impayé'],
        'annulé' => ['class' => 'secondary', 'label' => 'Annulé'],
        'annulee' => ['class' => 'secondary', 'label' => 'Annulé'],
        'partiel' => ['class' => 'warning', 'label' => 'Partiel'],
        'succes' => ['class' => 'success', 'label' => 'Succès'],
        'echec' => ['class' => 'danger', 'label' => 'Échec'],
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
    <title>Tableau de bord - Epencia SGI</title>
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

        .app-container {
            max-width: 1920px;
            margin: 0 auto;
        }

        /* ============================================================ */
        /* HEADER ADAPTATIF 800x600 → 1960x1600 */
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
        /* HEADER ACTIONS - SYSTÈME ADAPTATIF */
        /* ============================================================ */
        .header-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            align-items: center;
            flex-shrink: 0;
        }

        .search-box {
            display: flex;
            align-items: center;
            background: var(--medical-gray-light);
            padding: 9px 16px;
            border-radius: var(--medical-radius-sm);
            border: 1.5px solid var(--medical-border);
            transition: var(--medical-transition);
            gap: 8px;
        }

        .search-box:focus-within {
            border-color: var(--medical-blue);
            box-shadow: 0 0 0 4px rgba(26, 107, 138, 0.1);
            background: var(--medical-white);
        }

        .search-box input {
            border: none; outline: none;
            background: transparent;
            font-family: var(--font-primary);
            font-size: 0.88rem;
            color: var(--medical-text);
            width: 220px;
            font-weight: 500;
        }

        .search-box input::placeholder {
            color: var(--medical-text-muted);
            font-weight: 400;
        }

        .search-box i {
            color: var(--medical-text-muted);
            font-size: 16px;
            flex-shrink: 0;
        }

        .notification-btn {
            position: relative;
            width: 42px; height: 42px;
            border-radius: 50%;
            background: var(--medical-white);
            border: 1.5px solid var(--medical-border);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--medical-text-secondary);
            font-size: 18px;
            cursor: pointer;
            transition: var(--medical-transition);
            box-shadow: var(--medical-shadow);
            flex-shrink: 0;
        }

        .notification-btn:hover {
            border-color: var(--medical-blue);
            color: var(--medical-blue);
            transform: scale(1.05);
        }

        .notification-btn .notif-dot {
            position: absolute;
            top: 8px; right: 8px;
            width: 10px; height: 10px;
            background: var(--danger);
            border-radius: 50%;
            border: 2px solid var(--medical-white);
        }

        /* ----- PALIER 1 : ≤ 820px (800x600) ----- */
        @media (max-width: 820px) {
            .page-header {
                padding: 12px 14px;
                gap: 10px;
            }
            .page-header .header-left {
                flex: 1 1 100%;
            }
            .header-actions {
                width: 100%;
                justify-content: stretch;
            }
            .search-box {
                flex: 1 1 0;
                padding: 9px 12px;
            }
            .search-box input {
                width: 100%;
                font-size: 0.84rem;
            }
            .search-box .search-label-full {
                display: none;
            }
            .notification-btn {
                width: 42px; height: 42px;
            }
        }

        /* ----- PALIER 2 : 821 → 1024px ----- */
        @media (min-width: 821px) and (max-width: 1024px) {
            .page-header { padding: 14px 18px; }
            .search-box input { width: 160px; font-size: 0.84rem; }
        }

        /* ----- PALIER 3 : 1025 → 1366px ----- */
        @media (min-width: 1025px) and (max-width: 1366px) {
            .search-box input { width: 200px; }
        }

        /* ----- PALIER 4 : 1367 → 1960px ----- */
        @media (min-width: 1367px) {
            .search-box input { width: 260px; }
        }

        /* ----- HAUTEUR < 700px ----- */
        @media (max-height: 700px) and (max-width: 820px) {
            .page-header {
                padding: 8px 12px;
                margin-bottom: 12px;
            }
            .page-header .header-left .medical-badge {
                display: none;
            }
            .page-header h1 { font-size: 17px; }
            .page-header .subtitle { font-size: 10px; }
            .search-box { padding: 7px 10px; min-height: 34px; }
            .notification-btn { width: 34px; height: 34px; font-size: 15px; }
            .notification-btn .notif-dot { width: 8px; height: 8px; top: 6px; right: 6px; }
        }

        /* ============================================================ */
        /* STATS CARDS */
        /* ============================================================ */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 14px;
            margin-bottom: 20px;
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
        .stat-icon.danger { background: var(--danger-light); color: var(--danger); }
        .stat-icon.purple { background: #f0ebf8; color: #6a0dad; }
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

        .stat-card .stat-change {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-size: 10px;
            font-weight: 700;
            margin-top: 4px;
            padding: 2px 8px;
            border-radius: 20px;
        }

        .stat-card .stat-change.up { color: var(--medical-teal); background: var(--medical-teal-light); }
        .stat-card .stat-change.down { color: var(--danger); background: var(--danger-light); }

        /* ============================================================ */
        /* KPI ROW */
        /* ============================================================ */
        .kpi-row {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 12px;
            margin-bottom: 24px;
        }

        .kpi-item {
            background: var(--medical-white);
            border-radius: var(--medical-radius-sm);
            padding: 14px 16px;
            border: 1px solid var(--medical-border);
            text-align: center;
            transition: var(--medical-transition);
            box-shadow: var(--medical-shadow);
        }

        .kpi-item:hover {
            transform: translateY(-3px);
            box-shadow: var(--medical-shadow-hover);
        }

        .kpi-item .kpi-icon { font-size: 22px; display: block; margin-bottom: 4px; }
        .kpi-item .kpi-value {
            font-size: clamp(16px, 1.5vw, 22px);
            font-weight: 800;
            color: var(--medical-text);
            line-height: 1.2;
        }
        .kpi-item .kpi-value.green { color: var(--medical-teal); }
        .kpi-item .kpi-value.gold { color: var(--warning); }
        .kpi-item .kpi-value.blue { color: var(--medical-blue); }
        .kpi-item .kpi-value.red { color: var(--danger); }
        .kpi-item .kpi-value.purple { color: #6a0dad; }

        .kpi-item .kpi-label {
            font-size: 9px;
            color: var(--medical-text-muted);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            font-weight: 700;
            margin-top: 2px;
        }

        /* ============================================================ */
        /* CARDS */
        /* ============================================================ */
        .card-modern {
            background: var(--medical-white);
            border: none;
            border-radius: var(--medical-radius);
            box-shadow: var(--medical-shadow);
            overflow: hidden;
            border: 1px solid var(--medical-border);
            height: 100%;
            transition: box-shadow 0.3s ease;
        }

        .card-modern:hover { box-shadow: var(--medical-shadow-hover); }

        .card-modern .card-header {
            background: var(--medical-gray-light);
            border-bottom: 1px solid var(--medical-border);
            padding: 14px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 8px;
        }

        .card-modern .card-header h5 {
            font-size: clamp(12px, 0.95vw, 15px);
            font-weight: 700;
            margin: 0;
            color: var(--medical-text);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .card-modern .card-header .header-icon {
            width: 30px; height: 30px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
        }

        .card-action {
            font-size: 12px;
            color: var(--medical-blue);
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: color 0.2s;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .card-action:hover { color: var(--medical-blue-dark); }
        .card-modern .card-body { padding: 16px 20px 20px; }
        .card-modern .card-body.p-0 { padding: 0; }

        /* ============================================================ */
        /* TABLEAUX */
        /* ============================================================ */
        .table-dashboard {
            font-size: clamp(0.72rem, 0.78vw, 0.85rem);
            margin-bottom: 0;
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
        /* AVATAR */
        /* ============================================================ */
        .avatar {
            width: 32px; height: 32px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 11px;
            color: #fff;
            flex-shrink: 0;
        }

        .avatar.blue { background: linear-gradient(135deg, var(--medical-blue), #4a9bbf); }
        .avatar.teal { background: linear-gradient(135deg, var(--medical-teal), #5ab8ab); }
        .avatar.warning { background: linear-gradient(135deg, var(--warning), #e8c56a); }
        .avatar.danger { background: linear-gradient(135deg, var(--danger), #e8746a); }
        .avatar.purple { background: linear-gradient(135deg, #6a0dad, #a855f7); }
        .avatar.info { background: linear-gradient(135deg, var(--info), #6bb8e8); }

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
        .badge.bg-info { background: var(--info-light) !important; color: var(--info) !important; }
        .badge.bg-secondary { background: var(--medical-gray) !important; color: var(--medical-text-secondary) !important; }

        /* ============================================================ */
        /* ACTIVITÉS */
        /* ============================================================ */
        .activity-list { list-style: none; padding: 0; margin: 0; }

        .activity-list li {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 10px 0;
            border-bottom: 1px solid var(--medical-border);
        }

        .activity-list li:last-child { border-bottom: none; }

        .activity-dot {
            width: 10px; height: 10px;
            border-radius: 50%;
            margin-top: 5px;
            flex-shrink: 0;
        }

        .activity-dot.success { background: var(--medical-teal); }
        .activity-dot.warning { background: var(--warning); }
        .activity-dot.danger { background: var(--danger); }
        .activity-dot.info { background: var(--medical-blue); }

        .activity-content { flex: 1; min-width: 0; }

        .activity-text {
            font-size: clamp(11px, 0.72vw, 13px);
            font-weight: 500;
            color: var(--medical-text);
        }

        .activity-text .hl { color: var(--medical-blue); font-weight: 600; }

        .activity-time {
            font-size: 10px;
            color: var(--medical-text-muted);
            font-weight: 500;
        }

        /* ============================================================ */
        /* TOP CLIENTS BARRES */
        /* ============================================================ */
        .top-client-bar { margin-bottom: 12px; }
        .top-client-bar:last-child { margin-bottom: 0; }

        .top-client-bar .bar-label {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 3px;
            font-size: clamp(11px, 0.72vw, 13px);
        }

        .top-client-bar .bar-label .client-name {
            font-weight: 600;
            color: var(--medical-text);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 130px;
        }

        .top-client-bar .bar-label .client-rank {
            font-weight: 800;
            color: var(--warning);
            margin-right: 4px;
        }

        .top-client-bar .bar-label .client-amount {
            font-weight: 700;
            color: var(--medical-text);
            font-size: clamp(11px, 0.72vw, 13px);
        }

        .top-client-bar .bar-track {
            background: var(--medical-gray);
            border-radius: 4px;
            height: 6px;
            overflow: hidden;
        }

        .top-client-bar .bar-track .bar-fill {
            height: 100%;
            border-radius: 4px;
            transition: width 0.8s ease;
            background: linear-gradient(90deg, var(--warning), #f5d08a);
        }

        /* ============================================================ */
        /* MINI STATS */
        /* ============================================================ */
        .mini-stat {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 6px 0;
        }

        .mini-stat .ms-icon {
            width: 38px; height: 38px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 15px;
            flex-shrink: 0;
        }

        .mini-stat .ms-icon.blue { background: var(--medical-blue-light); color: var(--medical-blue); }
        .mini-stat .ms-icon.teal { background: var(--medical-teal-light); color: var(--medical-teal); }
        .mini-stat .ms-icon.warning { background: var(--warning-light); color: var(--warning); }
        .mini-stat .ms-icon.purple { background: #f0ebf8; color: #6a0dad; }
        .mini-stat .ms-icon.danger { background: var(--danger-light); color: var(--danger); }

        .mini-stat .ms-info { flex: 1; min-width: 0; }
        .mini-stat .ms-info h6 { font-size: clamp(13px, 0.9vw, 16px); font-weight: 700; margin: 0; color: var(--medical-text); }
        .mini-stat .ms-info small { font-size: 11px; color: var(--medical-text-muted); font-weight: 500; }

        /* ============================================================ */
        /* FOOTER */
        /* ============================================================ */
        .dashboard-footer {
            text-align: center;
            margin-top: 32px;
            padding-top: 20px;
            border-top: 1px solid var(--medical-border);
            font-size: clamp(10px, 0.75vw, 13px);
            color: var(--medical-text-muted);
        }

        .dashboard-footer .sep {
            display: inline-block;
            width: 4px; height: 4px;
            border-radius: 50%;
            background: var(--medical-text-muted);
            margin: 0 8px;
        }

        /* ============================================================ */
        /* RESPONSIVE GLOBAL */
        /* ============================================================ */
        @media (max-width: 1366px) {
            .stats-grid { grid-template-columns: repeat(3, 1fr); }
            .kpi-row { grid-template-columns: repeat(3, 1fr); }
        }

        @media (max-width: 1024px) {
            body { padding: 14px; }
            .stats-grid { grid-template-columns: repeat(3, 1fr); gap: 10px; }
            .kpi-row { grid-template-columns: repeat(3, 1fr); gap: 10px; }
            .stat-card { padding: 12px 14px; gap: 10px; }
            .stat-card .stat-icon { width: 38px; height: 38px; font-size: 16px; }
        }

        @media (max-width: 820px) {
            body { padding: 10px; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); gap: 8px; }
            .kpi-row { grid-template-columns: repeat(2, 1fr); gap: 8px; }
            .stat-card { padding: 10px 12px; gap: 10px; }
            .stat-card .stat-icon { width: 34px; height: 34px; font-size: 14px; border-radius: 10px; }
            .stat-card .stat-value { font-size: 16px; }
            .stat-card .stat-label { font-size: 10px; }
            .stat-card .stat-change { font-size: 9px; padding: 1px 6px; }
            .kpi-item { padding: 10px 12px; }
            .kpi-item .kpi-value { font-size: 15px; }
            .kpi-item .kpi-icon { font-size: 18px; }
            .kpi-item .kpi-label { font-size: 8px; }
            .card-modern .card-header { padding: 12px 14px; }
            .card-modern .card-body { padding: 12px 14px 16px; }
            .table-dashboard thead th, .table-dashboard tbody td { padding: 8px 10px; }
        }

        @media (max-height: 700px) and (max-width: 820px) {
            body { padding: 6px; }
            .stats-grid { margin-bottom: 10px; gap: 6px; }
            .kpi-row { margin-bottom: 10px; gap: 6px; }
            .stat-card { padding: 8px 10px; gap: 8px; }
            .stat-card .stat-icon { width: 28px; height: 28px; font-size: 12px; }
            .stat-card .stat-value { font-size: 14px; }
            .stat-card .stat-label { font-size: 9px; }
            .stat-card .stat-change { display: none; }
            .kpi-item { padding: 8px 10px; }
            .kpi-item .kpi-value { font-size: 13px; }
            .kpi-item .kpi-icon { font-size: 16px; }
            .kpi-item .kpi-label { font-size: 7px; }
        }

        /* ============================================================ */
        /* ANIMATIONS */
        /* ============================================================ */
        @keyframes fadeSlideUp {
            from { opacity: 0; transform: translateY(16px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .fade-in { animation: fadeSlideUp 0.5s ease forwards; }
        .fade-in-d1 { animation-delay: 0.04s; }
        .fade-in-d2 { animation-delay: 0.08s; }
        .fade-in-d3 { animation-delay: 0.12s; }
        .fade-in-d4 { animation-delay: 0.16s; }
        .fade-in-d5 { animation-delay: 0.20s; }
        .fade-in-d6 { animation-delay: 0.24s; }

        /* ============================================================ */
        /* SCROLLBAR */
        /* ============================================================ */
        .table-responsive::-webkit-scrollbar { height: 5px; }
        .table-responsive::-webkit-scrollbar-track { background: var(--medical-gray-light); border-radius: 10px; }
        .table-responsive::-webkit-scrollbar-thumb { background: var(--medical-border); border-radius: 10px; }
        .table-responsive::-webkit-scrollbar-thumb:hover { background: var(--medical-text-muted); }
        ::-webkit-scrollbar { width: 5px; }
        ::-webkit-scrollbar-track { background: var(--medical-gray-light); }
        ::-webkit-scrollbar-thumb { background: var(--medical-border); border-radius: 10px; }
    </style>
</head>
<body>

<div class="app-container">

    <!-- ============================================================ -->
    <!-- HEADER ADAPTATIF -->
    <!-- ============================================================ -->
    <header class="page-header">
        <div class="header-left">
            <div class="medical-badge">
                <i class="bi bi-heart-pulse-fill"></i> Epencia SGI
            </div>
            <h1>Tableau de <span class="highlight">bord</span></h1>
            <div class="subtitle">
                <i class="bi bi-calendar3"></i>
                <?php echo date('d/m/Y à H:i'); ?>
                <span class="dot"></span>
                <i class="bi bi-person-circle"></i> <?php echo htmlspecialchars($user_nom); ?>
                <?php if ($user_role): ?>
                    <span class="dot"></span>
                    <?php echo getRoleBadge($user_role); ?>
                <?php endif; ?>
            </div>
        </div>
        <div class="header-actions">
            <div class="search-box">
                <i class="bi bi-search"></i>
                <input type="text" placeholder="Rechercher un client, facture..." id="searchInput">
            </div>
            <div class="notification-btn" id="notificationBtn" title="Notifications">
                <i class="bi bi-bell"></i>
                <span class="notif-dot"></span>
            </div>
        </div>
    </header>

    <!-- ============================================================ -->
    <!-- STATS CARDS -->
    <!-- ============================================================ -->
    <div class="stats-grid">
        <div class="stat-card fade-in fade-in-d1">
            <div class="stat-icon blue"><i class="bi bi-people"></i></div>
            <div class="stat-content">
                <div class="stat-value"><?php echo number_format($total_clients_actifs); ?></div>
                <div class="stat-label">Clients actifs</div>
                <div class="stat-change up"><i class="bi bi-arrow-up"></i> <?php echo $total_clients_inactifs; ?> inactifs</div>
            </div>
        </div>
        <div class="stat-card fade-in fade-in-d2">
            <div class="stat-icon teal"><i class="bi bi-file-check"></i></div>
            <div class="stat-content">
                <div class="stat-value"><?php echo number_format($factures_payees); ?></div>
                <div class="stat-label">Factures payées</div>
                <div class="stat-change up"><i class="bi bi-check-circle"></i> <?php echo $taux_paiement; ?>%</div>
            </div>
        </div>
        <div class="stat-card fade-in fade-in-d3">
            <div class="stat-icon warning"><i class="bi bi-clock-history"></i></div>
            <div class="stat-content">
                <div class="stat-value"><?php echo number_format($factures_attente); ?></div>
                <div class="stat-label">En attente</div>
                <div class="stat-change down"><i class="bi bi-exclamation-circle"></i> <?php echo number_format($factures_impayees); ?> impayées</div>
            </div>
        </div>
        <div class="stat-card fade-in fade-in-d4">
            <div class="stat-icon purple"><i class="bi bi-box"></i></div>
            <div class="stat-content">
                <div class="stat-value"><?php echo number_format($total_produits); ?></div>
                <div class="stat-label">Produits</div>
                <div class="stat-change up"><i class="bi bi-tags"></i> <?php echo count($produits_types); ?> types</div>
            </div>
        </div>
        <div class="stat-card fade-in fade-in-d5">
            <div class="stat-icon info"><i class="bi bi-hospital"></i></div>
            <div class="stat-content">
                <div class="stat-value"><?php echo number_format($total_organismes); ?></div>
                <div class="stat-label">Organismes</div>
                <div class="stat-change up"><i class="bi bi-arrow-up"></i> partenaires</div>
            </div>
        </div>
        <div class="stat-card fade-in fade-in-d6">
            <div class="stat-icon danger"><i class="bi bi-arrow-left-right"></i></div>
            <div class="stat-content">
                <div class="stat-value"><?php echo number_format($transactions_jour); ?></div>
                <div class="stat-label">Tx aujourd'hui</div>
                <div class="stat-change up"><i class="bi bi-arrow-up"></i> <?php echo number_format($total_transactions); ?> total</div>
            </div>
        </div>
    </div>

    <!-- ============================================================ -->
    <!-- KPI ROW -->
    <!-- ============================================================ -->
    <div class="kpi-row">
        <div class="kpi-item fade-in fade-in-d1">
            <span class="kpi-icon">💰</span>
            <div class="kpi-value green"><?php echo number_format($ca_total, 0, ',', ' '); ?></div>
            <div class="kpi-label">CA Total (FCFA)</div>
        </div>
        <div class="kpi-item fade-in fade-in-d2">
            <span class="kpi-icon">📊</span>
            <div class="kpi-value gold"><?php echo number_format($ca_mois, 0, ',', ' '); ?></div>
            <div class="kpi-label">CA du mois (FCFA)</div>
        </div>
        <div class="kpi-item fade-in fade-in-d3">
            <span class="kpi-icon">📅</span>
            <div class="kpi-value blue"><?php echo number_format($ca_jour, 0, ',', ' '); ?></div>
            <div class="kpi-label">CA du jour (FCFA)</div>
        </div>
        <div class="kpi-item fade-in fade-in-d4">
            <span class="kpi-icon">👥</span>
            <div class="kpi-value purple"><?php echo number_format($total_utilisateurs); ?></div>
            <div class="kpi-label">Utilisateurs actifs</div>
        </div>
        <div class="kpi-item fade-in fade-in-d5">
            <span class="kpi-icon">📋</span>
            <div class="kpi-value"><?php echo number_format($total_commandes); ?></div>
            <div class="kpi-label">Commandes</div>
        </div>
        <div class="kpi-item fade-in fade-in-d6">
            <span class="kpi-icon">🏷️</span>
            <div class="kpi-value red"><?php echo number_format($montant_factures_impayees, 0, ',', ' '); ?></div>
            <div class="kpi-label">Créances (FCFA)</div>
        </div>
    </div>

    <!-- ============================================================ -->
    <!-- DASHBOARD GRID -->
    <!-- ============================================================ -->
    <div class="row g-3">

        <!-- ===== DERNIÈRES FACTURES ===== -->
        <div class="col-lg-6 col-xl-6">
            <div class="card-modern fade-in fade-in-d1">
                <div class="card-header">
                    <h5>
                        <span class="header-icon" style="background:var(--warning-light);color:var(--warning);"><i class="bi bi-file-text"></i></span>
                        Dernières factures
                    </h5>
                    <a href="factures/liste.php" class="card-action">Voir tout <i class="bi bi-chevron-right"></i></a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-dashboard">
                            <thead>
                                <tr>
                                    <th>N° Facture</th>
                                    <th>Client</th>
                                    <th>Montant</th>
                                    <th>Statut</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($dernieres_factures)): ?>
                                    <tr><td colspan="4" class="text-center text-muted py-4" style="color:var(--medical-text-muted);font-size:12px;">
                                        <i class="bi bi-inbox d-block mb-1" style="font-size:1.5rem;"></i>Aucune facture
                                    </td></tr>
                                <?php else: ?>
                                    <?php $avatarColors = ['blue', 'teal', 'purple', 'warning', 'danger', 'info', 'blue', 'teal']; ?>
                                    <?php foreach ($dernieres_factures as $idx => $f):
                                        $color = $avatarColors[$idx % count($avatarColors)];
                                        $nom = $f['client_nom'] ?? '?';
                                        $parts = preg_split('/[\s-]+/', trim($nom));
                                        $initials = '';
                                        foreach ($parts as $p) { if (!empty($p)) $initials .= strtoupper(mb_substr($p, 0, 1)); }
                                        if (empty($initials)) $initials = '?';
                                        $initials = mb_substr($initials, 0, 2);
                                        $montant = number_format((float)($f['montant_total'] ?? 0), 0, ',', ' ');
                                    ?>
                                        <tr>
                                            <td>
                                                <span style="font-weight:600;font-size:12px;color:var(--medical-text);">
                                                    <?php echo htmlspecialchars(mb_substr($f['facture_id'] ?? '', 0, 14)); ?><?php if (mb_strlen($f['facture_id'] ?? '') > 14): ?>…<?php endif; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div style="display:flex;align-items:center;gap:8px;">
                                                    <span class="avatar <?php echo $color; ?>"><?php echo $initials; ?></span>
                                                    <span style="font-weight:500;font-size:12px;"><?php echo htmlspecialchars(mb_substr($nom, 0, 18)); ?><?php if (mb_strlen($nom) > 18): ?>…<?php endif; ?></span>
                                                </div>
                                            </td>
                                            <td>
                                                <span style="font-weight:700;font-size:12px;"><?php echo $montant; ?></span>
                                                <small style="color:var(--medical-text-muted);font-size:10px;"> F</small>
                                            </td>
                                            <td><?php echo getStatusBadge($f['statut'] ?? ''); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- ===== TOP CLIENTS ===== -->
        <div class="col-lg-6 col-xl-3">
            <div class="card-modern fade-in fade-in-d2">
                <div class="card-header">
                    <h5>
                        <span class="header-icon" style="background:var(--warning-light);color:var(--warning);"><i class="bi bi-trophy"></i></span>
                        Top clients
                    </h5>
                    <a href="clients/liste.php" class="card-action">Voir tout <i class="bi bi-chevron-right"></i></a>
                </div>
                <div class="card-body">
                    <?php if (empty($top_clients)): ?>
                        <div class="text-center py-4" style="color:var(--medical-text-muted);font-size:12px;">
                            <i class="bi bi-people d-block mb-1" style="font-size:1.5rem;"></i>Aucun client
                        </div>
                    <?php else: ?>
                        <?php $max_achat = max(array_column($top_clients, 'total_achats')) ?: 1; ?>
                        <?php foreach ($top_clients as $rank => $tc):
                            $pct = round(($tc['total_achats'] / $max_achat) * 100);
                            $montant = number_format($tc['total_achats'], 0, ',', ' ');
                        ?>
                            <div class="top-client-bar">
                                <div class="bar-label">
                                    <span>
                                        <span class="client-rank">#<?php echo $rank + 1; ?></span>
                                        <span class="client-name"><?php echo htmlspecialchars(mb_substr($tc['nom_prenom'], 0, 16)); ?><?php if (mb_strlen($tc['nom_prenom']) > 16): ?>…<?php endif; ?></span>
                                    </span>
                                    <span class="client-amount"><?php echo $montant; ?> F</span>
                                </div>
                                <div class="bar-track">
                                    <div class="bar-fill" style="width:<?php echo $pct; ?>%;"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- ===== ACTIVITÉS RÉCENTES ===== -->
        <div class="col-lg-6 col-xl-3">
            <div class="card-modern fade-in fade-in-d3">
                <div class="card-header">
                    <h5>
                        <span class="header-icon" style="background:var(--medical-blue-light);color:var(--medical-blue);"><i class="bi bi-activity"></i></span>
                        Activités récentes
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (empty($activites_recentes)): ?>
                        <div class="text-center py-4" style="color:var(--medical-text-muted);font-size:12px;">
                            <i class="bi bi-clock-history d-block mb-1" style="font-size:1.5rem;"></i>Aucune activité
                        </div>
                    <?php else: ?>
                        <ul class="activity-list">
                            <?php foreach ($activites_recentes as $act):
                                $dotClass = ($act['status'] === 'succes') ? 'success' : (($act['status'] === 'echec') ? 'danger' : 'info');
                                $dateStr = date('d/m H:i', strtotime($act['date_connexion']));
                                $clientId = htmlspecialchars(mb_substr($act['client_id'] ?? '', 0, 12));
                                $typeLabel = ucfirst($act['type'] ?? 'connexion');
                            ?>
                                <li>
                                    <span class="activity-dot <?php echo $dotClass; ?>"></span>
                                    <div class="activity-content">
                                        <div class="activity-text">
                                            <span class="hl"><?php echo $clientId; ?></span> — <?php echo $typeLabel; ?>
                                            <?php if ($act['status'] === 'echec'): ?>
                                                <span style="color:var(--danger);font-weight:600;">(échec)</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="activity-time">
                                            <i class="bi bi-clock me-1"></i><?php echo $dateStr; ?>
                                            <?php if (!empty($act['ip_address'])): ?>
                                                <span class="sep" style="display:inline-block;width:3px;height:3px;border-radius:50%;background:var(--medical-text-muted);margin:0 4px;vertical-align:middle;"></span>
                                                <?php echo htmlspecialchars($act['ip_address']); ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- ===== DERNIERS CLIENTS ===== -->
        <div class="col-lg-6 col-xl-4">
            <div class="card-modern fade-in fade-in-d4">
                <div class="card-header">
                    <h5>
                        <span class="header-icon" style="background:var(--success-light);color:var(--success);"><i class="bi bi-person-plus"></i></span>
                        Derniers clients
                    </h5>
                    <a href="clients/liste.php" class="card-action">Voir tout <i class="bi bi-chevron-right"></i></a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-dashboard">
                            <thead>
                                <tr>
                                    <th>Client</th>
                                    <th>Téléphone</th>
                                    <th>Statut</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($derniers_clients)): ?>
                                    <tr><td colspan="3" class="text-center text-muted py-4" style="color:var(--medical-text-muted);font-size:12px;">
                                        <i class="bi bi-person d-block mb-1" style="font-size:1.5rem;"></i>Aucun client
                                    </td></tr>
                                <?php else: ?>
                                    <?php $clientColors = ['blue', 'teal', 'purple', 'warning', 'danger', 'info']; ?>
                                    <?php foreach ($derniers_clients as $cidx => $c):
                                        $ccolor = $clientColors[$cidx % count($clientColors)];
                                        $cnom = $c['nom_prenom'] ?? '?';
                                        $cparts = preg_split('/[\s-]+/', trim($cnom));
                                        $cinits = '';
                                        foreach ($cparts as $cp) { if (!empty($cp)) $cinits .= strtoupper(mb_substr($cp, 0, 1)); }
                                        if (empty($cinits)) $cinits = '?';
                                        $cinits = mb_substr($cinits, 0, 2);
                                    ?>
                                        <tr>
                                            <td>
                                                <div style="display:flex;align-items:center;gap:8px;">
                                                    <span class="avatar <?php echo $ccolor; ?>"><?php echo $cinits; ?></span>
                                                    <span style="font-weight:600;font-size:12px;"><?php echo htmlspecialchars(mb_substr($cnom, 0, 20)); ?><?php if (mb_strlen($cnom) > 20): ?>…<?php endif; ?></span>
                                                </div>
                                            </td>
                                            <td style="font-size:12px;color:var(--medical-text-secondary);"><?php echo htmlspecialchars($c['telephone'] ?? '-'); ?></td>
                                            <td><?php echo getStatusBadge($c['statut'] ?? 'actif'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- ===== RÉPARTITION ORGANISMES & PRODUITS ===== -->
        <div class="col-lg-6 col-xl-4">
            <div class="card-modern fade-in fade-in-d5">
                <div class="card-header">
                    <h5>
                        <span class="header-icon" style="background:var(--info-light);color:var(--info);"><i class="bi bi-pie-chart"></i></span>
                        Répartitions
                    </h5>
                </div>
                <div class="card-body">
                    <!-- Organismes par type -->
                    <div style="margin-bottom:20px;">
                        <div style="font-weight:700;font-size:12px;color:var(--medical-text-secondary);text-transform:uppercase;letter-spacing:0.04em;margin-bottom:10px;">
                            <i class="bi bi-hospital me-1" style="color:var(--medical-blue);"></i> Organismes
                        </div>
                        <?php if (!empty($organismes_types)): ?>
                            <?php
                            $orgColors = ['clinique' => 'var(--medical-blue)', 'pharmacie' => 'var(--medical-teal)', 'laboratoire' => 'var(--warning)'];
                            $maxOrg = max(array_values($organismes_types)) ?: 1;
                            foreach ($organismes_types as $otype => $ocount):
                                $opct = round(($ocount / $maxOrg) * 100);
                                $ocolor = $orgColors[$otype] ?? 'var(--medical-text-muted)';
                            ?>
                                <div class="top-client-bar">
                                    <div class="bar-label">
                                        <span style="font-weight:600;"><?php echo ucfirst($otype); ?></span>
                                        <span style="font-weight:700;"><?php echo $ocount; ?></span>
                                    </div>
                                    <div class="bar-track">
                                        <div class="bar-fill" style="width:<?php echo $opct; ?>%;background:<?php echo $ocolor; ?>;"></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div style="font-size:12px;color:var(--medical-text-muted);">Aucun organisme</div>
                        <?php endif; ?>
                    </div>

                    <!-- Produits par type -->
                    <div>
                        <div style="font-weight:700;font-size:12px;color:var(--medical-text-secondary);text-transform:uppercase;letter-spacing:0.04em;margin-bottom:10px;">
                            <i class="bi bi-box me-1" style="color:var(--medical-teal);"></i> Produits
                        </div>
                        <?php if (!empty($produits_types)): ?>
                            <?php
                            $prodColors = ['medicament' => 'var(--danger)', 'dispositif' => 'var(--info)', 'consommable' => 'var(--warning)', 'autre' => 'var(--medical-text-muted)'];
                            $maxProd = max(array_values($produits_types)) ?: 1;
                            foreach ($produits_types as $ptype => $pcount):
                                $ppct = round(($pcount / $maxProd) * 100);
                                $pcolor = $prodColors[$ptype] ?? 'var(--medical-text-muted)';
                            ?>
                                <div class="top-client-bar">
                                    <div class="bar-label">
                                        <span style="font-weight:600;"><?php echo ucfirst($ptype); ?></span>
                                        <span style="font-weight:700;"><?php echo $pcount; ?></span>
                                    </div>
                                    <div class="bar-track">
                                        <div class="bar-fill" style="width:<?php echo $ppct; ?>%;background:<?php echo $pcolor; ?>;"></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div style="font-size:12px;color:var(--medical-text-muted);">Aucun produit</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- ===== MINI STATS UTILISATEURS & CLIENTS ===== -->
        <div class="col-lg-6 col-xl-4">
            <div class="card-modern fade-in fade-in-d6">
                <div class="card-header">
                    <h5>
                        <span class="header-icon" style="background:#f0ebf8;color:#6a0dad;"><i class="bi bi-speedometer2"></i></span>
                        Détails
                    </h5>
                </div>
                <div class="card-body">
                    <!-- Utilisateurs par rôle -->
                    <div style="margin-bottom:18px;">
                        <div style="font-weight:700;font-size:12px;color:var(--medical-text-secondary);text-transform:uppercase;letter-spacing:0.04em;margin-bottom:10px;">
                            <i class="bi bi-people me-1" style="color:#6a0dad;"></i> Utilisateurs par rôle
                        </div>
                        <?php if (!empty($utilisateurs_roles)): ?>
                            <?php foreach ($utilisateurs_roles as $urole => $ucount): ?>
                                <div class="mini-stat">
                                    <div class="ms-icon <?php echo ($urole === 'Administrateur') ? 'blue' : (($urole === 'Medecin') ? 'teal' : (($urole === 'Pharmacien') ? 'warning' : 'danger')); ?>">
                                        <i class="bi <?php echo ($urole === 'Administrateur') ? 'bi-shield-fill' : (($urole === 'Medecin') ? 'bi-heart-pulse' : (($urole === 'Pharmacien') ? 'bi-capsule' : 'bi-shield-check')); ?>"></i>
                                    </div>
                                    <div class="ms-info">
                                        <h6><?php echo $ucount; ?></h6>
                                        <small><?php echo htmlspecialchars($urole); ?></small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div style="font-size:12px;color:var(--medical-text-muted);">Aucun utilisateur</div>
                        <?php endif; ?>
                    </div>

                    <!-- Clients par sexe -->
                    <div>
                        <div style="font-weight:700;font-size:12px;color:var(--medical-text-secondary);text-transform:uppercase;letter-spacing:0.04em;margin-bottom:10px;">
                            <i class="bi bi-gender-ambiguous me-1" style="color:var(--info);"></i> Clients par sexe
                        </div>
                        <div style="display:flex;gap:10px;">
                            <div class="mini-stat" style="flex:1;">
                                <div class="ms-icon blue"><i class="bi bi-gender-male"></i></div>
                                <div class="ms-info">
                                    <h6><?php echo number_format($clients_hommes); ?></h6>
                                    <small>Masculin</small>
                                </div>
                            </div>
                            <div class="mini-stat" style="flex:1;">
                                <div class="ms-icon danger"><i class="bi bi-gender-female"></i></div>
                                <div class="ms-info">
                                    <h6><?php echo number_format($clients_femmes); ?></h6>
                                    <small>Féminin</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Résumé factures -->
                    <div style="margin-top:18px;">
                        <div style="font-weight:700;font-size:12px;color:var(--medical-text-secondary);text-transform:uppercase;letter-spacing:0.04em;margin-bottom:10px;">
                            <i class="bi bi-receipt me-1" style="color:var(--warning);"></i> Résumé factures
                        </div>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
                            <div style="background:var(--success-light);border-radius:8px;padding:10px 12px;text-align:center;">
                                <div style="font-size:16px;font-weight:800;color:var(--success);"><?php echo number_format($factures_payees); ?></div>
                                <div style="font-size:9px;color:var(--medical-text-muted);text-transform:uppercase;font-weight:700;letter-spacing:0.04em;">Payées</div>
                            </div>
                            <div style="background:var(--danger-light);border-radius:8px;padding:10px 12px;text-align:center;">
                                <div style="font-size:16px;font-weight:800;color:var(--danger);"><?php echo number_format($factures_impayees); ?></div>
                                <div style="font-size:9px;color:var(--medical-text-muted);text-transform:uppercase;font-weight:700;letter-spacing:0.04em;">Impayées</div>
                            </div>
                            <div style="background:var(--warning-light);border-radius:8px;padding:10px 12px;text-align:center;">
                                <div style="font-size:16px;font-weight:800;color:var(--warning);"><?php echo number_format($factures_attente); ?></div>
                                <div style="font-size:9px;color:var(--medical-text-muted);text-transform:uppercase;font-weight:700;letter-spacing:0.04em;">En attente</div>
                            </div>
                            <div style="background:var(--medical-gray);border-radius:8px;padding:10px 12px;text-align:center;">
                                <div style="font-size:16px;font-weight:800;color:var(--medical-text-secondary);"><?php echo number_format($factures_annulees + $factures_partiel); ?></div>
                                <div style="font-size:9px;color:var(--medical-text-muted);text-transform:uppercase;font-weight:700;letter-spacing:0.04em;">Autres</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div><!-- /row -->

    <!-- ============================================================ -->
    <!-- FOOTER -->
    <!-- ============================================================ -->
    <div class="dashboard-footer">
        <i class="bi bi-heart-pulse-fill" style="color:var(--medical-teal);"></i>
        Epencia SGI
        <span class="sep"></span>
        Système de Gestion Intégré
        <span class="sep"></span>
        &copy; <?php echo date('Y'); ?>
    </div>

</div><!-- /app-container -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Animation des barres de progression au chargement
    document.addEventListener('DOMContentLoaded', function() {
        // Forcer le reflow pour animer les barres
        document.querySelectorAll('.bar-fill').forEach(function(bar) {
            const width = bar.style.width;
            bar.style.width = '0%';
            setTimeout(function() {
                bar.style.width = width;
            }, 200);
        });

        // Notification button - placeholder
        const notifBtn = document.getElementById('notificationBtn');
        if (notifBtn) {
            notifBtn.addEventListener('click', function() {
                alert('Aucune nouvelle notification.');
                const dot = this.querySelector('.notif-dot');
                if (dot) dot.style.display = 'none';
            });
        }

        // Recherche - navigation vers la page clients
        const searchInput = document.getElementById('searchInput');
        if (searchInput) {
            searchInput.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' && this.value.trim()) {
                    window.location.href = 'clients/liste.php?search=' + encodeURIComponent(this.value.trim());
                }
            });
        }
    });
</script>
</body>
</html>