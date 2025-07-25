<?php
header('Content-Type: application/json');
set_error_handler(function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

try {
    $dsn = 'mysql:host=localhost;dbname=coin_db;charset=utf8mb4';
    $pdo = new PDO($dsn, 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$adminId = null;

session_start();
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

$stmt = $pdo->prepare('SELECT email, is_admin FROM admins_agents WHERE id = ?');
$stmt->execute([$adminId]);
$admin = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$admin) {
    http_response_code(404);
    echo json_encode(['status' => 'error', 'message' => 'Admin not found']);
    exit;
}

$result = [
    'is_admin' => (int)$admin['is_admin'],
    'admin_id' => $adminId,
    'email' => $admin['email'],
];

if ((int)$admin['is_admin'] === 1) {
    $stmt = $pdo->prepare('SELECT id,email,is_admin,created_by FROM admins_agents WHERE created_by = ?');
    $stmt->execute([$adminId]);
    $result['agents'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$period = isset($_GET['period']) ? strtolower($_GET['period']) : 'all';
$startDate = null;
switch ($period) {
    case 'daily':
        $startDate = date('Y-m-d');
        break;
    case 'weekly':
        $startDate = date('Y-m-d', strtotime('-6 days'));
        break;
    case 'monthly':
        $startDate = date('Y-m-d', strtotime('-29 days'));
        break;
}

$userSql = 'SELECT * FROM personal_data WHERE linked_to_id = ?';
$userParams = [$adminId];
if ($startDate) {
    $userSql .= ' AND STR_TO_DATE(created_at, "%Y-%m-%d") >= STR_TO_DATE(?, "%Y-%m-%d")';
    $userParams[] = $startDate;
}
$stmt = $pdo->prepare($userSql);
$stmt->execute($userParams);
$result['users'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare('SELECT k.file_id,k.user_id,p.fullName,p.emailaddress,k.file_name,k.created_at,k.status FROM kyc k JOIN personal_data p ON k.user_id=p.user_id WHERE p.linked_to_id = ? AND k.status = "pending"');
$stmt->execute([$adminId]);
$result['kyc'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Compute statistics for the admin's users
$userIds = array_column($result['users'], 'user_id');
$totalUsers = count($userIds);
$sumDeposits = 0;
$depositCount = 0;
$successUsers = [];
if ($userIds) {
    $placeholders = implode(',', array_fill(0, count($userIds), '?'));
    $sql = "SELECT user_id, amount FROM deposits WHERE status='complet' AND user_id IN ($placeholders)";
    $params = $userIds;
    if ($startDate) {
        $sql .= " AND STR_TO_DATE(date, '%Y/%m/%d') >= STR_TO_DATE(?, '%Y-%m-%d')";
        $params[] = $startDate;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $sumDeposits += (float)$row['amount'];
        $depositCount++;
        $successUsers[$row['user_id']] = true;
    }
}
$successRate = $totalUsers ? round(count($successUsers) / $totalUsers * 100, 2) : 0;
$result['stats'] = [
    'total_users' => $totalUsers,
    'total_deposits' => $sumDeposits,
    'deposit_count' => $depositCount,
    'success_rate' => $successRate,
];

// Fetch recent notifications for the admin's users
$notifications = [];
if ($userIds) {
    $place = implode(',', array_fill(0, count($userIds), '?'));
    $sql = "SELECT n.type,n.title,n.message,n.time,n.alertClass,p.fullName
            FROM notifications n
            JOIN personal_data p ON p.user_id = n.user_id
            WHERE n.user_id IN ($place)
            ORDER BY n.id DESC
            LIMIT 10";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($userIds);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
$result['notifications'] = $notifications;

echo json_encode($result);
} catch (Throwable $e) {
    error_log(__FILE__ . ' - ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
