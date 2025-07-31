<?php
require 'baglanti.php';
require '../PHPMailer/src/PHPMailer.php';
require '../PHPMailer/src/SMTP.php';
require '../PHPMailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

session_start();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['kullanici_adi'])) {
    $username   = trim($_POST['kullanici_adi']);
    $email      = trim($_POST['eposta']);
    $password   = $_POST['sifre'];
    $confirm    = $_POST['confirm_password'];
    $phone      = trim($_POST['cep_telefonu']);
    $address    = trim($_POST['adres']);
    $birthdate  = $_POST['dogum_tarihi'];
    $gender     = $_POST['cinsiyet'];

    if (empty($username) || empty($email) || empty($password) || empty($confirm) || 
        empty($phone) || empty($address) || empty($birthdate) || empty($gender)) {
        $error = "Lütfen tüm alanları doldurun.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Geçerli bir e-posta girin.";
    } elseif ($password !== $confirm) {
        $error = "Şifreler eşleşmiyor.";
    } elseif (strlen($password) < 8 || !preg_match('/[A-Z]/', $password) || !preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password)) {
        $error = "Şifre kurallarına uyulmalı.";
    } else {
        try {
            $stmt = $pdo->prepare("SELECT * FROM kullanicilar WHERE eposta = ? OR kullanici_adi = ?");
            $stmt->execute([$email, $username]);

            if ($stmt->fetch()) {
                $error = "Bu e-posta veya kullanıcı adı zaten kayıtlı.";
            } else {
                $verificationCode = rand(100000, 999999);
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $profile_pic = null;

                $stmt = $pdo->prepare("INSERT INTO kullanicilar 
                    (kullanici_adi, sifre, eposta, cep_telefonu, adres, dogum_tarihi, cinsiyet, profil_resmi, rol, aktif_mi, kayit_tarihi, dogrulama_kodu, email_dogrulandi, dogrulama_suresi)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'kullanici', 1, NOW(), ?, 0, NOW())");

                $stmt->execute([
                    $username,
                    $hashedPassword,
                    $email,
                    $phone,
                    $address,
                    $birthdate,
                    $gender,
                    $profile_pic,
                    $verificationCode
                ]);

                // Kod ve eposta oturuma yaz
                $_SESSION['email_to_verify'] = $email;

                // Mail gönderimi
                $mail = new PHPMailer;
                $mail->isSMTP();
                $mail->Host = 'smtp.gmail.com';
                $mail->SMTPAuth = true;
                $mail->Username = 'algezsneakers@gmail.com'; // senin gmailin
                $mail->Password = 'hspu kpun iwrp nldk'; // Gmail uygulama şifresi
                $mail->SMTPSecure = 'tls';
                $mail->Port = 587;

                $mail->setFrom('algezsneakers@gmail.com', 'AlgezSneakers');
                $mail->addAddress($email, $username);
                $mail->isHTML(true); // 🔥 BU SATIR ÖNEMLİ!
                $mail->Subject = 'E-Posta Dogrulama Kodu';

$mail->Body = '
<div style="font-family: Arial, sans-serif; background-color: #f8f8f8; padding: 20px;">
    <div style="max-width: 600px; margin: auto; background: #fff; padding: 30px; border-radius: 10px; box-shadow: 0 4px 12px rgba(0,0,0,0.1);">
        <div style="text-align: center;">
            <h2 style="color: #333;">E-Posta Dogrulama Kodu</h2>
            <p style="font-size: 16px; color: #555;">Merhaba <strong>' . htmlspecialchars($username) . '</strong>,</p>
            <p style="font-size: 15px; color: #555;">AlgezSneakers hesabını tamamlamak için aşağıdaki doğrulama kodunu kullan:</p>
            <div style="margin: 20px 0; font-size: 24px; font-weight: bold; color: #4CAF50;">' . $verificationCode . '</div>
            <p style="font-size: 14px; color: #888;">Kod 10 dakika boyunca geçerlidir.</p>
            <p style="font-size: 13px; color: #999;">Eğer bu işlemi siz yapmadıysanız bu mesajı göz ardı edebilirsiniz.</p>
            <hr style="margin: 30px 0; border: none; border-top: 1px solid #eee;" />
            <p style="font-size: 12px; color: #aaa;">© ' . date("Y") . ' AlgezSneakers. Tüm hakları saklıdır.</p>
        </div>
    </div>
</div>';

                if ($mail->send()) {
                    $success = 'Kod gönderildi!';
                } else {
                    $error = 'E-posta gönderilemedi: ' . $mail->ErrorInfo;
                }
            }
        } catch (PDOException $e) {
            $error = "Veritabanı hatası: " . $e->getMessage();
        }
    }
}

