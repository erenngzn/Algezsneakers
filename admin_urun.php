<?php
$baglanti = new mysqli("localhost", "root", "", "Algezsneakers");
if ($baglanti->connect_error) die("Bağlantı hatası: " . $baglanti->connect_error);

// Ürün ekleme
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["urun_adi"])) {
    $urun_adi = $baglanti->real_escape_string($_POST["urun_adi"]);
    $aciklama = $baglanti->real_escape_string($_POST["aciklama"]);
    $kategori_id = (int)$_POST["kategori_id"];
    $marka = $baglanti->real_escape_string($_POST["marka"]);
    $fiyat = (float)$_POST["fiyat"];
    $aktif_mi = (int)$_POST["aktif_mi"];

    // Resim yükleme işlemi
    $resim_adi = NULL;
   if (isset($_FILES['resim']) && $_FILES['resim']['error'] === UPLOAD_ERR_OK) {
    $tmp_name = $_FILES['resim']['tmp_name'];
    $original_name = basename($_FILES['resim']['name']);
    $extension = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));

    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    if (in_array($extension, $allowed_extensions)) {
        // Dosya adını temizle (boşlukları ve özel karakterleri kaldır)
        $clean_name = preg_replace("/[^a-zA-Z0-9-_\.]/", "", $original_name);

        $upload_dir = __DIR__ . '/../images';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $upload_file = $upload_dir . $clean_name;

        // Eğer dosya zaten varsa, benzersiz isim yap
        $i = 1;
        $file_name_only = pathinfo($clean_name, PATHINFO_FILENAME);
        while (file_exists($upload_file)) {
            $clean_name = $file_name_only . '_' . $i . '.' . $extension;
            $upload_file = $upload_dir . $clean_name;
            $i++;
        }

        if (move_uploaded_file($tmp_name, $upload_file)) {
            $resim_adi = $clean_name;  // Veritabanına kaydedilecek isim
        } else {
            $resim_adi = NULL;
        }
    } else {
        $resim_adi = NULL;
    }
  

}


    $resim_adi_sql = $resim_adi ? "'".$baglanti->real_escape_string($resim_adi)."'" : "NULL";

    $baglanti->query("INSERT INTO urunler (urun_adi, aciklama, kategori_id, marka, fiyat, aktif_mi, resim) 
                      VALUES ('$urun_adi', '$aciklama', $kategori_id, '$marka', $fiyat, $aktif_mi, $resim_adi_sql)");

    $yeni_urun_id = $baglanti->insert_id;
      header("Location: admin_urun.php");
exit;

}

// Ürün silme
if (isset($_GET["sil"])) {
    $sil_id = (int)$_GET["sil"];
    $baglanti->query("DELETE FROM stoklar WHERE urun_id = $sil_id");
    $baglanti->query("DELETE FROM urunler WHERE urun_id = $sil_id");
    header("Location: admin_urun.php");
    exit;
}

// Fiyat güncelleme
if (isset($_POST["fiyat_guncelle"])) {
    $urun_id = (int)$_POST["urun_id"];
    $yeni_fiyat = (float)$_POST["yeni_fiyat"];
    $baglanti->query("UPDATE urunler SET fiyat = $yeni_fiyat WHERE urun_id = $urun_id");
    header("Location: admin_urun.php");
    exit;
}

// Durum değiştirme (aktif/pasif)
if (isset($_GET["durum_degistir"])) {
    $urun_id = (int)$_GET["durum_degistir"];
    $baglanti->query("UPDATE urunler SET aktif_mi = NOT aktif_mi WHERE urun_id = $urun_id");
    header("Location: admin_urun.php");
    exit;
}

// Kategoriler ve ürünler
$kategoriler = $baglanti->query("SELECT * FROM kategoriler");
$urunler = $baglanti->query("SELECT u.*, k.kategori_adi FROM urunler u LEFT JOIN kategoriler k ON u.kategori_id = k.kategori_id");

