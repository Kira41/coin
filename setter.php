<?php
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

    $pdo->beginTransaction();

    if (!empty($input['personalData'])) {
        $pdo->exec('DELETE FROM personal_data');
        $fields = array_keys($input['personalData']);
        $cols = implode(',', $fields);
        $place = ':' . implode(',:', $fields);
        $stmt = $pdo->prepare("INSERT INTO personal_data ($cols) VALUES ($place)");
        foreach ($input['personalData'] as $k => $v) {
            $stmt->bindValue(':' . $k, $v);
        }
        $stmt->execute();
    }

    function updateTable($pdo, $table, $fields, $rows) {
        if ($rows === null) return;    
        $pdo->exec("DELETE FROM $table");
        if (!is_array($rows)) return;
        $cols = implode(',', $fields);
        $placeholders = implode(',', array_fill(0, count($fields), '?'));
        $stmt = $pdo->prepare("INSERT INTO $table ($cols) VALUES ($placeholders)");
        foreach ($rows as $row) {
            $vals = [];
            foreach ($fields as $f) { $vals[] = $row[$f] ?? null; }
            $stmt->execute($vals);
        }
    }

    updateTable($pdo, 'transactions', ['operationNumber','type','amount','date','status','statusClass'], $input['transactions'] ?? null);
    updateTable($pdo, 'notifications', ['type','title','message','time','alertClass'], $input['notifications'] ?? null);
    updateTable($pdo, 'deposits', ['date','amount','method','status','statusClass'], $input['deposits'] ?? null);
    updateTable($pdo, 'retraits', ['date','amount','method','status','statusClass'], $input['retraits'] ?? null);
    updateTable($pdo, 'trading_history', ['temps','paire_devises','type','statutTypeClass','montant','prix','statut','statutClass','profitPerte','profitClass'], $input['tradingHistory'] ?? null);
    updateTable($pdo, 'login_history', ['date','ip','device'], $input['loginHistory'] ?? null);

    if (isset($input['kycStatus'])) {
        $pdo->exec('DELETE FROM kyc_status');
        $stmt = $pdo->prepare('INSERT INTO kyc_status (step_name,status,date) VALUES (?,?,?)');
        foreach ($input['kycStatus'] as $step => $info) {
            $stmt->execute([$step, $info['status'] ?? '', $info['date'] ?? '']);
        }
    }

    if (isset($input['formData'])) {
        $pdo->exec('DELETE FROM form_fields');
        $stmt = $pdo->prepare('INSERT INTO form_fields (form_name, field_name, field_value) VALUES (?,?,?)');
        foreach ($input['formData'] as $form => $fields) {
            foreach ($fields as $name => $val) {
                $stmt->execute([$form, $name, $val]);
            }
        }
    }

    $pdo->commit();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    if ($pdo && $pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
