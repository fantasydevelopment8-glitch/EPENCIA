<?php
require_once 'database/database.php';


// ============================================================
// HANDLERS AJAX
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');
    
    if ($_POST['ajax_action'] === 'generate_id') {
        $year = date('y');
        $stmt = $pdo->prepare("SELECT id FROM utilisateur_projet WHERE id LIKE ? ORDER BY id DESC LIMIT 1");
        $stmt->execute(['UP' . $year . '%']);
        $last = $stmt->fetchColumn();
        $num = $last ? str_pad(intval(substr($last, -4)) + 1, 4, '0', STR_PAD_LEFT) : '0001';
        $id = 'UP' . $year . $num;
        echo json_encode(['success' => true, 'id' => $id]);
        exit;
    }
}

// ============================================================
// TRAITEMENT DES ACTIONS CRUD
// ============================================================
$message = '';
$error = '';
$edit_affectation = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        
        // === AJOUTER ===
        if (isset($_POST['btn_ajouter'])) {
            $id = trim($_POST['sai_id']);
            $utilisateur = trim($_POST['sai_utilisateur']);
            $projet = trim($_POST['sai_projet']);
            $zone = trim($_POST['sai_zone']);
            $localite = trim($_POST['sai_localite']);
            
            if (empty($id)) throw new Exception('L\'ID est obligatoire.');
            if (empty($utilisateur)) throw new Exception('L\'utilisateur est obligatoire.');
            if (empty($projet)) throw new Exception('Le projet est obligatoire.');
            if (empty($zone)) throw new Exception('La zone est obligatoire.');
            if (empty($localite)) throw new Exception('La localité est obligatoire.');
            
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM utilisateur_projet WHERE id = ?');
            $stmt->execute([$id]);
            if ($stmt->fetchColumn() > 0) throw new Exception('Cet ID est déjà utilisé.');
            
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM utilisateur_projet WHERE utilisateur = ? AND projet = ?');
            $stmt->execute([$utilisateur, $projet]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception('Cet utilisateur est déjà associé à ce projet.');
            }
            
            $req = $pdo->prepare("INSERT INTO utilisateur_projet (id, utilisateur, projet, zone, localite) VALUES (?, ?, ?, ?, ?)");
            $req->execute([$id, $utilisateur, $projet, $zone, $localite]);
            
            $message = "Association utilisateur/projet créée avec succès !";
            unset($edit_affectation);
        }
        
        // === MODIFIER ===
        if (isset($_POST['btn_modifier'])) {
            $id = $_POST['sai_id'];
            $utilisateur = trim($_POST['sai_utilisateur']);
            $projet = trim($_POST['sai_projet']);
            $zone = trim($_POST['sai_zone']);
            $localite = trim($_POST['sai_localite']);
            
            if (empty($id) || empty($utilisateur) || empty($projet) || empty($zone) || empty($localite)) {
                throw new Exception('Les champs obligatoires doivent être remplis.');
            }
            
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM utilisateur_projet WHERE utilisateur = ? AND projet = ? AND id != ?');
            $stmt->execute([$utilisateur, $projet, $id]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception('Cet utilisateur est déjà associé à ce projet.');
            }
            
            $req = $pdo->prepare("UPDATE utilisateur_projet SET utilisateur = ?, projet = ?, zone = ?, localite = ? WHERE id = ?");
            $req->execute([$utilisateur, $projet, $zone, $localite, $id]);
            
            $message = 'Association utilisateur/projet modifiée avec succès.';
            unset($edit_affectation);
        }
        
        // === SUPPRIMER ===
        if (isset($_POST['btn_supprimer'])) {
            $id = $_POST['sai_id'];
            
            $req = $pdo->prepare("DELETE FROM utilisateur_projet WHERE id = ?");
            $req->execute([$id]);
            
            $message = 'Association utilisateur/projet supprimée avec succès.';
            unset($edit_affectation);
        }
        
        // === CHARGER POUR MODIFICATION ===
        if (isset($_GET['edit']) && !empty($_GET['edit'])) {
            $stmt = $pdo->prepare("SELECT * FROM utilisateur_projet WHERE id = ?");
            $stmt->execute([$_GET['edit']]);
            $edit_affectation = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        
        // === SUPPRESSION VIA GET ===
        if (isset($_GET['supprimer']) && !empty($_GET['supprimer'])) {
            $id = $_GET['supprimer'];
            
            $req = $pdo->prepare("DELETE FROM utilisateur_projet WHERE id = ?");
            $req->execute([$id]);
            
            header('Location: recherche_utilisateur_projet.php?success=supprime');
            exit;
        }
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// === CHARGER POUR MODIFICATION VIA GET ===
if (isset($_GET['edit']) && !empty($_GET['edit']) && !$edit_affectation) {
    $stmt = $pdo->prepare("SELECT * FROM utilisateur_projet WHERE id = ?");
    $stmt->execute([$_GET['edit']]);
    $edit_affectation = $stmt->fetch(PDO::FETCH_ASSOC);
}

// === LISTES ===
$utilisateurs = $pdo->query("SELECT id, nom_prenom FROM utilisateur ORDER BY nom_prenom")->fetchAll(PDO::FETCH_ASSOC);
$projets = $pdo->query("SELECT id_projet, titre_projet FROM projet ORDER BY titre_projet")->fetchAll(PDO::FETCH_ASSOC);

// === GÉNÉRER ID PAR DÉFAUT ===
$year = date('y');
$stmt = $pdo->prepare("SELECT id FROM utilisateur_projet WHERE id LIKE ? ORDER BY id DESC LIMIT 1");
$stmt->execute(['UP' . $year . '%']);
$last = $stmt->fetchColumn();
$num = $last ? str_pad(intval(substr($last, -4)) + 1, 4, '0', STR_PAD_LEFT) : '0001';
$generated_id = 'UP' . $year . $num;

// === VALEURS PAR DÉFAUT ===
$id = $edit_affectation['id'] ?? $generated_id;
$utilisateur = $edit_affectation['utilisateur'] ?? '';
$projet = $edit_affectation['projet'] ?? '';
$zone = $edit_affectation['zone'] ?? '';
$localite = $edit_affectation['localite'] ?? '';
$is_edit = ($edit_affectation !== null);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $is_edit ? 'Modifier' : 'Ajouter' ?> une Association Utilisateur/Projet</title>
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
        .code-input-group { display: flex; gap: 8px; align-items: center; }
        .code-input-group .form-control { flex: 1; font-family: 'Courier New', monospace; font-weight: 600; color: #0a4d8c; }
        .btn-generate-id { white-space: nowrap; background: #0a4d8c; color: white; border: none; padding: 6px 12px; border-radius: 6px; font-size: 0.85rem; }
        .btn-generate-id:hover { background: #05101c; color: white; }
        .loading-spinner { display: inline-block; width: 14px; height: 14px; border: 2px solid #f3f3f3; border-top: 2px solid white; border-radius: 50%; animation: spin 1s linear infinite; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        .select2-container--default .select2-selection--single { border-color: #dee2e6; border-radius: 6px; height: 38px; }
        .select2-container--default .select2-selection--single .select2-selection__rendered { line-height: 38px; }
    </style>
</head>
<body>

<div class="container my-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-0"><?= $is_edit ? '✏️ Modifier l\'association' : '📝 Nouvelle association Utilisateur/Projet' ?></h4>
            <div class="text-muted small"><?= $is_edit ? 'Modifier l\'association utilisateur/projet' : 'Associer un utilisateur à un projet' ?></div>
        </div>
        <a href="recherche_utilisateur_projet.php" class="btn btn-outline-secondary btn-sm">
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
                <form method="POST" id="affectationForm">
                    <div class="row g-3">
                        <div class="col-12">
                            <div class="section-title"><i class="bi bi-hash me-2"></i>Identification</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label required">ID</label>
                            <div class="code-input-group">
                                <input type="text" class="form-control" id="sai_id" name="sai_id" 
                                       value="<?= htmlspecialchars($id) ?>" required maxlength="20"
                                       <?= $is_edit ? 'readonly' : '' ?>>
                                <?php if (!$is_edit): ?>
                                    <button type="button" id="generateIdBtn" class="btn-generate-id">
                                        <i class="bi bi-arrow-repeat"></i>
                                    </button>
                                <?php endif; ?>
                            </div>
                            <small class="text-muted"><i class="bi bi-info-circle"></i> Format: UP + Année + 4 chiffres</small>
                        </div>
                        
                        <div class="col-12 mt-2">
                            <div class="section-title"><i class="bi bi-link me-2"></i>Association</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label required">Utilisateur</label>
                            <select name="sai_utilisateur" id="sai_utilisateur" class="form-select" required style="width:100%;">
                                <option value="">-- Sélectionnez un utilisateur --</option>
                                <?php foreach ($utilisateurs as $u): ?>
                                    <option value="<?= $u['id'] ?>" <?= ($utilisateur == $u['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($u['nom_prenom']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label required">Projet</label>
                            <select name="sai_projet" id="sai_projet" class="form-select" required style="width:100%;">
                                <option value="">-- Sélectionnez un projet --</option>
                                <?php foreach ($projets as $p): ?>
                                    <option value="<?= $p['id_projet'] ?>" <?= ($projet == $p['id_projet']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($p['titre_projet']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-12 mt-2">
                            <div class="section-title"><i class="bi bi-geo-alt me-2"></i>Localisation</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label required">Zone</label>
                            <input type="text" class="form-control" id="sai_zone" name="sai_zone" 
                                   value="<?= htmlspecialchars($zone) ?>" required maxlength="100"
                                   placeholder="Ex: Zone Nord">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label required">Localité</label>
                            <input type="text" class="form-control" id="sai_localite" name="sai_localite" 
                                   value="<?= htmlspecialchars($localite) ?>" required maxlength="100"
                                   placeholder="Ex: Abidjan">
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-center gap-2 mt-4">
                        <?php if ($is_edit): ?>
                            <button type="submit" name="btn_modifier" class="btn btn-temenos px-4">
                                <i class="bi bi-save me-2"></i>Modifier
                            </button>
                            <button type="submit" name="btn_supprimer" class="btn btn-danger-cbs px-4" 
                                    onclick="return confirm('⚠️ Supprimer cette association ? Cette action est irréversible.')">
                                <i class="bi bi-trash me-2"></i>Supprimer
                            </button>
                            <a href="enregistrement_utilisateur_projet.php" class="btn btn-outline-cbs px-4">
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
                            <strong>Association Utilisateur/Projet</strong>
                            <p class="text-muted small mb-0">Permet de lier un utilisateur à un projet</p>
                        </div>
                    </div>
                    <div class="d-flex align-items-center mb-2">
                        <div class="bg-success bg-opacity-10 rounded-circle p-2 me-2">
                            <i class="bi bi-person text-success"></i>
                        </div>
                        <div>
                            <strong>Un utilisateur peut avoir plusieurs projets</strong>
                            <p class="text-muted small mb-0">Un utilisateur peut être affecté à plusieurs projets</p>
                        </div>
                    </div>
                    <div class="d-flex align-items-center">
                        <div class="bg-danger bg-opacity-10 rounded-circle p-2 me-2">
                            <i class="bi bi-exclamation-triangle text-danger"></i>
                        </div>
                        <div>
                            <strong>Double association impossible</strong>
                            <p class="text-muted small mb-0">Une même association ne peut pas être créée deux fois</p>
                        </div>
                    </div>
                </div>
                
                <hr>
                
                <div class="alert alert-info small mt-3 mb-0">
                    <i class="bi bi-info-circle me-2"></i>
                    <strong>Zone et Localité :</strong> Permettent de définir le périmètre d'intervention de l'utilisateur pour le projet.
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Select2
    $('#sai_utilisateur').select2({
        theme: 'default',
        width: '100%',
        placeholder: 'Sélectionnez un utilisateur',
        allowClear: true
    });
    
    $('#sai_projet').select2({
        theme: 'default',
        width: '100%',
        placeholder: 'Sélectionnez un projet',
        allowClear: true
    });
    
    <?php if (!$is_edit): ?>
    $('#generateIdBtn').click(function() {
        const btn = $(this);
        btn.html('<span class="loading-spinner"></span>').prop('disabled', true);
        
        $.post(window.location.href, { ajax_action: 'generate_id' }, function(data) {
            if (data.success) {
                $('#sai_id').val(data.id);
            } else {
                alert('Erreur: ' + (data.message || 'Impossible de générer l\'ID'));
            }
        }, 'json').always(function() {
            btn.html('<i class="bi bi-arrow-repeat"></i>').prop('disabled', false);
        });
    });
    <?php endif; ?>
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>