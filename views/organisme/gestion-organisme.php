<?php
// ========================================
// GESTION DES ORGANISMES - EPENCIA SGI
// ========================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['utilisateur_id'])) {
    header('Location: ../utilisateur/connexion.php');
    exit();
}

require 'database/database.php';

$message = $error = '';
$edit_organisme = null;

// Récupérer l'utilisateur connecté
$user_role = $_SESSION['role'] ?? '';
$user_nom = $_SESSION['nom_prenom'] ?? 'Utilisateur';
$is_admin = in_array($user_role, ['Administrateur', 'Superviseur']);

// Vérifier les droits d'accès
if (!$is_admin) {
    header('Location: ../utilisateur/dashboard.php?error=Accès non autorisé');
    exit();
}

$limit = 15;
$page = max(1, (int)($_POST['page'] ?? 1));
$offset = ($page - 1) * $limit;
$search = trim($_POST['search'] ?? '');
$filter_type = trim($_POST['type'] ?? '');
$filter_statut = trim($_POST['statut'] ?? '');
$filter_pays = trim($_POST['pays'] ?? '');
$filter_ville = trim($_POST['ville'] ?? '');

// Gestion des filtres en session
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['search']) || isset($_POST['type']) || isset($_POST['statut']) || isset($_POST['pays']) || isset($_POST['ville'])) {
        $_SESSION['organismes_filters'] = [
            'search' => $search,
            'type' => $filter_type,
            'statut' => $filter_statut,
            'pays' => $filter_pays,
            'ville' => $filter_ville,
            'page' => $page
        ];
    }
} else {
    if (isset($_SESSION['organismes_filters'])) {
        $search = $_SESSION['organismes_filters']['search'] ?? '';
        $filter_type = $_SESSION['organismes_filters']['type'] ?? '';
        $filter_statut = $_SESSION['organismes_filters']['statut'] ?? '';
        $filter_pays = $_SESSION['organismes_filters']['pays'] ?? '';
        $filter_ville = $_SESSION['organismes_filters']['ville'] ?? '';
        $page = $_SESSION['organismes_filters']['page'] ?? 1;
        $offset = ($page - 1) * $limit;
    }
}

