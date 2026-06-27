<?php
require_once 'database/database.php';


// ============================================================
// HANDLERS AJAX (EN TOUT PREMIER)
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Générer ID district
    if (isset($_POST['generate_id'])) {
        header('Content-Type: application/json');
        $year = date('y');
        $stmt = $pdo->prepare("SELECT id_district FROM district WHERE id_district LIKE ? ORDER BY id_district DESC LIMIT 1");
        $stmt->execute(['DIS' . $year . '%']);
        $last = $stmt->fetchColumn();
        $num = $last ? str_pad(intval(substr($last, -4)) + 1, 4, '0', STR_PAD_LEFT) : '0001';
        $id_district = 'DIS' . $year . $num;
        echo json_encode(['success' => true, 'id_district' => $id_district]);
        exit;
    }
}

// ============================================================
// FONCTIONS DE GÉNÉRATION
// ============================================================
function generateDistrictId($pdo) {
    $year = date('y');
    $stmt = $pdo->prepare("SELECT id_district FROM district WHERE id_district LIKE ? ORDER BY id_district DESC LIMIT 1");
    $stmt->execute(['DIS' . $year . '%']);
    $last = $stmt->fetchColumn();
    $num = $last ? str_pad(intval(substr($last, -4)) + 1, 4, '0', STR_PAD_LEFT) : '0001';
    return 'DIS' . $year . $num;
}

