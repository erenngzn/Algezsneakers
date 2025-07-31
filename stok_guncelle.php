<?php
require 'baglanti.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['urun_id'], $_POST['numara_id'], $_POST['stok_adedi'])) {
    try {
        $urun_id = (int)$_POST['urun_id'];
        $numara_id = (int)$_POST['numara_id'];
        $stok_adedi = (int)$_POST['stok_adedi'];
        $kategori_id = (int)$_POST['kategori_id'];

        $check_stmt = $pdo->prepare("SELECT stok_id FROM stoklar WHERE urun_id = ? AND numara_id = ?");
        $check_stmt->execute([$urun_id, $numara_id]);
        $stok_kaydi = $check_stmt->fetch();

        if ($stok_kaydi) {
            $update_stmt = $pdo->prepare("UPDATE stoklar SET stok_adedi = ? WHERE stok_id = ?");
            $update_stmt->execute([$stok_adedi, $stok_kaydi['stok_id']]);
            $mesaj = "Stok güncellendi";
        } else {
            $insert_stmt = $pdo->prepare("INSERT INTO stoklar (urun_id, numara_id, stok_adedi) VALUES (?, ?, ?)");
            $insert_stmt->execute([$urun_id, $numara_id, $stok_adedi]);
            $mesaj = "Stok eklendi";
        }

        header("Location: admin_numara.php?kategori_id=$kategori_id&urun_id=$urun_id&success=" . urlencode($mesaj));
        exit;
    } catch (PDOException $e) {
        header("Location: admin_numara.php?kategori_id=$kategori_id&urun_id=$urun_id&error=" . urlencode("Stok işlemi hatası: " . $e->getMessage()));
        exit;
    }
} else {
    header("Location: admin_numara.php");
    exit;
}
?>
