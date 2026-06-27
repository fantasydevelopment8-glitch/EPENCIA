<?php
// ================================================================
// DASHBOARD CLIENT - EPENCIA SGI
// ================================================================

// ================================================================
// 1. CONNEXION BDD & SESSION
// ================================================================
require_once "database/database.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ================================================================
// 2. RÉCUPÉRATION DU CLIENT
// ================================================================
// Priorité : POST > SESSION
$client_id = $_POST['client_id'] ?? $_SESSION['client_id'] ?? null;

if ($client_id) {
    $_SESSION['client_id'] = $client_id;
    $_SESSION['client_connecte'] = true;
} else {
    // Rediriger vers le scanner si aucun ID
    header('Location: index.php?c=client&a=scan');
    exit();
}

$client = null;
$error = null;

if ($client_id) {
    try {
        $sql = "SELECT * FROM clients WHERE client_id = :client_id AND statut = 'actif'";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':client_id' => $client_id]);
        $client = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$client) {
            $error = "Client non trouvé ou compte inactif";
            // Nettoyer la session
            unset($_SESSION['client_id']);
            unset($_SESSION['client_connecte']);
        } else {
            $_SESSION['client_id'] = $client_id;
            $_SESSION['client_nom'] = $client['nom_prenom'];
            $_SESSION['client_connecte'] = true;
        }
    } catch (PDOException $e) {
        $error = "Erreur de base de données : " . $e->getMessage();
    }
}

if (!$client && !$error) {
    $error = "Aucun client sélectionné";
}

// ================================================================
// 3. FONCTIONS
// ================================================================
function getStatusBadge($status) {
    $statusMap = [
        'actif' => ['class' => 'success', 'label' => 'Actif'],
        'inactif' => ['class' => 'danger', 'label' => 'Inactif'],
        'payee' => ['class' => 'success', 'label' => 'Payée'],
        'payé' => ['class' => 'success', 'label' => 'Payé'],
        'impayee' => ['class' => 'danger', 'label' => 'Impayée'],
        'impayé' => ['class' => 'danger', 'label' => 'Impayé'],
        'en_attente' => ['class' => 'warning', 'label' => 'En attente'],
        'en attente' => ['class' => 'warning', 'label' => 'En attente'],
        'annulee' => ['class' => 'secondary', 'label' => 'Annulée'],
        'annulé' => ['class' => 'secondary', 'label' => 'Annulé'],
        'partielle' => ['class' => 'info', 'label' => 'Partielle'],
        'partiel' => ['class' => 'info', 'label' => 'Partiel']
    ];
    $status = strtolower($status ?? 'en_attente');
    $info = $statusMap[$status] ?? ['class' => 'secondary', 'label' => ucfirst($status)];
    return '<span class="badge bg-' . $info['class'] . '">' . $info['label'] . '</span>';
}

function escapeHtml($text) {
    if (!$text) return '';
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

function generateFactureId($pdo, $client_id) {
    $prefix = 'FAC_' . date('Ymd') . '_';
    $last6 = substr($client_id, -6);
    
    try {
        $sql = "SELECT COUNT(*) FROM factures WHERE facture_id LIKE :prefix";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':prefix' => $prefix . '%']);
        $count = $stmt->fetchColumn() + 1;
        $numero = str_pad($count, 4, '0', STR_PAD_LEFT);
        return $prefix . $last6 . '_' . $numero;
    } catch (PDOException $e) {
        return $prefix . $last6 . '_' . rand(1000, 9999);
    }
}

