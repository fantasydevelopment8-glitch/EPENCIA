<?php
// ================================================================
// GESTION DES FACTURES - EPENCIA SGI
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
        'payé' => ['class' => 'success', 'label' => 'Payé'],
        'impayé' => ['class' => 'danger', 'label' => 'Impayé'],
        'partiel' => ['class' => 'warning', 'label' => 'Partiel'],
        'annulé' => ['class' => 'secondary', 'label' => 'Annulé'],
        'en attente' => ['class' => 'info', 'label' => 'En attente']
    ];
    $status = strtolower($status ?? 'en attente');
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

// ================================================================
// 3. REQUÊTES PRINCIPALES
// ================================================================

// ── Requête principale ──
$sql = "SELECT
            f.facture_id,
            c.nom_prenom AS client,
            c.telephone AS client_telephone,
            c.email AS client_email,
            c.adresse AS client_adresse,
            o.nom AS organisme,
            o.adresse AS organisme_adresse,
            o.telephone AS organisme_telephone,
            u.nom_prenom AS utilisateur,
            f.date,
            f.heure,
            f.statut
        FROM factures f
        LEFT JOIN clients c ON c.client_id = f.client_id
        LEFT JOIN organismes o ON o.organisme_id = f.organisme_id
        LEFT JOIN utilisateurs u ON u.utilisateur_id = f.utilisateur_id
        ORDER BY f.date DESC, f.heure DESC";

// ── Stats ──
$reqStats = $pdo->query("SELECT statut, COUNT(*) as nb FROM factures GROUP BY statut");
$total = $payes = $impayes = $partiels = $annules = 0;
while($s = $reqStats->fetch(PDO::FETCH_ASSOC)){
    $total += $s['nb'];
    if($s['statut'] === 'payé') $payes = $s['nb'];
    if($s['statut'] === 'impayé') $impayes = $s['nb'];
    if($s['statut'] === 'partiel') $partiels = $s['nb'];
    if($s['statut'] === 'annulé') $annules = $s['nb'];
}

