<?php
session_start();
if (!defined('IN_SYSTEM')) {
    echo '<!DOCTYPE html>
    <html lang="zh-CN">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>捷顺ims - 非法访问</title>
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
            }
            
            .btn:hover {
                background-color: #2980b9;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="icon">🚫</div>
            <h1>非法访问</h1>
            <p>您无权访问此页面，请通过正常渠道进入系统。</p>
            <a href="../../index.php" class="btn">返回主页</a>
            <a href="javascript:history.back()" class="btn">返回上一页</a>
        </div>
    </body>
    </html>';
    exit;
}
if (!isset($_SESSION['username'])) {
    header("location:login.php");
    exit;
}

global $conn;
$user = $_SESSION['username'];
$perm = [];

if ($_SESSION['role'] == 1) {
    $perm = [
        'p_index' => 1,
        'p_in' => 1,
        'p_out' => 1,
        'p_in_list' => 1,
        'p_out_list' => 1,
        'p_export' => 1,
        'p_search' => 1
    ];
    define('PERM', true);
    return;
}

$stmt = $conn->prepare("SELECT * FROM user_perm WHERE username = ?");
$stmt->bind_param("s", $user);
$stmt->execute();
$p = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$p) {
    $stmt = $conn->prepare("INSERT INTO user_perm(username) VALUES(?)");
    $stmt->bind_param("s", $user);
    $stmt->execute();
    $stmt->close();
    $p = [
        'p_index' => 1,
        'p_in' => 0,
        'p_out' => 0,
        'p_in_list' => 0,
        'p_out_list' => 0,
        'p_export' => 0,
        'p_search' => 0
    ];
}

$perm = $p;
define('PERM', true);
?>