function getClientFactures($pdo, $client_id) {
    try {
        $sql = "SELECT * FROM factures WHERE client_id = :client_id ORDER BY date_creation DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':client_id' => $client_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

function getClientById($pdo, $client_id) {
    try {
        $sql = "SELECT * FROM clients WHERE client_id = :client_id AND statut = 'actif'";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':client_id' => $client_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return null;
    }
}

// ================================================================
// 4. GESTION DES FACTURES
// ================================================================
$message = '';
$error_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_facture') {
    $montant = floatval($_POST['montant'] ?? 0);
    $description = trim($_POST['description'] ?? '');
    $date_facture = $_POST['date_facture'] ?? date('Y-m-d');
    $statut = $_POST['statut'] ?? 'en_attente';
    
    if ($montant <= 0) {
        $error_msg = "Le montant doit être supérieur à 0";
    } elseif (empty($description)) {
        $error_msg = "La description est obligatoire";
    } else {
        try {
            $facture_id = generateFactureId($pdo, $client_id);
            
            $sql = "INSERT INTO factures (facture_id, client_id, montant, description, date_facture, statut, date_creation) 
                    VALUES (:facture_id, :client_id, :montant, :description, :date_facture, :statut, NOW())";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':facture_id' => $facture_id,
                ':client_id' => $client_id,
                ':montant' => $montant,
                ':description' => $description,
                ':date_facture' => $date_facture,
                ':statut' => $statut
            ]);
            
            $message = "Facture ajoutée avec succès ! ID: " . $facture_id;
            
            if ($statut === 'payee') {
                $sql = "UPDATE clients SET solde = solde - :montant WHERE client_id = :client_id";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':montant' => $montant,
                    ':client_id' => $client_id
                ]);
                $client = getClientById($pdo, $client_id);
            }
            
        } catch (PDOException $e) {
            $error_msg = "Erreur lors de l'ajout de la facture : " . $e->getMessage();
        }
    }
}

if (isset($_POST['delete_facture']) && !empty($_POST['delete_facture'])) {
    $facture_id = $_POST['delete_facture'];
    try {
        $sql = "DELETE FROM factures WHERE facture_id = :facture_id AND client_id = :client_id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':facture_id' => $facture_id,
            ':client_id' => $client_id
        ]);
        $message = "Facture supprimée avec succès";
    } catch (PDOException $e) {
        $error_msg = "Erreur lors de la suppression : " . $e->getMessage();
    }
}

if (isset($_POST['ajax_action']) && $_POST['ajax_action'] === 'get_facture_detail') {
    header('Content-Type: application/json; charset=utf-8');
    
    $facture_id = trim($_POST['facture_id'] ?? '');
    
    try {
        $sql = "SELECT * FROM factures WHERE facture_id = :facture_id AND client_id = :client_id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':facture_id' => $facture_id,
            ':client_id' => $client_id
        ]);
        $facture = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($facture) {
            echo json_encode(['success' => true, 'facture' => $facture]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Facture introuvable']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
    }
    exit;
}

// ================================================================
// 5. RÉCUPÉRATION DES DONNÉES
// ================================================================
$factures = getClientFactures($pdo, $client_id);

$total_factures = array_sum(array_column($factures, 'montant'));
$total_payees = array_sum(array_column(array_filter($factures, function($f) { return $f['statut'] === 'payee'; }), 'montant'));
$total_impayees = array_sum(array_column(array_filter($factures, function($f) { return $f['statut'] === 'impayee'; }), 'montant'));
$total_attente = array_sum(array_column(array_filter($factures, function($f) { return $f['statut'] === 'en_attente'; }), 'montant'));
?>

