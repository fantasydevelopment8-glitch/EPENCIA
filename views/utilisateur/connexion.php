<?php 
// ================================================================
// CONNEXION - EPENCIA SGI
// ================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require 'database/database.php';
require 'config/smtp_config.php';

require 'library/PHPMailer/src/PHPMailer.php';
require 'library/PHPMailer/src/SMTP.php';
require 'library/PHPMailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// ── Variables globales ──────────────────
$error   = '';
$success = '';
$mode = isset($_SESSION['auth_mode']) ? $_SESSION['auth_mode'] : 'login';
$step      = isset($_SESSION['auth_step'])      ? $_SESSION['auth_step']      : 1;
$user_data = isset($_SESSION['auth_user_data']) ? $_SESSION['auth_user_data'] : null;

// ── Reset complet ───────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset'])) {
    session_unset();
    header('Location: connexion.php');
    exit();
}

// ── Fonctions ───────────────────────────
function generate4Code() {
    return str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
}

function sendEmailSMTP($to, $subject, $body, &$errorMsg = '') {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = SMTP_PORT;
        $mail->CharSet    = 'UTF-8';
        $mail->Timeout    = 30;
        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer'       => false,
                'verify_peer_name'  => false,
                'allow_self_signed' => true,
            ]
        ];
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($to);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>'], "\n", $body));
        $mail->send();
        return true;
    } catch (Exception $e) {
        $errorMsg = 'Erreur SMTP : ' . $mail->ErrorInfo;
        error_log('[EPENCIA][SMTP] Erreur envoi vers ' . $to . ' : ' . $mail->ErrorInfo);
        return false;
    }
}

function emailTemplate($titre, $nom, $code, $message, $couleur = '#1a6b8a') {
    $year = date('Y');
    return "
    <div style='font-family:Plus Jakarta Sans,Arial,sans-serif;max-width:600px;margin:0 auto;background:#f0f5f8;padding:20px;'>
      <div style='background:linear-gradient(135deg,{$couleur} 0%,#0f4a62 100%);color:white;padding:30px;text-align:center;border-radius:16px 16px 0 0;'>
        <h1 style='margin:0;font-size:2rem;font-weight:400;font-family:DM Serif Display,Georgia,serif;'>🏥 Epencia SGI</h1>
        <p style='margin:8px 0 0;opacity:.85;font-weight:300;'>Système de Gestion Intégré</p>
      </div>
      <div style='background:white;padding:40px 30px;border-radius:0 0 16px 16px;box-shadow:0 4px 24px rgba(26,107,138,0.08);'>
        <h2 style='color:{$couleur};text-align:center;margin-bottom:20px;font-weight:700;font-size:1.4rem;'>{$titre}</h2>
        <p style='color:#1a2a3a;font-size:1.05rem;'>Bonjour <strong>{$nom}</strong>,</p>
        <p style='color:#4a6a7a;'>{$message}</p>
        <div style='background:#f7f9fb;border:3px solid {$couleur};border-radius:12px;padding:30px;text-align:center;margin:30px 0;'>
          <div style='font-size:3.5rem;font-weight:700;color:{$couleur};letter-spacing:12px;font-family:Courier New,monospace;'>{$code}</div>
        </div>
        <div style='background:#fdf5e6;border-left:4px solid #d4a843;padding:15px;border-radius:8px;'>
          <p style='margin:0;color:#b8860b;font-weight:500;'><strong>⏱️ Ce code est valable 10 minutes.</strong></p>
        </div>
        <p style='color:#8aa0b0;font-size:.85rem;margin-top:20px;'>Si vous n'avez pas fait cette demande, ignorez cet email.</p>
        <hr style='margin:25px 0;border:none;border-top:1px solid #dce4ea;'>
        <p style='color:#8aa0b0;font-size:.8rem;text-align:center;'>© {$year} Epencia SGI — Système de Gestion Intégré</p>
      </div>
    </div>";
}

