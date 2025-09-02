<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
if (!isset($_SESSION['user_id'])) {
    header("Location: /combined_login_register.php");
    exit;
}
