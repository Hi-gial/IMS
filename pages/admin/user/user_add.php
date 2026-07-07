<?php
session_start();
if (!defined('IN_SYSTEM')) define('IN_SYSTEM', true);
include '../../../config/config.php';
include '../../../config/check_perm.php';
include '../../../config/write_log.php';

// 定义必要的函数
function input($name) {
    return isset($_POST[$name]) ? trim($_POST[$name]) : '';
}

function h($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

// 检查是否是管理员
if ($_SESSION['role'] != 1) {
    header("Location: ../../auth/no_permission.php");
    exit;
}

if ($_POST) {
    $user = input('username');
    $pwd = password_hash(input('password'), PASSWORD_DEFAULT);
    $role = input('role');
    $nickname = input('nickname');
    
    // 尝试使用简单的SQL语句
    try {
        // 先尝试带first_login和nickname字段的插入
        $sql = "INSERT INTO admin (username, password, role, nickname, first_login) VALUES ('$user', '$pwd', '$role', '$nickname', 1)";
        if (mysqli_query($conn, $sql)) {
            write_log($conn, $_SESSION['username'], "添加用户：$user");
            header("location:user_list.php");
            exit;
        } else {
            // 如果失败，尝试不带first_login字段的插入
            $sql = "INSERT INTO admin (username, password, role, nickname) VALUES ('$user', '$pwd', '$role', '$nickname')";
            if (mysqli_query($conn, $sql)) {
                write_log($conn, $_SESSION['username'], "添加用户：$user");
                header("location:user_list.php");
                exit;
            } else {
                // 显示错误信息
                echo "<script>alert('添加用户失败：" . mysqli_error($conn) . "');history.back();</script>";
                exit;
            }
        }
    } catch (Exception $e) {
        // 显示错误信息
        echo "<script>alert('添加用户失败：" . $e->getMessage() . "');history.back();</script>";
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>捷顺ims - 添加用户</title>
    <link rel="icon" href="../../../assets/img/b5afc3a4b86e1.png" type="image/png">
    <link rel="stylesheet" href="../../../assets/style.css">
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
                欢迎：<?= $_SESSION['real_name'] ?>（管理员）
            </div>
            <a href="../../dashboard/index.php" class="menu-item">
                <span class="icon">🏠</span>
                <span class="text">首页</span>
            </a>
            <a href="../../products/product_list.php" class="menu-item">
                <span class="icon">📦</span>
                <span class="text">商品管理</span>
            </a>
            
            <!-- 所有人可见 -->
            <a href="../../inventory/in.php" class="menu-item">
                <span class="icon">📥</span>
                <span class="text">商品入库</span>
            </a>
            <a href="../../inventory/in_list.php" class="menu-item">
                <span class="icon">📋</span>
                <span class="text">入库记录</span>
            </a>
            <a href="../../inventory/out.php" class="menu-item">
                <span class="icon">📤</span>
                <span class="text">商品出库</span>
            </a>
            <a href="../../inventory/out_list.php" class="menu-item">
                <span class="icon">📄</span>
                <span class="text">出库记录</span>
            </a>
            
            <!-- 仅管理员可见 -->
            <a href="../supplier/supplier_list.php" class="menu-item">
                <span class="icon">🏭</span>
                <span class="text">供货商管理</span>
            </a>
            <a href="../supplier/receive_unit_list.php" class="menu-item">
                <span class="icon">🏢</span>
                <span class="text">领取单位管理</span>
            </a>
            <a href="user_list.php" class="menu-item active">
                <span class="icon">👥</span>
                <span class="text">用户管理</span>
            </a>
            <a href="../log_list.php" class="menu-item">
                <span class="icon">📊</span>
                <span class="text">日志查看</span>
            </a>
            <a href="../backup.php" class="menu-item">
                <span class="icon">💾</span>
                <span class="text">数据备份</span>
            </a>
            
            <div class="user-info">
                <a href="../../auth/logout.php" style="color: #bdc3c7; font-size: 14px; text-align: center; display: block;">退出登录</a>
            </div>
        </div>

        <!-- 右侧内容 -->
        <div class="content">
            <div class="content-header">
                <h3>添加用户</h3>
            </div>
            
            <div class="card w-50">
                <div class="card-body">
                    <form method="post">
                        <div class="mb-3">
                            <label class="form-label">账号</label>
                            <input name="username" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">昵称</label>
                            <input name="nickname" class="form-control" placeholder="请输入昵称（选填）">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">密码</label>
                            <input type="text" name="password" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">角色</label>
                            <select name="role" class="form-select">
                                <option value="user">普通用户</option>
                                <option value="admin">管理员</option>
                            </select>
                        </div>
                        <div>
                            <button class="btn btn-success">保存</button>
                            <a href="user_list.php" class="btn btn-outline-secondary ml-2">返回</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        // 页面加载时恢复导航栏状态
        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.getElementById('sidebar');
            const toggleBtn = sidebar.querySelector('.toggle-btn');
            const svg = toggleBtn.querySelector('svg');
            
            // 从localStorage获取状态
            const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
            
            if (isCollapsed) {
                sidebar.classList.add('collapsed');
                document.querySelector('.content').classList.add('sidebar-collapsed');
                // 折叠状态下的图标
                svg.innerHTML = '<path d="M112.064 896a48 48 0 1 1 0-96h800a48 48 0 0 1 0 96z m9.024-197.888a48.064 48.064 0 0 1-25.216-42.048v-288a48.128 48.128 0 0 1 74.112-40.32l224 143.872a48.192 48.192 0 0 1 0 80.896l-224 143.872a48.512 48.512 0 0 1-25.92 7.616 47.552 47.552 0 0 1-22.976-5.952zM192 568.128l87.168-56L192 456z m335.936 87.936a48 48 0 0 1 0-96h352a48 48 0 1 1 0 96z m0-192a48 48 0 0 1 0-96h352a48 48 0 1 1 0 96z m-416-240a48 48 0 1 1 0-96h800a48 48 0 0 1 0 96z" fill="#585858" p-id="2030"></path>';
                svg.setAttribute('t', '1772301296890');
                svg.setAttribute('p-id', '2029');
            } else {
                sidebar.classList.remove('collapsed');
                document.querySelector('.content').classList.remove('sidebar-collapsed');
                // 展开状态下的图标
                svg.innerHTML = '<path d="M111.936 896a48 48 0 1 1 0-96h800a48 48 0 1 1 0 96z m230.4-199.616l-224-143.872a47.936 47.936 0 0 1 0-80.896l224-143.872a46.72 46.72 0 0 1 25.6-7.616 48.704 48.704 0 0 1 22.976 5.824 48.064 48.064 0 0 1 25.024 42.112v288a47.872 47.872 0 0 1-25.024 42.048 48.512 48.512 0 0 1-23.104 5.888 46.464 46.464 0 0 1-25.6-7.68zM232.96 512.128l87.104 56V456z m295.104 143.936a48 48 0 0 1 0-96h352a48 48 0 0 1 0 96z m0-192a48 48 0 0 1 0-96h352a48 48 0 0 1 0 96z m-416-240a48 48 0 1 1 0-96h800a48 48 0 1 1 0 96z" fill="#585858" p-id="1758"></path>';
                svg.setAttribute('t', '1772298614313');
                svg.setAttribute('p-id', '1757');
            }
        });
        
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const toggleBtn = sidebar.querySelector('.toggle-btn');
            const svg = toggleBtn.querySelector('svg');
            
            sidebar.classList.toggle('collapsed');
            
            // 保存状态到localStorage
            const isCollapsed = sidebar.classList.contains('collapsed');
            localStorage.setItem('sidebarCollapsed', isCollapsed);
            
            document.querySelector('.content').classList.toggle('sidebar-collapsed');
            
            if (isCollapsed) {
                // 折叠状态下的图标
                svg.innerHTML = '<path d="M112.064 896a48 48 0 1 1 0-96h800a48 48 0 0 1 0 96z m9.024-197.888a48.064 48.064 0 0 1-25.216-42.048v-288a48.128 48.128 0 0 1 74.112-40.32l224 143.872a48.192 48.192 0 0 1 0 80.896l-224 143.872a48.512 48.512 0 0 1-25.92 7.616 47.552 47.552 0 0 1-22.976-5.952zM192 568.128l87.168-56L192 456z m335.936 87.936a48 48 0 0 1 0-96h352a48 48 0 1 1 0 96z m0-192a48 48 0 0 1 0-96h352a48 48 0 1 1 0 96z m-416-240a48 48 0 1 1 0-96h800a48 48 0 0 1 0 96z" fill="#585858" p-id="2030"></path>';
                svg.setAttribute('t', '1772301296890');
                svg.setAttribute('p-id', '2029');
            } else {
                // 展开状态下的图标
                svg.innerHTML = '<path d="M111.936 896a48 48 0 1 1 0-96h800a48 48 0 1 1 0 96z m230.4-199.616l-224-143.872a47.936 47.936 0 0 1 0-80.896l224-143.872a46.72 46.72 0 0 1 25.6-7.616 48.704 48.704 0 0 1 22.976 5.824 48.064 48.064 0 0 1 25.024 42.112v288a47.872 47.872 0 0 1-25.024 42.048 48.512 48.512 0 0 1-23.104 5.888 46.464 46.464 0 0 1-25.6-7.68zM232.96 512.128l87.104 56V456z m295.104 143.936a48 48 0 0 1 0-96h352a48 48 0 0 1 0 96z m0-192a48 48 0 0 1 0-96h352a48 48 0 0 1 0 96z m-416-240a48 48 0 1 1 0-96h800a48 48 0 1 1 0 96z" fill="#585858" p-id="1758"></path>';
                svg.setAttribute('t', '1772298614313');
                svg.setAttribute('p-id', '1757');
            }
        }
    </script>
</body>
</html>