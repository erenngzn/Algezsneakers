<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../PHPMailer/src/Exception.php';
require '../PHPMailer/src/PHPMailer.php';
require '../PHPMailer/src/SMTP.php';

// HATA AYIKLAMA
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require 'baglanti.php'; // PDO bağlantısı burada yapılmalı

$message = "";
$message_type = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);

    // Kullanıcıyı kontrol et
    $stmt = $pdo->prepare("SELECT kullanici_id FROM kullanicilar WHERE eposta = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        $token = bin2hex(random_bytes(32));
        date_default_timezone_set('Europe/Istanbul');
        $expires_at = date('Y-m-d H:i:s', time() + 3600); // 1 saat

        // Token'ı kaydet
        $stmt = $pdo->prepare("INSERT INTO password_reset_tokens (user_id, token, expires_at) VALUES (?, ?, ?)");
        $stmt->execute([$user['kullanici_id'], $token, $expires_at]);

        $reset_link = "http://localhost/ALGEZSNEAKERS/pages/sifresifirla.php?token=$token";

        // Mail gönder
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'algezsneakers@gmail.com'; // kendi adresin
            $mail->Password = 'hspu kpun iwrp nldk'; // uygulama şifresi
            $mail->SMTPSecure = 'tls';
            $mail->Port = 587;

            $mail->setFrom('algezsneakers@gmail.com', 'AlgezSneakers');
            $mail->addAddress($email);
            $mail->isHTML(true);
            $mail->Subject = 'Sifre sifirlama baglantisi';
            $mail->Body = "Merhaba,<br><br>Şifrenizi sıfırlamak için aşağıdaki bağlantıya tıklayın:<br><a href='$reset_link'>$reset_link</a><br><br>Bağlantı 1 saat geçerlidir.";

            $mail->send();
            $message = "Şifre sıfırlama bağlantısı e-posta adresinize gönderildi.";
            $message_type = "success";
        } catch (Exception $e) {
            $message = "Mail gönderilemedi. Hata: {$mail->ErrorInfo}";
            $message_type = "error";
        }
    } else {
        $message = "Bu e-posta sistemde kayıtlı değil.";
        $message_type = "error";
    }
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Şifre Sıfırla - AlgezSneakers</title>
    <link rel="icon" type="image/jpeg" href="../images/logo.jpg">
    <link rel="stylesheet" href="tasarim.css">
</head>
<body class="bg-image">

<div class="top-bar">ALGEZ SNEAKERS</div>

<div class="login-container">
    <img src="../images/logo.jpg" alt="Logo" class="login-logo" />
    <h2>Şifre Sıfırlama</h2>

    <?php if (!empty($message)): ?>
        <p class="<?= $message_type === 'success' ? 'success-message' : 'error-message' ?>">
            <?= htmlspecialchars($message) ?>
        </p>
    <?php endif; ?>

    <form method="POST" autocomplete="off">
        <input type="email" name="email" placeholder="E-posta adresinizi girin" required>
        <button type="submit">Sıfırlama Bağlantısı Gönder</button>
    </form>

    <div class="register-link">
        Giriş yapmak için <a href="giris.php">buraya tıklayın</a>
    </div>
</div>

</body>
</html>
