<?php
session_start();
if (!defined('IN_SYSTEM')) define('IN_SYSTEM', true);
include '../config/config.php';
include '../config/check_perm.php';

// 定义必要的函数
function input($name) {
    return isset($_REQUEST[$name]) ? trim($_REQUEST[$name]) : '';
}

function h($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

$id = intval(input('id'));
$stmt = $conn->prepare("SELECT * FROM product WHERE id=?");
$stmt->bind_param("i", $id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
    header("location:../pages/products/product_list.php");
    exit;
}

if ($_POST) {
    $pname = input('pname');
    $category = input('category');
    $price = input('price');
    $warn_stock = intval(input('warn_stock'));

    $stmt = $conn->prepare("UPDATE product SET pname=?,category=?,price=?,warn_stock=? WHERE id=?");
    $stmt->bind_param("ssdii", $pname, $category, $price, $warn_stock, $id);
    $stmt->execute();
    $stmt->close();

    header("location:../pages/products/product_list.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<title>捷顺ims - 编辑商品</title>
<link rel="stylesheet" href="../assets/style.css">
</head>
<body>
    <div class="layout-container">
        <!-- 左侧导航 -->
        <div class="sidebar">
            <div class="logo">捷顺ims</div>
            <div style="padding: 15px 20px; font-size: 14px; color: #bdc3c7; border-bottom: 1px solid #34495e; margin-bottom: 10px; text-align: center;">
                欢迎：<?= $_SESSION['real_name'] ?>（管理员）
            </div>
            <a href="../pages/dashboard/index.php" class="menu-item" style="text-align: center;">首页</a>
            <a href="../pages/products/product_list.php" class="menu-item active" style="text-align: center;">商品管理</a>
            
            <!-- 仅管理员可见 -->
            <a href="../pages/products/product_add.php" class="menu-item" style="text-align: center;">添加商品</a>
            <a href="../pages/inventory/in.php" class="menu-item" style="text-align: center;">商品入库</a>
            <a href="../pages/inventory/in_list.php" class="menu-item" style="text-align: center;">入库记录</a>
            <a href="../pages/admin/user/user_list.php" class="menu-item" style="text-align: center;">用户管理</a>
            
            <!-- 所有人可见 -->
            <a href="../pages/inventory/out.php" class="menu-item" style="text-align: center;">商品出库</a>
            <a href="../pages/inventory/out_list.php" class="menu-item" style="text-align: center;">出库记录</a>
            
            <div class="user-info" style="text-align: center; margin-top: 20px;">
                <a href="../pages/auth/logout.php" style="color: #bdc3c7; font-size: 14px;">退出登录</a>
            </div>
        </div>

        <!-- 右侧内容 -->
        <div class="content">
            <div class="content-header">
                <h3>编辑商品</h3>
            </div>
            
            <div class="card w-50">
                <div class="card-body">
                    <form method="post">
                        <div class="mb-3">
                            <label>商品名称</label>
                            <input name="pname" value="<?=h($row['pname'])?>" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label>分类</label>
                            <input name="category" value="<?=h($row['category'])?>" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label>价格</label>
                            <input name="price" value="<?=h($row['price'])?>" step="0.01" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label>库存预警</label>
                            <input name="warn_stock" value="<?=h($row['warn_stock'])?>" class="form-control">
                        </div>
                        <div>
                            <button class="btn btn-primary">保存修改</button>
                            <a href="../pages/products/product_list.php" class="btn btn-outline-secondary ml-2">返回</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</body>
</html>