// ════════════════════════════════════════
// TRAITEMENT POST
// ════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (isset($_POST['switch_to_forgot'])) {
        session_unset();
        $_SESSION['auth_mode'] = 'forgot';
        $_SESSION['auth_step'] = 1;
        $mode = 'forgot';
        $step = 1;
        $user_data = null;
    }

    // ─────────────────────────────────────
    // FLUX LOGIN
    // ─────────────────────────────────────

    if (isset($_POST['login_step1'])) {
        $_SESSION['auth_mode'] = 'login';
        $email = trim($_POST['email'] ?? '');
        $mdp   = trim($_POST['mdp'] ?? '');

        if (empty($email) || empty($mdp)) {
            $error = 'Veuillez remplir tous les champs.';
        } else {
            try {
                $stmt = $pdo->prepare('SELECT * FROM utilisateurs WHERE email = ?');
                $stmt->execute([$email]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($user && $mdp === $user['mdp']) {
                    session_unset();
                    $_SESSION['utilisateur_id'] = $user['utilisateur_id'];
                    $_SESSION['nom_prenom']     = $user['nom_prenom'];
                    $_SESSION['login']          = $user['login'];
                    $_SESSION['role']           = $user['role'];
                    $_SESSION['organisme_id']   = $user['organisme_id'] ?? null;
                    $_SESSION['email']          = $user['email'];
                    header('Location: ../utilisateur/menu');
                    exit();
                } else {
                    $error = 'Email ou mot de passe incorrect.';
                }
            } catch (PDOException $e) {
                $error = 'Erreur BDD : ' . $e->getMessage();
            }
        }
    }

    // ─────────────────────────────────────
    // FLUX MOT DE PASSE OUBLIÉ
    // ─────────────────────────────────────

    if (isset($_POST['forgot_step1'])) {
        $_SESSION['auth_mode'] = 'forgot';
        $email = trim($_POST['forgot_email'] ?? '');

        if (empty($email)) {
            $error = 'Veuillez entrer votre adresse email.';
            $mode = 'forgot'; $step = 1;
        } else {
            try {
                $stmt = $pdo->prepare('SELECT * FROM utilisateurs WHERE email = ?');
                $stmt->execute([$email]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($user) {
                    $etat_val = strtolower(trim((string)$user['etat']));
                    if (!in_array($etat_val, ['actif', '1', 'active'])) {
                        $user = null;
                    }
                }

                if ($user) {
                    $code = generate4Code();
                    $_SESSION['auth_code']      = $code;
                    $_SESSION['auth_time']      = time();
                    $_SESSION['auth_user_data'] = $user;
                    $_SESSION['auth_step']      = 2;
                    $_SESSION['auth_attempts']  = 0;
                    $_SESSION['auth_mode']      = 'forgot';

                    $body = emailTemplate(
                        'Réinitialisation du mot de passe',
                        $user['nom_prenom'],
                        $code,
                        'Vous avez demandé la réinitialisation de votre mot de passe. Voici votre code de vérification :',
                        '#c0392b'
                    );

                    if (sendEmailSMTP($email, '🔑 Réinitialisation mot de passe — Epencia SGI', $body, $error)) {
                        $success = "Code de réinitialisation envoyé à <strong>" . htmlspecialchars($email) . "</strong>";
                        $step = 2; $mode = 'forgot'; $user_data = $user;
                    } else {
                        unset($_SESSION['auth_code'], $_SESSION['auth_time'],
                              $_SESSION['auth_user_data'], $_SESSION['auth_step'], $_SESSION['auth_mode']);
                        if (!$error) $error = "Erreur lors de l'envoi de l'email.";
                        error_log('[EPENCIA][SMTP] Echec envoi reset vers ' . $email . ' : ' . $error);
                        $mode = 'forgot'; $step = 1;
                    }
                } else {
                    $success = "Si cet email existe, un code a été envoyé.";
                    $mode = 'forgot'; $step = 1;
                }
            } catch (PDOException $e) {
                $error = 'Erreur BDD : ' . $e->getMessage();
                $mode = 'forgot'; $step = 1;
            }
        }
    }

    if (isset($_POST['verify_forgot_code'])) {
        $code = trim($_POST['reset_code'] ?? '');
        $mode = 'forgot'; $step = 2; $user_data = $_SESSION['auth_user_data'] ?? null;

        if (empty($code)) {
            $error = 'Veuillez entrer le code reçu.';
        } elseif (!isset($_SESSION['auth_code']) || !isset($_SESSION['auth_user_data'])) {
            $error = 'Session expirée. Recommencez.';
            session_unset(); $step = 1; $mode = 'forgot';
        } elseif (time() - $_SESSION['auth_time'] > 600) {
            $error = 'Code expiré. Recommencez.';
            session_unset(); $step = 1; $mode = 'forgot';
        } else {
            $_SESSION['auth_attempts'] = ($_SESSION['auth_attempts'] ?? 0) + 1;
            if ($_SESSION['auth_attempts'] > 5) {
                $error = 'Trop de tentatives.';
                session_unset(); $step = 1; $mode = 'forgot';
            } elseif ($code !== $_SESSION['auth_code']) {
                $error = 'Code incorrect — tentative ' . $_SESSION['auth_attempts'] . '/5.';
            } else {
                $_SESSION['auth_step']     = 3;
                $_SESSION['auth_verified'] = true;
                unset($_SESSION['auth_code'], $_SESSION['auth_time'], $_SESSION['auth_attempts']);
                $step = 3; $mode = 'forgot'; $user_data = $_SESSION['auth_user_data'];
            }
        }
    }

    if (isset($_POST['resend_forgot_code'])) {
        if (isset($_SESSION['auth_user_data'])) {
            $user  = $_SESSION['auth_user_data'];
            $code  = generate4Code();
            $_SESSION['auth_code']     = $code;
            $_SESSION['auth_time']     = time();
            $_SESSION['auth_attempts'] = 0;
            $body = emailTemplate('Nouveau code de réinitialisation', $user['nom_prenom'], $code,
                'Voici votre nouveau code :', '#c0392b');
            if (sendEmailSMTP($user['email'], '🔑 Nouveau code reset — Epencia SGI', $body, $error)) {
                $success = 'Nouveau code envoyé.';
            }
            $step = 2; $mode = 'forgot'; $user_data = $user;
        }
    }

    if (isset($_POST['save_new_password'])) {
        $mode = 'forgot'; $step = 3; $user_data = $_SESSION['auth_user_data'] ?? null;

        if (!isset($_SESSION['auth_verified']) || !isset($_SESSION['auth_user_data'])) {
            $error = 'Session invalide. Recommencez.';
            session_unset(); $step = 1; $mode = 'forgot'; $user_data = null;
        } else {
            $new_mdp     = trim($_POST['new_mdp']     ?? '');
            $confirm_mdp = trim($_POST['confirm_mdp'] ?? '');

            if (empty($new_mdp) || empty($confirm_mdp)) {
                $error = 'Veuillez remplir les deux champs.';
            } elseif (strlen($new_mdp) < 6) {
                $error = 'Le mot de passe doit contenir au moins 6 caractères.';
            } elseif ($new_mdp !== $confirm_mdp) {
                $error = 'Les mots de passe ne correspondent pas.';
            } else {
                try {
                    $user = $_SESSION['auth_user_data'];
                    $stmt = $pdo->prepare('UPDATE utilisateurs SET mdp = ? WHERE utilisateur_id = ?');
                    $stmt->execute([$new_mdp, $user['utilisateur_id']]);

                    session_unset();
                    $success = 'Mot de passe mis à jour avec succès ! Vous pouvez maintenant vous connecter.';
                    $step = 1; $mode = 'login';
                } catch (PDOException $e) {
                    $error = 'Erreur BDD : ' . $e->getMessage();
                }
            }
        }
    }
}

$mode      = isset($_SESSION['auth_mode'])      ? $_SESSION['auth_mode']      : ($mode ?? 'login');
$step      = isset($_SESSION['auth_step'])      ? $_SESSION['auth_step']      : ($step ?? 1);
$user_data = isset($_SESSION['auth_user_data']) ? $_SESSION['auth_user_data'] : ($user_data ?? null);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion — Epencia SGI</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
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
            font-family: var(--font-primary);
            background: var(--medical-bg);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            background: linear-gradient(135deg, var(--medical-blue-dark) 0%, var(--medical-blue) 100%);
        }

        /* ============================================================ */
        /* CARD PRINCIPALE                                             */
        /* ============================================================ */
        .card-auth {
            background: var(--medical-white);
            border-radius: var(--medical-radius);
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.25);
            overflow: hidden;
            width: 100%;
            max-width: 480px;
            border: 1px solid rgba(255,255,255,0.1);
        }

        /* ============================================================ */
        /* HEADER                                                       */
        /* ============================================================ */
        .auth-header {
            padding: 36px 30px 28px;
            text-align: center;
            color: white;
            position: relative;
            overflow: hidden;
        }

        .auth-header::before {
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

        .auth-header.mode-login { 
            background: linear-gradient(135deg, var(--medical-blue-dark), var(--medical-blue)); 
        }
        .auth-header.mode-forgot { 
            background: linear-gradient(135deg, #7b1a1a, var(--danger)); 
        }

        .auth-header .icon-wrap {
            width: 72px; height: 72px;
            border-radius: 50%;
            background: rgba(255,255,255,0.15);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 14px;
            font-size: 2rem;
            border: 2px solid rgba(255,255,255,0.2);
        }

        .auth-header h1 {
            font-family: var(--font-serif);
            font-size: 1.8rem;
            font-weight: 400;
            margin: 0 0 4px;
            letter-spacing: -0.01em;
        }

        .auth-header h1 .highlight {
            color: var(--medical-teal);
        }

        .auth-header p {
            font-size: .9rem;
            margin: 0;
            opacity: .85;
            font-weight: 300;
        }

        /* ============================================================ */
        /* STEPS                                                       */
        /* ============================================================ */
        .steps-wrap {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0;
            margin-top: 18px;
        }

        .step-dot {
            width: 34px; height: 34px;
            border-radius: 50%;
            font-weight: 700;
            font-size: .85rem;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(255,255,255,.25);
            color: white;
            transition: all .3s;
        }

        .step-dot.active    { background: white; color: var(--medical-blue); }
        .step-dot.completed { background: var(--medical-teal); color: white; }

        .step-line {
            width: 40px; height: 2px;
            background: rgba(255,255,255,.3);
        }
        .step-line.done { background: var(--medical-teal); }

        /* ============================================================ */
        /* BODY                                                         */
        /* ============================================================ */
        .auth-body {
            padding: 32px 28px;
        }

        /* ============================================================ */
        /* ALERTS                                                       */
        /* ============================================================ */
        .alert-box {
            border-radius: var(--medical-radius-sm);
            border-left: 4px solid transparent;
            padding: 13px 16px;
            margin-bottom: 20px;
            font-size: .9rem;
            display: flex;
            align-items: flex-start;
            gap: 10px;
            font-weight: 500;
        }

        .alert-box i { font-size: 1.1rem; flex-shrink: 0; margin-top: 1px; }
        .alert-err  { background: var(--danger-light); border-color: var(--danger); color: var(--danger); }
        .alert-ok   { background: var(--success-light); border-color: var(--success); color: var(--success); }

        /* ============================================================ */
        /* FORMULAIRE                                                  */
        /* ============================================================ */
        .form-label {
            font-weight: 700;
            color: var(--medical-text-secondary);
            font-size: .78rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            margin-bottom: 6px;
        }

        .input-group-text {
            background: var(--medical-blue);
            color: white;
            border: none;
            border-radius: var(--medical-radius-sm) 0 0 var(--medical-radius-sm) !important;
            width: 44px;
            justify-content: center;
            font-size: 1rem;
        }

        .input-group-text.danger { background: var(--danger); }

        .form-control {
            border: 1.5px solid var(--medical-border);
            border-radius: 0 var(--medical-radius-sm) var(--medical-radius-sm) 0 !important;
            padding: 11px 14px;
            font-size: .95rem;
            font-weight: 500;
            color: var(--medical-text);
            background: var(--medical-gray-light);
            transition: var(--medical-transition);
        }

        .form-control:focus {
            border-color: var(--medical-blue);
            box-shadow: 0 0 0 4px rgba(26, 107, 138, 0.1);
            background: white;
        }

        .form-control.danger:focus {
            border-color: var(--danger);
            box-shadow: 0 0 0 4px rgba(192, 57, 43, 0.1);
        }

        /* ============================================================ */
        /* CODE INPUT                                                  */
        /* ============================================================ */
        .code-input {
            font-family: 'Courier New', monospace;
            font-size: 2.8rem;
            text-align: center;
            letter-spacing: 14px;
            font-weight: 700;
            border-radius: var(--medical-radius-sm) !important;
            border: 2px solid var(--medical-border) !important;
            padding: 16px !important;
            background: var(--medical-white);
        }

        .code-input:focus {
            border-color: var(--medical-blue) !important;
        }

        .code-input.danger:focus {
            border-color: var(--danger) !important;
        }

        /* ============================================================ */
        /* BOUTONS                                                      */
        /* ============================================================ */
        .btn-primary-auth {
            background: var(--medical-blue);
            color: white;
            font-weight: 700;
            padding: 13px;
            border: none;
            border-radius: var(--medical-radius-sm);
            font-size: 1rem;
            width: 100%;
            transition: var(--medical-transition);
            font-family: var(--font-primary);
        }

        .btn-primary-auth:hover {
            background: var(--medical-blue-dark);
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(26, 107, 138, 0.3);
            color: white;
        }

        .btn-danger-auth {
            background: var(--danger);
            color: white;
            font-weight: 700;
            padding: 13px;
            border: none;
            border-radius: var(--medical-radius-sm);
            font-size: 1rem;
            width: 100%;
            transition: var(--medical-transition);
            font-family: var(--font-primary);
        }

        .btn-danger-auth:hover {
            background: #a93226;
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(192, 57, 43, 0.3);
            color: white;
        }

        .btn-link-custom {
            background: none;
            border: none;
            color: var(--medical-blue);
            font-size: .88rem;
            font-weight: 600;
            cursor: pointer;
            padding: 0;
            font-family: var(--font-primary);
            transition: var(--medical-transition);
            text-decoration: none;
        }

        .btn-link-custom:hover {
            color: var(--medical-blue-dark);
            text-decoration: underline;
        }

        .btn-link-custom.danger {
            color: var(--danger);
        }

        .btn-link-custom.danger:hover {
            color: #a93226;
        }

        /* ============================================================ */
        /* EYE TOGGLE                                                  */
        /* ============================================================ */
        .pw-wrap { position: relative; }

        .eye-btn {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--medical-text-muted);
            cursor: pointer;
            font-size: 1rem;
            padding: 0;
            z-index: 5;
        }

        .eye-btn:hover {
            color: var(--medical-text);
        }

        .pw-wrap .form-control { 
            padding-right: 42px !important; 
        }

        /* ============================================================ */
        /* DIVIDER                                                      */
        /* ============================================================ */
        .divider {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 20px 0;
        }

        .divider span {
            font-size: .8rem;
            color: var(--medical-text-muted);
            white-space: nowrap;
            font-weight: 500;
        }

        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--medical-border);
        }

        /* ============================================================ */
        /* INFO BOX                                                     */
        /* ============================================================ */
        .info-box {
            background: var(--medical-blue-light);
            border-left: 4px solid var(--medical-blue);
            border-radius: var(--medical-radius-sm);
            padding: 12px 16px;
            margin-bottom: 22px;
            font-size: .88rem;
            color: var(--medical-blue);
            font-weight: 500;
        }

        .info-box.danger {
            background: var(--danger-light);
            border-color: var(--danger);
            color: var(--danger);
        }

        .info-box.success {
            background: var(--success-light);
            border-color: var(--success);
            color: var(--success);
        }

        /* ============================================================ */
        /* FOOTER                                                       */
        /* ============================================================ */
        .auth-footer {
            padding: 16px 28px;
            background: var(--medical-gray-light);
            border-top: 1px solid var(--medical-border);
            text-align: center;
            font-size: .85rem;
        }

        .auth-footer a {
            color: var(--medical-blue);
            text-decoration: none;
            font-weight: 600;
            transition: var(--medical-transition);
        }

        .auth-footer a:hover {
            color: var(--medical-blue-dark);
            text-decoration: underline;
        }

        /* ============================================================ */
        /* RESPONSIVE                                                   */
        /* ============================================================ */
        @media (max-width: 576px) {
            body { padding: 12px; }
            .auth-header { padding: 28px 20px 22px; }
            .auth-header h1 { font-size: 1.4rem; }
            .auth-header .icon-wrap { width: 56px; height: 56px; font-size: 1.5rem; }
            .auth-body { padding: 24px 18px; }
            .code-input { font-size: 2rem; letter-spacing: 10px; padding: 12px !important; }
            .step-dot { width: 28px; height: 28px; font-size: .7rem; }
            .step-line { width: 28px; }
        }

        @media (max-width: 400px) {
            .auth-header h1 { font-size: 1.2rem; }
            .auth-header p { font-size: .8rem; }
            .auth-body { padding: 18px 14px; }
            .code-input { font-size: 1.6rem; letter-spacing: 8px; }
            .form-label { font-size: .7rem; }
        }
    </style>
