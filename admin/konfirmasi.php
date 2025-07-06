<?php
require_once '../config.php';
require_once '../functions.php';
requireLogin();

// Ambil user_bills dengan status menunggu_konfirmasi
$stmt = $pdo->prepare("SELECT ub.*, u.username, b.kode_tagihan, b.jumlah, b.deskripsi, b.tenggat_waktu, b.tanggal AS tanggal_tagihan FROM user_bills ub JOIN users u ON ub.user_id = u.id JOIN bills b ON ub.bill_id = b.id WHERE ub.status = 'menunggu_konfirmasi' ORDER BY ub.tanggal_upload DESC");
$stmt->execute();
$user_bills = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Jika tombol jalankan OCR ditekan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_ocr'])) {
    $user_bill_id = (int) $_POST['user_bill_id'];
    $stmt = $pdo->prepare("SELECT ub.*, b.kode_tagihan, b.jumlah FROM user_bills ub JOIN bills b ON ub.bill_id = b.id WHERE ub.id = ?");
    $stmt->execute([$user_bill_id]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($data && $data['bukti_pembayaran']) {
        $image_path = realpath(__DIR__ . '/../warga/uploads/bukti_pembayaran/' . $data['bukti_pembayaran']);
        $kode_tagihan = escapeshellarg($data['kode_tagihan']);
        $jumlah = (int) $data['jumlah'];

        $cmd = "python3 ../ocr.py " . escapeshellarg($image_path) . " $kode_tagihan $jumlah";
        $output = shell_exec($cmd);

        $ocr_result = json_decode($output, true);

        if ($ocr_result) {
            $stmt = $pdo->prepare("UPDATE user_bills SET ocr_jumlah = ?, ocr_kode_found = ?, ocr_date_found = ?, ocr_confidence = ?, ocr_details = ? WHERE id = ?");
            $stmt->execute([
                $ocr_result['jumlah'] ?? null,
                isset($ocr_result['kode_tagihan']) ? 1 : 0,
                isset($ocr_result['tanggal']) ? 1 : 0,
                $ocr_result['confidence'] ?? 0,
                json_encode($ocr_result),
                $user_bill_id
            ]);
        }
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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        .img-preview { width: 120px; height: auto; border-radius: 6px; cursor: pointer; }
        .table td, .table th { vertical-align: middle; }
    </style>
</head>
<body class="p-4">
    <div class="container">
        <h3>Daftar Pembayaran Menunggu Konfirmasi</h3>
        <table class="table table-bordered table-striped">
            <thead class="table-dark">
                <tr>
                    <th>Pengguna</th>
                    <th>Bukti</th>
                    <th>Kode Tagihan</th>
                    <th>Jumlah</th>
                    <th>Deskripsi</th>
                    <th>Tanggal Upload</th>
                    <th>Tenggat</th>
                    <th>Hasil OCR</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($user_bills as $bill): ?>
                <tr>
                    <td><?= htmlspecialchars($bill['username']) ?></td>
                    <td>
                        <?php if ($bill['bukti_pembayaran']): ?>
                            <img src="../warga/uploads/bukti_pembayaran/<?= htmlspecialchars($bill['bukti_pembayaran']) ?>" class="img-preview">
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($bill['kode_tagihan']) ?></td>
                    <td>Rp <?= number_format($bill['jumlah'], 0, ',', '.') ?></td>
                    <td><?= nl2br(htmlspecialchars($bill['deskripsi'])) ?></td>
                    <td><?= date('d-m-Y H:i', strtotime($bill['tanggal_upload'])) ?></td>
                    <td><?= date('d-m-Y', strtotime($bill['tenggat_waktu'])) ?></td>
                    <td>
                        <?php
                        $details = json_decode($bill['ocr_details'], true);
                        ?>
                        <div><strong>Jumlah:</strong> Rp <?= isset($details['jumlah']) ? number_format($details['jumlah'], 0, ',', '.') : '❌' ?></div>
                        <div><strong>Kode:</strong> <?= isset($details['kode_tagihan']) ? $details['kode_tagihan'] : '❌' ?></div>
                        <div><strong>Tanggal:</strong> <?= isset($details['tanggal']) ? $details['tanggal'] : '❌' ?></div>
                        <div><strong>Akurasi:</strong> <?= $bill['ocr_confidence'] ?>%</div>
                    </td>
                    <td>
                        <form method="POST" class="mb-2">
                            <input type="hidden" name="user_bill_id" value="<?= $bill['id'] ?>">
                            <button type="submit" name="run_ocr" class="btn btn-sm btn-outline-primary">🔍 Jalankan OCR</button>
                        </form>
                        <form method="POST" action="proses_konfirmasi.php">
                            <input type="hidden" name="user_bill_id" value="<?= $bill['id'] ?>">
                            <select name="status" class="form-select form-select-sm mb-1" required>
                                <option value="">Pilih Aksi</option>
                                <option value="konfirmasi">✓ Konfirmasi</option>
                                <option value="tolak">✗ Tolak</option>
                            </select>
                            <button type="submit" class="btn btn-sm btn-success">Proses</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html>
