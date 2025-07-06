<?php
require_once '../config.php';
require_once '../midtrans-php-master/Midtrans.php';
requireLogin();
$user_id = $_SESSION['user_id'];

// Ambil tagihan
$stmt = $pdo->prepare("
    SELECT b.*, 
           ub.status, 
           ub.bukti_pembayaran, 
           ub.tanggal_bayar_online, 
           ub.id as user_bill_id
    FROM bills b
    LEFT JOIN user_bills ub ON b.id = ub.bill_id AND ub.user_id = ?
    WHERE ub.status IN ('menunggu_pembayaran', 'tolak') OR ub.status IS NULL
    ORDER BY b.tanggal DESC
");
$stmt->execute([$user_id]);
$bills = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Tagihan Warga</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { padding-top: 2rem; }
  </style>
</head>
<body>
<div class="container">
  <h2 class="mb-4">Daftar Tagihan Anda</h2>
  <table class="table table-bordered">
    <thead class="table-light">
      <tr>
        <th>Judul</th>
        <th>Jumlah</th>
        <th>Status</th>
        <th>Aksi</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($bills as $bill): ?>
        <tr>
          <td><?= htmlspecialchars($bill['judul']) ?></td>
          <td>Rp<?= number_format($bill['jumlah'], 0, ',', '.') ?></td>
          <td><?= $bill['status'] ?? 'Belum Dibayar' ?></td>
          <td>
            <button class="btn btn-success btn-sm" onclick="bayarTagihan(<?= $bill['id'] ?>)">Bayar</button>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<!-- Modal Upload Bukti -->
<div class="modal fade" id="uploadBuktiModal" tabindex="-1" aria-labelledby="uploadBuktiLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form id="uploadBuktiForm" enctype="multipart/form-data">
        <div class="modal-header">
          <h5 class="modal-title">Upload Bukti Pembayaran</h5>
        </div>
        <div class="modal-body">
          <p class="text-danger">Pembayaran berhasil. Anda wajib mengunggah bukti pembayaran!</p>
          <input type="file" name="bukti_pembayaran" class="form-control" accept=".jpg,.jpeg,.png" required>
          <input type="hidden" name="action" value="upload_bukti">
          <input type="hidden" name="user_bill_id" id="modal_user_bill_id">
          <div id="uploadAlert" class="mt-2 text-danger d-none"></div>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-primary">Upload Bukti</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://app.sandbox.midtrans.com/snap/snap.js" data-client-key="ISI_DENGAN_CLIENT_KEY_ANDA"></script>
<script>
function bayarTagihan(billId) {
  fetch('', {
    method: 'POST',
    body: new URLSearchParams({ action: 'create_payment', bill_id: billId })
  })
  .then(res => res.json())
  .then(data => {
    if (data.status === 'success') {
      snap.pay(data.snap_token, {
        onSuccess: function() {
          cekStatusPembayaran(data.order_id, data.user_bill_id);
        }
      });
    } else {
      alert(data.message);
    }
  });
}

function cekStatusPembayaran(order_id, user_bill_id) {
  fetch('', {
    method: 'POST',
    body: new URLSearchParams({
      action: 'check_payment_status',
      order_id: order_id,
      user_bill_id: user_bill_id
    })
  })
  .then(res => res.json())
  .then(data => {
    if (data.status === 'success' && data.payment_status === 'paid') {
      document.getElementById('modal_user_bill_id').value = user_bill_id;
      const modal = new bootstrap.Modal(document.getElementById('uploadBuktiModal'));
      modal.show();
    } else {
      alert(data.message || 'Pembayaran belum berhasil');
    }
  });
}

document.getElementById('uploadBuktiForm').addEventListener('submit', function(e) {
  e.preventDefault();

  const form = e.target;
  const formData = new FormData(form);
  const alertBox = document.getElementById('uploadAlert');
  alertBox.classList.add('d-none');
  alertBox.innerText = '';

  fetch('', {
    method: 'POST',
    body: formData
  })
  .then(res => res.json())
  .then(data => {
    if (data.status === 'success') {
      bootstrap.Modal.getInstance(document.getElementById('uploadBuktiModal')).hide();
      alert('Bukti berhasil diupload!');
      location.reload();
    } else {
      alertBox.classList.remove('d-none');
      alertBox.innerText = data.message;
    }
  });
});
</script>
</body>
</html>
