<?php
header('Content-Type: application/json');
set_error_handler(function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

try {
    $dsn = 'mysql:host=localhost;dbname=coin_db;charset=utf8mb4';
    $pdo = new PDO($dsn, 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    function formatTimeAgoFromDate($dateStr) {
        $ts = strtotime($dateStr);
        if (!$ts) return '';
        $diff = time() - $ts;
        if ($diff < 60) return "À l'instant";
        $mins = floor($diff / 60);
        if ($mins < 60) return 'Il y a ' . $mins . ' minute' . ($mins > 1 ? 's' : '');
        $hours = floor($diff / 3600);
        if ($hours < 24) return 'Il y a ' . $hours . ' heure' . ($hours > 1 ? 's' : '');
        $days = floor($diff / 86400);
        return 'Il y a ' . $days . ' jour' . ($days > 1 ? 's' : '');
    }

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
        'note','linked_to_id'
    ];

    $action = $data['action'] ?? '';

    if ($action === 'create_admin') {
        $email = $data['email'] ?? '';
        $password = $data['password'] ?? '';
        $isAdmin = isset($data['is_admin']) ? (int)$data['is_admin'] : 0;
        $createdBy = $data['created_by'] ?? null;
        if (!$email || !$password) {
            throw new Exception('Missing parameters');
        }
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
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT user_id FROM personal_data WHERE linked_to_id = ?');
            $stmt->execute([$id]);
            $userIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
            if ($userIds) {
                $tables = [
                    'wallets', 'transactions', 'notifications', 'deposits',
                    'retraits', 'tradingHistory', 'loginHistory',
                    'bank_withdrawl_info', 'personal_data'
                ];
                foreach ($userIds as $uid) {
                    foreach ($tables as $table) {
                        $pdo->prepare("DELETE FROM $table WHERE user_id = ?")->execute([$uid]);
                    }
                }
            }
            $stmt = $pdo->prepare('DELETE FROM admins_agents WHERE id = ?');
            $stmt->execute([$id]);
            $pdo->commit();
            echo json_encode(['status' => 'ok']);
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
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
    } elseif ($action === 'update_transaction') {
        $op = isset($data['id']) ? trim($data['id']) : '';
        if ($op === '') {
            throw new Exception('Missing id');
        }
        $prefix = strtoupper(substr($op, 0, 1));
        $historyTable = null;
        if ($prefix === 'D') {
            $historyTable = 'deposits';
        } elseif ($prefix === 'R') {
            $historyTable = 'retraits';
        }
        $pdo->beginTransaction();
        try {
            $stmt = ($historyTable
                ? $pdo->prepare("SELECT user_id, amount, status, date FROM $historyTable WHERE operationNumber = ? FOR UPDATE")
                : $pdo->prepare("SELECT user_id, amount, status, date FROM transactions WHERE operationNumber = ? FOR UPDATE"));
            $stmt->execute([$op]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                throw new Exception('Transaction not found');
            }
            $userId = (int)$row['user_id'];
            $amount = (float)$row['amount'];
            $oldStatus = $row['status'];
            if (!empty($data['delete'])) {
                if ($historyTable) {
                    $pdo->prepare("DELETE FROM $historyTable WHERE operationNumber = ?")->execute([$op]);
                }
                $pdo->prepare("DELETE FROM transactions WHERE operationNumber = ?")->execute([$op]);
                if ($oldStatus === 'complet') {
                    if ($prefix === 'D') {
                        $pdo->prepare(
                            'UPDATE personal_data SET '
                            . 'balance = COALESCE(balance,0)-?, '
                            . 'totalDepots = COALESCE(totalDepots,0)-?, '
                            . 'nbTransactions = COALESCE(nbTransactions,0)-1 '
                            . 'WHERE user_id = ?'
                        )->execute([$amount, $amount, $userId]);
                    } elseif ($prefix === 'R') {
                        $pdo->prepare(
                            'UPDATE personal_data SET '
                            . 'balance = COALESCE(balance,0)+?, '
                            . 'totalRetraits = COALESCE(totalRetraits,0)-?, '
                            . 'nbTransactions = COALESCE(nbTransactions,0)-1 '
                            . 'WHERE user_id = ?'
                        )->execute([$amount, $amount, $userId]);
                    }
                }
            } else {
                $status = $data['status'] ?? null;
                $class = $data['statusClass'] ?? null;
                if ($status === null || $class === null) {
                    throw new Exception('Missing status');
                }
                if ($historyTable) {
                    $pdo->prepare("UPDATE $historyTable SET status = ?, statusClass = ? WHERE operationNumber = ?")
                        ->execute([$status, $class, $op]);
                }
                $pdo->prepare("UPDATE transactions SET status = ?, statusClass = ? WHERE operationNumber = ?")
                    ->execute([$status, $class, $op]);
                if ($prefix === 'D') {
                    if ($oldStatus !== 'complet' && $status === 'complet') {
                        $pdo->prepare(
                            'UPDATE personal_data SET '
                            . 'balance = COALESCE(balance,0)+?, '
                            . 'totalDepots = COALESCE(totalDepots,0)+?, '
                            . 'nbTransactions = COALESCE(nbTransactions,0)+1 '
                            . 'WHERE user_id = ?'
                        )->execute([$amount, $amount, $userId]);

                        $timeAgo = formatTimeAgoFromDate($row['date']);
                        $msgAmount = number_format($amount, 0, '.', ' ') . ' $';
                        $pdo->prepare('INSERT INTO notifications (user_id,type,title,message,time,alertClass) VALUES (?,?,?,?,?,?)')
                            ->execute([
                                $userId,
                                'success',
                                'Dépôt réussi',
                                "Un montant de $msgAmount a été déposé avec succès.",
                                $timeAgo,
                                'alert-success'
                            ]);
                    } elseif ($oldStatus === 'complet' && $status !== 'complet') {
                        $pdo->prepare(
                            'UPDATE personal_data SET '
                            . 'balance = COALESCE(balance,0)-?, '
                            . 'totalDepots = COALESCE(totalDepots,0)-?, '
                            . 'nbTransactions = COALESCE(nbTransactions,0)-1 '
                            . 'WHERE user_id = ?'
                        )->execute([$amount, $amount, $userId]);
                    }
                } elseif ($prefix === 'R') {
                    if ($oldStatus !== 'complet' && $status === 'complet') {
                        $pdo->prepare(
                            'UPDATE personal_data SET '
                            . 'balance = COALESCE(balance,0)-?, '
                            . 'totalRetraits = COALESCE(totalRetraits,0)+?, '
                            . 'nbTransactions = COALESCE(nbTransactions,0)+1 '
                            . 'WHERE user_id = ?'
                        )->execute([$amount, $amount, $userId]);

                        $timeAgo = formatTimeAgoFromDate($row['date']);
                        $msgAmount = number_format($amount, 0, '.', ' ') . ' $';
                        $pdo->prepare('INSERT INTO notifications (user_id,type,title,message,time,alertClass) VALUES (?,?,?,?,?,?)')
                            ->execute([
                                $userId,
                                'success',
                                'Retrait approuvé',
                                "Votre retrait de $msgAmount a été approuvé.",
                                $timeAgo,
                                'alert-success'
                            ]);
                    } elseif ($oldStatus === 'complet' && $status !== 'complet') {
                        $pdo->prepare(
                            'UPDATE personal_data SET '
                            . 'balance = COALESCE(balance,0)+?, '
                            . 'totalRetraits = COALESCE(totalRetraits,0)-?, '
                            . 'nbTransactions = COALESCE(nbTransactions,0)-1 '
                            . 'WHERE user_id = ?'
                        )->execute([$amount, $amount, $userId]);
                    }
                }
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
} catch (Throwable $e) {
    error_log(__FILE__ . ' - ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
