<?php
// ================================================================
// GESTION DES NOTIFICATIONS - EPENCIA SGI
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
// 3. TRAITEMENT DES REQUÊTES AJAX
// ================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    header('Content-Type: application/json; charset=utf-8');
    
    if ($_POST['ajax_action'] === 'load_notification') {
        $id = $_POST['id'];
        $stmt = $pdo->prepare("SELECT n.*, GROUP_CONCAT(v.user) as destinataires 
                               FROM notifications n 
                               LEFT JOIN vue v ON n.id = v.notification 
                               WHERE n.id = ? 
                               GROUP BY n.id");
        $stmt->execute([$id]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($data) {
            $data['destinataires_list'] = $data['destinataires'] ? explode(',', $data['destinataires']) : [];
            echo json_encode(['success' => true, 'data' => $data]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Notification introuvable']);
        }
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
            $objet = trim($_POST['sai_objet']);
            $titre = trim($_POST['sai_titre']);
            $text = trim($_POST['sai_text']);
            $users = $_POST['sai_users'] ?? [];
            $fichier = null;
            
            if (empty($objet)) throw new Exception('L\'objet est obligatoire.');
            if (empty($titre)) throw new Exception('Le titre est obligatoire.');
            if (empty($text)) throw new Exception('Le message est obligatoire.');
            if (empty($users)) throw new Exception('Veuillez sélectionner au moins un destinataire.');
            
            // Gestion du fichier
            if (!empty($_FILES['sai_fichier']['name'])) {
                $upload_dir = '../uploads/notifications/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                $fichier = time() . '_' . basename($_FILES['sai_fichier']['name']);
                $upload_file = $upload_dir . $fichier;
                if (!move_uploaded_file($_FILES['sai_fichier']['tmp_name'], $upload_file)) {
                    throw new Exception('Erreur lors du téléchargement du fichier.');
                }
            }
            
            // Insertion pour chaque utilisateur sélectionné
            $req = $pdo->prepare("INSERT INTO notifications (objet, titre, text, date, user, fichier) VALUES (?, ?, ?, NOW(), ?, ?)");
            $req_vue = $pdo->prepare("INSERT INTO vue (notification, user, lecture, affichage) VALUES (?, ?, 0, 1)");
            
            foreach ($users as $user_id) {
                $req->execute([$objet, $titre, $text, $user_id, $fichier]);
                $notification_id = $pdo->lastInsertId();
                $req_vue->execute([$notification_id, $user_id]);
            }
            
            $nb_destinataires = count($users);
            $message = "Notification envoyée avec succès à $nb_destinataires utilisateur(s) !";
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        }
        
        // MODIFIER
        if (isset($_POST['btn_modifier'])) {
            $id = $_POST['sai_id'];
            $objet = trim($_POST['sai_objet']);
            $titre = trim($_POST['sai_titre']);
            $text = trim($_POST['sai_text']);
            $users = $_POST['sai_users'] ?? [];
            
            if (empty($id) || empty($objet) || empty($titre) || empty($text) || empty($users)) {
                throw new Exception('Les champs obligatoires doivent être remplis.');
            }
            
            // Gestion du fichier
            $fichier = null;
            if (!empty($_FILES['sai_fichier']['name'])) {
                $upload_dir = '../uploads/notifications/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                $fichier = time() . '_' . basename($_FILES['sai_fichier']['name']);
                $upload_file = $upload_dir . $fichier;
                if (!move_uploaded_file($_FILES['sai_fichier']['tmp_name'], $upload_file)) {
                    throw new Exception('Erreur lors du téléchargement du fichier.');
                }
                
                // Supprimer l'ancien fichier
                $stmt = $pdo->prepare('SELECT fichier FROM notifications WHERE id = ?');
                $stmt->execute([$id]);
                $old_file = $stmt->fetchColumn();
                if ($old_file && file_exists('../uploads/notifications/' . $old_file)) {
                    unlink('../uploads/notifications/' . $old_file);
                }
            }
            
            // Supprimer les anciennes entrées pour cette notification
            $req_vue_delete = $pdo->prepare("DELETE FROM vue WHERE notification = ?");
            $req_vue_delete->execute([$id]);
            
            // Mettre à jour et réattribuer aux nouveaux utilisateurs
            if ($fichier) {
                $req = $pdo->prepare("UPDATE notifications SET objet = ?, titre = ?, text = ?, fichier = ? WHERE id = ?");
                $req->execute([$objet, $titre, $text, $fichier, $id]);
            } else {
                $req = $pdo->prepare("UPDATE notifications SET objet = ?, titre = ?, text = ? WHERE id = ?");
                $req->execute([$objet, $titre, $text, $id]);
            }
            
            // Créer les nouvelles entrées dans vue pour chaque utilisateur
            $req_vue = $pdo->prepare("INSERT INTO vue (notification, user, lecture, affichage) VALUES (?, ?, 0, 1)");
            foreach ($users as $user_id) {
                $req_vue->execute([$id, $user_id]);
            }
            
            $message = 'Notification modifiée avec succès.';
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        }
        
        // SUPPRIMER
        if (isset($_POST['btn_supprimer'])) {
            $id = $_POST['sai_id'];
            
            // Supprimer le fichier associé
            $stmt = $pdo->prepare('SELECT fichier FROM notifications WHERE id = ?');
            $stmt->execute([$id]);
            $fichier = $stmt->fetchColumn();
            if ($fichier && file_exists('../uploads/notifications/' . $fichier)) {
                unlink('../uploads/notifications/' . $fichier);
            }
            
            // Supprimer les entrées dans vue
            $req_vue = $pdo->prepare("DELETE FROM vue WHERE notification = ?");
            $req_vue->execute([$id]);
            
            $req = $pdo->prepare("DELETE FROM notifications WHERE id = ?");
            $req->execute([$id]);
            
            $message = 'Notification supprimée avec succès.';
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        }
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// ================================================================
// 5. DONNÉES
// ================================================================
$utilisateurs = $pdo->query("SELECT utilisateur_id, nom_prenom, email FROM utilisateurs WHERE etat = 'actif' ORDER BY nom_prenom")->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les paramètres GET
$edit_id = isset($_GET['edit']) ? $_GET['edit'] : null;
$edit_notification = null;
$selected_users = [];
$objet = '';
$titre = '';
$text = '';
$fichier = '';
$is_edit = false;

if ($edit_id) {
    $stmt = $pdo->prepare("SELECT n.*, GROUP_CONCAT(v.user) as destinataires 
                           FROM notifications n 
                           LEFT JOIN vue v ON n.id = v.notification 
                           WHERE n.id = ? 
                           GROUP BY n.id");
    $stmt->execute([$edit_id]);
    $edit_notification = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($edit_notification) {
        $is_edit = true;
        $objet = $edit_notification['objet'] ?? '';
        $titre = $edit_notification['titre'] ?? '';
        $text = $edit_notification['text'] ?? '';
        $fichier = $edit_notification['fichier'] ?? '';
        $selected_users = $edit_notification['destinataires'] ? explode(',', $edit_notification['destinataires']) : [];
    }
}

// Suppression via GET
if (isset($_GET['supprimer']) && !empty($_GET['supprimer'])) {
    $id = $_GET['supprimer'];
    
    $stmt = $pdo->prepare('SELECT fichier FROM notifications WHERE id = ?');
    $stmt->execute([$id]);
    $fichier = $stmt->fetchColumn();
    if ($fichier && file_exists('../uploads/notifications/' . $fichier)) {
        unlink('../uploads/notifications/' . $fichier);
    }
    
    $req_vue = $pdo->prepare("DELETE FROM vue WHERE notification = ?");
    $req_vue->execute([$id]);
    
    $req = $pdo->prepare("DELETE FROM notifications WHERE id = ?");
    $req->execute([$id]);
    
    header('Location: ' . $_SERVER['PHP_SELF'] . '?success=supprime');
    exit;
}

// Afficher les messages de succès
if (isset($_GET['success']) && $_GET['success'] === 'supprime') {
    $message = 'Notification supprimée avec succès.';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $is_edit ? 'Modifier' : 'Nouvelle' ?> Notification - Epencia SGI</title>
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
        .header-icon.success { background: var(--success-light); color: var(--success); }
        .header-icon.warning { background: var(--warning-light); color: var(--warning); }
        .header-icon.danger { background: var(--danger-light); color: var(--danger); }

        .card-modern .card-body { padding: 20px; }
        .card-modern .card-footer {
            background: var(--medical-gray-light);
            border-top: 1px solid var(--medical-border);
            padding: 16px 20px;
        }

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
        /* SECTION TITLE                                               */
        /* ============================================================ */
        .section-title {
            font-weight: 700;
            font-size: clamp(13px, 0.85vw, 14px);
            color: var(--medical-text);
            margin-bottom: 12px;
            padding: 8px 12px;
            background: var(--medical-gray-light);
            border-radius: var(--medical-radius-sm);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .section-title i { color: var(--medical-blue); }

        /* ============================================================ */
        /* FORMULAIRE                                                   */
        /* ============================================================ */
        .form-label {
            font-weight: 600;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: var(--medical-text-secondary);
        }

        .form-control, .form-select {
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

        .form-control:focus, .form-select:focus {
            border-color: var(--medical-blue);
            box-shadow: 0 0 0 4px rgba(26, 107, 138, 0.1);
            background: #fff;
        }

        .form-control[readonly] {
            background: var(--medical-gray);
            cursor: not-allowed;
        }

        textarea.form-control {
            height: auto;
            min-height: 120px;
            resize: vertical;
        }

        .required:after {
            content: " *";
            color: var(--danger);
        }

        /* ============================================================ */
        /* FICHIER INFO                                                 */
        /* ============================================================ */
        .fichier-info {
            background: var(--medical-blue-light);
            border-left: 4px solid var(--medical-blue);
            padding: 12px 16px;
            border-radius: var(--medical-radius-sm);
            margin-top: 8px;
        }

        .fichier-info strong {
            display: block;
            color: var(--medical-blue);
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            margin-bottom: 4px;
        }

        /* ============================================================ */
        /* DESTINATAIRES TAGS                                           */
        /* ============================================================ */
        .destinataires-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-top: 8px;
        }

        .destinataire-tag {
            background: var(--medical-blue-light);
            color: var(--medical-blue);
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .destinataire-tag i {
            font-size: 0.7rem;
        }

        /* ============================================================ */
        /* SELECT2 OVERRIDE                                            */
        /* ============================================================ */
        .select2-container--default .select2-selection--multiple {
            border-color: var(--medical-border) !important;
            border-radius: var(--medical-radius-sm) !important;
            min-height: 42px !important;
            background: var(--medical-gray-light) !important;
            border-width: 1.5px !important;
        }

        .select2-container--default .select2-selection--multiple .select2-selection__choice {
            background: var(--medical-blue) !important;
            color: white !important;
            border: none !important;
            border-radius: 4px !important;
            padding: 2px 10px !important;
            font-size: 12px !important;
            font-family: var(--font-primary) !important;
        }

        .select2-container--default .select2-selection--multiple .select2-selection__choice__remove {
            color: white !important;
            margin-right: 4px !important;
        }

        .select2-container--default .select2-selection--multiple .select2-selection__choice__remove:hover {
            color: var(--warning) !important;
        }

        .select2-container--default .select2-selection--multiple .select2-selection__rendered {
            padding: 4px 8px !important;
        }

        .select2-container--default.select2-container--focus .select2-selection--multiple {
            border-color: var(--medical-blue) !important;
            box-shadow: 0 0 0 4px rgba(26, 107, 138, 0.1) !important;
            background: #fff !important;
        }

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

        .alert-medical.alert-warning {
            background: var(--warning-light);
            color: var(--warning);
            border-left-color: var(--warning);
        }

        /* ============================================================ */
        /* SIDEBAR INFO                                                */
        /* ============================================================ */
        .sidebar-info {
            position: sticky;
            top: 24px;
        }

        .sidebar-info .info-item {
            display: flex;
            align-items: flex-start;
            gap: 14px;
            padding: 10px 0;
            border-bottom: 1px solid var(--medical-border);
        }

        .sidebar-info .info-item:last-child {
            border-bottom: none;
        }

        .sidebar-info .info-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            flex-shrink: 0;
        }

        .sidebar-info .info-icon.blue { background: var(--medical-blue-light); color: var(--medical-blue); }
        .sidebar-info .info-icon.teal { background: var(--medical-teal-light); color: var(--medical-teal); }
        .sidebar-info .info-icon.danger { background: var(--danger-light); color: var(--danger); }

        .sidebar-info .info-content strong {
            display: block;
            font-size: 13px;
            color: var(--medical-text);
        }

        .sidebar-info .info-content p {
            color: var(--medical-text-muted);
            font-size: 12px;
            margin: 0;
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
            .btn-medical-primary, .btn-medical-secondary, .btn-medical-danger, .btn-medical-outline {
                padding: 6px 12px !important;
                font-size: 0.75rem !important;
                min-height: 34px !important;
                gap: 4px !important;
            }
            .btn-sm-medical { padding: 4px 8px !important; font-size: 0.7rem !important; min-height: 28px !important; }
            .btn-back-adaptive { padding: 10px 0 !important; width: 42px !important; height: 42px !important; border-radius: 50% !important; min-height: 42px !important; }
            .btn-back-adaptive .btn-label { display: none !important; }
            .btn-back-adaptive .btn-icon { font-size: 18px !important; }
            .sidebar-info { position: static; margin-top: 20px; }
        }

        @media (max-width: 576px) {
            body { padding: 6px; }
            .page-header { padding: 12px 14px; }
            .page-header h1 { font-size: 18px; }
            .header-actions { flex-direction: column; width: 100%; gap: 6px; }
            .header-actions .btn-medical-primary, .header-actions .btn-back-adaptive {
                width: 100% !important;
                justify-content: center !important;
                font-size: 0.75rem !important;
                min-height: 32px !important;
                padding: 4px 10px !important;
            }
            .header-actions .btn-medical-primary span { display: none !important; }
            .header-actions .btn-back-adaptive .btn-label { display: none !important; }
            .btn-back-adaptive { border-radius: var(--medical-radius-sm) !important; width: 100% !important; height: 32px !important; padding: 4px 10px !important; }
            .btn-medical-primary, .btn-medical-secondary, .btn-medical-danger, .btn-medical-outline {
                padding: 4px 8px !important;
                font-size: 0.65rem !important;
                min-height: 28px !important;
                gap: 3px !important;
            }
            .btn-medical-primary i, .btn-medical-secondary i, .btn-medical-danger i, .btn-medical-outline i { font-size: 0.75rem !important; }
            .btn-sm-medical { padding: 3px 6px !important; font-size: 0.6rem !important; min-height: 24px !important; }
            .modal-footer .btn-medical-primary, .modal-footer .btn-medical-secondary, .modal-footer .btn-medical-danger, .modal-footer .btn-medical-outline {
                padding: 4px 8px !important;
                font-size: 0.6rem !important;
                min-height: 26px !important;
            }
            .card-modern .card-header { padding: 10px 12px; }
            .card-modern .card-header h5 { font-size: 12px; }
            .card-modern .card-body { padding: 15px; }
            .card-modern .card-footer { padding: 12px 16px; }
            .form-control, .form-select { font-size: 0.8rem; padding: 6px 10px; height: 36px; }
            textarea.form-control { min-height: 80px; }
            .section-title { font-size: 12px; padding: 6px 10px; }
        }

        @media (max-width: 400px) {
            .page-header h1 { font-size: 16px; }
            .btn-medical-primary, .btn-medical-secondary, .btn-medical-danger, .btn-medical-outline {
                padding: 3px 6px !important;
                font-size: 0.55rem !important;
                min-height: 24px !important;
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
            <h1><?= $is_edit ? 'Modifier la' : 'Nouvelle' ?> <span class="highlight">notification</span></h1>
            <div class="subtitle">
                <i class="bi bi-bell"></i>
                <?= $is_edit ? 'Modifier les informations de la notification' : 'Envoyer une notification à un ou plusieurs utilisateurs' ?>
                <span class="dot"></span>
                <i class="bi bi-person-circle"></i> <?php echo htmlspecialchars($user_nom); ?>
                <span class="dot"></span>
                <?php echo getRoleBadge($user_role); ?>
            </div>
        </div>
        <div class="header-actions">
            <a href="recherche_notification.php" class="btn-back-adaptive" title="Retour à la liste">
                <span class="btn-icon"><i class="bi bi-arrow-left"></i></span>
                <span class="btn-label long">Retour à la liste</span>
                <span class="btn-label short">Retour</span>
            </a>
        </div>
    </header>

    <!-- ============================================================ -->
    <!-- MESSAGES                                                     -->
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
    <!-- FORMULAIRE                                                   -->
    <!-- ============================================================ -->
    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card-modern fade-in">
                <div class="card-header">
                    <h5>
                        <span class="header-icon blue"><i class="bi bi-envelope"></i></span>
                        <?= $is_edit ? 'Modifier la notification' : 'Nouvelle notification' ?>
                    </h5>
                    <?php if ($is_edit): ?>
                        <span class="badge bg-secondary">ID: <?= htmlspecialchars($edit_id) ?></span>
                    <?php endif; ?>
                </div>
                <form method="POST" id="notificationForm" enctype="multipart/form-data">
                    <div class="card-body">
                        <?php if ($is_edit): ?>
                            <input type="hidden" name="sai_id" value="<?= htmlspecialchars($edit_id) ?>">
                        <?php endif; ?>
                        
                        <div class="section-title"><i class="bi bi-envelope-paper"></i> Informations</div>
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="form-label">Objet <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="sai_objet" 
                                       value="<?= htmlspecialchars($objet) ?>" required maxlength="100"
                                       placeholder="Ex: Rapport mensuel">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Titre <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="sai_titre" 
                                       value="<?= htmlspecialchars($titre) ?>" required maxlength="200"
                                       placeholder="Ex: Rapport du mois de Janvier">
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">Message <span class="text-danger">*</span></label>
                                <textarea class="form-control" name="sai_text" rows="5" required
                                          placeholder="Contenu de la notification..."><?= htmlspecialchars($text) ?></textarea>
                            </div>
                        </div>

                        <div class="section-title"><i class="bi bi-people"></i> Destinataires</div>
                        <div class="row g-3 mb-4">
                            <div class="col-md-12">
                                <label class="form-label">Sélectionner les destinataires <span class="text-danger">*</span></label>
                                <select name="sai_users[]" id="sai_users" class="form-select" multiple="multiple" required style="width:100%;">
                                    <?php foreach ($utilisateurs as $u): ?>
                                        <option value="<?= htmlspecialchars($u['utilisateur_id']) ?>" 
                                            <?= in_array($u['utilisateur_id'], $selected_users) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($u['nom_prenom']) ?> (<?= htmlspecialchars($u['email'] ?? '') ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="mt-2">
                                    <small class="text-muted"><i class="bi bi-info-circle"></i> Vous pouvez sélectionner plusieurs destinataires</small>
                                </div>
                                <?php if ($is_edit && !empty($selected_users)): ?>
                                    <div class="destinataires-tags">
                                        <span class="text-muted small me-2">Destinataires actuels :</span>
                                        <?php foreach ($selected_users as $uid): 
                                            $user = array_filter($utilisateurs, function($u) use ($uid) { return $u['utilisateur_id'] == $uid; });
                                            $user = reset($user);
                                        ?>
                                            <span class="destinataire-tag"><i class="bi bi-person-circle"></i> <?= htmlspecialchars($user['nom_prenom'] ?? $uid) ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="section-title"><i class="bi bi-paperclip"></i> Fichier joint</div>
                        <div class="row g-3">
                            <div class="col-md-12">
                                <label class="form-label">Fichier (optionnel)</label>
                                <input type="file" class="form-control" name="sai_fichier" id="sai_fichier">
                                <?php if ($is_edit && $fichier): ?>
                                    <div class="fichier-info">
                                        <strong><i class="bi bi-file-earmark me-1"></i> Fichier actuel</strong>
                                        <div class="d-flex flex-wrap gap-2 mt-1">
                                            <span style="font-family:monospace;font-size:13px;color:var(--medical-text);"><?= htmlspecialchars($fichier) ?></span>
                                            <a href="../uploads/notifications/<?= htmlspecialchars($fichier) ?>" target="_blank" class="btn-medical-outline btn-sm-medical" style="padding:4px 12px;font-size:0.8rem;min-height:28px;">
                                                <i class="bi bi-download me-1"></i>Télécharger
                                            </a>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                <small class="text-muted"><i class="bi bi-info-circle"></i> Taille max : 10 Mo (PDF, DOCX, XLSX, JPG, PNG)</small>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer">
                        <div class="d-flex flex-wrap gap-2 justify-content-end">
                            <?php if ($is_edit): ?>
                                <button type="submit" name="btn_modifier" class="btn-medical-primary">
                                    <i class="bi bi-save"></i> Modifier
                                </button>
                                <button type="submit" name="btn_supprimer" class="btn-medical-danger" 
                                        onclick="return confirm('⚠️ Supprimer cette notification ? Cette action est irréversible.')">
                                    <i class="bi bi-trash"></i> Supprimer
                                </button>
                                <a href="enregistrement_notification.php" class="btn-medical-outline">
                                    <i class="bi bi-x-circle"></i> Annuler
                                </a>
                            <?php else: ?>
                                <button type="submit" name="btn_ajouter" class="btn-medical-secondary" id="btnEnvoyer">
                                    <i class="bi bi-send"></i> <span>Envoyer</span>
                                </button>
                                <button type="reset" class="btn-medical-outline">
                                    <i class="bi bi-arrow-counterclockwise"></i> Réinitialiser
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- ============================================================ -->
        <!-- SIDEBAR INFO                                                -->
        <!-- ============================================================ -->
        <div class="col-lg-4">
            <div class="card-modern fade-in fade-in-d1 sidebar-info">
                <div class="card-header">
                    <h5>
                        <span class="header-icon info"><i class="bi bi-info-circle"></i></span>
                        Informations
                    </h5>
                </div>
                <div class="card-body">
                    <div class="info-item">
                        <div class="info-icon blue"><i class="bi bi-envelope"></i></div>
                        <div class="info-content">
                            <strong>Envoi multiple</strong>
                            <p>Envoyez à plusieurs destinataires en une seule fois</p>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="info-icon teal"><i class="bi bi-paperclip"></i></div>
                        <div class="info-content">
                            <strong>Fichier joint optionnel</strong>
                            <p>Vous pouvez joindre un document à la notification</p>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="info-icon danger"><i class="bi bi-eye"></i></div>
                        <div class="info-content">
                            <strong>Suivi individuel</strong>
                            <p>Chaque destinataire aura son propre suivi de lecture</p>
                        </div>
                    </div>

                    <hr style="border-color:var(--medical-border);margin:16px 0;">

                    <div class="alert-medical alert-info" style="font-size:13px;">
                        <i class="bi bi-info-circle me-2"></i>
                        <strong>Conseil :</strong> Utilisez la sélection multiple pour choisir plusieurs destinataires.
                    </div>

                    <?php if ($is_edit): ?>
                        <div class="alert-medical alert-warning" style="font-size:13px;margin-top:12px;">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            <strong>Modification :</strong> Les destinataires actuels seront remplacés par ceux que vous sélectionnez.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
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
    $('#sai_users').select2({
        theme: 'default',
        width: '100%',
        placeholder: 'Sélectionnez les destinataires...',
        allowClear: true,
        closeOnSelect: false
    });

    // ============================================================
    // MISE À JOUR DU BOUTON D'ENVOI
    // ============================================================
    $('#sai_users').on('change', function() {
        var count = $(this).val() ? $(this).val().length : 0;
        var btn = $('#btnEnvoyer');
        if (count > 0) {
            btn.html('<i class="bi bi-send me-2"></i>Envoyer à ' + count + ' destinataire' + (count > 1 ? 's' : ''));
        } else {
            btn.html('<i class="bi bi-send me-2"></i>Envoyer');
        }
    });

    // ============================================================
    // VALIDATION DU FICHIER
    // ============================================================
    $('#sai_fichier').on('change', function() {
        const file = this.files[0];
        if (file) {
            const ext = file.name.split('.').pop().toLowerCase();
            const allowed = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'gif'];
            if (!allowed.includes(ext)) {
                alert('Format de fichier non autorisé. Formats acceptés : PDF, DOC, DOCX, XLS, XLSX, JPG, PNG, GIF');
                this.value = '';
            }
            if (file.size > 10485760) {
                alert('Le fichier ne doit pas dépasser 10 Mo.');
                this.value = '';
            }
        }
    });

    // ============================================================
    // VALIDATION DES DESTINATAIRES
    // ============================================================
    $('#notificationForm').on('submit', function(e) {
        var selected = $('#sai_users').val();
        if (!selected || selected.length === 0) {
            e.preventDefault();
            alert('⚠️ Veuillez sélectionner au moins un destinataire.');
            $('#sai_users').next('.select2-container').find('.select2-selection').css('border-color', 'var(--danger)');
            setTimeout(function() {
                $('#sai_users').next('.select2-container').find('.select2-selection').css('border-color', '');
            }, 3000);
            return false;
        }
        return true;
    });

    // ============================================================
    // OUVERTURE AUTOMATIQUE POUR MODIFICATION
    // ============================================================
    <?php if ($is_edit): ?>
    setTimeout(function() {
        $('#sai_users').trigger('change');
    }, 100);
    <?php endif; ?>
});
</script>

</body>
</html>