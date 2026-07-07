<?php
session_start();
if (!defined('IN_SYSTEM')) define('IN_SYSTEM', true);
include '../../config/config.php';

$brand = isset($_GET['brand']) ? mysqli_real_escape_string($conn, $_GET['brand']) : '';

if (empty($brand)) {
    echo '<option value="">-- 请先选择品牌 --</option>';
    exit;
}

$res = mysqli_query($conn, "SELECT id, model, pname, stock FROM product WHERE brand = '$brand' ORDER BY model");

$hasData = false;
while ($row = mysqli_fetch_assoc($res)) {
    $hasData = true;
    $model = !empty($row['model']) ? $row['model'] : $row['pname'];
    echo "<option value='{$row['id']}'>{$model}（当前库存：{$row['stock']}）</option>";
}

if (!$hasData) {
    echo '<option value="">-- 该品牌暂无商品 --</option>';
}
?>