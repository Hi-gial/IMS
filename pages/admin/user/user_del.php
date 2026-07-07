<?php
session_start();
if (!defined('IN_SYSTEM')) define('IN_SYSTEM', true);
include '../../../config/config.php';
include '../../../config/check_perm.php';
include '../../../config/write_log.php';

// 定义必要的函数
function input($name) {
    return isset($_REQUEST[$name]) ? trim($_REQUEST[$name]) : '';
}

// 检查是否是管理员
if ($_SESSION['role'] != 1) {
    header("Location: ../../auth/no_permission.php");
    exit;
}

$id = intval(input('id'));

$stmt = $conn->prepare("SELECT username FROM admin WHERE id=?");
$stmt->bind_param("i", $id);
$stmt->execute();
$u = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($u && $u['username'] != 'admin') {
    $stmt = $conn->prepare("DELETE FROM admin WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    write_log($conn, $_SESSION['username'], "删除用户：{$u['username']}");
}

header("location:user_list.php");
exit;
?>