<?php
require_once 'database/database.php';


// ============================================================
// HANDLERS AJAX
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Générer ID domaine
    if (isset($_POST['generate_id'])) {
        header('Content-Type: application/json');
        $year = date('y');
        $stmt = $pdo->prepare("SELECT id_domaine FROM domaine WHERE id_domaine LIKE ? ORDER BY id_domaine DESC LIMIT 1");
        $stmt->execute(['DOM' . $year . '%']);
        $last = $stmt->fetchColumn();
        $num = $last ? str_pad(intval(substr($last, -4)) + 1, 4, '0', STR_PAD_LEFT) : '0001';
        $id_domaine = 'DOM' . $year . $num;
        echo json_encode(['success' => true, 'id_domaine' => $id_domaine]);
        exit;
    }
}

// ============================================================
// FONCTIONS DE GÉNÉRATION
// ============================================================
function generateDomaineId($pdo) {
    $year = date('y');
    $stmt = $pdo->prepare("SELECT id_domaine FROM domaine WHERE id_domaine LIKE ? ORDER BY id_domaine DESC LIMIT 1");
    $stmt->execute(['DOM' . $year . '%']);
    $last = $stmt->fetchColumn();
    $num = $last ? str_pad(intval(substr($last, -4)) + 1, 4, '0', STR_PAD_LEFT) : '0001';
    return 'DOM' . $year . $num;
}

