<?php
session_start();
if (!defined('IN_SYSTEM')) define('IN_SYSTEM', true);
include '../../../config/config.php';
include '../../../config/check_perm.php';
include '../../../config/write_log.php';

if ($_SESSION['role'] != 1) {
    header("Location: ../../auth/no_permission.php");
    exit;
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) {
    echo "<script>alert('无效的领取单位ID');location.href='receive_unit_list.php';</script>";
    exit;
}

$stmt = mysqli_prepare($conn, "SELECT name FROM receive_unit WHERE id = ?");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$unit = mysqli_stmt_get_result($stmt)->fetch_assoc();

if (!$unit) {
    echo "<script>alert('领取单位不存在');location.href='receive_unit_list.php';</script>";
    exit;
}

$name = $unit['name'];

$stmt = mysqli_prepare($conn, "DELETE FROM receive_unit WHERE id = ?");
mysqli_stmt_bind_param($stmt, "i", $id);

if (mysqli_stmt_execute($stmt)) {
    write_log($conn, $_SESSION['username'], "删除领取单位：$name");
    echo "<script>alert('删除成功');location.href='receive_unit_list.php';</script>";
} else {
    echo "<script>alert('删除失败：" . mysqli_error($conn) . "');location.href='receive_unit_list.php';</script>";
}
?>