</head>
<body>

<script>
try{
    sessionStorage.removeItem('cmu_client_data');
    sessionStorage.removeItem('cmu_open_new_facture');
}catch(e){}
</script>

<div class="card-auth">

    <?php
    // ─── Calcul header ───
    $headerClass = ($mode === 'forgot') ? 'mode-forgot' : 'mode-login';
    $totalSteps  = ($mode === 'forgot') ? 3 : 1;

    if ($mode === 'login') {
        $icon  = 'bi-shield-lock-fill';
        $titre = 'Connexion';
        $sous  = 'Accédez à votre espace Epencia SGI';
    } elseif ($mode === 'forgot' && $step == 1) {
        $icon  = 'bi-key-fill';
        $titre = 'Mot de passe oublié';
        $sous  = 'Saisissez votre email pour recevoir un code';
    } elseif ($mode === 'forgot' && $step == 2) {
        $icon  = 'bi-envelope-open-fill';
        $titre = 'Vérification';
        $sous  = 'Entrez le code reçu par email';
    } else {
        $icon  = 'bi-lock-fill';
        $titre = 'Nouveau mot de passe';
        $sous  = 'Choisissez un mot de passe sécurisé';
    }
    ?>

    <!-- ============================================================ -->
    <!-- HEADER                                                       -->
    <!-- ============================================================ -->
    <div class="auth-header <?= $headerClass ?>">
        <div class="icon-wrap"><i class="bi <?= $icon ?>"></i></div>
        <h1><?= $titre ?></h1>
        <p><?= $sous ?></p>

        <?php if ($step > 1 || $mode === 'forgot'): ?>
        <div class="steps-wrap">
            <?php for ($i = 1; $i <= $totalSteps; $i++):
                $cls = ($i < $step) ? 'completed' : (($i == $step) ? 'active' : '');
            ?>
                <div class="step-dot <?= $cls ?>"><?= ($i < $step) ? '<i class="bi bi-check"></i>' : $i ?></div>
                <?php if ($i < $totalSteps):
                    $lineCls = ($i < $step) ? 'done' : '';
                ?><div class="step-line <?= $lineCls ?>"></div><?php endif; ?>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- ============================================================ -->
    <!-- BODY                                                         -->
    <!-- ============================================================ -->
    <div class="auth-body">

        <?php if ($error): ?>
            <div class="alert-box alert-err">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert-box alert-ok">
                <i class="bi bi-check-circle-fill"></i>
                <span><?= $success ?></span>
            </div>
        <?php endif; ?>

        <?php
        // ════════════════════════════
        // LOGIN — ÉTAPE 1
        // ════════════════════════════
        if ($mode === 'login' && $step == 1): ?>

            <form method="POST">
                <div class="mb-3">
                    <label class="form-label"><i class="bi bi-envelope-fill me-1"></i>Email</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                        <input type="email" name="email" class="form-control"
                               placeholder="votre@email.com"
                               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required autofocus>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label"><i class="bi bi-lock-fill me-1"></i>Mot de passe</label>
                    <div class="pw-wrap">
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-key"></i></span>
                            <input type="password" name="mdp" id="mdp" class="form-control" placeholder="••••••••" required>
                        </div>
                        <button type="button" class="eye-btn" onclick="togglePw('mdp','eye1')">
                            <i class="bi bi-eye" id="eye1"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" name="login_step1" class="btn-primary-auth">
                    <i class="bi bi-box-arrow-in-right me-2"></i>Se connecter
                </button>
            </form>

            <div class="divider"><span>ou</span></div>
            <div class="text-center">
                <form method="POST">
                    <button type="submit" name="switch_to_forgot" class="btn-link-custom danger">
                        <i class="bi bi-key me-1"></i>Mot de passe oublié ?
                    </button>
                </form>
            </div>

        <?php
        // ════════════════════════════
        // FORGOT — ÉTAPE 1 : Email
        // ════════════════════════════
        elseif ($mode === 'forgot' && $step == 1): ?>

            <form method="POST">
                <div class="mb-4">
                    <label class="form-label"><i class="bi bi-envelope-fill me-1"></i>Votre adresse email</label>
                    <div class="input-group">
                        <span class="input-group-text danger"><i class="bi bi-envelope"></i></span>
                        <input type="email" name="forgot_email" class="form-control danger"
                               placeholder="votre@email.com"
                               value="<?= htmlspecialchars($_POST['forgot_email'] ?? '') ?>"
                               required autofocus>
                    </div>
                    <small class="text-muted mt-1 d-block" style="color:var(--medical-text-muted);font-size:.8rem;">
                        Vous recevrez un code à 4 chiffres pour réinitialiser votre mot de passe.
                    </small>
                </div>
                <button type="submit" name="forgot_step1" class="btn-danger-auth">
                    <i class="bi bi-send me-2"></i>Envoyer le code de réinitialisation
                </button>
            </form>

            <div class="divider"><span>ou</span></div>
            <div class="text-center">
                <form method="POST">
                    <button type="submit" name="reset" class="btn-link-custom">
                        <i class="bi bi-arrow-left me-1"></i>Retour à la connexion
                    </button>
                </form>
            </div>

        <?php
        // ════════════════════════════
        // FORGOT — ÉTAPE 2 : Code
        // ════════════════════════════
        elseif ($mode === 'forgot' && $step == 2): ?>

            <div class="info-box danger">
                <i class="bi bi-envelope-fill me-1"></i>
                Code envoyé à : <strong><?= htmlspecialchars($user_data['email'] ?? '') ?></strong>
            </div>

            <form method="POST">
                <div class="mb-4">
                    <label class="form-label text-center d-block"><i class="bi bi-key me-1"></i>Code de réinitialisation</label>
                    <input type="text" name="reset_code" class="form-control code-input danger"
                           placeholder="0000" maxlength="4" pattern="[0-9]{4}"
                           required autofocus autocomplete="off">
                    <p class="text-center mt-2" style="font-size:.82rem;color:var(--danger);font-weight:600;">
                        <i class="bi bi-clock me-1"></i>Valable 10 minutes
                    </p>
                </div>
                <button type="submit" name="verify_forgot_code" class="btn-danger-auth">
                    <i class="bi bi-check-circle me-2"></i>Vérifier le code
                </button>
            </form>

            <div class="d-flex justify-content-between align-items-center mt-3">
                <form method="POST">
                    <button type="submit" name="resend_forgot_code" class="btn-link-custom danger">
                        <i class="bi bi-arrow-clockwise me-1"></i>Renvoyer le code
                    </button>
                </form>
                <form method="POST" class="d-inline">
                    <button type="submit" name="reset" class="btn-link-custom" style="font-size:.82rem;">
                        <i class="bi bi-arrow-left me-1"></i>Retour
                    </button>
                </form>
            </div>

        <?php
        // ════════════════════════════
        // FORGOT — ÉTAPE 3 : Nouveau mdp
        // ════════════════════════════
        elseif ($mode === 'forgot' && $step == 3): ?>

            <div class="info-box success">
                <i class="bi bi-check-circle-fill me-1"></i>
                Code vérifié ! Choisissez votre nouveau mot de passe.
            </div>

            <form method="POST">
                <div class="mb-3">
                    <label class="form-label"><i class="bi bi-lock-fill me-1"></i>Nouveau mot de passe</label>
                    <div class="pw-wrap">
                        <div class="input-group">
                            <span class="input-group-text danger"><i class="bi bi-lock"></i></span>
                            <input type="password" name="new_mdp" id="new_mdp" class="form-control danger"
                                   placeholder="Minimum 6 caractères" required minlength="6" autofocus>
                        </div>
                        <button type="button" class="eye-btn" onclick="togglePw('new_mdp','eye2')">
                            <i class="bi bi-eye" id="eye2"></i>
                        </button>
                    </div>
                </div>
                <div class="mb-4">
                    <label class="form-label"><i class="bi bi-lock-fill me-1"></i>Confirmer le mot de passe</label>
                    <div class="pw-wrap">
                        <div class="input-group">
                            <span class="input-group-text danger"><i class="bi bi-lock-fill"></i></span>
                            <input type="password" name="confirm_mdp" id="confirm_mdp" class="form-control danger"
                                   placeholder="Répétez le mot de passe" required minlength="6">
                        </div>
                        <button type="button" class="eye-btn" onclick="togglePw('confirm_mdp','eye3')">
                            <i class="bi bi-eye" id="eye3"></i>
                        </button>
                    </div>
                    <div id="match-msg" class="mt-1" style="font-size:.82rem;"></div>
                </div>
                <button type="submit" name="save_new_password" class="btn-danger-auth">
                    <i class="bi bi-save me-2"></i>Enregistrer le nouveau mot de passe
                </button>
            </form>

        <?php endif; ?>

    </div><!-- /auth-body -->

    <!-- ============================================================ -->
    <!-- FOOTER                                                       -->
    <!-- ============================================================ -->
    <div class="auth-footer">
        <a href="index.php"><i class="bi bi-house me-1"></i>Retour à l'accueil</a>
        <span style="color:var(--medical-text-muted);margin:0 8px;">•</span>
        <span style="color:var(--medical-text-muted);font-size:.75rem;">Epencia SGI v1.0</span>
    </div>

