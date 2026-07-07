<?php
session_start();
if (!defined('IN_SYSTEM')) define('IN_SYSTEM', true);
include '../../config/config.php';

// 检查登录状态和权限
if (!isset($_SESSION['username']) || $_SESSION['role'] != 1) {
    header("Location: ../auth/login.php");
    exit;
}

// 定义缺失的函数
function input($name) {
    return isset($_POST[$name]) ? trim($_POST[$name]) : '';
}

function h($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

include '../../config/write_log.php';

if ($_POST) {
    $brand = input('brand');
    $model = input('model');
    $category = input('category');
    $price = input('price');
    $cost_price = input('cost_price');
    $warn_stock = intval(input('warn_stock'));

    $stmt = $conn->prepare("INSERT INTO product(brand,model,category,price,cost_price,warn_stock) VALUES(?,?,?,?,?,?)");
    $stmt->bind_param("sssddi", $brand, $model, $category, $price, $cost_price, $warn_stock);
    $stmt->execute();
    $stmt->close();

    write_log($conn, $_SESSION['username'], "添加商品：{$brand}-{$model}");
    header("location:product_list.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>捷顺ims - 添加商品</title>
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
                欢迎：<?= $_SESSION['real_name'] ?>（管理员）
            </div>
            <a href="../dashboard/index.php" class="menu-item">
                <span class="icon">🏠</span>
                <span class="text">首页</span>
            </a>
            <a href="product_list.php" class="menu-item">
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
            <a href="product_add.php" class="menu-item active">
                <span class="icon">➕</span>
                <span class="text">添加商品</span>
            </a>
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
            
            <div class="user-info">
                <?php if ($_SESSION['role'] == 1) { ?>
                    <a href="../admin/user/user_edit.php?id=<?= $_SESSION['role'] ?>" style="color: #bdc3c7; font-size: 14px; display: block; margin-bottom: 10px; text-align: center;">修改密码</a>
                <?php } else { ?>
                    <a href="../auth/change_password.php" style="color: #bdc3c7; font-size: 14px; display: block; margin-bottom: 10px; text-align: center;">修改密码</a>
                <?php } ?>
                <a href="../auth/logout.php" style="color: #bdc3c7; font-size: 14px; text-align: center; display: block;">退出登录</a>
            </div>
        </div>

        <!-- 右侧内容 -->
        <div class="content">
            <div class="content-header">
                <h3>新增商品</h3>
            </div>
            <div class="card">
                <div class="card-body">
                    <!-- 错误提示 -->
                    <?php if (isset($error)) { ?>
                        <div style="color: #e74c3c; margin-bottom: 15px; padding: 10px; background: #fef0f0; border-radius: 4px;">
                            <?= $error ?>
                        </div>
                    <?php } ?>
                    <form method="post">
                        <div class="mb-3">
                            <label>品牌</label>
                            <input name="brand" class="form-control" placeholder="请输入品牌">
                        </div>
                        <div class="mb-3">
                            <label>型号</label>
                            <input name="model" class="form-control" placeholder="请输入型号">
                        </div>
                        <div class="mb-3">
                            <label>分类</label>
                            <input name="category" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label>进价 <span style="color:#999; font-size:12px;">（采购成本）</span></label>
                            <input name="cost_price" type="number" step="0.01" min="0" class="form-control" placeholder="0.00" value="0.00">
                        </div>
                        <div class="mb-3">
                            <label>单价 <span style="color:#999; font-size:12px;">（销售价格）</span></label>
                            <input name="price" type="number" step="0.01" min="0" class="form-control" placeholder="0.00">
                        </div>
                        <div class="mb-3">
                            <label>库存预警值</label>
                            <input name="warn_stock" type="number" class="form-control" value="5">
                        </div>
                        <button class="btn btn-primary">保存商品</button>
                        <a href="product_list.php" class="btn btn-outline-secondary ml-2">返回</a>
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
                const content = document.querySelector('.content');
                
                // 从localStorage获取状态
                const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
                
                if (isCollapsed) {
                    sidebar.classList.add('collapsed');
                    content.classList.add('sidebar-collapsed');
                    // 折叠状态下的图标
                svg.innerHTML = '<path d="M112.064 896a48 48 0 1 1 0-96h800a48 48 0 0 1 0 96z m9.024-197.888a48.064 48.064 0 0 1-25.216-42.048v-288a48.128 48.128 0 0 1 74.112-40.32l224 143.872a48.192 48.192 0 0 1 0 80.896l-224 143.872a48.512 48.512 0 0 1-25.92 7.616 47.552 47.552 0 0 1-22.976-5.952zM192 568.128l87.168-56L192 456z m335.936 87.936a48 48 0 0 1 0-96h352a48 48 0 1 1 0 96z m0-192a48 48 0 0 1 0-96h352a48 48 0 1 1 0 96z m-416-240a48 48 0 1 1 0-96h800a48 48 0 0 1 0 96z" fill="#585858" p-id="2030"></path>';
                svg.setAttribute('t', '1772301296890');
                svg.setAttribute('p-id', '2029');
            } else {
                sidebar.classList.remove('collapsed');
                content.classList.remove('sidebar-collapsed');
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
            const content = document.querySelector('.content');
            
            sidebar.classList.toggle('collapsed');
            content.classList.toggle('sidebar-collapsed');
            
            // 保存状态到localStorage
            const isCollapsed = sidebar.classList.contains('collapsed');
            localStorage.setItem('sidebarCollapsed', isCollapsed);
            
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