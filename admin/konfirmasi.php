<?php
require_once '../config.php';
requireAdmin();

// Jalankan OCR jika tombol ditekan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_ocr'])) {
    $user_bill_id = (int) $_POST['user_bill_id'];

    $stmt = $pdo->prepare("SELECT ub.*, b.kode_tagihan, b.jumlah 
        FROM user_bills ub 
        JOIN bills b ON ub.bill_id = b.id 
        WHERE ub.id = ?");
    $stmt->execute([$user_bill_id]);
    $bill = $stmt->fetch();

    if ($bill && $bill['bukti_pembayaran']) {
        $image_path = '../warga/uploads/bukti_pembayaran/' . $bill['bukti_pembayaran'];
        $kode_tagihan = escapeshellarg($bill['kode_tagihan']);
        $jumlah = escapeshellarg($bill['jumlah']);
        $escaped_image = escapeshellarg($image_path);

        $command = "python3 ocr.py $escaped_image $kode_tagihan $jumlah";
        $output = shell_exec($command);
        $ocr_result = json_decode($output, true);

        if ($ocr_result) {
            $ocr_jumlah = (int) ($ocr_result['jumlah'] ?? 0);
            $ocr_kode_found = isset($ocr_result['kode_tagihan']);
            $ocr_date_found = isset($ocr_result['tanggal']);
            $ocr_confidence = 90.0; // Sementara static, bisa ganti nanti
            $ocr_details = json_encode([
                'extracted_text' => $ocr_result['extracted_text'] ?? '',
                'normalized_text' => $ocr_result['normalized_text'] ?? '',
                'extracted_code' => $ocr_result['kode_tagihan'] ?? '',
                'extracted_date' => $ocr_result['tanggal'] ?? '',
            ]);

            $update = $pdo->prepare("UPDATE user_bills SET 
                ocr_jumlah = ?, 
                ocr_kode_found = ?, 
                ocr_date_found = ?, 
                ocr_confidence = ?, 
                ocr_details = ? 
                WHERE id = ?");
            $update->execute([$ocr_jumlah, $ocr_kode_found, $ocr_date_found, $ocr_confidence, $ocr_details, $user_bill_id]);
        }
    }
}

// Ambil semua tagihan yang menunggu konfirmasi
$stmt = $pdo->query("SELECT ub.*, b.kode_tagihan, b.jumlah, b.deskripsi, b.tenggat_waktu, b.tanggal AS tanggal_kirim, u.username 
    FROM user_bills ub
    JOIN bills b ON ub.bill_id = b.id
    JOIN users u ON ub.user_id = u.id
    WHERE ub.status = 'menunggu_konfirmasi'");
$bills = $stmt->fetchAll();

function format_tgl($tgl) {
    return date('d M Y', strtotime($tgl));
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Konfirmasi Pembayaran</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="p-4">
<h2>Daftar Pembayaran Menunggu Konfirmasi</h2>
<table class="table table-bordered">
    <thead>
        <tr>
            <th>User</th>
            <th>Kode Tagihan</th>
            <th>Jumlah</th>
            <th>Deskripsi</th>
            <th>Upload</th>
            <th>OCR</th>
            <th>Aksi</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($bills as $bill): ?>
        <tr>
            <td><?= htmlspecialchars($bill['username']) ?></td>
            <td><?= htmlspecialchars($bill['kode_tagihan']) ?></td>
            <td>Rp <?= number_format($bill['jumlah'], 0, ',', '.') ?></td>
            <td><?= htmlspecialchars($bill['deskripsi']) ?></td>
            <td>
                <?php if ($bill['bukti_pembayaran']): ?>
                    <img src="../warga/uploads/bukti_pembayaran/<?= htmlspecialchars($bill['bukti_pembayaran']) ?>" width="100">
                <?php else: ?>
                    <span class="text-muted">Tidak ada</span>
                <?php endif; ?>
            </td>
            <td>
                <?php if ($bill['ocr_jumlah']): ?>
                    Rp <?= number_format($bill['ocr_jumlah'], 0, ',', '.') ?>
                    <br><small><?= $bill['ocr_details'] ? json_decode($bill['ocr_details'], true)['extracted_date'] ?? '' : '' ?></small>
                <?php else: ?>
                    <form method="POST">
                        <input type="hidden" name="user_bill_id" value="<?= $bill['id'] ?>">
                        <button name="run_ocr" class="btn btn-sm btn-warning">Jalankan OCR</button>
                    </form>
                <?php endif; ?>
            </td>
            <td>
                <form method="POST">
                    <input type="hidden" name="user_bill_id" value="<?= $bill['id'] ?>">
                    <select name="status" class="form-select form-select-sm" required>
                        <option value="">Pilih</option>
                        <option value="konfirmasi">✓ Konfirmasi</option>
                        <option value="tolak">✗ Tolak</option>
                    </select>
                    <button type="submit" name="action" value="update_status" class="btn btn-sm btn-success mt-1">Simpan</button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</body>
</html>

<?php
// Proses perubahan status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $status = $_POST['status'];
    $user_bill_id = (int) $_POST['user_bill_id'];

    $update = $pdo->prepare("UPDATE user_bills SET status = ? WHERE id = ?");
    $update->execute([$status, $user_bill_id]);

    header("Location: konfirmasi.php");
    exit;
}
?>