<!-- ============================================================ -->
<!-- HTML DE LA PAGE ESPACE CLIENT -->
<!-- ============================================================ -->
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de bord client - Epencia SGI</title>
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

        .app-container { max-width: 1200px; margin: 0 auto; }

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
        /* BOUTON ESPACE PERSONNEL                                      */
        /* ============================================================ */
        .btn-espace {
            background: rgba(26, 107, 138, 0.1);
            color: var(--medical-blue);
            border: 2px solid var(--medical-border);
            padding: 10px 22px;
            border-radius: 50px;
            font-weight: 600;
            transition: var(--medical-transition);
            display: inline-flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            font-size: 0.95rem;
            font-family: var(--font-primary);
            border-color: var(--medical-border);
        }

        .btn-espace:hover {
            background: var(--medical-blue);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(26, 107, 138, 0.2);
            border-color: var(--medical-blue);
        }

        .btn-espace i {
            font-size: 1.2rem;
            transition: transform 0.3s ease;
        }

        .btn-espace.active i {
            transform: rotate(180deg);
        }

        .btn-espace .badge-notif {
            background: var(--medical-teal);
            color: white;
            border-radius: 50%;
            padding: 2px 8px;
            font-size: 0.65rem;
            margin-left: 5px;
        }

        /* ============================================================ */
        /* STATS CARDS                                                  */
        /* ============================================================ */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
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
        .stat-icon.danger { background: var(--danger-light); color: var(--danger); }

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

        .card-modern .card-body { padding: 20px; }

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
        .btn-action.voir:hover { background: var(--info); color: white; }
        .btn-action.voir { background: var(--info-light); color: var(--info); }

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

        .modal-body .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        .modal-body .info-item {
            background: var(--medical-gray-light);
            padding: 10px 14px;
            border-radius: var(--medical-radius-sm);
            border-left: 3px solid var(--medical-blue);
        }

        .modal-body .info-item .lbl {
            font-size: 0.65rem;
            color: var(--medical-text-muted);
            text-transform: uppercase;
            font-weight: 700;
            letter-spacing: 0.3px;
        }

        .modal-body .info-item .val {
            font-weight: 600;
            color: var(--medical-text);
            font-size: 0.95rem;
        }

        .modal-body .info-item.green { border-left-color: var(--success); background: var(--success-light); }
        .modal-body .info-item.green .val { color: var(--success); }
        .modal-body .info-item.danger { border-left-color: var(--danger); background: var(--danger-light); }
        .modal-body .info-item.danger .val { color: var(--danger); }
        .modal-body .info-item.warning { border-left-color: var(--warning); background: var(--warning-light); }
        .modal-body .info-item.warning .val { color: var(--warning); }

        /* ============================================================ */
        /* PROFIL CARD                                                  */
        /* ============================================================ */
        .profile-card {
            background: var(--medical-white);
            border-radius: var(--medical-radius);
            box-shadow: var(--medical-shadow);
            border: 1px solid var(--medical-border);
            overflow: hidden;
            margin-bottom: 24px;
            max-height: 0;
            opacity: 0;
            overflow: hidden;
            transition: max-height 0.8s cubic-bezier(0.4, 0, 0.2, 1), 
                        opacity 0.5s ease,
                        margin 0.3s ease,
                        padding 0.3s ease;
            padding: 0 25px;
        }

        .profile-card.show {
            max-height: 3000px;
            opacity: 1;
            padding: 0 25px;
        }

        .profile-card .card-header {
            background: var(--medical-gray-light);
            color: var(--medical-text);
            padding: 18px 0;
            border: none;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }

        .profile-card .card-header h5 {
            margin: 0;
            font-weight: 700;
            letter-spacing: 0.3px;
        }

        .profile-card .card-body {
            padding: 25px 0;
        }

        .profile-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .profile-grid .full-width {
            grid-column: 1 / -1;
        }

        .info-group {
            background: var(--medical-gray-light);
            border-radius: 8px;
            padding: 12px 16px;
            border: 1px solid var(--medical-border);
        }

        .info-group .label {
            font-size: 0.75rem;
            font-weight: 700;
            color: var(--medical-text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
        }

        .info-group .value {
            font-size: 1rem;
            font-weight: 500;
            color: var(--medical-text);
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
        .fade-in-d5 { animation-delay: 0.25s; }

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
        @media (max-width: 992px) {
            .stats-grid { grid-template-columns: repeat(3, 1fr); }
        }

        @media (max-width: 820px) {
            body { padding: 10px; }
            .page-header h1 { font-size: 20px; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); gap: 10px; }
            .stat-card { padding: 12px 14px; gap: 10px; }
            .stat-card .stat-icon { width: 38px; height: 38px; font-size: 16px; }
            .stat-card .stat-value { font-size: 16px; }
            .profile-grid { grid-template-columns: 1fr; }
            .modal-body .info-grid { grid-template-columns: 1fr; }
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
            .card-modern .card-body { padding: 15px; }
            .table-dashboard { font-size: 0.65rem; min-width: 480px; }
            .table-dashboard thead th, .table-dashboard tbody td { padding: 6px 8px; }
            .profile-card.show { padding: 0 15px; }
            .info-group { padding: 8px 12px; }
            .info-group .value { font-size: 0.85rem; }
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
    
    <?php if ($error): ?>
        <!-- ==================== PAGE D'ERREUR ==================== -->
        <div class="page-header" style="border-left: 4px solid var(--danger);">
            <div class="header-left">
                <div class="medical-badge">
                    <i class="bi bi-heart-pulse-fill"></i> Epencia SGI
                </div>
                <h1>Erreur</h1>
                <div class="subtitle">
                    <i class="bi bi-exclamation-triangle"></i>
                    Une erreur est survenue lors du chargement
                </div>
            </div>
        </div>
        <div class="alert-medical alert-danger">
            <i class="bi bi-x-circle-fill me-2"></i>
            <?= htmlspecialchars($error) ?>
        </div>
        
    <?php elseif ($client): ?>
    
        <!-- ==================== HEADER ==================== -->
        <header class="page-header">
            <div class="header-left">
                <div class="medical-badge">
                    <i class="bi bi-heart-pulse-fill"></i> Epencia SGI
                </div>
                <h1>Tableau de <span class="highlight">bord</span></h1>
                <div class="subtitle">
                    <i class="bi bi-person-circle"></i>
                    Bienvenue, <strong><?= htmlspecialchars($client['nom_prenom']) ?></strong>
                    <span class="dot"></span>
                    <?= date('d/m/Y à H:i') ?>
                </div>
            </div>
            <div class="header-actions">
                <button class="btn-espace" id="toggleEspaceBtn" onclick="toggleEspace()">
                    <i class="bi bi-chevron-down" id="espaceIcon"></i>
                    <span>Votre espace</span>
                    <span class="badge-notif">●</span>
                </button>
                <a href="index.php?c=client&a=scan" class="btn-medical-secondary btn-sm-medical">
                    <i class="bi bi-qr-code"></i> Scanner
                </a>
                <a href="../utilisateur/dashboard.php" class="btn-back-adaptive" title="Retour au tableau de bord">
                    <span class="btn-icon"><i class="bi bi-arrow-left"></i></span>
                    <span class="btn-label long">Retour</span>
                    <span class="btn-label short">Retour</span>
                </a>
            </div>
        </header>
        
        <!-- ==================== STATISTIQUES ==================== -->
        <div class="stats-grid">
            <div class="stat-card fade-in fade-in-d1">
                <div class="stat-icon blue"><i class="bi bi-person-fill"></i></div>
                <div class="stat-content">
                    <div class="stat-value"><?= htmlspecialchars($client['nom_prenom']) ?></div>
                    <div class="stat-label">Nom du client</div>
                </div>
            </div>
            <div class="stat-card fade-in fade-in-d2">
                <div class="stat-icon info"><i class="bi bi-receipt"></i></div>
                <div class="stat-content">
                    <div class="stat-value"><?= count($factures) ?></div>
                    <div class="stat-label">Total factures</div>
                </div>
            </div>
            <div class="stat-card fade-in fade-in-d3">
                <div class="stat-icon teal"><i class="bi bi-check-circle"></i></div>
                <div class="stat-content">
                    <div class="stat-value"><?= number_format($total_payees, 0) ?> F</div>
                    <div class="stat-label">Payées</div>
                </div>
            </div>
            <div class="stat-card fade-in fade-in-d4">
                <div class="stat-icon danger"><i class="bi bi-x-circle"></i></div>
                <div class="stat-content">
                    <div class="stat-value"><?= number_format($total_impayees, 0) ?> F</div>
                    <div class="stat-label">Impayées</div>
                </div>
            </div>
            <div class="stat-card fade-in fade-in-d5">
                <div class="stat-icon warning"><i class="bi bi-clock-history"></i></div>
                <div class="stat-content">
                    <div class="stat-value"><?= number_format($total_attente, 0) ?> F</div>
                    <div class="stat-label">En attente</div>
                </div>
            </div>
        </div>
        
        <!-- ==================== PROFIL CLIENT (CACHÉ) ==================== -->
        <div class="profile-card" id="profileCard">
            <div class="card-header">
                <h5><i class="bi bi-person-badge me-2"></i>Informations personnelles</h5>
            </div>
            <div class="card-body">
                <div class="profile-grid">
                    <div class="info-group">
                        <div class="label">Nom et prénom</div>
                        <div class="value"><?= htmlspecialchars($client['nom_prenom']) ?></div>
                    </div>
                    <div class="info-group">
                        <div class="label">Date de naissance</div>
                        <div class="value"><?= $client['date_naissance'] ? date('d/m/Y', strtotime($client['date_naissance'])) : 'Non spécifié' ?></div>
                    </div>
                    <div class="info-group">
                        <div class="label">Lieu de naissance</div>
                        <div class="value"><?= htmlspecialchars($client['lieu_naissance'] ?? 'Non spécifié') ?></div>
                    </div>
                    <div class="info-group">
                        <div class="label">Sexe</div>
                        <div class="value"><?= ucfirst(htmlspecialchars($client['sexe'] ?? 'Non spécifié')) ?></div>
                    </div>
                    <div class="info-group">
                        <div class="label">Nationalité</div>
                        <div class="value"><?= htmlspecialchars($client['nationalite'] ?? 'Non spécifié') ?></div>
                    </div>
                    <div class="info-group">
                        <div class="label">Groupe sanguin</div>
                        <div class="value"><?= htmlspecialchars($client['groupe_sanguin'] ?? 'Non spécifié') ?></div>
                    </div>
                    <div class="info-group">
                        <div class="label">Téléphone</div>
                        <div class="value"><?= htmlspecialchars($client['telephone']) ?></div>
                    </div>
                    <div class="info-group">
                        <div class="label">Email</div>
                        <div class="value"><?= htmlspecialchars($client['email'] ?? 'Non spécifié') ?></div>
                    </div>
                    <div class="info-group">
                        <div class="label">Statut</div>
                        <div class="value"><?= getStatusBadge($client['statut'] ?? 'actif') ?></div>
                    </div>
                    <div class="info-group">
                        <div class="label">Profession</div>
                        <div class="value"><?= htmlspecialchars($client['profession'] ?? 'Non spécifié') ?></div>
                    </div>
                    <div class="info-group full-width">
                        <div class="label">Adresse</div>
                        <div class="value">
                            <?php
                            $adresse = [];
                            if (!empty($client['adresse'])) $adresse[] = htmlspecialchars($client['adresse']);
                            if (!empty($client['ville'])) $adresse[] = htmlspecialchars($client['ville']);
                            if (!empty($client['pays'])) $adresse[] = htmlspecialchars($client['pays']);
                            echo !empty($adresse) ? implode(', ', $adresse) : 'Non spécifié';
                            ?>
                        </div>
                    </div>
                    <?php if (!empty($client['description'])): ?>
                    <div class="info-group full-width">
                        <div class="label">Description</div>
                        <div class="value"><?= nl2br(htmlspecialchars($client['description'])) ?></div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- ==================== CONTACT D'URGENCE (CACHÉ) ==================== -->
        <?php if (!empty($client['nom_prenom_urgence']) || !empty($client['telephone_urgence']) || !empty($client['email_urgence'])): ?>
        <div class="profile-card" id="urgenceCard">
            <div class="card-header" style="border-left: 4px solid var(--danger);">
                <h5><i class="bi bi-person-exclamation me-2"></i>Contact d'urgence</h5>
            </div>
            <div class="card-body">
                <div class="profile-grid">
                    <?php if (!empty($client['nom_prenom_urgence'])): ?>
                    <div class="info-group">
                        <div class="label">Nom et prénom</div>
                        <div class="value"><?= htmlspecialchars($client['nom_prenom_urgence']) ?></div>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($client['telephone_urgence'])): ?>
                    <div class="info-group">
                        <div class="label">Téléphone</div>
                        <div class="value"><?= htmlspecialchars($client['telephone_urgence']) ?></div>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($client['email_urgence'])): ?>
                    <div class="info-group">
                        <div class="label">Email</div>
                        <div class="value"><?= htmlspecialchars($client['email_urgence']) ?></div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- ==================== SECTION FACTURES ==================== -->
        <div class="card-modern fade-in">
            <div class="card-header">
                <h5>
                    <span class="header-icon blue"><i class="bi bi-receipt"></i></span>
                    Mes factures
                    <span class="badge bg-secondary ms-2"><?= count($factures) ?></span>
                </h5>
                <button class="btn-medical-primary btn-sm-medical" data-bs-toggle="modal" data-bs-target="#addFactureModal">
                    <i class="bi bi-plus-circle"></i> Ajouter
                </button>
            </div>
            <div class="card-body p-0">
                <?php if (!empty($message)): ?>
                    <div class="alert-medical alert-success m-3">
                        <i class="bi bi-check-circle-fill me-2"></i> <?= htmlspecialchars($message) ?>
                        <button type="button" class="btn-close float-end" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                <?php if (!empty($error_msg)): ?>
                    <div class="alert-medical alert-danger m-3">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= htmlspecialchars($error_msg) ?>
                        <button type="button" class="btn-close float-end" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if (count($factures) > 0): ?>
                    <div class="table-wrapper">
                        <table class="table table-dashboard">
                            <thead>
                                <tr>
                                    <th>ID Facture</th>
                                    <th>Date</th>
                                    <th>Description</th>
                                    <th class="text-end">Montant</th>
                                    <th class="text-center">Statut</th>
                                    <th class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($factures as $facture): ?>
                                    <tr>
                                        <td>
                                            <code style="font-size:0.8rem;color:var(--medical-blue);background:var(--medical-blue-light);padding:2px 8px;border-radius:4px;">
                                                <?= htmlspecialchars($facture['facture_id']) ?>
                                            </code>
                                        </td>
                                        <td><?= date('d/m/Y', strtotime($facture['date_facture'])) ?></td>
                                        <td><?= htmlspecialchars($facture['description']) ?></td>
                                        <td class="text-end fw-bold" style="color:var(--medical-teal);">
                                            <?= number_format($facture['montant'], 0) ?> F
                                        </td>
                                        <td class="text-center"><?= getStatusBadge($facture['statut']) ?></td>
                                        <td>
                                            <div class="actions-group">
                                                <button class="btn-action voir" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#voirFactureModal"
                                                        data-facture-id="<?= htmlspecialchars($facture['facture_id']) ?>"
                                                        title="Voir les détails">
                                                    <i class="bi bi-eye"></i>
                                                </button>
                                                <form method="POST" class="d-inline" onsubmit="return confirm('Supprimer cette facture ?')">
                                                    <input type="hidden" name="delete_facture" value="<?= htmlspecialchars($facture['facture_id']) ?>">
                                                    <button type="submit" class="btn-action delete" title="Supprimer">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr class="table-footer-total">
                                    <td colspan="3" class="total-label">Total</td>
                                    <td class="total-value"><?= number_format($total_factures, 0) ?> F</td>
                                    <td colspan="2"></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5" style="color:var(--medical-text-muted);">
                        <i class="bi bi-receipt" style="font-size:3rem;display:block;margin-bottom:12px;opacity:0.3;"></i>
                        <p>Aucune facture pour ce client</p>
                        <button class="btn-medical-primary btn-sm-medical" data-bs-toggle="modal" data-bs-target="#addFactureModal">
                            <i class="bi bi-plus-circle"></i> Ajouter la première facture
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- ==================== PIED DE PAGE ==================== -->
        <div class="text-center mt-4 pt-3 border-top">
            <p class="text-muted small mb-0">Epencia SGI - Système de Gestion Intégré © <?= date('Y') ?></p>
            <p class="text-muted small">Connecté en tant que : <?= htmlspecialchars($client['nom_prenom']) ?></p>
        </div>
        
    <?php endif; ?>
