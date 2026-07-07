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
    echo "<script>alert('无效的供货商ID');location.href='supplier_list.php';</script>";
    exit;
}

$stmt = mysqli_prepare($conn, "SELECT name FROM supplier WHERE id = ?");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$supplier = mysqli_stmt_get_result($stmt)->fetch_assoc();

if (!$supplier) {
    echo "<script>alert('供货商不存在');location.href='supplier_list.php';</script>";
    exit;
}

$name = $supplier['name'];

$stmt = mysqli_prepare($conn, "DELETE FROM supplier WHERE id = ?");
mysqli_stmt_bind_param($stmt, "i", $id);

if (mysqli_stmt_execute($stmt)) {
    write_log($conn, $_SESSION['username'], "删除供货商：$name");
    echo "<script>alert('删除成功');location.href='supplier_list.php';</script>";
} else {
    echo "<script>alert('删除失败：" . mysqli_error($conn) . "');location.href='supplier_list.php';</script>";
}
?>