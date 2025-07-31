<?php
// Veritabanı bağlantısı
$host = 'localhost';
$dbname = 'algezsneakers';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Veritabanı bağlantı hatası: " . $e->getMessage());
}

// Yeni kategori ekleme işlemi
if ($_POST && isset($_POST['kategori_adi'])) {
    try {
        $kategori_adi = trim($_POST['kategori_adi']);
        $aciklama = trim($_POST['aciklama']);
        $aktif_mi = (int)$_POST['aktif_mi'];
        
        // Aynı isimde kategori var mı kontrol et
        $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM kategoriler WHERE kategori_adi = ?");
        $check_stmt->execute([$kategori_adi]);
        
        if ($check_stmt->fetchColumn() > 0) {
            $error_message = "Bu kategori adı zaten mevcut!";
        } else {
            $stmt = $pdo->prepare("INSERT INTO kategoriler (kategori_adi, aciklama, aktif_mi, olusturma_tarihi) VALUES (?, ?, ?, NOW())");
            $stmt->execute([$kategori_adi, $aciklama, $aktif_mi]);
            $success_message = "Kategori başarıyla eklendi!";
        }
    } catch(PDOException $e) {
        $error_message = "Kategori eklenirken hata oluştu: " . $e->getMessage();
    }
}

// Kategori silme işlemi
if (isset($_GET['sil']) && is_numeric($_GET['sil'])) {
    try {
        $kategori_id = (int)$_GET['sil'];
        
        // Önce bu kategoriye ait ürün var mı kontrol et
        $check_products = $pdo->prepare("SELECT COUNT(*) FROM urunler WHERE kategori_id = ?");
        $check_products->execute([$kategori_id]);
        
        if ($check_products->fetchColumn() > 0) {
            $error_message = "Bu kategoriye ait ürünler var, önce ürünleri silin!";
        } else {
            $delete_stmt = $pdo->prepare("DELETE FROM kategoriler WHERE kategori_id = ?");
            $delete_stmt->execute([$kategori_id]);
            $success_message = "Kategori başarıyla silindi!";
        }
    } catch(PDOException $e) {
        $error_message = "Kategori silinirken hata oluştu: " . $e->getMessage();
    }
}

// Kategori aktif/pasif durumunu değiştirme
if (isset($_GET['durum']) && isset($_GET['id']) && is_numeric($_GET['id'])) {
    try {
        $kategori_id = (int)$_GET['id'];
        $yeni_durum = (int)$_GET['durum'];
        
        $update_stmt = $pdo->prepare("UPDATE kategoriler SET aktif_mi = ? WHERE kategori_id = ?");
        $update_stmt->execute([$yeni_durum, $kategori_id]);
        
        $_SESSION['success_message'] = "Kategori durumu başarıyla güncellendi!";
        header("Location: admin_kategori.php");
        exit();
    } catch(PDOException $e) {
        $error_message = "Durum güncellenirken hata oluştu: " . $e->getMessage();
    }
}

