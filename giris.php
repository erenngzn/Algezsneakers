<?php
// HATA AYIKLAMA
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require 'baglanti.php'; // PDO bağlantısı

// Admin kullanıcı bilgileri
$adminEmail = 'admin@example.com';
$adminSifre = 'admin';
$adminKullaniciAdi = 'admin';

// Admin kullanıcıyı kontrol edip yoksa ekle
try {
    $stmt = $pdo->prepare("SELECT * FROM kullanicilar WHERE eposta = ?");
    $stmt->execute([$adminEmail]);
    $adminVarMi = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$adminVarMi) {
        // Admin kullanıcı yoksa ekle
        $hashedSifre = password_hash($adminSifre, PASSWORD_DEFAULT);
        $stmtInsert = $pdo->prepare("INSERT INTO kullanicilar (eposta, sifre, kullanici_adi, adres, cinsiyet, dogum_tarihi) VALUES (?, ?, ?, ?, ?, ?)");
        $stmtInsert->execute([
            $adminEmail,
            $hashedSifre,
            $adminKullaniciAdi,
            'Admin Adres',
            'Erkek',
            '1970-01-01'
        ]);
    }
} catch (PDOException $e) {
    die("Veritabanı hatası: " . $e->getMessage());
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = "Lütfen tüm alanları doldurun.";
    } else {
        try {
            $stmt = $pdo->prepare("SELECT * FROM kullanicilar WHERE eposta = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['sifre'])) {
    // E-posta doğrulandı mı kontrol et
    if ($user['email_dogrulandi'] == 0) {
        $error = "Lütfen e-posta adresinizi doğrulayın.";
    } else {
        // Giriş başarılı, session oluştur
        $_SESSION['eposta'] = $user['eposta'];
        $_SESSION['kullanici_id'] = $user['kullanici_id'];
        $_SESSION['kullanici_adi'] = $user['kullanici_adi'];
        $_SESSION['adres'] = $user['adres'];
        $_SESSION['cinsiyet'] = $user['cinsiyet'];
        $_SESSION['dogum_tarihi'] = $user['dogum_tarihi'];

        // Admin kontrolü
        if ($user['eposta'] === $adminEmail && $password === $adminSifre) {
            header("Location: admin_urun.php");
        } else {
            header("Location: anasayfa.php");
        }
        exit();
    }
} else {
    $error = "E-posta veya şifre hatalı.";
}
        } catch (PDOException $e) {
            $error = "Veritabanı hatası: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8" />
    <title>Giriş Yap - AlgezSneakers</title>
    <link rel="icon" type="image/jpeg" href="../images/logo.jpg">
    <link rel="stylesheet" href="tasarim.css" />
</head>
<body class="bg-image">

<div class="top-bar">ALGEZ SNEAKERS</div>
<div class="login-container">
    <img src="../images/logo.jpg" alt="Logo" class="login-logo" />
    <h2>Giriş Yap</h2>

    <?php if ($error): ?>
        <p class="error-message"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <form method="POST" autocomplete="off">
        <input type="email" name="email" placeholder="E-posta" required>

        <div class="password-container">
            <input type="password" id="password" name="password" placeholder="Şifre" required>
            <button type="button" class="toggle-password" onclick="togglePassword()" aria-label="Şifreyi göster/gizle">Göster</button>
        </div>

        <button type="submit">Giriş Yap</button>
    </form>

    <div class="register-link">
        Hesabınız yok mu? <a href="kayit.php">Kayıt Ol</a>
    </div>
    <div class="register-link">
        Şifreni mi unuttun? <a href="sifreunuttum.php">Şifre Sıfırla</a>
    </div>
</div>

<script>
    function togglePassword() {
        const input = document.getElementById('password');
        const button = document.querySelector('.toggle-password');
        if (input.type === 'password') {
            input.type = 'text';
            button.textContent = 'Gizle';
        } else {
            input.type = 'password';
            button.textContent = 'Göster';
        }
    }
</script>

</body>
</html>
