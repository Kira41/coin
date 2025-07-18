<?php
header('Content-Type: application/json');
set_error_handler(function($s,$m,$f,$l){throw new ErrorException($m,0,$s,$f,$l);});
try{
    $dsn='mysql:host=localhost;dbname=coin_db;charset=utf8mb4';
    $pdo=new PDO($dsn,'root','');
    $pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
    session_start();
    $adminId=$_SESSION['admin_id']??null;
    if(!$adminId){
        http_response_code(401);
        echo json_encode(['status'=>'error','message'=>'Unauthorized']);
        exit;
    }
    $stmt=$pdo->prepare('SELECT is_admin FROM admins_agents WHERE id=?');
    $stmt->execute([$adminId]);
    $isAdmin=(int)$stmt->fetchColumn();
    if($_SERVER['REQUEST_METHOD']==='GET'){
        if(isset($_GET['id'])){
            $stmt=$pdo->prepare('SELECT k.file_id,k.user_id,p.fullName,p.emailaddress,k.file_name,k.file_data,k.status,k.created_at FROM kyc k JOIN personal_data p ON k.user_id=p.user_id WHERE k.file_id=?');
            $stmt->execute([(int)$_GET['id']]);
            $file=$stmt->fetch(PDO::FETCH_ASSOC);
            echo json_encode(['status'=>'ok','file'=>$file]);
            exit;
        }
        if(isset($_GET['all'])){
            if($isAdmin===1){
                $stmt=$pdo->query('SELECT k.file_id,k.user_id,p.fullName,p.emailaddress,k.file_name,k.created_at,k.status FROM kyc k JOIN personal_data p ON k.user_id=p.user_id ORDER BY k.created_at DESC');
            }else{
                $stmt=$pdo->prepare('SELECT k.file_id,k.user_id,p.fullName,p.emailaddress,k.file_name,k.created_at,k.status FROM kyc k JOIN personal_data p ON k.user_id=p.user_id WHERE p.linked_to_id=? ORDER BY k.created_at DESC');
                $stmt->execute([$adminId]);
            }
            $rows=$stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['status'=>'ok','kyc'=>$rows]);
        }else{
            $sql='SELECT k.file_id,k.user_id,p.fullName,p.emailaddress,k.file_name,k.created_at,k.status FROM kyc k JOIN personal_data p ON k.user_id=p.user_id WHERE p.linked_to_id=? AND k.status="pending"';
            $stmt=$pdo->prepare($sql);
            $stmt->execute([$adminId]);
            $rows=$stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['status'=>'ok','kyc'=>$rows]);
        }
    } else {
        $input=json_decode(file_get_contents('php://input'),true);
        if(!is_array($input)) throw new Exception('Invalid JSON');
        $id=$input['file_id']??0;
        $status=$input['status']??'';
        if(!$id || !in_array($status,['approved','rejected'])) throw new Exception('Invalid params');
        $stmt=$pdo->prepare('UPDATE kyc SET status=? WHERE file_id=?');
        $stmt->execute([$status,(int)$id]);
        $uidStmt=$pdo->prepare('SELECT user_id FROM kyc WHERE file_id=?');
        $uidStmt->execute([(int)$id]);
        $uid=$uidStmt->fetchColumn();
        if($uid){
            $val=$status==='approved'?1:0;
            $pdo->prepare('INSERT INTO verification_status (user_id, telechargerlesdocumentsdidentite) VALUES (?,?) ON DUPLICATE KEY UPDATE telechargerlesdocumentsdidentite=VALUES(telechargerlesdocumentsdidentite)')->execute([$uid,$val]);
        }
        echo json_encode(['status'=>'ok']);
    }
}catch(Throwable $e){
    http_response_code(500);
    echo json_encode(['status'=>'error','message'=>$e->getMessage()]);
}
