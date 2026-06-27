<?php
// ================================================================
// GESTION DES DONNÉES - EPENCIA SGI
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

function getSexeBadge($sexe) {
    $sexeMap = [
        'M' => ['class' => 'primary', 'icon' => 'bi-gender-male'],
        'F' => ['class' => 'danger', 'icon' => 'bi-gender-female'],
        'Tous' => ['class' => 'secondary', 'icon' => 'bi-gender-ambiguous']
    ];
    $info = $sexeMap[$sexe] ?? ['class' => 'secondary', 'icon' => 'bi-person'];
    return '<span class="badge bg-' . $info['class'] . '"><i class="bi ' . $info['icon'] . ' me-1"></i>' . htmlspecialchars($sexe) . '</span>';
}

// ================================================================
// 3. HANDLERS AJAX
// ================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    header('Content-Type: application/json; charset=utf-8');
    
    if ($_POST['ajax_action'] === 'load_donnee') {
        $id = (int)$_POST['id'];
        $stmt = $pdo->prepare("SELECT d.*, 
                               p.titre_projet, 
                               di.titre_district, 
                               s.titre_site, 
                               do.titre_domaine, 
                               i.titre_indicateur,
                               t.titre_debut,
                               t.titre_fin,
                               t.age_debut,
                               t.age_fin,
                               u.nom_prenom as saisie_par_nom
                               FROM donnee d 
                               LEFT JOIN projet p ON d.id_projet = p.id_projet 
                               LEFT JOIN district di ON d.id_district = di.id_district 
                               LEFT JOIN site s ON d.id_site = s.id_site 
                               LEFT JOIN domaine do ON d.id_domaine = do.id_domaine 
                               LEFT JOIN indicateur i ON d.id_indicateur = i.id_indicateur 
                               LEFT JOIN tranche_age t ON d.id_tranche = t.id_tranche 
                               LEFT JOIN utilisateurs u ON d.saisi_par = u.utilisateur_id
                               WHERE d.id = ?");
        $stmt->execute([$id]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($data) {
            echo json_encode(['success' => true, 'data' => $data]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Donnée introuvable']);
        }
        exit;
    }
    
    // AJOUTER
    if (isset($_POST['btn_ajouter'])) {
        try {
            $id_projet = trim($_POST['id_projet']);
            $id_district = trim($_POST['id_district']);
            $id_site = trim($_POST['id_site']);
            $id_domaine = trim($_POST['id_domaine']);
            $id_indicateur = trim($_POST['id_indicateur']);
            $id_tranche = trim($_POST['id_tranche']);
            $sexe = trim($_POST['sexe']);
            $valeur = trim($_POST['valeur']);
            $mois = trim($_POST['mois']);
            $annee = trim($_POST['annee']);
            $etat_donnee = trim($_POST['etat_donnee'] ?? 'ACTIF');
            $saisi_par = $_SESSION['utilisateur_id'];
            $date_enregistrement = date('Y-m-d');
            
            if (empty($id_projet) || empty($id_district) || empty($id_site) || empty($id_domaine) || 
                empty($id_indicateur) || empty($id_tranche) || empty($valeur) || empty($mois) || empty($annee)) {
                throw new Exception('Tous les champs obligatoires doivent être remplis.');
            }
            
            $req = $pdo->prepare("INSERT INTO donnee (id_projet, id_district, id_site, id_domaine, id_indicateur, id_tranche, sexe, valeur, mois, annee, date_enregistrement, saisi_par, etat_donnee) 
                                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $req->execute([$id_projet, $id_district, $id_site, $id_domaine, $id_indicateur, $id_tranche, $sexe, $valeur, $mois, $annee, $date_enregistrement, $saisi_par, $etat_donnee]);
            
            echo json_encode(['success' => true, 'message' => 'Donnée créée avec succès']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }
    
    // MODIFIER
    if (isset($_POST['btn_modifier'])) {
        try {
            $id = (int)$_POST['id'];
            $id_projet = trim($_POST['id_projet']);
            $id_district = trim($_POST['id_district']);
            $id_site = trim($_POST['id_site']);
            $id_domaine = trim($_POST['id_domaine']);
            $id_indicateur = trim($_POST['id_indicateur']);
            $id_tranche = trim($_POST['id_tranche']);
            $sexe = trim($_POST['sexe']);
            $valeur = trim($_POST['valeur']);
            $mois = trim($_POST['mois']);
            $annee = trim($_POST['annee']);
            $etat_donnee = trim($_POST['etat_donnee'] ?? 'ACTIF');
            
            if (empty($id) || empty($id_projet) || empty($id_district) || empty($id_site) || empty($id_domaine) || 
                empty($id_indicateur) || empty($id_tranche) || empty($valeur) || empty($mois) || empty($annee)) {
                throw new Exception('Tous les champs obligatoires doivent être remplis.');
            }
            
            $req = $pdo->prepare("UPDATE donnee SET 
                id_projet = ?, id_district = ?, id_site = ?, id_domaine = ?, id_indicateur = ?, 
                id_tranche = ?, sexe = ?, valeur = ?, mois = ?, annee = ?, etat_donnee = ? 
                WHERE id = ?");
            $req->execute([$id_projet, $id_district, $id_site, $id_domaine, $id_indicateur, 
                          $id_tranche, $sexe, $valeur, $mois, $annee, $etat_donnee, $id]);
            
            echo json_encode(['success' => true, 'message' => 'Donnée modifiée avec succès']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }
    
    // SUPPRIMER
    if (isset($_POST['btn_supprimer'])) {
        try {
            $id = (int)$_POST['id'];
            $req = $pdo->prepare("DELETE FROM donnee WHERE id = ?");
            $req->execute([$id]);
            echo json_encode(['success' => true, 'message' => 'Donnée supprimée avec succès']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }
}

// ================================================================
// 4. GESTION DES FILTRES ET PAGINATION
// ================================================================
$limit = 15;
$page = max(1, (int)($_POST['page'] ?? $_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;
$search = trim($_POST['search'] ?? $_GET['search'] ?? '');
$filter_projet = trim($_POST['projet'] ?? $_GET['projet'] ?? '');
$filter_district = trim($_POST['district'] ?? $_GET['district'] ?? '');
$filter_site = trim($_POST['site'] ?? $_GET['site'] ?? '');
$filter_domaine = trim($_POST['domaine'] ?? $_GET['domaine'] ?? '');
$filter_indicateur = trim($_POST['indicateur'] ?? $_GET['indicateur'] ?? '');
$filter_annee = trim($_POST['annee'] ?? $_GET['annee'] ?? '');
$filter_sexe = trim($_POST['sexe'] ?? $_GET['sexe'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['apply_filters'])) {
    $_SESSION['donnee_filters'] = [
        'search' => $search,
        'projet' => $filter_projet,
        'district' => $filter_district,
        'site' => $filter_site,
        'domaine' => $filter_domaine,
        'indicateur' => $filter_indicateur,
        'annee' => $filter_annee,
        'sexe' => $filter_sexe
    ];
} elseif (isset($_SESSION['donnee_filters']) && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $search = $_SESSION['donnee_filters']['search'] ?? '';
    $filter_projet = $_SESSION['donnee_filters']['projet'] ?? '';
    $filter_district = $_SESSION['donnee_filters']['district'] ?? '';
    $filter_site = $_SESSION['donnee_filters']['site'] ?? '';
    $filter_domaine = $_SESSION['donnee_filters']['domaine'] ?? '';
    $filter_indicateur = $_SESSION['donnee_filters']['indicateur'] ?? '';
    $filter_annee = $_SESSION['donnee_filters']['annee'] ?? '';
    $filter_sexe = $_SESSION['donnee_filters']['sexe'] ?? '';
    $page = $_SESSION['donnee_filters']['page'] ?? 1;
    $offset = ($page - 1) * $limit;
}

// LISTES POUR LES FILTRES ET FORMULAIRES
$projets = $pdo->query("SELECT id_projet, titre_projet FROM projet WHERE etat_projet = 'actif' ORDER BY titre_projet")->fetchAll(PDO::FETCH_ASSOC);
$districts = $pdo->query("SELECT id_district, titre_district FROM district ORDER BY titre_district")->fetchAll(PDO::FETCH_ASSOC);
$sites = $pdo->query("SELECT id_site, titre_site FROM site ORDER BY titre_site")->fetchAll(PDO::FETCH_ASSOC);
$domaines = $pdo->query("SELECT id_domaine, titre_domaine FROM domaine ORDER BY titre_domaine")->fetchAll(PDO::FETCH_ASSOC);
$indicateurs = $pdo->query("SELECT id_indicateur, titre_indicateur FROM indicateur ORDER BY titre_indicateur")->fetchAll(PDO::FETCH_ASSOC);
$tranches = $pdo->query("SELECT id_tranche, titre_debut, titre_fin, age_debut, age_fin FROM tranche_age ORDER BY age_debut")->fetchAll(PDO::FETCH_ASSOC);
$annees = $pdo->query("SELECT DISTINCT annee FROM donnee ORDER BY annee DESC")->fetchAll(PDO::FETCH_COLUMN);
$sexes = ['M', 'F', 'Tous'];
$mois_liste = ['Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'];
$etats = ['ACTIF', 'INACTIF'];

// COMPTER TOTAL
$count_sql = 'SELECT COUNT(*) FROM donnee d 
              LEFT JOIN projet p ON d.id_projet = p.id_projet 
              LEFT JOIN district di ON d.id_district = di.id_district 
              LEFT JOIN site s ON d.id_site = s.id_site 
              LEFT JOIN domaine do ON d.id_domaine = do.id_domaine 
              LEFT JOIN indicateur i ON d.id_indicateur = i.id_indicateur 
              LEFT JOIN tranche_age t ON d.id_tranche = t.id_tranche 
              LEFT JOIN utilisateurs u ON d.saisi_par = u.utilisateur_id';
$count_params = [];
$where_clauses = [];

if (!empty($search)) {
    $where_clauses[] = '(p.titre_projet LIKE ? OR di.titre_district LIKE ? OR s.titre_site LIKE ? OR do.titre_domaine LIKE ? OR i.titre_indicateur LIKE ? OR d.valeur LIKE ?)';
    $search_term = "%$search%";
    $count_params = array_merge($count_params, [$search_term, $search_term, $search_term, $search_term, $search_term, $search_term]);
}
if (!empty($filter_projet)) {
    $where_clauses[] = 'd.id_projet = ?';
    $count_params[] = $filter_projet;
}
if (!empty($filter_district)) {
    $where_clauses[] = 'd.id_district = ?';
    $count_params[] = $filter_district;
}
if (!empty($filter_site)) {
    $where_clauses[] = 'd.id_site = ?';
    $count_params[] = $filter_site;
}
if (!empty($filter_domaine)) {
    $where_clauses[] = 'd.id_domaine = ?';
    $count_params[] = $filter_domaine;
}
if (!empty($filter_indicateur)) {
    $where_clauses[] = 'd.id_indicateur = ?';
    $count_params[] = $filter_indicateur;
}
if (!empty($filter_annee)) {
    $where_clauses[] = 'd.annee = ?';
    $count_params[] = $filter_annee;
}
if (!empty($filter_sexe)) {
    $where_clauses[] = 'd.sexe = ?';
    $count_params[] = $filter_sexe;
}
if (!empty($where_clauses)) {
    $count_sql .= ' WHERE ' . implode(' AND ', $where_clauses);
}

$stmt = $pdo->prepare($count_sql);
$stmt->execute($count_params);
$total_donnees = $stmt->fetchColumn();
$total_pages = max(1, ceil($total_donnees / $limit));

// RÉCUPÉRER LES DONNÉES
$sql = 'SELECT 
            d.id,
            d.valeur,
            d.sexe,
            d.mois,
            d.annee,
            d.date_enregistrement,
            d.etat_donnee,
            p.titre_projet,
            di.titre_district,
            s.titre_site,
            do.titre_domaine,
            i.titre_indicateur,
            t.titre_debut,
            t.titre_fin,
            t.age_debut,
            t.age_fin,
            u.nom_prenom,
            u.utilisateur_id
        FROM donnee d 
        LEFT JOIN projet p ON d.id_projet = p.id_projet 
        LEFT JOIN district di ON d.id_district = di.id_district 
        LEFT JOIN site s ON d.id_site = s.id_site 
        LEFT JOIN domaine do ON d.id_domaine = do.id_domaine 
        LEFT JOIN indicateur i ON d.id_indicateur = i.id_indicateur 
        LEFT JOIN tranche_age t ON d.id_tranche = t.id_tranche 
        LEFT JOIN utilisateurs u ON d.saisi_par = u.utilisateur_id';
$params = [];
$where_clauses = [];

if (!empty($search)) {
    $where_clauses[] = '(p.titre_projet LIKE ? OR di.titre_district LIKE ? OR s.titre_site LIKE ? OR do.titre_domaine LIKE ? OR i.titre_indicateur LIKE ? OR d.valeur LIKE ?)';
    $search_term = "%$search%";
    $params = array_merge($params, [$search_term, $search_term, $search_term, $search_term, $search_term, $search_term]);
}
if (!empty($filter_projet)) {
    $where_clauses[] = 'd.id_projet = ?';
    $params[] = $filter_projet;
}
if (!empty($filter_district)) {
    $where_clauses[] = 'd.id_district = ?';
    $params[] = $filter_district;
}
if (!empty($filter_site)) {
    $where_clauses[] = 'd.id_site = ?';
    $params[] = $filter_site;
}
if (!empty($filter_domaine)) {
    $where_clauses[] = 'd.id_domaine = ?';
    $params[] = $filter_domaine;
}
if (!empty($filter_indicateur)) {
    $where_clauses[] = 'd.id_indicateur = ?';
    $params[] = $filter_indicateur;
}
if (!empty($filter_annee)) {
    $where_clauses[] = 'd.annee = ?';
    $params[] = $filter_annee;
}
if (!empty($filter_sexe)) {
    $where_clauses[] = 'd.sexe = ?';
    $params[] = $filter_sexe;
}
if (!empty($where_clauses)) {
    $sql .= ' WHERE ' . implode(' AND ', $where_clauses);
}
$sql .= ' ORDER BY d.id DESC LIMIT ' . (int)$limit . ' OFFSET ' . (int)$offset;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$donnees = $stmt->fetchAll(PDO::FETCH_ASSOC);

// STATISTIQUES
$stmt = $pdo->query("SELECT COUNT(*) FROM donnee");
$total = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM donnee WHERE etat_donnee = 'ACTIF'");
$actives = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM donnee WHERE etat_donnee = 'INACTIF'");
$inactives = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(DISTINCT id_projet) FROM donnee");
$projets_avec_donnees = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(DISTINCT id_indicateur) FROM donnee");
$indicateurs_utilises = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COALESCE(SUM(CAST(valeur AS DECIMAL(10,2))), 0) FROM donnee");
$total_valeur = $stmt->fetchColumn();

// Filtres actifs
$active_filters = [];
if (!empty($search)) $active_filters[] = ['label' => 'Recherche', 'value' => $search];
if (!empty($filter_projet)) {
    $label = '';
    foreach ($projets as $p) {
        if ($p['id_projet'] == $filter_projet) { $label = $p['titre_projet']; break; }
    }
    $active_filters[] = ['label' => 'Projet', 'value' => $label ?: $filter_projet];
}
if (!empty($filter_district)) {
    $label = '';
    foreach ($districts as $d) {
        if ($d['id_district'] == $filter_district) { $label = $d['titre_district']; break; }
    }
    $active_filters[] = ['label' => 'District', 'value' => $label ?: $filter_district];
}
if (!empty($filter_site)) {
    $label = '';
    foreach ($sites as $s) {
        if ($s['id_site'] == $filter_site) { $label = $s['titre_site']; break; }
    }
    $active_filters[] = ['label' => 'Site', 'value' => $label ?: $filter_site];
}
if (!empty($filter_domaine)) {
    $label = '';
    foreach ($domaines as $d) {
        if ($d['id_domaine'] == $filter_domaine) { $label = $d['titre_domaine']; break; }
    }
    $active_filters[] = ['label' => 'Domaine', 'value' => $label ?: $filter_domaine];
}
if (!empty($filter_indicateur)) {
    $label = '';
    foreach ($indicateurs as $i) {
        if ($i['id_indicateur'] == $filter_indicateur) { $label = $i['titre_indicateur']; break; }
    }
    $active_filters[] = ['label' => 'Indicateur', 'value' => $label ?: $filter_indicateur];
}
if (!empty($filter_annee)) $active_filters[] = ['label' => 'Année', 'value' => $filter_annee];
if (!empty($filter_sexe)) $active_filters[] = ['label' => 'Sexe', 'value' => $filter_sexe];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Données - Epencia SGI</title>
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
            <h1>Gestion des <span class="highlight">données</span></h1>
            <div class="subtitle">
                <i class="bi bi-database"></i>
                Consulter, filtrer et gérer les données saisies
                <span class="dot"></span>
                <i class="bi bi-person-circle"></i> <?php echo htmlspecialchars($user_nom); ?>
                <span class="dot"></span>
                <?php echo getRoleBadge($user_role); ?>
            </div>
        </div>
        <div class="header-actions">
            <button class="btn-medical-primary" data-bs-toggle="modal" data-bs-target="#addModal">
                <i class="bi bi-plus-circle"></i> <span>Nouvelle donnée</span>
            </button>
        </div>
    </header>

    <!-- ============================================================ -->
    <!-- MESSAGES                                                     -->
    <!-- ============================================================ -->
    <div id="alertContainer"></div>

    <!-- ============================================================ -->
    <!-- STATISTIQUES                                                 -->
    <!-- ============================================================ -->
    <div class="stats-grid">
        <div class="stat-card fade-in fade-in-d1">
            <div class="stat-icon info"><i class="bi bi-database"></i></div>
            <div class="stat-content">
                <div class="stat-value"><?= number_format($total) ?></div>
                <div class="stat-label">Total données</div>
            </div>
        </div>
        <div class="stat-card fade-in fade-in-d2">
            <div class="stat-icon teal"><i class="bi bi-check-circle"></i></div>
            <div class="stat-content">
                <div class="stat-value"><?= number_format($actives) ?></div>
                <div class="stat-label">Données actives</div>
            </div>
        </div>
        <div class="stat-card fade-in fade-in-d3">
            <div class="stat-icon warning"><i class="bi bi-x-circle"></i></div>
            <div class="stat-content">
                <div class="stat-value"><?= number_format($inactives) ?></div>
                <div class="stat-label">Données inactives</div>
            </div>
        </div>
        <div class="stat-card fade-in fade-in-d4">
            <div class="stat-icon blue"><i class="bi bi-cash-stack"></i></div>
            <div class="stat-content">
                <div class="stat-value"><?= number_format((float)$total_valeur, 0, ',', ' ') ?></div>
                <div class="stat-label">Valeur totale</div>
                <div class="stat-sub"><?= number_format($projets_avec_donnees) ?> projets • <?= number_format($indicateurs_utilises) ?> indicateurs</div>
            </div>
        </div>
    </div>

    <!-- ============================================================ -->
    <!-- FILTRES                                                      -->
    <!-- ============================================================ -->
    <form method="POST" action="" id="filterForm" class="filter-section fade-in">
        <div class="filter-title"><i class="bi bi-funnel"></i> Filtres</div>
        <div class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label">Recherche</label>
                <input type="text" class="form-control" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Projet, district, site...">
            </div>
            <div class="col-md-2">
                <label class="form-label">Projet</label>
                <select name="projet" id="filterProjet" class="form-select">
                    <option value="">Tous</option>
                    <?php foreach ($projets as $p): ?>
                        <option value="<?= $p['id_projet'] ?>" <?= $filter_projet == $p['id_projet'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($p['titre_projet']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">District</label>
                <select name="district" id="filterDistrict" class="form-select">
                    <option value="">Tous</option>
                    <?php foreach ($districts as $d): ?>
                        <option value="<?= $d['id_district'] ?>" <?= $filter_district == $d['id_district'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($d['titre_district']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Indicateur</label>
                <select name="indicateur" id="filterIndicateur" class="form-select">
                    <option value="">Tous</option>
                    <?php foreach ($indicateurs as $i): ?>
                        <option value="<?= $i['id_indicateur'] ?>" <?= $filter_indicateur == $i['id_indicateur'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($i['titre_indicateur']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Année</label>
                <select name="annee" id="filterAnnee" class="form-select">
                    <option value="">Toutes</option>
                    <?php foreach ($annees as $a): ?>
                        <option value="<?= $a ?>" <?= $filter_annee == $a ? 'selected' : '' ?>><?= $a ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-1">
                <label class="form-label">Sexe</label>
                <select name="sexe" id="filterSexe" class="form-select">
                    <option value="">Tous</option>
                    <?php foreach ($sexes as $s): ?>
                        <option value="<?= $s ?>" <?= $filter_sexe == $s ? 'selected' : '' ?>><?= $s ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 mt-3">
                <div class="d-flex gap-2 flex-wrap">
                    <button type="submit" name="apply_filters" class="btn-medical-primary btn-sm-medical">
                        <i class="bi bi-search"></i> Filtrer
                    </button>
                    <button type="submit" name="reset_filters" class="btn-medical-outline btn-sm-medical">
                        <i class="bi bi-arrow-repeat"></i> Réinitialiser
                    </button>
                </div>
                <?php if (!empty($active_filters)): ?>
                    <div class="filter-pills">
                        <span class="text-muted me-2" style="font-size:12px;"><i class="bi bi-funnel-fill"></i> Filtres actifs :</span>
                        <?php foreach ($active_filters as $f): ?>
                            <span class="filter-pill"><strong><?= $f['label'] ?>:</strong> <?= htmlspecialchars($f['value']) ?></span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </form>

    <!-- ============================================================ -->
    <!-- LISTE DES DONNÉES                                           -->
    <!-- ============================================================ -->
    <div class="card-modern fade-in">
        <div class="card-header">
            <h5>
                <span class="header-icon blue"><i class="bi bi-list-ul"></i></span>
                Liste des données
                <span class="badge bg-secondary ms-2"><?= number_format($total_donnees) ?></span>
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
                            <th style="width:50px;">ID</th>
                            <th>Projet</th>
                            <th>District</th>
                            <th>Site</th>
                            <th>Indicateur</th>
                            <th>Tranche</th>
                            <th style="width:70px;">Sexe</th>
                            <th style="width:80px;">Valeur</th>
                            <th style="width:100px;">Période</th>
                            <th style="width:100px;">Saisi par</th>
                            <th style="width:80px;">État</th>
                            <th class="text-center" style="width:90px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($donnees)): ?>
                            <tr>
                                <td colspan="12" class="text-center py-5" style="color:var(--medical-text-muted);">
                                    <i class="bi bi-inbox" style="font-size:3rem;display:block;margin-bottom:12px;opacity:0.3;"></i>
                                    <?php if ($total == 0): ?>
                                        <strong>Aucune donnée enregistrée.</strong>
                                        <br>
                                        <span style="font-size:13px;">Cliquez sur "Nouvelle donnée" pour en créer une.</span>
                                    <?php else: ?>
                                        <strong>Aucune donnée trouvée avec ces filtres.</strong>
                                        <br>
                                        <span style="font-size:13px;">Essayez de modifier vos filtres de recherche.</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php else: foreach ($donnees as $d): ?>
                            <tr>
                                <td>
                                    <span class="fw-bold" style="color:var(--medical-text-secondary);">#<?= $d['id'] ?></span>
                                </td>
                                <td>
                                    <span class="ellipsis" title="<?= htmlspecialchars($d['titre_projet'] ?? '') ?>" style="display:block;max-width:120px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                                        <?= htmlspecialchars($d['titre_projet'] ?? '-') ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="ellipsis" title="<?= htmlspecialchars($d['titre_district'] ?? '') ?>" style="display:block;max-width:120px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                                        <?= htmlspecialchars($d['titre_district'] ?? '-') ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="ellipsis" title="<?= htmlspecialchars($d['titre_site'] ?? '') ?>" style="display:block;max-width:120px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                                        <?= htmlspecialchars($d['titre_site'] ?? '-') ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="ellipsis" title="<?= htmlspecialchars($d['titre_indicateur'] ?? '') ?>" style="display:block;max-width:120px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                                        <?= htmlspecialchars($d['titre_indicateur'] ?? '-') ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($d['titre_debut'] ?? '') ?>-<?= htmlspecialchars($d['titre_fin'] ?? '') ?></td>
                                <td><?= getSexeBadge($d['sexe'] ?? 'Tous') ?></td>
                                <td><strong style="color:var(--medical-teal);"><?= number_format((float)($d['valeur'] ?? 0), 0, ',', ' ') ?></strong></td>
                                <td><?= htmlspecialchars($d['mois'] ?? '') ?> <?= $d['annee'] ?? '' ?></td>
                                <td>
                                    <span class="ellipsis" title="<?= htmlspecialchars($d['nom_prenom'] ?? '') ?>" style="display:block;max-width:100px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                                        <?= htmlspecialchars($d['nom_prenom'] ?? '-') ?>
                                    </span>
                                </td>
                                <td><?= getStatusBadge($d['etat_donnee'] ?? 'INACTIF') ?></td>
                                <td>
                                    <div class="actions-group">
                                        <button type="button" class="btn-action edit" data-bs-toggle="modal" data-bs-target="#editModal" 
                                            data-id="<?= $d['id'] ?>" title="Modifier">
                                            <i class="bi bi-pencil-square"></i>
                                        </button>
                                        <button type="button" class="btn-action delete" data-bs-toggle="modal" data-bs-target="#deleteModal" 
                                            data-id="<?= $d['id'] ?>" 
                                            data-info="#<?= $d['id'] ?> - <?= htmlspecialchars($d['titre_projet'] ?? 'N/A') ?>" 
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
                            <a class="page-link" href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&projet=<?= urlencode($filter_projet) ?>&district=<?= urlencode($filter_district) ?>&site=<?= urlencode($filter_site) ?>&domaine=<?= urlencode($filter_domaine) ?>&indicateur=<?= urlencode($filter_indicateur) ?>&annee=<?= urlencode($filter_annee) ?>&sexe=<?= urlencode($filter_sexe) ?>">
                                <i class="bi bi-chevron-left"></i>
                            </a>
                        </li>
                        <?php 
                        $start = max(1, $page - 2);
                        $end = min($total_pages, $page + 2);
                        if ($start > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=1&search=<?= urlencode($search) ?>&projet=<?= urlencode($filter_projet) ?>&district=<?= urlencode($filter_district) ?>&site=<?= urlencode($filter_site) ?>&domaine=<?= urlencode($filter_domaine) ?>&indicateur=<?= urlencode($filter_indicateur) ?>&annee=<?= urlencode($filter_annee) ?>&sexe=<?= urlencode($filter_sexe) ?>">1</a>
                            </li>
                            <?php if ($start > 2): ?>
                                <li class="page-item disabled"><span class="page-link">…</span></li>
                            <?php endif; ?>
                        <?php endif; ?>
                        <?php for ($i = $start; $i <= $end; $i++): ?>
                            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&projet=<?= urlencode($filter_projet) ?>&district=<?= urlencode($filter_district) ?>&site=<?= urlencode($filter_site) ?>&domaine=<?= urlencode($filter_domaine) ?>&indicateur=<?= urlencode($filter_indicateur) ?>&annee=<?= urlencode($filter_annee) ?>&sexe=<?= urlencode($filter_sexe) ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                        <?php if ($end < $total_pages): ?>
                            <?php if ($end < $total_pages - 1): ?>
                                <li class="page-item disabled"><span class="page-link">…</span></li>
                            <?php endif; ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?= $total_pages ?>&search=<?= urlencode($search) ?>&projet=<?= urlencode($filter_projet) ?>&district=<?= urlencode($filter_district) ?>&site=<?= urlencode($filter_site) ?>&domaine=<?= urlencode($filter_domaine) ?>&indicateur=<?= urlencode($filter_indicateur) ?>&annee=<?= urlencode($filter_annee) ?>&sexe=<?= urlencode($filter_sexe) ?>"><?= $total_pages ?></a>
                            </li>
                        <?php endif; ?>
                        <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&projet=<?= urlencode($filter_projet) ?>&district=<?= urlencode($filter_district) ?>&site=<?= urlencode($filter_site) ?>&domaine=<?= urlencode($filter_domaine) ?>&indicateur=<?= urlencode($filter_indicateur) ?>&annee=<?= urlencode($filter_annee) ?>&sexe=<?= urlencode($filter_sexe) ?>">
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
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header modal-header-medical success">
                <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>Nouvelle donnée</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="addForm">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="section-title"><i class="bi bi-folder"></i> Projet</div>
                            <select name="id_projet" id="addProjet" class="form-select" required style="width:100%;">
                                <option value="">-- Sélectionnez un projet --</option>
                                <?php foreach ($projets as $p): ?>
                                    <option value="<?= $p['id_projet'] ?>"><?= htmlspecialchars($p['titre_projet']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <div class="section-title"><i class="bi bi-geo-alt"></i> District</div>
                            <select name="id_district" id="addDistrict" class="form-select" required style="width:100%;">
                                <option value="">-- Sélectionnez un district --</option>
                                <?php foreach ($districts as $d): ?>
                                    <option value="<?= $d['id_district'] ?>"><?= htmlspecialchars($d['titre_district']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <div class="section-title"><i class="bi bi-pin-map"></i> Site</div>
                            <select name="id_site" id="addSite" class="form-select" required style="width:100%;">
                                <option value="">-- Sélectionnez un site --</option>
                                <?php foreach ($sites as $s): ?>
                                    <option value="<?= $s['id_site'] ?>"><?= htmlspecialchars($s['titre_site']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <div class="section-title"><i class="bi bi-tag"></i> Domaine</div>
                            <select name="id_domaine" id="addDomaine" class="form-select" required style="width:100%;">
                                <option value="">-- Sélectionnez un domaine --</option>
                                <?php foreach ($domaines as $d): ?>
                                    <option value="<?= $d['id_domaine'] ?>"><?= htmlspecialchars($d['titre_domaine']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <div class="section-title"><i class="bi bi-bar-chart"></i> Indicateur</div>
                            <select name="id_indicateur" id="addIndicateur" class="form-select" required style="width:100%;">
                                <option value="">-- Sélectionnez un indicateur --</option>
                                <?php foreach ($indicateurs as $i): ?>
                                    <option value="<?= $i['id_indicateur'] ?>"><?= htmlspecialchars($i['titre_indicateur']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <div class="section-title"><i class="bi bi-person"></i> Tranche d'âge</div>
                            <select name="id_tranche" id="addTranche" class="form-select" required style="width:100%;">
                                <option value="">-- Sélectionnez une tranche --</option>
                                <?php foreach ($tranches as $t): ?>
                                    <option value="<?= $t['id_tranche'] ?>">
                                        <?= htmlspecialchars($t['titre_debut'] . ' - ' . $t['titre_fin'] . ' (' . $t['age_debut'] . '-' . $t['age_fin'] . ' ans)') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <div class="section-title"><i class="bi bi-gender-ambiguous"></i> Sexe</div>
                            <select name="sexe" id="addSexe" class="form-select" required>
                                <option value="Tous">Tous</option>
                                <option value="M">Masculin</option>
                                <option value="F">Féminin</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <div class="section-title"><i class="bi bi-number"></i> Valeur</div>
                            <input type="number" step="0.01" name="valeur" class="form-control" required placeholder="Ex: 100.50">
                        </div>
                        <div class="col-md-4">
                            <div class="section-title"><i class="bi bi-calendar-month"></i> Mois</div>
                            <select name="mois" class="form-select" required>
                                <option value="">-- Sélectionnez un mois --</option>
                                <?php foreach ($mois_liste as $m): ?>
                                    <option value="<?= $m ?>"><?= $m ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <div class="section-title"><i class="bi bi-calendar-year"></i> Année</div>
                            <select name="annee" class="form-select" required>
                                <option value="">-- Sélectionnez une année --</option>
                                <?php for ($a = date('Y'); $a >= 2000; $a--): ?>
                                    <option value="<?= $a ?>"><?= $a ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <div class="section-title"><i class="bi bi-toggle-on"></i> État</div>
                            <select name="etat_donnee" class="form-select">
                                <option value="ACTIF">Actif</option>
                                <option value="INACTIF">Inactif</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-medical-outline" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn-medical-secondary" id="addSubmitBtn">
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
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header modal-header-medical">
                <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i>Modifier la donnée</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="editForm">
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
            <form id="deleteForm">
                <div class="modal-body">
                    <input type="hidden" name="id" id="delete_id">
                    
                    <p style="font-weight:500;color:var(--medical-text);">Voulez-vous vraiment supprimer cette donnée ?</p>
                    
                    <div style="background:var(--medical-gray-light);padding:14px 18px;border-radius:var(--medical-radius-sm);border-left:4px solid var(--danger);margin-top:12px;">
                        <div style="display:flex;align-items:center;gap:12px;margin-bottom:6px;">
                            <span style="font-weight:600;color:var(--medical-text);">ID :</span>
                            <code style="font-size:13px;color:var(--medical-blue);background:var(--medical-blue-light);padding:2px 8px;border-radius:4px;" id="delete_info"></code>
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
<!-- SCRIPTS                                                      -->
<!-- ============================================================ -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
$(document).ready(function() {
    // ============================================================
    // INITIALISATION SELECT2
    // ============================================================
    $('#filterProjet').select2({ theme: 'default', width: '100%', placeholder: 'Tous', allowClear: true });
    $('#filterDistrict').select2({ theme: 'default', width: '100%', placeholder: 'Tous', allowClear: true });
    $('#filterIndicateur').select2({ theme: 'default', width: '100%', placeholder: 'Tous', allowClear: true });
    $('#filterAnnee').select2({ theme: 'default', width: '100%', placeholder: 'Toutes', allowClear: true });
    $('#filterSexe').select2({ theme: 'default', width: '100%', placeholder: 'Tous', allowClear: true });
    
    $('#addProjet').select2({ theme: 'default', width: '100%', placeholder: 'Sélectionnez un projet', allowClear: true, dropdownParent: $('#addModal') });
    $('#addDistrict').select2({ theme: 'default', width: '100%', placeholder: 'Sélectionnez un district', allowClear: true, dropdownParent: $('#addModal') });
    $('#addSite').select2({ theme: 'default', width: '100%', placeholder: 'Sélectionnez un site', allowClear: true, dropdownParent: $('#addModal') });
    $('#addDomaine').select2({ theme: 'default', width: '100%', placeholder: 'Sélectionnez un domaine', allowClear: true, dropdownParent: $('#addModal') });
    $('#addIndicateur').select2({ theme: 'default', width: '100%', placeholder: 'Sélectionnez un indicateur', allowClear: true, dropdownParent: $('#addModal') });
    $('#addTranche').select2({ theme: 'default', width: '100%', placeholder: 'Sélectionnez une tranche', allowClear: true, dropdownParent: $('#addModal') });

    // ============================================================
    // AJOUTER
    // ============================================================
    $('#addForm').on('submit', function(e) {
        e.preventDefault();
        const btn = $('#addSubmitBtn');
        btn.html('<span class="spinner-border spinner-border-sm me-2"></span>Envoi...').prop('disabled', true);
        
        $.post(window.location.href, $(this).serialize() + '&btn_ajouter=1', function(data) {
            btn.html('<i class="bi bi-check-circle me-1"></i>Ajouter').prop('disabled', false);
            if (data.success) {
                showAlert('success', data.message);
                $('#addModal').modal('hide');
                setTimeout(() => location.reload(), 1000);
            } else {
                showAlert('danger', data.message);
            }
        }, 'json').fail(function() {
            btn.html('<i class="bi bi-check-circle me-1"></i>Ajouter').prop('disabled', false);
            showAlert('danger', 'Erreur de connexion');
        });
    });

    // ============================================================
    // CHARGEMENT POUR MODIFICATION
    // ============================================================
    $('#editModal').on('show.bs.modal', function(e) {
        const button = $(e.relatedTarget);
        const id = button.data('id');
        const body = $(this).find('#editModalBody');
        const submitBtn = $(this).find('#editSubmitBtn');
        
        body.html(`
            <div class="text-center py-4">
                <div class="loading-spinner"></div>
                <p class="mt-2 text-muted">Chargement...</p>
            </div>
        `);
        submitBtn.hide();
        
        $.post(window.location.href, { ajax_action: 'load_donnee', id: id }, function(data) {
            if (data.success) {
                const d = data.data;
                body.html(`
                    <input type="hidden" name="id" value="${d.id}">
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="section-title"><i class="bi bi-folder"></i> Projet</div>
                            <select name="id_projet" id="editProjet" class="form-select" required style="width:100%;">
                                <option value="">-- Sélectionnez un projet --</option>
                                <?php foreach ($projets as $p): ?>
                                    <option value="<?= $p['id_projet'] ?>" ${d.id_projet == '<?= $p['id_projet'] ?>' ? 'selected' : ''}>
                                        <?= htmlspecialchars($p['titre_projet']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <div class="section-title"><i class="bi bi-geo-alt"></i> District</div>
                            <select name="id_district" id="editDistrict" class="form-select" required style="width:100%;">
                                <option value="">-- Sélectionnez un district --</option>
                                <?php foreach ($districts as $d): ?>
                                    <option value="<?= $d['id_district'] ?>" ${d.id_district == '<?= $d['id_district'] ?>' ? 'selected' : ''}>
                                        <?= htmlspecialchars($d['titre_district']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <div class="section-title"><i class="bi bi-pin-map"></i> Site</div>
                            <select name="id_site" id="editSite" class="form-select" required style="width:100%;">
                                <option value="">-- Sélectionnez un site --</option>
                                <?php foreach ($sites as $s): ?>
                                    <option value="<?= $s['id_site'] ?>" ${d.id_site == '<?= $s['id_site'] ?>' ? 'selected' : ''}>
                                        <?= htmlspecialchars($s['titre_site']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <div class="section-title"><i class="bi bi-tag"></i> Domaine</div>
                            <select name="id_domaine" id="editDomaine" class="form-select" required style="width:100%;">
                                <option value="">-- Sélectionnez un domaine --</option>
                                <?php foreach ($domaines as $do): ?>
                                    <option value="<?= $do['id_domaine'] ?>" ${d.id_domaine == '<?= $do['id_domaine'] ?>' ? 'selected' : ''}>
                                        <?= htmlspecialchars($do['titre_domaine']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <div class="section-title"><i class="bi bi-bar-chart"></i> Indicateur</div>
                            <select name="id_indicateur" id="editIndicateur" class="form-select" required style="width:100%;">
                                <option value="">-- Sélectionnez un indicateur --</option>
                                <?php foreach ($indicateurs as $i): ?>
                                    <option value="<?= $i['id_indicateur'] ?>" ${d.id_indicateur == '<?= $i['id_indicateur'] ?>' ? 'selected' : ''}>
                                        <?= htmlspecialchars($i['titre_indicateur']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <div class="section-title"><i class="bi bi-person"></i> Tranche d'âge</div>
                            <select name="id_tranche" id="editTranche" class="form-select" required style="width:100%;">
                                <option value="">-- Sélectionnez une tranche --</option>
                                <?php foreach ($tranches as $t): ?>
                                    <option value="<?= $t['id_tranche'] ?>" ${d.id_tranche == '<?= $t['id_tranche'] ?>' ? 'selected' : ''}>
                                        <?= htmlspecialchars($t['titre_debut'] . ' - ' . $t['titre_fin'] . ' (' . $t['age_debut'] . '-' . $t['age_fin'] . ' ans)') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <div class="section-title"><i class="bi bi-gender-ambiguous"></i> Sexe</div>
                            <select name="sexe" class="form-select" required>
                                <option value="Tous" ${d.sexe == 'Tous' ? 'selected' : ''}>Tous</option>
                                <option value="M" ${d.sexe == 'M' ? 'selected' : ''}>Masculin</option>
                                <option value="F" ${d.sexe == 'F' ? 'selected' : ''}>Féminin</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <div class="section-title"><i class="bi bi-number"></i> Valeur</div>
                            <input type="number" step="0.01" name="valeur" class="form-control" value="${d.valeur || 0}" required>
                        </div>
                        <div class="col-md-4">
                            <div class="section-title"><i class="bi bi-calendar-month"></i> Mois</div>
                            <select name="mois" class="form-select" required>
                                <option value="">-- Sélectionnez un mois --</option>
                                <?php foreach ($mois_liste as $m): ?>
                                    <option value="<?= $m ?>" ${d.mois == '<?= $m ?>' ? 'selected' : ''}>${esc('<?= $m ?>')}</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <div class="section-title"><i class="bi bi-calendar-year"></i> Année</div>
                            <select name="annee" class="form-select" required>
                                <option value="">-- Sélectionnez une année --</option>
                                <?php for ($a = date('Y'); $a >= 2000; $a--): ?>
                                    <option value="<?= $a ?>" ${d.annee == '<?= $a ?>' ? 'selected' : ''}>${esc('<?= $a ?>')}</option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <div class="section-title"><i class="bi bi-toggle-on"></i> État</div>
                            <select name="etat_donnee" class="form-select">
                                <option value="ACTIF" ${d.etat_donnee == 'ACTIF' ? 'selected' : ''}>Actif</option>
                                <option value="INACTIF" ${d.etat_donnee == 'INACTIF' ? 'selected' : ''}>Inactif</option>
                            </select>
                        </div>
                    </div>
                `);
                
                $('#editProjet').select2({ theme: 'default', width: '100%', placeholder: 'Sélectionnez un projet', allowClear: true, dropdownParent: $('#editModal') });
                $('#editDistrict').select2({ theme: 'default', width: '100%', placeholder: 'Sélectionnez un district', allowClear: true, dropdownParent: $('#editModal') });
                $('#editSite').select2({ theme: 'default', width: '100%', placeholder: 'Sélectionnez un site', allowClear: true, dropdownParent: $('#editModal') });
                $('#editDomaine').select2({ theme: 'default', width: '100%', placeholder: 'Sélectionnez un domaine', allowClear: true, dropdownParent: $('#editModal') });
                $('#editIndicateur').select2({ theme: 'default', width: '100%', placeholder: 'Sélectionnez un indicateur', allowClear: true, dropdownParent: $('#editModal') });
                $('#editTranche').select2({ theme: 'default', width: '100%', placeholder: 'Sélectionnez une tranche', allowClear: true, dropdownParent: $('#editModal') });
                
                submitBtn.show();
            } else {
                body.html(`<div class="alert-medical alert-danger">${data.message || 'Erreur lors du chargement.'}</div>`);
            }
        }, 'json');
    });

    // ============================================================
    // MODIFIER
    // ============================================================
    $('#editForm').on('submit', function(e) {
        e.preventDefault();
        const btn = $('#editSubmitBtn');
        btn.html('<span class="spinner-border spinner-border-sm me-2"></span>Envoi...').prop('disabled', true);
        
        $.post(window.location.href, $(this).serialize() + '&btn_modifier=1', function(data) {
            btn.html('<i class="bi bi-check-circle me-1"></i>Enregistrer').prop('disabled', false);
            if (data.success) {
                showAlert('success', data.message);
                $('#editModal').modal('hide');
                setTimeout(() => location.reload(), 1000);
            } else {
                showAlert('danger', data.message);
            }
        }, 'json').fail(function() {
            btn.html('<i class="bi bi-check-circle me-1"></i>Enregistrer').prop('disabled', false);
            showAlert('danger', 'Erreur de connexion');
        });
    });

    // ============================================================
    // SUPPRESSION
    // ============================================================
    $('#deleteModal').on('show.bs.modal', function(e) {
        const button = $(e.relatedTarget);
        const id = button.data('id');
        const info = button.data('info');
        $('#delete_id').val(id);
        $('#delete_info').text(info);
    });

    $('#deleteForm').on('submit', function(e) {
        e.preventDefault();
        const btn = $(this).find('button[type="submit"]');
        btn.html('<span class="spinner-border spinner-border-sm me-2"></span>...').prop('disabled', true);
        
        $.post(window.location.href, $(this).serialize() + '&btn_supprimer=1', function(data) {
            btn.html('<i class="bi bi-trash me-1"></i>Supprimer').prop('disabled', false);
            if (data.success) {
                showAlert('success', data.message);
                $('#deleteModal').modal('hide');
                setTimeout(() => location.reload(), 1000);
            } else {
                showAlert('danger', data.message);
            }
        }, 'json').fail(function() {
            btn.html('<i class="bi bi-trash me-1"></i>Supprimer').prop('disabled', false);
            showAlert('danger', 'Erreur de connexion');
        });
    });

    // ============================================================
    // AFFICHAGE DES ALERTES
    // ============================================================
    function showAlert(type, message) {
        const container = $('#alertContainer');
        const alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
        const icon = type === 'success' ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill';
        container.html(`
            <div class="alert-medical ${alertClass} alert-dismissible fade show">
                <i class="bi ${icon} me-2"></i> ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `);
        setTimeout(() => container.html(''), 5000);
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