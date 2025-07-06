<?php
require_once '../config.php';
requireLogin();

// Jalankan OCR otomatis untuk semua user_bills yang menunggu konfirmasi dan punya bukti pembayaran
$ocr_stmt = $pdo->query("SELECT ub.id, ub.bukti_pembayaran, b.kode_tagihan, b.jumlah 
                         FROM user_bills ub
                         JOIN bills b ON ub.bill_id = b.id
                         WHERE ub.status = 'menunggu_konfirmasi' AND ub.bukti_pembayaran IS NOT NULL");

foreach ($ocr_stmt as $ocr_row) {
    $image_path = '../warga/uploads/bukti_pembayaran/' . $ocr_row['bukti_pembayaran'];
    $cmd = "python3 ../admin/ocr.py " . escapeshellarg($image_path) . " " . escapeshellarg($ocr_row['kode_tagihan']) . " " . escapeshellarg($ocr_row['jumlah']);
    $output = shell_exec($cmd);
    $result = json_decode($output, true);

    if ($result) {
        $ocr_details = json_encode([
            'extracted_text' => $result['extracted_text'] ?? '',
            'normalized_text' => $result['normalized_text'] ?? '',
            'extracted_code' => $result['kode_tagihan'] ?? '',
            'extracted_date' => $result['tanggal'] ?? '',
        ]);

        $stmt2 = $pdo->prepare("UPDATE user_bills SET 
            ocr_jumlah = ?,
            ocr_kode_found = ?,
            ocr_date_found = ?,
            ocr_details = ?,
            ocr_confidence = ?
            WHERE id = ?");
        $stmt2->execute([
            $result['jumlah'] ?? 0,
            isset($result['kode_tagihan']) ? 1 : 0,
            isset($result['tanggal']) ? 1 : 0,
            $ocr_details,
            98.5,
            $ocr_row['id']
        ]);
    }
}

// Proses form konfirmasi/tolak
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_bill_id'], $_POST['status'])) {
    $id = $_POST['user_bill_id'];
    $status = $_POST['status'];
    if (in_array($status, ['konfirmasi', 'tolak'])) {
        $stmt = $pdo->prepare("UPDATE user_bills SET status = ? WHERE id = ?");
        $stmt->execute([$status, $id]);
    }
    header('Location: konfirmasi.php');
    exit;
}

// Ambil data semua tagihan yang menunggu konfirmasi
$stmt = $pdo->query("SELECT ub.id as user_bill_id, ub.bukti_pembayaran, ub.ocr_jumlah, ub.ocr_kode_found, ub.ocr_date_found,
                            ub.ocr_details, ub.status, ub.tanggal_upload,
                            b.kode_tagihan, b.jumlah, b.deskripsi, b.tanggal as tanggal_tagihan,
                            b.tenggat_waktu, u.username
                     FROM user_bills ub
                     JOIN bills b ON ub.bill_id = b.id
                     JOIN users u ON ub.user_id = u.id
                     WHERE ub.status = 'menunggu_konfirmasi'");

$tagihans = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Konfirmasi Pembayaran</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        img.preview {
            max-height: 120px;
            cursor: pointer;
        }
    </style>
</head>
<body class="container py-4">
    <h2>Daftar Tagihan Menunggu Konfirmasi</h2>
    <table class="table table-bordered table-striped">
        <thead>
            <tr>
                <th>Nama</th>
                <th>Kode Tagihan</th>
                <th>Deskripsi</th>
                <th>Jumlah</th>
                <th>Tanggal</th>
                <th>Tenggat</th>
                <th>Bukti</th>
                <th>OCR</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($tagihans as $bill): ?>
                <tr>
                    <td><?= htmlspecialchars($bill['username']) ?></td>
                    <td><?= htmlspecialchars($bill['kode_tagihan']) ?></td>
                    <td><?= nl2br(htmlspecialchars($bill['deskripsi'])) ?></td>
                    <td>Rp <?= number_format($bill['jumlah'], 0, ',', '.') ?></td>
                    <td><?= htmlspecialchars($bill['tanggal_tagihan']) ?></td>
                    <td><?= htmlspecialchars($bill['tenggat_waktu']) ?></td>
                    <td>
                        <?php if ($bill['bukti_pembayaran']): ?>
                            <img src="../warga/uploads/bukti_pembayaran/<?= htmlspecialchars($bill['bukti_pembayaran']) ?>" class="preview">
                        <?php else: ?>
                            <em>Belum upload</em>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php
                        $ocr = json_decode($bill['ocr_details'], true);
                        echo 'Jumlah: Rp ' . number_format($bill['ocr_jumlah'], 0, ',', '.') . '<br>';
                        echo 'Kode: ' . ($ocr['extracted_code'] ?? '-') . '<br>';
                        echo 'Tanggal: ' . ($ocr['extracted_date'] ?? '-');
                        ?>
                    </td>
                    <td>
                        <form method="POST">
                            <input type="hidden" name="user_bill_id" value="<?= $bill['user_bill_id'] ?>">
                            <select name="status" class="form-select form-select-sm" required>
                                <option value="">--Aksi--</option>
                                <option value="konfirmasi">✓ Konfirmasi</option>
                                <option value="tolak">✗ Tolak</option>
                            </select>
                            <button type="submit" class="btn btn-sm btn-primary mt-1">Proses</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</body>
</html>
