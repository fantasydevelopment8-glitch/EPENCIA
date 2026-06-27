<?php
// ========================================
// LAYOUT PARTAGÉ — SIDEBAR + TOPBAR (CMU)
// À inclure depuis pages/<module>/<fichier>.php
// Variables attendues (définies par la page AVANT le require) :
//   $BASE        => chemin relatif vers la racine du projet, ex: '../../'
//   $active_link => clé de la page courante (ex: 'gestion-client')
//   $extra_head  => (optionnel) HTML brut (liens CDN / <style>) à injecter dans <head>
// ========================================
if (session_status() === PHP_SESSION_NONE) { session_start(); }

$BASE        = $BASE        ?? '../../';
$active_link = $active_link ?? '';
$extra_head  = $extra_head  ?? '';

$user_role     = $_SESSION['role']       ?? '';
$user_nom      = $_SESSION['nom_prenom'] ?? 'Utilisateur';
$is_medecin    = ($user_role === 'Medecin');
$is_pharmacien = ($user_role === 'Pharmacien');
$is_admin      = in_array($user_role, ['Administrateur', 'Superviseur']);

$role_label = match(true) {
    $is_admin      => 'Administrateur',
    $is_medecin     => 'Médecin',
    $is_pharmacien => 'Pharmacien',
    default        => $user_role
};

$initiales = strtoupper(implode('', array_map(fn($w) => $w[0], array_slice(explode(' ', $user_nom), 0, 2))));

// Groupe de sidebar à ouvrir + libellé de la page courante (fil d'Ariane)
$group_map = [
    'gestion-client'      => 'clients',
    'gestion-facture'     => 'factures',
    'gestion-produit'     => 'produits',
    'gestion-prestation'  => 'prestations',
    'gestion-diagnostic'  => 'diagnostics',
    'gestion-transaction' => 'transactions',
    'gestion-commande'    => 'commandes',
    'gestion-utilisateur' => 'utilisateurs',
    'gestion-organisme'   => 'organismes',
];
$title_map = [
    'gestion-client'      => 'Clients',
    'gestion-facture'     => 'Factures',
    'gestion-produit'     => 'Produits',
    'gestion-prestation'  => 'Prestations',
    'gestion-diagnostic'  => 'Diagnostics',
    'gestion-transaction' => 'Transactions',
    'gestion-commande'    => 'Commandes',
    'gestion-utilisateur' => 'Utilisateurs',
    'gestion-organisme'   => 'Organismes',
    'qrcode'              => 'Scanner client',
];
$active_group = $group_map[$active_link] ?? '';
$page_name    = $title_map[$active_link] ?? '';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($page_name ?: 'CMU') ?> — CMU</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
  <style>