// İstatistikler
$toplam_urun = $baglanti->query("SELECT COUNT(*) as toplam FROM urunler")->fetch_assoc()['toplam'];
$aktif_urun = $baglanti->query("SELECT COUNT(*) as aktif FROM urunler WHERE aktif_mi = 1")->fetch_assoc()['aktif'];
$pasif_urun = $toplam_urun - $aktif_urun;
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>AlgezSneakers - Ürün Yönetimi</title>
    <link rel="icon" type="image/jpeg" href="../images/logo.jpg">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Raleway:wght@400;600;700;800&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="adminurun.css" />
</head>
<body>
    <div class="loading" id="loading">
        <div class="spinner"></div>
    </div>

    <!-- Header -->
    <div class="header">
        <div class="header-content">
            <div class="logo">AlgezSneakers</div>
            <div class="admin-badge">
                <i class="fas fa-user-shield"></i>
                Admin Panel
            </div>
        </div>
    </div>

    <!-- Sidebar Toggle Button -->
    <button class="sidebar-toggle" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <ul class="sidebar-menu">
            <li><a href="admin_kategori.php"><i class="fas fa-tags"></i> Kategori Yönetimi</a></li>
            <li><a href="admin_urun.php" class="active"><i class="fas fa-box"></i> Ürün Yönetimi</a></li>
            <li><a href="admin_numara.php"><i class="fas fa-sort-numeric-up"></i> Numara/Stok Yönetimi</a></li>
            <li><a href="admin_siparis.php"><i class="fas fa-shopping-cart"></i> Sipariş Yönetimi</a></li>
            <li><a href="giris.php"><i class="fas fa-sign-out-alt"></i> Çıkış Yap</a></li>
        </ul>
    </div>

    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

    <!-- Main Content -->
    <div class="main-content" id="mainContent">
        <h1 class="page-title">Ürün Yönetimi</h1>
        <p class="page-subtitle">Mağaza ürünlerini ekleyin, düzenleyin ve yönetin</p>

        <!-- Stats Cards -->
        <div class="stats-grid fade-in">
            <div class="stat-card">
                <span class="stat-number"><?= $toplam_urun ?></span>
                <div class="stat-label">Toplam Ürün</div>
            </div>
            <div class="stat-card">
                <span class="stat-number"><?= $aktif_urun ?></span>
                <div class="stat-label">Aktif Ürün</div>
            </div>
            <div class="stat-card">
                <span class="stat-number"><?= $pasif_urun ?></span>
                <div class="stat-label">Pasif Ürün</div>
            </div>
        </div>

        <!-- Content Grid -->
        <div class="content-grid">
            <!-- Add Product Form -->
            <div class="card fade-in">
                <h2>
                    <i class="fas fa-plus-circle"></i>
                    Yeni Ürün Ekle
                </h2>
                <form method="POST" enctype="multipart/form-data" onsubmit="showLoading()">
                    <div class="form-group">
                        <label for="urun_adi">Ürün Adı</label>
                        <input type="text" id="urun_adi" name="urun_adi" class="form-control" placeholder="Örn: Nike Air Max 270" required />
                    </div>

                    <div class="form-group">
                        <label for="aciklama">Ürün Açıklaması</label>
                        <textarea id="aciklama" name="aciklama" class="form-control" placeholder="Bu ürün hakkında detaylı açıklama yazın..."></textarea>
                    </div>

                    <div class="form-group">
                        <label for="kategori_id">Kategori</label>
                        <select id="kategori_id" name="kategori_id" class="form-control" required>
                            <option value="">-- Kategori Seçin --</option>
                            <?php while($kategori = $kategoriler->fetch_assoc()): ?>
                                <option value="<?= $kategori['kategori_id'] ?>">
                                    <?= htmlspecialchars($kategori['kategori_adi']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="marka">Marka</label>
                        <input type="text" id="marka" name="marka" class="form-control" placeholder="Örn: Nike, Adidas, Puma" />
                    </div>

                    <div class="form-group">
                        <label for="fiyat">Fiyat (₺)</label>
                        <input type="number" step="0.01" id="fiyat" name="fiyat" class="form-control" placeholder="0.00" required />
                    </div>

                    <div class="form-group">
                        <label for="resim">Ürün Resmi</label>
                        <input type="file" id="resim" name="resim" class="form-control" accept="image/*" />
                    </div>

                    <div class="form-group">
                        <label for="aktif_mi">Durum</label>
                        <select id="aktif_mi" name="aktif_mi" class="form-control">
                            <option value="1">✅ Aktif</option>
                            <option value="0">❌ Pasif</option>
                        </select>
                    </div>

                    
                    

                    <button type="submit" class="btn">
                        <i class="fas fa-save"></i>
                        Ürün Kaydet
                    </button>
                </form>
            </div>

            <!-- Product List -->
            <div class="card fade-in">
                <h2>
                    <i class="fas fa-list"></i>
                    Ürün Listesi
                </h2>

                <div class="table-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <th><i class="fas fa-hashtag"></i> ID</th>
                                <th><i class="fas fa-box"></i> Ürün Adı</th>
                                <th><i class="fas fa-tag"></i> Kategori</th>
                                <th><i class="fas fa-industry"></i> Marka</th>
                                <th><i class="fas fa-money-bill"></i> Fiyat</th>
                                <th><i class="fas fa-toggle-on"></i> Durum</th>
                                <th><i class="fas fa-cogs"></i> İşlemler</th>
                                <th><i class="fas fa-image"></i> Resim</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $urunler->data_seek(0);
                            while($urun = $urunler->fetch_assoc()):
                            ?>
                            <tr>
                                <td><?= $urun["urun_id"] ?></td>
                                <td><?= htmlspecialchars($urun["urun_adi"]) ?></td>
                                <td><?= htmlspecialchars($urun["kategori_adi"]) ?></td>
                                <td><?= htmlspecialchars($urun["marka"]) ?></td>

                                <td>
                                    <span class="price-badge">
                                        <?= number_format($urun["fiyat"], 2) ?> ₺
                                    </span>
                                    <button class="btn btn-sm" onclick="toggleFiyatGuncelle(<?= $urun['urun_id'] ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <div id="fiyatGuncelleForm_<?= $urun['urun_id'] ?>" style="display:none; margin-top:5px;">
                                        <form method="POST" style="display:flex; gap:5px;">
                                            <input type="hidden" name="urun_id" value="<?= $urun['urun_id'] ?>" />
                                            <input type="number" step="0.01" name="yeni_fiyat" value="<?= $urun['fiyat'] ?>" class="form-control" style="padding:5px; width:80px;" required />
                                            <button type="submit" name="fiyat_guncelle" class="btn btn-sm" style="padding:5px 10px;">
                                                <i class="fas fa-check"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>

                                <td>
                                    <a href="admin_urun.php?durum_degistir=<?= $urun["urun_id"] ?>" class="status-badge <?= $urun["aktif_mi"] ? 'status-active' : 'status-inactive' ?>" title="<?= $urun["aktif_mi"] ? 'Aktif' : 'Pasif' ?>">
                                        <?= $urun["aktif_mi"] ? '✅' : '❌' ?>
                                    </a>
                                </td>

                                <td style="white-space: nowrap;">
                                    <a href="admin_urun.php?sil=<?= $urun["urun_id"] ?>" class="btn btn-danger" onclick="return confirm('Bu ürünü silmek istediğinize emin misiniz?')">
                                        <i class="fas fa-trash"></i>
                                        Sil
                                    </a>
                                </td>

                                <td>
                                    <?php if ($urun['resim'] && file_exists(__DIR__ . '/../images/' . $urun['resim'])): ?>
                                    <img src="../images/<?= htmlspecialchars($urun['resim']) ?>" alt="<?= htmlspecialchars($urun['urun_adi']) ?>" style="height:50px; object-fit: contain; border-radius: 8px;" />
                                    <?php else: ?>
                                    <span>Yok</span>
                                    <?php endif; ?>

                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

<script src="adminurun.js"></script>
</body>
</html>
