<?php
require '../config/db_connect.php';
$data = json_decode(file_get_contents("php://input"), true);

if($data && isset($data['user_id'])){
    $stmt = $pdo->prepare("UPDATE users SET name=?, email=?, department=?, role=? WHERE user_id=?");
    $ok = $stmt->execute([
        $data['name'],
        $data['email'],
        $data['department'],
        $data['role'],
        $data['user_id']
    ]);

    echo json_encode(["success"=>$ok]);
} else {
    echo json_encode(["success"=>false,"message"=>"invalid data"]);
}