$req = $pdo->query($sql);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Factures - Epencia SGI</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=DM+Serif+Display:ital@0;1&display=swap" rel="stylesheet">
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

        /* ============================================================ */
        /* STATS CARDS                                                  */
        /* ============================================================ */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 14px;
            margin-bottom: 24px;
        }

        .stat-card {
            background: var(--medical-white);
            padding: 16px 18px;
            border-radius: var(--medical-radius);
            box-shadow: var(--medical-shadow);
            border: 1px solid var(--medical-border);
            display: flex;
            align-items: center;
            gap: 14px;
            transition: var(--medical-transition);
            cursor: pointer;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--medical-shadow-hover);
        }

        .stat-card .stat-icon {
            width: 44px; height: 44px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            flex-shrink: 0;
        }

        .stat-icon.blue { background: var(--medical-blue-light); color: var(--medical-blue); }
        .stat-icon.teal { background: var(--medical-teal-light); color: var(--medical-teal); }
        .stat-icon.warning { background: var(--warning-light); color: var(--warning); }
        .stat-icon.danger { background: var(--danger-light); color: var(--danger); }
        .stat-icon.info { background: var(--info-light); color: var(--info); }

        .stat-card .stat-content { flex: 1; min-width: 0; }

        .stat-card .stat-value {
            font-size: clamp(18px, 1.8vw, 26px);
            font-weight: 800;
            color: var(--medical-text);
            line-height: 1.1;
        }

        .stat-card .stat-label {
            font-size: clamp(10px, 0.75vw, 12px);
            color: var(--medical-text-secondary);
            font-weight: 600;
            margin-top: 2px;
            letter-spacing: 0.02em;
        }

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
        .card-modern .card-body { padding: 0; }

        /* ============================================================ */
        /* TOOLBAR                                                      */
        /* ============================================================ */
        .toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 14px 20px;
            border-bottom: 1px solid var(--medical-border);
            flex-wrap: wrap;
            gap: 12px;
            background: var(--medical-gray-light);
        }

        .result-count {
            font-size: 13px;
            color: var(--medical-text-secondary);
        }

        .result-count strong {
            color: var(--medical-text);
            font-weight: 700;
        }

        .search-box {
            position: relative;
        }

        .search-box i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--medical-text-muted);
            font-size: 14px;
            pointer-events: none;
        }

        .search-box input {
            width: 300px;
            padding: 9px 14px 9px 40px;
            background: var(--medical-white);
            border: 1.5px solid var(--medical-border);
            border-radius: var(--medical-radius-sm);
            color: var(--medical-text);
            font-size: 13px;
            outline: none;
            transition: var(--medical-transition);
            font-family: var(--font-primary);
        }

        .search-box input::placeholder { color: var(--medical-text-muted); }
        .search-box input:focus {
            border-color: var(--medical-blue);
            box-shadow: 0 0 0 4px rgba(26, 107, 138, 0.08);
        }

        /* ============================================================ */
        /* TABLE                                                        */
        /* ============================================================ */
        .table-wrapper {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        .table-dashboard {
            font-size: clamp(0.72rem, 0.78vw, 0.85rem);
            margin-bottom: 0;
            min-width: 680px;
        }

        .table-dashboard thead th {
            padding: 10px 16px;
            font-weight: 700;
            color: var(--medical-text-secondary);
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            border-bottom: 2px solid var(--medical-border);
            background: var(--medical-gray-light);
            white-space: nowrap;
        }

        .table-dashboard tbody td {
            padding: 10px 16px;
            border-bottom: 1px solid var(--medical-border);
            color: var(--medical-text);
            vertical-align: middle;
        }

        .table-dashboard tbody tr:last-child td { border-bottom: none; }
        .table-dashboard tbody tr:hover { background: var(--medical-gray-light); }

        /* ============================================================ */
        /* BADGES                                                       */
        /* ============================================================ */
        .badge {
            font-family: var(--font-primary);
            font-weight: 700;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 10px;
            letter-spacing: 0.03em;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            border: none;
        }

        .badge.bg-success { background: var(--success-light) !important; color: var(--success) !important; }
        .badge.bg-danger { background: var(--danger-light) !important; color: var(--danger) !important; }
        .badge.bg-warning { background: var(--warning-light) !important; color: var(--warning) !important; }
        .badge.bg-primary { background: var(--medical-blue-light) !important; color: var(--medical-blue) !important; }
        .badge.bg-info { background: var(--info-light) !important; color: var(--info) !important; }
        .badge.bg-secondary { background: var(--medical-gray) !important; color: var(--medical-text-secondary) !important; }

        .facture-id {
            font-family: 'Courier New', monospace;
            font-size: 11px;
            font-weight: 600;
            color: var(--medical-blue);
            background: var(--medical-blue-light);
            padding: 3px 10px;
            border-radius: 6px;
            display: inline-block;
            letter-spacing: 0.02em;
        }

        .client-cell {
            color: var(--medical-text);
            font-weight: 600;
        }

        /* ============================================================ */
        /* EMPTY STATE                                                  */
        /* ============================================================ */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: var(--medical-text-muted);
        }

        .empty-state i {
            font-size: 48px;
            display: block;
            margin-bottom: 12px;
            opacity: 0.3;
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
        .fade-in-d3 { animation-delay: 0.15s; }
        .fade-in-d4 { animation-delay: 0.20s; }
        .fade-in-d5 { animation-delay: 0.25s; }

        /* ============================================================ */
        /* RESPONSIVE                                                   */
        /* ============================================================ */
        @media (max-width: 1024px) {
            .stats-grid { grid-template-columns: repeat(3, 1fr); }
        }

        @media (max-width: 820px) {
            body { padding: 10px; }
            .page-header h1 { font-size: 20px; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); gap: 10px; }
            .stat-card { padding: 12px 14px; gap: 10px; }
            .stat-card .stat-icon { width: 38px; height: 38px; font-size: 16px; }
            .stat-card .stat-value { font-size: 16px; }
            .toolbar { flex-direction: column; align-items: stretch; }
            .search-box input { width: 100%; }
        }

        @media (max-width: 576px) {
            body { padding: 6px; }
            .page-header { padding: 12px 14px; }
            .page-header h1 { font-size: 18px; }
            .stats-grid { grid-template-columns: 1fr; gap: 8px; }
            .stat-card { padding: 10px 12px; gap: 8px; }
            .stat-card .stat-icon { width: 32px; height: 32px; font-size: 14px; }
            .stat-card .stat-value { font-size: 14px; }
            .table-dashboard { font-size: 0.65rem; min-width: 480px; }
            .table-dashboard thead th, .table-dashboard tbody td { padding: 6px 8px; }
            .search-box input { width: 100%; }
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
            <h1>Gestion des <span class="highlight">factures</span></h1>
            <div class="subtitle">
                <i class="bi bi-receipt"></i>
                Consulter et suivre les factures
                <span class="dot"></span>
                <i class="bi bi-person-circle"></i> <?php echo htmlspecialchars($user_nom); ?>
                <span class="dot"></span>
                <?php echo getRoleBadge($user_role); ?>
            </div>
        </div>
    </header>

    <!-- ============================================================ -->
    <!-- STATISTIQUES                                                 -->
    <!-- ============================================================ -->
    <div class="stats-grid">
        <div class="stat-card fade-in fade-in-d1">
            <div class="stat-icon info"><i class="bi bi-receipt"></i></div>
            <div class="stat-content">
                <div class="stat-value"><?= number_format($total) ?></div>
                <div class="stat-label">Total factures</div>
            </div>
        </div>
        <div class="stat-card fade-in fade-in-d2">
            <div class="stat-icon teal"><i class="bi bi-check-circle"></i></div>
            <div class="stat-content">
                <div class="stat-value"><?= number_format($payes) ?></div>
                <div class="stat-label">Payées</div>
            </div>
        </div>
        <div class="stat-card fade-in fade-in-d3">
            <div class="stat-icon danger"><i class="bi bi-x-circle"></i></div>
            <div class="stat-content">
                <div class="stat-value"><?= number_format($impayes) ?></div>
                <div class="stat-label">Impayées</div>
            </div>
        </div>
        <div class="stat-card fade-in fade-in-d4">
            <div class="stat-icon warning"><i class="bi bi-clock"></i></div>
            <div class="stat-content">
                <div class="stat-value"><?= number_format($partiels) ?></div>
                <div class="stat-label">Partielles</div>
            </div>
        </div>
        <div class="stat-card fade-in fade-in-d5">
            <div class="stat-icon blue"><i class="bi bi-ban"></i></div>
            <div class="stat-content">
                <div class="stat-value"><?= number_format($annules) ?></div>
                <div class="stat-label">Annulées</div>
            </div>
        </div>
    </div>

    <!-- ============================================================ -->
    <!-- LISTE DES FACTURES                                          -->
    <!-- ============================================================ -->
    <div class="card-modern fade-in">
        <div class="card-header">
            <h5>
                <span class="header-icon blue"><i class="bi bi-list-ul"></i></span>
                Liste des factures
                <span class="badge bg-secondary ms-2"><?= number_format($total) ?></span>
            </h5>
        </div>
        <div class="card-body p-0">
            <div class="toolbar">
                <span class="result-count"><strong id="countDisplay"><?= $total ?></strong> facture(s)</span>
                <div class="search-box">
                    <input type="text" id="recherche" placeholder="Rechercher une facture..." autocomplete="off">
                    <i class="bi bi-search"></i>
                </div>
            </div>
            <div class="table-wrapper">
                <table class="table table-dashboard" id="tableFacture">
                    <thead>
                        <tr>
                            <th style="width:120px;">N° Facture</th>
                            <th>Client</th>
                            <th>Organisme</th>
                            <th style="width:100px;">Date</th>
                            <th style="width:80px;">Heure</th>
                            <th>Utilisateur</th>
                            <th style="width:100px;">Statut</th>
                        </tr>
                    </thead>
                    <tbody id="tableBody">
                        <?php $idx=0; while($f = $req->fetch(PDO::FETCH_ASSOC)): $idx++; ?>
                            <tr style="animation-delay:<?= $idx*0.03 ?>s" data-id="<?= $f['facture_id'] ?>">
                                <td><span class="facture-id"><?= htmlspecialchars($f['facture_id']) ?></span></td>
                                <td class="client-cell"><?= htmlspecialchars($f['client']?:'—') ?></td>
                                <td><?= htmlspecialchars($f['organisme']?:'—') ?></td>
                                <td class="date-cell"><?= date('d/m/Y',strtotime($f['date'])) ?></td>
                                <td class="date-cell"><?= $f['heure'] ?></td>
                                <td><?= htmlspecialchars($f['utilisateur']?:'—') ?></td>
                                <td><?= getStatusBadge($f['statut'] ?? 'en attente') ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                <div class="empty-state" id="emptyState" style="display:none;">
                    <i class="bi bi-inbox"></i>
                    <p>Aucune facture trouvée</p>
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
// ── Recherche ──
document.getElementById("recherche").addEventListener("keyup", function(){
    const filtre = this.value.toLowerCase();
    let visible = 0;
    document.querySelectorAll("#tableBody tr").forEach(row => {
        const match = row.innerText.toLowerCase().includes(filtre);
        row.style.display = match ? "" : "none";
        if(match) visible++;
    });
    document.getElementById("countDisplay").textContent = visible;
    document.getElementById("emptyState").style.display = visible === 0 ? "block" : "none";
});

// ── Stagger lignes ──
document.addEventListener("DOMContentLoaded", function(){
    document.querySelectorAll("#tableBody tr").forEach((row, i) => {
        row.style.animationDelay = (i * 0.04) + "s";
    });
});
</script>

</body>
</html>