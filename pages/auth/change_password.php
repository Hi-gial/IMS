<?php
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit;
}
include '../../config/config.php';

// 定义必要的函数
function input($name) {
    return isset($_POST[$name]) ? trim($_POST[$name]) : '';
}

function h($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

$error = '';
$success = '';

// 检查密码强度
function check_password_strength($password) {
    // 8-16位
    if (strlen($password) < 8 || strlen($password) > 16) {
        return '密码长度必须在8-16位之间';
    }
    // 包含大写字母
    if (!preg_match('/[A-Z]/', $password)) {
        return '密码必须包含大写字母';
    }
    // 包含小写字母
    if (!preg_match('/[a-z]/', $password)) {
        return '密码必须包含小写字母';
    }
    // 包含数字
    if (!preg_match('/[0-9]/', $password)) {
        return '密码必须包含数字';
    }
    // 包含特殊字符
    if (!preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password)) {
        return '密码必须包含特殊字符';
    }
    return true;
}

if ($_POST) {
    $old_password = input('old_password');
    $new_password = input('new_password');
    $confirm_password = input('confirm_password');
    
    // 验证密码
    if (empty($old_password)) {
        $error = '请输入旧密码';
    } elseif (empty($new_password)) {
        $error = '请输入新密码';
    } elseif (empty($confirm_password)) {
        $error = '请确认新密码';
    } elseif ($new_password != $confirm_password) {
        $error = '两次输入的密码不一致';
    } else {
        // 检查旧密码是否正确
        $username = $_SESSION['username'];
        $sql = "SELECT * FROM admin WHERE username = '$username'";
        $res = mysqli_query($conn, $sql);
        $user = mysqli_fetch_assoc($res);
        
        // 使用password_verify验证旧密码
        if (!$user || !password_verify($old_password, $user['password'])) {
            $error = '旧密码错误';
        } else {
            // 检查密码强度（仅普通用户）
            if ($_SESSION['role'] == 2) {
                $strength_result = check_password_strength($new_password);
                if ($strength_result !== true) {
                    $error = $strength_result;
                } else {
                    // 更新密码，使用password_hash加密
                    $encrypted_new_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $update_sql = "UPDATE admin SET password = '$encrypted_new_password' WHERE username = '$username'";
                if (mysqli_query($conn, $update_sql)) {
                    $success = '密码修改成功';
                    // 记录日志
                    if (function_exists('write_log')) {
                        write_log($conn, $username, "修改密码");
                    }
                    // 首次登录修改密码成功后跳转到主页
                    if (isset($_GET['first_login']) && $_GET['first_login'] == 1) {
                        header("Location: ../dashboard/index.php");
                        exit;
                    }
                } else {
                    $error = '密码修改失败，请重试';
                }
                }
            } else {
                // 管理员密码不强制要求强度
                $encrypted_new_password = password_hash($new_password, PASSWORD_DEFAULT);
                $update_sql = "UPDATE admin SET password = '$encrypted_new_password' WHERE username = '$username'";
                if (mysqli_query($conn, $update_sql)) {
                    $success = '密码修改成功';
                    // 记录日志
                    if (function_exists('write_log')) {
                        write_log($conn, $username, "修改密码");
                    }
                    // 首次登录修改密码成功后跳转到主页
                    if (isset($_GET['first_login']) && $_GET['first_login'] == 1) {
                        header("Location: ../dashboard/index.php");
                        exit;
                    }
                } else {
                    $error = '密码修改失败，请重试';
                }
            }
        }
    }
}?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>捷顺ims - 修改密码</title>
    <link rel="icon" href="../../assets/img/b5afc3a4b86e1.png" type="image/png">
    <link rel="stylesheet" href="../../assets/style.css">
