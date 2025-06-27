<?php
session_start();
header('Content-Type: application/json');
try {
    // Connect to MySQL
    require __DIR__ . '/config.php';
    $dsn = "mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4";
    $pdo = new PDO($dsn, $dbUser, $dbPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    function clean_decimal($v) {
        if ($v === null || $v === '') return null;
        return preg_replace('/[^0-9.\-]/', '', $v);
    }

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
        $fields = array_keys($input['personalData']);
        $cols = 'id,' . implode(',', $fields);
        $place = '?,:' . implode(',:', $fields);
        $updateCols = [];
        foreach ($fields as $f) { $updateCols[] = "$f = VALUES($f)"; }
        $sql = "INSERT INTO personal_data ($cols) VALUES ($place) ON DUPLICATE KEY UPDATE " . implode(',', $updateCols);
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(1, $userId, PDO::PARAM_INT);
        $numericMap = ['balance'=>'decimal','totalDepots'=>'decimal','totalRetraits'=>'decimal','nbTransactions'=>'int'];
        foreach ($input['personalData'] as $k => $v) {
            if (isset($numericMap[$k])) {
                if ($numericMap[$k] === 'int') {
                    $stmt->bindValue(':' . $k, (int)preg_replace('/[^0-9-]/', '', $v), PDO::PARAM_INT);
                } else {
                    $stmt->bindValue(':' . $k, clean_decimal($v));
                }
            } else {
                $stmt->bindValue(':' . $k, $v);
            }
        }
        $stmt->execute();
    }


    function updateTable($pdo, $table, $fields, $rows, $userId) {
        if ($rows === null || !is_array($rows)) return;
        $hasId = in_array('id', $fields);
        $dataFields = array_diff($fields, ['id']);
        $cols = ($hasId ? 'id,' : '') . 'user_id,' . implode(',', $dataFields);
        $placeholders = implode(',', array_fill(0, ($hasId ? 1 : 0) + 1 + count($dataFields), '?'));
        $updates = [];
        foreach ($dataFields as $f) { $updates[] = "$f = VALUES($f)"; }
        $sql = "INSERT INTO $table ($cols) VALUES ($placeholders)";
        if ($hasId) { $sql .= " ON DUPLICATE KEY UPDATE " . implode(',', $updates); }
        $stmt = $pdo->prepare($sql);
        foreach ($rows as $row) {
            $vals = [];
            if ($hasId) $vals[] = $row['id'] ?? null;
            $vals[] = $userId;
            foreach ($dataFields as $f) { $vals[] = $row[$f] ?? null; }
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
        $checkKyc = $pdo->prepare('SELECT id FROM kyc_status WHERE user_id = ? AND step_name = ?');
        $insertKyc = $pdo->prepare('INSERT INTO kyc_status (user_id, step_name, status, date) VALUES (?,?,?,?)');
        $updateKyc = $pdo->prepare('UPDATE kyc_status SET status = ?, date = ? WHERE user_id = ? AND step_name = ?');
        foreach ($input['kycStatus'] as $step => $info) {
            $checkKyc->execute([$userId, $step]);
            if ($checkKyc->fetch()) {
                $updateKyc->execute([$info['status'] ?? '', $info['date'] ?? '', $userId, $step]);
            } else {
                $insertKyc->execute([$userId, $step, $info['status'] ?? '', $info['date'] ?? '']);
            }
        }
    }

    if (isset($input['formData'])) {
        $checkField = $pdo->prepare('SELECT id FROM form_fields WHERE user_id = ? AND form_name = ? AND field_name = ?');
        $insertField = $pdo->prepare('INSERT INTO form_fields (user_id, form_name, field_name, field_value) VALUES (?,?,?,?)');
        $updateField = $pdo->prepare('UPDATE form_fields SET field_value = ? WHERE user_id = ? AND form_name = ? AND field_name = ?');
        foreach ($input['formData'] as $form => $fields) {
            foreach ($fields as $name => $val) {
                $checkField->execute([$userId, $form, $name]);
                if ($checkField->fetch()) {
                    $updateField->execute([$val, $userId, $form, $name]);
                } else {
                    $insertField->execute([$userId, $form, $name, $val]);
                }
            }
        }
    }

    $pdo->commit();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    if ($pdo && $pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
