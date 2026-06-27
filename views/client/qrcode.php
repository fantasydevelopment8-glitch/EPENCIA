<?php
// ================================================================
// QR CODE SCANNER - EPENCIA SGI
// ================================================================

// ================================================================
// 1. CONNEXION BDD
// ================================================================
require_once "database/database.php";

// ================================================================
// 2. FONCTIONS
// ================================================================
function getClientByLast6Digits($pdo, $last6) {
    try {
        $sql = "SELECT * FROM clients WHERE RIGHT(client_id, 6) = :last6 AND statut = 'actif'";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':last6' => $last6]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return null;
    }
}

function logConnexion($pdo, $client_id, $type = 'scan_qr', $status = 'succes') {
    try {
        $log_id = 'LOG_' . date('YmdHis') . '_' . rand(1000, 9999);
        $sql = "INSERT INTO connexions_log (log_id, client_id, date_connexion, type, status, ip_address, user_agent)
                VALUES (:log_id, :client_id, NOW(), :type, :status, :ip, :user_agent)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':log_id' => $log_id,
            ':client_id' => $client_id,
            ':type' => $type,
            ':status' => $status,
            ':ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ]);
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

// ================================================================
// 3. TRAITEMENT AJAX
// ================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    header('Content-Type: application/json; charset=utf-8');
    $action = $_POST['action'];

    // Scan QR Code
    if ($action === 'scan_qr') {
        $qrData = $_POST['qr_data'] ?? '';
        $result = ['success' => false, 'message' => '', 'client' => null, 'last_6_digits' => ''];

        if (empty($qrData)) {
            $result['message'] = 'Données QR Code vides';
            echo json_encode($result);
            exit();
        }

        $last6 = preg_replace('/[^0-9]/', '', $qrData);
        if (strlen($last6) >= 6) {
            $last6 = substr($last6, -6);
        } else {
            $result['message'] = 'Format QR Code invalide (6 chiffres requis)';
            echo json_encode($result);
            exit();
        }

        $result['last_6_digits'] = $last6;
        $client = getClientByLast6Digits($pdo, $last6);

        if ($client) {
            $result['success'] = true;
            $result['message'] = 'Connexion réussie !';
            $result['client'] = $client;
            logConnexion($pdo, $client['client_id'], 'scan_qr', 'succes');
        } else {
            $result['message'] = 'Aucun client trouvé avec cet ID ou compte inactif';
            logConnexion($pdo, 'unknown', 'scan_qr', 'echec_client_introuvable');
        }

        echo json_encode($result);
        exit();
    }

    // Recherche manuelle
    if ($action === 'search_client') {
        $last6 = $_POST['client_id'] ?? '';
        $result = ['success' => false, 'message' => '', 'client' => null];

        if (empty($last6) || strlen($last6) !== 6 || !ctype_digit($last6)) {
            $result['message'] = 'Veuillez entrer 6 chiffres valides';
            echo json_encode($result);
            exit();
        }

        $client = getClientByLast6Digits($pdo, $last6);

        if ($client) {
            $result['success'] = true;
            $result['message'] = 'Client trouvé avec succès';
            $result['client'] = $client;
            logConnexion($pdo, $client['client_id'], 'recherche_manuelle', 'succes');
        } else {
            $result['message'] = 'Aucun client trouvé avec ces 6 chiffres ou compte inactif';
        }

        echo json_encode($result);
        exit();
    }

    // Factures d'un client
    if ($action === 'get_factures') {
        $client_id = $_POST['client_id'] ?? '';
        $result = ['success' => false, 'factures' => [], 'total_impaye' => 0];

        if (empty($client_id)) {
            echo json_encode($result);
            exit();
        }

        try {
            $stmt = $pdo->prepare("SELECT * FROM factures WHERE client_id = :client_id ORDER BY date_creation DESC LIMIT 10");
            $stmt->execute([':client_id' => $client_id]);
            $factures = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $totalImpaye = 0;
            foreach ($factures as $facture) {
                if (in_array($facture['statut'], ['impayé', 'impayee', 'en_attente', 'en attente', 'partiel', 'partielle'])) {
                    $totalImpaye += floatval($facture['montant'] ?? 0);
                }
            }

            $result['success'] = true;
            $result['factures'] = $factures;
            $result['total_impaye'] = $totalImpaye;
        } catch (PDOException $e) {
            $result['message'] = 'Erreur : ' . $e->getMessage();
        }

        echo json_encode($result);
        exit();
    }

    exit();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title>Epencia SGI - Recherche Client</title>

    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

    <style>
        /* ============================================================ */
        /* DESIGN - EPENCIA SGI                                        */
        /* ============================================================ */
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f0f4f8;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            padding: 20px;
            -webkit-font-smoothing: antialiased;
        }

        .container {
            max-width: 1100px;
            width: 100%;
            background: white;
            border-radius: 28px;
            box-shadow: 0 20px 40px -12px rgba(0,0,0,0.25);
            padding: 30px 30px 40px;
            transition: all 0.2s;
        }

        /* ── Header ── */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 28px;
            flex-wrap: wrap;
            gap: 16px;
        }
        .page-title {
            display: flex;
            align-items: center;
            gap: 14px;
        }
        .page-title .icon-box {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            background: #e8edf5;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            color: #1a3a5c;
        }
        .page-title h1 {
            font-size: 26px;
            font-weight: 600;
            color: #0b2a4a;
            letter-spacing: -0.03em;
        }
        .page-title .subtitle {
            font-size: 13px;
            color: #6a8aaa;
            margin-top: 2px;
        }

        /* ── ID hint ── */
        .id-hint {
            background: #f8fbfe;
            border: 1px solid #e9eef3;
            border-radius: 10px;
            padding: 10px 16px;
            font-size: 13px;
            color: #6a8aaa;
            text-align: center;
            margin-bottom: 24px;
            font-family: 'Courier New', monospace;
        }
        .id-hint .prefix { color: #1a4b78; font-weight: 600; }
        .id-hint .suffix { color: #b13e3e; font-weight: 600; background: #ffe9e9; padding: 2px 8px; border-radius: 4px; }

        /* ── Cards ── */
        .cards-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            margin-bottom: 24px;
        }

        .card {
            background: #ffffff;
            border: 1px solid #e9eef3;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0,0,0,0.04);
            transition: all 0.2s ease;
        }
        .card:hover { border-color: #d0dce8; }

        .card-head {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 18px 20px 14px;
            border-bottom: 1px solid #e9eef3;
        }
        .card-head .c-icon {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            flex-shrink: 0;
        }
        .c-icon.green { background: #e6f5ed; color: #1d7a4b; }
        .c-icon.cyan { background: #e8edf5; color: #1a4b78; }
        .card-head h2 {
            font-size: 15px;
            font-weight: 600;
            color: #0b2a4a;
        }
        .card-head .c-sub {
            font-size: 11px;
            color: #6a8aaa;
            margin-top: 1px;
        }

        .card-body {
            padding: 20px;
        }

        /* ── Digit inputs ── */
        .code-inputs {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin-bottom: 16px;
            flex-wrap: wrap;
        }
        .digit-box {
            width: 52px;
            height: 62px;
            text-align: center;
            font-size: 28px;
            font-weight: 600;
            border: 2px solid #cbdbeb;
            border-radius: 14px;
            background: white;
            transition: 0.2s;
            color: #0b2a4a;
            outline: none;
            font-family: 'Courier New', monospace;
        }
        .digit-box:focus {
            border-color: #2a6b9e;
            box-shadow: 0 0 0 3px rgba(42,107,158,0.2);
        }
        .digit-box.filled {
            background: #f2f8ff;
            border-color: #6f9bc7;
        }
        .digit-box.loading {
            background: #fff3cd;
            border-color: #ffc107;
        }
        .digit-box.error {
            border-color: #dc3545;
            background: #fff8f8;
        }

        .preview-line {
            text-align: center;
            font-size: 13px;
            color: #6a8aaa;
            margin-bottom: 16px;
            font-family: 'Courier New', monospace;
        }
        .preview-line strong {
            color: #0b2a4a;
            font-weight: 600;
        }

        /* ── Buttons ── */
        .btn-row {
            display: flex;
            gap: 10px;
            justify-content: center;
            flex-wrap: wrap;
        }
        .btn {
            padding: 10px 22px;
            border-radius: 12px;
            font-family: 'Inter', sans-serif;
            font-size: 13px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all 0.15s ease;
            display: inline-flex;
            align-items: center;
            gap: 7px;
            text-decoration: none;
        }
        .btn:disabled { opacity: 0.5; pointer-events: none; }
        .btn-accent {
            background: #1a4b78;
            color: #fff;
        }
        .btn-accent:hover {
            background: #0f3a5f;
            transform: translateY(-1px);
            box-shadow: 0 4px 15px rgba(26,75,120,0.3);
        }
        .btn-ghost {
            background: transparent;
            color: #4a6a85;
            border: 2px solid #b6cddf;
        }
        .btn-ghost:hover {
            background: #e3edf5;
            color: #0b2a4a;
        }
        .btn-info {
            background: #1a4b78;
            color: #fff;
        }
        .btn-info:hover {
            background: #0f3a5f;
            transform: translateY(-1px);
            box-shadow: 0 4px 15px rgba(26,75,120,0.3);
        }
        .btn-danger {
            background: #b13e3e;
            color: #fff;
        }
        .btn-danger:hover {
            background: #922f2f;
            transform: translateY(-1px);
            box-shadow: 0 4px 15px rgba(177,62,62,0.3);
        }
        .btn-success {
            background: #1d7a4b;
            color: #fff;
        }
        .btn-success:hover {
            background: #15633b;
            transform: translateY(-1px);
            box-shadow: 0 4px 15px rgba(29,122,75,0.3);
        }

        /* ── Scanner ── */
        .scanner-container {
            position: relative;
            background: #0b1a2b;
            border-radius: 14px;
            overflow: hidden;
            margin-bottom: 16px;
            min-height: 220px;
        }
        #qr-reader {
            width: 100% !important;
            border: none !important;
        }
        #qr-reader video {
            border-radius: 14px !important;
        }
        #qr-reader__scan_region { min-height: 200px !important; }
        #qr-reader__dashboard { display: none !important; }
        #qr-reader__header_message { display: none !important; }

        .scanner-placeholder {
            position: absolute;
            inset: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: #b6d0e8;
            font-size: 14px;
            gap: 8px;
            z-index: 5;
            background: rgba(11,26,43,0.8);
            border-radius: 14px;
            transition: opacity 0.3s ease;
        }
        .scanner-placeholder.hidden { opacity: 0; pointer-events: none; }
        .scanner-placeholder i { font-size: 32px; color: #b6d0e8; }

        .scan-status-bar {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            font-size: 13px;
            color: #4a6a85;
            margin-top: 4px;
        }
        .status-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #ffc107;
            transition: all 0.3s ease;
        }
        .status-dot.active {
            background: #1d7a4b;
            box-shadow: 0 0 10px rgba(29,122,75,0.4);
            animation: pulseDot 1.5s ease-in-out infinite;
        }
        .status-dot.error {
            background: #b13e3e;
        }
        @keyframes pulseDot {
            0%,100% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.3); opacity: 0.6; }
        }

        /* ── Messages ── */
        .msg {
            padding: 12px 18px;
            border-radius: 12px;
            font-size: 13px;
            font-weight: 500;
            display: none;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
        }
        .msg.show { display: flex; }
        .msg-error {
            background: #ffe9e9;
            color: #b13e3e;
            border: 1px solid rgba(177,62,62,0.15);
        }
        .msg-success {
            background: #e6f5ed;
            color: #1d7a4b;
            border: 1px solid rgba(29,122,75,0.15);
        }

        /* ── Result card ── */
        .result-card {
            background: #f8fbfe;
            border: 1px solid #dce6ef;
            border-radius: 20px;
            overflow: hidden;
            display: none;
            animation: fadeSlideIn 0.4s ease both;
        }
        .result-card.show { display: block; }

        @keyframes fadeSlideIn {
            0% { opacity: 0; transform: translateY(12px); }
            100% { opacity: 1; transform: translateY(0); }
        }

        .result-top {
            display: flex;
            align-items: center;
            gap: 18px;
            padding: 22px 24px;
            border-bottom: 1px solid #dce6ef;
        }
        .result-avatar {
            width: 72px;
            height: 72px;
            border-radius: 40px;
            background: #dbe6f0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            font-weight: 600;
            color: #1f3b57;
            flex-shrink: 0;
            overflow: hidden;
        }
        .result-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .result-name {
            font-size: 20px;
            font-weight: 600;
            color: #0b2a4a;
        }
        .result-meta {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-top: 4px;
        }

        .badge {
            padding: 4px 14px;
            border-radius: 30px;
            font-size: 12px;
            font-weight: 600;
            font-family: monospace;
        }
        .badge-id {
            background: #e3ecf5;
            color: #1a3a5c;
        }
        .badge-actif {
            background: #e6f5ed;
            color: #1d7a4b;
        }
        .badge-inactif {
            background: #ffe9e9;
            color: #b13e3e;
        }

        .result-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0;
        }
        .result-field {
            padding: 14px 24px;
            border-bottom: 1px solid #e9eef3;
        }
        .result-field:nth-child(odd) {
            border-right: 1px solid #e9eef3;
        }
        .result-field.full {
            grid-column: 1 / -1;
            border-right: none;
        }
        .result-field .label {
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #6a8aaa;
            margin-bottom: 4px;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .result-field .value {
            font-size: 14px;
            color: #0b2a4a;
            font-weight: 500;
            word-break: break-word;
        }
        .result-field .value.muted {
            color: #6a8aaa;
            font-weight: 400;
        }

        .result-section-title {
            padding: 12px 24px 10px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #6a8aaa;
            border-top: 1px solid #e9eef3;
            border-bottom: 1px solid #e9eef3;
            background: rgba(0,0,0,0.01);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .result-section-title .count {
            color: #1a4b78;
            font-weight: 500;
        }

        /* ── Factures table ── */
        .f-table {
            width: 100%;
            border-collapse: collapse;
        }
        .f-table th {
            padding: 10px 24px;
            text-align: left;
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #6a8aaa;
            border-bottom: 1px solid #e9eef3;
        }
        .f-table td {
            padding: 12px 24px;
            font-size: 13px;
            color: #4a6a85;
            border-bottom: 1px solid #e9eef3;
        }
        .f-table tr:last-child td { border-bottom: none; }
        .f-table tr:hover td { background: rgba(0,0,0,0.01); }

        .f-id {
            font-family: 'Courier New', monospace;
            font-size: 11px;
            background: #f0f5fa;
            padding: 3px 10px;
            border-radius: 6px;
            border: 1px solid #e9eef3;
        }
        .badge-statut {
            padding: 3px 12px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        .badge-statut .dot {
            width: 5px;
            height: 5px;
            border-radius: 50%;
        }
        .bs-paye {
            background: #e6f5ed;
            color: #1d7a4b;
            border: 1px solid rgba(29,122,75,0.15);
        }
        .bs-paye .dot { background: #1d7a4b; }
        .bs-impaye {
            background: #ffe9e9;
            color: #b13e3e;
            border: 1px solid rgba(177,62,62,0.15);
        }
        .bs-impaye .dot { background: #b13e3e; }
        .bs-attente {
            background: #e8edf5;
            color: #1a3a5c;
            border: 1px solid rgba(26,58,92,0.15);
        }
        .bs-attente .dot { background: #1a3a5c; }
        .bs-partiel {
            background: #fef5e6;
            color: #b18f2b;
            border: 1px solid rgba(177,143,43,0.15);
        }
        .bs-partiel .dot { background: #b18f2b; }
        .bs-annule {
            background: #f0f0f0;
            color: #7a7a7a;
            border: 1px solid rgba(122,122,122,0.15);
        }
        .bs-annule .dot { background: #7a7a7a; }

        .f-empty {
            text-align: center;
            color: #6a8aaa;
            padding: 24px !important;
            font-size: 13px;
        }

        .impaye-box {
            margin: 12px 24px 16px;
            background: #ffe9e9;
            border: 1px solid rgba(177,62,62,0.12);
            border-radius: 10px;
            padding: 10px 16px;
            font-size: 13px;
            color: #b13e3e;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .impaye-box strong { color: #b13e3e; }

        .result-actions {
            padding: 18px 24px;
            border-top: 1px solid #e9eef3;
            display: flex;
            gap: 12px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .hidden-section { display: none !important; }

        .spinner {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid rgba(255,255,255,0.2);
            border-top-color: #fff;
            border-radius: 50%;
            animation: spin 0.7s linear infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }

        /* ── Responsive ── */
        @media (max-width: 700px) {
            .container { padding: 16px; }
            .cards-row { grid-template-columns: 1fr; gap: 16px; }
            .digit-box { width: 42px; height: 52px; font-size: 22px; }
            .code-inputs { gap: 6px; }
            .result-grid { grid-template-columns: 1fr; }
            .result-field:nth-child(odd) { border-right: none; }
            .result-top { flex-direction: column; text-align: center; }
            .result-meta { justify-content: center; }
            .result-actions { flex-direction: column; }
            .result-actions .btn { width: 100%; justify-content: center; }
            .f-table th, .f-table td { padding: 10px 16px; font-size: 12px; }
            .result-field { padding: 14px 16px; }
            .page-header { flex-direction: column; align-items: flex-start; }
            .page-title h1 { font-size: 22px; }
        }

        /* ── Error page ── */
        .error-page {
            text-align: center;
            padding: 60px 20px;
        }
        .error-page .icon {
            font-size: 64px;
            color: #b13e3e;
            margin-bottom: 20px;
        }
        .error-page h2 {
            color: #0b2a4a;
            font-size: 24px;
            margin-bottom: 10px;
        }
        .error-page p {
            color: #6a8aaa;
            font-size: 16px;
            margin-bottom: 20px;
        }
        .error-page .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 28px;
            background: #1a4b78;
            color: #fff;
            border: none;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s ease;
        }
        .error-page .btn:hover {
            background: #0f3a5f;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(26,75,120,0.3);
        }
    </style>
</head>
<body>

<div class="container">

    <!-- Header -->
    <div class="page-header">
        <div class="page-title">
            <div class="icon-box"><i class="bi bi-qr-code-scan"></i></div>
            <div>
                <h1>Epencia SGI</h1>
                <div class="subtitle">Recherche client · QR Code / saisie manuelle</div>
            </div>
        </div>
    </div>

    <!-- ID hint -->
    <div class="id-hint" id="idHint">
        Format : <span class="prefix">CLT_EPENCIA_</span><span class="suffix">XXXXXX</span>
        <span style="font-family:'Inter',sans-serif; margin-left:6px; font-weight:400; color:#6a8aaa;">(6 chiffres uniques)</span>
    </div>

    <!-- Messages -->
    <div class="msg msg-error" id="msgError"><i class="bi bi-exclamation-circle"></i><span></span></div>
    <div class="msg msg-success" id="msgSuccess"><i class="bi bi-check-circle"></i><span></span></div>

    <!-- ═══ RECHERCHE ═══ -->
    <div id="searchSection">
        <div class="cards-row">

            <!-- Saisie manuelle -->
            <div class="card">
                <div class="card-head">
                    <div class="c-icon green"><i class="bi bi-keyboard"></i></div>
                    <div>
                        <h2>Saisie manuelle</h2>
                        <div class="c-sub">6 chiffres de l'ID client</div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="code-inputs" id="codeInputs">
                        <input type="text" class="digit-box" maxlength="1" inputmode="numeric" id="d0" autofocus>
                        <input type="text" class="digit-box" maxlength="1" inputmode="numeric" id="d1">
                        <input type="text" class="digit-box" maxlength="1" inputmode="numeric" id="d2">
                        <input type="text" class="digit-box" maxlength="1" inputmode="numeric" id="d3">
                        <input type="text" class="digit-box" maxlength="1" inputmode="numeric" id="d4">
                        <input type="text" class="digit-box" maxlength="1" inputmode="numeric" id="d5">
                    </div>

                    <div class="preview-line">
                        ID : <strong id="previewId">CLT_EPENCIA_______</strong>
                    </div>

                    <div class="btn-row">
                        <button class="btn btn-accent" id="btnSearch" disabled>
                            <i class="bi bi-search"></i> Rechercher
                        </button>
                        <button class="btn btn-ghost" id="btnClear">
                            <i class="bi bi-x-lg"></i> Effacer
                        </button>
                    </div>
                </div>
            </div>

            <!-- Scan QR -->
            <div class="card">
                <div class="card-head">
                    <div class="c-icon cyan"><i class="bi bi-camera"></i></div>
                    <div>
                        <h2>Scan QR Code</h2>
                        <div class="c-sub">Caméra du dispositif</div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="scanner-container">
                        <div id="qr-reader"></div>
                        <div class="scanner-placeholder" id="scannerPlaceholder">
                            <i class="bi bi-camera-video-off"></i>
                            <span>Prêt · cliquez sur Démarrer</span>
                        </div>
                    </div>

                    <div class="btn-row">
                        <button class="btn btn-info" id="btnStartScan">
                            <i class="bi bi-play-fill"></i> Démarrer
                        </button>
                        <button class="btn btn-danger" id="btnStopScan" style="display:none">
                            <i class="bi bi-stop-fill"></i> Arrêter
                        </button>
                    </div>

                    <div class="scan-status-bar">
                        <span id="scanStatusText">Prêt à scanner</span>
                        <span class="status-dot" id="statusDot"></span>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <!-- ═══ RÉSULTAT ═══ -->
    <div class="result-card" id="resultCard">

        <div class="result-top">
            <div class="result-avatar" id="rAvatar">?</div>
            <div>
                <div class="result-name" id="rNom">—</div>
                <div class="result-meta">
                    <span class="badge badge-id" id="rId">—</span>
                    <span class="badge badge-actif" id="rStatut">actif</span>
                </div>
            </div>
        </div>

        <div class="result-grid">
            <div class="result-field">
                <div class="label"><i class="bi bi-telephone"></i> Téléphone</div>
                <div class="value" id="rTel">—</div>
            </div>
            <div class="result-field">
                <div class="label"><i class="bi bi-envelope"></i> Email</div>
                <div class="value" id="rEmail">—</div>
            </div>
            <div class="result-field">
                <div class="label"><i class="bi bi-geo-alt"></i> Ville / Pays</div>
                <div class="value" id="rLoc">—</div>
            </div>
            <div class="result-field">
                <div class="label"><i class="bi bi-droplet-half"></i> Groupe sanguin</div>
                <div class="value" id="rGs">—</div>
            </div>
            <div class="result-field">
                <div class="label"><i class="bi bi-wallet2"></i> Solde</div>
                <div class="value" id="rSolde">0.00000</div>
            </div>
            <div class="result-field">
                <div class="label"><i class="bi bi-briefcase"></i> Profession</div>
                <div class="value" id="rPro">—</div>
            </div>
        </div>

        <!-- Contact urgence -->
        <div class="result-section-title">
            <span><i class="bi bi-exclamation-triangle" style="margin-right:6px"></i>Contact d'urgence</span>
        </div>
        <div class="result-grid">
            <div class="result-field">
                <div class="label">Nom</div>
                <div class="value" id="rUNom">—</div>
            </div>
            <div class="result-field">
                <div class="label">Téléphone</div>
                <div class="value" id="rUTel">—</div>
            </div>
            <div class="result-field full">
                <div class="label">Email</div>
                <div class="value" id="rUEmail">—</div>
            </div>
        </div>

        <!-- Factures -->
        <div class="result-section-title">
            <span><i class="bi bi-receipt" style="margin-right:6px"></i>Dernières factures</span>
            <span class="count" id="fCount">0 facture(s)</span>
        </div>
        <table class="f-table">
            <thead>
                <tr><th>N°</th><th>Date</th><th>Statut</th></tr>
            </thead>
            <tbody id="fBody">
                <tr><td colspan="3" class="f-empty">Aucune facture</td></tr>
            </tbody>
        </table>
        <div class="impaye-box" id="impayeBox" style="display:none">
            <i class="bi bi-credit-card"></i>
            Total impayé : <strong id="impayeVal">0.00</strong>
        </div>

        <!-- Actions -->
        <div class="result-actions">
            <button class="btn btn-ghost" id="btnNewSearch">
                <i class="bi bi-arrow-counterclockwise"></i> Nouvelle recherche
            </button>
            <!-- Formulaire POST pour rediriger vers l'espace client -->
            <form method="POST" action="index.php?c=client&a=espace" id="formEspace" style="display:inline;">
                <input type="hidden" name="client_id" id="clientIdInput" value="">
                <button type="submit" class="btn btn-success" id="btnEspace" style="display:none;">
                    <i class="bi bi-box-arrow-up-right"></i> Espace client
                </button>
            </form>
        </div>
    </div>

</div>

<script>
(function() {
    "use strict";

    // ═══ DOM ═══
    const digits = [
        document.getElementById('d0'), document.getElementById('d1'),
        document.getElementById('d2'), document.getElementById('d3'),
        document.getElementById('d4'), document.getElementById('d5')
    ];
    const previewId    = document.getElementById('previewId');
    const btnSearch    = document.getElementById('btnSearch');
    const btnClear     = document.getElementById('btnClear');
    const btnStartScan = document.getElementById('btnStartScan');
    const btnStopScan  = document.getElementById('btnStopScan');
    const btnEspace    = document.getElementById('btnEspace');
    const clientIdInput = document.getElementById('clientIdInput');
    const formEspace   = document.getElementById('formEspace');
    const scanText     = document.getElementById('scanStatusText');
    const statusDot    = document.getElementById('statusDot');
    const placeholder  = document.getElementById('scannerPlaceholder');
    const searchSection= document.getElementById('searchSection');
    const idHint       = document.getElementById('idHint');
    const msgError     = document.getElementById('msgError');
    const msgSuccess   = document.getElementById('msgSuccess');
    const resultCard   = document.getElementById('resultCard');

    // ═══ ÉTAT ═══
    let html5QrCode = null;
    let scannerRunning = false;
    let searching = false;
    let currentClientId = null;

    // ═══ HELPERS ═══
    function getDigits() {
        return digits.map(d => d.value.trim()).join('');
    }

    function buildId(d) {
        return d.length === 6 ? 'CLT_EPENCIA_' + d : 'CLT_EPENCIA_______';
    }

    function updatePreview() {
        const d = getDigits();
        previewId.textContent = buildId(d);
        digits.forEach(inp => inp.classList.toggle('filled', /^[0-9]$/.test(inp.value)));
        const allFilled = digits.every(inp => /^[0-9]$/.test(inp.value));
        btnSearch.disabled = !allFilled || searching;
    }

    function hideMsgs() {
        msgError.classList.remove('show');
        msgSuccess.classList.remove('show');
    }

    function showError(msg) {
        msgError.querySelector('span').textContent = msg;
        msgError.classList.add('show');
        msgSuccess.classList.remove('show');
        setScanStatus('Erreur', 'error');
    }

    function showSuccess(msg) {
        msgSuccess.querySelector('span').textContent = msg;
        msgSuccess.classList.add('show');
        msgError.classList.remove('show');
        setScanStatus('Succès', 'active');
    }

    function setScanStatus(text, state) {
        scanText.textContent = text;
        statusDot.className = 'status-dot' + (state ? ' ' + state : '');
    }

    function setLoading(on) {
        searching = on;
        btnSearch.disabled = on;
        digits.forEach(d => {
            d.classList.toggle('loading', on);
            d.disabled = on;
        });
        btnSearch.innerHTML = on
            ? '<span class="spinner"></span> Recherche…'
            : '<i class="bi bi-search"></i> Rechercher';
    }

    function showSearch() {
        searchSection.classList.remove('hidden-section');
        idHint.classList.remove('hidden-section');
    }

    function hideSearch() {
        searchSection.classList.add('hidden-section');
        idHint.classList.add('hidden-section');
    }

    function clearAll() {
        digits.forEach(d => { d.value = ''; d.classList.remove('filled','error','loading'); d.disabled = false; });
        updatePreview();
        hideMsgs();
        resultCard.classList.remove('show');
        showSearch();
        setScanStatus('Prêt à scanner', '');
        currentClientId = null;
        btnEspace.style.display = 'none';
        clientIdInput.value = '';
        digits[0].focus();
    }

    // ═══ RECHERCHE ═══
    async function performSearch(code6) {
        if (!/^[0-9]{6}$/.test(code6)) { showError('6 chiffres requis.'); return; }
        if (searching) return;

        hideMsgs();
        resultCard.classList.remove('show');
        setLoading(true);

        try {
            const fd = new FormData();
            fd.append('action', 'search_client');
            fd.append('client_id', code6);

            const res = await fetch(location.href, { method: 'POST', body: fd });
            const data = await res.json();

            if (data.success) {
                currentClientId = data.client.client_id;
                showSuccess('Client trouvé !');
                displayClient(data.client);
                await loadFactures(data.client.client_id);
                hideSearch();
                resultCard.classList.add('show');
                
                // Mettre à jour le formulaire POST pour l'espace client
                clientIdInput.value = currentClientId;
                btnEspace.style.display = 'inline-flex';
                
                setTimeout(() => resultCard.scrollIntoView({ behavior:'smooth', block:'start' }), 200);
            } else {
                showError(data.message || 'Client non trouvé');
                digits.forEach(d => d.classList.add('error'));
            }
        } catch(e) {
            showError('Erreur réseau : ' + e.message);
        } finally {
            setLoading(false);
        }
    }

    // ═══ FACTURES ═══
    async function loadFactures(clientId) {
        try {
            const fd = new FormData();
            fd.append('action', 'get_factures');
            fd.append('client_id', clientId);

            const res = await fetch(location.href, { method: 'POST', body: fd });
            const data = await res.json();

            if (data.success) displayFactures(data.factures, data.total_impaye);
        } catch(e) {
            console.error('Factures:', e);
        }
    }

    function displayFactures(factures, total) {
        const fBody = document.getElementById('fBody');
        const fCount = document.getElementById('fCount');
        const impayeBox = document.getElementById('impayeBox');
        const impayeVal = document.getElementById('impayeVal');

        fCount.textContent = factures.length + ' facture(s)';

        if (factures.length === 0) {
            fBody.innerHTML = '<tr><td colspan="3" class="f-empty">Aucune facture</td></tr>';
            impayeBox.style.display = 'none';
            return;
        }

        const clsMap = {
            'payé': 'bs-paye', 'payee': 'bs-paye',
            'impayé': 'bs-impaye', 'impayee': 'bs-impaye',
            'partiel': 'bs-partiel', 'partielle': 'bs-partiel',
            'annulé': 'bs-annule', 'annulee': 'bs-annule',
            'en attente': 'bs-attente', 'en_attente': 'bs-attente'
        };
        let html = '';
        factures.forEach(f => {
            const cls = clsMap[f.statut] || 'bs-attente';
            const label = f.statut || 'en attente';
            const dateStr = f.date_facture ? new Date(f.date_facture+'T00:00:00').toLocaleDateString('fr-FR') : '—';
            html += `<tr>
                <td><span class="f-id">${f.facture_id||'—'}</span></td>
                <td>${dateStr}</td>
                <td><span class="badge-statut ${cls}"><span class="dot"></span>${label}</span></td>
            </tr>`;
        });
        fBody.innerHTML = html;

        if (total > 0) {
            impayeBox.style.display = 'inline-flex';
            impayeVal.textContent = total.toFixed(2);
        } else {
            impayeBox.style.display = 'none';
        }
    }

    // ═══ AFFICHAGE CLIENT ═══
    function displayClient(c) {
        document.getElementById('rNom').textContent = c.nom_prenom || '—';
        document.getElementById('rId').textContent = c.client_id || '—';

        const statutEl = document.getElementById('rStatut');
        statutEl.textContent = c.statut || 'actif';
        statutEl.className = 'badge ' + (c.statut === 'inactif' ? 'badge-inactif' : 'badge-actif');

        const avatar = document.getElementById('rAvatar');
        if (c.photo && c.type_photo) {
            avatar.innerHTML = '<img src="data:' + c.type_photo + ';base64,' + c.photo + '" alt="Photo">';
        } else {
            avatar.textContent = (c.nom_prenom || '?')[0].toUpperCase();
        }

        document.getElementById('rTel').textContent = c.telephone || '—';
        document.getElementById('rEmail').textContent = c.email || '—';
        document.getElementById('rLoc').textContent = (c.ville||'') + (c.pays ? ', '+c.pays : '') || '—';
        document.getElementById('rGs').textContent = c.groupe_sanguin || '—';
        document.getElementById('rSolde').textContent = c.solde || '0.00000';
        document.getElementById('rPro').textContent = c.profession || '—';
        document.getElementById('rUNom').textContent = c.nom_prenom_urgence || '—';
        document.getElementById('rUTel').textContent = c.telephone_urgence || '—';
        document.getElementById('rUEmail').textContent = c.email_urgence || '—';
    }

    // ═══ SCANNER QR (html5-qrcode) ═══
    async function startScanner() {
        if (scannerRunning) return;

        if (!html5QrCode) {
            html5QrCode = new Html5Qrcode("qr-reader");
        }

        hideMsgs();
        placeholder.querySelector('span').textContent = 'Accès caméra…';
        placeholder.classList.remove('hidden');
        setScanStatus('Connexion…', '');

        try {
            await html5QrCode.start(
                { facingMode: "environment" },
                {
                    fps: 10,
                    qrbox: { width: 220, height: 220 },
                    aspectRatio: 1.0
                },
                onScanSuccess,
                () => {}
            );

            scannerRunning = true;
            placeholder.classList.add('hidden');
            setScanStatus('Actif — en attente de QR', 'active');
            btnStartScan.style.display = 'none';
            btnStopScan.style.display = 'inline-flex';

        } catch(err) {
            console.error('Scanner error:', err);
            let msg = err.toString();
            if (msg.includes('NotAllowedError') || msg.includes('Permission')) {
                msg = 'Accès caméra refusé. Autorisez la caméra.';
            } else if (msg.includes('NotFoundError') || msg.includes('Requested device not found')) {
                msg = 'Aucune caméra trouvée.';
            } else if (msg.includes('NotReadableError') || msg.includes('Could not start video')) {
                msg = 'Caméra déjà utilisée.';
            }
            showError('Caméra : ' + msg);
            placeholder.querySelector('span').textContent = 'Erreur';
            placeholder.querySelector('i').className = 'bi bi-camera-video-off';
            placeholder.classList.remove('hidden');
            setScanStatus('Échec', 'error');
        }
    }

    function onScanSuccess(decodedText) {
        const nums = decodedText.replace(/\D/g, '');
        const last6 = nums.length >= 6 ? nums.slice(-6) : nums;

        if (last6.length === 6) {
            digits.forEach((d, i) => { d.value = last6[i] || ''; });
            updatePreview();
            stopScanner();
            performSearch(last6);
        } else {
            showError('QR Code ne contient pas 6 chiffres valides.');
            stopScanner();
        }
    }

    async function stopScanner() {
        if (!html5QrCode || !scannerRunning) return;

        try {
            await html5QrCode.stop();
        } catch(e) {}

        scannerRunning = false;
        placeholder.querySelector('span').textContent = 'Arrêté';
        placeholder.querySelector('i').className = 'bi bi-camera-video-off';
        placeholder.classList.remove('hidden');
        setScanStatus('Arrêté', '');
        btnStartScan.style.display = 'inline-flex';
        btnStopScan.style.display = 'none';

        setTimeout(() => {
            if (!scannerRunning) {
                placeholder.querySelector('span').textContent = 'Prêt · cliquez sur Démarrer';
                placeholder.querySelector('i').className = 'bi bi-camera-video-off';
                setScanStatus('Prêt à scanner', '');
            }
        }, 1200);
    }

    // ═══ ÉVÉNEMENTS DIGITS ═══
    digits.forEach((inp, idx) => {
        inp.addEventListener('input', function() {
            this.value = this.value.replace(/\D/g, '').slice(0, 1);
            this.classList.remove('error');
            updatePreview();
            if (this.value.length === 1 && idx < 5) digits[idx+1].focus();
            if (idx === 5 && this.value.length === 1) {
                const full = getDigits();
                if (/^[0-9]{6}$/.test(full)) performSearch(full);
            }
        });

        inp.addEventListener('keydown', function(e) {
            if (e.key === 'Backspace' && this.value === '' && idx > 0) {
                digits[idx-1].focus();
                digits[idx-1].select();
            }
            if (e.key === 'Enter') {
                const full = getDigits();
                if (/^[0-9]{6}$/.test(full)) performSearch(full);
                else showError('Veuillez saisir 6 chiffres valides.');
            }
            if (e.key === 'ArrowLeft' && idx > 0) { digits[idx-1].focus(); digits[idx-1].select(); }
            if (e.key === 'ArrowRight' && idx < 5) { digits[idx+1].focus(); digits[idx+1].select(); }
        });

        inp.addEventListener('focus', function() { this.select(); });

        inp.addEventListener('paste', function(e) {
            e.preventDefault();
            const txt = (e.clipboardData || window.clipboardData).getData('text');
            const nums = txt.replace(/\D/g, '').slice(0, 6);
            digits.forEach((d, i) => { d.value = nums[i] || ''; d.classList.remove('error'); });
            updatePreview();
            if (nums.length === 6) performSearch(nums);
            else digits[Math.min(nums.length, 5)].focus();
        });
    });

    btnSearch.addEventListener('click', () => {
        const d = getDigits();
        if (/^[0-9]{6}$/.test(d)) performSearch(d);
        else showError('Veuillez saisir 6 chiffres valides.');
    });

    btnClear.addEventListener('click', clearAll);
    btnStartScan.addEventListener('click', startScanner);
    btnStopScan.addEventListener('click', stopScanner);

    document.getElementById('btnNewSearch').addEventListener('click', () => {
        stopScanner();
        clearAll();
    });

    // ═══ Gestionnaire du bouton Espace client (POST) ═══
    // Le bouton est déjà dans un formulaire POST
    // La redirection se fait via le contrôleur

    // ═══ INIT ═══
    updatePreview();
    btnSearch.disabled = true;
    setScanStatus('Prêt à scanner', '');
    btnEspace.style.display = 'none';
    clientIdInput.value = '';

})();
</script>

</body>
</html>