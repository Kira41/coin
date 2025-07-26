<?php
header('Content-Type: application/json');
set_error_handler(function($s,$m,$f,$l){throw new ErrorException($m,0,$s,$f,$l);});
try{
    require __DIR__ . '/config.php';
    $pdo=new PDO($dsn,$dbUser,$dbPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);

    function formatTimeAgoFromDate($dateStr){
        $ts=strtotime($dateStr);
        if(!$ts) return '';
        $diff=time()-$ts;
        if($diff<60) return "À l'instant";
        $mins=floor($diff/60);
        if($mins<60) return 'Il y a '.$mins.' minute'.($mins>1?'s':'');
        $hours=floor($diff/3600);
        if($hours<24) return 'Il y a '.$hours.' heure'.($hours>1?'s':'');
        $days=floor($diff/86400);
        return 'Il y a '.$days.' jour'.($days>1?'s':'');
    }
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
            if($status==='approved'){
                $timeAgo=formatTimeAgoFromDate(date('Y-m-d H:i:s'));
                $pdo->prepare('INSERT INTO notifications (user_id,type,title,message,time,alertClass) VALUES (?,?,?,?,?,?)')
                    ->execute([
                        $uid,
                        'kyc',
                        'Vérification approuvée',
                        "Votre vérification d'identité a été approuvée.",
                        $timeAgo,
                        'alert-success'
                    ]);
            }
        }
        echo json_encode(['status'=>'ok']);
    }
}catch(Throwable $e){
    http_response_code(500);
    echo json_encode(['status'=>'error','message'=>$e->getMessage()]);
}
