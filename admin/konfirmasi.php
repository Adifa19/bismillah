<?php
require_once '../config.php';
requireAdmin();

// Jalankan OCR jika diminta
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_ocr'], $_POST['user_bill_id'])) {
    $user_bill_id = (int)$_POST['user_bill_id'];

    $stmt = $pdo->prepare("SELECT ub.*, b.kode_tagihan, b.jumlah FROM user_bills ub JOIN bills b ON ub.bill_id = b.id WHERE ub.id = ?");
    $stmt->execute([$user_bill_id]);
    $bill = $stmt->fetch();

    if ($bill && $bill['bukti_pembayaran']) {
        $image_path = '../warga/uploads/bukti_pembayaran/' . $bill['bukti_pembayaran'];
        $ocr_script_path = __DIR__ . '/ocr.py';

        // Jalankan ocr.py dengan shell_exec, tangkap error juga
        $command = escapeshellcmd("python3 $ocr_script_path " . escapeshellarg($image_path)) . " 2>&1";
       $output = shell_exec($command . ' 2>&1');

// Tambahkan pengecekan output dan buat file log aman
if (trim($output) === '') {
    file_put_contents(__DIR__ . '/ocr_debug.txt', "⚠️ Tidak ada output dari shell_exec()\nCommand: $command\n");
} else {
    file_put_contents(__DIR__ . '/ocr_debug.txt', $output);
}


        // Simpan hasil debug ke file
        file_put_contents(__DIR__ . '/ocr_debug.txt', $output);

        // Coba decode hasil JSON dari skrip
        $result = json_decode($output, true);

        if (is_array($result)) {
            $ocr_jumlah = $result['jumlah'] ?? null;
            $ocr_kode_found = isset($result['kode_tagihan']) && $result['kode_tagihan'] !== '' ? 1 : 0;
            $ocr_date_found = isset($result['tanggal']) && $result['tanggal'] !== '' ? 1 : 0;
            $ocr_confidence = 0.0;
            $ocr_details = json_encode([
                'extracted_text' => $result['extracted_text'] ?? '',
                'normalized_text' => $result['normalized_text'] ?? '',
                'extracted_code' => $result['kode_tagihan'] ?? '',
                'extracted_date' => $result['tanggal'] ?? ''
            ]);

            $update = $pdo->prepare("UPDATE user_bills SET ocr_jumlah = ?, ocr_kode_found = ?, ocr_date_found = ?, ocr_confidence = ?, ocr_details = ? WHERE id = ?");
            $update->execute([$ocr_jumlah, $ocr_kode_found, $ocr_date_found, $ocr_confidence, $ocr_details, $user_bill_id]);

            $_SESSION['message'] = '✅ OCR berhasil dijalankan.';
        } else {
            $_SESSION['message'] = '❌ OCR gagal memproses gambar.';
        }
    } else {
        $_SESSION['message'] = '❌ Data tagihan tidak valid atau tidak ada bukti pembayaran.';
    }

    header('Location: konfirmasi.php');
    exit;
}

// Ambil tagihan menunggu konfirmasi
$stmt = $pdo->query("SELECT ub.*, b.kode_tagihan, b.jumlah, b.deskripsi, b.tenggat_waktu, b.tanggal AS tanggal_tagihan, u.username
    FROM user_bills ub
    JOIN bills b ON ub.bill_id = b.id
    JOIN users u ON ub.user_id = u.id
    WHERE ub.status = 'menunggu_konfirmasi'
    ORDER BY ub.tanggal_upload DESC");
$bills = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Konfirmasi Pembayaran</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="container py-4">
    <h2>Daftar Pembayaran Menunggu Konfirmasi</h2>

    <?php if (isset($_SESSION['message'])): ?>
        <div class="alert alert-info"><?= $_SESSION['message']; unset($_SESSION['message']); ?></div>
    <?php endif; ?>

    <table class="table table-bordered table-hover mt-4">
        <thead class="table-light">
            <tr>
                <th>Username</th>
                <th>Kode Tagihan</th>
                <th>Jumlah</th>
                <th>Deskripsi</th>
                <th>Bukti</th>
                <th>Ekstrak OCR</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($bills as $bill): ?>
                <tr>
                    <td><?= htmlspecialchars($bill['username']) ?></td>
                    <td><?= htmlspecialchars($bill['kode_tagihan']) ?></td>
                    <td>Rp <?= number_format($bill['jumlah'], 0, ',', '.') ?></td>
                    <td><?= nl2br(htmlspecialchars($bill['deskripsi'])) ?></td>
                    <td>
                        <?php if ($bill['bukti_pembayaran']): ?>
                            <img src="../warga/uploads/bukti_pembayaran/<?= htmlspecialchars($bill['bukti_pembayaran']) ?>" width="120">
                        <?php else: ?>
                            <em>Belum upload</em>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php
                        $ocr_details = json_decode($bill['ocr_details'], true);
                        if ($ocr_details):
                        ?>
                            <strong>Jumlah:</strong> Rp <?= number_format($bill['ocr_jumlah'] ?? 0, 0, ',', '.') ?><br>
                            <strong>Kode:</strong> <?= htmlspecialchars($ocr_details['extracted_code'] ?? '-') ?><br>
                            <strong>Tanggal:</strong> <?= htmlspecialchars($ocr_details['extracted_date'] ?? '-') ?><br>
                            <strong>OCR:</strong><br>
                            <small style="font-size: 0.8em; color: #555;">
                                <?= nl2br(htmlspecialchars($ocr_details['extracted_text'] ?? '')) ?>
                            </small>
                        <?php else: ?>
                            <em>Belum diproses OCR</em>
                        <?php endif; ?>

                        <form method="POST" class="mt-2">
                            <input type="hidden" name="user_bill_id" value="<?= $bill['id'] ?>">
                            <button name="run_ocr" class="btn btn-sm btn-warning">🔍 Jalankan OCR</button>
                        </form>
                    </td>
                    <td>
                        <form method="POST" action="proses_konfirmasi.php">
                            <input type="hidden" name="user_bill_id" value="<?= $bill['id'] ?>">
                            <select name="status" class="form-select mb-2">
                                <option value="konfirmasi">Konfirmasi</option>
                                <option value="tolak">Tolak</option>
                            </select>
                            <button class="btn btn-success btn-sm" onclick="return confirm('Yakin ingin memproses ini?')">✔️ Proses</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</body>
</html>
