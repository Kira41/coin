<?php
session_start();
header('Content-Type: application/json');
try {
    // Connect to MySQL
    require_once __DIR__ . '/database.php';
    $pdo = db_connect();

    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
    $userId = (int)$_SESSION['user_id'];

    $data = [];
    $stmt = $pdo->prepare("SELECT * FROM personal_data WHERE id = ?");
    $stmt->execute([$userId]);
    $data['personalData'] = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $stmt = $pdo->prepare("SELECT operationNumber, type, amount, date, status, statusClass FROM transactions WHERE user_id = ?");
    $stmt->execute([$userId]);
    $data['transactions'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt = $pdo->prepare("SELECT type, title, message, time, alertClass FROM notifications WHERE user_id = ?");
    $stmt->execute([$userId]);
    $data['notifications'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt = $pdo->prepare("SELECT date, amount, method, status, statusClass FROM deposits WHERE user_id = ?");
    $stmt->execute([$userId]);
    $data['deposits'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt = $pdo->prepare("SELECT date, amount, method, status, statusClass FROM retraits WHERE user_id = ?");
    $stmt->execute([$userId]);
    $data['retraits'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt = $pdo->prepare("SELECT temps, paire_devises, type, statutTypeClass, montant, prix, statut, statutClass, profitPerte, profitClass FROM trading_history WHERE user_id = ?");
    $stmt->execute([$userId]);
    $data['tradingHistory'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt = $pdo->prepare("SELECT date, ip, device FROM login_history WHERE user_id = ?");
    $stmt->execute([$userId]);
    $data['loginHistory'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("SELECT id, currency, network, address, label FROM wallets WHERE user_id = ?");
    $stmt->execute([$userId]);
    $data['wallets'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $kyc = [];
    $stmt = $pdo->prepare("SELECT step_name, status, date FROM kyc_status WHERE user_id = ?");
    $stmt->execute([$userId]);
    foreach ($stmt as $row) {
        $kyc[$row['step_name']] = ['status' => $row['status'], 'date' => $row['date']];
    }
    $data['defaultKYCStatus'] = $kyc;
    $data['kycStatus'] = $kyc;

    $formStmt = $pdo->prepare("SELECT form_name, field_name, field_value FROM form_fields WHERE user_id = ?");
    $formStmt->execute([$userId]);
    $forms = [];
    foreach ($formStmt as $row) {
        $forms[$row['form_name']][$row['field_name']] = $row['field_value'];
    }
    $data['formData'] = $forms;

    echo json_encode($data);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
