<?php
header('Content-Type: application/json');
set_error_handler(function ($s,$m,$f,$l){throw new ErrorException($m,0,$s,$f,$l);});
try {
    require_once __DIR__.'/../config/db_connection.php';
    $pdo = db();

    $userId = $_POST['user_id'] ?? '';
    if ($userId === '') {
        throw new Exception('Missing user_id');
    }

    if (!isset($_FILES['files'])) {
        throw new Exception('No files uploaded');
    }

    $files = $_FILES['files'];
    if (!is_array($files['tmp_name'])) {
        $files = [
            'name'     => [$files['name']],
            'tmp_name' => [$files['tmp_name']],
            'error'    => [$files['error'] ?? UPLOAD_ERR_NO_FILE],
        ];
    }

    $pdo->beginTransaction();
    $stmt = $pdo->prepare('INSERT INTO kyc (user_id,file_name,file_data) VALUES (?,?,?)');
    foreach ($files['tmp_name'] as $i => $tmp) {
        if (($files['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new Exception('Upload error: ' . ($files['error'][$i] ?? 0));
        }
        if (!is_uploaded_file($tmp)) {
            throw new Exception('Invalid upload');
        }
        $name = $files['name'][$i];
        $data = file_get_contents($tmp);
        $base64 = base64_encode($data);
        $stmt->execute([$userId, $name, $base64]);
    }
    $pdo->prepare('INSERT INTO verification_status (user_id, telechargerlesdocumentsdidentite) VALUES (?,2) ON DUPLICATE KEY UPDATE telechargerlesdocumentsdidentite=2')->execute([$userId]);
    $pdo->commit();
    echo json_encode(['status' => 'ok']);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
