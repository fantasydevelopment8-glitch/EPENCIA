<?php
require_once 'database/database.php';


// ============================================================
// TRAITEMENT DES ACTIONS CRUD
// ============================================================
$message = '';
$error = '';
$edit_vue = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        
        // === AJOUTER ===
        if (isset($_POST['btn_ajouter'])) {
            $notification = trim($_POST['sai_notification']);
            $user = trim($_POST['sai_user']);
            $lecture = trim($_POST['sai_lecture']);
            $affichage = trim($_POST['sai_affichage']);
            
            if (empty($notification)) throw new Exception('La notification est obligatoire.');
            if (empty($user)) throw new Exception('L\'utilisateur est obligatoire.');
            if (empty($lecture) && $lecture !== '0') throw new Exception('Le champ lecture est obligatoire.');
            if (empty($affichage) && $affichage !== '0') throw new Exception('Le champ affichage est obligatoire.');
            
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM vue WHERE notification = ? AND user = ?');
            $stmt->execute([$notification, $user]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception('Cette entrée existe déjà.');
            }
            
            $req = $pdo->prepare("INSERT INTO vue (notification, user, lecture, affichage) VALUES (?, ?, ?, ?)");
            $req->execute([$notification, $user, $lecture, $affichage]);
            
            $message = "Entrée vue créée avec succès !";
            unset($edit_vue);
        }
        
        // === MODIFIER ===
        if (isset($_POST['btn_modifier'])) {
            $id = $_POST['sai_id'];
            $notification = trim($_POST['sai_notification']);
            $user = trim($_POST['sai_user']);
            $lecture = trim($_POST['sai_lecture']);
            $affichage = trim($_POST['sai_affichage']);
            
            if (empty($id) || empty($notification) || empty($user) || (empty($lecture) && $lecture !== '0') || (empty($affichage) && $affichage !== '0')) {
                throw new Exception('Les champs obligatoires doivent être remplis.');
            }
            
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM vue WHERE notification = ? AND user = ? AND id != ?');
            $stmt->execute([$notification, $user, $id]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception('Cette entrée existe déjà.');
            }
            
            $req = $pdo->prepare("UPDATE vue SET notification = ?, user = ?, lecture = ?, affichage = ? WHERE id = ?");
            $req->execute([$notification, $user, $lecture, $affichage, $id]);
            
            $message = 'Entrée vue modifiée avec succès.';
            unset($edit_vue);
        }
        
        // === SUPPRIMER ===
        if (isset($_POST['btn_supprimer'])) {
            $id = $_POST['sai_id'];
            
            $req = $pdo->prepare("DELETE FROM vue WHERE id = ?");
            $req->execute([$id]);
            
            $message = 'Entrée vue supprimée avec succès.';
            unset($edit_vue);
        }
        
        // === CHARGER POUR MODIFICATION ===
        if (isset($_GET['edit']) && !empty($_GET['edit'])) {
            $stmt = $pdo->prepare("SELECT * FROM vue WHERE id = ?");
            $stmt->execute([$_GET['edit']]);
            $edit_vue = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        
        // === SUPPRESSION VIA GET ===
        if (isset($_GET['supprimer']) && !empty($_GET['supprimer'])) {
            $id = $_GET['supprimer'];
            
            $req = $pdo->prepare("DELETE FROM vue WHERE id = ?");
            $req->execute([$id]);
            
            header('Location: recherche_vue.php?success=supprime');
            exit;
        }
        
        // === MARQUER COMME LU ===
        if (isset($_GET['lu']) && !empty($_GET['lu'])) {
            $id = $_GET['lu'];
            
            $req = $pdo->prepare("UPDATE vue SET lecture = 1 WHERE id = ?");
            $req->execute([$id]);
            
            header('Location: recherche_vue.php?success=lu');
            exit;
        }
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// === CHARGER POUR MODIFICATION VIA GET ===
if (isset($_GET['edit']) && !empty($_GET['edit']) && !$edit_vue) {
    $stmt = $pdo->prepare("SELECT * FROM vue WHERE id = ?");
    $stmt->execute([$_GET['edit']]);
    $edit_vue = $stmt->fetch(PDO::FETCH_ASSOC);
}

// === LISTES ===
$notifications = $pdo->query("SELECT id, titre FROM notifications ORDER BY titre")->fetchAll(PDO::FETCH_ASSOC);
$utilisateurs = $pdo->query("SELECT id, nom_prenom FROM utilisateur ORDER BY nom_prenom")->fetchAll(PDO::FETCH_ASSOC);

// === VALEURS PAR DÉFAUT ===
$id = $edit_vue['id'] ?? '';
$notification = $edit_vue['notification'] ?? '';
$user = $edit_vue['user'] ?? '';
$lecture = $edit_vue['lecture'] ?? 0;
$affichage = $edit_vue['affichage'] ?? 1;
$is_edit = ($edit_vue !== null);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $is_edit ? 'Modifier' : 'Ajouter' ?> une Vue</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <style>
        body { background-color: #f4f6f9; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .card { border: none; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .card-title-cbs { color: #0a4d8c; font-weight: 600; border-bottom: 2px solid #e9ecef; padding-bottom: 10px; }
        .form-label { font-weight: 500; color: #495057; font-size: 0.85rem; margin-bottom: 0.25rem; }
        .form-control:focus, .form-select:focus { border-color: #0a4d8c; box-shadow: 0 0 0 0.25rem rgba(13, 35, 58, 0.25); }
        .btn-temenos { background-color: #0a4d8c; color: white; font-weight: 600; }
        .btn-temenos:hover { background-color: #05101c; color: white; }
        .btn-success-cbs { background: linear-gradient(135deg, #198754 0%, #157347 100%); color: white; font-weight: 600; border: none; }
        .btn-danger-cbs { background: linear-gradient(135deg, #dc3545 0%, #bb2d3b 100%); color: white; font-weight: 600; border: none; }
        .btn-outline-cbs { background: transparent; color: #0a4d8c; border: 1px solid #0a4d8c; font-weight: 600; }
        .btn-outline-cbs:hover { background: #0a4d8c; color: white; }
        .section-title { font-size: 0.85rem; font-weight: 600; color: #0a4d8c; background: #e9ecef; padding: 8px 12px; border-radius: 6px; margin-bottom: 15px; }
        .required:after { content: " *"; color: #dc3545; }
        .select2-container--default .select2-selection--single { border-color: #dee2e6; border-radius: 6px; height: 38px; }
        .select2-container--default .select2-selection--single .select2-selection__rendered { line-height: 38px; }
    </style>
</head>
<body>

<div class="container my-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-0"><?= $is_edit ? '✏️ Modifier la vue' : '📝 Nouvelle vue' ?></h4>
            <div class="text-muted small"><?= $is_edit ? 'Modifier les informations de la vue' : 'Créer une nouvelle entrée vue' ?></div>
        </div>
        <a href="recherche_vue.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-2"></i>Retour à la liste
        </a>
    </div>
    <hr>

    <?php if ($message): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="bi bi-check-circle-fill me-2"></i> <?= $message ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-lg-8">
            <div class="card p-4">
                <form method="POST" id="vueForm">
                    <?php if ($is_edit): ?>
                        <input type="hidden" name="sai_id" value="<?= htmlspecialchars($id) ?>">
                    <?php endif; ?>
                    
                    <div class="row g-3">
                        <div class="col-12">
                            <div class="section-title"><i class="bi bi-link me-2"></i>Association</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label required">Notification</label>
                            <select name="sai_notification" id="sai_notification" class="form-select" required style="width:100%;">
                                <option value="">-- Sélectionnez une notification --</option>
                                <?php foreach ($notifications as $n): ?>
                                    <option value="<?= $n['id'] ?>" <?= ($notification == $n['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($n['titre']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label required">Utilisateur</label>
                            <select name="sai_user" id="sai_user" class="form-select" required style="width:100%;">
                                <option value="">-- Sélectionnez un utilisateur --</option>
                                <?php foreach ($utilisateurs as $u): ?>
                                    <option value="<?= $u['id'] ?>" <?= ($user == $u['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($u['nom_prenom']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-12 mt-2">
                            <div class="section-title"><i class="bi bi-eye me-2"></i>Statut de lecture</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label required">Lecture</label>
                            <select name="sai_lecture" id="sai_lecture" class="form-select" required>
                                <option value="0" <?= ($lecture == 0) ? 'selected' : '' ?>>Non lu</option>
                                <option value="1" <?= ($lecture == 1) ? 'selected' : '' ?>>Lu</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label required">Affichage</label>
                            <select name="sai_affichage" id="sai_affichage" class="form-select" required>
                                <option value="1" <?= ($affichage == 1) ? 'selected' : '' ?>>Visible</option>
                                <option value="0" <?= ($affichage == 0) ? 'selected' : '' ?>>Masqué</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-center gap-2 mt-4">
                        <?php if ($is_edit): ?>
                            <button type="submit" name="btn_modifier" class="btn btn-temenos px-4">
                                <i class="bi bi-save me-2"></i>Modifier
                            </button>
                            <button type="submit" name="btn_supprimer" class="btn btn-danger-cbs px-4" 
                                    onclick="return confirm('⚠️ Supprimer cette entrée ? Cette action est irréversible.')">
                                <i class="bi bi-trash me-2"></i>Supprimer
                            </button>
                            <a href="enregistrement_vue.php" class="btn btn-outline-cbs px-4">
                                <i class="bi bi-x-circle me-2"></i>Annuler
                            </a>
                        <?php else: ?>
                            <button type="submit" name="btn_ajouter" class="btn btn-success-cbs px-4">
                                <i class="bi bi-save me-2"></i>Ajouter
                            </button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
        
        <div class="col-lg-4">
            <div class="card p-4">
                <h5 class="card-title-cbs mb-3">
                    <i class="bi bi-info-circle me-2 text-primary"></i>Informations
                </h5>
                
                <div class="mb-3">
                    <div class="d-flex align-items-center mb-2">
                        <div class="bg-primary bg-opacity-10 rounded-circle p-2 me-2">
                            <i class="bi bi-link text-primary"></i>
                        </div>
                        <div>
                            <strong>Vue de notification</strong>
                            <p class="text-muted small mb-0">Permet de suivre qui a lu quelle notification</p>
                        </div>
                    </div>
                    <div class="d-flex align-items-center mb-2">
                        <div class="bg-success bg-opacity-10 rounded-circle p-2 me-2">
                            <i class="bi bi-eye text-success"></i>
                        </div>
                        <div>
                            <strong>Lecture</strong>
                            <p class="text-muted small mb-0">Indique si la notification a été lue par l'utilisateur</p>
                        </div>
                    </div>
                    <div class="d-flex align-items-center">
                        <div class="bg-danger bg-opacity-10 rounded-circle p-2 me-2">
                            <i class="bi bi-eye-slash text-danger"></i>
                        </div>
                        <div>
                            <strong>Affichage</strong>
                            <p class="text-muted small mb-0">Contrôle la visibilité de la notification</p>
                        </div>
                    </div>
                </div>
                
                <hr>
                
                <div class="alert alert-info small mt-3 mb-0">
                    <i class="bi bi-info-circle me-2"></i>
                    <strong>Conseil :</strong> Cette table est généralement gérée automatiquement lors de l'envoi de notifications.
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Select2
    $('#sai_notification').select2({
        theme: 'default',
        width: '100%',
        placeholder: 'Sélectionnez une notification',
        allowClear: true
    });
    
    $('#sai_user').select2({
        theme: 'default',
        width: '100%',
        placeholder: 'Sélectionnez un utilisateur',
        allowClear: true
    });
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>