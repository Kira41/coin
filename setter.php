<?php
session_start();
header('Content-Type: application/json');
try {
    // Connect to MySQL instead of the previous SQLite database
    $dbHost = 'localhost';
    $dbName = 'coin_db';
    $dbUser = 'root';
    $dbPass = '';
    $dsn = "mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4";
    $pdo = new PDO($dsn, $dbUser, $dbPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        throw new Exception('Invalid JSON');
    }

    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }
    $userId = (int)$_SESSION['user_id'];

    $pdo->beginTransaction();

    if (!empty($input['personalData'])) {
        $pdo->prepare('DELETE FROM personal_data WHERE id = ?')->execute([$userId]);
        $fields = array_keys($input['personalData']);
        $cols = 'id,' . implode(',', $fields);
        $place = '?,:' . implode(',:', $fields);
        $stmt = $pdo->prepare("INSERT INTO personal_data ($cols) VALUES ($place)");
        $stmt->bindValue(1, $userId, PDO::PARAM_INT);
        foreach ($input['personalData'] as $k => $v) {
            $stmt->bindValue(':' . $k, $v);
        }
        $stmt->execute();
    }

    function updateTable($pdo, $table, $fields, $rows, $userId) {
        if ($rows === null) return;
        $pdo->prepare("DELETE FROM $table WHERE user_id = ?")->execute([$userId]);
        if (!is_array($rows)) return;
        $cols = 'user_id,' . implode(',', $fields);
        $placeholders = implode(',', array_fill(0, count($fields) + 1, '?'));
        $stmt = $pdo->prepare("INSERT INTO $table ($cols) VALUES ($placeholders)");
        foreach ($rows as $row) {
            $vals = [$userId];
            foreach ($fields as $f) { $vals[] = $row[$f] ?? null; }
            $stmt->execute($vals);
        }
    }

    updateTable($pdo, 'transactions', ['operationNumber','type','amount','date','status','statusClass'], $input['transactions'] ?? null, $userId);
    updateTable($pdo, 'notifications', ['type','title','message','time','alertClass'], $input['notifications'] ?? null, $userId);
    updateTable($pdo, 'deposits', ['date','amount','method','status','statusClass'], $input['deposits'] ?? null, $userId);
    updateTable($pdo, 'retraits', ['date','amount','method','status','statusClass'], $input['retraits'] ?? null, $userId);
    updateTable($pdo, 'trading_history', ['temps','paire_devises','type','statutTypeClass','montant','prix','statut','statutClass','profitPerte','profitClass'], $input['tradingHistory'] ?? null, $userId);
    updateTable($pdo, 'login_history', ['date','ip','device'], $input['loginHistory'] ?? null, $userId);
    updateTable($pdo, 'wallets', ['id','currency','network','address','label'], $input['wallets'] ?? null, $userId);

    if (isset($input['kycStatus'])) {
        $pdo->prepare('DELETE FROM kyc_status WHERE user_id = ?')->execute([$userId]);
        $stmt = $pdo->prepare('INSERT INTO kyc_status (user_id, step_name, status, date) VALUES (?,?,?,?)');
        foreach ($input['kycStatus'] as $step => $info) {
            $stmt->execute([$userId, $step, $info['status'] ?? '', $info['date'] ?? '']);
        }
    }

    if (isset($input['formData'])) {
        $pdo->prepare('DELETE FROM form_fields WHERE user_id = ?')->execute([$userId]);
        $stmt = $pdo->prepare('INSERT INTO form_fields (user_id, form_name, field_name, field_value) VALUES (?,?,?,?)');
        foreach ($input['formData'] as $form => $fields) {
            foreach ($fields as $name => $val) {
                $stmt->execute([$userId, $form, $name, $val]);
            }
        }
    }

    $pdo->commit();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    if ($pdo && $pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
