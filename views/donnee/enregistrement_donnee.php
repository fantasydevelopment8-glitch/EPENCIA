<?php
require_once 'database/database.php';


// ============================================================
// TRAITEMENT DES ACTIONS CRUD
// ============================================================
$message = '';
$error = '';
$edit_donnee = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        
        // === AJOUTER ===
        if (isset($_POST['btn_ajouter'])) {
            $id_projet = trim($_POST['sai_id_projet']);
            $id_district = trim($_POST['sai_id_district']);
            $id_site = trim($_POST['sai_id_site']);
            $id_domaine = trim($_POST['sai_id_domaine']);
            $id_indicateur = trim($_POST['sai_id_indicateur']);
            $id_tranche = trim($_POST['sai_id_tranche']);
            $sexe = trim($_POST['sai_sexe']);
            $valeur = trim($_POST['sai_valeur']);
            $mois = trim($_POST['sai_mois']);
            $annee = trim($_POST['sai_annee']);
            $saisi_par = trim($_POST['sai_saisi_par']);
            $etat_donnee = trim($_POST['sai_etat_donnee']);
            
            if (empty($id_projet)) throw new Exception('Le projet est obligatoire.');
            if (empty($id_district)) throw new Exception('Le district est obligatoire.');
            if (empty($id_site)) throw new Exception('Le site est obligatoire.');
            if (empty($id_domaine)) throw new Exception('Le domaine est obligatoire.');
            if (empty($id_indicateur)) throw new Exception('L\'indicateur est obligatoire.');
            if (empty($id_tranche)) throw new Exception('La tranche d\'âge est obligatoire.');
            if (empty($sexe)) throw new Exception('Le sexe est obligatoire.');
            if (empty($valeur) && $valeur !== '0') throw new Exception('La valeur est obligatoire.');
            if (empty($mois)) throw new Exception('Le mois est obligatoire.');
            if (empty($annee)) throw new Exception('L\'année est obligatoire.');
            if (empty($saisi_par)) throw new Exception('Le saisisseur est obligatoire.');
            
            // Vérifier que la combinaison projet/district/site/domaine/indicateur/tranche/sexe/mois/annee n'existe pas déjà
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM donnee WHERE id_projet = ? AND id_district = ? AND id_site = ? AND id_domaine = ? AND id_indicateur = ? AND id_tranche = ? AND sexe = ? AND mois = ? AND annee = ?');
            $stmt->execute([$id_projet, $id_district, $id_site, $id_domaine, $id_indicateur, $id_tranche, $sexe, $mois, $annee]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception('Une donnée existe déjà pour cette combinaison.');
            }
            
            $req = $pdo->prepare("INSERT INTO donnee (id_projet, id_district, id_site, id_domaine, id_indicateur, id_tranche, sexe, valeur, mois, annee, date_enregistrement, saisi_par, etat_donnee) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?)");
            $req->execute([$id_projet, $id_district, $id_site, $id_domaine, $id_indicateur, $id_tranche, $sexe, $valeur, $mois, $annee, $saisi_par, $etat_donnee]);
            
            $message = "Donnée enregistrée avec succès !";
            unset($edit_donnee);
        }
        
        // === MODIFIER ===
        if (isset($_POST['btn_modifier'])) {
            $id = $_POST['sai_id'];
            $id_projet = trim($_POST['sai_id_projet']);
            $id_district = trim($_POST['sai_id_district']);
            $id_site = trim($_POST['sai_id_site']);
            $id_domaine = trim($_POST['sai_id_domaine']);
            $id_indicateur = trim($_POST['sai_id_indicateur']);
            $id_tranche = trim($_POST['sai_id_tranche']);
            $sexe = trim($_POST['sai_sexe']);
            $valeur = trim($_POST['sai_valeur']);
            $mois = trim($_POST['sai_mois']);
            $annee = trim($_POST['sai_annee']);
            $saisi_par = trim($_POST['sai_saisi_par']);
            $etat_donnee = trim($_POST['sai_etat_donnee']);
            
            if (empty($id) || empty($id_projet) || empty($id_district) || empty($id_site) || empty($id_domaine) || empty($id_indicateur) || empty($id_tranche) || empty($sexe) || (empty($valeur) && $valeur !== '0') || empty($mois) || empty($annee) || empty($saisi_par)) {
                throw new Exception('Les champs obligatoires doivent être remplis.');
            }
            
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM donnee WHERE id_projet = ? AND id_district = ? AND id_site = ? AND id_domaine = ? AND id_indicateur = ? AND id_tranche = ? AND sexe = ? AND mois = ? AND annee = ? AND id != ?');
            $stmt->execute([$id_projet, $id_district, $id_site, $id_domaine, $id_indicateur, $id_tranche, $sexe, $mois, $annee, $id]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception('Une donnée existe déjà pour cette combinaison.');
            }
            
            $req = $pdo->prepare("UPDATE donnee SET id_projet = ?, id_district = ?, id_site = ?, id_domaine = ?, id_indicateur = ?, id_tranche = ?, sexe = ?, valeur = ?, mois = ?, annee = ?, saisi_par = ?, etat_donnee = ? WHERE id = ?");
            $req->execute([$id_projet, $id_district, $id_site, $id_domaine, $id_indicateur, $id_tranche, $sexe, $valeur, $mois, $annee, $saisi_par, $etat_donnee, $id]);
            
            $message = 'Donnée modifiée avec succès.';
            unset($edit_donnee);
        }
        
        // === SUPPRIMER ===
        if (isset($_POST['btn_supprimer'])) {
            $id = $_POST['sai_id'];
            
            $req = $pdo->prepare("DELETE FROM donnee WHERE id = ?");
            $req->execute([$id]);
            
            $message = 'Donnée supprimée avec succès.';
            unset($edit_donnee);
        }
        
        // === CHARGER POUR MODIFICATION ===
        if (isset($_GET['edit']) && !empty($_GET['edit'])) {
            $stmt = $pdo->prepare("SELECT * FROM donnee WHERE id = ?");
            $stmt->execute([$_GET['edit']]);
            $edit_donnee = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        
        // === SUPPRESSION VIA GET ===
        if (isset($_GET['supprimer']) && !empty($_GET['supprimer'])) {
            $id = $_GET['supprimer'];
            
            $req = $pdo->prepare("DELETE FROM donnee WHERE id = ?");
            $req->execute([$id]);
            
            header('Location: recherche_donnee.php?success=supprime');
            exit;
        }
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// === CHARGER POUR MODIFICATION VIA GET ===
if (isset($_GET['edit']) && !empty($_GET['edit']) && !$edit_donnee) {
    $stmt = $pdo->prepare("SELECT * FROM donnee WHERE id = ?");
    $stmt->execute([$_GET['edit']]);
    $edit_donnee = $stmt->fetch(PDO::FETCH_ASSOC);
}

// === LISTES ===
$projets = $pdo->query("SELECT * FROM projet ORDER BY titre_projet")->fetchAll(PDO::FETCH_ASSOC);
$districts = $pdo->query("SELECT * FROM district ORDER BY titre_district")->fetchAll(PDO::FETCH_ASSOC);
$sites = $pdo->query("SELECT * FROM site ORDER BY titre_site")->fetchAll(PDO::FETCH_ASSOC);
$domaines = $pdo->query("SELECT * FROM domaine ORDER BY titre_domaine")->fetchAll(PDO::FETCH_ASSOC);
$indicateurs = $pdo->query("SELECT * FROM indicateur ORDER BY titre_indicateur")->fetchAll(PDO::FETCH_ASSOC);
$tranches = $pdo->query("SELECT * FROM tranche_age ORDER BY age_debut")->fetchAll(PDO::FETCH_ASSOC);
$utilisateurs = $pdo->query("SELECT id, nom_prenom FROM utilisateur ORDER BY nom_prenom")->fetchAll(PDO::FETCH_ASSOC);

// === VALEURS PAR DÉFAUT ===
$id = $edit_donnee['id'] ?? '';
$id_projet = $edit_donnee['id_projet'] ?? '';
$id_district = $edit_donnee['id_district'] ?? '';
$id_site = $edit_donnee['id_site'] ?? '';
$id_domaine = $edit_donnee['id_domaine'] ?? '';
$id_indicateur = $edit_donnee['id_indicateur'] ?? '';
$id_tranche = $edit_donnee['id_tranche'] ?? '';
$sexe = $edit_donnee['sexe'] ?? '';
$valeur = $edit_donnee['valeur'] ?? '';
$mois = $edit_donnee['mois'] ?? '';
$annee = $edit_donnee['annee'] ?? '';
$saisi_par = $edit_donnee['saisi_par'] ?? '';
$etat_donnee = $edit_donnee['etat_donnee'] ?? 'ACTIF';
$is_edit = ($edit_donnee !== null);

// Mois disponibles
$mois_list = ['Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'];
$annee_list = range(date('Y') - 10, date('Y') + 1);
$sexes = ['M', 'F', 'Tous'];
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $is_edit ? 'Modifier' : 'Ajouter' ?> une Donnée</title>
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
        .badge-active { background: #198754; color: white; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; }
        .badge-inactive { background: #dc3545; color: white; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; }
    </style>
</head>
<body>

<div class="container my-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-0"><?= $is_edit ? '✏️ Modifier la donnée' : '📝 Ajouter une donnée' ?></h4>
            <div class="text-muted small"><?= $is_edit ? 'Modifier les informations de la donnée' : 'Enregistrer une nouvelle donnée' ?></div>
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
                <form method="POST" id="donneeForm">
                    <?php if ($is_edit): ?>
                        <input type="hidden" name="sai_id" value="<?= htmlspecialchars($id) ?>">
                    <?php endif; ?>
                    
                    <div class="row g-3">
                        <div class="col-12">
                            <div class="section-title"><i class="bi bi-link me-2"></i>Contexte</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label required">Projet</label>
                            <select name="sai_id_projet" id="sai_id_projet" class="form-select" required style="width:100%;">
                                <option value="">-- Sélectionnez --</option>
                                <?php foreach ($projets as $p): ?>
                                    <option value="<?= $p['id_projet'] ?>" <?= ($id_projet == $p['id_projet']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($p['titre_projet']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label required">District</label>
                            <select name="sai_id_district" id="sai_id_district" class="form-select" required style="width:100%;">
                                <option value="">-- Sélectionnez --</option>
                                <?php foreach ($districts as $d): ?>
                                    <option value="<?= $d['id_district'] ?>" <?= ($id_district == $d['id_district']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($d['titre_district']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label required">Site</label>
                            <select name="sai_id_site" id="sai_id_site" class="form-select" required style="width:100%;">
                                <option value="">-- Sélectionnez --</option>
                                <?php foreach ($sites as $s): ?>
                                    <option value="<?= $s['id_site'] ?>" <?= ($id_site == $s['id_site']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($s['titre_site']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label required">Domaine</label>
                            <select name="sai_id_domaine" id="sai_id_domaine" class="form-select" required style="width:100%;">
                                <option value="">-- Sélectionnez --</option>
                                <?php foreach ($domaines as $d): ?>
                                    <option value="<?= $d['id_domaine'] ?>" <?= ($id_domaine == $d['id_domaine']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($d['titre_domaine']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-12 mt-2">
                            <div class="section-title"><i class="bi bi-bar-chart me-2"></i>Indicateur</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label required">Indicateur</label>
                            <select name="sai_id_indicateur" id="sai_id_indicateur" class="form-select" required style="width:100%;">
                                <option value="">-- Sélectionnez --</option>
                                <?php foreach ($indicateurs as $i): ?>
                                    <option value="<?= $i['id_indicateur'] ?>" <?= ($id_indicateur == $i['id_indicateur']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($i['titre_indicateur']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label required">Tranche d'âge</label>
                            <select name="sai_id_tranche" id="sai_id_tranche" class="form-select" required style="width:100%;">
                                <option value="">-- Sélectionnez --</option>
                                <?php foreach ($tranches as $t): ?>
                                    <option value="<?= $t['id_tranche'] ?>" <?= ($id_tranche == $t['id_tranche']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($t['titre_debut'] . ' - ' . $t['titre_fin']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-12 mt-2">
                            <div class="section-title"><i class="bi bi-person me-2"></i>Valeur</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label required">Sexe</label>
                            <select name="sai_sexe" id="sai_sexe" class="form-select" required>
                                <option value="">-- Sélectionnez --</option>
                                <?php foreach ($sexes as $s): ?>
                                    <option value="<?= $s ?>" <?= ($sexe == $s) ? 'selected' : '' ?>><?= $s ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label required">Valeur</label>
                            <input type="number" step="0.01" class="form-control" id="sai_valeur" name="sai_valeur" 
                                   value="<?= htmlspecialchars($valeur) ?>" required>
                        </div>
                        
                        <div class="col-12 mt-2">
                            <div class="section-title"><i class="bi bi-calendar me-2"></i>Période</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label required">Mois</label>
                            <select name="sai_mois" id="sai_mois" class="form-select" required>
                                <option value="">-- Sélectionnez --</option>
                                <?php foreach ($mois_list as $m): ?>
                                    <option value="<?= $m ?>" <?= ($mois == $m) ? 'selected' : '' ?>><?= $m ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label required">Année</label>
                            <select name="sai_annee" id="sai_annee" class="form-select" required>
                                <option value="">-- Sélectionnez --</option>
                                <?php foreach ($annee_list as $a): ?>
                                    <option value="<?= $a ?>" <?= ($annee == $a) ? 'selected' : '' ?>><?= $a ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label required">Saisi par</label>
                            <select name="sai_saisi_par" id="sai_saisi_par" class="form-select" required style="width:100%;">
                                <option value="">-- Sélectionnez --</option>
                                <?php foreach ($utilisateurs as $u): ?>
                                    <option value="<?= $u['id'] ?>" <?= ($saisi_par == $u['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($u['nom_prenom']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-12 mt-2">
                            <div class="section-title"><i class="bi bi-toggle-on me-2"></i>Statut</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label required">État de la donnée</label>
                            <select name="sai_etat_donnee" id="sai_etat_donnee" class="form-select" required>
                                <option value="ACTIF" <?= ($etat_donnee == 'ACTIF') ? 'selected' : '' ?>>ACTIF</option>
                                <option value="INACTIF" <?= ($etat_donnee == 'INACTIF') ? 'selected' : '' ?>>INACTIF</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-center gap-2 mt-4">
                        <?php if ($is_edit): ?>
                            <button type="submit" name="btn_modifier" class="btn btn-temenos px-4">
                                <i class="bi bi-save me-2"></i>Modifier
                            </button>
                            <button type="submit" name="btn_supprimer" class="btn btn-danger-cbs px-4" 
                                    onclick="return confirm('⚠️ Supprimer cette donnée ? Cette action est irréversible.')">
                                <i class="bi bi-trash me-2"></i>Supprimer
                            </button>
                            <a href="enregistrement_donnee.php" class="btn btn-outline-cbs px-4">
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
                            <strong>Enregistrement de donnée</strong>
                            <p class="text-muted small mb-0">Une combinaison unique est vérifiée pour éviter les doublons</p>
                        </div>
                    </div>
                    <div class="d-flex align-items-center mb-2">
                        <div class="bg-success bg-opacity-10 rounded-circle p-2 me-2">
                            <i class="bi bi-calendar text-success"></i>
                        </div>
                        <div>
                            <strong>Période</strong>
                            <p class="text-muted small mb-0">Mois et Année de la donnée</p>
                        </div>
                    </div>
                    <div class="d-flex align-items-center">
                        <div class="bg-danger bg-opacity-10 rounded-circle p-2 me-2">
                            <i class="bi bi-shield text-danger"></i>
                        </div>
                        <div>
                            <strong>Validation</strong>
                            <p class="text-muted small mb-0">La combinaison projet/district/site/domaine/indicateur/tranche/sexe/mois/année doit être unique</p>
                        </div>
                    </div>
                </div>
                
                <hr>
                
                <div class="alert alert-info small mt-3 mb-0">
                    <i class="bi bi-info-circle me-2"></i>
                    <strong>Conseil :</strong> Assurez-vous que tous les champs sont correctement renseignés avant d'enregistrer.
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Select2
    $('#sai_id_projet').select2({ theme: 'default', width: '100%', placeholder: 'Sélectionnez un projet', allowClear: true });
    $('#sai_id_district').select2({ theme: 'default', width: '100%', placeholder: 'Sélectionnez un district', allowClear: true });
    $('#sai_id_site').select2({ theme: 'default', width: '100%', placeholder: 'Sélectionnez un site', allowClear: true });
    $('#sai_id_domaine').select2({ theme: 'default', width: '100%', placeholder: 'Sélectionnez un domaine', allowClear: true });
    $('#sai_id_indicateur').select2({ theme: 'default', width: '100%', placeholder: 'Sélectionnez un indicateur', allowClear: true });
    $('#sai_id_tranche').select2({ theme: 'default', width: '100%', placeholder: 'Sélectionnez une tranche', allowClear: true });
    $('#sai_saisi_par').select2({ theme: 'default', width: '100%', placeholder: 'Sélectionnez un utilisateur', allowClear: true });
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>