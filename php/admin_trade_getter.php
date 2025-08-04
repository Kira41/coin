<?php
header('Content-Type: application/json');
set_error_handler(function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

try {
    require_once __DIR__ . '/../config/db_connection.php';
    $pdo = db();

    session_start();
    $adminId = null;
    if (isset($_SESSION['admin_id'])) {
        $adminId = (int)$_SESSION['admin_id'];
    } elseif (!empty($_SERVER['HTTP_AUTHORIZATION']) &&
        preg_match('/Bearer\s+(\d+)/i', $_SERVER['HTTP_AUTHORIZATION'], $m)) {
        $adminId = (int)$m[1];
    }

    if (!$adminId) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
        exit;
    }

    $op = isset($_GET['op']) ? trim($_GET['op']) : '';
    if ($op === '') {
        throw new Exception('Missing op');
    }

    $stmt = $pdo->prepare('SELECT profitPerte, prix FROM tradingHistory WHERE operationNumber = ?');
    $stmt->execute([$op]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode(['trade' => $row]);
} catch (Throwable $e) {
    error_log(__FILE__ . ' - ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