// ============================================================
// TRAITEMENT DES ACTIONS
// ============================================================
$message = '';
$error = '';
$edit_district = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        
        // === AJOUTER ===
        if (isset($_POST['btn_ajouter'])) {
            $id_district = trim($_POST['sai_id_district']);
            $titre_district = trim($_POST['sai_titre_district']);
            $ville = trim($_POST['sai_ville']);
            $id_region = trim($_POST['sai_id_region']);
            $etat_district = trim($_POST['sai_etat_district']);
            
            // Validations
            if (empty($id_district)) throw new Exception('L\'ID district est obligatoire.');
            if (empty($titre_district)) throw new Exception('Le titre district est obligatoire.');
            if (empty($ville)) throw new Exception('La ville est obligatoire.');
            if (empty($id_region)) throw new Exception('La région est obligatoire.');
            
            // Vérifier unicité id_district
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM district WHERE id_district = ?');
            $stmt->execute([$id_district]);
            if ($stmt->fetchColumn() > 0) throw new Exception('Cet ID de district est déjà utilisé.');
            
            // Insertion
            $req = $pdo->prepare("
                INSERT INTO district (id_district, titre_district, ville, id_region, etat_district)
                VALUES (?, ?, ?, ?, ?)
            ");
            $req->execute([$id_district, $titre_district, $ville, $id_region, $etat_district]);
            
            $message = "District <strong>$titre_district</strong> créé avec succès !";
            unset($edit_district);
        }
        
        // === MODIFIER ===
        if (isset($_POST['btn_modifier'])) {
            $id_district = $_POST['sai_id_district'];
            $titre_district = trim($_POST['sai_titre_district']);
            $ville = trim($_POST['sai_ville']);
            $id_region = trim($_POST['sai_id_region']);
            $etat_district = trim($_POST['sai_etat_district']);
            
            if (empty($id_district) || empty($titre_district) || empty($ville) || empty($id_region)) {
                throw new Exception('Les champs obligatoires doivent être remplis.');
            }
            
            // Update
            $req = $pdo->prepare("
                UPDATE district
                SET titre_district = ?, ville = ?, id_region = ?, etat_district = ?
                WHERE id_district = ?
            ");
            $req->execute([$titre_district, $ville, $id_region, $etat_district, $id_district]);
            
            $message = 'District modifié avec succès.';
            unset($edit_district);
        }
        
        // === SUPPRIMER ===
        if (isset($_POST['btn_supprimer'])) {
            $id_district = $_POST['sai_id_district'];
            
            // Vérifier si le district est utilisé dans la table site
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM site WHERE id_district = ?');
            $stmt->execute([$id_district]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception("Ce district est utilisé dans des sites. Supprimez d'abord les sites associés.");
            }
            
            // Vérifier si le district est utilisé dans la table donnee
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM donnee WHERE id_district = ?');
            $stmt->execute([$id_district]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception("Ce district est utilisé dans des données. Supprimez d'abord les données associées.");
            }
            
            // Vérifier si le district est utilisé dans la table projet_district
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM projet_district WHERE id_district = ?');
            $stmt->execute([$id_district]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception("Ce district est associé à des projets. Supprimez d'abord les associations.");
            }
            
            // Supprimer
            $req = $pdo->prepare("DELETE FROM district WHERE id_district = ?");
            $req->execute([$id_district]);
            
            $message = 'District supprimé avec succès.';
            unset($edit_district);
        }
        
        // === RECHERCHER ===
        if (isset($_POST['btn_rechercher'])) {
            $search_id = trim($_POST['sai_rechercher'] ?? '');
            if (!empty($search_id)) {
                $stmt = $pdo->prepare("
                    SELECT d.*, r.titre_region 
                    FROM district d
                    LEFT JOIN region r ON d.id_region = r.id_region
                    WHERE d.id_district = ?
                ");
                $stmt->execute([$search_id]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($result) {
                    $edit_district = $result;
                } else {
                    $error = 'Aucun district trouvé avec cet ID.';
                    $edit_district = null;
                }
            } else {
                $error = 'Veuillez sélectionner un district.';
            }
        }
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// ============================================================
// RÉCUPÉRATION DES DONNÉES
// ============================================================

// Liste des régions
$regions = $pdo->query("SELECT * FROM region ORDER BY titre_region");

// Liste des districts pour la recherche
$liste_districts = $pdo->query("
    SELECT d.*, r.titre_region 
    FROM district d
    LEFT JOIN region r ON d.id_region = r.id_region
    ORDER BY d.titre_district
")->fetchAll(PDO::FETCH_ASSOC);

// Générer ID par défaut
$generated_id = generateDistrictId($pdo);

// Initialiser valeurs
$id_district = $edit_district['id_district'] ?? $generated_id;
$titre_district = $edit_district['titre_district'] ?? '';
$ville = $edit_district['ville'] ?? '';
$id_region = $edit_district['id_region'] ?? '';
$etat_district = $edit_district['etat_district'] ?? 'ACTIF';

// ============================================================
// STATISTIQUES
// ============================================================
$stmt = $pdo->query("SELECT COUNT(*) FROM district");
$total_districts = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM district WHERE etat_district = 'ACTIF'");
$districts_actifs = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM district WHERE etat_district = 'INACTIF'");
$districts_inactifs = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(DISTINCT id_region) FROM district");
$regions_couvertes = $stmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Districts - Enregistrement</title>
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
        .stat-card.warning { border-left-color: #ffc107; }
        .stat-card.danger { border-left-color: #dc3545; }
        .stat-value { font-size: 1.6rem; font-weight: 700; color: #0a4d8c; line-height: 1; }
        .stat-label { font-size: 0.7rem; color: #6c757d; text-transform: uppercase; letter-spacing: 0.5px; }
        .badge-active { background: #198754; color: white; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; }
        .badge-inactive { background: #dc3545; color: white; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; }
        .info-district { background: #e8f4fd; border-left: 4px solid #0a4d8c; padding: 12px 15px; border-radius: 6px; font-size: 0.85rem; height: 100%; }
        .info-district strong { display: block; margin-bottom: 5px; color: #0a4d8c; font-size: 0.8rem; text-transform: uppercase; }
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
        .region-info { background: #fff3cd; border-left: 4px solid #ffc107; padding: 10px 12px; border-radius: 6px; color: #856404; font-size: 0.8rem; }
    </style>
</head>
<body>

<div class="container-fluid my-4 px-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-0">📝 Saisie des districts</h4>
            <div class="text-muted small">Ajouter, modifier, supprimer et rechercher</div>
        </div>
        <div class="btn-export-group">
            <a href="<?= $_SERVER['PHP_SELF'] ?>" class="btn btn-outline-dark btn-sm">
                <i class="bi bi-arrow-left"></i> Retour à la liste
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
        <div class="col-md-3">
            <div class="stat-card primary">
                <div class="stat-value"><?= $total_districts ?></div>
                <div class="stat-label"><i class="bi bi-building me-1"></i> Total districts</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card success">
                <div class="stat-value"><?= $districts_actifs ?></div>
                <div class="stat-label"><i class="bi bi-check-circle me-1"></i> Districts actifs</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card danger">
                <div class="stat-value"><?= $districts_inactifs ?></div>
                <div class="stat-label"><i class="bi bi-pause-circle me-1"></i> Districts inactifs</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card warning">
                <div class="stat-value"><?= $regions_couvertes ?></div>
                <div class="stat-label"><i class="bi bi-globe me-1"></i> Régions couvertes</div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- COLONNE PRINCIPALE -->
        <div class="col-lg-8">
            
            <!-- Recherche -->
            <div class="card p-4 mb-4 border-start-primary">
                <h5 class="card-title text-uppercase card-title-cbs mb-3">
                    <i class="bi bi-search me-2 text-primary"></i>Recherche de district
                </h5>
                <form method="POST" class="row g-3 align-items-end">
                    <div class="col-md-8">
                        <label class="form-label">Sélectionner un district</label>
                        <select name="sai_rechercher" id="sai_rechercher" class="form-select" style="width: 100%;">
                            <option value="">-- Sélectionnez un district --</option>
                            <?php foreach ($liste_districts as $d): ?>
                                <option value="<?= htmlspecialchars($d['id_district']) ?>" <?= (isset($edit_district) && $edit_district['id_district'] == $d['id_district']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($d['id_district'] . ' - ' . $d['titre_district'] . ' (' . $d['titre_region'] . ')') ?>
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
                    <i class="bi bi-building me-2 text-primary"></i><?= isset($edit_district) ? 'Modifier le district' : 'Ajouter un district' ?>
                </h5>
                <form method="POST" id="districtForm">
                    <?php if (isset($edit_district)): ?>
                        <input type="hidden" name="sai_id_district" value="<?= htmlspecialchars($edit_district['id_district']) ?>">
                    <?php endif; ?>
                    
                    <div class="row g-3">
                        <!-- Identification -->
                        <div class="col-12">
                            <div class="section-title"><i class="bi bi-hash me-2"></i>Identification</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label required">ID District</label>
                            <div class="code-input-group">
                                <input type="text" class="form-control" id="sai_id_district" name="sai_id_district" 
                                       value="<?= htmlspecialchars($id_district) ?>" required maxlength="20"
                                       <?= isset($edit_district) ? 'readonly' : '' ?>>
                                <?php if (!isset($edit_district)): ?>
                                    <button type="button" id="generateIdBtn" class="btn-generate-id">
                                        <i class="bi bi-arrow-repeat"></i>
                                    </button>
                                <?php endif; ?>
                            </div>
                            <small class="text-muted"><i class="bi bi-info-circle"></i> Format: DIS + Année + 4 chiffres</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label required">Nom du district</label>
                            <input type="text" class="form-control" id="sai_titre_district" name="sai_titre_district" 
                                   value="<?= htmlspecialchars($titre_district) ?>" required maxlength="100"
                                   placeholder="Ex: District d'Abidjan">
                        </div>
                        
                        <!-- Localisation -->
                        <div class="col-12 mt-2">
                            <div class="section-title"><i class="bi bi-geo-alt me-2"></i>Localisation</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label required">Ville</label>
                            <input type="text" class="form-control" id="sai_ville" name="sai_ville" 
                                   value="<?= htmlspecialchars($ville) ?>" required maxlength="100"
                                   placeholder="Ex: Abidjan">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label required">Région</label>
                            <select name="sai_id_region" id="sai_id_region" class="form-select" required>
                                <option value="">-- Sélectionnez une région --</option>
                                <?php foreach ($regions as $r): ?>
                                    <option value="<?= $r['id_region'] ?>" <?= ($id_region == $r['id_region']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($r['titre_region']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <!-- Statut -->
                        <div class="col-12 mt-2">
                            <div class="section-title"><i class="bi bi-toggle-on me-2"></i>Statut</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label required">État du district</label>
                            <select name="sai_etat_district" id="sai_etat_district" class="form-select" required>
                                <option value="ACTIF" <?= ($etat_district == 'ACTIF') ? 'selected' : '' ?>>ACTIF</option>
                                <option value="INACTIF" <?= ($etat_district == 'INACTIF') ? 'selected' : '' ?>>INACTIF</option>
                            </select>
                            <small class="text-muted"><i class="bi bi-info-circle"></i> Un district inactif n'est pas disponible pour les données</small>
                        </div>
                        
                        <!-- Infos complémentaires (mode édition) -->
                        <?php if (isset($edit_district)): ?>
                        <div class="col-12 mt-2">
                            <div class="section-title"><i class="bi bi-info-circle me-2"></i>Informations complémentaires</div>
                        </div>
                        <div class="col-md-4">
                            <div class="info-district">
                                <strong><i class="bi bi-geo-alt me-1"></i> Région</strong>
                                <?= htmlspecialchars($edit_district['titre_region'] ?? 'Non définie') ?>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="info-district">
                                <strong><i class="bi bi-shield me-1"></i> Statut actuel</strong>
                                <span class="badge-<?= strtolower($etat_district) ?>"><?= $etat_district ?></span>
                                <?php if ($etat_district == 'ACTIF'): ?>
                                    <small class="text-muted d-block mt-1">Disponible pour les données</small>
                                <?php else: ?>
                                    <small class="text-muted d-block mt-1">Non disponible</small>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="info-district">
                                <strong><i class="bi bi-building me-1"></i> Sites associés</strong>
                                <?php
                                $stmt = $pdo->prepare('SELECT COUNT(*) FROM site WHERE id_district = ?');
                                $stmt->execute([$edit_district['id_district']]);
                                echo $stmt->fetchColumn();
                                ?>
                                <small class="text-muted d-block mt-1">Sites dans ce district</small>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Boutons -->
                    <div class="d-flex justify-content-center gap-2 mt-4">
                        <button type="submit" name="btn_ajouter" class="btn btn-success-cbs px-4" <?= isset($edit_district) ? 'style="display:none;"' : '' ?>>
                            <i class="bi bi-save me-2"></i>Ajouter
                        </button>
                        <button type="submit" name="btn_modifier" class="btn btn-temenos px-4" <?= !isset($edit_district) ? 'style="display:none;"' : '' ?>>
                            <i class="bi bi-pencil me-2"></i>Modifier
                        </button>
                        <button type="submit" name="btn_supprimer" class="btn btn-danger-cbs px-4" <?= !isset($edit_district) ? 'style="display:none;"' : '' ?>>
                            <i class="bi bi-trash me-2"></i>Supprimer
                        </button>
                        <?php if (isset($edit_district)): ?>
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
                            <i class="bi bi-building text-primary"></i>
                        </div>
                        <div>
                            <strong>Création de district</strong>
                            <p class="text-muted small mb-0">L'ID est généré automatiquement au format DIS + Année + 4 chiffres</p>
                        </div>
                    </div>
                    <div class="d-flex align-items-center mb-3">
                        <div class="bg-success bg-opacity-10 rounded-circle p-2 me-2">
                            <i class="bi bi-geo-alt text-success"></i>
                        </div>
                        <div>
                            <strong>Ville et Région</strong>
                            <p class="text-muted small mb-0">Permet de localiser géographiquement le district</p>
                        </div>
                    </div>
                    <div class="d-flex align-items-center">
                        <div class="bg-danger bg-opacity-10 rounded-circle p-2 me-2">
                            <i class="bi bi-toggle-on text-danger"></i>
                        </div>
                        <div>
                            <strong>Statut actif/inactif</strong>
                            <p class="text-muted small mb-0">Contrôle la disponibilité du district</p>
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

                <div class="info-district">
                    <i class="bi bi-lightbulb me-2"></i>
                    <strong>Astuce :</strong>
                    <small>Un district doit être créé avant de pouvoir y associer des sites et des données.</small>
                </div>
                
                <div class="alert alert-warning small mt-3 mb-0">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    <strong>Suppression :</strong> Impossible si le district a des sites, des données ou des associations projet associés.
                </div>
                
                <div class="alert alert-info small mt-3 mb-0">
                    <i class="bi bi-info-circle me-2"></i>
                    <strong>Conseil :</strong> Un district peut couvrir plusieurs sites dans une même région.</small>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Select2
    $('#sai_rechercher').select2({
        theme: 'default',
        width: '100%',
        placeholder: 'Sélectionnez un district',
        allowClear: true
    });
    
    // Génération ID
    $('#generateIdBtn').click(function() {
        const btn = $(this);
        btn.html('<span class="loading-spinner"></span>').prop('disabled', true);
        
        $.post(window.location.href, { generate_id: 1 }, function(data) {
            if (data.success) {
                $('#sai_id_district').val(data.id_district);
            } else {
                alert('Erreur: ' + (data.message || 'Impossible de générer l\'ID'));
            }
        }, 'json').always(function() {
            btn.html('<i class="bi bi-arrow-repeat"></i>').prop('disabled', false);
        });
    });
    
    // Confirmation suppression
    $('button[name="btn_supprimer"]').on('click', function(e) {
        if (!confirm('⚠️ ATTENTION ⚠️\n\nÊtes-vous sûr de vouloir supprimer ce district ?\n\nCette action est IRRÉVERSIBLE et nécessite :\n- Aucun site associé\n- Aucune donnée associée\n- Aucune association projet')) {
            e.preventDefault();
            return false;
        }
    });
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>