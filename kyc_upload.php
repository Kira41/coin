<?php
header('Content-Type: application/json');
set_error_handler(function ($s,$m,$f,$l){throw new ErrorException($m,0,$s,$f,$l);});
try{
    $dsn='mysql:host=localhost;dbname=coin_db;charset=utf8mb4';
    $pdo=new PDO($dsn,'root','');
    $pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
    $userId=isset($_POST['user_id'])?(int)$_POST['user_id']:0;
    if(!$userId){throw new Exception('Missing user_id');}
    if(empty($_FILES['files'])){throw new Exception('No files uploaded');}
    $pdo->beginTransaction();
    $stmt=$pdo->prepare('INSERT INTO kyc (user_id,file_name,file_data) VALUES (?,?,?)');
    foreach($_FILES['files']['tmp_name'] as $i=>$tmp){
        $name=$_FILES['files']['name'][$i];
        $data=file_get_contents($tmp);
        $base64=base64_encode($data);
        $stmt->execute([$userId,$name,$base64]);
    }
    $pdo->commit();
    echo json_encode(['status'=>'ok']);
}catch(Throwable $e){
    if($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['status'=>'error','message'=>$e->getMessage()]);
}
