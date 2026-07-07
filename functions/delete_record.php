<?php
session_start();
if (!defined('IN_SYSTEM')) define('IN_SYSTEM', true);
include '../config/config.php';
include '../config/check_perm.php';
include '../config/write_log.php';

// 定义必要的函数
function input($name) {
    return isset($_REQUEST[$name]) ? trim($_REQUEST[$name]) : '';
}

// 检查是否是管理员
if ($_SESSION['role'] != 1) {
    header("Location: ../pages/auth/no_permission.php");
    exit;
}

$id   = intval(input('id'));
$type = input('type');
$pid  = intval(input('pid'));
$num  = intval(input('num'));

if ($id < 1 || $pid < 1 || $num < 1) {
    echo '<!DOCTYPE html>
    <html lang="zh-CN">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>捷顺ims - 非法参数</title>
        <link rel="icon" href="../assets/img/b5afc3a4b86e1.png" type="image/png">
        <style>
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }
            
            body {
                font-family: Arial, sans-serif;
                background-color: #f5f5f5;
                color: #333;
                display: flex;
                justify-content: center;
                align-items: center;
                height: 100vh;
            }
            
            .container {
                max-width: 500px;
                padding: 40px;
                background-color: white;
                border-radius: 8px;
                box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
                text-align: center;
            }
            
            .icon {
                font-size: 64px;
                margin-bottom: 20px;
                color: #e74c3c;
            }
            
            h1 {
                font-size: 24px;
                margin-bottom: 15px;
                color: #333;
            }
            
            p {
                font-size: 16px;
                margin-bottom: 30px;
                color: #666;
            }
            
            .btn {
                display: inline-block;
                padding: 10px 20px;
                background-color: #3498db;
                color: white;
                text-decoration: none;
                border-radius: 4px;
                font-weight: bold;
                transition: background-color 0.3s ease;
                margin: 0 10px;
            }
            
            .btn:hover {
                background-color: #2980b9;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="icon">⚠️</div>
            <h1>非法参数</h1>
            <p>请提供有效的参数。</p>
            <a href="../pages/dashboard/index.php" class="btn">返回首页</a>
            <a href="javascript:history.back()" class="btn">返回上一页</a>
        </div>
    </body>
    </html>';
    exit;
}

$conn->begin_transaction();
try {
    if ($type === 'in') {
        $stmt = $conn->prepare("DELETE FROM stock_in WHERE id=?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare("UPDATE product SET stock=stock-? WHERE id=?");
        $stmt->bind_param("ii", $num, $pid);
        $stmt->execute();
        $stmt->close();
    } elseif ($type === 'out') {
        $stmt = $conn->prepare("DELETE FROM stock_out WHERE id=?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare("UPDATE product SET stock=stock+? WHERE id=?");
        $stmt->bind_param("ii", $num, $pid);
        $stmt->execute();
        $stmt->close();
    }
    $conn->commit();
    write_log($conn, $_SESSION['username'], "删除{$type}记录 ID:$id");
} catch (Exception $e) {
    $conn->rollback();
}

header("location:".$_SERVER['HTTP_REFERER']);
exit;
?>