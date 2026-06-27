<?php
// views/utilisateur/profil.php - Page de profil utilisateur

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['utilisateur_id'])) {
    header('Location: connexion.php');
    exit;
}

require 'database/database.php';

 $user_id = $_SESSION['utilisateur_id'];
 $message = '';
 $error = '';

try {
    $stmt = $pdo->prepare("SELECT * FROM utilisateurs WHERE utilisateur_id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        header('Location: deconnexion.php');
        exit;
    }
} catch (PDOException $e) {
    $error = 'Erreur lors du chargement du profil.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    if (isset($_POST['update_profil'])) {
        $nom_prenom = trim($_POST['nom_prenom'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $telephone = trim($_POST['telephone'] ?? '');
        $organisme_id = trim($_POST['organisme_id'] ?? '');
        
        if (empty($nom_prenom) || empty($email)) {
            $error = 'Le nom et l\'email sont obligatoires.';
        } else {
            try {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM utilisateurs WHERE email = ? AND utilisateur_id != ?");
                $stmt->execute([$email, $user_id]);
                if ($stmt->fetchColumn() > 0) {
                    $error = 'Cet email est déjà utilisé par un autre utilisateur.';
                } else {
                    $stmt = $pdo->prepare("UPDATE utilisateurs SET nom_prenom = ?, email = ?, telephone = ?, organisme_id = ? WHERE utilisateur_id = ?");
                    $stmt->execute([$nom_prenom, $email, $telephone, $organisme_id, $user_id]);
                    
                    $_SESSION['nom_prenom'] = $nom_prenom;
                    $_SESSION['email'] = $email;
                    $message = 'Profil mis à jour avec succès !';
                    
                    $stmt = $pdo->prepare("SELECT * FROM utilisateurs WHERE utilisateur_id = ?");
                    $stmt->execute([$user_id]);
                    $user = $stmt->fetch(PDO::FETCH_ASSOC);
                }
            } catch (PDOException $e) {
                $error = 'Erreur lors de la mise à jour du profil.';
            }
        }
    }
    
    if (isset($_POST['change_password'])) {
        $old_password = trim($_POST['old_password'] ?? '');
        $new_password = trim($_POST['new_password'] ?? '');
        $confirm_password = trim($_POST['confirm_password'] ?? '');
        
        if (empty($old_password) || empty($new_password) || empty($confirm_password)) {
            $error = 'Tous les champs sont obligatoires.';
        } elseif ($new_password !== $confirm_password) {
            $error = 'Les nouveaux mots de passe ne correspondent pas.';
        } elseif (strlen($new_password) < 6) {
            $error = 'Le mot de passe doit contenir au moins 6 caractères.';
        } else {
            try {
                $stmt = $pdo->prepare("SELECT mdp FROM utilisateurs WHERE utilisateur_id = ?");
                $stmt->execute([$user_id]);
                $current_password_hash = $stmt->fetchColumn();
                
                if (password_verify($old_password, $current_password_hash) || $old_password === $current_password_hash) {
                    $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE utilisateurs SET mdp = ? WHERE utilisateur_id = ?");
                    $stmt->execute([$new_hash, $user_id]);
                    $message = 'Mot de passe changé avec succès !';
                } else {
                    $error = 'L\'ancien mot de passe est incorrect.';
                }
            } catch (PDOException $e) {
                $error = 'Erreur lors du changement de mot de passe.';
            }
        }
    }
}

 $organismes = [];
try {
    $stmt = $pdo->query("SELECT organisme_id, nom FROM organismes WHERE statut = 'actif' ORDER BY nom");
    $organismes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

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
    <title>Mon Profil - Epencia SGI</title>
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
            max-width: 1200px;
            margin: 0 auto;
        }

        /* ============================================================ */
        /* PAGE TITLE BAR - remplace le header classique */
        /* ============================================================ */
        .page-title-bar {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
            padding: 16px 22px;
            background: var(--medical-white);
            border-radius: var(--medical-radius);
            box-shadow: var(--medical-shadow);
            border: 1px solid var(--medical-border);
            position: relative;
            overflow: hidden;
        }

        .page-title-bar::before {
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

        .page-title-bar .left {
            flex: 1 1 auto;
            min-width: 0;
        }

        .page-title-bar .medical-badge {
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
            margin-bottom: 4px;
        }

        .page-title-bar h1 {
            font-family: var(--font-serif);
            font-size: clamp(20px, 2.5vw, 30px);
            font-weight: 400;
            color: var(--medical-text);
            line-height: 1.2;
        }

        .page-title-bar h1 .highlight {
            color: var(--medical-blue);
            position: relative;
        }

        .page-title-bar h1 .highlight::after {
            content: '';
            position: absolute;
            bottom: 2px; left: 0; right: 0;
            height: 3px;
            background: var(--medical-teal);
            border-radius: 2px;
            opacity: 0.4;
        }

        /* ============================================================ */
        /* BOUTON RETOUR ADAPTATIF */
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

        .btn-back-adaptive .btn-label {
            flex-shrink: 0;
        }

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
            .btn-back-adaptive {
                padding: 9px 14px;
                font-size: 0.8rem;
                gap: 6px;
            }
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
            .page-title-bar { padding: 10px 14px; margin-bottom: 12px; }
            .page-title-bar .medical-badge { display: none; }
            .page-title-bar h1 { font-size: 17px; }
            .btn-back-adaptive { width: 36px; height: 36px; }
            .btn-back-adaptive .btn-icon { font-size: 15px; }
        }

        /* ============================================================ */
        /* SIDEBAR */
        /* ============================================================ */
        .sidebar {
            background: var(--medical-white);
            border-radius: var(--medical-radius);
            box-shadow: var(--medical-shadow);
            padding: 24px;
            position: sticky;
            top: 20px;
            border: 1px solid var(--medical-border);
            transition: var(--medical-transition);
        }

        .sidebar:hover { box-shadow: var(--medical-shadow-hover); }

        .profile-avatar-wrapper {
            position: relative;
            width: 100px;
            height: 100px;
            margin: 0 auto 16px;
        }

        .profile-avatar {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--medical-blue), var(--medical-teal));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 42px;
            color: white;
            box-shadow: 0 4px 20px rgba(26, 107, 138, 0.3);
            transition: var(--medical-transition);
        }

        .profile-avatar:hover {
            transform: scale(1.05);
            box-shadow: 0 6px 30px rgba(26, 107, 138, 0.4);
        }

        .profile-name-sidebar { text-align: center; }

        .profile-name-sidebar h5 {
            font-weight: 700;
            color: var(--medical-text);
            margin-bottom: 4px;
            font-size: clamp(16px, 1.2vw, 20px);
        }

        .profile-name-sidebar .email-text {
            color: var(--medical-text-muted);
            font-size: 0.85rem;
            font-weight: 500;
            word-break: break-all;
        }

        .sidebar-divider {
            border: none;
            border-top: 1px solid var(--medical-border);
            margin: 16px 0;
        }

        .sidebar-stat {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            font-size: 0.88rem;
        }

        .sidebar-stat .label { color: var(--medical-text-muted); font-weight: 500; }
        .sidebar-stat .value { font-weight: 700; color: var(--medical-text); text-align: right; word-break: break-all; max-width: 55%; }

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
            transition: var(--medical-transition);
        }

        .card-modern:hover { box-shadow: var(--medical-shadow-hover); }

        .card-modern .card-header {
            background: var(--medical-gray-light);
            border-bottom: 1px solid var(--medical-border);
            padding: 16px 22px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 8px;
        }

        .card-modern .card-header h5 {
            font-weight: 700;
            color: var(--medical-text);
            margin: 0;
            font-size: clamp(13px, 1vw, 16px);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .card-modern .card-header .header-icon {
            width: 34px; height: 34px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 15px;
            flex-shrink: 0;
        }

        .header-icon.blue { background: var(--medical-blue-light); color: var(--medical-blue); }
        .header-icon.danger { background: var(--danger-light); color: var(--danger); }

        .card-modern .card-body { padding: 22px; }

        /* ============================================================ */
        /* FORM */
        /* ============================================================ */
        .form-group { margin-bottom: 16px; }

        .form-label {
            font-weight: 700;
            font-size: 0.78rem;
            color: var(--medical-text-secondary);
            margin-bottom: 6px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .form-label .required { color: var(--danger); margin-left: 2px; }

        .form-control, .form-select {
            border-radius: var(--medical-radius-sm);
            border: 1.5px solid var(--medical-border);
            padding: 10px 14px;
            font-size: 0.92rem;
            transition: var(--medical-transition);
            background: var(--medical-gray-light);
            color: var(--medical-text);
            font-weight: 500;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--medical-blue);
            box-shadow: 0 0 0 4px rgba(26, 107, 138, 0.1);
            background: var(--medical-white);
        }

        .form-control::placeholder { color: var(--medical-text-muted); font-weight: 400; }

        .input-group-custom { display: flex; align-items: center; }

        .input-group-custom .form-control {
            border-radius: var(--medical-radius-sm) 0 0 var(--medical-radius-sm);
            border-right: none;
        }

        .input-group-custom .btn-toggle {
            border-radius: 0 var(--medical-radius-sm) var(--medical-radius-sm) 0;
            border: 1.5px solid var(--medical-border);
            border-left: none;
            background: var(--medical-gray-light);
            padding: 10px 14px;
            color: var(--medical-text-muted);
            transition: var(--medical-transition);
            cursor: pointer;
            flex-shrink: 0;
        }

        .input-group-custom .btn-toggle:hover { background: var(--medical-gray); color: var(--medical-text); }

        .form-text {
            font-size: 0.75rem;
            color: var(--medical-text-muted);
            margin-top: 4px;
            font-weight: 500;
        }

        /* ============================================================ */
        /* BOUTONS FORMULAIRES ADAPTATIFS */
        /* ============================================================ */
        .btn-medical-primary {
            background: var(--medical-blue);
            border: none;
            color: white;
            padding: 11px 26px;
            border-radius: var(--medical-radius-sm);
            font-family: var(--font-primary);
            font-weight: 700;
            font-size: 0.88rem;
            transition: var(--medical-transition);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            letter-spacing: 0.02em;
            white-space: nowrap;
        }

        .btn-medical-primary:hover {
            background: var(--medical-blue-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 20px rgba(26, 107, 138, 0.3);
            color: white;
        }

        .btn-medical-outline {
            background: transparent;
            border: 1.5px solid var(--medical-blue);
            color: var(--medical-blue);
            padding: 11px 26px;
            border-radius: var(--medical-radius-sm);
            font-family: var(--font-primary);
            font-weight: 700;
            font-size: 0.88rem;
            transition: var(--medical-transition);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            letter-spacing: 0.02em;
            white-space: nowrap;
        }

        .btn-medical-outline:hover {
            background: var(--medical-blue);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 20px rgba(26, 107, 138, 0.2);
        }

        /* PALIER 1 : ≤ 820px - boutons pleine largeur + texte court */
        @media (max-width: 820px) {
            .btn-medical-primary,
            .btn-medical-outline {
                width: 100%;
                padding: 10px 16px;
                font-size: 0.82rem;
            }
            .btn-medical-primary .btn-label.long,
            .btn-medical-outline .btn-label.long { display: none; }
            .btn-medical-primary .btn-label.short,
            .btn-medical-outline .btn-label.short { display: inline; }
        }

        /* PALIER 2 : 821 → 1024px */
        @media (min-width: 821px) and (max-width: 1024px) {
            .btn-medical-primary,
            .btn-medical-outline {
                padding: 9px 18px;
                font-size: 0.82rem;
            }
            .btn-medical-primary .btn-label.short,
            .btn-medical-outline .btn-label.short { display: inline; }
            .btn-medical-primary .btn-label.long,
            .btn-medical-outline .btn-label.long { display: none; }
        }

        /* PALIER 3 : 1025 → 1366px */
        @media (min-width: 1025px) and (max-width: 1366px) {
            .btn-medical-primary,
            .btn-medical-outline {
                padding: 10px 22px;
                font-size: 0.85rem;
            }
            .btn-medical-primary .btn-label.short,
            .btn-medical-outline .btn-label.short { display: none; }
            .btn-medical-primary .btn-label.long,
            .btn-medical-outline .btn-label.long { display: inline; }
        }

        /* PALIER 4 : 1367 → 1960px */
        @media (min-width: 1367px) {
            .btn-medical-primary,
            .btn-medical-outline {
                padding: 11px 28px;
                font-size: 0.9rem;
            }
            .btn-medical-primary .btn-label.short,
            .btn-medical-outline .btn-label.short { display: none; }
            .btn-medical-primary .btn-label.long,
            .btn-medical-outline .btn-label.long { display: inline; }
        }

        /* HAUTEUR < 700px */
        @media (max-height: 700px) and (max-width: 820px) {
            .btn-medical-primary,
            .btn-medical-outline {
                padding: 8px 14px;
                font-size: 0.78rem;
            }
        }

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

        /* ============================================================ */
        /* ALERTS */
        /* ============================================================ */
        .alert-medical {
            border: none;
            border-radius: var(--medical-radius-sm);
            padding: 14px 18px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 500;
            font-size: 0.88rem;
        }

        .alert-medical.alert-success {
            background: var(--success-light);
            color: var(--success);
            border-left: 4px solid var(--success);
        }

        .alert-medical.alert-danger {
            background: var(--danger-light);
            color: var(--danger);
            border-left: 4px solid var(--danger);
        }

        .alert-medical .btn-close { margin-left: auto; flex-shrink: 0; }

        /* ============================================================ */
        /* RESPONSIVE GLOBAL */
        /* ============================================================ */
        @media (max-width: 1024px) {
            body { padding: 14px; }
            .sidebar { position: relative; top: 0; }
        }

        @media (max-width: 820px) {
            body { padding: 10px; }
            .sidebar { padding: 18px; margin-bottom: 0; }
            .profile-avatar-wrapper { width: 80px; height: 80px; }
            .profile-avatar { font-size: 32px; }
            .sidebar-stat { font-size: 0.8rem; padding: 5px 0; }
            .card-modern .card-body { padding: 16px; }
            .card-modern .card-header { padding: 12px 16px; }
            .form-group { margin-bottom: 12px; }
            .form-control, .form-select { font-size: 0.88rem; padding: 9px 12px; }
            .input-group-custom .btn-toggle { padding: 9px 12px; }
        }

        @media (max-height: 700px) and (max-width: 820px) {
            body { padding: 6px; }
            .page-title-bar { margin-bottom: 8px; }
            .sidebar { padding: 12px; margin-bottom: 0; }
            .profile-avatar-wrapper { width: 60px; height: 60px; }
            .profile-avatar { font-size: 24px; }
            .profile-name-sidebar h5 { font-size: 14px; }
            .sidebar-divider { margin: 10px 0; }
            .sidebar-stat { font-size: 0.72rem; padding: 3px 0; }
            .card-modern .card-body { padding: 12px; }
            .card-modern .card-header { padding: 10px 12px; }
            .form-group { margin-bottom: 8px; }
            .form-label { font-size: 0.7rem; margin-bottom: 4px; }
            .form-control, .form-select { font-size: 0.82rem; padding: 7px 10px; }
            .input-group-custom .btn-toggle { padding: 7px 10px; }
            .form-text { font-size: 0.68rem; margin-top: 2px; }
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
        ::-webkit-scrollbar { width: 5px; }
        ::-webkit-scrollbar-track { background: var(--medical-gray-light); border-radius: 10px; }
        ::-webkit-scrollbar-thumb { background: var(--medical-border); border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: var(--medical-text-muted); }
    </style>
</head>
<body>

<div class="app-container">

    <!-- ============================================================ -->
    <!-- TITLE BAR AVEC BOUTON RETOUR ADAPTATIF -->
    <!-- ============================================================ -->
    <div class="page-title-bar fade-in">
        <div class="left">
            <div class="medical-badge">
                <i class="bi bi-heart-pulse-fill"></i> Epencia SGI
            </div>
            <h1>Mon <span class="highlight">profil</span></h1>
        </div>
    </div>

    <div class="row g-3">

        <!-- ============================================================ -->
        <!-- SIDEBAR -->
        <!-- ============================================================ -->
        <div class="col-lg-3 col-md-4">
            <div class="sidebar fade-in fade-in-d1">
                <div class="profile-avatar-wrapper">
                    <div class="profile-avatar">
                        <i class="bi bi-person-fill"></i>
                    </div>
                </div>

                <div class="profile-name-sidebar">
                    <h5><?= htmlspecialchars($user['nom_prenom'] ?? 'Utilisateur') ?></h5>
                    <div class="email-text"><?= htmlspecialchars($user['email'] ?? '') ?></div>
                    <div class="mt-2"><?= getRoleBadge($user['role'] ?? '') ?></div>
                    <div class="mt-1"><?= getStatusBadge($user['etat'] ?? '') ?></div>
                </div>

                <hr class="sidebar-divider">

                <div class="sidebar-stat">
                    <span class="label">ID</span>
                    <span class="value"><?= htmlspecialchars($user['utilisateur_id'] ?? '-') ?></span>
                </div>
                <div class="sidebar-stat">
                    <span class="label">Login</span>
                    <span class="value"><?= htmlspecialchars($user['login'] ?? '-') ?></span>
                </div>
                <div class="sidebar-stat">
                    <span class="label">Téléphone</span>
                    <span class="value"><?= htmlspecialchars($user['telephone'] ?? 'Non renseigné') ?></span>
                </div>
                <div class="sidebar-stat">
                    <span class="label">Création</span>
                    <span class="value"><?= isset($user['date_saisie']) ? date('d/m/Y', strtotime($user['date_saisie'])) : '-' ?></span>
                </div>
            </div>
        </div>

        <!-- ============================================================ -->
        <!-- MAIN CONTENT -->
        <!-- ============================================================ -->
        <div class="col-lg-9 col-md-8">
            <div style="display:flex;flex-direction:column;gap:16px;">

                <!-- Messages -->
                <?php if ($message): ?>
                    <div class="alert-medical alert-success alert-dismissible fade show">
                        <i class="bi bi-check-circle-fill" style="font-size:1.1rem;flex-shrink:0;"></i>
                        <?= htmlspecialchars($message) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert-medical alert-danger alert-dismissible fade show">
                        <i class="bi bi-exclamation-triangle-fill" style="font-size:1.1rem;flex-shrink:0;"></i>
                        <?= htmlspecialchars($error) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Informations personnelles -->
                <div class="card-modern fade-in fade-in-d2">
                    <div class="card-header">
                        <h5>
                            <span class="header-icon blue"><i class="bi bi-person"></i></span>
                            Informations personnelles
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="row g-3">
                                <div class="col-md-6 form-group">
                                    <label class="form-label">Nom et Prénom <span class="required">*</span></label>
                                    <input type="text" class="form-control" name="nom_prenom"
                                           value="<?= htmlspecialchars($user['nom_prenom'] ?? '') ?>" required>
                                </div>
                                <div class="col-md-6 form-group">
                                    <label class="form-label">Email <span class="required">*</span></label>
                                    <input type="email" class="form-control" name="email"
                                           value="<?= htmlspecialchars($user['email'] ?? '') ?>" required>
                                </div>
                                <div class="col-md-6 form-group">
                                    <label class="form-label">Téléphone</label>
                                    <input type="text" class="form-control" name="telephone"
                                           value="<?= htmlspecialchars($user['telephone'] ?? '') ?>"
                                           placeholder="+225 XX XX XX XX">
                                </div>
                                <div class="col-md-6 form-group">
                                    <label class="form-label">Organisme</label>
                                    <select name="organisme_id" class="form-select">
                                        <option value="">Aucun</option>
                                        <?php foreach ($organismes as $o): ?>
                                            <option value="<?= htmlspecialchars($o['organisme_id']) ?>"
                                                <?= ($user['organisme_id'] ?? '') == $o['organisme_id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($o['nom']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="mt-3">
                                <button type="submit" name="update_profil" class="btn-medical-primary">
                                    <i class="bi bi-save"></i>
                                    <span class="btn-label long">Enregistrer les modifications</span>
                                    <span class="btn-label short">Enregistrer</span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Changement de mot de passe -->
                <div class="card-modern fade-in fade-in-d3">
                    <div class="card-header">
                        <h5>
                            <span class="header-icon danger"><i class="bi bi-lock"></i></span>
                            Changer le mot de passe
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="row g-3">
                                <div class="col-md-4 form-group">
                                    <label class="form-label">Ancien mot de passe <span class="required">*</span></label>
                                    <div class="input-group-custom">
                                        <input type="password" class="form-control" name="old_password" id="old_password" required>
                                        <button type="button" class="btn-toggle" onclick="togglePassword('old_password', 'eye_old')" aria-label="Afficher le mot de passe">
                                            <i class="bi bi-eye" id="eye_old"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="col-md-4 form-group">
                                    <label class="form-label">Nouveau <span class="required">*</span></label>
                                    <div class="input-group-custom">
                                        <input type="password" class="form-control" name="new_password" id="new_password" required minlength="6">
                                        <button type="button" class="btn-toggle" onclick="togglePassword('new_password', 'eye_new')" aria-label="Afficher le mot de passe">
                                            <i class="bi bi-eye" id="eye_new"></i>
                                        </button>
                                    </div>
                                    <div class="form-text">Minimum 6 caractères</div>
                                </div>
                                <div class="col-md-4 form-group">
                                    <label class="form-label">Confirmer <span class="required">*</span></label>
                                    <div class="input-group-custom">
                                        <input type="password" class="form-control" name="confirm_password" id="confirm_password" required>
                                        <button type="button" class="btn-toggle" onclick="togglePassword('confirm_password', 'eye_confirm')" aria-label="Afficher le mot de passe">
                                            <i class="bi bi-eye" id="eye_confirm"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="mt-3">
                                <button type="submit" name="change_password" class="btn-medical-outline">
                                    <i class="bi bi-key"></i>
                                    <span class="btn-label long">Changer le mot de passe</span>
                                    <span class="btn-label short">Changer</span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

            </div>
        </div>

    </div><!-- /row -->
</div><!-- /app-container -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function togglePassword(inputId, iconId) {
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

    document.addEventListener('DOMContentLoaded', function() {
        const newPassword = document.getElementById('new_password');
        const confirmPassword = document.getElementById('confirm_password');

        if (newPassword && confirmPassword) {
            function checkMatch() {
                if (confirmPassword.value && newPassword.value !== confirmPassword.value) {
                    confirmPassword.style.borderColor = 'var(--danger)';
                    confirmPassword.style.background = 'var(--danger-light)';
                } else if (confirmPassword.value) {
                    confirmPassword.style.borderColor = 'var(--success)';
                    confirmPassword.style.background = 'var(--success-light)';
                } else {
                    confirmPassword.style.borderColor = '';
                    confirmPassword.style.background = '';
                }
            }
            confirmPassword.addEventListener('input', checkMatch);
            newPassword.addEventListener('input', checkMatch);
        }
    });
</script>
</body>
</html>