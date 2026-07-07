<?php
session_start();
if (!defined('IN_SYSTEM')) define('IN_SYSTEM', true);
include '../../../config/config.php';

// 检查是否是管理员
if ($_SESSION['role'] != 1) {
    header("Location: ../../auth/no_permission.php");
    exit;
}

// 定义 h() 函数用于 HTML 转义
function h($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

$user = isset($_GET['user']) ? $_GET['user'] : '';

if (empty($user)) {
    echo "<script>alert('参数错误');history.back();</script>";
    exit;
}

// 检查用户是否存在
$check_user = mysqli_query($conn, "SELECT * FROM admin WHERE username = '$user'");
if (mysqli_num_rows($check_user) == 0) {
    echo "<script>alert('用户不存在');history.back();</script>";
    exit;
}

// 检查是否是管理员
$is_admin = false;
$admin_res = mysqli_query($conn, "SELECT * FROM admin WHERE username = '$user' AND role = 'admin'");
if (mysqli_num_rows($admin_res) > 0) {
    $is_admin = true;
}

// 处理权限提交
if ($_POST) {
    $p_index = isset($_POST['p_index']) ? 1 : 0;
    $p_in = isset($_POST['p_in']) ? 1 : 0;
    $p_out = isset($_POST['p_out']) ? 1 : 0;
    $p_in_list = isset($_POST['p_in_list']) ? 1 : 0;
    $p_out_list = isset($_POST['p_out_list']) ? 1 : 0;
    $p_export = isset($_POST['p_export']) ? 1 : 0;
    $p_search = isset($_POST['p_search']) ? 1 : 0;
    
    // 检查用户权限是否已存在
    $check_perm = mysqli_query($conn, "SELECT * FROM user_perm WHERE username = '$user'");
    if (mysqli_num_rows($check_perm) > 0) {
        // 更新权限
        $update_sql = "UPDATE user_perm SET p_index = $p_index, p_in = $p_in, p_out = $p_out, p_in_list = $p_in_list, p_out_list = $p_out_list, p_export = $p_export, p_search = $p_search WHERE username = '$user'";
        mysqli_query($conn, $update_sql);
    } else {
        // 插入权限
        $insert_sql = "INSERT INTO user_perm (username, p_index, p_in, p_out, p_in_list, p_out_list, p_export, p_search) VALUES ('$user', $p_index, $p_in, $p_out, $p_in_list, $p_out_list, $p_export, $p_search)";
        mysqli_query($conn, $insert_sql);
    }
    
    echo "<script>alert('权限设置成功');location.href='user_list.php';</script>";
    exit;
}

// 获取用户当前权限
$perm_res = mysqli_query($conn, "SELECT * FROM user_perm WHERE username = '$user'");
$perm = mysqli_fetch_assoc($perm_res);
if (!$perm) {
    // 检查是否是管理员
    $check_admin = mysqli_query($conn, "SELECT * FROM admin WHERE username = '$user' AND role = 'admin'");
    if (mysqli_num_rows($check_admin) > 0) {
        // 管理员默认全部权限
        $perm = array(
            'p_index' => 1,
            'p_in' => 1,
            'p_out' => 1,
            'p_in_list' => 1,
            'p_out_list' => 1,
            'p_export' => 1,
            'p_search' => 1
        );
    } else {
        // 普通用户默认权限
        $perm = array(
            'p_index' => 1,
            'p_in' => 0,
            'p_out' => 0,
            'p_in_list' => 0,
            'p_out_list' => 0,
            'p_export' => 0,
            'p_search' => 1
        );
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>捷顺ims - 设置权限</title>
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
            
            <div class="user-info">
                <a href="../../auth/logout.php" style="color: #bdc3c7; font-size: 14px; text-align: center; display: block;">退出登录</a>
            </div>
        </div>

        <!-- 右侧内容 -->
        <div class="content">
            <div class="content-header">
                <h3>权限设置 - <?= h($user) ?></h3>
            </div>
            
            <div class="card">
                <div class="card-body">
                    <form method="post">
                        <div class="mb-3">
                            <label class="form-label">首页访问</label>
                            <div class="form-check">
                                <input type="checkbox" name="p_index" class="form-check-input" value="1" <?= $perm['p_index'] ? 'checked' : '' ?> <?= $is_admin ? 'disabled' : '' ?>>
                                <label class="form-check-label">允许访问首页</label>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">入库权限</label>
                            <div class="form-check">
                                <input type="checkbox" name="p_in" class="form-check-input" value="1" <?= $perm['p_in'] ? 'checked' : '' ?> <?= $is_admin ? 'disabled' : '' ?>>
                                <label class="form-check-label">允许商品入库</label>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">出库权限</label>
                            <div class="form-check">
                                <input type="checkbox" name="p_out" class="form-check-input" value="1" <?= $perm['p_out'] ? 'checked' : '' ?> <?= $is_admin ? 'disabled' : '' ?>>
                                <label class="form-check-label">允许商品出库</label>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">入库记录</label>
                            <div class="form-check">
                                <input type="checkbox" name="p_in_list" class="form-check-input" value="1" <?= $perm['p_in_list'] ? 'checked' : '' ?> <?= $is_admin ? 'disabled' : '' ?>>
                                <label class="form-check-label">允许查看入库记录</label>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">出库记录</label>
                            <div class="form-check">
                                <input type="checkbox" name="p_out_list" class="form-check-input" value="1" <?= $perm['p_out_list'] ? 'checked' : '' ?> <?= $is_admin ? 'disabled' : '' ?>>
                                <label class="form-check-label">允许查看出库记录</label>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">导出权限</label>
                            <div class="form-check export-permission">
                                <input type="checkbox" name="p_export" class="form-check-input" value="1" <?= $perm['p_export'] ? 'checked' : '' ?> disabled>
                                <label class="form-check-label">允许导出数据</label>
                                <span class="text-danger">暂时不支持</span>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">搜索权限</label>
                            <div class="form-check">
                                <input type="checkbox" name="p_search" class="form-check-input" value="1" <?= $perm['p_search'] ? 'checked' : '' ?> <?= $is_admin ? 'disabled' : '' ?>>
                                <label class="form-check-label">允许搜索商品</label>
                            </div>
                        </div>
                        
                        <div class="mt-4">
                            <button type="submit" class="btn btn-primary" <?= $is_admin ? 'disabled' : '' ?>>保存权限</button>
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