</div>

<!-- ==================== MODAL AJOUT FACTURE ==================== -->
<div class="modal fade" id="addFactureModal" tabindex="-1" aria-labelledby="addFactureModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header modal-header-medical success">
                <h5 class="modal-title" id="addFactureModalLabel">
                    <i class="bi bi-plus-circle me-2"></i>Ajouter une facture
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_facture">
                    
                    <div class="section-title"><i class="bi bi-cash-coin"></i> Informations</div>
                    
                    <div class="mb-3">
                        <label for="montant" class="form-label">Montant <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text" style="background:var(--medical-gray-light);border-color:var(--medical-border);">FCFA</span>
                            <input type="number" name="montant" id="montant" class="form-control" required step="0.01" min="1" placeholder="0.00">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Description <span class="text-danger">*</span></label>
                        <input type="text" name="description" id="description" class="form-control" required placeholder="Description de la facture">
                    </div>
                    
                    <div class="mb-3">
                        <label for="date_facture" class="form-label">Date de la facture</label>
                        <input type="date" name="date_facture" id="date_facture" class="form-control" value="<?= date('Y-m-d') ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label for="statut" class="form-label">Statut</label>
                        <select name="statut" id="statut" class="form-select">
                            <option value="en_attente">En attente</option>
                            <option value="payee">Payée</option>
                            <option value="impayee">Impayée</option>
                            <option value="annulee">Annulée</option>
                            <option value="partielle">Partielle</option>
                        </select>
                    </div>
                    
                    <div class="alert-medical alert-info">
                        <i class="bi bi-info-circle me-2"></i>
                        <small>L'ID de la facture sera généré automatiquement</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-medical-outline" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn-medical-primary">
                        <i class="bi bi-check-circle me-1"></i>Ajouter
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ==================== MODAL VOIR FACTURE ==================== -->
<div class="modal fade" id="voirFactureModal" tabindex="-1" aria-labelledby="voirFactureModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header modal-header-medical">
                <h5 class="modal-title" id="voirFactureModalLabel">
                    <i class="bi bi-receipt me-2"></i>Détail de la facture
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>
            <div class="modal-body" id="voirFactureBody">
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// ==================== FONCTION POUR AFFICHER/MASQUER L'ESPACE PERSONNEL ====================
let espaceVisible = false;