</head>
<body>
    <div class="layout-container">
        <!-- 左侧导航 -->
        <div class="sidebar" id="sidebar">
            <div class="toggle-btn" onclick="toggleSidebar()">
                <svg t="1772298614313" class="icon" viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" p-id="1757">
                    <path d="M111.936 896a48 48 0 1 1 0-96h800a48 48 0 1 1 0 96z m230.4-199.616l-224-143.872a47.936 47.936 0 0 1 0-80.896l224-143.872a46.72 46.72 0 0 1 25.6-7.616 48.704 48.704 0 0 1 22.976 5.824 48.064 48.064 0 0 1 25.024 42.112v288a47.872 47.872 0 0 1-25.024 42.048 48.512 48.512 0 0 1-23.104 5.888 46.464 46.464 0 0 1-25.6-7.68zM232.96 512.128l87.104 56V456z m295.104 143.936a48 48 0 0 1 0-96h352a48 48 0 0 1 0 96z m0-192a48 48 0 0 1 0-96h352a48 48 0 0 1 0 96z m-416-240a48 48 0 1 1 0-96h800a48 48 0 1 1 0 96z" fill="#585858" p-id="1758"></path>
                </svg>
            </div>
            <div class="logo"><span>捷顺ims</span></div>
            <div class="welcome-info" style="padding: 15px 20px; font-size: 14px; color: #bdc3c7; border-bottom: 1px solid #34495e; margin-bottom: 10px; text-align: center;">
                欢迎：<?= $_SESSION['real_name'] ?>（<?= $_SESSION['role'] == 1 ? '管理员' : '普通用户' ?>）
            </div>
            <a href="../dashboard/index.php" class="menu-item">
                <span class="icon">🏠</span>
                <span class="text">首页</span>
            </a>
            <a href="../products/product_list.php" class="menu-item">
                <span class="icon">📦</span>
                <span class="text">商品管理</span>
            </a>
            
            <!-- 所有人可见 -->
            <a href="../inventory/in.php" class="menu-item">
                <span class="icon">📥</span>
                <span class="text">商品入库</span>
            </a>
            <a href="../inventory/in_list.php" class="menu-item">
                <span class="icon">📋</span>
                <span class="text">入库记录</span>
            </a>
            <a href="../inventory/out.php" class="menu-item">
                <span class="icon">📤</span>
                <span class="text">商品出库</span>
            </a>
            <a href="../inventory/out_list.php" class="menu-item">
                <span class="icon">📄</span>
                <span class="text">出库记录</span>
            </a>
            
            <!-- 仅管理员可见 -->
            <?php if ($_SESSION['role'] == 1) { ?>
                <a href="../admin/supplier/supplier_list.php" class="menu-item">
                    <span class="icon">🏭</span>
                    <span class="text">供货商管理</span>
                </a>
                <a href="../admin/supplier/receive_unit_list.php" class="menu-item">
                    <span class="icon">🏢</span>
                    <span class="text">领取单位管理</span>
                </a>
                <a href="../admin/user/user_list.php" class="menu-item">
                    <span class="icon">👥</span>
                    <span class="text">用户管理</span>
                </a>
                <a href="../admin/log_list.php" class="menu-item">
                    <span class="icon">📊</span>
                    <span class="text">日志查看</span>
                </a>
            <?php } ?>
            
            <div class="user-info">
                <a href="change_password.php" style="color: #bdc3c7; font-size: 14px; display: block; margin-bottom: 10px; text-align: center;">修改密码</a>
                <a href="logout.php" style="color: #bdc3c7; font-size: 14px; text-align: center; display: block;">退出登录</a>
            </div>
        </div>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const sidebar = document.getElementById('sidebar');
                const toggleBtn = document.querySelector('.toggle-btn');
                const welcomeInfo = document.querySelector('.welcome-info');
                const menuItems = document.querySelectorAll('.menu-item');

                // 从localStorage加载侧边栏状态
                const sidebarCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
                if (sidebarCollapsed) {
                    sidebar.classList.add('collapsed');
                    document.querySelector('.content').classList.add('sidebar-collapsed');
                    welcomeInfo.style.display = 'none';
                    menuItems.forEach(item => {
                        item.style.textAlign = 'center';
                    });
                    // 切换按钮图标
                    toggleBtn.innerHTML = '<svg t="1772301296890" class="icon" viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" p-id="2029"><path d="M112.064 896a48 48 0 1 1 0-96h800a48 48 0 0 1 0 96z m9.024-197.888a48.064 48.064 0 0 1-25.216-42.048v-288a48.128 48.128 0 0 1 74.112-40.32l224 143.872a48.192 48.192 0 0 1 0 80.896l-224 143.872a48.512 48.512 0 0 1-25.92 7.616 47.552 47.552 0 0 1-22.976-5.952zM192 568.128l87.168-56L192 456z m335.936 87.936a48 48 0 0 1 0-96h352a48 48 0 1 1 0 96z m0-192a48 48 0 0 1 0-96h352a48 48 0 1 1 0 96z m-416-240a48 48 0 1 1 0-96h800a48 48 0 0 1 0 96z" fill="#585858" p-id="2030"></path></svg>';
                } else {
                    document.querySelector('.content').classList.remove('sidebar-collapsed');
                }
            });

            function toggleSidebar() {
                const sidebar = document.getElementById('sidebar');
                const toggleBtn = document.querySelector('.toggle-btn');
                const welcomeInfo = document.querySelector('.welcome-info');
                const menuItems = document.querySelectorAll('.menu-item');
                
                sidebar.classList.toggle('collapsed');
                const isCollapsed = sidebar.classList.contains('collapsed');
                
                // 保存状态到localStorage
                localStorage.setItem('sidebarCollapsed', isCollapsed);
                
                document.querySelector('.content').classList.toggle('sidebar-collapsed');
                
                if (isCollapsed) {
                    welcomeInfo.style.display = 'none';
                    menuItems.forEach(item => {
                        item.style.textAlign = 'center';
                    });
                    // 切换按钮图标
                    toggleBtn.innerHTML = '<svg t="1772301296890" class="icon" viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" p-id="2029"><path d="M112.064 896a48 48 0 1 1 0-96h800a48 48 0 0 1 0 96z m9.024-197.888a48.064 48.064 0 0 1-25.216-42.048v-288a48.128 48.128 0 0 1 74.112-40.32l224 143.872a48.192 48.192 0 0 1 0 80.896l-224 143.872a48.512 48.512 0 0 1-25.92 7.616 47.552 47.552 0 0 1-22.976-5.952zM192 568.128l87.168-56L192 456z m335.936 87.936a48 48 0 0 1 0-96h352a48 48 0 1 1 0 96z m0-192a48 48 0 0 1 0-96h352a48 48 0 1 1 0 96z m-416-240a48 48 0 1 1 0-96h800a48 48 0 0 1 0 96z" fill="#585858" p-id="2030"></path></svg>';
                } else {
                    welcomeInfo.style.display = 'block';
                    menuItems.forEach(item => {
                        item.style.textAlign = 'center';
                    });
                    // 切换按钮图标
                    toggleBtn.innerHTML = '<svg t="1772298614313" class="icon" viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" p-id="1757"><path d="M111.936 896a48 48 0 1 1 0-96h800a48 48 0 1 1 0 96z m230.4-199.616l-224-143.872a47.936 47.936 0 0 1 0-80.896l224-143.872a46.72 46.72 0 0 1 25.6-7.616 48.704 48.704 0 0 1 22.976 5.824 48.064 48.064 0 0 1 25.024 42.112v288a47.872 47.872 0 0 1-25.024 42.048 48.512 48.512 0 0 1-23.104 5.888 46.464 46.464 0 0 1-25.6-7.68zM232.96 512.128l87.104 56V456z m295.104 143.936a48 48 0 0 1 0-96h352a48 48 0 0 1 0 96z m0-192a48 48 0 0 1 0-96h352a48 48 0 0 1 0 96z m-416-240a48 48 0 1 1 0-96h800a48 48 0 1 1 0 96z" fill="#585858" p-id="1758"></path></svg>';
                }
            }
        </script>

        <!-- 右侧内容 -->
        <div class="content">
            <div class="content-header">
                <h3>修改密码</h3>
            </div>
            
            <div class="card w-50">
                <div class="card-body">
                    <!-- 首次登录提示 -->
                    <?php if (isset($_GET['first_login']) && $_GET['first_login'] == 1) { ?>
                        <div style="color: #3498db; margin-bottom: 15px; padding: 10px; background: #e3f2fd; border-radius: 4px;">
                            您是首次登录，请修改密码
                        </div>
                    <?php } ?>
                    
                    <!-- 错误提示 -->
                    <?php if ($error) { ?>
                        <div style="color: #e74c3c; margin-bottom: 15px; padding: 10px; background: #fef0f0; border-radius: 4px;">
                            <?= $error ?>
                        </div>
                    <?php } ?>
                    
                    <!-- 成功提示 -->
                    <?php if ($success) { ?>
                        <div style="color: #27ae60; margin-bottom: 15px; padding: 10px; background: #f0f9ff; border-radius: 4px;">
                            <?= $success ?>
                        </div>
                    <?php } ?>
                    
                    <form method="post">
                        <div class="mb-3">
                            <label>旧密码</label>
                            <input type="password" name="old_password" class="form-control" placeholder="请输入旧密码" required>
                        </div>
                        <div class="mb-3">
                            <label>新密码</label>
                            <input type="password" name="new_password" class="form-control" placeholder="请输入新密码" required>
                            <?php if ($_SESSION['role'] == 2) { ?>
                                <small style="color: #666; display: block; margin-top: 5px;">
                                    密码要求：8-16位，包含大写字母、小写字母、数字和特殊字符
                                </small>
                            <?php } ?>
                        </div>
                        <div class="mb-3">
                            <label>确认新密码</label>
                            <input type="password" name="confirm_password" class="form-control" placeholder="请确认新密码" required>
                        </div>
                        <div>
                            <button class="btn btn-primary">保存修改</button>
                            <?php if (!isset($_GET['first_login'])) { ?>
                                <a href="../dashboard/index.php" class="btn btn-outline-secondary ml-2">返回首页</a>
                            <?php } ?>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</body>
</html>