// ============================================================
// TRAITEMENT DES ACTIONS
// ============================================================
$message = '';
$error = '';
$edit_domaine = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        
        // === AJOUTER ===
        if (isset($_POST['btn_ajouter'])) {
            $id_domaine = trim($_POST['sai_id_domaine']);
            $titre_domaine = trim($_POST['sai_titre_domaine']);
            $etat_domaine = trim($_POST['sai_etat_domaine']);
            
            if (empty($id_domaine)) throw new Exception('L\'ID domaine est obligatoire.');
            if (empty($titre_domaine)) throw new Exception('Le titre domaine est obligatoire.');
            
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM domaine WHERE id_domaine = ?');
            $stmt->execute([$id_domaine]);
            if ($stmt->fetchColumn() > 0) throw new Exception('Cet ID de domaine est déjà utilisé.');
            
            $req = $pdo->prepare("INSERT INTO domaine (id_domaine, titre_domaine, etat_domaine) VALUES (?, ?, ?)");
            $req->execute([$id_domaine, $titre_domaine, $etat_domaine]);
            
            $message = "Domaine <strong>$titre_domaine</strong> créé avec succès !";
            unset($edit_domaine);
        }
        
        // === MODIFIER ===
        if (isset($_POST['btn_modifier'])) {
            $id_domaine = $_POST['sai_id_domaine'];
            $titre_domaine = trim($_POST['sai_titre_domaine']);
            $etat_domaine = trim($_POST['sai_etat_domaine']);
            
            if (empty($id_domaine) || empty($titre_domaine)) {
                throw new Exception('Les champs obligatoires doivent être remplis.');
            }
            
            $req = $pdo->prepare("UPDATE domaine SET titre_domaine = ?, etat_domaine = ? WHERE id_domaine = ?");
            $req->execute([$titre_domaine, $etat_domaine, $id_domaine]);
            
            $message = 'Domaine modifié avec succès.';
            unset($edit_domaine);
        }
        
        // === SUPPRIMER ===
        if (isset($_POST['btn_supprimer'])) {
            $id_domaine = $_POST['sai_id_domaine'];
            
            // Vérifier si le domaine est utilisé
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM indicateur WHERE id_domaine = ?');
            $stmt->execute([$id_domaine]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception("Ce domaine est utilisé dans des indicateurs. Supprimez d'abord les indicateurs associés.");
            }
            
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM projet_domaine WHERE id_domaine = ?');
            $stmt->execute([$id_domaine]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception("Ce domaine est associé à des projets. Supprimez d'abord les associations.");
            }
            
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM donnee WHERE id_domaine = ?');
            $stmt->execute([$id_domaine]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception("Ce domaine est utilisé dans des données.");
            }
            
            $req = $pdo->prepare("DELETE FROM domaine WHERE id_domaine = ?");
            $req->execute([$id_domaine]);
            
            $message = 'Domaine supprimé avec succès.';
            unset($edit_domaine);
        }
        
        // === RECHERCHER ===
        if (isset($_POST['btn_rechercher'])) {
            $search_id = trim($_POST['sai_rechercher'] ?? '');
            if (!empty($search_id)) {
                $stmt = $pdo->prepare("SELECT * FROM domaine WHERE id_domaine = ?");
                $stmt->execute([$search_id]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($result) {
                    $edit_domaine = $result;
                } else {
                    $error = 'Aucun domaine trouvé avec cet ID.';
                    $edit_domaine = null;
                }
            } else {
                $error = 'Veuillez sélectionner un domaine.';
            }
        }
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// ============================================================
// RÉCUPÉRATION DES DONNÉES
// ============================================================

$liste_domaines = $pdo->query("SELECT * FROM domaine ORDER BY titre_domaine")->fetchAll(PDO::FETCH_ASSOC);

$generated_id = generateDomaineId($pdo);

$id_domaine = $edit_domaine['id_domaine'] ?? $generated_id;
$titre_domaine = $edit_domaine['titre_domaine'] ?? '';
$etat_domaine = $edit_domaine['etat_domaine'] ?? 'ACTIF';

// ============================================================
// STATISTIQUES
// ============================================================
$stmt = $pdo->query("SELECT COUNT(*) FROM domaine");
$total_domaines = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM domaine WHERE etat_domaine = 'ACTIF'");
$domaines_actifs = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM indicateur");
$total_indicateurs = $stmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Domaines - Enregistrement</title>
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
        .border-start-primary { border-left: 3px solid #0a4d8c !important; }
        .section-title { font-size: 0.85rem; font-weight: 600; color: #0a4d8c; background: #e9ecef; padding: 8px 12px; border-radius: 6px; margin-bottom: 15px; }
        .required:after { content: " *"; color: #dc3545; }
        .stat-card { background: white; border-radius: 8px; padding: 15px; border-left: 4px solid; box-shadow: 0 2px 6px rgba(0,0,0,0.05); transition: all 0.2s; }
        .stat-card:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        .stat-card.primary { border-left-color: #0a4d8c; }
        .stat-card.success { border-left-color: #198754; }
        .stat-card.danger { border-left-color: #dc3545; }
        .stat-card.warning { border-left-color: #ffc107; }
        .stat-value { font-size: 1.6rem; font-weight: 700; color: #0a4d8c; line-height: 1; }
        .stat-label { font-size: 0.7rem; color: #6c757d; text-transform: uppercase; letter-spacing: 0.5px; }
        .badge-active { background: #198754; color: white; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; }
        .badge-inactive { background: #dc3545; color: white; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; }
        .info-domaine { background: #e8f4fd; border-left: 4px solid #0a4d8c; padding: 12px 15px; border-radius: 6px; font-size: 0.85rem; height: 100%; }
        .info-domaine strong { display: block; margin-bottom: 5px; color: #0a4d8c; font-size: 0.8rem; text-transform: uppercase; }
        .code-input-group { display: flex; gap: 8px; align-items: center; }
        .code-input-group .form-control { flex: 1; font-family: 'Courier New', monospace; font-weight: 600; color: #0a4d8c; }
        .btn-generate-id { white-space: nowrap; background: #0a4d8c; color: white; border: none; padding: 6px 12px; border-radius: 6px; font-size: 0.85rem; }
        .btn-generate-id:hover { background: #05101c; color: white; }
        .select2-container--default .select2-selection--single { border-color: #dee2e6; border-radius: 6px; height: 38px; }
        .select2-container--default .select2-selection--single .select2-selection__rendered { line-height: 38px; }
        .sticky-md-top { top: 80px; z-index: 10; }
        @media (max-width: 768px) { .sticky-md-top { position: relative; top: 0; } }
        .loading-spinner { display: inline-block; width: 14px; height: 14px; border: 2px solid #f3f3f3; border-top: 2px solid white; border-radius: 50%; animation: spin 1s linear infinite; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
    </style>
</head>
<body>

<div class="container-fluid my-4 px-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-0">📝 Saisie des domaines</h4>
            <div class="text-muted small">Ajouter, modifier, supprimer et rechercher</div>
        </div>
        <div class="btn-export-group">
            <a href="recherche_domaine.php" class="btn btn-outline-dark btn-sm">
                <i class="bi bi-list me-2"></i>Voir la liste
            </a>
        </div>
    </div>
    <hr>

    <!-- Messages -->
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

    <!-- Statistiques -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="stat-card primary">
                <div class="stat-value"><?= $total_domaines ?></div>
                <div class="stat-label"><i class="bi bi-tag me-1"></i> Total domaines</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card success">
                <div class="stat-value"><?= $domaines_actifs ?></div>
                <div class="stat-label"><i class="bi bi-check-circle me-1"></i> Domaines actifs</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card warning">
                <div class="stat-value"><?= $total_indicateurs ?></div>
                <div class="stat-label"><i class="bi bi-list-ul me-1"></i> Indicateurs associés</div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- COLONNE PRINCIPALE -->
        <div class="col-lg-8">
            
            <!-- Recherche -->
            <div class="card p-4 mb-4 border-start-primary">
                <h5 class="card-title text-uppercase card-title-cbs mb-3">
                    <i class="bi bi-search me-2 text-primary"></i>Recherche de domaine
                </h5>
                <form method="POST" class="row g-3 align-items-end">
                    <div class="col-md-8">
                        <label class="form-label">Sélectionner un domaine</label>
                        <select name="sai_rechercher" id="sai_rechercher" class="form-select" style="width: 100%;">
                            <option value="">-- Sélectionnez un domaine --</option>
                            <?php foreach ($liste_domaines as $d): ?>
                                <option value="<?= htmlspecialchars($d['id_domaine']) ?>" <?= (isset($edit_domaine) && $edit_domaine['id_domaine'] == $d['id_domaine']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($d['id_domaine'] . ' - ' . $d['titre_domaine']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <button type="submit" name="btn_rechercher" class="btn btn-temenos w-100">
                            <i class="bi bi-search me-2"></i>Rechercher
                        </button>
                    </div>
                </form>
            </div>

            <!-- Formulaire -->
            <div class="card p-4">
                <h5 class="card-title text-uppercase card-title-cbs mb-3">
                    <i class="bi bi-tag me-2 text-primary"></i><?= isset($edit_domaine) ? 'Modifier le domaine' : 'Ajouter un domaine' ?>
                </h5>
                <form method="POST" id="domaineForm">
                    <?php if (isset($edit_domaine)): ?>
                        <input type="hidden" name="sai_id_domaine" value="<?= htmlspecialchars($edit_domaine['id_domaine']) ?>">
                    <?php endif; ?>
                    
                    <div class="row g-3">
                        <div class="col-12">
                            <div class="section-title"><i class="bi bi-hash me-2"></i>Identification</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label required">ID Domaine</label>
                            <div class="code-input-group">
                                <input type="text" class="form-control" id="sai_id_domaine" name="sai_id_domaine" 
                                       value="<?= htmlspecialchars($id_domaine) ?>" required maxlength="20"
                                       <?= isset($edit_domaine) ? 'readonly' : '' ?>>
                                <?php if (!isset($edit_domaine)): ?>
                                    <button type="button" id="generateIdBtn" class="btn-generate-id">
                                        <i class="bi bi-arrow-repeat"></i>
                                    </button>
                                <?php endif; ?>
                            </div>
                            <small class="text-muted"><i class="bi bi-info-circle"></i> Format: DOM + Année + 4 chiffres</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label required">Titre du domaine</label>
                            <input type="text" class="form-control" id="sai_titre_domaine" name="sai_titre_domaine" 
                                   value="<?= htmlspecialchars($titre_domaine) ?>" required maxlength="100"
                                   placeholder="Ex: Santé, Éducation, Agriculture...">
                        </div>
                        
                        <div class="col-12 mt-2">
                            <div class="section-title"><i class="bi bi-toggle-on me-2"></i>Statut</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label required">État du domaine</label>
                            <select name="sai_etat_domaine" id="sai_etat_domaine" class="form-select" required>
                                <option value="ACTIF" <?= ($etat_domaine == 'ACTIF') ? 'selected' : '' ?>>ACTIF</option>
                                <option value="INACTIF" <?= ($etat_domaine == 'INACTIF') ? 'selected' : '' ?>>INACTIF</option>
                            </select>
                        </div>
                        
                        <?php if (isset($edit_domaine)): ?>
                        <div class="col-12 mt-2">
                            <div class="section-title"><i class="bi bi-info-circle me-2"></i>Informations complémentaires</div>
                        </div>
                        <div class="col-md-6">
                            <div class="info-domaine">
                                <strong><i class="bi bi-shield me-1"></i> Statut actuel</strong>
                                <span class="badge-<?= strtolower($etat_domaine) ?>"><?= $etat_domaine ?></span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="info-domaine">
                                <strong><i class="bi bi-list-ul me-1"></i> Indicateurs associés</strong>
                                <?php
                                $stmt = $pdo->prepare('SELECT COUNT(*) FROM indicateur WHERE id_domaine = ?');
                                $stmt->execute([$edit_domaine['id_domaine']]);
                                echo $stmt->fetchColumn();
                                ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Boutons -->
                    <div class="d-flex justify-content-center gap-2 mt-4">
                        <button type="submit" name="btn_ajouter" class="btn btn-success-cbs px-4" <?= isset($edit_domaine) ? 'style="display:none;"' : '' ?>>
                            <i class="bi bi-save me-2"></i>Ajouter
                        </button>
                        <button type="submit" name="btn_modifier" class="btn btn-temenos px-4" <?= !isset($edit_domaine) ? 'style="display:none;"' : '' ?>>
                            <i class="bi bi-pencil me-2"></i>Modifier
                        </button>
                        <button type="submit" name="btn_supprimer" class="btn btn-danger-cbs px-4" <?= !isset($edit_domaine) ? 'style="display:none;"' : '' ?>>
                            <i class="bi bi-trash me-2"></i>Supprimer
                        </button>
                        <?php if (isset($edit_domaine)): ?>
                            <a href="<?= $_SERVER['PHP_SELF'] ?>" class="btn btn-outline-cbs px-4">
                                <i class="bi bi-x-circle me-2"></i>Annuler
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <!-- PANNEAU LATÉRAL -->
        <div class="col-lg-4">
            <div class="card p-4 sticky-md-top">
                <h5 class="card-title text-uppercase card-title-cbs mb-3">
                    <i class="bi bi-info-circle me-2 text-primary"></i>Guide
                </h5>
                
                <div class="mb-4">
                    <div class="d-flex align-items-center mb-3">
                        <div class="bg-primary bg-opacity-10 rounded-circle p-2 me-2">
                            <i class="bi bi-tag text-primary"></i>
                        </div>
                        <div>
                            <strong>Création de domaine</strong>
                            <p class="text-muted small mb-0">L'ID est généré automatiquement au format DOM + Année + 4 chiffres</p>
                        </div>
                    </div>
                    <div class="d-flex align-items-center mb-3">
                        <div class="bg-success bg-opacity-10 rounded-circle p-2 me-2">
                            <i class="bi bi-list-ul text-success"></i>
                        </div>
                        <div>
                            <strong>Indicateurs associés</strong>
                            <p class="text-muted small mb-0">Un domaine peut regrouper plusieurs indicateurs</p>
                        </div>
                    </div>
                    <div class="d-flex align-items-center">
                        <div class="bg-danger bg-opacity-10 rounded-circle p-2 me-2">
                            <i class="bi bi-toggle-on text-danger"></i>
                        </div>
                        <div>
                            <strong>Statut actif/inactif</strong>
                            <p class="text-muted small mb-0">Contrôle la disponibilité du domaine</p>
                        </div>
                    </div>
                </div>

                <hr>

                <h6 class="text-primary mb-2"><i class="bi bi-shield me-2"></i>Statuts disponibles</h6>
                <div class="d-flex flex-wrap gap-2 mb-3">
                    <span class="badge-active">ACTIF</span>
                    <span class="badge-inactive">INACTIF</span>
                </div>

                <hr>

                <div class="info-domaine">
                    <i class="bi bi-lightbulb me-2"></i>
                    <strong>Astuce :</strong>
                    <small>Un domaine doit être créé avant de pouvoir y associer des indicateurs.</small>
                </div>
                
                <div class="alert alert-warning small mt-3 mb-0">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    <strong>Suppression :</strong> Impossible si le domaine a des indicateurs, des données ou des associations projet associés.
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#sai_rechercher').select2({
        theme: 'default',
        width: '100%',
        placeholder: 'Sélectionnez un domaine',
        allowClear: true
    });
    
    $('#generateIdBtn').click(function() {
        const btn = $(this);
        btn.html('<span class="loading-spinner"></span>').prop('disabled', true);
        
        $.post(window.location.href, { generate_id: 1 }, function(data) {
            if (data.success) {
                $('#sai_id_domaine').val(data.id_domaine);
            } else {
                alert('Erreur: ' + (data.message || 'Impossible de générer l\'ID'));
            }
        }, 'json').always(function() {
            btn.html('<i class="bi bi-arrow-repeat"></i>').prop('disabled', false);
        });
    });
    
    $('button[name="btn_supprimer"]').on('click', function(e) {
        if (!confirm('⚠️ ATTENTION ⚠️\n\nÊtes-vous sûr de vouloir supprimer ce domaine ?\n\nCette action est IRRÉVERSIBLE et nécessite :\n- Aucun indicateur associé\n- Aucune donnée associée\n- Aucune association projet')) {
            e.preventDefault();
            return false;
        }
    });
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>