<?php
include '../../config/config.php';

session_start();
// 销毁所有会话变量
session_unset();
session_destroy();
header("Location: login.php");
exit;
?>