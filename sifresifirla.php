<?php
require 'baglanti.php';

$message = "";
$message_type = "";

if (isset($_GET['token'])) {
    $token = $_GET['token'];

    // Token geçerli mi?
    $stmt = $pdo->prepare("SELECT * FROM password_reset_tokens WHERE token = ? AND expires_at > NOW()");
    $stmt->execute([$token]);
    $resetRequest = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$resetRequest) {
        $message = "Geçersiz veya süresi dolmuş bir bağlantı.";
        $message_type = "error";
    }

    // Yeni şifre gönderildiğinde
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $resetRequest) {
        $yeniSifre = $_POST['yeni_sifre'];
        $sifreTekrar = $_POST['sifre_tekrar'];

        if ($yeniSifre !== $sifreTekrar) {
            $message = "Şifreler uyuşmuyor.";
            $message_type = "error";
        } elseif (strlen($yeniSifre) < 6) {
            $message = "Şifre en az 6 karakter olmalıdır.";
            $message_type = "error";
        } else {
            $hashedPassword = password_hash($yeniSifre, PASSWORD_DEFAULT);

            // Şifreyi güncelle
            $stmt = $pdo->prepare("UPDATE kullanicilar SET sifre = ? WHERE kullanici_id = ?");
            $stmt->execute([$hashedPassword, $resetRequest['user_id']]);

            // Token'ı sil
            $stmt = $pdo->prepare("DELETE FROM password_reset_tokens WHERE token = ?");
            $stmt->execute([$token]);

            $message = "Şifreniz başarıyla güncellendi. Giriş sayfasına yönlendiriliyorsunuz.";
            $message_type = "success";

            header("refresh:4;url=pages/giris.php");
        }
    }
} else {
    $message = "Token bulunamadı.";
    $message_type = "error";
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Yeni Şifre Belirle - AlgezSneakers</title>
    <link rel="icon" type="image/jpeg" href="../images/logo.jpg">
    <link rel="stylesheet" href="tasarim.css">
</head>
<body class="bg-image">

<div class="top-bar">ALGEZ SNEAKERS</div>

<div class="login-container">
    <img src="images/logo.jpg" alt="Logo" class="login-logo" />
    <h2>Yeni Şifre Belirle</h2>

    <?php if (!empty($message)): ?>
        <p class="<?= $message_type === 'success' ? 'success-message' : 'error-message' ?>">
            <?= htmlspecialchars($message) ?>
        </p>
    <?php endif; ?>

    <?php if (isset($resetRequest) && $resetRequest): ?>
    <form method="POST">
        <input type="password" name="yeni_sifre" placeholder="Yeni Şifre" required>
        <input type="password" name="sifre_tekrar" placeholder="Şifre Tekrar" required>
        <button type="submit">Şifreyi Güncelle</button>
    </form>
    <?php endif; ?>

    <div class="register-link">
        Giriş yapmak için <a href="pages/giris.php">tıklayın</a>
    </div>
</div>

</body>
</html>
