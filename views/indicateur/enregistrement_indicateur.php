<?php
require_once 'database/database.php';


// ============================================================
// HANDLERS AJAX
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');
    
    if ($_POST['ajax_action'] === 'generate_id') {
        $year = date('y');
        $stmt = $pdo->prepare("SELECT id_indicateur FROM indicateur WHERE id_indicateur LIKE ? ORDER BY id_indicateur DESC LIMIT 1");
        $stmt->execute(['IND' . $year . '%']);
        $last = $stmt->fetchColumn();
        $num = $last ? str_pad(intval(substr($last, -4)) + 1, 4, '0', STR_PAD_LEFT) : '0001';
        $id_indicateur = 'IND' . $year . $num;
        echo json_encode(['success' => true, 'id_indicateur' => $id_indicateur]);
        exit;
    }
}

// ============================================================
// TRAITEMENT DES ACTIONS CRUD
// ============================================================
$message = '';
$error = '';
$edit_indicateur = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        
        // === AJOUTER ===
        if (isset($_POST['btn_ajouter'])) {
            $id_indicateur = trim($_POST['sai_id_indicateur']);
            $numero = trim($_POST['sai_numero']);
            $titre_indicateur = trim($_POST['sai_titre_indicateur']);
            $id_domaine = trim($_POST['sai_id_domaine']);
            $etat_indicateur = trim($_POST['sai_etat_indicateur']);
            
            if (empty($id_indicateur)) throw new Exception('L\'ID indicateur est obligatoire.');
            if (empty($numero)) throw new Exception('Le numéro est obligatoire.');
            if (empty($titre_indicateur)) throw new Exception('Le titre indicateur est obligatoire.');
            if (empty($id_domaine)) throw new Exception('Le domaine est obligatoire.');
            
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM indicateur WHERE id_indicateur = ?');
            $stmt->execute([$id_indicateur]);
            if ($stmt->fetchColumn() > 0) throw new Exception('Cet ID d\'indicateur est déjà utilisé.');
            
            $req = $pdo->prepare("INSERT INTO indicateur (id_indicateur, numero, titre_indicateur, id_domaine, etat_indicateur) VALUES (?, ?, ?, ?, ?)");
            $req->execute([$id_indicateur, $numero, $titre_indicateur, $id_domaine, $etat_indicateur]);
            
            $message = "Indicateur <strong>$titre_indicateur</strong> créé avec succès !";
            unset($edit_indicateur);
        }
        
        // === MODIFIER ===
        if (isset($_POST['btn_modifier'])) {
            $id_indicateur = $_POST['sai_id_indicateur'];
            $numero = trim($_POST['sai_numero']);
            $titre_indicateur = trim($_POST['sai_titre_indicateur']);
            $id_domaine = trim($_POST['sai_id_domaine']);
            $etat_indicateur = trim($_POST['sai_etat_indicateur']);
            
            if (empty($id_indicateur) || empty($numero) || empty($titre_indicateur) || empty($id_domaine)) {
                throw new Exception('Les champs obligatoires doivent être remplis.');
            }
            
            $req = $pdo->prepare("UPDATE indicateur SET numero = ?, titre_indicateur = ?, id_domaine = ?, etat_indicateur = ? WHERE id_indicateur = ?");
            $req->execute([$numero, $titre_indicateur, $id_domaine, $etat_indicateur, $id_indicateur]);
            
            $message = 'Indicateur modifié avec succès.';
            unset($edit_indicateur);
        }
        
        // === SUPPRIMER ===
        if (isset($_POST['btn_supprimer'])) {
            $id_indicateur = $_POST['sai_id_indicateur'];
            
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM donnee WHERE id_indicateur = ?');
            $stmt->execute([$id_indicateur]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception("Cet indicateur est utilisé dans des données.");
            }
            
            $req = $pdo->prepare("DELETE FROM indicateur WHERE id_indicateur = ?");
            $req->execute([$id_indicateur]);
            
            $message = 'Indicateur supprimé avec succès.';
            unset($edit_indicateur);
        }
        
        // === CHARGER POUR MODIFICATION ===
        if (isset($_GET['edit']) && !empty($_GET['edit'])) {
            $stmt = $pdo->prepare("SELECT i.*, d.titre_domaine FROM indicateur i LEFT JOIN domaine d ON i.id_domaine = d.id_domaine WHERE i.id_indicateur = ?");
            $stmt->execute([$_GET['edit']]);
            $edit_indicateur = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        
        // === SUPPRESSION VIA GET ===
        if (isset($_GET['supprimer']) && !empty($_GET['supprimer'])) {
            $id_indicateur = $_GET['supprimer'];
            
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM donnee WHERE id_indicateur = ?');
            $stmt->execute([$id_indicateur]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception("Cet indicateur est utilisé dans des données.");
            }
            
            $req = $pdo->prepare("DELETE FROM indicateur WHERE id_indicateur = ?");
            $req->execute([$id_indicateur]);
            
            header('Location: recherche_indicateur.php?success=supprime');
            exit;
        }
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// === CHARGER POUR MODIFICATION VIA GET ===
if (isset($_GET['edit']) && !empty($_GET['edit']) && !$edit_indicateur) {
    $stmt = $pdo->prepare("SELECT i.*, d.titre_domaine FROM indicateur i LEFT JOIN domaine d ON i.id_domaine = d.id_domaine WHERE i.id_indicateur = ?");
    $stmt->execute([$_GET['edit']]);
    $edit_indicateur = $stmt->fetch(PDO::FETCH_ASSOC);
}

// === LISTE DES DOMAINES ===
$domaines = $pdo->query("SELECT * FROM domaine ORDER BY titre_domaine")->fetchAll(PDO::FETCH_ASSOC);

// === GÉNÉRER ID PAR DÉFAUT ===
$year = date('y');
$stmt = $pdo->prepare("SELECT id_indicateur FROM indicateur WHERE id_indicateur LIKE ? ORDER BY id_indicateur DESC LIMIT 1");
$stmt->execute(['IND' . $year . '%']);
$last = $stmt->fetchColumn();
$num = $last ? str_pad(intval(substr($last, -4)) + 1, 4, '0', STR_PAD_LEFT) : '0001';
$generated_id = 'IND' . $year . $num;

// === VALEURS PAR DÉFAUT ===
$id_indicateur = $edit_indicateur['id_indicateur'] ?? $generated_id;
$numero = $edit_indicateur['numero'] ?? '';
$titre_indicateur = $edit_indicateur['titre_indicateur'] ?? '';
$id_domaine = $edit_indicateur['id_domaine'] ?? '';
$etat_indicateur = $edit_indicateur['etat_indicateur'] ?? 'ACTIF';
$is_edit = ($edit_indicateur !== null);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $is_edit ? 'Modifier' : 'Ajouter' ?> un Indicateur</title>
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
        .badge-active { background: #198754; color: white; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; }
        .badge-inactive { background: #dc3545; color: white; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; }
        .info-indicateur { background: #e8f4fd; border-left: 4px solid #0a4d8c; padding: 12px 15px; border-radius: 6px; font-size: 0.85rem; }
        .info-indicateur strong { display: block; margin-bottom: 5px; color: #0a4d8c; font-size: 0.8rem; text-transform: uppercase; }
        .select2-container--default .select2-selection--single { border-color: #dee2e6; border-radius: 6px; height: 38px; }
        .select2-container--default .select2-selection--single .select2-selection__rendered { line-height: 38px; }
    </style>
</head>
<body>

<div class="container my-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-0"><?= $is_edit ? '✏️ Modifier l\'indicateur' : '📝 Ajouter un indicateur' ?></h4>
            <div class="text-muted small"><?= $is_edit ? 'Modifier les informations de l\'indicateur' : 'Créer un nouvel indicateur' ?></div>
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
                <form method="POST" id="indicateurForm">
                    <div class="row g-3">
                        <div class="col-12">
                            <div class="section-title"><i class="bi bi-hash me-2"></i>Identification</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label required">ID Indicateur</label>
                            <div class="code-input-group">
                                <input type="text" class="form-control" id="sai_id_indicateur" name="sai_id_indicateur" 
                                       value="<?= htmlspecialchars($id_indicateur) ?>" required maxlength="20"
                                       <?= $is_edit ? 'readonly' : '' ?>>
                                <?php if (!$is_edit): ?>
                                    <button type="button" id="generateIdBtn" class="btn-generate-id">
                                        <i class="bi bi-arrow-repeat"></i>
                                    </button>
                                <?php endif; ?>
                            </div>
                            <small class="text-muted"><i class="bi bi-info-circle"></i> Format: IND + Année + 4 chiffres</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label required">Numéro</label>
                            <input type="text" class="form-control" id="sai_numero" name="sai_numero" 
                                   value="<?= htmlspecialchars($numero) ?>" required maxlength="20"
                                   placeholder="Ex: IND-001">
                        </div>
                        
                        <div class="col-12 mt-2">
                            <div class="section-title"><i class="bi bi-tag me-2"></i>Informations</div>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label required">Titre de l'indicateur</label>
                            <input type="text" class="form-control" id="sai_titre_indicateur" name="sai_titre_indicateur" 
                                   value="<?= htmlspecialchars($titre_indicateur) ?>" required maxlength="200"
                                   placeholder="Ex: Taux de scolarisation des enfants">
                        </div>
                        <div class="col-md-12">
                            <label class="form-label required">Domaine</label>
                            <select name="sai_id_domaine" id="sai_id_domaine" class="form-select" required style="width:100%;">
                                <option value="">-- Sélectionnez un domaine --</option>
                                <?php foreach ($domaines as $d): ?>
                                    <option value="<?= $d['id_domaine'] ?>" <?= ($id_domaine == $d['id_domaine']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($d['titre_domaine']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-12 mt-2">
                            <div class="section-title"><i class="bi bi-toggle-on me-2"></i>Statut</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label required">État de l'indicateur</label>
                            <select name="sai_etat_indicateur" id="sai_etat_indicateur" class="form-select" required>
                                <option value="ACTIF" <?= ($etat_indicateur == 'ACTIF') ? 'selected' : '' ?>>ACTIF</option>
                                <option value="INACTIF" <?= ($etat_indicateur == 'INACTIF') ? 'selected' : '' ?>>INACTIF</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-center gap-2 mt-4">
                        <?php if ($is_edit): ?>
                            <button type="submit" name="btn_modifier" class="btn btn-temenos px-4">
                                <i class="bi bi-save me-2"></i>Modifier
                            </button>
                            <button type="submit" name="btn_supprimer" class="btn btn-danger-cbs px-4" 
                                    onclick="return confirm('⚠️ Supprimer cet indicateur ? Cette action est irréversible.')">
                                <i class="bi bi-trash me-2"></i>Supprimer
                            </button>
                            <a href="enregistrement_indicateur.php" class="btn btn-outline-cbs px-4">
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
                            <i class="bi bi-list-ul text-primary"></i>
                        </div>
                        <div>
                            <strong>Création d'indicateur</strong>
                            <p class="text-muted small mb-0">L'ID est généré automatiquement au format IND + Année + 4 chiffres</p>
                        </div>
                    </div>
                    <div class="d-flex align-items-center mb-2">
                        <div class="bg-success bg-opacity-10 rounded-circle p-2 me-2">
                            <i class="bi bi-tag text-success"></i>
                        </div>
                        <div>
                            <strong>Domaine associé</strong>
                            <p class="text-muted small mb-0">Un indicateur appartient à un domaine</p>
                        </div>
                    </div>
                    <div class="d-flex align-items-center">
                        <div class="bg-danger bg-opacity-10 rounded-circle p-2 me-2">
                            <i class="bi bi-toggle-on text-danger"></i>
                        </div>
                        <div>
                            <strong>Statut actif/inactif</strong>
                            <p class="text-muted small mb-0">Contrôle la disponibilité de l'indicateur</p>
                        </div>
                    </div>
                </div>
                
                <hr>
                
                <?php if ($is_edit): ?>
                <div class="info-indicateur">
                    <strong><i class="bi bi-tag me-1"></i> Domaine</strong>
                    <?= htmlspecialchars($edit_indicateur['titre_domaine'] ?? 'Non défini') ?>
                </div>
                <div class="info-indicateur mt-2">
                    <strong><i class="bi bi-hash me-1"></i> Numéro</strong>
                    <?= htmlspecialchars($numero) ?>
                </div>
                <div class="info-indicateur mt-2">
                    <strong><i class="bi bi-shield me-1"></i> Statut actuel</strong>
                    <span class="badge-<?= strtolower($etat_indicateur) ?>"><?= $etat_indicateur ?></span>
                </div>
                <?php endif; ?>
                
                <div class="alert alert-warning small mt-3 mb-0">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    <strong>Suppression :</strong> Impossible si l'indicateur est utilisé dans des données.
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Select2
    $('#sai_id_domaine').select2({
        theme: 'default',
        width: '100%',
        placeholder: 'Sélectionnez un domaine',
        allowClear: true
    });
    
    <?php if (!$is_edit): ?>
    $('#generateIdBtn').click(function() {
        const btn = $(this);
        btn.html('<span class="loading-spinner"></span>').prop('disabled', true);
        
        $.post(window.location.href, { ajax_action: 'generate_id' }, function(data) {
            if (data.success) {
                $('#sai_id_indicateur').val(data.id_indicateur);
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