<?php
$dsn = 'mysql:host=localhost;dbname=coin_db;charset=utf8mb4';
$pdo = new PDO($dsn, 'root', '');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$input = file_get_contents('php://input');
$data = json_decode($input, true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid JSON']);
    exit;
}

$allowedUserCols = [
    'user_id','balance','totalDepots','totalRetraits','nbTransactions',
    'fullName','compteverifie','compteverifie01','niveauavance','passwordHash',
    'passwordStrength','passwordStrengthBar','emailNotifications','smsNotifications',
    'loginAlerts','transactionAlerts','twoFactorAuth','emailaddress','address',
    'phone','dob','nationality','created_at','btcAddress','ethAddress','usdtAddress',
    'userBankName','userAccountName','userAccountNumber','userIban','userSwiftCode',
    'linked_to_id'
];

$action = $data['action'] ?? '';

try {
    if ($action === 'create_admin') {
        $email = $data['email'] ?? '';
        $password = $data['password'] ?? '';
        $isAdmin = isset($data['is_admin']) ? (int)$data['is_admin'] : 0;
        $createdBy = $data['created_by'] ?? null;
        if (!$email || !$password) {
            throw new Exception('Missing parameters');
        }
        // Passwords are now pre-hashed on the client using MD5
        $stmt = $pdo->prepare('INSERT INTO admins_agents (email, password, is_admin, created_by) VALUES (?,?,?,?)');
        $stmt->execute([$email, $password, $isAdmin, $createdBy]);
        echo json_encode(['status' => 'ok', 'id' => $pdo->lastInsertId()]);
    } elseif ($action === 'create_user') {
        $user = $data['user'] ?? [];
        if (!$user || !isset($user['linked_to_id']) || !isset($user['password'])) {
            throw new Exception('Missing parameters');
        }
        $password = $user['password'];
        unset($user['password']);
        // Client provides an MD5 hash for the password
        $user['passwordHash'] = $password;
        if (!isset($user['created_at']) || $user['created_at'] === '') {
            $user['created_at'] = date('Y-m-d');
        }

        $user = array_intersect_key($user, array_flip($allowedUserCols));
        $cols = array_keys($user);
        $place = implode(',', array_fill(0, count($cols), '?'));
        $sql = 'INSERT INTO personal_data (' . implode(',', $cols) . ') VALUES (' . $place . ')';
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array_values($user));
        echo json_encode(['status' => 'ok']);
    } elseif ($action === 'update_user') {
        $user = $data['user'] ?? [];
        if (!$user || !isset($user['user_id'])) {
            throw new Exception('Missing parameters');
        }
        $userId = (int)$user['user_id'];
        unset($user['user_id']);
        $user = array_intersect_key($user, array_flip($allowedUserCols));
        $cols = array_keys($user);
        if (!$cols) {
            throw new Exception('No fields to update');
        }
        $set = implode(',', array_map(fn($c) => "$c = ?", $cols));
        $sql = "UPDATE personal_data SET $set WHERE user_id = ?";
        $stmt = $pdo->prepare($sql);
        $values = array_values($user);
        $values[] = $userId;
        $stmt->execute($values);
        echo json_encode(['status' => 'ok']);
    } elseif ($action === 'update_admin') {
        $id = isset($data['id']) ? (int)$data['id'] : 0;
        if (!$id) {
            throw new Exception('Missing id');
        }
        $fields = [];
        $values = [];
        if (isset($data['email'])) {
            $fields[] = 'email = ?';
            $values[] = $data['email'];
        }
        if (isset($data['password']) && $data['password'] !== '') {
            $oldPwd = $data['old_password'] ?? '';
            if ($oldPwd === '') {
                throw new Exception('Missing old_password');
            }
            $stmt = $pdo->prepare('SELECT password FROM admins_agents WHERE id = ?');
            $stmt->execute([$id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row || !hash_equals($row['password'], $oldPwd)) {
                throw new Exception('Incorrect old password');
            }
            $fields[] = 'password = ?';
            // Password is expected to be pre-hashed with MD5
            $values[] = $data['password'];
        }
        if (isset($data['is_admin'])) {
            $fields[] = 'is_admin = ?';
            $values[] = (int)$data['is_admin'];
        }
        if (!$fields) {
            throw new Exception('No fields to update');
        }
        $values[] = $id;
        $sql = 'UPDATE admins_agents SET ' . implode(',', $fields) . ' WHERE id = ?';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($values);
        echo json_encode(['status' => 'ok']);
    } elseif ($action === 'delete_admin') {
        $id = isset($data['id']) ? (int)$data['id'] : 0;
        if (!$id) {
            throw new Exception('Missing id');
        }
        $stmt = $pdo->prepare('DELETE FROM admins_agents WHERE id = ?');
        $stmt->execute([$id]);
        echo json_encode(['status' => 'ok']);
    } elseif ($action === 'delete_user') {
        $userId = isset($data['user_id']) ? (int)$data['user_id'] : 0;
        if (!$userId) {
            throw new Exception('Missing user_id');
        }

        $pdo->beginTransaction();
        try {
            $tables = [
                'wallets', 'transactions', 'notifications', 'deposits',
                'retraits', 'tradingHistory', 'loginHistory',
                'bank_withdrawl_info', 'personal_data'
            ];
            foreach ($tables as $table) {
                $pdo->prepare("DELETE FROM $table WHERE user_id = ?")->execute([$userId]);
            }
            $pdo->commit();
            echo json_encode(['status' => 'ok']);
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    } else {
        throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