function toggleEspace() {
    const profileCard = document.getElementById('profileCard');
    const urgenceCard = document.getElementById('urgenceCard');
    const espaceIcon = document.getElementById('espaceIcon');
    const btnEspace = document.getElementById('toggleEspaceBtn');
    
    espaceVisible = !espaceVisible;
    
    if (espaceVisible) {
        profileCard.classList.add('show');
        if (urgenceCard) {
            urgenceCard.classList.add('show');
            urgenceCard.style.maxHeight = '800px';
            urgenceCard.style.opacity = '1';
            urgenceCard.style.padding = '0 25px';
        }
        espaceIcon.className = 'bi bi-chevron-up';
        btnEspace.classList.add('active');
        btnEspace.style.background = 'rgba(26, 107, 138, 0.15)';
        
        setTimeout(() => {
            profileCard.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }, 300);
    } else {
        profileCard.classList.remove('show');
        if (urgenceCard) {
            urgenceCard.classList.remove('show');
            urgenceCard.style.maxHeight = '0';
            urgenceCard.style.opacity = '0';
            urgenceCard.style.padding = '0 25px';
        }
        espaceIcon.className = 'bi bi-chevron-down';
        btnEspace.classList.remove('active');
        btnEspace.style.background = 'rgba(26, 107, 138, 0.1)';
    }
}