// Kategorileri çek
try {
    $kategoriler_stmt = $pdo->prepare("SELECT * FROM kategoriler ORDER BY kategori_id DESC");
    $kategoriler_stmt->execute();
    $kategoriler = $kategoriler_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // İstatistikleri hesapla
    $toplam_kategori = count($kategoriler);
    $aktif_kategori = count(array_filter($kategoriler, function($k) { return $k['aktif_mi'] == 1; }));
    $pasif_kategori = $toplam_kategori - $aktif_kategori;
    
} catch(PDOException $e) {
    $error_message = "Kategoriler yüklenirken hata oluştu: " . $e->getMessage();
    $kategoriler = [];
    $toplam_kategori = $aktif_kategori = $pasif_kategori = 0;
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AlgezSneakers - Kategori Yönetimi</title>
    <link rel="icon" type="image/jpeg" href="../images/logo.jpg">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Raleway:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="adminkategori.css"> 
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
            <li><a href="#" class="active"><i class="fas fa-tags"></i> Kategori Yönetimi</a></li>
            <li><a href="admin_urun.php"><i class="fas fa-box"></i> Ürün Yönetimi</a></li>
            <li><a href="admin_numara.php" class="active"><i class="fas fa-sort-numeric-up"></i> Numara/Stok Yönetimi</a></li>
            <li><a href="admin_siparis.php"><i class="fas fa-shopping-cart"></i> Sipariş Yönetimi</a></li>
            <li><a href="giris.php"><i class="fas fa-sign-out-alt"></i> Çıkış Yap</a></li>
            
        </ul>
    </div>

    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

    <!-- Main Content -->
    <div class="main-content" id="mainContent">
        <h1 class="page-title">Kategori Yönetimi</h1>
        <p class="page-subtitle">Ürün kategorilerini ekleyin, düzenleyin ve yönetin</p>

        <!-- Alert Messages -->
        <?php if (isset($success_message)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo $success_message; ?>
            </div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo $error_message; ?>
            </div>
        <?php endif; ?>

        <!-- Stats Cards -->
        <div class="stats-grid fade-in">
            <div class="stat-card">
                <span class="stat-number"><?php echo $toplam_kategori; ?></span>
                <div class="stat-label">Toplam Kategori</div>
            </div>
            <div class="stat-card">
                <span class="stat-number"><?php echo $aktif_kategori; ?></span>
                <div class="stat-label">Aktif Kategori</div>
            </div>
            <div class="stat-card">
                <span class="stat-number"><?php echo $pasif_kategori; ?></span>
                <div class="stat-label">Pasif Kategori</div>
            </div>
        </div>

        <!-- Content Grid -->
        <div class="content-grid">
            <!-- Add Category Form -->
            <div class="card fade-in">
                <h2>
                    <i class="fas fa-plus-circle"></i>
                    Yeni Kategori Ekle
                </h2>
                <form method="POST" onsubmit="showLoading()">
                    <div class="form-group">
                        <label for="kategori_adi">Kategori Adı</label>
                        <input type="text" id="kategori_adi" name="kategori_adi" class="form-control" placeholder="Örn: Spor Ayakkabıları" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="aciklama">Kategori Açıklaması</label>
                        <textarea id="aciklama" name="aciklama" class="form-control" placeholder="Bu kategori hakkında kısa bir açıklama yazın..."></textarea>
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
                        Kategori Kaydet
                    </button>
                </form>
            </div>

            <!-- Category List -->
            <div class="card fade-in">
                <h2>
                    <i class="fas fa-list"></i>
                    Kategori Listesi
                </h2>
                
                <div class="table-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <th><i class="fas fa-hashtag"></i> ID</th>
                                <th><i class="fas fa-tag"></i> Kategori Adı</th>
                                <th><i class="fas fa-info-circle"></i> Açıklama</th>
                                <th><i class="fas fa-toggle-on"></i> Durum</th>
                                <th><i class="fas fa-cogs"></i> İşlemler</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($kategoriler)): ?>
                                <tr>
                                    <td colspan="5" style="text-align: center; padding: 40px;">
                                        <i class="fas fa-inbox" style="font-size: 48px; color: #ccc; margin-bottom: 10px;"></i>
                                        <br>Henüz kategori eklenmemiş.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($kategoriler as $kategori): ?>
                                    <tr>
                                        <td><?php echo $kategori['kategori_id']; ?></td>
                                        <td><?php echo htmlspecialchars($kategori['kategori_adi']); ?></td>
                                        <td><?php echo htmlspecialchars($kategori['aciklama']); ?></td>
                                        <td>
                                            <span class="status-badge <?php echo $kategori['aktif_mi'] ? 'status-active' : 'status-inactive'; ?>">
                                                <?php echo $kategori['aktif_mi'] ? 'Aktif' : 'Pasif'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group">
                                                <a href="?id=<?php echo $kategori['kategori_id']; ?>&durum=1" 
                                                   class="btn btn-sm <?php echo $kategori['aktif_mi'] ? 'btn-success' : 'btn-outline-success'; ?>" 
                                                   title="Aktif Yap">
                                                    <i class="fas fa-check"></i>
                                                </a>
                                                <a href="?id=<?php echo $kategori['kategori_id']; ?>&durum=0" 
                                                   class="btn btn-sm <?php echo !$kategori['aktif_mi'] ? 'btn-danger' : 'btn-outline-danger'; ?>" 
                                                   title="Pasif Yap">
                                                    <i class="fas fa-times"></i>
                                                </a>
                                                <a href="?sil=<?php echo $kategori['kategori_id']; ?>" 
                                                   class="btn btn-sm btn-outline-dark" 
                                                   title="Sil"
                                                   onclick="return confirm('Bu kategoriyi silmek istediğinize emin misiniz?')">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <script src="adminkategori.js"></script>
</body>
</html>