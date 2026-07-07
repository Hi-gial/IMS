<?php
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
            <a href="../index.php" class="btn">返回主页</a>
        </div>
    </body>
    </html>';
    exit;
}
function write_log($conn, $user, $content) {
    $stmt = $conn->prepare("INSERT INTO log(username,content) VALUES(?,?)");
    $stmt->bind_param("ss", $user, $content);
    $stmt->execute();
    $stmt->close();
}
?>