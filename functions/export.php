<?php
session_start();
if (!defined('IN_SYSTEM')) define('IN_SYSTEM', true);
include '../config/config.php';
include '../config/check_perm.php';

// 检查导出权限
if ($_SESSION['role'] != 1 && (!isset($perm['p_export']) || $perm['p_export'] != 1)) {
    echo '<!DOCTYPE html>
    <html lang="zh-CN">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>捷顺ims - 无权限操作</title>
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
            <div class="icon">🔒</div>
            <h1>无权限操作</h1>
            <p>您没有权限执行此操作，请联系管理员获取权限。</p>
            <a href="../pages/dashboard/index.php" class="btn">返回首页</a>
            <a href="javascript:history.back()" class="btn">返回上一页</a>
        </div>
    </body>
    </html>';
    exit;
}

// 定义必要的函数
function input($name) {
    return isset($_REQUEST[$name]) ? trim($_REQUEST[$name]) : '';
}

function h($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

$type = input('type');
$keyword = input('keyword');
$stime = input('stime');
$etime = input('etime');

if ($type !== 'in' && $type !== 'out') {
    echo '<!DOCTYPE html>
    <html lang="zh-CN">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>捷顺ims - 非法类型</title>
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
            <h1>非法类型</h1>
            <p>请使用正确的导出类型。</p>
            <a href="../pages/dashboard/index.php" class="btn">返回首页</a>
            <a href="javascript:history.back()" class="btn">返回上一页</a>
        </div>
    </body>
    </html>';
    exit;
}

header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment;filename=record_".date('YmdHis').".xls");
echo "ID\t商品名称\t数量\t时间\n";

if ($type === 'in') {
    $sql = "SELECT i.*,p.pname FROM stock_in i LEFT JOIN product p ON i.pid=p.id WHERE 1=1";
    if ($keyword) $sql .= " AND p.pname LIKE CONCAT('%',?,'%')";
    if ($stime)   $sql .= " AND i.ctime >= ?";
    if ($etime)   $sql .= " AND i.ctime <= ?";
    $sql .= " ORDER BY i.id DESC";
    $stmt = $conn->prepare($sql);
} else {
    $sql = "SELECT o.*,p.pname FROM stock_out o LEFT JOIN product p ON o.pid=p.id WHERE 1=1";
    if ($keyword) $sql .= " AND p.pname LIKE CONCAT('%',?,'%')";
    if ($stime)   $sql .= " AND o.ctime >= ?";
    if ($etime)   $sql .= " AND o.ctime <= ?";
    $sql .= " ORDER BY o.id DESC";
    $stmt = $conn->prepare($sql);
}

$bind = [];
if ($keyword) $bind[] = $keyword;
if ($stime)   $bind[] = $stime;
if ($etime)   $bind[] = $etime." 23:59:59";
if ($bind) $stmt->bind_param(str_repeat("s", count($bind)), ...$bind);
$stmt->execute();
$res = $stmt->get_result();

while ($r = $res->fetch_assoc()) {
    echo h($r['id'])."\t".h($r['pname'])."\t".h($r['num'])."\t".h($r['ctime'])."\n";
}
exit;
?>