// Raccourci clavier Ctrl+E
document.addEventListener('keydown', function(e) {
    if (e.ctrlKey && e.key === 'e') {
        e.preventDefault();
        toggleEspace();
    }
});

// Exposer la fonction globalement
window.toggleEspace = toggleEspace;

// ==================== CHARGEMENT DES DÉTAILS DE LA FACTURE ====================
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('voirFactureModal');
    
    if (modal) {
        modal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const factureId = button.getAttribute('data-facture-id');
            const body = document.getElementById('voirFactureBody');
            
            body.innerHTML = `
                <div class="text-center py-4">
                    <div class="loading-spinner"></div>
                    <p class="mt-2 text-muted">Chargement des détails...</p>
                </div>
            `;
            
            const formData = new FormData();
            formData.append('ajax_action', 'get_facture_detail');
            formData.append('facture_id', factureId);
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const f = data.facture;
                    
                    const statusColors = {
                        'payee': 'green',
                        'impayee': 'danger',
                        'en_attente': 'warning',
                        'annulee': 'secondary',
                        'partielle': 'info'
                    };
                    const color = statusColors[f.statut] || 'secondary';
                    
                    body.innerHTML = `
                        <div class="info-grid">
                            <div class="info-item">
                                <div class="lbl">ID Facture</div>
                                <div class="val" style="font-family: monospace;">${esc(f.facture_id)}</div>
                            </div>
                            <div class="info-item ${color}">
                                <div class="lbl">Statut</div>
                                <div class="val">${esc(f.statut)}</div>
                            </div>
                            <div class="info-item">
                                <div class="lbl">Date de création</div>
                                <div class="val">${f.date_creation ? new Date(f.date_creation).toLocaleDateString('fr-FR') : '-'}</div>
                            </div>
                            <div class="info-item">
                                <div class="lbl">Date de facture</div>
                                <div class="val">${f.date_facture ? new Date(f.date_facture).toLocaleDateString('fr-FR') : '-'}</div>
                            </div>
                            <div class="info-item green">
                                <div class="lbl">Montant</div>
                                <div class="val">${Number(f.montant).toLocaleString('fr-FR')} FCFA</div>
                            </div>
                            <div class="info-item">
                                <div class="lbl">Description</div>
                                <div class="val">${esc(f.description || '-')}</div>
                            </div>
                        </div>
                        <div class="mt-3">
                            <small class="text-muted">
                                <i class="bi bi-info-circle me-1"></i>
                                Cette facture a été créée le ${f.date_creation ? new Date(f.date_creation).toLocaleDateString('fr-FR') : '-'}
                            </small>
                        </div>
                    `;
                } else {
                    body.innerHTML = `
                        <div class="alert-medical alert-danger">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            ${esc(data.message || 'Erreur lors du chargement de la facture')}
                        </div>
                    `;
                }
            })
            .catch(error => {
                body.innerHTML = `
                    <div class="alert-medical alert-danger">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        Erreur de connexion : ${esc(error.message)}
                    </div>
                `;
            });
        });
    }
});

function esc(t) {
    const d = document.createElement('div');
    d.textContent = t;
    return d.innerHTML;
}
</script>

</body>
</html>