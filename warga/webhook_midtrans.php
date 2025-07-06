<?php
// File: webhook_midtrans.php
// Letakkan di root folder atau folder khusus webhooks

require_once '../config.php';

// Fungsi untuk verifikasi signature Midtrans
function verifySignature($order_id, $status_code, $gross_amount, $server_key) {
    $hash = hash('sha512', $order_id . $status_code . $gross_amount . $server_key);
    return $hash;
}

// Fungsi untuk log webhook
function logWebhook($data, $message = '') {
    $log_file = 'logs/midtrans_webhook.log';
    $log_dir = dirname($log_file);
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[$timestamp] $message\n" . json_encode($data, JSON_PRETTY_PRINT) . "\n\n";
    file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
}

// Set header untuk response
header('Content-Type: application/json');

try {
    // Ambil raw input dari POST
    $json_result = file_get_contents('php://input');
    $result = json_decode($json_result, true);
    
    // Log webhook yang diterima
    logWebhook($result, 'Webhook received');
    
    // Validasi data yang diperlukan
    if (!isset($result['order_id']) || !isset($result['transaction_status']) || !isset($result['signature_key'])) {
        throw new Exception('Invalid webhook data');
    }
    
    $order_id = $result['order_id'];
    $transaction_status = $result['transaction_status'];
    $fraud_status = isset($result['fraud_status']) ? $result['fraud_status'] : '';
    $signature_key = $result['signature_key'];
    $status_code = $result['status_code'];
    $gross_amount = $result['gross_amount'];
    
    // Verifikasi signature
    $expected_signature = verifySignature($order_id, $status_code, $gross_amount, MIDTRANS_SERVER_KEY);
    
    if ($signature_key !== $expected_signature) {
        throw new Exception('Invalid signature');
    }
    
    // Parse order_id untuk mendapatkan user_bill_id
    // Format order_id: BILL_KODEBILL_USERID_TIMESTAMP
    $order_parts = explode('_', $order_id);
    if (count($order_parts) < 4) {
        throw new Exception('Invalid order_id format');
    }
    
    $user_id = $order_parts[2];
    $timestamp = $order_parts[3];
    
    // Cari tagihan berdasarkan payment_token atau order_id
    $stmt = $pdo->prepare("
        SELECT ub.*, b.kode_tagihan 
        FROM user_bills ub 
        JOIN bills b ON ub.bill_id = b.id 
        WHERE ub.user_id = ? AND ub.payment_token IS NOT NULL
        ORDER BY ub.id DESC LIMIT 1
    ");
    $stmt->execute([$user_id]);
    $bill = $stmt->fetch();
    
    if (!$bill) {
        throw new Exception('Bill not found for user_id: ' . $user_id);
    }
    
    // Tentukan status baru berdasarkan transaction_status
    $new_status = 'menunggu_pembayaran';
    $payment_date = null;
    
    if ($transaction_status == 'capture') {
        if ($fraud_status == 'accept') {
            $new_status = 'dibayar_online';
            $payment_date = date('Y-m-d H:i:s');
        }
    } elseif ($transaction_status == 'settlement') {
        $new_status = 'dibayar_online';
        $payment_date = date('Y-m-d H:i:s');
    } elseif ($transaction_status == 'pending') {
        $new_status = 'menunggu_pembayaran';
    } elseif ($transaction_status == 'deny' || $transaction_status == 'cancel' || $transaction_status == 'expire') {
        $new_status = 'menunggu_pembayaran';
    }
    
    // Update status tagihan
    if ($payment_date) {
        $stmt = $pdo->prepare("
            UPDATE user_bills 
            SET status = ?, tanggal_bayar_online = ?, midtrans_transaction_id = ?, midtrans_order_id = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $new_status, 
            $payment_date, 
            $result['transaction_id'], 
            $order_id, 
            $bill['id']
        ]);
    } else {
        $stmt = $pdo->prepare("
            UPDATE user_bills 
            SET status = ?, midtrans_transaction_id = ?, midtrans_order_id = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $new_status, 
            $result['transaction_id'], 
            $order_id, 
            $bill['id']
        ]);
    }
    
    // Log hasil update
    logWebhook([
        'order_id' => $order_id,
        'transaction_status' => $transaction_status,
        'new_status' => $new_status,
        'bill_id' => $bill['id'],
        'user_id' => $user_id
    ], 'Status updated successfully');
    
    // Response sukses
    echo json_encode([
        'status' => 'success',
        'message' => 'Webhook processed successfully'
    ]);
    
} catch (Exception $e) {
    // Log error
    logWebhook([
        'error' => $e->getMessage(),
        'webhook_data' => $result ?? null
    ], 'Webhook error');
    
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>