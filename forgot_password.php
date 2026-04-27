<?php
require_once 'config.php';

// ── CONFIGURE ICI ──────────────────────────
define('GMAIL_USER', 'Ahmedomar.etudes@gmail.com');
define('GMAIL_PASS', 'vvjdkpgfyiohkwxo');
define('GMAIL_NAME', 'FixMyStreet Djibouti');
// ───────────────────────────────────────────

require_once dirname(__DIR__) . '/phpmailer/PHPMailer-master/src/Exception.php';
require_once dirname(__DIR__) . '/phpmailer/PHPMailer-master/src/PHPMailer.php';
require_once dirname(__DIR__) . '/phpmailer/PHPMailer-master/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$data  = json_decode(file_get_contents('php://input'), true);
$step  = $data['step']  ?? '';
$email = trim($data['email'] ?? '');

// ── ÉTAPE 1 : Envoyer le code ──────────────
if ($step === 'send_code') {
    if (empty($email)) { sendResponse(false, 'Email requis'); }

    $stmt = $pdo->prepare("SELECT id, prenom, nom FROM citoyens WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user) { sendResponse(false, 'Aucun compte trouvé avec cet email'); }

    // Générer code 4 chiffres
    $code   = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
    $expiry = date('Y-m-d H:i:s', strtotime('+10 minutes'));

    $stmt = $pdo->prepare("UPDATE citoyens SET reset_token = ?, reset_expiry = ? WHERE email = ?");
    $stmt->execute([$code, $expiry, $email]);

    // Envoyer email
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = GMAIL_USER;
        $mail->Password   = GMAIL_PASS;
        $mail->SMTPSecure = 'tls';
        $mail->Port       = 587;
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom(GMAIL_USER, GMAIL_NAME);
        $mail->addAddress($email, $user['prenom'] . ' ' . $user['nom']);
        $mail->Subject = 'Code de vérification - FixMyStreet';
        $mail->isHTML(true);
        $mail->Body = "
        <div style='font-family:Arial,sans-serif;max-width:500px;margin:auto;background:#f5f5f5;padding:30px;border-radius:16px'>
            <div style='text-align:center;margin-bottom:24px'>
                <h1 style='color:#007AFF;font-size:28px;margin:0'>FixMyStreet</h1>
                <p style='color:#888;margin:4px 0'>Djibouti 🇩🇯</p>
            </div>
            <div style='background:white;border-radius:12px;padding:24px;text-align:center'>
                <p style='color:#333;font-size:16px'>Bonjour <b>{$user['prenom']}</b> 👋</p>
                <p style='color:#666;font-size:14px'>Voici votre code de vérification :</p>
                <div style='background:#007AFF;border-radius:12px;padding:20px;margin:20px 0'>
                    <span style='font-size:42px;font-weight:bold;color:white;letter-spacing:12px'>{$code}</span>
                </div>
                <p style='color:#FF3B30;font-size:13px'>⏱ Ce code expire dans <b>10 minutes</b></p>
                <p style='color:#999;font-size:12px'>Si vous n'avez pas demandé ce code, ignorez cet email.</p>
            </div>
            <p style='text-align:center;color:#aaa;font-size:11px;margin-top:16px'>Service officiel de la Mairie de Djibouti</p>
        </div>";

        $mail->send();
        sendResponse(true, 'Code envoyé avec succès');
    } catch (Exception $e) {
        sendResponse(false, 'Erreur envoi email: ' . $mail->ErrorInfo);
    }
}

// ── ÉTAPE 2 : Vérifier le code ─────────────
if ($step === 'verify_code') {
    $code = trim($data['code'] ?? '');
    if (empty($email) || empty($code)) { sendResponse(false, 'Données manquantes'); }

    $stmt = $pdo->prepare("SELECT reset_token, reset_expiry FROM citoyens WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user) { sendResponse(false, 'Email introuvable'); }
    if ($user['reset_token'] !== $code) { sendResponse(false, 'Code incorrect'); }
    if (strtotime($user['reset_expiry']) < time()) { sendResponse(false, 'Code expiré'); }

    sendResponse(true, 'Code valide');
}

// ── ÉTAPE 3 : Nouveau mot de passe ─────────
if ($step === 'reset_password') {
    $code     = trim($data['code']     ?? '');
    $password = trim($data['password'] ?? '');
    if (empty($email) || empty($code) || empty($password)) { sendResponse(false, 'Données manquantes'); }

    $stmt = $pdo->prepare("SELECT reset_token, reset_expiry FROM citoyens WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || $user['reset_token'] !== $code) { sendResponse(false, 'Code invalide'); }
    if (strtotime($user['reset_expiry']) < time()) { sendResponse(false, 'Code expiré'); }

    // ← Correction : la colonne s'appelle 'password' pas 'mot_de_passe'
    $stmt = $pdo->prepare("UPDATE citoyens SET password = ?, reset_token = NULL, reset_expiry = NULL WHERE email = ?");
    $stmt->execute([$password, $email]);

    sendResponse(true, 'Mot de passe modifié avec succès');
}

sendResponse(false, 'Action invalide');
?>