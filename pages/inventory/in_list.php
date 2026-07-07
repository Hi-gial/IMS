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
                    $result = mysqli_query($conn, "SHOW COLUMNS FROM stock_in LIKE 'supplier_id'");
                    if (mysqli_num_rows($result) == 0) {
                        mysqli_query($conn, "ALTER TABLE stock_in ADD COLUMN supplier_id INT DEFAULT NULL COMMENT '供货商ID' AFTER operator");
                    }
                    
                    $result = mysqli_query($conn, "SHOW COLUMNS FROM stock_in LIKE 'supplier_name'");
                    if (mysqli_num_rows($result) == 0) {
                        mysqli_query($conn, "ALTER TABLE stock_in ADD COLUMN supplier_name VARCHAR(100) DEFAULT NULL COMMENT '供货商名称' AFTER supplier_id");
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

// 检查查看权限
if ($_SESSION['role'] != 1 && (!isset($perm['p_in_list']) || $perm['p_in_list'] != 1)) {
    header("Location: ../auth/no_permission.php");
    exit;
}

if (isset($_GET['export_csv'])) {
    $search_brand = isset($_GET['search_brand']) ? mysqli_real_escape_string($conn, $_GET['search_brand']) : '';
    $search_model = isset($_GET['search_model']) ? mysqli_real_escape_string($conn, $_GET['search_model']) : '';
    $operator = isset($_GET['operator']) ? mysqli_real_escape_string($conn, $_GET['operator']) : '';
    $supplier_name = isset($_GET['supplier_name']) ? mysqli_real_escape_string($conn, $_GET['supplier_name']) : '';
    $start_date = isset($_GET['start_date']) ? mysqli_real_escape_string($conn, $_GET['start_date']) : '';
    $end_date = isset($_GET['end_date']) ? mysqli_real_escape_string($conn, $_GET['end_date']) : '';

    $where = array();
    if (!empty($search_brand)) {
        $where[] = "p.brand LIKE '%$search_brand%'";
    }
    if (!empty($search_model)) {
        $where[] = "p.model LIKE '%$search_model%'";
    }
    if (!empty($operator)) {
        $where[] = "(a.nickname LIKE '%$operator%' OR si.operator LIKE '%$operator%')";
    }
    if (!empty($supplier_name)) {
        $where[] = "(si.supplier_name LIKE '%$supplier_name%' OR si.purchase_unit LIKE '%$supplier_name%')";
    }
    if (!empty($start_date)) {
        $where[] = "si.ctime >= '$start_date 00:00:00'";
    }
    if (!empty($end_date)) {
        $where[] = "si.ctime <= '$end_date 23:59:59'";
    }

    $where_clause = '';
    if (!empty($where)) {
        $where_clause = "WHERE " . implode(' AND ', $where);
    }

    $sql = "SELECT si.*, p.brand, p.model, a.nickname FROM stock_in si LEFT JOIN product p ON si.pid = p.id LEFT JOIN admin a ON si.operator = a.username $where_clause ORDER BY si.id DESC";
    $res = mysqli_query($conn, $sql);

    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="入库记录_' . date('Ymd') . '.xls"');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');

    echo "\xEF\xBB\xBF";
    echo '<table border="1" cellspacing="0" cellpadding="5">';
    echo '<tr><td colspan="9" style="text-align: center; font-weight: bold; font-size: 16px; padding: 10px;">康保县城乡发展集团有限公司入库记录</td></tr>';
    echo '<thead><tr style="background-color: #f8f9fa; font-weight: bold;">';
    echo '<th>序号</th><th>品牌</th><th>型号</th><th>入库数量</th><th>单位</th><th>供货商</th><th>备注</th><th>操作人</th><th>入库时间</th>';
    echo '</tr></thead>';
    echo '<tbody>';

    $index = 1;
    while ($row = mysqli_fetch_assoc($res)) {
        echo '<tr>';
        echo '<td>' . $index++ . '</td>';
        echo '<td>' . htmlspecialchars($row['brand'] ?: '-') . '</td>';
        echo '<td>' . htmlspecialchars($row['model'] ?: '-') . '</td>';
        echo '<td>' . $row['num'] . '</td>';
        echo '<td>' . htmlspecialchars($row['unit'] ?: '-') . '</td>';
        echo '<td>' . htmlspecialchars($row['supplier_name'] ?: $row['purchase_unit'] ?: '-') . '</td>';
        echo '<td>' . htmlspecialchars($row['remark'] ?: '-') . '</td>';
        echo '<td>' . htmlspecialchars(isset($row['operator']) ? (!empty($row['nickname']) ? $row['nickname'] : $row['operator']) : '系统') . '</td>';
        echo '<td>' . htmlspecialchars($row['ctime']) . '</td>';
        echo '</tr>';
    }

    echo '</tbody></table>';
    exit;
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>捷顺ims - 入库记录</title>
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
            <a href="in_list.php" class="menu-item active">
                <span class="icon">📋</span>
                <span class="text">入库记录</span>
            </a>
            <a href="out.php" class="menu-item">
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
        </script>

        <!-- 右侧内容 -->
        <div class="content">
            <div class="content-header">
                <h3>入库记录</h3>
            </div>
            
            <?php
            $search_brand = isset($_GET['search_brand']) ? mysqli_real_escape_string($conn, $_GET['search_brand']) : '';
            $search_model = isset($_GET['search_model']) ? mysqli_real_escape_string($conn, $_GET['search_model']) : '';
            $operator = isset($_GET['operator']) ? mysqli_real_escape_string($conn, $_GET['operator']) : '';
            $supplier_name = isset($_GET['supplier_name']) ? mysqli_real_escape_string($conn, $_GET['supplier_name']) : '';
            $start_date = isset($_GET['start_date']) ? mysqli_real_escape_string($conn, $_GET['start_date']) : '';
            $end_date = isset($_GET['end_date']) ? mysqli_real_escape_string($conn, $_GET['end_date']) : '';
            
            if ((!empty($search_brand) || !empty($search_model) || !empty($operator) || !empty($supplier_name) || !empty($start_date) || !empty($end_date)) && $_SESSION['role'] != 1 && (!isset($perm['p_search']) || $perm['p_search'] != 1)) {
                echo "<script>alert('无搜索权限');history.back();</script>";
                exit;
            }
            
            $where = array();
            if (!empty($search_brand)) {
                $where[] = "p.brand LIKE '%$search_brand%'";
            }
            if (!empty($search_model)) {
                $where[] = "p.model LIKE '%$search_model%'";
            }
            if (!empty($operator)) {
                $where[] = "(a.nickname LIKE '%$operator%' OR si.operator LIKE '%$operator%')";
            }
            if (!empty($supplier_name)) {
                $where[] = "(si.supplier_name LIKE '%$supplier_name%' OR si.purchase_unit LIKE '%$supplier_name%')";
            }
            if (!empty($start_date)) {
                $where[] = "si.ctime >= '$start_date 00:00:00'";
            }
            if (!empty($end_date)) {
                $where[] = "si.ctime <= '$end_date 23:59:59'";
            }
            
            $where_clause = '';
            if (!empty($where)) {
                $where_clause = "WHERE " . implode(' AND ', $where);
            }
            
            $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
            $page_size = isset($_GET['page_size']) ? intval($_GET['page_size']) : 15;
            echo "<script>
                if (window.URLSearchParams) {
                    var params = new URLSearchParams(window.location.search);
                    if (params.has('page_size')) {
                        localStorage.setItem('page_size', params.get('page_size'));
                    } else {
                        var savedPageSize = localStorage.getItem('page_size');
                        if (savedPageSize) {
                            params.set('page_size', savedPageSize);
                            window.location.href = window.location.pathname + '?' + params.toString();
                        }
                    }
                }
            </script>";
            $offset = ($page - 1) * $page_size;

            $count_sql = "SELECT COUNT(*) FROM stock_in si LEFT JOIN product p ON si.pid = p.id LEFT JOIN admin a ON si.operator = a.username $where_clause";
            $sql = "SELECT si.*, p.brand, p.model, a.nickname FROM stock_in si LEFT JOIN product p ON si.pid = p.id LEFT JOIN admin a ON si.operator = a.username $where_clause ORDER BY si.id DESC LIMIT $offset, $page_size";
            
            // 获取总记录数
            $count_res = mysqli_query($conn, $count_sql);
            $count_row = mysqli_fetch_row($count_res);
            $total = $count_row[0];
            $total_pages = ceil($total / $page_size);
            
            $res = mysqli_query($conn, $sql);
            
            // 增加查询失败的容错
            if (!$res) {
                echo "<div class='mb-3'></div>";
            } else {
                echo "<div class='mb-3'>
                    <div class='d-flex justify-content-end'>
                        <a href='in_list.php?export_csv=1" . (!empty($search_brand) ? '&search_brand=' . urlencode($search_brand) : '') . (!empty($search_model) ? '&search_model=' . urlencode($search_model) : '') . (!empty($operator) ? '&operator=' . urlencode($operator) : '') . (!empty($supplier_name) ? '&supplier_name=' . urlencode($supplier_name) : '') . (!empty($start_date) ? '&start_date=' . urlencode($start_date) : '') . (!empty($end_date) ? '&end_date=' . urlencode($end_date) : '') . "' class='btn btn-success'>导出表格</a>
                    </div>
                </div>
                <div class='mb-3'>
                    <form method='GET' class='search-form' style='display: flex; flex-wrap: wrap; gap: 10px; align-items: end;' onsubmit='clearSearchInputs()'>
                        <input type='text' name='search_brand' class='form-control' placeholder='搜索品牌' id='search-brand-input' style='flex: 1; min-width: 150px;'>
                        <input type='text' name='search_model' class='form-control' placeholder='搜索型号' id='search-model-input' style='flex: 1; min-width: 150px;'>
                        <input type='text' name='operator' class='form-control' placeholder='搜索操作人' id='operator-input' style='flex: 1; min-width: 150px;'>
                        <input type='text' name='supplier_name' class='form-control' placeholder='搜索供货商' id='supplier-name-input' style='flex: 1; min-width: 150px;'>
                        <input type='date' name='start_date' class='form-control' placeholder='开始日期' id='start-date-input' style='flex: 1; min-width: 150px;'>
                        <input type='date' name='end_date' class='form-control' placeholder='结束日期' id='end-date-input' style='flex: 1; min-width: 150px;'>
                        <button type='submit' class='btn btn-primary' style='height: 38px;'>搜索</button>
                        <a href='in_list.php' class='btn btn-secondary' style='height: 38px;'>取消</a>
                    </form>
                    <script>
                        function clearSearchInputs() {
                            setTimeout(function() {
                                document.getElementById('search-brand-input').value = '';
                                document.getElementById('search-model-input').value = '';
                                document.getElementById('operator-input').value = '';
                                document.getElementById('supplier-name-input').value = '';
                                document.getElementById('start-date-input').value = '';
                                document.getElementById('end-date-input').value = '';
                            }, 100);
                        }
                    </script>
                </div>";
            }
            ?>
            
            <table class="table">
                <thead>
                    <tr>
                        <th>序号</th>
                        <th>品牌</th>
                        <th>型号</th>
                        <th>入库数量</th>
                        <th>单位</th>
                        <th>供货商</th>
                        <th>备注</th>
                        <th>操作人</th>
                        <th>入库时间</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if (!$res) {
                        echo "<tr><td colspan='9' style='text-align:center; color:red;'>查询失败：" . mysqli_error($conn) . "</td></tr>";
                    } else {
                        $index = ($page - 1) * $page_size + 1;
                        while ($row = mysqli_fetch_assoc($res)) {
                    ?>
                    <tr>
                        <td><?= $index++ ?></td>
                        <td><?= $row['brand'] ?: '-' ?></td>
                        <td><?= $row['model'] ?: '-' ?></td>
                        <td><?= $row['num'] ?></td>
                        <td><?= $row['unit'] ?: '-' ?></td>
                        <td><?= $row['supplier_name'] ?: $row['purchase_unit'] ?: '-' ?></td>
                        <td><?= $row['remark'] ?: '-' ?></td>
                        <td><?= isset($row['operator']) ? (!empty($row['nickname']) ? $row['nickname'] : $row['operator']) : '系统' ?></td>
                        <td><?= $row['ctime'] ?></td>
                    </tr>
                    <?php 
                        }
                        if (mysqli_num_rows($res) == 0) {
                            echo "<tr><td colspan='9' style='text-align:center;'>暂无入库记录</td></tr>";
                        }
                    }
                    ?>
                </tbody>
            </table>
            
            <!-- 分页导航 -->
            <div class="mt-3">
                <!-- 页码显示靠右 -->
                <div class="d-flex justify-content-end align-items-center">
                    <span>共 <?= $total_pages ?> 页</span>
                    <form method="GET" class="ml-3" style="margin-right: 15px;">
                        <input type="hidden" name="page" value="1">
                        <input type="hidden" name="search_brand" value="<?= urlencode($search_brand) ?>">
                        <input type="hidden" name="search_model" value="<?= urlencode($search_model) ?>">
                        <input type="hidden" name="operator" value="<?= urlencode($operator) ?>">
                        <input type="hidden" name="supplier_name" value="<?= urlencode($supplier_name) ?>">
                        <input type="hidden" name="start_date" value="<?= urlencode($start_date) ?>">
                        <input type="hidden" name="end_date" value="<?= urlencode($end_date) ?>">
                        <select name="page_size" class="page-size-select" onchange="this.form.submit()">
                            <option value="10" <?= $page_size == 10 ? 'selected' : '' ?>>10条/页</option>
                            <option value="15" <?= $page_size == 15 ? 'selected' : '' ?>>15条/页</option>
                            <option value="20" <?= $page_size == 20 ? 'selected' : '' ?>>20条/页</option>
                            <option value="50" <?= $page_size == 50 ? 'selected' : '' ?>>50条/页</option>
                        </select>
                    </form>
                    <nav class="ml-3">
                        <ul class="pagination">
                            <?php if ($page > 1) { ?>
                            <li>
                                <a href="?page=1&search_brand=<?= urlencode($search_brand) ?>&search_model=<?= urlencode($search_model) ?>&operator=<?= urlencode($operator) ?>&supplier_name=<?= urlencode($supplier_name) ?>&start_date=<?= urlencode($start_date) ?>&end_date=<?= urlencode($end_date) ?>&page_size=<?= $page_size ?>">首页</a>
                            </li>
                            <li>
                                <a href="?page=<?= $page - 1 ?>&search_brand=<?= urlencode($search_brand) ?>&search_model=<?= urlencode($search_model) ?>&operator=<?= urlencode($operator) ?>&supplier_name=<?= urlencode($supplier_name) ?>&start_date=<?= urlencode($start_date) ?>&end_date=<?= urlencode($end_date) ?>&page_size=<?= $page_size ?>">
                                    <svg t="1772301714347" class="icon" viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" p-id="4888" width="16" height="16">
                                        <path d="M507.733333 490.666667L768 230.4 704 170.666667 384 490.666667l320 320 59.733333-59.733334-256-260.266666zM341.333333 170.666667H256v640h85.333333V170.666667z" fill="#444444" p-id="4889"></path>
                                    </svg>
                                </a>
                            </li>
                            <?php } ?>
                            
                            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++) { ?>
                            <li>
                                <a href="?page=<?= $i ?>&search_brand=<?= urlencode($search_brand) ?>&search_model=<?= urlencode($search_model) ?>&operator=<?= urlencode($operator) ?>&supplier_name=<?= urlencode($supplier_name) ?>&start_date=<?= urlencode($start_date) ?>&end_date=<?= urlencode($end_date) ?>&page_size=<?= $page_size ?>" class="<?= $i == $page ? 'btn-primary' : '' ?>"><?= $i ?></a>
                            </li>
                            <?php } ?>
                            
                            <?php if ($page < $total_pages) { ?>
                            <li>
                                <a href="?page=<?= $page + 1 ?>&search_brand=<?= urlencode($search_brand) ?>&search_model=<?= urlencode($search_model) ?>&operator=<?= urlencode($operator) ?>&supplier_name=<?= urlencode($supplier_name) ?>&start_date=<?= urlencode($start_date) ?>&end_date=<?= urlencode($end_date) ?>&page_size=<?= $page_size ?>">
                                    <svg t="1772301426562" class="icon" viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" p-id="3903" width="16" height="16">
                                        <path d="M516.266667 490.666667L256 230.4 315.733333 170.666667l320 320L315.733333 810.666667 256 750.933333l260.266667-260.266666zM678.4 170.666667h85.333333v640h-85.333333V170.666667z" fill="#444444" p-id="3904"></path>
                                    </svg>
                                </a>
                            </li>
                            <li>
                                <a href="?page=<?= $total_pages ?>&search_brand=<?= urlencode($search_brand) ?>&search_model=<?= urlencode($search_model) ?>&operator=<?= urlencode($operator) ?>&supplier_name=<?= urlencode($supplier_name) ?>&start_date=<?= urlencode($start_date) ?>&end_date=<?= urlencode($end_date) ?>&page_size=<?= $page_size ?>">末页</a>
                            </li>
                            <?php } ?>
                        </ul>
                    </nav>
                </div>
            </div>
        </div>
    </div>
</body>
</html>