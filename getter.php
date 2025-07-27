<?php
header('Content-Type: application/json');
set_error_handler(function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

try {
    $dsn = 'mysql:host=localhost;dbname=coin_db;charset=utf8mb4';
    $pdo = new PDO($dsn, 'root', '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => false
    ]);

    $userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 1;

function fetchAll($pdo, $sql, $params = []) {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$personal = fetchAll($pdo, 'SELECT * FROM personal_data WHERE user_id = ?', [$userId]);
$personal = $personal ? $personal[0] : [];
$bankWithdraw = fetchAll($pdo, 'SELECT * FROM bank_withdrawl_info WHERE user_id = ? LIMIT 1', [$userId]);
$bankWithdraw = $bankWithdraw ? $bankWithdraw[0] : [];

$kycRows = fetchAll($pdo, 'SELECT status,created_at FROM kyc WHERE user_id = ? ORDER BY created_at DESC LIMIT 20', [$userId]);
$kycStatus = '0';
$kycDate = null;
foreach ($kycRows as $r) {
    if ($kycDate === null) { $kycDate = $r['created_at']; }
    if ($r['status'] === 'approved') { $kycStatus = '1'; break; }
    if ($r['status'] === 'pending' && $kycStatus !== '1') { $kycStatus = '2'; }
}

$verify = fetchAll($pdo, 'SELECT * FROM verification_status WHERE user_id = ? LIMIT 1', [$userId]);
$verify = $verify ? $verify[0] : null;

$data = [
    'personalData' => $personal,
    'wallets' => fetchAll($pdo, 'SELECT * FROM wallets WHERE user_id = ?', [$userId]),
    'transactions' => fetchAll($pdo, 'SELECT operationNumber,type,amount,date,status,statusClass FROM transactions WHERE user_id = ? ORDER BY STR_TO_DATE(date, "%Y/%m/%d") DESC, id DESC LIMIT 10', [$userId]),
    'notifications' => fetchAll($pdo, 'SELECT DISTINCT type,title,message,time,alertClass FROM notifications WHERE user_id = ? ORDER BY id DESC LIMIT 100', [$userId]),
    'deposits' => fetchAll($pdo, 'SELECT operationNumber,date,amount,method,status,statusClass FROM deposits WHERE user_id = ? ORDER BY STR_TO_DATE(date, "%Y/%m/%d") DESC, id DESC LIMIT 10', [$userId]),
    'retraits' => fetchAll($pdo, 'SELECT operationNumber,date,amount,method,status,statusClass FROM retraits WHERE user_id = ? ORDER BY STR_TO_DATE(date, "%Y/%m/%d") DESC, id DESC LIMIT 10', [$userId]),
    'tradingHistory' => array_map(function($r){
        if (!empty($r['details'])) {
            $d = json_decode($r['details'], true);
            if (is_array($d)) { $r = array_merge($r, $d); }
        }
        unset($r['details']);
        return $r;
    }, fetchAll($pdo, 'SELECT operationNumber,temps,paireDevises,type,statutTypeClass,montant,prix,statut,statutClass,profitPerte,profitClass,details FROM tradingHistory WHERE user_id = ? ORDER BY id DESC LIMIT 5', [$userId])),
    'loginHistory' => fetchAll($pdo, 'SELECT date,ip,device FROM loginHistory WHERE user_id = ? ORDER BY STR_TO_DATE(date, "%Y/%m/%d") DESC, id DESC LIMIT 100', [$userId]),
    'bankWithdrawInfo' => $bankWithdraw,
    'cryptoDepositAddresses' => fetchAll($pdo, 'SELECT id,crypto_name,wallet_info FROM deposit_crypto_address WHERE user_id = ?', [$userId]),
    'kycDocs' => $kycRows,
    // placeholders for front-end
    'formData' => new stdClass(),
    'defaultKYCStatus' => [
        'enregistrementducomptestat' => ['status' => $verify['enregistrementducompte'] ?? '2', 'date' => date('Y-m-d')],
        'confirmationdeladresseemailstat' => ['status' => $verify['confirmationdeladresseemail'] ?? '2', 'date' => date('Y-m-d')],
        'telechargerlesdocumentsdidentitestat' => ['status' => $verify['telechargerlesdocumentsdidentite'] ?? ($kycRows ? $kycStatus : '2'), 'date' => $kycDate],
        'verificationdeladressestat' => ['status' => $verify['verificationdeladresse'] ?? '2', 'date' => null],
        'revisionfinalestat' => ['status' => $verify['revisionfinale'] ?? '2', 'date' => null],
    ],
];

echo json_encode($data);
} catch (Throwable $e) {
    error_log(__FILE__ . ' - ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