</div><!-- /card-auth -->

<!-- ============================================================ -->
<!-- SCRIPTS                                                      -->
<!-- ============================================================ -->
<script>
function togglePw(fieldId, iconId) {
    const f = document.getElementById(fieldId);
    const i = document.getElementById(iconId);
    if (f.type === 'password') {
        f.type = 'text';
        i.classList.replace('bi-eye', 'bi-eye-slash');
    } else {
        f.type = 'password';
        i.classList.replace('bi-eye-slash', 'bi-eye');
    }
}

document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.code-input').forEach(function (el) {
        el.addEventListener('input', function () {
            this.value = this.value.replace(/[^0-9]/g, '');
        });
    });

    const newMdp     = document.getElementById('new_mdp');
    const confirmMdp = document.getElementById('confirm_mdp');
    const matchMsg   = document.getElementById('match-msg');

    if (newMdp && confirmMdp && matchMsg) {
        function checkMatch() {
            if (confirmMdp.value === '') { matchMsg.innerHTML = ''; return; }
            if (newMdp.value === confirmMdp.value) {
                matchMsg.innerHTML = '<span style="color:var(--medical-teal);font-weight:600;"><i class="bi bi-check-circle"></i> Les mots de passe correspondent</span>';
            } else {
                matchMsg.innerHTML = '<span style="color:var(--danger);font-weight:600;"><i class="bi bi-x-circle"></i> Les mots de passe ne correspondent pas</span>';
            }
        }
        newMdp.addEventListener('input', checkMatch);
        confirmMdp.addEventListener('input', checkMatch);
    }
});
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>