// Traitement des actions POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // === Créer un organisme ===
        if (isset($_POST['action']) && $_POST['action'] === 'add') {
            $organisme_id = trim($_POST['organisme_id']);
            $nom = trim($_POST['nom']);
            $type = $_POST['type'];
            $telephone = trim($_POST['telephone']);
            $email = trim($_POST['email']);
            $pays = trim($_POST['pays']);
            $ville = trim($_POST['ville']);
            $adresse = trim($_POST['adresse']);
            $statut = $_POST['statut'] ?? 'actif';

            if (empty($organisme_id) || empty($nom) || empty($type)) {
                throw new Exception('Tous les champs obligatoires doivent être remplis.');
            }

            $stmt = $pdo->prepare('SELECT COUNT(*) FROM organismes WHERE organisme_id = ?');
            $stmt->execute([$organisme_id]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception('Cet ID d\'organisme existe déjà.');
            }

            $stmt = $pdo->prepare('SELECT COUNT(*) FROM organismes WHERE nom = ?');
            $stmt->execute([$nom]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception('Un organisme avec ce nom existe déjà.');
            }

            $stmt = $pdo->prepare('INSERT INTO organismes (organisme_id, nom, type, telephone, email, pays, ville, adresse, statut) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$organisme_id, $nom, $type, $telephone, $email, $pays, $ville, $adresse, $statut]);
            $message = 'Organisme créé avec succès.';
        }

        // === Modifier un organisme ===
        if (isset($_POST['action']) && $_POST['action'] === 'edit') {
            $id = $_POST['id'];
            $organisme_id = trim($_POST['organisme_id']);
            $nom = trim($_POST['nom']);
            $type = $_POST['type'];
            $telephone = trim($_POST['telephone']);
            $email = trim($_POST['email']);
            $pays = trim($_POST['pays']);
            $ville = trim($_POST['ville']);
            $adresse = trim($_POST['adresse']);
            $statut = $_POST['statut'];

            if (empty($organisme_id) || empty($nom) || empty($type)) {
                throw new Exception('Tous les champs obligatoires doivent être remplis.');
            }

            $stmt = $pdo->prepare('SELECT COUNT(*) FROM organismes WHERE nom = ? AND organisme_id != ?');
            $stmt->execute([$nom, $id]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception('Un organisme avec ce nom existe déjà.');
            }

            $stmt = $pdo->prepare('UPDATE organismes SET nom=?, type=?, telephone=?, email=?, pays=?, ville=?, adresse=?, statut=? WHERE organisme_id=?');
            $stmt->execute([$nom, $type, $telephone, $email, $pays, $ville, $adresse, $statut, $id]);
            $message = 'Organisme modifié avec succès.';
        }

        // === Supprimer un organisme ===
        if (isset($_POST['action']) && $_POST['action'] === 'delete') {
            $id = $_POST['id'];
            
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM factures WHERE organisme_id = ?');
            $stmt->execute([$id]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception('Impossible de supprimer cet organisme car il est utilisé dans des factures.');
            }
            
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM prestations WHERE organisme_id = ?');
            $stmt->execute([$id]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception('Impossible de supprimer cet organisme car il est utilisé dans des prestations.');
            }
            
            $stmt = $pdo->prepare('DELETE FROM organismes WHERE organisme_id = ?');
            $stmt->execute([$id]);
            $message = 'Organisme supprimé avec succès.';
        }

        // === Charger pour modification ===
        if (isset($_POST['edit_organisme_id'])) {
            $stmt = $pdo->prepare('SELECT o.*, COUNT(DISTINCT f.facture_id) as nb_factures, COUNT(DISTINCT p.prestation_id) as nb_prestations FROM organismes o LEFT JOIN factures f ON o.organisme_id = f.organisme_id LEFT JOIN prestations p ON o.organisme_id = p.organisme_id WHERE o.organisme_id = ? GROUP BY o.organisme_id');
            $stmt->execute([$_POST['edit_organisme_id']]);
            $edit_organisme = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        // === Réinitialiser les filtres ===
        if (isset($_POST['reset_filters'])) {
            unset($_SESSION['organismes_filters']);
            $search = '';
            $filter_type = '';
            $filter_statut = '';
            $filter_pays = '';
            $filter_ville = '';
            $page = 1;
            $offset = 0;
        }

        // === Export Excel ===
        if (isset($_POST['export_excel'])) {
            header('Content-Type: application/vnd.ms-excel');
            header('Content-Disposition: attachment; filename="organismes_' . date('Ymd_His') . '.xls"');
            
            $sql_export = 'SELECT organisme_id, nom, type, telephone, email, pays, ville, adresse, statut FROM organismes';
            $where_clauses = [];
            $params_export = [];
            
            if (!empty($search)) { $where_clauses[] = '(organisme_id LIKE ? OR nom LIKE ?)'; $t="%$search%"; $params_export=array_merge($params_export,[$t,$t]); }
            if (!empty($filter_type)) { $where_clauses[] = "type = ?"; $params_export[] = $filter_type; }
            if (!empty($filter_statut)) { $where_clauses[] = "statut = ?"; $params_export[] = $filter_statut; }
            if (!empty($filter_pays)) { $where_clauses[] = "pays = ?"; $params_export[] = $filter_pays; }
            if (!empty($filter_ville)) { $where_clauses[] = "ville = ?"; $params_export[] = $filter_ville; }
            
            if (!empty($where_clauses)) $sql_export .= ' WHERE ' . implode(' AND ', $where_clauses);
            $sql_export .= ' ORDER BY nom ASC';
            
            $stmt = $pdo->prepare($sql_export);
            $stmt->execute($params_export);
            $export_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo '<table border="1"><tr><th>ID</th><th>Nom</th><th>Type</th><th>Téléphone</th><th>Email</th><th>Pays</th><th>Ville</th><th>Adresse</th><th>Statut</th></tr>';
            foreach ($export_data as $row) {
                echo '<tr>';
                echo '<td>' . htmlspecialchars($row['organisme_id']) . '</td>';
                echo '<td>' . htmlspecialchars($row['nom']) . '</td>';
                echo '<td>' . ucfirst($row['type']) . '</td>';
                echo '<td>' . htmlspecialchars($row['telephone'] ?? '') . '</td>';
                echo '<td>' . htmlspecialchars($row['email'] ?? '') . '</td>';
                echo '<td>' . htmlspecialchars($row['pays'] ?? '') . '</td>';
                echo '<td>' . htmlspecialchars($row['ville'] ?? '') . '</td>';
                echo '<td>' . htmlspecialchars($row['adresse'] ?? '') . '</td>';
                echo '<td>' . ucfirst($row['statut']) . '</td>';
                echo '</tr>';
            }
            echo '</table>';
            exit;
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Récupération des données pour l'affichage
$offset = ($page - 1) * $limit;

$types_organisme = ['clinique', 'pharmacie', 'laboratoire'];
$statuts_organisme = ['actif', 'inactif'];

// Liste des pays
$stmt = $pdo->prepare('SELECT DISTINCT pays FROM organismes WHERE pays IS NOT NULL AND pays != "" ORDER BY pays');
$stmt->execute();
$liste_pays = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Liste des villes
$stmt = $pdo->prepare('SELECT DISTINCT ville FROM organismes WHERE ville IS NOT NULL AND ville != "" ORDER BY ville');
$stmt->execute();
$liste_villes = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Construction de la requête de comptage
$count_sql = 'SELECT COUNT(*) FROM organismes';
$count_params = [];
$where_clauses = [];

if (!empty($search)) { $where_clauses[] = '(organisme_id LIKE ? OR nom LIKE ? OR telephone LIKE ? OR email LIKE ?)'; $t="%$search%"; $count_params=array_merge($count_params,[$t,$t,$t,$t]); }
if (!empty($filter_type)) { $where_clauses[] = 'type = ?'; $count_params[] = $filter_type; }
if (!empty($filter_statut)) { $where_clauses[] = 'statut = ?'; $count_params[] = $filter_statut; }
if (!empty($filter_pays)) { $where_clauses[] = 'pays = ?'; $count_params[] = $filter_pays; }
if (!empty($filter_ville)) { $where_clauses[] = 'ville = ?'; $count_params[] = $filter_ville; }

if (!empty($where_clauses)) $count_sql .= ' WHERE ' . implode(' AND ', $where_clauses);
$stmt = $pdo->prepare($count_sql);
$stmt->execute($count_params);
$total_organismes = $stmt->fetchColumn();
$total_pages = max(1, ceil($total_organismes / $limit));

// Requête principale
$sql = 'SELECT o.*, COUNT(DISTINCT f.facture_id) as nb_factures, COUNT(DISTINCT p.prestation_id) as nb_prestations FROM organismes o LEFT JOIN factures f ON o.organisme_id = f.organisme_id LEFT JOIN prestations p ON o.organisme_id = p.organisme_id';
$params = $count_params;
$where_clauses = [];

if (!empty($search)) { $where_clauses[] = '(o.organisme_id LIKE ? OR o.nom LIKE ? OR o.telephone LIKE ? OR o.email LIKE ?)'; $t="%$search%"; $params=array_merge($params,[$t,$t,$t,$t]); }
if (!empty($filter_type)) { $where_clauses[] = 'o.type = ?'; $params[] = $filter_type; }
if (!empty($filter_statut)) { $where_clauses[] = 'o.statut = ?'; $params[] = $filter_statut; }
if (!empty($filter_pays)) { $where_clauses[] = 'o.pays = ?'; $params[] = $filter_pays; }
if (!empty($filter_ville)) { $where_clauses[] = 'o.ville = ?'; $params[] = $filter_ville; }

if (!empty($where_clauses)) $sql .= ' WHERE ' . implode(' AND ', $where_clauses);
$sql .= ' GROUP BY o.organisme_id ORDER BY o.nom ASC LIMIT ? OFFSET ?';

$stmt = $pdo->prepare($sql);
$i = 1;
foreach ($params as $p) $stmt->bindValue($i++, $p, PDO::PARAM_STR);
$stmt->bindValue($i++, $limit, PDO::PARAM_INT);
$stmt->bindValue($i++, $offset, PDO::PARAM_INT);
$stmt->execute();
$organismes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Statistiques
$stmt = $pdo->query('SELECT COUNT(*) FROM organismes'); $total_general = $stmt->fetchColumn();
$stmt = $pdo->query('SELECT COUNT(*) FROM organismes WHERE statut = "actif"'); $total_actif = $stmt->fetchColumn();
$stmt = $pdo->query('SELECT COUNT(*) FROM organismes WHERE statut = "inactif"'); $total_inactif = $stmt->fetchColumn();
$stmt = $pdo->query('SELECT COUNT(DISTINCT pays) FROM organismes WHERE pays IS NOT NULL AND pays != ""'); $pays_count = $stmt->fetchColumn();

// Filtres actifs
$active_filters = [];
if (!empty($search)) $active_filters[] = ['label' => 'Recherche', 'value' => $search];
if (!empty($filter_type)) $active_filters[] = ['label' => 'Type', 'value' => ucfirst($filter_type)];
if (!empty($filter_statut)) $active_filters[] = ['label' => 'Statut', 'value' => ucfirst($filter_statut)];
if (!empty($filter_pays)) $active_filters[] = ['label' => 'Pays', 'value' => $filter_pays];
if (!empty($filter_ville)) $active_filters[] = ['label' => 'Ville', 'value' => $filter_ville];

// Fonctions pour les badges
function getStatusBadge($status) {
    $statusMap = [
        'actif' => ['class' => 'success', 'label' => 'Actif'],
        'inactif' => ['class' => 'danger', 'label' => 'Inactif']
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

function getTypeBadge($type) {
    $typeMap = [
        'clinique' => ['class' => 'primary', 'icon' => 'bi-hospital'],
        'pharmacie' => ['class' => 'success', 'icon' => 'bi-capsule'],
        'laboratoire' => ['class' => 'warning', 'icon' => 'bi-flask']
    ];
    $info = $typeMap[$type] ?? ['class' => 'secondary', 'icon' => 'bi-building'];
    return '<span class="badge bg-' . $info['class'] . '"><i class="bi ' . $info['icon'] . ' me-1"></i>' . htmlspecialchars(ucfirst($type)) . '</span>';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des organismes - Epencia SGI</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=DM+Serif+Display:ital@0;1&display=swap" rel="stylesheet">
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

        /* ============================================================ */
        /* CONTAINER */
        /* ============================================================ */
        .app-container {
            max-width: 1400px;
            margin: 0 auto;
        }

        /* ============================================================ */
        /* HEADER */
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

        /* ============================================================ */
        /* HEADER ACTIONS */
        /* ============================================================ */
        .header-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            align-items: center;
            flex-shrink: 0;
            margin-left: auto;
        }

        .btn-medical-primary {
            background: var(--medical-blue);
            color: #fff;
            border: none;
            padding: 10px 24px;
            border-radius: var(--medical-radius-sm);
            font-family: var(--font-primary);
            font-weight: 700;
            font-size: clamp(0.75rem, 0.9vw, 0.9rem);
            transition: var(--medical-transition);
            display: inline-flex;
            align-items: center;
            gap: 8px;
            letter-spacing: 0.02em;
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
            font-size: clamp(0.75rem, 0.9vw, 0.9rem);
            transition: var(--medical-transition);
            display: inline-flex;
            align-items: center;
            gap: 8px;
            letter-spacing: 0.02em;
            text-decoration: none;
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
            font-size: clamp(0.75rem, 0.9vw, 0.9rem);
            transition: var(--medical-transition);
            display: inline-flex;
            align-items: center;
            gap: 8px;
            letter-spacing: 0.02em;
            text-decoration: none;
        }

        .btn-medical-outline:hover {
            background: var(--medical-gray-light);
            border-color: var(--medical-blue);
            color: var(--medical-blue);
            text-decoration: none;
        }

        /* ============================================================ */
        /* BOUTON RETOUR ADAPTATIF - 4 PALIERS */
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

        /* PALIER 1 : ≤ 820px */
        @media (max-width: 820px) {
            .btn-back-adaptive {
                padding: 10px 0;
                width: 42px;
                height: 42px;
                border-radius: 50%;
            }
            .btn-back-adaptive .btn-label { display: none; }
            .btn-back-adaptive .btn-icon { font-size: 18px; }
        }

        /* PALIER 2 : 821 → 1024px */
        @media (min-width: 821px) and (max-width: 1024px) {
            .btn-back-adaptive { padding: 9px 14px; font-size: 0.8rem; gap: 6px; }
            .btn-back-adaptive .btn-label.short { display: inline; }
            .btn-back-adaptive .btn-label.long { display: none; }
        }

        /* PALIER 3 : 1025 → 1366px */
        @media (min-width: 1025px) and (max-width: 1366px) {
            .btn-back-adaptive { padding: 10px 18px; font-size: 0.85rem; }
            .btn-back-adaptive .btn-label.short { display: none; }
            .btn-back-adaptive .btn-label.long { display: inline; }
        }

        /* PALIER 4 : 1367 → 1960px */
        @media (min-width: 1367px) {
            .btn-back-adaptive { padding: 10px 24px; font-size: 0.9rem; }
            .btn-back-adaptive .btn-label.short { display: none; }
            .btn-back-adaptive .btn-label.long { display: inline; }
        }

        /* HAUTEUR < 700px */
        @media (max-height: 700px) and (max-width: 820px) {
            .page-header { padding: 10px 14px; margin-bottom: 12px; }
            .page-header .medical-badge { display: none; }
            .page-header h1 { font-size: 17px; }
            .page-header .subtitle { font-size: 10px; }
            .btn-back-adaptive { width: 36px; height: 36px; }
            .btn-back-adaptive .btn-icon { font-size: 15px; }
        }

        /* ============================================================ */
        /* STATS CARDS */
        /* ============================================================ */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }

        .stat-card {
            background: var(--medical-white);
            padding: 18px 20px;
            border-radius: var(--medical-radius);
            box-shadow: var(--medical-shadow);
            border: 1px solid var(--medical-border);
            display: flex;
            align-items: center;
            gap: 14px;
            transition: var(--medical-transition);
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--medical-shadow-hover);
        }

        .stat-card .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            flex-shrink: 0;
        }

        .stat-card .stat-icon.blue { background: var(--medical-blue-light); color: var(--medical-blue); }
        .stat-card .stat-icon.success { background: var(--success-light); color: var(--success); }
        .stat-card .stat-icon.danger { background: var(--danger-light); color: var(--danger); }
        .stat-card .stat-icon.warning { background: var(--warning-light); color: var(--warning); }

        .stat-card .stat-value {
            font-size: clamp(20px, 1.8vw, 24px);
            font-weight: 700;
            color: var(--medical-text);
            line-height: 1.1;
            font-family: var(--font-primary);
        }

        .stat-card .stat-label {
            font-size: clamp(11px, 0.8vw, 13px);
            color: var(--medical-text-muted);
            font-weight: 500;
            font-family: var(--font-primary);
        }

        /* ============================================================ */
        /* CARD */
        /* ============================================================ */
        .card-modern {
            background: var(--medical-white);
            border: none;
            border-radius: var(--medical-radius);
            box-shadow: var(--medical-shadow);
            overflow: hidden;
            border: 1px solid var(--medical-border);
            font-family: var(--font-primary);
            transition: var(--medical-transition);
        }

        .card-modern:hover {
            box-shadow: var(--medical-shadow-hover);
        }

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
            font-family: var(--font-primary);
            font-size: clamp(14px, 1.1vw, 16px);
            font-weight: 700;
            margin: 0;
            color: var(--medical-text);
            display: flex;
            align-items: center;
            gap: 8px;
            letter-spacing: 0.01em;
        }

        .card-modern .card-header .badge-count {
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
        /* FILTERS */
        /* ============================================================ */
        .filter-section {
            background: var(--medical-white);
            border-radius: var(--medical-radius);
            padding: 20px;
            margin-bottom: 24px;
            box-shadow: var(--medical-shadow);
            border: 1px solid var(--medical-border);
            border-left: 4px solid var(--medical-blue);
        }

        .filter-section .filter-title {
            font-weight: 700;
            color: var(--medical-text);
            margin-bottom: 16px;
            font-size: clamp(13px, 0.9vw, 14px);
            font-family: var(--font-primary);
            display: flex;
            align-items: center;
            gap: 8px;
        }

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
            font-family: var(--font-primary);
        }

        .form-label {
            font-weight: 600 !important;
            font-size: 10px !important;
            color: var(--medical-text-secondary) !important;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            font-family: var(--font-primary) !important;
            margin-bottom: 4px !important;
        }

        .form-control, .form-select {
            border: 1.5px solid var(--medical-border);
            border-radius: var(--medical-radius-sm);
            font-family: var(--font-primary);
            font-size: clamp(0.8rem, 0.85vw, 0.9rem);
            padding: 8px 12px;
            color: var(--medical-text);
            transition: var(--medical-transition);
            background: var(--medical-gray-light);
            height: 42px;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--medical-blue);
            box-shadow: 0 0 0 3px rgba(26, 107, 138, 0.1);
            background: var(--medical-white);
        }

        .form-control::placeholder {
            color: var(--medical-text-muted);
            font-weight: 400;
        }

        .filter-actions {
            display: flex;
            gap: 6px;
            align-items: flex-end;
            height: 42px;
        }

        .filter-actions .btn {
            font-family: var(--font-primary);
            font-weight: 700;
            height: 42px;
            padding: 0 18px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            font-size: clamp(0.75rem, 0.8vw, 0.85rem);
            border-radius: var(--medical-radius-sm);
            transition: var(--medical-transition);
            border: none;
            white-space: nowrap;
            letter-spacing: 0.02em;
        }

        .btn-filter {
            background: var(--medical-blue);
            color: #fff;
        }
        .btn-filter:hover {
            background: var(--medical-blue-dark);
            color: #fff;
        }

        .btn-reset {
            background: var(--medical-gray);
            color: var(--medical-text-secondary);
        }
        .btn-reset:hover {
            background: var(--medical-border);
            color: var(--medical-text);
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
            min-width: 680px;
        }

        .table-modern thead th {
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
            padding: 12px 16px;
            border-bottom: 1px solid var(--medical-border);
            color: var(--medical-text);
            vertical-align: middle;
            font-size: clamp(0.75rem, 0.8vw, 0.85rem);
        }

        .table-modern tbody tr:last-child td { border-bottom: none; }
        .table-modern tbody tr:hover { background: var(--medical-gray-light); }
        .table-modern tbody tr.row-actif { background: rgba(45, 155, 142, 0.04); }
        .table-modern tbody tr.row-inactif { background: rgba(192, 57, 43, 0.04); }

        /* ============================================================ */
        /* BADGES */
        /* ============================================================ */
        .badge {
            font-family: var(--font-primary);
            font-weight: 700;
            padding: 4px 12px;
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

        .badge i { font-size: 0.85em; }

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
        .btn-action.delete.disabled { opacity: 0.4; cursor: not-allowed; }
        .btn-action.delete.disabled:hover { transform: none; background: transparent; color: var(--medical-text-muted); }

        /* ============================================================ */
        /* ALERTS */
        /* ============================================================ */
        .alert-medical {
            border: none;
            border-radius: var(--medical-radius-sm);
            padding: 14px 20px;
            font-family: var(--font-primary);
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
        /* MODALS */
        /* ============================================================ */
        .modal-content {
            border: none;
            border-radius: var(--medical-radius);
            overflow: hidden;
            font-family: var(--font-primary);
            box-shadow: 0 24px 60px rgba(0, 0, 0, 0.15);
        }

        .modal-header-medical {
            background: var(--medical-blue);
            color: white;
            border: none;
            padding: 16px 24px;
        }

        .modal-header-medical .modal-title {
            font-family: var(--font-primary);
            font-weight: 700;
            font-size: clamp(16px, 1.2vw, 18px);
        }

        .modal-header-medical .btn-close {
            filter: brightness(0) invert(1);
        }

        .modal-header-medical.danger {
            background: var(--danger);
        }

        .modal-body {
            padding: 24px;
        }

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
            font-size: clamp(0.8rem, 0.9vw, 0.9rem);
            transition: var(--medical-transition);
            height: 42px;
            font-weight: 500;
            color: var(--medical-text);
            font-family: var(--font-primary);
        }

        .modal .form-control:focus, .modal .form-select:focus {
            border-color: var(--medical-blue);
            box-shadow: 0 0 0 4px rgba(26, 107, 138, 0.1);
            background: #fff;
        }

        .modal .form-label {
            font-family: var(--font-primary);
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
            font-family: var(--font-primary);
        }

        .form-section-title i {
            color: var(--medical-blue);
        }

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
            .stats-grid { grid-template-columns: repeat(2, 1fr); gap: 10px; }
            .stat-card .stat-icon { width: 40px; height: 40px; font-size: 16px; }
            .stat-card .stat-value { font-size: 18px; }
            .filter-section { padding: 14px 16px; }
            .card-modern .card-header { padding: 12px 16px; }
            .table-modern thead th, .table-modern tbody td { padding: 8px 12px; }
            .btn-action { width: 30px; height: 30px; font-size: 13px; }
            .header-actions .btn { padding: 7px 14px; font-size: 0.8rem; }
            .modal-body { padding: 16px; }
            .filter-actions { height: auto; flex-wrap: wrap; }
            .filter-actions .btn { height: 38px; padding: 0 14px; font-size: 0.8rem; }
        }

        @media (max-width: 576px) {
            body { padding: 6px; }
            .stats-grid { grid-template-columns: 1fr 1fr; gap: 6px; }
            .stat-card { padding: 12px 14px; gap: 10px; }
            .stat-card .stat-icon { width: 34px; height: 34px; font-size: 14px; border-radius: 8px; }
            .stat-card .stat-value { font-size: 16px; }
            .stat-card .stat-label { font-size: 10px; }
            .filter-section { padding: 10px 12px; }
            .filter-section .form-control, .filter-section .form-select { font-size: 0.75rem; padding: 6px 10px; height: 36px; }
            .table-modern { font-size: 0.7rem; min-width: 550px; }
            .table-modern thead th, .table-modern tbody td { padding: 6px 8px; }
            .btn-action { width: 26px; height: 26px; font-size: 11px; border-radius: 6px; }
            .pagination .page-link { padding: 4px 8px; font-size: 0.7rem; }
            .modal-dialog { margin: 8px; }
            .modal-body { padding: 12px; }
            .header-actions .btn span { display: none; }
            .header-actions .btn i { font-size: 1.1rem; }
        }

        /* ============================================================ */
        /* ANIMATIONS */
        /* ============================================================ */
        @keyframes fadeSlideUp {
            from { opacity: 0; transform: translateY(16px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .fade-in { animation: fadeSlideUp 0.5s ease forwards; }
        .fade-in-d1 { animation-delay: 0.05s; }
        .fade-in-d2 { animation-delay: 0.10s; }
        .fade-in-d3 { animation-delay: 0.15s; }

        /* ============================================================ */
        /* SCROLLBAR */
        /* ============================================================ */
        .table-wrapper::-webkit-scrollbar { height: 5px; }
        .table-wrapper::-webkit-scrollbar-track { background: var(--medical-gray-light); border-radius: 10px; }
        .table-wrapper::-webkit-scrollbar-thumb { background: var(--medical-border); border-radius: 10px; }
        .table-wrapper::-webkit-scrollbar-thumb:hover { background: var(--medical-text-muted); }

        ::-webkit-scrollbar { width: 5px; }
        ::-webkit-scrollbar-track { background: var(--medical-gray-light); border-radius: 10px; }
        ::-webkit-scrollbar-thumb { background: var(--medical-border); border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: var(--medical-text-muted); }
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
            <h1>Gestion des <span class="highlight">organismes</span></h1>
            <div class="subtitle">
                <i class="bi bi-building"></i>
                Cliniques, pharmacies et laboratoires
                <span class="dot"></span>
                <i class="bi bi-person-circle"></i> <?php echo htmlspecialchars($user_nom); ?>
                <span class="dot"></span>
                <?php echo getRoleBadge($user_role); ?>
            </div>
        </div>
        <div class="header-actions">
            <button class="btn-medical-primary" data-bs-toggle="modal" data-bs-target="#addModal">
                <i class="bi bi-plus-circle"></i> <span>Nouvel organisme</span>
            </button>
            <form method="POST" class="d-inline">
                <button type="submit" name="export_excel" class="btn-medical-secondary">
                    <i class="bi bi-file-earmark-excel"></i> <span>Export</span>
                </button>
            </form>
        </div>
    </header>

    <!-- ============================================================ -->
    <!-- MESSAGES -->
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
    <!-- STATISTIQUES -->
    <!-- ============================================================ -->
    <div class="stats-grid fade-in">
        <div class="stat-card">
            <div class="stat-icon blue"><i class="bi bi-building"></i></div>
            <div>
                <div class="stat-value"><?= $total_general ?></div>
                <div class="stat-label">Total organismes</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon success"><i class="bi bi-check-circle"></i></div>
            <div>
                <div class="stat-value"><?= $total_actif ?></div>
                <div class="stat-label">Organismes actifs</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon danger"><i class="bi bi-x-circle"></i></div>
            <div>
                <div class="stat-value"><?= $total_inactif ?></div>
                <div class="stat-label">Organismes inactifs</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon warning"><i class="bi bi-geo-alt"></i></div>
            <div>
                <div class="stat-value"><?= $pays_count ?></div>
                <div class="stat-label">Pays représentés</div>
            </div>
        </div>
    </div>

    <!-- ============================================================ -->
    <!-- FILTRES -->
    <!-- ============================================================ -->
    <form method="POST" action="" id="filterForm" class="filter-section fade-in fade-in-d1">
        <div class="filter-title">
            <i class="bi bi-funnel-fill" style="color:var(--medical-blue);"></i>
            Filtres avancés
        </div>
        <div class="row g-2 g-md-3 align-items-end">
            <div class="col-12 col-md-3">
                <label class="form-label">Recherche</label>
                <input type="text" class="form-control" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="ID, nom, téléphone...">
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label">Type</label>
                <select name="type" class="form-select">
                    <option value="">Tous</option>
                    <?php foreach ($types_organisme as $t): ?>
                        <option value="<?= $t ?>" <?= $filter_type == $t ? 'selected' : '' ?>><?= ucfirst($t) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label">Statut</label>
                <select name="statut" class="form-select">
                    <option value="">Tous</option>
                    <?php foreach ($statuts_organisme as $s): ?>
                        <option value="<?= $s ?>" <?= $filter_statut == $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label">Pays</label>
                <select name="pays" class="form-select">
                    <option value="">Tous</option>
                    <?php foreach ($liste_pays as $p): ?>
                        <option value="<?= htmlspecialchars($p) ?>" <?= $filter_pays == $p ? 'selected' : '' ?>><?= htmlspecialchars($p) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label">Ville</label>
                <select name="ville" class="form-select">
                    <option value="">Toutes</option>
                    <?php foreach ($liste_villes as $v): ?>
                        <option value="<?= htmlspecialchars($v) ?>" <?= $filter_ville == $v ? 'selected' : '' ?>><?= htmlspecialchars($v) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-1">
                <div class="filter-actions">
                    <button type="submit" name="apply_filters" class="btn btn-filter w-100">
                        <i class="bi bi-search"></i>
                    </button>
                    <button type="submit" name="reset_filters" class="btn btn-reset">
                        <i class="bi bi-arrow-repeat"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- Filtres actifs -->
        <?php if (!empty($active_filters)): ?>
            <div class="filter-pills">
                <span style="font-size:12px;color:var(--medical-text-muted);font-weight:600;"><i class="bi bi-funnel-fill"></i> Filtres :</span>
                <?php foreach ($active_filters as $f): ?>
                    <span class="filter-pill"><strong><?= $f['label'] ?>:</strong> <?= htmlspecialchars($f['value']) ?></span>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </form>

    <!-- ============================================================ -->
    <!-- LISTE DES ORGANISMES -->
    <!-- ============================================================ -->
    <div class="card-modern fade-in fade-in-d2">
        <div class="card-header">
            <h5>
                <i class="bi bi-list-ul" style="color:var(--medical-blue);"></i>
                Liste des organismes
                <span class="badge-count"><?= $total_organismes ?></span>
            </h5>
            <?php if (!empty($active_filters)): ?>
                <form method="POST" class="d-inline">
                    <button type="submit" name="reset_filters" class="btn-reset" style="padding:4px 14px;font-size:clamp(10px,0.7vw,12px);height:auto;border:none;border-radius:8px;font-weight:700;background:var(--medical-gray);color:var(--medical-text-secondary);">
                        <i class="bi bi-x-lg"></i> Effacer
                    </button>
                </form>
            <?php endif; ?>
        </div>
        <div class="card-body p-0">
            <div class="table-wrapper">
                <table class="table table-modern">
                    <thead>
                        <tr>
                            <th style="width:100px;">ID</th>
                            <th>Nom</th>
                            <th style="width:100px;">Type</th>
                            <th>Contact</th>
                            <th>Localisation</th>
                            <th class="text-center" style="width:80px;">Activité</th>
                            <th class="text-center" style="width:80px;">Statut</th>
                            <th class="text-center" style="width:90px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($organismes)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-5" style="color:var(--medical-text-muted);">
                                    <i class="bi bi-inbox" style="font-size:3rem;display:block;margin-bottom:12px;opacity:0.3;"></i>
                                    Aucun organisme trouvé.
                                </td>
                            </tr>
                        <?php else: foreach ($organismes as $o):
                            $row_class = 'row-' . ($o['statut'] ?? 'actif');
                        ?>
                            <tr class="<?= $row_class ?>">
                                <td><code style="font-size:clamp(11px,0.75vw,13px);color:var(--medical-blue);font-weight:600;"><?= htmlspecialchars($o['organisme_id']) ?></code></td>
                                <td>
                                    <strong style="color:var(--medical-text);"><?= htmlspecialchars($o['nom']) ?></strong>
                                    <?php if (!empty($o['adresse'])): ?>
                                        <br><small style="color:var(--medical-text-muted);font-size:clamp(10px,0.7vw,12px);"><?= htmlspecialchars($o['adresse']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?= getTypeBadge($o['type']) ?></td>
                                <td>
                                    <?php if (!empty($o['telephone'])): ?>
                                        <div style="font-size:clamp(11px,0.75vw,13px);"><i class="bi bi-telephone me-1" style="color:var(--medical-text-muted);"></i><?= htmlspecialchars($o['telephone']) ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($o['email'])): ?>
                                        <div style="font-size:clamp(11px,0.75vw,13px);"><i class="bi bi-envelope me-1" style="color:var(--medical-text-muted);"></i><?= htmlspecialchars($o['email']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($o['ville']) || !empty($o['pays'])): ?>
                                        <?php if (!empty($o['ville'])): ?>
                                            <i class="bi bi-geo-alt me-1" style="color:var(--medical-text-muted);font-size:clamp(10px,0.7vw,12px);"></i><?= htmlspecialchars($o['ville']) ?>
                                        <?php endif; ?>
                                        <?php if (!empty($o['pays'])): ?>
                                            <br><small style="color:var(--medical-text-muted);font-size:clamp(10px,0.7vw,12px);"><?= htmlspecialchars($o['pays']) ?></small>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span style="color:var(--medical-text-muted);font-size:clamp(11px,0.75vw,13px);">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($o['nb_factures'] > 0): ?>
                                        <span class="badge bg-primary" style="font-size:10px;">
                                            <i class="bi bi-receipt me-1"></i><?= $o['nb_factures'] ?>
                                        </span>
                                    <?php endif; ?>
                                    <?php if ($o['nb_prestations'] > 0): ?>
                                        <span class="badge bg-success" style="font-size:10px;">
                                            <i class="bi bi-clipboard-check me-1"></i><?= $o['nb_prestations'] ?>
                                        </span>
                                    <?php endif; ?>
                                    <?php if ($o['nb_factures'] == 0 && $o['nb_prestations'] == 0): ?>
                                        <span style="color:var(--medical-text-muted);font-size:11px;">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center"><?= getStatusBadge($o['statut'] ?? 'actif') ?></td>
                                <td>
                                    <div class="actions-group">
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="edit_organisme_id" value="<?= htmlspecialchars($o['organisme_id']) ?>">
                                            <input type="hidden" name="page" value="<?= $page ?>">
                                            <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
                                            <input type="hidden" name="type" value="<?= htmlspecialchars($filter_type) ?>">
                                            <input type="hidden" name="statut" value="<?= htmlspecialchars($filter_statut) ?>">
                                            <input type="hidden" name="pays" value="<?= htmlspecialchars($filter_pays) ?>">
                                            <input type="hidden" name="ville" value="<?= htmlspecialchars($filter_ville) ?>">
                                            <button type="submit" class="btn-action edit" title="Modifier">
                                                <i class="bi bi-pencil-square"></i>
                                            </button>
                                        </form>
                                        <?php if ($o['nb_factures'] == 0 && $o['nb_prestations'] == 0): ?>
                                            <button type="button" class="btn-action delete" data-bs-toggle="modal" data-bs-target="#deleteModal"
                                                data-organisme-id="<?= htmlspecialchars($o['organisme_id']) ?>"
                                                data-organisme-nom="<?= htmlspecialchars($o['nom']) ?>"
                                                title="Supprimer">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        <?php else: ?>
                                            <button type="button" class="btn-action delete disabled" disabled title="Utilisé dans des factures ou prestations">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        <?php endif; ?>
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
                                <input type="hidden" name="type" value="<?= htmlspecialchars($filter_type) ?>">
                                <input type="hidden" name="statut" value="<?= htmlspecialchars($filter_statut) ?>">
                                <input type="hidden" name="pays" value="<?= htmlspecialchars($filter_pays) ?>">
                                <input type="hidden" name="ville" value="<?= htmlspecialchars($filter_ville) ?>">
                                <button type="submit" class="page-link" <?= $page <= 1 ? 'disabled' : '' ?>>
                                    <i class="bi bi-chevron-left"></i>
                                </button>
                            </form>
                        </li>
                        <?php for ($pg = max(1, $page - 2); $pg <= min($total_pages, $page + 2); $pg++): ?>
                            <li class="page-item <?= $pg === $page ? 'active' : '' ?>">
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="page" value="<?= $pg ?>">
                                    <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
                                    <input type="hidden" name="type" value="<?= htmlspecialchars($filter_type) ?>">
                                    <input type="hidden" name="statut" value="<?= htmlspecialchars($filter_statut) ?>">
                                    <input type="hidden" name="pays" value="<?= htmlspecialchars($filter_pays) ?>">
                                    <input type="hidden" name="ville" value="<?= htmlspecialchars($filter_ville) ?>">
                                    <button type="submit" class="page-link"><?= $pg ?></button>
                                </form>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="page" value="<?= $page + 1 ?>">
                                <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
                                <input type="hidden" name="type" value="<?= htmlspecialchars($filter_type) ?>">
                                <input type="hidden" name="statut" value="<?= htmlspecialchars($filter_statut) ?>">
                                <input type="hidden" name="pays" value="<?= htmlspecialchars($filter_pays) ?>">
                                <input type="hidden" name="ville" value="<?= htmlspecialchars($filter_ville) ?>">
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
<!-- MODAL NOUVEL ORGANISME -->
<!-- ============================================================ -->
<div class="modal fade" id="addModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header modal-header-medical">
                <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>Nouvel organisme</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">

                    <div class="form-section-title">
                        <i class="bi bi-fingerprint"></i> Identification
                    </div>
                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <label class="form-label">ID Organisme <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="organisme_id" required placeholder="Ex: ORG-001">
                        </div>
                        <div class="col-md-5">
                            <label class="form-label">Nom <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="nom" required placeholder="Ex: Clinique Centrale">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Type <span class="text-danger">*</span></label>
                            <select name="type" class="form-select" required>
                                <option value="">Choisir...</option>
                                <?php foreach ($types_organisme as $t): ?>
                                    <option value="<?= $t ?>"><?= ucfirst($t) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-section-title">
                        <i class="bi bi-telephone"></i> Contact
                    </div>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label">Téléphone</label>
                            <input type="text" class="form-control" name="telephone" placeholder="Ex: +225 07 00 00 00">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email" placeholder="Ex: contact@clinique.com">
                        </div>
                    </div>

                    <div class="form-section-title">
                        <i class="bi bi-geo-alt"></i> Localisation
                    </div>
                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <label class="form-label">Pays</label>
                            <input type="text" class="form-control" name="pays" placeholder="Ex: Côte d'Ivoire">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Ville</label>
                            <input type="text" class="form-control" name="ville" placeholder="Ex: Abidjan">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Adresse</label>
                            <input type="text" class="form-control" name="adresse" placeholder="Ex: Zone 4, Rue 12">
                        </div>
                    </div>

                    <div class="form-section-title">
                        <i class="bi bi-toggle-on"></i> Statut
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <select name="statut" class="form-select">
                                <option value="actif">Actif</option>
                                <option value="inactif">Inactif</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-medical-outline" data-bs-dismiss="modal">
                        <i class="bi bi-x-lg"></i> Annuler
                    </button>
                    <button type="submit" class="btn-medical-primary">
                        <i class="bi bi-check-lg"></i> Créer l'organisme
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ============================================================ -->
<!-- MODAL MODIFIER ORGANISME -->
<!-- ============================================================ -->
<?php if ($edit_organisme): ?>
<div class="modal fade" id="editModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header modal-header-medical">
                <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i>Modifier l'organisme</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" value="<?= htmlspecialchars($edit_organisme['organisme_id']) ?>">

                    <div class="form-section-title">
                        <i class="bi bi-fingerprint"></i> Identification
                    </div>
                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <label class="form-label">ID Organisme</label>
                            <input type="text" class="form-control" value="<?= htmlspecialchars($edit_organisme['organisme_id']) ?>" disabled>
                            <input type="hidden" name="organisme_id" value="<?= htmlspecialchars($edit_organisme['organisme_id']) ?>">
                        </div>
                        <div class="col-md-5">
                            <label class="form-label">Nom <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="nom" required value="<?= htmlspecialchars($edit_organisme['nom']) ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Type <span class="text-danger">*</span></label>
                            <select name="type" class="form-select" required>
                                <?php foreach ($types_organisme as $t): ?>
                                    <option value="<?= $t ?>" <?= $edit_organisme['type'] == $t ? 'selected' : '' ?>><?= ucfirst($t) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-section-title">
                        <i class="bi bi-telephone"></i> Contact
                    </div>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label">Téléphone</label>
                            <input type="text" class="form-control" name="telephone" value="<?= htmlspecialchars($edit_organisme['telephone'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email" value="<?= htmlspecialchars($edit_organisme['email'] ?? '') ?>">
                        </div>
                    </div>

                    <div class="form-section-title">
                        <i class="bi bi-geo-alt"></i> Localisation
                    </div>
                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <label class="form-label">Pays</label>
                            <input type="text" class="form-control" name="pays" value="<?= htmlspecialchars($edit_organisme['pays'] ?? '') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Ville</label>
                            <input type="text" class="form-control" name="ville" value="<?= htmlspecialchars($edit_organisme['ville'] ?? '') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Adresse</label>
                            <input type="text" class="form-control" name="adresse" value="<?= htmlspecialchars($edit_organisme['adresse'] ?? '') ?>">
                        </div>
                    </div>

                    <div class="form-section-title">
                        <i class="bi bi-toggle-on"></i> Statut
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <select name="statut" class="form-select">
                                <option value="actif" <?= ($edit_organisme['statut'] ?? '') == 'actif' ? 'selected' : '' ?>>Actif</option>
                                <option value="inactif" <?= ($edit_organisme['statut'] ?? '') == 'inactif' ? 'selected' : '' ?>>Inactif</option>
                            </select>
                        </div>
                    </div>

                    <?php if ($edit_organisme['nb_factures'] > 0 || $edit_organisme['nb_prestations'] > 0): ?>
                        <div class="alert-medical alert-info mt-4" style="font-size:13px;">
                            <i class="bi bi-info-circle-fill me-1"></i>
                            Cet organisme est lié à
                            <?= $edit_organisme['nb_factures'] ?> facture(s) et
                            <?= $edit_organisme['nb_prestations'] ?> prestation(s).
                            La suppression n'est pas possible.
                        </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-medical-outline" data-bs-dismiss="modal">
                        <i class="bi bi-x-lg"></i> Annuler
                    </button>
                    <button type="submit" class="btn-medical-primary">
                        <i class="bi bi-check-lg"></i> Enregistrer
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

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
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" id="deleteOrganismeId" value="">

                    <div style="width:64px;height:64px;border-radius:50%;background:var(--danger-light);color:var(--danger);display:flex;align-items:center;justify-content:center;font-size:28px;margin:0 auto 16px;">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                    </div>
                    <p style="font-weight:600;font-size:15px;color:var(--medical-text);margin-bottom:8px;">Supprimer cet organisme ?</p>
                    <p style="font-size:13px;color:var(--medical-text-muted);">
                        <strong id="deleteOrganismeNom" style="color:var(--medical-text);"></strong>
                        <br>Cette action est irréversible.
                    </p>
                </div>
                <div class="modal-footer justify-content-center">
                    <button type="button" class="btn-medical-outline" data-bs-dismiss="modal" style="padding:8px 20px;font-size:13px;">
                        <i class="bi bi-x-lg"></i> Annuler
                    </button>
                    <button type="submit" class="btn-medical-danger" style="padding:8px 20px;font-size:13px;">
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
    // Transfert des données vers le modal de suppression
    const deleteModal = document.getElementById('deleteModal');
    if (deleteModal) {
        deleteModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            if (button) {
                const id = button.getAttribute('data-organisme-id');
                const nom = button.getAttribute('data-organisme-nom');
                document.getElementById('deleteOrganismeId').value = id;
                document.getElementById('deleteOrganismeNom').textContent = nom;
            }
        });
    }

    // Ouvrir automatiquement le modal d'édition si chargé
    <?php if ($edit_organisme): ?>
        document.addEventListener('DOMContentLoaded', function() {
            const editModal = new bootstrap.Modal(document.getElementById('editModal'));
            editModal.show();
        });
    <?php endif; ?>
</script>

</body>
</html>