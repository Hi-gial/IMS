<?php
session_start();
if (!isset($_SESSION['username']) || $_SESSION['role'] != 1) {
    header("Location: ../auth/login.php");
    exit;
}
include '../../config/config.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$res = mysqli_query($conn, "SELECT * FROM product WHERE id = $id");

if (!mysqli_fetch_assoc($res)) {
    echo "<script>alert('商品不存在');location.href='product_list.php';</script>";
    exit;
}

mysqli_query($conn, "DELETE FROM product WHERE id = $id");
echo "<script>alert('商品删除成功');location.href='product_list.php';</script>";
exit;
?>