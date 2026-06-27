<?php
// ========================================
// GESTION DES UTILISATEURS - EPENCIA SGI
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
$edit_utilisateur = null;

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
$filter_etat = trim($_POST['etat'] ?? '');
$filter_role = trim($_POST['role'] ?? '');
$filter_organisme = trim($_POST['organisme_id'] ?? '');

// Gestion des filtres en session
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['search']) || isset($_POST['etat']) || isset($_POST['role']) || isset($_POST['organisme_id'])) {
        $_SESSION['utilisateurs_filters'] = [
            'search' => $search,
            'etat' => $filter_etat,
            'role' => $filter_role,
            'organisme_id' => $filter_organisme,
            'page' => $page
        ];
    }
} else {
    if (isset($_SESSION['utilisateurs_filters'])) {
        $search = $_SESSION['utilisateurs_filters']['search'] ?? '';
        $filter_etat = $_SESSION['utilisateurs_filters']['etat'] ?? '';
        $filter_role = $_SESSION['utilisateurs_filters']['role'] ?? '';
        $filter_organisme = $_SESSION['utilisateurs_filters']['organisme_id'] ?? '';
        $page = $_SESSION['utilisateurs_filters']['page'] ?? 1;
        $offset = ($page - 1) * $limit;
    }
}

// Traitement des actions POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // === Créer un utilisateur ===
        if (isset($_POST['action']) && $_POST['action'] === 'add') {
            $utilisateur_id = trim($_POST['utilisateur_id']);
            $nom_prenom = trim($_POST['nom_prenom']);
            $login = trim($_POST['login']);
            $mdp = trim($_POST['mdp']);
            $telephone = trim($_POST['telephone']);
            $email = trim($_POST['email']);
            $role = $_POST['role'];
            $organisme_id = trim($_POST['organisme_id']);
            $date_saisie = $_POST['date_saisie'] ?? date('Y-m-d');
            $etat = $_POST['etat'] ?? 'actif';

            if (empty($utilisateur_id) || empty($nom_prenom) || empty($login) || empty($mdp) || empty($role)) {
                throw new Exception('Tous les champs obligatoires doivent être remplis.');
            }

            // Vérification ID unique
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM utilisateurs WHERE utilisateur_id = ?');
            $stmt->execute([$utilisateur_id]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception('Cet ID utilisateur existe déjà.');
            }

            // Vérification login unique
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM utilisateurs WHERE login = ?');
            $stmt->execute([$login]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception('Ce login existe déjà.');
            }

            // Vérification email unique
            if (!empty($email)) {
                $stmt = $pdo->prepare('SELECT COUNT(*) FROM utilisateurs WHERE email = ?');
                $stmt->execute([$email]);
                if ($stmt->fetchColumn() > 0) {
                    throw new Exception('Cet email existe déjà.');
                }
            }

            // Hash du mot de passe
            $hashed_password = password_hash($mdp, PASSWORD_DEFAULT);

            $stmt = $pdo->prepare('INSERT INTO utilisateurs (utilisateur_id, nom_prenom, login, mdp, telephone, email, role, organisme_id, date_saisie, etat) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$utilisateur_id, $nom_prenom, $login, $hashed_password, $telephone, $email, $role, $organisme_id, $date_saisie, $etat]);
            $message = 'Utilisateur créé avec succès.';
        }

        // === Modifier un utilisateur ===
        if (isset($_POST['action']) && $_POST['action'] === 'edit') {
            $id = $_POST['id'];
            $utilisateur_id = trim($_POST['utilisateur_id']);
            $nom_prenom = trim($_POST['nom_prenom']);
            $login = trim($_POST['login']);
            $mdp = trim($_POST['mdp']);
            $telephone = trim($_POST['telephone']);
            $email = trim($_POST['email']);
            $role = $_POST['role'];
            $organisme_id = trim($_POST['organisme_id']);
            $etat = $_POST['etat'];

            if (empty($utilisateur_id) || empty($nom_prenom) || empty($login) || empty($role)) {
                throw new Exception('Tous les champs obligatoires doivent être remplis.');
            }

            // Vérification login unique
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM utilisateurs WHERE login = ? AND utilisateur_id != ?');
            $stmt->execute([$login, $id]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception('Ce login existe déjà.');
            }

            // Vérification email unique
            if (!empty($email)) {
                $stmt = $pdo->prepare('SELECT COUNT(*) FROM utilisateurs WHERE email = ? AND utilisateur_id != ?');
                $stmt->execute([$email, $id]);
                if ($stmt->fetchColumn() > 0) {
                    throw new Exception('Cet email existe déjà.');
                }
            }

            if (!empty($mdp)) {
                $hashed_password = password_hash($mdp, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare('UPDATE utilisateurs SET utilisateur_id=?, nom_prenom=?, login=?, mdp=?, telephone=?, email=?, role=?, organisme_id=?, etat=? WHERE utilisateur_id=?');
                $stmt->execute([$utilisateur_id, $nom_prenom, $login, $hashed_password, $telephone, $email, $role, $organisme_id, $etat, $id]);
            } else {
                $stmt = $pdo->prepare('UPDATE utilisateurs SET utilisateur_id=?, nom_prenom=?, login=?, telephone=?, email=?, role=?, organisme_id=?, etat=? WHERE utilisateur_id=?');
                $stmt->execute([$utilisateur_id, $nom_prenom, $login, $telephone, $email, $role, $organisme_id, $etat, $id]);
            }
            $message = 'Utilisateur modifié avec succès.';
        }

        // === Réinitialiser le mot de passe ===
        if (isset($_POST['action']) && $_POST['action'] === 'reset_password') {
            $id = $_POST['id'];
            $new_password = trim($_POST['new_password']);
            
            if (empty($new_password)) {
                throw new Exception('Le nouveau mot de passe ne peut pas être vide.');
            }
            
            if (strlen($new_password) < 6) {
                throw new Exception('Le mot de passe doit contenir au moins 6 caractères.');
            }
            
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('UPDATE utilisateurs SET mdp = ? WHERE utilisateur_id = ?');
            $stmt->execute([$hashed_password, $id]);
            
            $message = 'Mot de passe réinitialisé avec succès pour l\'utilisateur.';
        }

        // === Générer un mot de passe aléatoire ===
        if (isset($_POST['action']) && $_POST['action'] === 'generate_password') {
            $id = $_POST['id'];
            
            // Générer un mot de passe aléatoire de 10 caractères
            $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%';
            $new_password = substr(str_shuffle($chars), 0, 10);
            
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('UPDATE utilisateurs SET mdp = ? WHERE utilisateur_id = ?');
            $stmt->execute([$hashed_password, $id]);
            
            // Récupérer les infos de l'utilisateur pour afficher
            $stmt = $pdo->prepare('SELECT nom_prenom, login, email FROM utilisateurs WHERE utilisateur_id = ?');
            $stmt->execute([$id]);
            $user_info = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Stocker en session pour affichage
            $_SESSION['generated_password'] = [
                'nom_prenom' => $user_info['nom_prenom'],
                'login' => $user_info['login'],
                'email' => $user_info['email'],
                'password' => $new_password
            ];
            
            $message = 'Un nouveau mot de passe a été généré et affiché ci-dessous. Veuillez le communiquer à l\'utilisateur.';
        }

        // === Supprimer un utilisateur ===
        if (isset($_POST['action']) && $_POST['action'] === 'delete') {
            $id = $_POST['id'];
            
            // Vérifier si l'utilisateur a des factures
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM factures WHERE utilisateur_id = ?');
            $stmt->execute([$id]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception('Impossible de supprimer un utilisateur lié à des factures.');
            }

            // Vérifier si l'utilisateur a des commandes
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM commandes WHERE utilisateur_id = ?');
            $stmt->execute([$id]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception('Impossible de supprimer un utilisateur lié à des commandes.');
            }

            $stmt = $pdo->prepare('DELETE FROM utilisateurs WHERE utilisateur_id = ?');
            $stmt->execute([$id]);
            $message = 'Utilisateur supprimé avec succès.';
        }

        // === Charger pour modification ===
        if (isset($_POST['edit_utilisateur_id'])) {
            $stmt = $pdo->prepare('SELECT u.*, o.nom as organisme_nom FROM utilisateurs u LEFT JOIN organismes o ON u.organisme_id = o.organisme_id WHERE u.utilisateur_id = ?');
            $stmt->execute([$_POST['edit_utilisateur_id']]);
            $edit_utilisateur = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        // === Réinitialiser les filtres ===
        if (isset($_POST['reset_filters'])) {
            unset($_SESSION['utilisateurs_filters']);
            $search = '';
            $filter_etat = '';
            $filter_role = '';
            $filter_organisme = '';
            $page = 1;
            $offset = 0;
        }

        // === Export Excel ===
        if (isset($_POST['export_excel'])) {
            header('Content-Type: application/vnd.ms-excel');
            header('Content-Disposition: attachment; filename="utilisateurs_' . date('Ymd_His') . '.xls"');
            
            $sql_export = 'SELECT u.utilisateur_id, u.nom_prenom, u.login, u.telephone, u.email, u.role, u.date_saisie, u.etat, o.nom as organisme_nom FROM utilisateurs u LEFT JOIN organismes o ON u.organisme_id = o.organisme_id';
            $where_clauses_exp = [];
            $params_exp = [];
            
            if (!empty($search)) { $where_clauses_exp[] = '(u.utilisateur_id LIKE ? OR u.nom_prenom LIKE ? OR u.login LIKE ? OR u.email LIKE ?)'; $t="%$search%"; $params_exp=array_merge($params_exp,[$t,$t,$t,$t]); }
            if (!empty($filter_etat)) { $where_clauses_exp[] = "u.etat = ?"; $params_exp[] = $filter_etat; }
            if (!empty($filter_role)) { $where_clauses_exp[] = "u.role = ?"; $params_exp[] = $filter_role; }
            if (!empty($filter_organisme)) { $where_clauses_exp[] = "u.organisme_id = ?"; $params_exp[] = $filter_organisme; }
            
            if (!empty($where_clauses_exp)) $sql_export .= ' WHERE ' . implode(' AND ', $where_clauses_exp);
            $sql_export .= ' ORDER BY u.nom_prenom ASC';
            
            $stmt = $pdo->prepare($sql_export);
            $stmt->execute($params_exp);
            $export_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo '<table border="1"><tr><th>ID</th><th>Nom & Prénom</th><th>Login</th><th>Téléphone</th><th>Email</th><th>Rôle</th><th>Organisme</th><th>Date saisie</th><th>État</th></tr>';
            foreach ($export_data as $row) {
                echo '<tr><td>'.htmlspecialchars($row['utilisateur_id']).'</td><td>'.htmlspecialchars($row['nom_prenom']??'-').'</td><td>'.htmlspecialchars($row['login']??'-').'</td><td>'.htmlspecialchars($row['telephone']??'-').'</td><td>'.htmlspecialchars($row['email']??'-').'</td><td>'.htmlspecialchars($row['role']??'-').'</td><td>'.htmlspecialchars($row['organisme_nom']??'-').'</td><td>'.date('d/m/Y', strtotime($row['date_saisie'])).'</td><td>'.ucfirst($row['etat']).'</td></tr>';
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

// Liste des organismes
$stmt = $pdo->prepare('SELECT organisme_id, nom, type FROM organismes WHERE statut = "actif" ORDER BY nom');
$stmt->execute();
$liste_organismes = $stmt->fetchAll(PDO::FETCH_ASSOC);

$roles_utilisateur = ['Superviseur', 'Administrateur', 'Pharmacien', 'Medecin'];
$etats_utilisateur = ['actif', 'inactif', 'en attente'];

// Construction de la requête de comptage
$count_sql = 'SELECT COUNT(*) FROM utilisateurs u LEFT JOIN organismes o ON u.organisme_id = o.organisme_id';
$count_params = [];
$where_clauses = [];

if (!empty($search)) { $where_clauses[] = '(u.utilisateur_id LIKE ? OR u.nom_prenom LIKE ? OR u.login LIKE ? OR u.email LIKE ?)'; $t="%$search%"; $count_params=array_merge($count_params,[$t,$t,$t,$t]); }
if (!empty($filter_etat)) { $where_clauses[] = 'u.etat = ?'; $count_params[] = $filter_etat; }
if (!empty($filter_role)) { $where_clauses[] = 'u.role = ?'; $count_params[] = $filter_role; }
if (!empty($filter_organisme)) { $where_clauses[] = 'u.organisme_id = ?'; $count_params[] = $filter_organisme; }

if (!empty($where_clauses)) $count_sql .= ' WHERE ' . implode(' AND ', $where_clauses);
$stmt = $pdo->prepare($count_sql);
$stmt->execute($count_params);
$total_utilisateurs = $stmt->fetchColumn();
$total_pages = max(1, ceil($total_utilisateurs / $limit));

// Requête principale
$sql = 'SELECT u.*, o.nom as organisme_nom, o.type as organisme_type FROM utilisateurs u LEFT JOIN organismes o ON u.organisme_id = o.organisme_id';
$params = $count_params;

if (!empty($where_clauses)) $sql .= ' WHERE ' . implode(' AND ', $where_clauses);
$sql .= ' ORDER BY u.nom_prenom ASC LIMIT ? OFFSET ?';

$stmt = $pdo->prepare($sql);
$i = 1;
foreach ($params as $p) $stmt->bindValue($i++, $p, PDO::PARAM_STR);
$stmt->bindValue($i++, $limit, PDO::PARAM_INT);
$stmt->bindValue($i++, $offset, PDO::PARAM_INT);
$stmt->execute();
$utilisateurs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Statistiques
$stmt = $pdo->query('SELECT COUNT(*) FROM utilisateurs'); $total_general = $stmt->fetchColumn();
$stmt = $pdo->query('SELECT COUNT(*) FROM utilisateurs WHERE etat = "actif"'); $total_actif = $stmt->fetchColumn();
$stmt = $pdo->query('SELECT COUNT(*) FROM utilisateurs WHERE etat = "inactif"'); $total_inactif = $stmt->fetchColumn();
$stmt = $pdo->query('SELECT COUNT(*) FROM utilisateurs WHERE etat = "en attente"'); $total_attente = $stmt->fetchColumn();

$stmt = $pdo->query('SELECT role, COUNT(*) as count FROM utilisateurs GROUP BY role');
$roles_stats_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);
$roles_stats = [];
foreach ($roles_stats_raw as $s) $roles_stats[$s['role']] = $s['count'];

// Récupérer le mot de passe généré depuis la session
$generated_password = $_SESSION['generated_password'] ?? null;
if ($generated_password) {
    unset($_SESSION['generated_password']);
}

// Fonctions pour les badges
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
    <title>Gestion des utilisateurs - Epencia SGI</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=DM+Serif+Display:ital@0;1&display=swap" rel="stylesheet">
    <style>
        /* ============================================================ */
        /* VARIABLES - DESIGN MÉDICAL AVEC NOUVELLE POLICE */
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

            /* Nouvelle police */
            --font-primary: 'Plus Jakarta Sans', -apple-system, system-ui, sans-serif;
            --font-serif: 'DM Serif Display', Georgia, serif;
        }

        /* ============================================================ */
        /* RESET & BASE - NOUVELLE POLICE */
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
            max-width: 1920px;
            margin: 0 auto;
        }

        /* ============================================================ */
        /* HEADER - AVEC NOUVELLE POLICE */
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

        .header-actions .btn {
            font-family: var(--font-primary);
            font-weight: 700;
            font-size: clamp(0.75rem, 1vw, 0.9rem);
            padding: 9px 18px;
            border-radius: var(--medical-radius-sm);
            transition: var(--medical-transition);
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border: none;
            white-space: nowrap;
            letter-spacing: 0.02em;
        }

        .btn-medical-primary {
            background: var(--medical-blue);
            color: #fff;
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
            box-shadow: 0 4px 16px rgba(45, 155, 142, 0.3);
        }
        .btn-medical-secondary:hover {
            background: #23837a;
            transform: translateY(-2px);
            box-shadow: 0 8px 28px rgba(45, 155, 142, 0.4);
            color: #fff;
        }

        /* ============================================================ */
        /* STATS CARDS */
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

        /* ============================================================ */
        /* FILTER SECTION */
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

        .filter-section .form-control::placeholder {
            color: var(--medical-text-muted);
            font-weight: 400;
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
            font-size: clamp(0.75rem, 0.85vw, 0.9rem);
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

        .filter-pills {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-top: 14px;
        }

        .filter-pill {
            font-family: var(--font-primary);
            background: var(--medical-blue-light);
            color: var(--medical-blue);
            padding: 4px 14px;
            border-radius: 20px;
            font-size: clamp(11px, 0.7vw, 12px);
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        /* ============================================================ */
        /* CARD PRINCIPALE */
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
            min-width: 680px;
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

        .table-modern tbody tr.row-actif { background: #f0faf8; }
        .table-modern tbody tr.row-inactif { background: #fdf5f4; }
        .table-modern tbody tr.row-attente { background: #fdf8f0; }

        /* ============================================================ */
        /* AVATAR */
        /* ============================================================ */
        .avatar-group {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 13px;
            color: #fff;
            flex-shrink: 0;
            font-family: var(--font-primary);
        }

        .avatar.blue { background: linear-gradient(135deg, var(--medical-blue), #4a9bbf); }
        .avatar.teal { background: linear-gradient(135deg, var(--medical-teal), #5ab8ab); }
        .avatar.warning { background: linear-gradient(135deg, var(--warning), #e8c56a); }
        .avatar.danger { background: linear-gradient(135deg, var(--danger), #e8746a); }
        .avatar.info { background: linear-gradient(135deg, var(--info), #6bb8e8); }

        .user-details {
            display: flex;
            flex-direction: column;
            min-width: 0;
        }
        .user-details .name {
            font-family: var(--font-primary);
            font-weight: 700;
            font-size: clamp(0.75rem, 0.85vw, 0.9rem);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            color: var(--medical-text);
        }
        .user-details .id {
            font-family: var(--font-primary);
            font-size: clamp(10px, 0.65vw, 12px);
            color: var(--medical-text-muted);
            font-weight: 500;
        }

        /* ============================================================ */
        /* BADGES */
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

        .badge-medical.success { background: var(--medical-teal-light); color: var(--medical-teal); }
        .badge-medical.danger { background: var(--danger-light); color: var(--danger); }
        .badge-medical.warning { background: var(--warning-light); color: var(--warning); }
        .badge-medical.primary { background: var(--medical-blue-light); color: var(--medical-blue); }
        .badge-medical.info { background: var(--info-light); color: var(--info); }
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
        .btn-action.key:hover { background: var(--warning-light); color: var(--warning); }
        .btn-action.shuffle:hover { background: var(--medical-teal-light); color: var(--medical-teal); }
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
            background: var(--medical-gray-light);
            border-bottom: 1px solid var(--medical-border);
        }

        .modal-header-custom h5 {
            font-family: var(--font-primary);
            font-weight: 700;
            color: var(--medical-text);
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

        .modal-body .form-text {
            font-family: var(--font-primary);
            color: var(--medical-text-muted);
            font-size: 0.8rem;
            font-weight: 400;
        }

        .password-display-medical {
            background: var(--medical-teal-light);
            border: 2px solid var(--medical-teal);
            border-radius: var(--medical-radius-sm);
            padding: 20px;
            text-align: center;
            margin: 16px 0;
            font-family: var(--font-primary);
        }

        .password-display-medical .password-text {
            font-family: 'SF Mono', 'Fira Code', 'Courier New', monospace;
            font-size: clamp(1.2rem, 2vw, 1.8rem);
            font-weight: 800;
            color: var(--medical-teal);
            letter-spacing: 2px;
            background: #fff;
            padding: 10px 24px;
            border-radius: 8px;
            display: inline-block;
            margin: 8px 0;
            border: 2px dashed var(--medical-teal);
            word-break: break-all;
        }

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
            .header-actions .btn { padding: 7px 14px; font-size: 0.8rem; }
            .header-actions .btn span { display: none; }
            .header-actions .btn i { font-size: 1.1rem; }
            .modal-body { padding: 16px; }
            .filter-actions { height: auto; flex-wrap: wrap; }
            .filter-actions .btn { height: 38px; padding: 0 14px; font-size: 0.8rem; }
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
            .table-modern { font-size: 0.7rem; min-width: 550px; }
            .table-modern thead th, .table-modern tbody td { padding: 6px 8px; }
            .avatar { width: 28px; height: 28px; font-size: 10px; }
            .user-details .name { font-size: 0.7rem; }
            .user-details .id { font-size: 9px; }
            .btn-action { width: 26px; height: 26px; font-size: 11px; border-radius: 6px; }
            .pagination .page-link { padding: 4px 8px; font-size: 0.7rem; }
            .modal-dialog { margin: 8px; }
            .modal-body { padding: 12px; }
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
        /* SCROLLBAR */
        /* ============================================================ */
        .table-wrapper::-webkit-scrollbar { height: 5px; }
        .table-wrapper::-webkit-scrollbar-track { background: var(--medical-gray-light); border-radius: 10px; }
        .table-wrapper::-webkit-scrollbar-thumb { background: var(--medical-border); border-radius: 10px; }
        .table-wrapper::-webkit-scrollbar-thumb:hover { background: var(--medical-text-muted); }
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
            <h1>Gestion des <span class="highlight">utilisateurs</span></h1>
            <div class="subtitle">
                <i class="bi bi-people-fill"></i>
                Gérer les utilisateurs du système
                <span class="dot"></span>
                <i class="bi bi-person-circle"></i> <?php echo htmlspecialchars($user_nom); ?>
                <span class="dot"></span>
                <?php echo getRoleBadge($user_role); ?>
            </div>
        </div>
        <div class="header-actions">
            <button class="btn btn-medical-primary" data-bs-toggle="modal" data-bs-target="#addModal">
                <i class="bi bi-person-plus"></i> <span>Nouvel utilisateur</span>
            </button>
            <form method="POST" class="d-inline">
                <button type="submit" name="export_excel" class="btn btn-medical-secondary">
                    <i class="bi bi-file-earmark-spreadsheet"></i> <span>Exporter</span>
                </button>
            </form>
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
    <!-- MOT DE PASSE GÉNÉRÉ -->
    <!-- ============================================================ -->
    <?php if ($generated_password): ?>
        <div class="alert alert-medical alert-info alert-dismissible fade show" role="alert">
            <h6 class="mb-2"><i class="bi bi-key-fill me-2"></i>Nouveau mot de passe généré</h6>
            <div class="password-display-medical">
                <div><strong>Utilisateur :</strong> <?= htmlspecialchars($generated_password['nom_prenom']) ?></div>
                <div><strong>Login :</strong> <code><?= htmlspecialchars($generated_password['login']) ?></code></div>
                <div><strong>Email :</strong> <?= htmlspecialchars($generated_password['email'] ?? 'Non renseigné') ?></div>
                <div class="mt-3">
                    <div>Nouveau mot de passe :</div>
                    <div class="password-text" id="generatedPassword"><?= htmlspecialchars($generated_password['password']) ?></div>
                </div>
                <div class="mt-3 d-flex flex-wrap justify-content-center gap-2">
                    <button onclick="copyPassword()" class="btn btn-medical-secondary"><i class="bi bi-clipboard me-1"></i> Copier</button>
                    <button onclick="printPassword()" class="btn btn-medical-secondary"><i class="bi bi-printer me-1"></i> Imprimer</button>
                </div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- ============================================================ -->
    <!-- STATISTIQUES -->
    <!-- ============================================================ -->
    <div class="stats-grid fade-in">
        <div class="stat-card">
            <div class="stat-icon blue"><i class="bi bi-people"></i></div>
            <div>
                <div class="stat-value"><?= $total_general ?></div>
                <div class="stat-label">Total utilisateurs</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon teal"><i class="bi bi-check-circle"></i></div>
            <div>
                <div class="stat-value"><?= $total_actif ?></div>
                <div class="stat-label">Utilisateurs actifs</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon warning"><i class="bi bi-clock"></i></div>
            <div>
                <div class="stat-value"><?= $total_attente ?></div>
                <div class="stat-label">En attente</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon danger"><i class="bi bi-x-circle"></i></div>
            <div>
                <div class="stat-value"><?= $total_inactif ?></div>
                <div class="stat-label">Inactifs</div>
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
            <div class="col-12 col-sm-6 col-md-3">
                <label class="form-label">Recherche</label>
                <input type="text" class="form-control" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="ID, nom, login, email...">
            </div>
            <div class="col-6 col-sm-3 col-md-2">
                <label class="form-label">État</label>
                <select name="etat" class="form-select">
                    <option value="">Tous</option>
                    <?php foreach ($etats_utilisateur as $e): ?>
                        <option value="<?= $e ?>" <?= $filter_etat == $e ? 'selected' : '' ?>><?= ucfirst($e) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-sm-3 col-md-2">
                <label class="form-label">Rôle</label>
                <select name="role" class="form-select">
                    <option value="">Tous</option>
                    <?php foreach ($roles_utilisateur as $r): ?>
                        <option value="<?= $r ?>" <?= $filter_role == $r ? 'selected' : '' ?>><?= ucfirst($r) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-8 col-sm-6 col-md-3">
                <label class="form-label">Organisme</label>
                <select name="organisme_id" class="form-select">
                    <option value="">Tous</option>
                    <?php foreach ($liste_organismes as $o): ?>
                        <option value="<?= htmlspecialchars($o['organisme_id']) ?>" <?= $filter_organisme == $o['organisme_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($o['nom']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-4 col-sm-6 col-md-2">
                <div class="filter-actions">
                    <button type="submit" name="apply_filters" class="btn btn-filter w-100">
                        <i class="bi bi-search"></i> Filtrer
                    </button>
                    <button type="submit" name="reset_filters" class="btn btn-reset">
                        <i class="bi bi-arrow-repeat"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- Filtres actifs -->
        <?php 
        $active_filters = [];
        if (!empty($search)) $active_filters[] = ['label' => 'Recherche', 'value' => $search];
        if (!empty($filter_etat)) $active_filters[] = ['label' => 'État', 'value' => ucfirst($filter_etat)];
        if (!empty($filter_role)) $active_filters[] = ['label' => 'Rôle', 'value' => ucfirst($filter_role)];
        if (!empty($filter_organisme)) {
            $org_label = '';
            foreach ($liste_organismes as $o) { 
                if ($o['organisme_id'] == $filter_organisme) { $org_label = $o['nom']; break; } 
            }
            $active_filters[] = ['label' => 'Organisme', 'value' => $org_label ?: $filter_organisme];
        }
        ?>
        <?php if (!empty($active_filters)): ?>
            <div class="filter-pills">
                <span class="text-muted me-1" style="font-size:clamp(10px,0.7vw,12px);"><i class="bi bi-funnel-fill"></i> Filtres :</span>
                <?php foreach ($active_filters as $f): ?>
                    <span class="filter-pill">
                        <strong><?= $f['label'] ?>:</strong> <?= htmlspecialchars($f['value']) ?>
                    </span>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </form>

    <!-- ============================================================ -->
    <!-- LISTE DES UTILISATEURS -->
    <!-- ============================================================ -->
    <div class="card-modern fade-in">
        <div class="card-header">
            <h5>
                <i class="bi bi-people-fill" style="color:var(--medical-blue);"></i>
                Liste des utilisateurs
                <span class="badge-count"><?= $total_utilisateurs ?></span>
            </h5>
            <?php if (!empty($active_filters)): ?>
                <form method="POST" class="d-inline">
                    <button type="submit" name="reset_filters" class="btn btn-reset" style="padding:4px 14px;font-size:clamp(10px,0.7vw,12px);height:auto;">
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
                            <th>Utilisateur</th>
                            <th>Login</th>
                            <th class="d-none d-md-table-cell">Email</th>
                            <th class="d-none d-lg-table-cell">Téléphone</th>
                            <th>Rôle</th>
                            <th class="d-none d-xl-table-cell">Organisme</th>
                            <th class="text-center">État</th>
                            <th class="text-center" style="min-width:120px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($utilisateurs)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-5" style="color:var(--medical-text-muted);">
                                    <i class="bi bi-inbox" style="font-size:3rem;display:block;margin-bottom:12px;opacity:0.3;"></i>
                                    Aucun utilisateur trouvé
                                </td>
                            </tr>
                        <?php else: 
                            $avatarColors = ['blue', 'teal', 'warning', 'danger', 'info'];
                            $colorIndex = 0;
                            foreach ($utilisateurs as $u): 
                                $row_class = 'row-' . str_replace(' ', '', $u['etat']);
                                $initiales = '';
                                $parts = explode(' ', trim($u['nom_prenom'] ?? 'U'));
                                foreach ($parts as $p) { if (!empty($p)) $initiales .= strtoupper(substr($p, 0, 1)); }
                                if (empty($initiales)) $initiales = 'U';
                                $color = $avatarColors[$colorIndex % count($avatarColors)];
                                $colorIndex++;
                        ?>
                            <tr class="<?= $row_class ?>">
                                <td>
                                    <div class="avatar-group">
                                        <span class="avatar <?= $color ?>"><?= substr($initiales, 0, 2) ?></span>
                                        <div class="user-details">
                                            <span class="name"><?= htmlspecialchars($u['nom_prenom'] ?? '-') ?></span>
                                            <span class="id"><?= htmlspecialchars($u['utilisateur_id']) ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td><code style="font-size:clamp(10px,0.7vw,13px);background:var(--medical-gray-light);padding:2px 10px;border-radius:6px;font-weight:600;font-family:var(--font-primary);"><?= htmlspecialchars($u['login'] ?? '-') ?></code></td>
                                <td class="d-none d-md-table-cell"><?= htmlspecialchars($u['email'] ?? '-') ?></td>
                                <td class="d-none d-lg-table-cell"><?= htmlspecialchars($u['telephone'] ?? '-') ?></td>
                                <td><?= getRoleBadge($u['role'] ?? '') ?></td>
                                <td class="d-none d-xl-table-cell">
                                    <?php if (!empty($u['organisme_nom'])): ?>
                                        <span style="font-weight:600;font-size:clamp(0.7rem,0.75vw,0.85rem);font-family:var(--font-primary);"><?= htmlspecialchars($u['organisme_nom']) ?></span>
                                    <?php else: ?>
                                        <span class="text-muted" style="font-size:clamp(0.7rem,0.75vw,0.85rem);font-family:var(--font-primary);">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center"><?= getStatusBadge($u['etat'] ?? '') ?></td>
                                <td>
                                    <div class="actions-group">
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="edit_utilisateur_id" value="<?= htmlspecialchars($u['utilisateur_id']) ?>">
                                            <button type="submit" class="btn-action edit" title="Modifier">
                                                <i class="bi bi-pencil-square"></i>
                                            </button>
                                        </form>
                                        <button type="button" class="btn-action key" data-bs-toggle="modal" data-bs-target="#resetPasswordModal" 
                                            data-utilisateur-id="<?= htmlspecialchars($u['utilisateur_id']) ?>" 
                                            data-utilisateur-nom="<?= htmlspecialchars($u['nom_prenom'] ?? '-') ?>" 
                                            title="Réinitialiser mot de passe">
                                            <i class="bi bi-key"></i>
                                        </button>
                                        <button type="button" class="btn-action shuffle" data-bs-toggle="modal" data-bs-target="#generatePasswordModal" 
                                            data-utilisateur-id="<?= htmlspecialchars($u['utilisateur_id']) ?>" 
                                            data-utilisateur-nom="<?= htmlspecialchars($u['nom_prenom'] ?? '-') ?>" 
                                            title="Générer mot de passe">
                                            <i class="bi bi-shuffle"></i>
                                        </button>
                                        <button type="button" class="btn-action delete" data-bs-toggle="modal" data-bs-target="#deleteModal" 
                                            data-utilisateur-id="<?= htmlspecialchars($u['utilisateur_id']) ?>" 
                                            data-utilisateur-nom="<?= htmlspecialchars($u['nom_prenom'] ?? '-') ?>" 
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
                                <input type="hidden" name="etat" value="<?= htmlspecialchars($filter_etat) ?>">
                                <input type="hidden" name="role" value="<?= htmlspecialchars($filter_role) ?>">
                                <input type="hidden" name="organisme_id" value="<?= htmlspecialchars($filter_organisme) ?>">
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
                                    <input type="hidden" name="etat" value="<?= htmlspecialchars($filter_etat) ?>">
                                    <input type="hidden" name="role" value="<?= htmlspecialchars($filter_role) ?>">
                                    <input type="hidden" name="organisme_id" value="<?= htmlspecialchars($filter_organisme) ?>">
                                    <button type="submit" class="page-link"><?= $i ?></button>
                                </form>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="page" value="<?= $page + 1 ?>">
                                <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
                                <input type="hidden" name="etat" value="<?= htmlspecialchars($filter_etat) ?>">
                                <input type="hidden" name="role" value="<?= htmlspecialchars($filter_role) ?>">
                                <input type="hidden" name="organisme_id" value="<?= htmlspecialchars($filter_organisme) ?>">
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
<!-- MODALS -->
<!-- ============================================================ -->

<!-- Modal Nouvel Utilisateur -->
<div class="modal fade" id="addModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header modal-header-custom">
                <h5><i class="bi bi-person-plus" style="color:var(--medical-blue);"></i>Nouvel utilisateur</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="row g-3 mb-3">
                        <div class="col-12 col-md-6">
                            <label class="form-label">ID Utilisateur <span class="text-danger">*</span></label>
                            <input type="text" name="utilisateur_id" class="form-control" required placeholder="Ex: USR-001">
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label">Nom & Prénom <span class="text-danger">*</span></label>
                            <input type="text" name="nom_prenom" class="form-control" required placeholder="Ex: Dr. Jean DUPONT">
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-12 col-md-6">
                            <label class="form-label">Login <span class="text-danger">*</span></label>
                            <input type="text" name="login" class="form-control" required placeholder="Nom d'utilisateur">
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label">Mot de passe <span class="text-danger">*</span></label>
                            <input type="password" name="mdp" class="form-control" required placeholder="Minimum 6 caractères" minlength="6">
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-12 col-md-6">
                            <label class="form-label">Téléphone</label>
                            <input type="text" name="telephone" class="form-control" placeholder="+225 XX XX XX XX">
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" placeholder="exemple@hopital.ci">
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-12 col-md-6">
                            <label class="form-label">Rôle <span class="text-danger">*</span></label>
                            <select name="role" class="form-select" required>
                                <option value="">Sélectionnez un rôle</option>
                                <?php foreach ($roles_utilisateur as $r): ?>
                                    <option value="<?= $r ?>"><?= ucfirst($r) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label">Organisme</label>
                            <select name="organisme_id" class="form-select">
                                <option value="">Aucun</option>
                                <?php foreach ($liste_organismes as $o): ?>
                                    <option value="<?= htmlspecialchars($o['organisme_id']) ?>">
                                        <?= htmlspecialchars($o['nom']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label">Date de saisie</label>
                            <input type="date" name="date_saisie" class="form-control" value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label">État</label>
                            <select name="etat" class="form-select">
                                <?php foreach ($etats_utilisateur as $e): ?>
                                    <option value="<?= $e ?>" <?= $e == 'actif' ? 'selected' : '' ?>><?= ucfirst($e) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-medical-secondary" data-bs-dismiss="modal" style="background:var(--medical-gray);color:var(--medical-text);">Annuler</button>
                    <button type="submit" class="btn btn-medical-primary"><i class="bi bi-check-lg me-1"></i>Créer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Modification -->
<div class="modal fade" id="editModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header modal-header-custom">
                <h5><i class="bi bi-pencil-square" style="color:var(--medical-blue);"></i>Modifier l'utilisateur</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="editForm">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" id="edit_id">
                    <input type="hidden" name="page" value="<?= $page ?>">
                    <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
                    <input type="hidden" name="etat" value="<?= htmlspecialchars($filter_etat) ?>">
                    <input type="hidden" name="role" value="<?= htmlspecialchars($filter_role) ?>">
                    <input type="hidden" name="organisme_id" value="<?= htmlspecialchars($filter_organisme) ?>">

                    <div class="row g-3 mb-3">
                        <div class="col-12 col-md-6">
                            <label class="form-label">ID Utilisateur</label>
                            <input type="text" name="utilisateur_id" id="edit_utilisateur_id" class="form-control" required readonly style="background:var(--medical-gray-light);font-family:monospace;font-weight:600;">
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label">Nom & Prénom</label>
                            <input type="text" name="nom_prenom" id="edit_nom_prenom" class="form-control" required>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-12 col-md-6">
                            <label class="form-label">Login</label>
                            <input type="text" name="login" id="edit_login" class="form-control" required>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label">Nouveau mot de passe <span class="form-text">(laisser vide)</span></label>
                            <input type="password" name="mdp" id="edit_mdp" class="form-control" placeholder="Nouveau mot de passe">
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-12 col-md-6">
                            <label class="form-label">Téléphone</label>
                            <input type="text" name="telephone" id="edit_telephone" class="form-control">
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" id="edit_email" class="form-control">
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-12 col-md-6">
                            <label class="form-label">Rôle</label>
                            <select name="role" id="edit_role" class="form-select" required>
                                <?php foreach ($roles_utilisateur as $r): ?>
                                    <option value="<?= $r ?>"><?= ucfirst($r) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label">Organisme</label>
                            <select name="organisme_id" id="edit_organisme_id" class="form-select">
                                <option value="">Aucun</option>
                                <?php foreach ($liste_organismes as $o): ?>
                                    <option value="<?= htmlspecialchars($o['organisme_id']) ?>">
                                        <?= htmlspecialchars($o['nom']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">État</label>
                            <select name="etat" id="edit_etat" class="form-select">
                                <?php foreach ($etats_utilisateur as $e): ?>
                                    <option value="<?= $e ?>"><?= ucfirst($e) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-medical-secondary" data-bs-dismiss="modal" style="background:var(--medical-gray);color:var(--medical-text);">Annuler</button>
                    <button type="submit" class="btn btn-medical-primary"><i class="bi bi-check-lg me-1"></i>Enregistrer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Réinitialiser mot de passe -->
<div class="modal fade" id="resetPasswordModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header modal-header-custom">
                <h5><i class="bi bi-key" style="color:var(--warning);"></i>Réinitialiser le mot de passe</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="reset_password">
                    <input type="hidden" name="id" id="reset_id">
                    
                    <div class="alert alert-medical alert-warning">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        Vous êtes sur le point de réinitialiser le mot de passe de :<br>
                        <strong id="reset_nom"></strong>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Nouveau mot de passe <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="password" name="new_password" id="new_password" class="form-control" required minlength="6" placeholder="Minimum 6 caractères">
                            <button type="button" class="btn btn-medical-secondary" onclick="togglePasswordVisibility('new_password', 'eye_reset')" style="border-radius:0 var(--medical-radius-sm) var(--medical-radius-sm) 0;">
                                <i class="bi bi-eye" id="eye_reset"></i>
                            </button>
                        </div>
                        <div class="form-text">Le mot de passe doit contenir au moins 6 caractères.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Confirmer le mot de passe <span class="text-danger">*</span></label>
                        <input type="password" id="confirm_password" class="form-control" required minlength="6" placeholder="Confirmer le mot de passe">
                        <div id="password_match_error" class="text-danger mt-1" style="display:none;font-size:13px;">Les mots de passe ne correspondent pas.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-medical-secondary" data-bs-dismiss="modal" style="background:var(--medical-gray);color:var(--medical-text);">Annuler</button>
                    <button type="submit" id="btn_reset_submit" class="btn" style="background:var(--warning);color:#fff;border:none;padding:9px 24px;border-radius:var(--medical-radius-sm);font-weight:700;font-family:var(--font-primary);transition:var(--medical-transition);">
                        <i class="bi bi-check-lg me-1"></i>Réinitialiser
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Générer mot de passe -->
<div class="modal fade" id="generatePasswordModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header modal-header-custom">
                <h5><i class="bi bi-shuffle" style="color:var(--medical-teal);"></i>Générer un mot de passe</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="generate_password">
                    <input type="hidden" name="id" id="generate_id">
                    
                    <div class="alert alert-medical alert-info">
                        <i class="bi bi-info-circle me-2"></i>
                        Un mot de passe aléatoire de 10 caractères sera généré pour :<br>
                        <strong id="generate_nom"></strong>
                    </div>

                    <div class="alert alert-medical alert-warning">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        <strong>Attention :</strong> Le mot de passe sera affiché une seule fois. Assurez-vous de le noter ou de le copier avant de valider.
                    </div>

                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="confirm_generate" required>
                        <label class="form-check-label" for="confirm_generate" style="font-weight:600;font-size:clamp(13px,0.9vw,14px);font-family:var(--font-primary);">
                            Je confirme vouloir générer un nouveau mot de passe
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-medical-secondary" data-bs-dismiss="modal" style="background:var(--medical-gray);color:var(--medical-text);">Annuler</button>
                    <button type="submit" class="btn btn-medical-secondary"><i class="bi bi-shuffle me-1"></i>Générer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Suppression -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header modal-header-custom">
                <h5><i class="bi bi-exclamation-triangle-fill" style="color:var(--danger);"></i>Confirmer la suppression</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" id="delete_id">
                    
                    <p style="font-weight:500;font-family:var(--font-primary);">Voulez-vous vraiment supprimer cet utilisateur ?</p>
                    
                    <div style="background:var(--medical-gray-light);padding:14px 18px;border-radius:var(--medical-radius-sm);border-left:4px solid var(--danger);font-family:var(--font-primary);">
                        <strong>ID Utilisateur :</strong> <span id="delete_utilisateur_id"></span><br>
                        <strong>Nom :</strong> <span id="delete_utilisateur_nom"></span>
                    </div>
                    
                    <p class="text-danger mb-0 mt-3" style="font-size:clamp(12px,0.8vw,13px);font-weight:700;font-family:var(--font-primary);">
                        <i class="bi bi-exclamation-circle me-1"></i>
                        Cette action est irréversible.
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-medical-secondary" data-bs-dismiss="modal" style="background:var(--medical-gray);color:var(--medical-text);">Annuler</button>
                    <button type="submit" class="btn btn-medical-danger"><i class="bi bi-trash me-1"></i>Supprimer</button>
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
document.addEventListener('DOMContentLoaded', function() {
    // Chargement des données pour la modification
    <?php if ($edit_utilisateur): ?>
        document.getElementById('edit_id').value = '<?= addslashes($edit_utilisateur['utilisateur_id']) ?>';
        document.getElementById('edit_utilisateur_id').value = '<?= addslashes($edit_utilisateur['utilisateur_id']) ?>';
        document.getElementById('edit_nom_prenom').value = '<?= addslashes($edit_utilisateur['nom_prenom'] ?? '') ?>';
        document.getElementById('edit_login').value = '<?= addslashes($edit_utilisateur['login'] ?? '') ?>';
        document.getElementById('edit_telephone').value = '<?= addslashes($edit_utilisateur['telephone'] ?? '') ?>';
        document.getElementById('edit_email').value = '<?= addslashes($edit_utilisateur['email'] ?? '') ?>';
        document.getElementById('edit_role').value = '<?= addslashes($edit_utilisateur['role'] ?? '') ?>';
        document.getElementById('edit_organisme_id').value = '<?= addslashes($edit_utilisateur['organisme_id'] ?? '') ?>';
        document.getElementById('edit_etat').value = '<?= addslashes($edit_utilisateur['etat'] ?? '') ?>';
        
        var editModal = new bootstrap.Modal(document.getElementById('editModal'));
        editModal.show();
    <?php endif; ?>

    // Modal Réinitialiser mot de passe
    document.getElementById('resetPasswordModal')?.addEventListener('show.bs.modal', function(e) {
        let button = e.relatedTarget;
        document.getElementById('reset_id').value = button.getAttribute('data-utilisateur-id');
        document.getElementById('reset_nom').textContent = button.getAttribute('data-utilisateur-nom');
        document.getElementById('new_password').value = '';
        document.getElementById('confirm_password').value = '';
        document.getElementById('password_match_error').style.display = 'none';
        document.getElementById('btn_reset_submit').disabled = false;
    });

    // Modal Générer mot de passe
    document.getElementById('generatePasswordModal')?.addEventListener('show.bs.modal', function(e) {
        let button = e.relatedTarget;
        document.getElementById('generate_id').value = button.getAttribute('data-utilisateur-id');
        document.getElementById('generate_nom').textContent = button.getAttribute('data-utilisateur-nom');
        document.getElementById('confirm_generate').checked = false;
    });

    // Modal Suppression
    document.getElementById('deleteModal')?.addEventListener('show.bs.modal', function(e) {
        let button = e.relatedTarget;
        document.getElementById('delete_id').value = button.getAttribute('data-utilisateur-id');
        document.getElementById('delete_utilisateur_id').textContent = button.getAttribute('data-utilisateur-id');
        document.getElementById('delete_utilisateur_nom').textContent = button.getAttribute('data-utilisateur-nom');
    });

    // Validation des mots de passe
    document.getElementById('confirm_password')?.addEventListener('input', function() {
        const newPwd = document.getElementById('new_password').value;
        const confirmPwd = this.value;
        const errorDiv = document.getElementById('password_match_error');
        const submitBtn = document.getElementById('btn_reset_submit');

        if (newPwd !== confirmPwd) {
            errorDiv.style.display = 'block';
            if (submitBtn) submitBtn.disabled = true;
        } else {
            errorDiv.style.display = 'none';
            if (submitBtn) submitBtn.disabled = false;
        }
    });

    document.getElementById('new_password')?.addEventListener('input', function() {
        const confirmPwd = document.getElementById('confirm_password').value;
        if (confirmPwd) {
            document.getElementById('confirm_password').dispatchEvent(new Event('input'));
        }
    });
});

// Toggle visibility du mot de passe
function togglePasswordVisibility(inputId, iconId) {
    const input = document.getElementById(inputId);
    const icon = document.getElementById(iconId);
    
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('bi-eye');
        icon.classList.add('bi-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.remove('bi-eye-slash');
        icon.classList.add('bi-eye');
    }
}

// Copier le mot de passe généré
function copyPassword() {
    const password = document.getElementById('generatedPassword')?.textContent;
    if (!password) return;
    
    navigator.clipboard.writeText(password).then(() => {
        alert('✅ Mot de passe copié dans le presse-papier !');
    }).catch(() => {
        const textArea = document.createElement('textarea');
        textArea.value = password;
        document.body.appendChild(textArea);
        textArea.select();
        document.execCommand('copy');
        document.body.removeChild(textArea);
        alert('✅ Mot de passe copié !');
    });
}

// Imprimer le mot de passe
function printPassword() {
    const password = document.getElementById('generatedPassword')?.textContent;
    if (!password) return;
    
    const printWindow = window.open('', '', 'height=400,width=600');
    printWindow.document.write('<html><head><title>Mot de passe</title></head><body>');
    printWindow.document.write('<h2>Nouveau mot de passe</h2>');
    printWindow.document.write('<p><strong>Mot de passe :</strong> <span style="font-family:monospace;font-size:24px;background:#f0f0f0;padding:10px;">' + password + '</span></p>');
    printWindow.document.write('<p><em>Veuillez changer ce mot de passe lors de votre première connexion.</em></p>');
    printWindow.document.write('</body></html>');
    printWindow.document.close();
    printWindow.print();
}
</script>

</body>
</html>