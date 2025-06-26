<?php
header('Content-Type: application/json');
try {
    $pdo = new PDO('sqlite:database.db');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $data = [];
    $data['personalData'] = $pdo->query("SELECT * FROM personal_data LIMIT 1")->fetch(PDO::FETCH_ASSOC) ?: [];
    $data['transactions'] = $pdo->query("SELECT operationNumber, type, amount, date, status, statusClass FROM transactions")->fetchAll(PDO::FETCH_ASSOC);
    $data['notifications'] = $pdo->query("SELECT type, title, message, time, alertClass FROM notifications")->fetchAll(PDO::FETCH_ASSOC);
    $data['deposits'] = $pdo->query("SELECT date, amount, method, status, statusClass FROM deposits")->fetchAll(PDO::FETCH_ASSOC);
    $data['retraits'] = $pdo->query("SELECT date, amount, method, status, statusClass FROM retraits")->fetchAll(PDO::FETCH_ASSOC);
    $data['tradingHistory'] = $pdo->query("SELECT temps, paire_devises, type, statutTypeClass, montant, prix, statut, statutClass, profitPerte, profitClass FROM trading_history")->fetchAll(PDO::FETCH_ASSOC);
    $data['loginHistory'] = $pdo->query("SELECT date, ip, device FROM login_history")->fetchAll(PDO::FETCH_ASSOC);

    $kyc = [];
    foreach ($pdo->query("SELECT step_name, status, date FROM kyc_status") as $row) {
        $kyc[$row['step_name']] = ['status' => $row['status'], 'date' => $row['date']];
    }
    $data['defaultKYCStatus'] = $kyc;
    $data['kycStatus'] = $kyc;

    $formStmt = $pdo->query("SELECT form_name, field_name, field_value FROM form_fields");
    $forms = [];
    foreach ($formStmt as $row) {
        $forms[$row['form_name']][$row['field_name']] = $row['field_value'];
    }
    $data['formData'] = $forms;

    echo json_encode($data);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
