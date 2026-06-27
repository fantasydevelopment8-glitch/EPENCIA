<?php
require_once 'database/database.php';


// ============================================================
// HANDLERS AJAX
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');
    
    if ($_POST['ajax_action'] === 'generate_id') {
        $year = date('y');
        $stmt = $pdo->prepare("SELECT id_tranche FROM tranche_age WHERE id_tranche LIKE ? ORDER BY id_tranche DESC LIMIT 1");
        $stmt->execute(['TRA' . $year . '%']);
        $last = $stmt->fetchColumn();
        $num = $last ? str_pad(intval(substr($last, -4)) + 1, 4, '0', STR_PAD_LEFT) : '0001';
        $id_tranche = 'TRA' . $year . $num;
        echo json_encode(['success' => true, 'id_tranche' => $id_tranche]);
        exit;
    }
}

// ============================================================
// TRAITEMENT DES ACTIONS CRUD
// ============================================================
$message = '';
$error = '';
$edit_tranche = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        
        // === AJOUTER ===
        if (isset($_POST['btn_ajouter'])) {
            $id_tranche = trim($_POST['sai_id_tranche']);
            $age_debut = trim($_POST['sai_age_debut']);
            $age_fin = trim($_POST['sai_age_fin']);
            $titre_debut = trim($_POST['sai_titre_debut']);
            $titre_fin = trim($_POST['sai_titre_fin']);
            $etat_tranche_age = trim($_POST['sai_etat_tranche_age']);
            
            if (empty($id_tranche)) throw new Exception('L\'ID tranche est obligatoire.');
            if (empty($age_debut) && $age_debut !== '0') throw new Exception('L\'âge début est obligatoire.');
            if (empty($age_fin) && $age_fin !== '0') throw new Exception('L\'âge fin est obligatoire.');
            if (empty($titre_debut)) throw new Exception('Le titre début est obligatoire.');
            if (empty($titre_fin)) throw new Exception('Le titre fin est obligatoire.');
            
            if ($age_debut > $age_fin) {
                throw new Exception('L\'âge début doit être inférieur ou égal à l\'âge fin.');
            }
            
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM tranche_age WHERE id_tranche = ?');
            $stmt->execute([$id_tranche]);
            if ($stmt->fetchColumn() > 0) throw new Exception('Cet ID de tranche est déjà utilisé.');
            
            $req = $pdo->prepare("INSERT INTO tranche_age (id_tranche, age_debut, age_fin, titre_debut, titre_fin, etat_tranche_age) VALUES (?, ?, ?, ?, ?, ?)");
            $req->execute([$id_tranche, $age_debut, $age_fin, $titre_debut, $titre_fin, $etat_tranche_age]);
            
            $message = "Tranche d'âge <strong>$titre_debut - $titre_fin</strong> créée avec succès !";
            unset($edit_tranche);
        }
        
        // === MODIFIER ===
        if (isset($_POST['btn_modifier'])) {
            $id_tranche = $_POST['sai_id_tranche'];
            $age_debut = trim($_POST['sai_age_debut']);
            $age_fin = trim($_POST['sai_age_fin']);
            $titre_debut = trim($_POST['sai_titre_debut']);
            $titre_fin = trim($_POST['sai_titre_fin']);
            $etat_tranche_age = trim($_POST['sai_etat_tranche_age']);
            
            if (empty($id_tranche) || (empty($age_debut) && $age_debut !== '0') || (empty($age_fin) && $age_fin !== '0') || empty($titre_debut) || empty($titre_fin)) {
                throw new Exception('Les champs obligatoires doivent être remplis.');
            }
            
            if ($age_debut > $age_fin) {
                throw new Exception('L\'âge début doit être inférieur ou égal à l\'âge fin.');
            }
            
            $req = $pdo->prepare("UPDATE tranche_age SET age_debut = ?, age_fin = ?, titre_debut = ?, titre_fin = ?, etat_tranche_age = ? WHERE id_tranche = ?");
            $req->execute([$age_debut, $age_fin, $titre_debut, $titre_fin, $etat_tranche_age, $id_tranche]);
            
            $message = 'Tranche d\'âge modifiée avec succès.';
            unset($edit_tranche);
        }
        
        // === SUPPRIMER ===
        if (isset($_POST['btn_supprimer'])) {
            $id_tranche = $_POST['sai_id_tranche'];
            
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM donnee WHERE id_tranche = ?');
            $stmt->execute([$id_tranche]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception("Cette tranche d'âge est utilisée dans des données.");
            }
            
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM projet_tranche WHERE id_tranche = ?');
            $stmt->execute([$id_tranche]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception("Cette tranche d'âge est associée à des projets.");
            }
            
            $req = $pdo->prepare("DELETE FROM tranche_age WHERE id_tranche = ?");
            $req->execute([$id_tranche]);
            
            $message = 'Tranche d\'âge supprimée avec succès.';
            unset($edit_tranche);
        }
        
        // === CHARGER POUR MODIFICATION ===
        if (isset($_GET['edit']) && !empty($_GET['edit'])) {
            $stmt = $pdo->prepare("SELECT * FROM tranche_age WHERE id_tranche = ?");
            $stmt->execute([$_GET['edit']]);
            $edit_tranche = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        
        // === SUPPRESSION VIA GET ===
        if (isset($_GET['supprimer']) && !empty($_GET['supprimer'])) {
            $id_tranche = $_GET['supprimer'];
            
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM donnee WHERE id_tranche = ?');
            $stmt->execute([$id_tranche]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception("Cette tranche d'âge est utilisée dans des données.");
            }
            
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM projet_tranche WHERE id_tranche = ?');
            $stmt->execute([$id_tranche]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception("Cette tranche d'âge est associée à des projets.");
            }
            
            $req = $pdo->prepare("DELETE FROM tranche_age WHERE id_tranche = ?");
            $req->execute([$id_tranche]);
            
            header('Location: recherche_tranche_age.php?success=supprime');
            exit;
        }
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// === CHARGER POUR MODIFICATION VIA GET ===
if (isset($_GET['edit']) && !empty($_GET['edit']) && !$edit_tranche) {
    $stmt = $pdo->prepare("SELECT * FROM tranche_age WHERE id_tranche = ?");
    $stmt->execute([$_GET['edit']]);
    $edit_tranche = $stmt->fetch(PDO::FETCH_ASSOC);
}

// === GÉNÉRER ID PAR DÉFAUT ===
$year = date('y');
$stmt = $pdo->prepare("SELECT id_tranche FROM tranche_age WHERE id_tranche LIKE ? ORDER BY id_tranche DESC LIMIT 1");
$stmt->execute(['TRA' . $year . '%']);
$last = $stmt->fetchColumn();
$num = $last ? str_pad(intval(substr($last, -4)) + 1, 4, '0', STR_PAD_LEFT) : '0001';
$generated_id = 'TRA' . $year . $num;

// === VALEURS PAR DÉFAUT ===
$id_tranche = $edit_tranche['id_tranche'] ?? $generated_id;
$age_debut = $edit_tranche['age_debut'] ?? '';
$age_fin = $edit_tranche['age_fin'] ?? '';
$titre_debut = $edit_tranche['titre_debut'] ?? '';
$titre_fin = $edit_tranche['titre_fin'] ?? '';
$etat_tranche_age = $edit_tranche['etat_tranche_age'] ?? 'ACTIF';
$is_edit = ($edit_tranche !== null);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $is_edit ? 'Modifier' : 'Ajouter' ?> une Tranche d'Âge</title>
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
        .info-tranche { background: #e8f4fd; border-left: 4px solid #0a4d8c; padding: 12px 15px; border-radius: 6px; font-size: 0.85rem; }
        .info-tranche strong { display: block; margin-bottom: 5px; color: #0a4d8c; font-size: 0.8rem; text-transform: uppercase; }
    </style>
</head>
<body>

<div class="container my-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-0"><?= $is_edit ? '✏️ Modifier la tranche d\'âge' : '📝 Ajouter une tranche d\'âge' ?></h4>
            <div class="text-muted small"><?= $is_edit ? 'Modifier les informations de la tranche d\'âge' : 'Créer une nouvelle tranche d\'âge' ?></div>
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
                <form method="POST" id="trancheForm">
                    <div class="row g-3">
                        <div class="col-12">
                            <div class="section-title"><i class="bi bi-hash me-2"></i>Identification</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label required">ID Tranche</label>
                            <div class="code-input-group">
                                <input type="text" class="form-control" id="sai_id_tranche" name="sai_id_tranche" 
                                       value="<?= htmlspecialchars($id_tranche) ?>" required maxlength="20"
                                       <?= $is_edit ? 'readonly' : '' ?>>
                                <?php if (!$is_edit): ?>
                                    <button type="button" id="generateIdBtn" class="btn-generate-id">
                                        <i class="bi bi-arrow-repeat"></i>
                                    </button>
                                <?php endif; ?>
                            </div>
                            <small class="text-muted"><i class="bi bi-info-circle"></i> Format: TRA + Année + 4 chiffres</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label required">Tranche d'âge</label>
                            <div class="row g-2">
                                <div class="col-6">
                                    <input type="number" class="form-control" id="sai_age_debut" name="sai_age_debut" 
                                           value="<?= htmlspecialchars($age_debut) ?>" required placeholder="Début">
                                </div>
                                <div class="col-6">
                                    <input type="number" class="form-control" id="sai_age_fin" name="sai_age_fin" 
                                           value="<?= htmlspecialchars($age_fin) ?>" required placeholder="Fin">
                                </div>
                            </div>
                            <small class="text-muted"><i class="bi bi-info-circle"></i> Âge début doit être ≤ Âge fin</small>
                        </div>
                        
                        <div class="col-12 mt-2">
                            <div class="section-title"><i class="bi bi-tag me-2"></i>Libellés</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label required">Libellé Début</label>
                            <input type="text" class="form-control" id="sai_titre_debut" name="sai_titre_debut" 
                                   value="<?= htmlspecialchars($titre_debut) ?>" required maxlength="50"
                                   placeholder="Ex: Enfant, Jeune, Adulte...">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label required">Libellé Fin</label>
                            <input type="text" class="form-control" id="sai_titre_fin" name="sai_titre_fin" 
                                   value="<?= htmlspecialchars($titre_fin) ?>" required maxlength="50"
                                   placeholder="Ex: Enfant, Jeune, Adulte...">
                        </div>
                        
                        <div class="col-12 mt-2">
                            <div class="section-title"><i class="bi bi-toggle-on me-2"></i>Statut</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label required">État de la tranche</label>
                            <select name="sai_etat_tranche_age" id="sai_etat_tranche_age" class="form-select" required>
                                <option value="ACTIF" <?= ($etat_tranche_age == 'ACTIF') ? 'selected' : '' ?>>ACTIF</option>
                                <option value="INACTIF" <?= ($etat_tranche_age == 'INACTIF') ? 'selected' : '' ?>>INACTIF</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-center gap-2 mt-4">
                        <?php if ($is_edit): ?>
                            <button type="submit" name="btn_modifier" class="btn btn-temenos px-4">
                                <i class="bi bi-save me-2"></i>Modifier
                            </button>
                            <button type="submit" name="btn_supprimer" class="btn btn-danger-cbs px-4" 
                                    onclick="return confirm('⚠️ Supprimer cette tranche d\'âge ? Cette action est irréversible.')">
                                <i class="bi bi-trash me-2"></i>Supprimer
                            </button>
                            <a href="enregistrement_tranche_age.php" class="btn btn-outline-cbs px-4">
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
                            <i class="bi bi-person text-primary"></i>
                        </div>
                        <div>
                            <strong>Création de tranche</strong>
                            <p class="text-muted small mb-0">L'ID est généré automatiquement au format TRA + Année + 4 chiffres</p>
                        </div>
                    </div>
                    <div class="d-flex align-items-center mb-2">
                        <div class="bg-success bg-opacity-10 rounded-circle p-2 me-2">
                            <i class="bi bi-arrows-expand text-success"></i>
                        </div>
                        <div>
                            <strong>Plage d'âge</strong>
                            <p class="text-muted small mb-0">Définit l'intervalle d'âge de la tranche</p>
                        </div>
                    </div>
                    <div class="d-flex align-items-center">
                        <div class="bg-danger bg-opacity-10 rounded-circle p-2 me-2">
                            <i class="bi bi-toggle-on text-danger"></i>
                        </div>
                        <div>
                            <strong>Statut actif/inactif</strong>
                            <p class="text-muted small mb-0">Contrôle la disponibilité de la tranche</p>
                        </div>
                    </div>
                </div>
                
                <hr>
                
                <?php if ($is_edit): ?>
                <div class="info-tranche">
                    <strong><i class="bi bi-calendar me-1"></i> Plage d'âge</strong>
                    <?= $age_debut ?> - <?= $age_fin ?> ans
                </div>
                <div class="info-tranche mt-2">
                    <strong><i class="bi bi-tag me-1"></i> Libellés</strong>
                    <?= htmlspecialchars($titre_debut) ?> - <?= htmlspecialchars($titre_fin) ?>
                </div>
                <div class="info-tranche mt-2">
                    <strong><i class="bi bi-shield me-1"></i> Statut actuel</strong>
                    <span class="badge-<?= strtolower($etat_tranche_age) ?>"><?= $etat_tranche_age ?></span>
                </div>
                <?php endif; ?>
                
                <div class="alert alert-warning small mt-3 mb-0">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    <strong>Suppression :</strong> Impossible si la tranche d'âge est utilisée dans des données ou associée à des projets.
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
                $('#sai_id_tranche').val(data.id_tranche);
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