<?php
require 'db_connect.php';
$id = intval($_GET['id'] ?? 0);
if($id){
    $stmt = $pdo->prepare("DELETE FROM users WHERE user_id=?");
    $stmt->execute([$id]);
    echo "deleted";
}