// Kod kontrolü (AJAX'ten gelen veriyle)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dogrulama_kodu'])) {
    $girilen_kod = $_POST['dogrulama_kodu'];
    $email = $_SESSION['email_to_verify'] ?? '';

    if ($email && $girilen_kod) {
        $stmt = $pdo->prepare("SELECT * FROM kullanicilar WHERE eposta = ? AND dogrulama_kodu = ?");
        $stmt->execute([$email, $girilen_kod]);
        if ($stmt->fetch()) {
            $stmt = $pdo->prepare("UPDATE kullanicilar SET email_dogrulandi = 1 WHERE eposta = ?");
            $stmt->execute([$email]);
            echo 'success';
        } else {
            echo 'invalid';
        }
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8" />
<title>Kayıt Ol - AlgezSneakers</title>
</head>
<body class="bg-image">

<div class="top-bar">ALGEZ SNEAKERS</div>
<link rel="icon" type="image/jpeg" href="../images/logo.jpg">
<link rel="stylesheet" href="kayit.css" />
<div class="login-wrapper">
    <div class="login-container">
        <img src="../images/logo.jpg" alt="Logo" class="login-logo" />
        <h2>Kayıt Ol</h2>

        <?php if ($error): ?>
            <p class="error-message"><?= htmlspecialchars($error) ?></p>
        <?php elseif ($success): ?>
            <p class="success-message"><?= htmlspecialchars($success) ?></p>
            <script>
                setTimeout(function() {
                    
                }, 2000);
            </script>
        <?php endif; ?>

        <form method="POST" autocomplete="off" class="form-grid">
            <input type="text" name="kullanici_adi" placeholder="Kullanıcı Adı" 
                   value="<?= htmlspecialchars($_POST['kullanici_adi'] ?? '') ?>" 
                   required minlength="3" maxlength="30" />

            <input type="email" name="eposta" placeholder="E-posta" 
                   value="<?= htmlspecialchars($_POST['eposta'] ?? '') ?>" required />

            <div class="password-container">
    <input type="password" name="sifre" placeholder="Şifre" 
           required minlength="8" id="password" oninput="checkPasswordStrength()" />
    <span class="toggle-password" onclick="togglePassword('password')">Göster</span>
</div>

           <!-- Doğrulama Modalı -->
<div id="kodModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.7); z-index:9999; justify-content:center; align-items:center;">
    <div style="background:white; padding:30px; border-radius:10px; width:90%; max-width:400px; text-align:center;">
        <h3>E-posta Doğrulama</h3>
        <p>Mail adresinize gönderilen 6 haneli kodu giriniz:</p>
        <input type="text" id="kodInput" maxlength="6" style="padding:10px; font-size:16px; width:100%; margin-bottom:15px;" />
        <button onclick="dogrulaKod()" style="padding:10px 20px; font-weight:700;">Onayla</button>
        <p id="kodHata" style="color:red; margin-top:10px;"></p>
    </div>
</div>


            <div class="password-container">
                <input type="password" name="confirm_password" placeholder="Şifre Tekrar" 
                       required minlength="6" id="confirm_password" />
                <span class="toggle-password" onclick="togglePassword('confirm_password')">Göster</span>
            </div>
             <div class="password-rules">
                <strong>Şifre Kuralları:</strong>
                <ul>
                    <li id="rule-length">En az 8 karakter</li>
                    <li id="rule-uppercase">En az bir büyük harf</li>
                    <li id="rule-special">En az bir özel karakter</li>
                </ul>
            </div>

            <input type="tel" name="cep_telefonu" placeholder="Cep Telefonu" 
                   value="<?= htmlspecialchars($_POST['cep_telefonu'] ?? '') ?>" 
                   required pattern="[0-9\s\-\+]+" 
                   title="Sadece rakam, boşluk, + ve - işaretleri olabilir." />

            <input type="text" name="adres" placeholder="Adres" 
                   value="<?= htmlspecialchars($_POST['adres'] ?? '') ?>" required />

            <input type="date" name="dogum_tarihi" placeholder="Doğum Tarihi" 
                   value="<?= htmlspecialchars($_POST['dogum_tarihi'] ?? '') ?>" required />

            <select name="cinsiyet" required>
                <option value="" disabled <?= empty($_POST['cinsiyet']) ? 'selected' : '' ?>>Cinsiyet</option>
                <option value="Erkek" <?= (($_POST['cinsiyet'] ?? '') === 'Erkek') ? 'selected' : '' ?>>Erkek</option>
                <option value="Kadın" <?= (($_POST['cinsiyet'] ?? '') === 'Kadın') ? 'selected' : '' ?>>Kadın</option>
                <option value="Diğer" <?= (($_POST['cinsiyet'] ?? '') === 'Diğer') ? 'selected' : '' ?>>Diğer</option>
            </select>

            <button type="submit" class="submit-btn">Kayıt Ol</button>
        </form>

        <p class="register-link">Zaten üye misiniz? <a href="giris.php">Giriş Yap</a></p>
    </div>
</div>

<script>
    document.querySelector('form').addEventListener('submit', function(e) {
        const password = document.getElementById('password').value;
        let error = '';
        
        if (password.length < 8) {
            error = "Şifre en az 8 karakter olmalıdır.";
        } else if (!/[A-Z]/.test(password)) {
            error = "Şifre en az bir büyük harf içermelidir.";
        } else if (!/[!@#$%^&*(),.?":{}|<>]/.test(password)) {
            error = "Şifre en az bir özel karakter içermelidir.";
        }
        
        if (error) {
            e.preventDefault(); // Formun gönderilmesini engelle
            alert(error); // Hata mesajını göster
            document.getElementById('password').focus(); // Şifre alanına odaklan
        }
    });
    function togglePassword(inputId) {
        const input = document.getElementById(inputId);
        const button = input.nextElementSibling;
        
        if (input.type === "password") {
            input.type = "text";
            button.textContent = "Gizle";
        } else {
            input.type = "password";
            button.textContent = "Göster";
        }
    }
    function checkPasswordStrength() {
        const password = document.getElementById('password').value;
        
        // Uzunluk kontrolü (8 karakter)
        document.getElementById('rule-length').className = password.length >= 8 ? 'valid' : 'invalid';
        
        // Büyük harf kontrolü
        document.getElementById('rule-uppercase').className = /[A-Z]/.test(password) ? 'valid' : 'invalid';
        
        // Özel karakter kontrolü
        document.getElementById('rule-special').className = /[!@#$%^&*(),.?":{}|<>]/.test(password) ? 'valid' : 'invalid';
    }

    // Sayfa yüklendiğinde kuralları kontrol et (boş durum için)
    document.addEventListener('DOMContentLoaded', function() {
        checkPasswordStrength();
    });

    <?php if (!empty($success)): ?>
        document.addEventListener("DOMContentLoaded", function() {
            document.getElementById('kodModal').style.display = 'flex';
        });
    <?php endif; ?>

    function dogrulaKod() {
    const kod = document.getElementById("kodInput").value.trim();
    const hataAlani = document.getElementById("kodHata");

    // Önce alanı temizle
    hataAlani.textContent = "";

    // Kod boşsa uyarı ver
    if (kod === "") {
        hataAlani.textContent = "Lütfen doğrulama kodunu girin.";
        hataAlani.style.color = "red";
        return;
    }

    fetch("kayit.php", {
        method: "POST",
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: "dogrulama_kodu=" + encodeURIComponent(kod)
    })
    .then(res => res.text())
    .then(data => {
        if (data === "success") {
            hataAlani.innerHTML = "<span style='color: green; font-weight: bold;'>✔ Doğrulama başarılı. Giriş sayfasına yönlendiriliyorsunuz...</span>";
            setTimeout(() => {
                window.location.href = "giris.php";
            }, 2000);
        } else {
            hataAlani.textContent = "❌ Kod hatalı. Lütfen tekrar deneyin.";
            hataAlani.style.color = "red";
        }
    })
    .catch(() => {
        hataAlani.textContent = "❌ Bir hata oluştu. Lütfen tekrar deneyin.";
        hataAlani.style.color = "red";
    });
}
</script>

</body>
</html>