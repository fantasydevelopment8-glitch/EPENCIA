<?php
require_once 'database/database.php';


// ============================================================
// HANDLERS AJAX
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');
    
    if ($_POST['ajax_action'] === 'generate_id') {
        $year = date('y');
        $stmt = $pdo->prepare("SELECT id FROM utilisateur WHERE id LIKE ? ORDER BY id DESC LIMIT 1");
        $stmt->execute(['USR' . $year . '%']);
        $last = $stmt->fetchColumn();
        $num = $last ? str_pad(intval(substr($last, -4)) + 1, 4, '0', STR_PAD_LEFT) : '0001';
        $id = 'USR' . $year . $num;
        echo json_encode(['success' => true, 'id' => $id]);
        exit;
    }
}

// ============================================================
// TRAITEMENT DES ACTIONS CRUD
// ============================================================
$message = '';
$error = '';
$edit_utilisateur = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        
        // === AJOUTER ===
        if (isset($_POST['btn_ajouter'])) {
            $id = trim($_POST['sai_id']);
            $login = trim($_POST['sai_login']);
            $nom_prenom = trim($_POST['sai_nom_prenom']);
            $email = trim($_POST['sai_email']);
            $mdp = trim($_POST['sai_mdp']);
            $telephone = trim($_POST['sai_telephone']);
            $matricule = trim($_POST['sai_matricule']);
            $role = trim($_POST['sai_role']);
            $fonction = trim($_POST['sai_fonction']);
            $entreprise = trim($_POST['sai_entreprise']);
            $activite = trim($_POST['sai_activite']);
            $etat = trim($_POST['sai_etat']);
            
            if (empty($id)) throw new Exception('L\'ID utilisateur est obligatoire.');
            if (empty($login)) throw new Exception('Le login est obligatoire.');
            if (empty($nom_prenom)) throw new Exception('Le nom et prénom sont obligatoires.');
            if (empty($mdp)) throw new Exception('Le mot de passe est obligatoire.');
            if (empty($role)) throw new Exception('Le rôle est obligatoire.');
            
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM utilisateur WHERE id = ?');
            $stmt->execute([$id]);
            if ($stmt->fetchColumn() > 0) throw new Exception('Cet ID utilisateur est déjà utilisé.');
            
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM utilisateur WHERE login = ?');
            $stmt->execute([$login]);
            if ($stmt->fetchColumn() > 0) throw new Exception('Ce login est déjà utilisé.');
            
            if (!empty($email)) {
                $stmt = $pdo->prepare('SELECT COUNT(*) FROM utilisateur WHERE email = ?');
                $stmt->execute([$email]);
                if ($stmt->fetchColumn() > 0) throw new Exception('Cet email est déjà utilisé.');
            }
            
            
            $req = $pdo->prepare("INSERT INTO utilisateur (id, login, nom_prenom, email, mdp, telephone, matricule, role, fonction, entreprise, activite, date_creation, etat) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)");
            $req->execute([$id, $login, $nom_prenom, $email, $mdp, $telephone, $matricule, $role, $fonction, $entreprise, $activite, $etat]);
            
            $message = "Utilisateur <strong>$nom_prenom</strong> créé avec succès !";
            unset($edit_utilisateur);
        }
        
        // === MODIFIER ===
        if (isset($_POST['btn_modifier'])) {
            $id = $_POST['sai_id'];
            $login = trim($_POST['sai_login']);
            $nom_prenom = trim($_POST['sai_nom_prenom']);
            $email = trim($_POST['sai_email']);
            $mdp = trim($_POST['sai_mdp']);
            $telephone = trim($_POST['sai_telephone']);
            $matricule = trim($_POST['sai_matricule']);
            $role = trim($_POST['sai_role']);
            $fonction = trim($_POST['sai_fonction']);
            $entreprise = trim($_POST['sai_entreprise']);
            $activite = trim($_POST['sai_activite']);
            $etat = trim($_POST['sai_etat']);
            
            if (empty($id) || empty($login) || empty($nom_prenom) || empty($role)) {
                throw new Exception('Les champs obligatoires doivent être remplis.');
            }
            
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM utilisateur WHERE login = ? AND id != ?');
            $stmt->execute([$login, $id]);
            if ($stmt->fetchColumn() > 0) throw new Exception('Ce login est déjà utilisé.');
            
            if (!empty($email)) {
                $stmt = $pdo->prepare('SELECT COUNT(*) FROM utilisateur WHERE email = ? AND id != ?');
                $stmt->execute([$email, $id]);
                if ($stmt->fetchColumn() > 0) throw new Exception('Cet email est déjà utilisé.');
            }
            
            if (!empty($mdp)) {
                $sqlMdp = ", mdp = ?";
                $params = [$login, $nom_prenom, $email, $telephone, $matricule, $role, $fonction, $entreprise, $activite, $etat, $mdp, $id];
            } else {
                $sqlMdp = "";
                $params = [$login, $nom_prenom, $email, $telephone, $matricule, $role, $fonction, $entreprise, $activite, $etat, $id];
            }
            
            $sql = "UPDATE utilisateur SET login = ?, nom_prenom = ?, email = ?, telephone = ?, matricule = ?, role = ?, fonction = ?, entreprise = ?, activite = ?, etat = ? $sqlMdp WHERE id = ?";
            $req = $pdo->prepare($sql);
            $req->execute($params);
            
            $message = 'Utilisateur modifié avec succès.';
            unset($edit_utilisateur);
        }
        
        // === SUPPRIMER ===
        if (isset($_POST['btn_supprimer'])) {
            $id = $_POST['sai_id'];
            
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM utilisateur_projet WHERE utilisateur = ?');
            $stmt->execute([$id]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception("Cet utilisateur est associé à des projets.");
            }
            
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM donnee WHERE saisi_par = ?');
            $stmt->execute([$id]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception("Cet utilisateur a saisi des données.");
            }
            
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user = ?');
            $stmt->execute([$id]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception("Cet utilisateur a des notifications.");
            }
            
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM vue WHERE user = ?');
            $stmt->execute([$id]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception("Cet utilisateur a des vues de notifications.");
            }
            
            $req = $pdo->prepare("DELETE FROM utilisateur WHERE id = ?");
            $req->execute([$id]);
            
            $message = 'Utilisateur supprimé avec succès.';
            unset($edit_utilisateur);
        }
        
        // === CHARGER POUR MODIFICATION ===
        if (isset($_POST['edit_id'])) {
            $stmt = $pdo->prepare("SELECT * FROM utilisateur WHERE id = ?");
            $stmt->execute([$_POST['edit_id']]);
            $edit_utilisateur = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        // === RÉINITIALISER LES FILTRES ===
        if (isset($_POST['reset_filters'])) {
            unset($_SESSION['utilisateur_filters']);
        }
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// ============================================================
// GESTION DES FILTRES ET PAGINATION
// ============================================================
$limit = 10;
$page = max(1, (int)($_POST['page'] ?? $_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;
$search = trim($_POST['search'] ?? $_SESSION['utilisateur_filters']['search'] ?? '');
$filter_role = trim($_POST['role'] ?? $_SESSION['utilisateur_filters']['role'] ?? '');
$filter_etat = trim($_POST['etat'] ?? $_SESSION['utilisateur_filters']['etat'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['apply_filters'])) {
    $_SESSION['utilisateur_filters'] = [
        'search' => $search,
        'role' => $filter_role,
        'etat' => $filter_etat
    ];
}

$offset = ($page - 1) * $limit;

// === LISTES POUR LES FILTRES ===
$roles = ['Administrateur', 'Superviseur', 'Agent', 'Visiteur'];
$etats = ['ACTIF', 'INACTIF', 'BLOQUE'];

// === COMPTER TOTAL ===
$count_sql = 'SELECT COUNT(*) FROM utilisateur';
$count_params = [];
$where_clauses = [];

if (!empty($search)) {
    $where_clauses[] = '(id LIKE ? OR login LIKE ? OR nom_prenom LIKE ? OR email LIKE ? OR fonction LIKE ? OR entreprise LIKE ?)';
    $search_term = "%$search%";
    $count_params = array_merge($count_params, [$search_term, $search_term, $search_term, $search_term, $search_term, $search_term]);
}
if (!empty($filter_role)) {
    $where_clauses[] = 'role = ?';
    $count_params[] = $filter_role;
}
if (!empty($filter_etat)) {
    $where_clauses[] = 'etat = ?';
    $count_params[] = $filter_etat;
}
if (!empty($where_clauses)) {
    $count_sql .= ' WHERE ' . implode(' AND ', $where_clauses);
}

$stmt = $pdo->prepare($count_sql);
$stmt->execute($count_params);
$total_utilisateurs = $stmt->fetchColumn();
$total_pages = max(1, ceil($total_utilisateurs / $limit));

// === RÉCUPÉRER LES UTILISATEURS ===
$sql = 'SELECT * FROM utilisateur';
$params = [];
$where_clauses = [];

if (!empty($search)) {
    $where_clauses[] = '(id LIKE ? OR login LIKE ? OR nom_prenom LIKE ? OR email LIKE ? OR fonction LIKE ? OR entreprise LIKE ?)';
    $search_term = "%$search%";
    $params = array_merge($params, [$search_term, $search_term, $search_term, $search_term, $search_term, $search_term]);
}
if (!empty($filter_role)) {
    $where_clauses[] = 'role = ?';
    $params[] = $filter_role;
}
if (!empty($filter_etat)) {
    $where_clauses[] = 'etat = ?';
    $params[] = $filter_etat;
}
if (!empty($where_clauses)) {
    $sql .= ' WHERE ' . implode(' AND ', $where_clauses);
}
$sql .= ' ORDER BY nom_prenom ASC LIMIT ? OFFSET ?';

$stmt = $pdo->prepare($sql);
$paramIndex = 1;
foreach ($params as $param) {
    $stmt->bindValue($paramIndex++, $param, PDO::PARAM_STR);
}
$stmt->bindValue($paramIndex++, $limit, PDO::PARAM_INT);
$stmt->bindValue($paramIndex++, $offset, PDO::PARAM_INT);
$stmt->execute();
$utilisateurs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// === STATISTIQUES ===
$stmt = $pdo->query("SELECT COUNT(*) FROM utilisateur");
$total = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM utilisateur WHERE etat = 'ACTIF'");
$actifs = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM utilisateur WHERE etat = 'INACTIF'");
$inactifs = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM utilisateur WHERE etat = 'BLOQUE'");
$bloques = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(DISTINCT role) FROM utilisateur");
$total_roles = $stmt->fetchColumn();

// Récupérer le rôle le plus utilisé
$stmt = $pdo->query("SELECT role, COUNT(*) as nb FROM utilisateur GROUP BY role ORDER BY nb DESC LIMIT 1");
$role_plus_utilise = $stmt->fetch(PDO::FETCH_ASSOC);

// Générer ID par défaut
$year = date('y');
$stmt = $pdo->prepare("SELECT id FROM utilisateur WHERE id LIKE ? ORDER BY id DESC LIMIT 1");
$stmt->execute(['USR' . $year . '%']);
$last = $stmt->fetchColumn();
$num = $last ? str_pad(intval(substr($last, -4)) + 1, 4, '0', STR_PAD_LEFT) : '0001';
$generated_id = 'USR' . $year . $num;
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Utilisateurs</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <style>
        :root {
            --light-bg: #f5f5f5;
            --light-border: #e0e0e0;
            --light-blue: #0366d6;
            --light-text: #24292e;
            --light-green: #28a745;
            --light-hover: #f6f8fa;
            --light-text-secondary: #6a737d;
            --light-card-bg: #ffffff;
            --light-red: #dc3545;
            --light-orange: #fd7e14;
        }

        body { background-color: var(--light-bg); font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        
        .github-header { padding-bottom: 16px; border-bottom: 1px solid var(--light-border); margin-bottom: 24px; }
        .github-card {
            background-color: var(--light-card-bg);
            border: 1px solid var(--light-border);
            border-radius: 8px;
            padding: 16px;
            transition: all 0.2s ease;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }
        .github-card:hover { border-color: var(--light-blue); box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1); }
        .github-btn {
            background-color: var(--light-green);
            color: white;
            border: 1px solid rgba(27, 31, 36, 0.15);
            border-radius: 6px;
            padding: 5px 10px;
            font-size: 12px;
            font-weight: 500;
            transition: all 0.2s ease;
        }
        .github-btn:hover { background-color: #2ea043; color: white; }
        .github-btn-search {
            background-color: var(--light-blue);
            color: white;
            border: 1px solid rgba(27, 31, 36, 0.15);
            border-radius: 6px;
            padding: 8px 16px;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s ease;
            height: 38px;
        }
        .github-btn-search:hover { background-color: #0353b3; color: white; }
        
        .badge-success { background-color: #28a745; color: white; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; }
        .badge-danger { background-color: #dc3545; color: white; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; }
        .badge-warning { background-color: #fd7e14; color: white; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; }
        .badge-info { background-color: #17a2b8; color: white; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; }
        .badge-primary { background-color: #0366d6; color: white; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; }
        .badge-secondary { background-color: #6c757d; color: white; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; }
        
        .table th { font-weight: 600; font-size: 13px; color: var(--light-text-secondary); white-space: nowrap; }
        .table td { font-size: 13px; vertical-align: middle; }
        
        .pagination .page-link { color: var(--light-text); border-color: var(--light-border); cursor: pointer; }
        .pagination .page-item.active .page-link { background-color: var(--light-blue); border-color: var(--light-blue); color: white; }
        .pagination .page-item.disabled .page-link { cursor: not-allowed; opacity: 0.6; }
        
        .stat-card {
            text-align: center;
            padding: 15px;
            border-radius: 8px;
            background: var(--light-card-bg);
            border: 1px solid var(--light-border);
        }
        .stat-card .stat-value { font-size: 24px; font-weight: bold; }
        .stat-card .stat-label { font-size: 12px; color: var(--light-text-secondary); }
        .stat-card .stat-sub { font-size: 11px; color: var(--light-text-secondary); }
        
        .section-title { font-size: 0.85rem; font-weight: 600; color: #0a4d8c; background: #e9ecef; padding: 8px 12px; border-radius: 6px; margin-bottom: 15px; }
        .required:after { content: " *"; color: #dc3545; }
        .code-input-group { display: flex; gap: 8px; align-items: center; }
        .code-input-group .form-control { flex: 1; font-family: 'Courier New', monospace; font-weight: 600; color: #0a4d8c; }
        .btn-generate-id { white-space: nowrap; background: #0a4d8c; color: white; border: none; padding: 6px 12px; border-radius: 6px; font-size: 0.85rem; }
        .btn-generate-id:hover { background: #05101c; color: white; }
        .loading-spinner { display: inline-block; width: 14px; height: 14px; border: 2px solid #f3f3f3; border-top: 2px solid white; border-radius: 50%; animation: spin 1s linear infinite; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        
        .modal-lg { max-width: 800px; }
        .select2-container--default .select2-selection--single { border-color: #dee2e6; border-radius: 6px; height: 38px; }
        .select2-container--default .select2-selection--single .select2-selection__rendered { line-height: 38px; }
        .select2-container--default .select2-selection--single .select2-selection__arrow { height: 38px; }
    </style>
</head>
<body>

<main class="container-fluid my-4 px-4">
    <div class="github-header d-flex justify-content-between align-items-center">
        <div>
            <h4 class="mb-0">👤 Gestion des Utilisateurs</h4>
            <div class="text-muted small">Consulter, ajouter, modifier et supprimer les utilisateurs</div>
        </div>
        <button class="btn github-btn" data-bs-toggle="modal" data-bs-target="#utilisateurModal" onclick="resetForm()">
            <i class="bi bi-plus-circle me-1"></i>Nouvel utilisateur
        </button>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="bi bi-check-circle-fill me-2"></i><?= $message ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Statistiques -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-value text-info"><?= $total ?></div>
                <div class="stat-label">Total utilisateurs</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-value text-success"><?= $actifs ?></div>
                <div class="stat-label">Utilisateurs actifs</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-value text-danger"><?= $inactifs ?></div>
                <div class="stat-label">Utilisateurs inactifs</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-value text-warning"><?= $bloques ?></div>
                <div class="stat-label">Utilisateurs bloqués</div>
                <div class="stat-sub"><?= $total_roles ?> rôles • <?= $role_plus_utilise ? $role_plus_utilise['role'] : '-' ?> principal</div>
            </div>
        </div>
    </div>

    <!-- Filtres -->
    <form method="POST" action="" id="filterForm">
        <div class="github-card mb-4">
            <h5 class="mb-3"><i class="bi bi-funnel me-2"></i>Filtres</h5>
            <div class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label">Recherche</label>
                    <input type="text" class="form-control" name="search" value="<?= htmlspecialchars($search) ?>" 
                           placeholder="ID, login, nom, email, fonction...">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Rôle</label>
                    <select name="role" class="form-control">
                        <option value="">Tous</option>
                        <?php foreach ($roles as $r): ?>
                            <option value="<?= $r ?>" <?= $filter_role == $r ? 'selected' : '' ?>><?= $r ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">État</label>
                    <select name="etat" class="form-control">
                        <option value="">Tous</option>
                        <?php foreach ($etats as $e): ?>
                            <option value="<?= $e ?>" <?= $filter_etat == $e ? 'selected' : '' ?>><?= $e ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <div class="d-flex gap-2">
                        <button type="submit" name="apply_filters" class="btn github-btn-search w-100">
                            <i class="bi bi-search me-2"></i>Filtrer
                        </button>
                        <button type="submit" name="reset_filters" class="btn btn-outline-secondary" title="Réinitialiser">
                            <i class="bi bi-arrow-repeat"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </form>

    <!-- Tableau -->
    <div class="github-card">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0"><i class="bi bi-people me-2"></i>Liste des utilisateurs (<?= $total_utilisateurs ?>)</h5>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nom</th>
                        <th>Login</th>
                        <th>Email</th>
                        <th>Téléphone</th>
                        <th>Rôle</th>
                        <th>Fonction</th>
                        <th>Entreprise</th>
                        <th>État</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($utilisateurs)): ?>
                        <tr><td colspan="10" class="text-center text-muted py-4"><i class="bi bi-inbox me-2"></i>Aucun utilisateur trouvé.</td></tr>
                    <?php else: foreach ($utilisateurs as $u): ?>
                        <tr>
                            <td><span class="fw-bold"><?= htmlspecialchars($u['id']) ?></span></td>
                            <td><strong><?= htmlspecialchars($u['nom_prenom']) ?></strong></td>
                            <td><span class="badge badge-secondary"><?= htmlspecialchars($u['login']) ?></span></td>
                            <td><?= htmlspecialchars($u['email'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($u['telephone'] ?? '-') ?></td>
                            <td><span class="badge badge-primary"><?= htmlspecialchars($u['role']) ?></span></td>
                            <td><?= htmlspecialchars($u['fonction'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($u['entreprise'] ?? '-') ?></td>
                            <td>
                                <span class="badge <?= $u['etat'] == 'ACTIF' ? 'badge-success' : ($u['etat'] == 'BLOQUE' ? 'badge-warning' : 'badge-danger') ?>">
                                    <?= $u['etat'] ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <div class="btn-group">
                                    <a href="enregistrement_utilisateur.php?edit=<?= $u['id'] ?>" 
                                       class="btn btn-sm btn-outline-primary" title="Modifier">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <a href="enregistrement_utilisateur.php?supprimer=<?= $u['id'] ?>" 
                                       class="btn btn-sm btn-outline-danger" 
                                       onclick="return confirm('⚠️ Supprimer cet utilisateur ?')" title="Supprimer">
                                        <i class="bi bi-trash"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <nav class="mt-4">
                <ul class="pagination justify-content-center">
                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                        <form method="POST" class="d-inline page-form">
                            <input type="hidden" name="page" value="<?= $page - 1 ?>">
                            <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
                            <input type="hidden" name="role" value="<?= htmlspecialchars($filter_role) ?>">
                            <input type="hidden" name="etat" value="<?= htmlspecialchars($filter_etat) ?>">
                            <button type="submit" class="page-link" <?= $page <= 1 ? 'disabled' : '' ?>>
                                <i class="bi bi-chevron-left"></i> Précédent
                            </button>
                        </form>
                    </li>
                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                            <form method="POST" class="d-inline page-form">
                                <input type="hidden" name="page" value="<?= $i ?>">
                                <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
                                <input type="hidden" name="role" value="<?= htmlspecialchars($filter_role) ?>">
                                <input type="hidden" name="etat" value="<?= htmlspecialchars($filter_etat) ?>">
                                <button type="submit" class="page-link"><?= $i ?></button>
                            </form>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                        <form method="POST" class="d-inline page-form">
                            <input type="hidden" name="page" value="<?= $page + 1 ?>">
                            <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
                            <input type="hidden" name="role" value="<?= htmlspecialchars($filter_role) ?>">
                            <input type="hidden" name="etat" value="<?= htmlspecialchars($filter_etat) ?>">
                            <button type="submit" class="page-link" <?= $page >= $total_pages ? 'disabled' : '' ?>>
                                Suivant <i class="bi bi-chevron-right"></i>
                            </button>
                        </form>
                    </li>
                </ul>
            </nav>
        <?php endif; ?>
    </div>
</main>

<!-- ============================================================ -->
<!-- MODAL CRUD UTILISATEUR -->
<!-- ============================================================ -->
<div class="modal fade" id="utilisateurModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">
                    <i class="bi bi-person-plus me-2"></i>Nouvel utilisateur
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="utilisateurForm">
                <div class="modal-body">
                    <input type="hidden" name="btn_ajouter" id="btn_action" value="1">
                    
                    <div class="row g-3">
                        <div class="col-12">
                            <div class="section-title"><i class="bi bi-hash me-2"></i>Identification</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label required">ID Utilisateur</label>
                            <div class="code-input-group">
                                <input type="text" class="form-control" id="sai_id" name="sai_id" 
                                       value="<?= $generated_id ?>" required maxlength="20" readonly>
                                <button type="button" id="generateIdBtn" class="btn-generate-id">
                                    <i class="bi bi-arrow-repeat"></i>
                                </button>
                            </div>
                            <small class="text-muted"><i class="bi bi-info-circle"></i> Format: USR + Année + 4 chiffres</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label required">Login</label>
                            <input type="text" class="form-control" id="sai_login" name="sai_login" 
                                   value="" required maxlength="50" placeholder="Ex: john.doe">
                        </div>
                        
                        <div class="col-12 mt-2">
                            <div class="section-title"><i class="bi bi-person me-2"></i>Informations personnelles</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label required">Nom et Prénom</label>
                            <input type="text" class="form-control" id="sai_nom_prenom" name="sai_nom_prenom" 
                                   value="" required maxlength="100" placeholder="Ex: John DOE">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" id="sai_email" name="sai_email" 
                                   value="" maxlength="100" placeholder="Ex: john.doe@email.com">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Téléphone</label>
                            <input type="text" class="form-control" id="sai_telephone" name="sai_telephone" 
                                   value="" maxlength="20" placeholder="Ex: +225 07 00 00 00 00">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Matricule</label>
                            <input type="text" class="form-control" id="sai_matricule" name="sai_matricule" 
                                   value="" maxlength="50" placeholder="Ex: MAT-001">
                        </div>
                        
                        <div class="col-12 mt-2">
                            <div class="section-title"><i class="bi bi-briefcase me-2"></i>Informations professionnelles</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label required">Rôle</label>
                            <select name="sai_role" id="sai_role" class="form-select" required>
                                <option value="">-- Sélectionnez un rôle --</option>
                                <option value="Administrateur">Administrateur</option>
                                <option value="Superviseur">Superviseur</option>
                                <option value="Agent">Agent</option>
                                <option value="Visiteur">Visiteur</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Fonction</label>
                            <input type="text" class="form-control" id="sai_fonction" name="sai_fonction" 
                                   value="" maxlength="100" placeholder="Ex: Chef de projet">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Entreprise</label>
                            <input type="text" class="form-control" id="sai_entreprise" name="sai_entreprise" 
                                   value="" maxlength="100" placeholder="Ex: ABC Corporation">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Activité</label>
                            <input type="text" class="form-control" id="sai_activite" name="sai_activite" 
                                   value="" maxlength="100" placeholder="Ex: Gestion de projet">
                        </div>
                        
                        <div class="col-12 mt-2">
                            <div class="section-title"><i class="bi bi-lock me-2"></i>Sécurité</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label required">Mot de passe</label>
                            <input type="password" class="form-control" id="sai_mdp" name="sai_mdp" 
                                   required placeholder="Mot de passe">
                        </div>
                        
                        <div class="col-12 mt-2">
                            <div class="section-title"><i class="bi bi-toggle-on me-2"></i>Statut</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label required">État de l'utilisateur</label>
                            <select name="sai_etat" id="sai_etat" class="form-select" required>
                                <option value="ACTIF" selected>ACTIF</option>
                                <option value="INACTIF">INACTIF</option>
                                <option value="BLOQUE">BLOQUÉ</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn github-btn" id="submitBtn">Ajouter</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Select2 pour les filtres
    $('select[name="role"]').select2({
        theme: 'default',
        width: '100%',
        placeholder: 'Tous',
        allowClear: true
    });
    $('select[name="etat"]').select2({
        theme: 'default',
        width: '100%',
        placeholder: 'Tous',
        allowClear: true
    });
    
    // Génération ID
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
});

function resetForm() {
    $('#utilisateurForm')[0].reset();
    $('#btn_action').val('1');
    $('#sai_id').val('<?= $generated_id ?>');
    $('#sai_etat').val('ACTIF');
    $('#modalTitle').html('<i class="bi bi-person-plus me-2"></i>Nouvel utilisateur');
    $('#submitBtn').text('Ajouter').removeClass('btn-warning').addClass('github-btn');
    $('#sai_id').prop('readonly', true);
}

<?php if ($edit_utilisateur): ?>
$(document).ready(function() {
    $('#modalTitle').html('<i class="bi bi-pencil me-2"></i>Modifier l\'utilisateur');
    $('#submitBtn').text('Modifier').removeClass('github-btn').addClass('btn-warning');
    $('#btn_action').val('0');
    
    $('#sai_id').val('<?= addslashes($edit_utilisateur['id']) ?>').prop('readonly', true);
    $('#sai_login').val('<?= addslashes($edit_utilisateur['login']) ?>');
    $('#sai_nom_prenom').val('<?= addslashes($edit_utilisateur['nom_prenom']) ?>');
    $('#sai_email').val('<?= addslashes($edit_utilisateur['email']) ?>');
    $('#sai_telephone').val('<?= addslashes($edit_utilisateur['telephone']) ?>');
    $('#sai_matricule').val('<?= addslashes($edit_utilisateur['matricule']) ?>');
    $('#sai_role').val('<?= addslashes($edit_utilisateur['role']) ?>');
    $('#sai_fonction').val('<?= addslashes($edit_utilisateur['fonction']) ?>');
    $('#sai_entreprise').val('<?= addslashes($edit_utilisateur['entreprise']) ?>');
    $('#sai_activite').val('<?= addslashes($edit_utilisateur['activite']) ?>');
    $('#sai_etat').val('<?= addslashes($edit_utilisateur['etat']) ?>');
    $('#sai_mdp').prop('required', false).attr('placeholder', 'Laisser vide pour conserver');
    
    $('#utilisateurModal').modal('show');
});
<?php endif; ?>
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>