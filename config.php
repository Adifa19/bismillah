<?php
session_start();
$host = 'localhost';
$dbname = 'tetangga_id';
$username = 'root';
$password = 'Dipa190503!@#';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Koneksi database gagal: " . $e->getMessage());
}

// ========== MIDTRANS CONFIG ==========
define('MIDTRANS_SERVER_KEY', ''MIDTRANS_SERVER_KEY'');
define('MIDTRANS_CLIENT_KEY', 'MIDTRANS_CLIENT_KEY');
define('MIDTRANS_MERCHANT_ID', 'MIDTRANS_MERCHANT_ID');
define('MIDTRANS_IS_PRODUCTION', false); // Set to true untuk production

define('MIDTRANS_SNAP_URL', MIDTRANS_IS_PRODUCTION ? 
    'https://app.midtrans.com/snap/snap.js' : 
    'https://app.sandbox.midtrans.com/snap/snap.js');

define('MIDTRANS_API_URL', MIDTRANS_IS_PRODUCTION ? 
    'https://api.midtrans.com/v2' : 
    'https://api.sandbox.midtrans.com/v2');

// ========== MIDTRANS FUNCTIONS ==========

// Generate Snap Token
function getMidtransSnapToken($order_id, $gross_amount, $customer_details) {
    $params = array(
        'transaction_details' => array(
            'order_id' => $order_id,
            'gross_amount' => $gross_amount,
        ),
        'customer_details' => $customer_details,
        'enabled_payments' => array('credit_card', 'bank_transfer', 'echannel', 'gopay', 'shopeepay', 'other_qris'),
        'credit_card' => array('secure' => true),
        'callbacks' => array(
            'finish' => 'http://localhost/tetangga/warga/tagihan.php?status=finish'
            // Ganti ke domain asli jika sudah live
        )
    );

    $auth = base64_encode(MIDTRANS_SERVER_KEY . ':');
    $url = MIDTRANS_IS_PRODUCTION ? 
        'https://app.midtrans.com/snap/v1/transactions' : 
        'https://app.sandbox.midtrans.com/snap/v1/transactions';

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/json',
        'Accept: application/json',
        'Authorization: Basic ' . $auth
    ));

    $response = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        throw new Exception('Curl error: ' . $error);
    }

    $result = json_decode($response, true);
    if (!isset($result['token'])) {
        throw new Exception('Failed to get snap token: ' . $response);
    }

    return $result['token'];
}

// Ambil status transaksi
function getMidtransTransactionStatus($orderId) {
    $auth = base64_encode(MIDTRANS_SERVER_KEY . ':');
    $url = MIDTRANS_API_URL . '/' . $orderId . '/status';

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/json',
        'Accept: application/json',
        'Authorization: Basic ' . $auth
    ));

    $response = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        throw new Exception('Curl error: ' . $error);
    }

    return json_decode($response, true);
}

// Signature Verification
function verifyMidtransSignature($orderId, $statusCode, $grossAmount, $serverKey) {
    $input = $orderId . $statusCode . $grossAmount . $serverKey;
    return hash('sha512', $input);
}

// ========== AUTH & HELPER ==========
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

function requireAdmin() {
    if (!isAdmin()) {
        header('Location: admin/pendataan.php');
        exit;
    }
}

function sanitize($data) {
    return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
}
?>
