<?php
require_once '../config.php';
requireAdmin();

// Fungsi Ambil Status Midtrans
function fetch_midtrans_status_display($order_id) {
    try {
        $result = getMidtransTransactionStatus($order_id);
        return $result['transaction_status'] ?? 'Tidak Diketahui';
    } catch (Exception $e) {
        return '❌ Error';
    }
}

// Ambil tagihan yang menunggu konfirmasi
$stmt = $pdo->query("SELECT ub.*, u.nama_lengkap, u.no_rumah FROM user_bills ub JOIN users u ON ub.user_id = u.id WHERE ub.status = 'menunggu_konfirmasi'");
$tagihan = $stmt->fetchAll();

// Proses konfirmasi atau tolak
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'];
    $aksi = $_POST['aksi'];

    if ($aksi === 'konfirmasi') {
        // Pindahkan ke tabel tagihan_oke dan hapus dari user_bills
        $stmt = $pdo->prepare("INSERT INTO tagihan_oke SELECT * FROM user_bills WHERE id = ?");
        $stmt->execute([$id]);
        $stmt = $pdo->prepare("DELETE FROM user_bills WHERE id = ?");
        $stmt->execute([$id]);
    } elseif ($aksi === 'tolak') {
        $stmt = $pdo->prepare("UPDATE user_bills SET status = 'ditolak' WHERE id = ?");
        $stmt->execute([$id]);
    }

    header('Location: konfirmasi.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Konfirmasi Pembayaran</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
</head>
<body class="bg-light">
<div class="container py-5">
    <h2 class="mb-4">Konfirmasi Pembayaran</h2>
    <div class="table-responsive">
        <table class="table table-bordered table-striped align-middle">
            <thead class="table-dark">
                <tr>
                    <th>Nama</th>
                    <th>No Rumah</th>
                    <th>Jumlah</th>
                    <th>Bukti</th>
                    <th>Hasil OCR</th>
                    <th>Midtrans</th>
                    <th>Status</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($tagihan as $bill): ?>
                <?php
                // Jalankan OCR jika bukti ada
                $buktiPath = '../warga/uploads/bukti_pembayaran/' . $bill['bukti_pembayaran'];
                $ocr_result = file_exists($buktiPath) ? shell_exec("python3 ../ocr/ocr.py " . escapeshellarg($buktiPath)) : 'Bukti tidak ditemukan';
                
                // Ambil status Midtrans
                $midtrans_status = fetch_midtrans_status_display($bill['kode_tagihan']);
                $badge_class = match($midtrans_status) {
                    'settlement' => 'badge bg-success',
                    'pending' => 'badge bg-warning text-dark',
                    'cancel', 'expire' => 'badge bg-danger',
                    default => 'badge bg-secondary'
                };
                ?>
                <tr>
                    <td><?= sanitize($bill['nama_lengkap']) ?></td>
                    <td><?= sanitize($bill['no_rumah']) ?></td>
                    <td>Rp<?= number_format($bill['jumlah'], 0, ',', '.') ?></td>
                    <td>
                        <?php if (file_exists($buktiPath)): ?>
                            <a href="<?= $buktiPath ?>" target="_blank">Lihat Bukti</a>
                        <?php else: ?>
                            Tidak Ada
                        <?php endif; ?>
                    </td>
                    <td><pre><?= sanitize($ocr_result) ?></pre></td>
                    <td><span class="<?= $badge_class ?>"><?= htmlspecialchars($midtrans_status) ?></span></td>
                    <td><span class="badge bg-info"><?= sanitize($bill['status']) ?></span></td>
                    <td>
                        <form method="post" class="d-flex gap-2">
                            <input type="hidden" name="id" value="<?= $bill['id'] ?>">
                            <button type="submit" name="aksi" value="konfirmasi" class="btn btn-success btn-sm">Konfirmasi</button>
                            <button type="submit" name="aksi" value="tolak" class="btn btn-danger btn-sm">Tolak</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>