/* ===== RESET & BASE (identique au tableau de bord) ===== */
*, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }
body { font-family:'Segoe UI',system-ui,sans-serif; background:#f1f3f6; color:#1a1a2e; min-height:100vh; }
a { text-decoration:none; color:inherit; }

/* ===== LAYOUT ===== */
.layout { display:flex; min-height:100vh; }

/* ===== SIDEBAR ===== */
.sidebar {
    width:220px; background:#1c1c2e; color:#c8c8d8;
    display:flex; flex-direction:column;
    position:fixed; top:0; left:0; height:100vh;
    overflow-y:auto; z-index:100;
}
.sidebar-logo {
    padding:16px 16px 14px; border-bottom:1px solid rgba(255,255,255,.08);
    display:flex; align-items:center; gap:10px; margin-bottom:4px;
}
.sidebar-logo .logo-icon {
    width:36px; height:36px; background:#0ea5e9; border-radius:8px;
    display:flex; align-items:center; justify-content:center;
    font-size:16px; font-weight:900; color:#fff; flex-shrink:0;
}
.sidebar-logo .logo-text-main { font-size:16px; font-weight:800; letter-spacing:1.5px; color:#fff; }
.sidebar-logo .logo-text-sub  { font-size:10px; color:rgba(255,255,255,.4); font-weight:400; }

.sidebar nav { display:flex; flex-direction:column; padding:4px 0 16px; flex:1; }

.sidebar nav > a.nav-main {
    padding:9px 16px; color:rgba(255,255,255,.65); font-size:13px;
    transition:all .2s; display:flex; align-items:center; gap:9px;
    border-left:3px solid transparent; margin:1px 0;
}
.sidebar nav > a.nav-main:hover { background:rgba(255,255,255,.07); color:#fff; border-left-color:rgba(255,255,255,.3); }
.sidebar nav > a.nav-main.active { background:rgba(255,255,255,.12); color:#fff; border-left-color:#0ea5e9; }

.sidebar-section {
    font-size:9.5px; letter-spacing:2px; color:rgba(255,255,255,.3);
    padding:12px 16px 4px; text-transform:uppercase; font-weight:600;
}

.sidebar-group { display:flex; flex-direction:column; }
.sidebar-toggle {
    background:none; border:none; border-left:3px solid transparent;
    color:rgba(255,255,255,.65); font-size:13px; text-align:left;
    padding:9px 16px; cursor:pointer; display:flex; align-items:center;
    width:100%; font-family:inherit; transition:all .2s; gap:9px; margin:1px 0;
}
.sidebar-toggle:hover { background:rgba(255,255,255,.07); color:#fff; border-left-color:rgba(255,255,255,.3); }
.sidebar-group.open .sidebar-toggle { background:rgba(255,255,255,.1); color:#fff; border-left-color:#0ea5e9; }

.chevron { margin-left:auto; font-size:9px; transition:transform .25s; opacity:.5; }
.sidebar-group.open .chevron { transform:rotate(180deg); }

.sidebar-submenu { display:none; flex-direction:column; background:rgba(0,0,0,.2); }
.sidebar-group.open .sidebar-submenu { display:flex; }
.sidebar-submenu a {
    padding:8px 16px 8px 42px; font-size:12.5px;
    color:rgba(255,255,255,.5); transition:all .2s;
    display:flex; align-items:center; gap:8px;
}
.sidebar-submenu a:hover { background:rgba(255,255,255,.06); color:#fff; }
.sidebar-submenu a.active { color:#fff; background:rgba(14,165,233,.15); font-weight:600; }

.sidebar-logout { margin-top:auto; border-top:1px solid rgba(255,255,255,.08); }
.sidebar-logout button, .sidebar-logout a {
    background:none; border:none; width:100%; text-align:left;
    padding:12px 16px; color:rgba(255,120,120,.8); font-size:13px;
    display:flex; align-items:center; gap:9px; cursor:pointer;
    font-family:inherit; transition:all .2s;
}
.sidebar-logout button:hover, .sidebar-logout a:hover { background:rgba(220,53,69,.1); color:#ff8080; }

/* ===== CONTENU PRINCIPAL ===== */
.content { flex:1; display:flex; flex-direction:column; min-height:100vh; overflow-x:hidden; }


/* ===== NAV HORIZONTALE (Médecin / Pharmacien) ===== */
.topnav-h {
    background:#1c1c2e; color:#c8c8d8;
    display:flex; align-items:center; justify-content:space-between;
    padding:0 20px; height:58px;
    position:sticky; top:0; z-index:100;
    box-shadow:0 1px 6px rgba(0,0,0,.15);
}
.topnav-h-logo { display:flex; align-items:center; gap:10px; }
.topnav-h-logo .logo-icon {
    width:34px; height:34px; background:#0ea5e9; border-radius:8px;
    display:flex; align-items:center; justify-content:center;
    font-size:15px; font-weight:900; color:#fff; flex-shrink:0;
}
.topnav-h-logo .logo-text-main { font-size:15px; font-weight:800; letter-spacing:1.5px; color:#fff; }
.topnav-h-logo .logo-text-sub  { font-size:9.5px; color:rgba(255,255,255,.4); font-weight:400; }
.topnav-h-links { display:flex; align-items:center; gap:4px; flex:1; justify-content:center; }
.topnav-h-links a {
    padding:9px 18px; color:rgba(255,255,255,.65); font-size:13px;
    transition:all .2s; display:flex; align-items:center; gap:8px;
    border-radius:8px; border-bottom:3px solid transparent;
}
.topnav-h-links a:hover { background:rgba(255,255,255,.07); color:#fff; }
.topnav-h-links a.active { background:rgba(255,255,255,.1); color:#fff; border-bottom-color:#0ea5e9; }
.topnav-h-logout button {
    background:none; border:none; padding:9px 16px; color:rgba(255,120,120,.85);
    font-size:13px; display:flex; align-items:center; gap:7px; cursor:pointer;
    font-family:inherit; border-radius:8px; transition:all .2s;
}
.topnav-h-logout button:hover { background:rgba(220,53,69,.12); color:#ff8080; }

@media(max-width:700px) {
    .topnav-h-logo .logo-text-main, .topnav-h-logo .logo-text-sub { display:none; }
    .topnav-h-links a span.lbl { display:none; }
}

/* ===== TOPBAR ===== */
.topbar {
    background:#fff; border-bottom:1px solid #e4e7ec;
    padding:0 22px; height:52px; display:flex;
    justify-content:space-between; align-items:center;
    position:sticky; top:0; z-index:99;
    box-shadow:0 1px 4px rgba(0,0,0,.05);
}
.topbar-left { display:flex; align-items:center; gap:6px; font-size:12.5px; color:#9aa0ac; }
.topbar-left .sep { color:#d0d5dd; font-size:14px; }
.topbar-left .page-name { color:#1a1a2e; font-weight:600; }
.topbar-right { display:flex; align-items:center; gap:12px; }
.topbar-user-info { text-align:right; }
.topbar-user-info .user-name { font-size:13px; font-weight:700; color:#1a1a2e; }
.topbar-user-info .user-role { font-size:11px; color:#9aa0ac; }
.topbar-avatar {
    width:36px; height:36px; border-radius:50%;
    background:linear-gradient(135deg,#0d233a,#1a3a5c);
    color:#fff; font-weight:700; font-size:13px;
    display:flex; align-items:center; justify-content:center;
}

/* ===== ZONE DE CONTENU DE PAGE ===== */
.page-body { padding:18px 22px; }

/* Responsive */
@media(max-width:768px) { .sidebar { display:none; } .content { margin-left:0; } }

  </style>
  <?= $extra_head ?>
</head>
<body>
<?php if (!$is_admin): ?>
<!-- =============================== NAV HORIZONTALE (Médecin / Pharmacien) =============================== -->
<div class="topnav-h">
  <div class="topnav-h-logo">
    <div class="logo-icon">✚</div>
    <div>
      <div class="logo-text-main">CMU</div>
      <div class="logo-text-sub">Couverture Maladie</div>
    </div>
  </div>
  <nav class="topnav-h-links">
    <a href="<?= $BASE ?>index.php">
      <i class="bi bi-grid-fill"></i> <span class="lbl">Dashboard</span>
    </a>
    <a href="<?= $BASE ?>pages/client/qrcode.php" class="<?= $active_link === 'qrcode' ? 'active' : '' ?>">
      <i class="bi bi-qr-code-scan"></i> <span class="lbl">Scanner client</span>
    </a>
  </nav>
  <div class="topnav-h-logout">
    <form method="POST" action="<?= $BASE ?>index.php" onsubmit="try{sessionStorage.removeItem('cmu_client_data');sessionStorage.removeItem('cmu_open_new_facture');}catch(e){}">
      <input type="hidden" name="action" value="logout">
      <button type="submit">
        <i class="bi bi-box-arrow-right"></i> <span class="lbl">Déconnexion</span>
      </button>
    </form>
  </div>
</div>
<?php endif; ?>
<div class="layout">

<?php if ($is_admin): ?>
<!-- =============================== SIDEBAR (Admin) =============================== -->

<?php endif; ?>

<!-- =============================== CONTENU =============================== -->
<main class="content<?= $is_admin ? ' with-sidebar' : '' ?>">


  <!-- TOPBAR -->
  <div class="topbar">
    <div class="topbar-left">
      <span>CMU</span>
      <span class="sep">›</span>
      <span class="page-name"><?= htmlspecialchars($page_name ?: 'Tableau de bord') ?></span>
    </div>
    <div class="topbar-right">
      <div class="topbar-user-info">
        <div class="user-name"><?= htmlspecialchars($user_nom) ?></div>
        <div class="user-role"><?= htmlspecialchars($role_label) ?></div>
      </div>
      <div class="topbar-avatar"><?= $initiales ?></div>
    </div>
  </div>

  <div class="page-body">

<script>
function toggleMenu(btn) {
    const group = btn.closest('.sidebar-group');
    const isOpen = group.classList.contains('open');
    document.querySelectorAll('.sidebar-group.open').forEach(g => g.classList.remove('open'));
    if (!isOpen) group.classList.add('open');
}
</script>