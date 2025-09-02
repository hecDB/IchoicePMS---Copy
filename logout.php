<?php
session_start();
session_destroy();
header("Location: combined_login_register.php");
exit;
?>