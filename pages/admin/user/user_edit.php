<?php
session_start();
if (!defined('IN_SYSTEM')) define('IN_SYSTEM', true);
include '../../../config/config.php';
include '../../../config/check_perm.php';
include '../../../config/write_log.php';

// 定义必要的函数
function input($name) {
    return isset($_REQUEST[$name]) ? trim($_REQUEST[$name]) : '';
}

function h($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

// 检查是否是管理员
if ($_SESSION['role'] != 1) {
    header("Location: ../../auth/no_permission.php");
    exit;
}

$id = intval(input('id'));
$stmt = $conn->prepare("SELECT * FROM admin WHERE id=?");
$stmt->bind_param("i", $id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
    echo "<script>alert('用户不存在');location.href='user_list.php';</script>";
    exit;
}

if ($_POST) {
    // 检查是否是当前用户修改自己的密码
    $is_self_edit = $_SESSION['username'] == $row['username'];
    
    // 如果是普通用户修改自己的密码，需要验证旧密码
    if ($is_self_edit && $_SESSION['role'] != 1) {
        $old_password_input = input('old_password');
        if (!password_verify($old_password_input, $row['password'])) {
            echo "<script>alert('旧密码错误');history.back();</script>";
            exit;
        }
    }
    
    $pwd = password_hash(input('password'), PASSWORD_DEFAULT);
    $role = input('role');
    $nickname = input('nickname');
    $stmt = $conn->prepare("UPDATE admin SET password=?,role=?,nickname=? WHERE id=?");
    $stmt->bind_param("sssi", $pwd, $role, $nickname, $id);
    $stmt->execute();
    $stmt->close();
    write_log($conn, $_SESSION['username'], "修改用户：{$row['username']}");
    header("location:user_list.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>捷顺ims - 编辑用户</title>
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
                    toggleBtn.innerHTML = '<svg t="1772298614313" class="icon" viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" p-id="1757"><path d="M111.936 896a48 48 0 1 1 0-96h800a48 48 0 1 1 0 96z m230.4-199.616l-224-143.872a47.936 47.936 0 0 1 0-80.896l224-143.872a46.72 46.72 0 0 1 25.6-7.616 48.704 48.704 0 0 1 22.976 5.824 48.064 48.064 0 0 1 25.024 42.112v288a47.872 47.872 0 0 1-25.024 42.048 48.512 48.512 0 0 1-23.104 5.888 46.464 46.464 0 0 1-25.6-7.68zM232.96 512.128l87.104 56V456z m295.104 143.936a48 48 0 0 1 0-96h352a48 48 0 1 1 0 96z m0-192a48 48 0 0 1 0-96h352a48 48 0 1 1 0 96z m-416-240a48 48 0 1 1 0-96h800a48 48 0 1 1 0 96z" fill="#585858" p-id="1758"></path></svg>';
                }
            }
        </script>

        <!-- 右侧内容 -->
        <div class="content">
            <div class="content-header">
                <h3>修改用户：<?=h($row['username'])?></h3>
            </div>
            
            <div class="card w-50">
                <div class="card-body">
                    <form method="post">
                        <!-- 旧密码字段 -->
                        <?php if ($_SESSION['role'] == 1) { ?>
                            <div class="mb-3">
                                <label class="form-label">旧密码</label>
                                <input type="text" class="form-control" value="无法显示明文密码" disabled>
                                <small class="form-text text-muted">管理员修改用户密码无需验证旧密码</small>
                            </div>
                        <?php } elseif ($_SESSION['username'] == $row['username']) { ?>
                            <div class="mb-3">
                                <label class="form-label">旧密码</label>
                                <input type="password" name="old_password" class="form-control" placeholder="请输入旧密码" required>
                            </div>
                        <?php } ?>
                        
                        <!-- 昵称字段 -->
                        <div class="mb-3">
                            <label class="form-label">昵称</label>
                            <input name="nickname" class="form-control" placeholder="请输入昵称（选填）" value="<?=h($row['nickname'])?>">
                        </div>
                        <!-- 新密码字段 -->
                        <div class="mb-3">
                            <label class="form-label">新密码</label>
                            <input type="password" name="password" class="form-control" placeholder="请输入新密码" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">角色</label>
                            <select name="role" class="form-select">
                                <option value="user" <?=$row['role']=='user'?'selected':''?>>普通用户</option>
                                <option value="admin" <?=$row['role']=='admin'?'selected':''?>>管理员</option>
                            </select>
                        </div>
                        <div>
                            <button class="btn btn-primary">保存</button>
                            <a href="user_list.php" class="btn btn-outline-secondary ml-2">返回</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</body>
</html>