<?php
function emitEvent(string $event, array $data = [], $userId = null): bool {
    $payload = json_encode(['event' => $event, 'data' => $data, 'userId' => $userId]);
    $ch = curl_init('http://localhost:3001/emit');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_TIMEOUT => 2
    ]);
    $res = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);
    return $res !== false && !$err;
}
?>
