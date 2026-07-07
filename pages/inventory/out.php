<?php
// 禁用错误显示
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);

// 定义系统常量
define('IN_SYSTEM', true);

// 检查是否已安装
function check_installation() {
    // 检查安装锁定文件是否存在
    if (file_exists('../../config/install.lock.php')) {
        // 尝试读取数据库配置并测试连接
        $install_lock = '../../config/install.lock.php';
        $lines = file($install_lock, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $config = [];
        
        foreach ($lines as $line) {
            // 跳过PHP exit行
            if (strpos($line, '<?php exit; ?>') !== false) {
                continue;
            }
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $config[trim($key)] = trim($value);
            }
        }
        
        // 检查是否有完整的数据库配置
        if (isset($config['db_host'], $config['db_user'], $config['db_pwd'], $config['db_name'])) {
            // 测试数据库连接
            $conn = mysqli_connect($config['db_host'], $config['db_user'], $config['db_pwd'], $config['db_name']);
            if ($conn) {
                // 检查必要的数据库表是否存在
                $required_tables = [
                    'product',
                    'stock_in',
                    'stock_out',
                    'admin',
                    'user_perm',
                    'log'
                ];

                $tables_exist = true;
                foreach ($required_tables as $table) {
                    $result = mysqli_query($conn, "SHOW TABLES LIKE '$table'");
                    if (mysqli_num_rows($result) == 0) {
                        $tables_exist = false;
                        break;
                    }
                }

                if ($tables_exist) {
                    $result = mysqli_query($conn, "SHOW COLUMNS FROM stock_out LIKE 'unit'");
                    if (mysqli_num_rows($result) == 0) {
                        mysqli_query($conn, "ALTER TABLE stock_out ADD COLUMN unit VARCHAR(20) DEFAULT NULL COMMENT '单位' AFTER num");
                    }

                    $result = mysqli_query($conn, "SHOW COLUMNS FROM stock_out LIKE 'receive_unit_id'");
                    if (mysqli_num_rows($result) == 0) {
                        mysqli_query($conn, "ALTER TABLE stock_out ADD COLUMN receive_unit_id INT DEFAULT NULL COMMENT '领取单位ID' AFTER unit");
                    }

                    $result = mysqli_query($conn, "SHOW COLUMNS FROM stock_out LIKE 'receive_unit_name'");
                    if (mysqli_num_rows($result) == 0) {
                        mysqli_query($conn, "ALTER TABLE stock_out ADD COLUMN receive_unit_name VARCHAR(100) DEFAULT NULL COMMENT '领取单位名称' AFTER receive_unit_id");
                    }
                    
                    $result = mysqli_query($conn, "SHOW COLUMNS FROM product LIKE 'brand'");
                    if (mysqli_num_rows($result) == 0) {
                        mysqli_query($conn, "ALTER TABLE product ADD COLUMN brand VARCHAR(50) DEFAULT NULL COMMENT '品牌' AFTER pname");
                    }
                    
                    $result = mysqli_query($conn, "SHOW COLUMNS FROM product LIKE 'model'");
                    if (mysqli_num_rows($result) == 0) {
                        mysqli_query($conn, "ALTER TABLE product ADD COLUMN model VARCHAR(50) DEFAULT NULL COMMENT '型号' AFTER brand");
                    }
                }

                mysqli_close($conn);
                
                if ($tables_exist) {
                    return true; // 已安装且连接正常
                } else {
                    // 检测服务器系统
                    $os = PHP_OS;
                    $config_path = '../../config/config.php';
                    $lock_path = '../../config/install.lock';
                    
                    // 取消只读权限，设为所有人可读写
                    if (strtoupper(substr($os, 0, 3)) === 'WIN') {
                        // Windows系统
                        $cmd1 = "icacls \"$config_path\" /inheritance:r /grant:r Everyone:F";
                        $cmd2 = "icacls \"$lock_path\" /inheritance:r /grant:r Everyone:F";
                        @exec($cmd1);
                        @exec($cmd2);
                    } else {
                        // Linux/Unix系统
                        @chmod($config_path, 0666);
                        @chmod($lock_path, 0666);
                    }
                    
                    // 数据库表不存在，提示重新安装
                    die("数据库表不存在或已被删除。<br><br>config.php和install.lock文件已设为可读写权限，如需重新安装系统，请删除 `config/install.lock` 文件后重新运行安装程序。<br><br>⚠️ 注意：重新安装会清空数据，重要数据请先通过 phpMyAdmin 等工具备份。<br><br>🔒 安全提醒：若非您手动删除，建议检查服务器是否存在安全风险。");
                }
            } else {
                // 数据库连接失败，提示重新安装
                die("数据库连接失败：" . mysqli_connect_error() . "<br><br>请删除 config/install.lock 文件并重新运行安装程序。<br><br>注意：重新安装可能会导致数据丢失，请谨慎操作。<br><br>如果数据重要，可通过 phpMyAdmin 等工具进行手动备份剩余数据库数据之后再重新安装程序。");
            }
        }
    }
    return false; // 未安装
}

