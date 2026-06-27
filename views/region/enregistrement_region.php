<?php
require_once 'database/database.php';


// ============================================================
// HANDLERS AJAX
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');
    
    if ($_POST['ajax_action'] === 'generate_id') {
        $year = date('y');
        $stmt = $pdo->prepare("SELECT id_region FROM region WHERE id_region LIKE ? ORDER BY id_region DESC LIMIT 1");
        $stmt->execute(['REG' . $year . '%']);
        $last = $stmt->fetchColumn();
        $num = $last ? str_pad(intval(substr($last, -4)) + 1, 4, '0', STR_PAD_LEFT) : '0001';
        $id_region = 'REG' . $year . $num;
        echo json_encode(['success' => true, 'id_region' => $id_region]);
        exit;
    }
}

// ============================================================
// TRAITEMENT DES ACTIONS CRUD
// ============================================================
$message = '';
$error = '';
$edit_region = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        
        // === AJOUTER ===
        if (isset($_POST['btn_ajouter'])) {
            $id_region = trim($_POST['sai_id_region']);
            $titre_region = trim($_POST['sai_titre_region']);
            $etat_region = trim($_POST['sai_etat_region']);
            
            if (empty($id_region)) throw new Exception('L\'ID région est obligatoire.');
            if (empty($titre_region)) throw new Exception('Le titre région est obligatoire.');
            
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM region WHERE id_region = ?');
            $stmt->execute([$id_region]);
            if ($stmt->fetchColumn() > 0) throw new Exception('Cet ID de région est déjà utilisé.');
            
            $req = $pdo->prepare("INSERT INTO region (id_region, titre_region, etat_region) VALUES (?, ?, ?)");
            $req->execute([$id_region, $titre_region, $etat_region]);
            
            $message = "Région <strong>$titre_region</strong> créée avec succès !";
            unset($edit_region);
        }
        
        // === MODIFIER ===
        if (isset($_POST['btn_modifier'])) {
            $id_region = $_POST['sai_id_region'];
            $titre_region = trim($_POST['sai_titre_region']);
            $etat_region = trim($_POST['sai_etat_region']);
            
            if (empty($id_region) || empty($titre_region)) {
                throw new Exception('Les champs obligatoires doivent être remplis.');
            }
            
            $req = $pdo->prepare("UPDATE region SET titre_region = ?, etat_region = ? WHERE id_region = ?");
            $req->execute([$titre_region, $etat_region, $id_region]);
            
            $message = 'Région modifiée avec succès.';
            unset($edit_region);
        }
        
        // === SUPPRIMER ===
        if (isset($_POST['btn_supprimer'])) {
            $id_region = $_POST['sai_id_region'];
            
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM district WHERE id_region = ?');
            $stmt->execute([$id_region]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception("Cette région est utilisée dans des districts.");
            }
            
            $req = $pdo->prepare("DELETE FROM region WHERE id_region = ?");
            $req->execute([$id_region]);
            
            $message = 'Région supprimée avec succès.';
            unset($edit_region);
        }
        
        // === CHARGER POUR MODIFICATION ===
        if (isset($_GET['edit']) && !empty($_GET['edit'])) {
            $stmt = $pdo->prepare("SELECT * FROM region WHERE id_region = ?");
            $stmt->execute([$_GET['edit']]);
            $edit_region = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        
        // === SUPPRESSION VIA GET ===
        if (isset($_GET['supprimer']) && !empty($_GET['supprimer'])) {
            $id_region = $_GET['supprimer'];
            
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM district WHERE id_region = ?');
            $stmt->execute([$id_region]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception("Cette région est utilisée dans des districts.");
            }
            
            $req = $pdo->prepare("DELETE FROM region WHERE id_region = ?");
            $req->execute([$id_region]);
            
            header('Location: recherche_region.php?success=supprime');
            exit;
        }
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// === CHARGER POUR MODIFICATION VIA GET ===
if (isset($_GET['edit']) && !empty($_GET['edit']) && !$edit_region) {
    $stmt = $pdo->prepare("SELECT * FROM region WHERE id_region = ?");
    $stmt->execute([$_GET['edit']]);
    $edit_region = $stmt->fetch(PDO::FETCH_ASSOC);
}

// === GÉNÉRER ID PAR DÉFAUT ===
$year = date('y');
$stmt = $pdo->prepare("SELECT id_region FROM region WHERE id_region LIKE ? ORDER BY id_region DESC LIMIT 1");
$stmt->execute(['REG' . $year . '%']);
$last = $stmt->fetchColumn();
$num = $last ? str_pad(intval(substr($last, -4)) + 1, 4, '0', STR_PAD_LEFT) : '0001';
$generated_id = 'REG' . $year . $num;

// === VALEURS PAR DÉFAUT ===
$id_region = $edit_region['id_region'] ?? $generated_id;
$titre_region = $edit_region['titre_region'] ?? '';
$etat_region = $edit_region['etat_region'] ?? 'ACTIF';
$is_edit = ($edit_region !== null);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $is_edit ? 'Modifier' : 'Ajouter' ?> une Région</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
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
        .badge-active { background: #198754; color: white; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; }
        .badge-inactive { background: #dc3545; color: white; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; }
        .info-region { background: #e8f4fd; border-left: 4px solid #0a4d8c; padding: 12px 15px; border-radius: 6px; font-size: 0.85rem; }
        .info-region strong { display: block; margin-bottom: 5px; color: #0a4d8c; font-size: 0.8rem; text-transform: uppercase; }
    </style>
</head>
<body>

<div class="container my-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-0"><?= $is_edit ? '✏️ Modifier la région' : '📝 Ajouter une région' ?></h4>
            <div class="text-muted small"><?= $is_edit ? 'Modifier les informations de la région' : 'Créer une nouvelle région' ?></div>
        </div>
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
                <form method="POST" id="regionForm">
                    <div class="row g-3">
                        <div class="col-12">
                            <div class="section-title"><i class="bi bi-hash me-2"></i>Identification</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label required">ID Région</label>
                            <div class="code-input-group">
                                <input type="text" class="form-control" id="sai_id_region" name="sai_id_region" 
                                       value="<?= htmlspecialchars($id_region) ?>" required maxlength="20"
                                       <?= $is_edit ? 'readonly' : '' ?>>
                                <?php if (!$is_edit): ?>
                                    <button type="button" id="generateIdBtn" class="btn-generate-id">
                                        <i class="bi bi-arrow-repeat"></i>
                                    </button>
                                <?php endif; ?>
                            </div>
                            <small class="text-muted"><i class="bi bi-info-circle"></i> Format: REG + Année + 4 chiffres</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label required">Nom de la région</label>
                            <input type="text" class="form-control" id="sai_titre_region" name="sai_titre_region" 
                                   value="<?= htmlspecialchars($titre_region) ?>" required maxlength="100"
                                   placeholder="Ex: Abidjan, Yamoussoukro...">
                        </div>
                        
                        <div class="col-12 mt-2">
                            <div class="section-title"><i class="bi bi-toggle-on me-2"></i>Statut</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label required">État de la région</label>
                            <select name="sai_etat_region" id="sai_etat_region" class="form-select" required>
                                <option value="ACTIF" <?= ($etat_region == 'ACTIF') ? 'selected' : '' ?>>ACTIF</option>
                                <option value="INACTIF" <?= ($etat_region == 'INACTIF') ? 'selected' : '' ?>>INACTIF</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-center gap-2 mt-4">
                        <?php if ($is_edit): ?>
                            <button type="submit" name="btn_modifier" class="btn btn-temenos px-4">
                                <i class="bi bi-save me-2"></i>Modifier
                            </button>
                            <button type="submit" name="btn_supprimer" class="btn btn-danger-cbs px-4" 
                                    onclick="return confirm('⚠️ Supprimer cette région ? Cette action est irréversible.')">
                                <i class="bi bi-trash me-2"></i>Supprimer
                            </button>
                            <a href="enregistrement_region.php" class="btn btn-outline-cbs px-4">
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
                            <i class="bi bi-globe text-primary"></i>
                        </div>
                        <div>
                            <strong>Création de région</strong>
                            <p class="text-muted small mb-0">L'ID est généré automatiquement au format REG + Année + 4 chiffres</p>
                        </div>
                    </div>
                    <div class="d-flex align-items-center">
                        <div class="bg-danger bg-opacity-10 rounded-circle p-2 me-2">
                            <i class="bi bi-toggle-on text-danger"></i>
                        </div>
                        <div>
                            <strong>Statut actif/inactif</strong>
                            <p class="text-muted small mb-0">Contrôle la disponibilité de la région</p>
                        </div>
                    </div>
                </div>
                
                <hr>
                
                <?php if ($is_edit): ?>
                <div class="info-region">
                    <strong><i class="bi bi-shield me-1"></i> Statut actuel</strong>
                    <span class="badge-<?= strtolower($etat_region) ?>"><?= $etat_region ?></span>
                </div>
                <div class="info-region mt-2">
                    <strong><i class="bi bi-building me-1"></i> Districts associés</strong>
                    <?php
                    $stmt = $pdo->prepare('SELECT COUNT(*) FROM district WHERE id_region = ?');
                    $stmt->execute([$id_region]);
                    echo $stmt->fetchColumn();
                    ?>
                </div>
                <?php endif; ?>
                
                <div class="alert alert-warning small mt-3 mb-0">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    <strong>Suppression :</strong> Impossible si la région a des districts associés.
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    <?php if (!$is_edit): ?>
    $('#generateIdBtn').click(function() {
        const btn = $(this);
        btn.html('<span class="loading-spinner"></span>').prop('disabled', true);
        
        $.post(window.location.href, { ajax_action: 'generate_id' }, function(data) {
            if (data.success) {
                $('#sai_id_region').val(data.id_region);
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