// 检查安装状态
if (!check_installation()) {
    header("Location: ../../install/install.php");
    exit;
}

session_start();
if (!isset($_SESSION['username'])) {
    header("Location: ../auth/login.php");
    exit;
}
include '../../config/config.php';
include '../../config/check_perm.php';

// 检查出库权限
if ($_SESSION['role'] != 1 && (!isset($perm['p_out']) || $perm['p_out'] != 1)) {
    header("Location: ../auth/no_permission.php");
    exit;
}

if ($_POST) {
    $product_id = intval($_POST['product_id']);
    $num = intval($_POST['num']);
    $unit = isset($_POST['unit']) ? trim($_POST['unit']) : '';
    $receive_unit_id = intval($_POST['receive_unit_id']);
    if ($receive_unit_id <= 0) {
        $receive_unit_id = NULL;
    }
    $remark = isset($_POST['remark']) ? trim($_POST['remark']) : '';
    $user = $_SESSION['username'];

    $receive_unit_name = '';
    if (!is_null($receive_unit_id)) {
        $stmt = mysqli_prepare($conn, "SELECT name FROM receive_unit WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "i", $receive_unit_id);
        mysqli_stmt_execute($stmt);
        $ru_res = mysqli_stmt_get_result($stmt);
        if ($ru = mysqli_fetch_assoc($ru_res)) {
            $receive_unit_name = $ru['name'];
        }
    }

    if ($product_id <= 0 || $num <= 0) {
        echo "<script>alert('请输入有效的出库数量');history.back();</script>";
        exit;
    }

    if (empty($unit)) {
        echo "<script>alert('请选择出库单位');history.back();</script>";
        exit;
    }

    // 检查商品是否存在并获取库存
    $product_res = mysqli_query($conn, "SELECT * FROM product WHERE id = $product_id");
    $product = mysqli_fetch_assoc($product_res);

    if (!$product) {
        echo "<script>alert('该商品不存在');history.back();</script>";
        exit;
    }

    // 检查库存是否足够
    // if ($product['stock'] < $num) {
    //     echo "<script>alert('库存不足，当前库存：{$product['stock']}');history.back();</script>";
    //     exit;
    // }

    // 写入出库记录
    $unit_esc = mysqli_real_escape_string($conn, $unit);
    $remark_esc = mysqli_real_escape_string($conn, $remark);
    $ru_id_sql = is_null($receive_unit_id) ? "NULL" : "'$receive_unit_id'";
    $ru_name_esc = mysqli_real_escape_string($conn, $receive_unit_name);
    $sql = "INSERT INTO stock_out (pid, num, unit, receive_unit_id, receive_unit_name, operator, remark) VALUES ('$product_id', '$num', '$unit_esc', $ru_id_sql, '$ru_name_esc', '$user', '$remark_esc')";
    mysqli_query($conn, $sql);

    // 更新库存（扣减）
    mysqli_query($conn, "UPDATE product SET stock = stock - $num WHERE id = $product_id");

    // 记录操作日志
    $log_content = "用户 {$user} 出库商品：{$product['brand']}-{$product['model']}，数量：{$num}{$unit}";
    mysqli_query($conn, "INSERT INTO log (username, content) VALUES ('$user', '$log_content')");

    echo "<script>alert('出库成功');location.href='out_list.php';</script>";
    exit;
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>捷顺ims - 商品出库</title>
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
            <a href="in.php" class="menu-item">
                <span class="icon">📥</span>
                <span class="text">商品入库</span>
            </a>
            <a href="in_list.php" class="menu-item">
                <span class="icon">📋</span>
                <span class="text">入库记录</span>
            </a>
            <a href="out.php" class="menu-item active">
                <span class="icon">📤</span>
                <span class="text">商品出库</span>
            </a>
            <a href="out_list.php" class="menu-item">
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
                <a href="../admin/backup.php" class="menu-item">
                    <span class="icon">💾</span>
                    <span class="text">数据备份</span>
                </a>
            <?php } ?>
            
            <div class="user-info">
                <?php if ($_SESSION['role'] == 1) { ?>
                    <a href="../admin/user/user_edit.php?id=<?= $_SESSION['role'] ?>" style="color: #bdc3c7; font-size: 14px; display: block; margin-bottom: 10px; text-align: center;">修改密码</a>
                <?php } else { ?>
                    <a href="../auth/change_password.php" style="color: #bdc3c7; font-size: 14px; display: block; margin-bottom: 10px; text-align: center;">修改密码</a>
                <?php } ?>
                <a href="../auth/logout.php" style="color: #bdc3c7; font-size: 14px; text-align: center; display: block;">退出登录</a>
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
                    svg.innerHTML = '<path d="M111.936 896a48 48 0 1 1 0-96h800a48 48 0 1 1 0 96z m230.4-199.616l-224-143.872a47.936 47.936 0 0 1 0-80.896l224-143.872a46.72 46.72 0 0 1 25.6-7.616 48.704 48.704 0 0 1 22.976 5.824 48.064 48.064 0 0 1 25.024 42.112v288a47.872 47.872 0 0 1-25.024 42.048 48.512 48.512 0 0 1-23.104 5.888 46.464 46.464 0 0 1-25.6-7.68zM232.96 512.128l87.104 56V456z m295.104 143.936a48 48 0 0 1 0-96h352a48 48 0 1 1 0 96z m0-192a48 48 0 0 1 0-96h352a48 48 0 1 1 0 96z m-416-240a48 48 0 1 1 0-96h800a48 48 0 0 1 0 96z" fill="#585858" p-id="1758"></path>';
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
            
            function loadModels() {
                const brand = document.getElementById('brand-select').value;
                const modelSelect = document.getElementById('model-select');
                
                modelSelect.innerHTML = '<option value="">-- 加载中 --</option>';
                
                if (!brand) {
                    modelSelect.innerHTML = '<option value="">-- 请先选择品牌 --</option>';
                    return;
                }
                
                const xhr = new XMLHttpRequest();
                xhr.open('GET', '../products/get_models.php?brand=' + encodeURIComponent(brand), true);
                xhr.onreadystatechange = function() {
                    if (xhr.readyState === 4 && xhr.status === 200) {
                        modelSelect.innerHTML = xhr.responseText;
                    }
                };
                xhr.send();
            }
        </script>

        <!-- 右侧内容 -->
        <div class="content">
            <div class="content-header">
                <h3>商品出库</h3>
            </div>
            
            <div class="card w-50">
                <form method="post">
                    <div class="mb-3" style="display: flex; gap: 15px;">
                        <div style="flex: 1;">
                            <label>选择品牌</label>
                            <select name="brand" id="brand-select" class="form-select" onchange="loadModels()">
                                <option value="">-- 请选择品牌 --</option>
                                <?php
                                $brand_res = mysqli_query($conn, "SELECT DISTINCT brand FROM product WHERE brand IS NOT NULL AND brand != '' ORDER BY brand");
                                while ($brand_row = mysqli_fetch_assoc($brand_res)) {
                                    echo "<option value='{$brand_row['brand']}'>{$brand_row['brand']}</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div style="flex: 1;">
                            <label>选择型号</label>
                            <select name="product_id" id="model-select" class="form-select" required>
                                <option value="">-- 请先选择品牌 --</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label>出库数量</label>
                        <input type="number" min="1" name="num" class="form-control" placeholder="请输入出库数量" required>
                    </div>
                    <div class="mb-3">
                        <label>单位 <span style="color:red">*</span></label>
                        <input type="text" name="unit" class="form-control" placeholder="请输入单位，如：个、件、箱、kg等" required>
                    </div>
                    <div class="mb-3">
                        <label>领取单位</label>
                        <select name="receive_unit_id" class="form-select">
                            <option value="0">-- 请选择领取单位 --</option>
                            <?php
                            $ru_res = mysqli_query($conn, "SELECT * FROM receive_unit ORDER BY name");
                            while ($ru = mysqli_fetch_assoc($ru_res)) {
                                echo "<option value='{$ru['id']}'>{$ru['name']}</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label>备注</label>
                        <textarea name="remark" class="form-control" rows="3" placeholder="请输入备注信息（选填）"></textarea>
                    </div>
                    <div>
                        <button class="btn btn-primary">确认出库</button>
                        <a href="out_list.php" class="btn btn-warning" style="margin-left: 10px;">查看出库记录</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>