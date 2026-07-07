<?php
// 禁用错误显示
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);

// 检查是否已安装
function check_installation() {
    // 检查安装锁定文件是否存在
    if (file_exists('../../../config/install.lock.php')) {
        // 尝试读取数据库配置并测试连接
        $install_lock = '../../../config/install.lock.php';
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
                
                mysqli_close($conn);
                
                if ($tables_exist) {
                    return true; // 已安装且连接正常
                } else {
                    // 检测服务器系统
                    $os = PHP_OS;
                    $config_path = '../../../config/config.php';
                    $lock_path = '../../../config/install.lock';
                    
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
                    die("数据库表不存在或已被删除。<br><br><br>config.php和install.lock文件已设为可读写权限，如需重新安装系统，请删除 `config/install.lock` 文件后重新运行安装程序。<br><br>⚠️ 注意：重新安装会清空数据，重要数据请先通过 phpMyAdmin 等工具备份。<br><br>🔒 安全提醒：若非您手动删除，建议检查服务器是否存在安全风险。");
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
    header("Location: ../../../install/install.php");
    exit;
}

session_start();
if (!defined('IN_SYSTEM')) define('IN_SYSTEM', true);
include '../../../config/config.php';
include '../../../config/check_perm.php';

// 定义 h() 函数用于 HTML 转义
function h($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

// 检查是否是管理员
if ($_SESSION['role'] != 1) {
    header("Location: ../../auth/no_permission.php");
    exit;
}

// 分页功能
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

// 获取总记录数
$count_res = $conn->query("SELECT COUNT(*) FROM admin");
$count_row = $count_res->fetch_row();
$total = $count_row[0];
$total_pages = ceil($total / $page_size);

$res = $conn->query("SELECT * FROM admin ORDER BY id DESC LIMIT $offset, $page_size");
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>捷顺ims - 用户管理</title>
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
                const content = document.querySelector('.content');

                // 从localStorage加载侧边栏状态
                const sidebarCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
                if (sidebarCollapsed) {
                    sidebar.classList.add('collapsed');
                    content.classList.add('sidebar-collapsed');
                    welcomeInfo.style.display = 'none';
                    menuItems.forEach(item => {
                        item.style.textAlign = 'center';
                    });
                    // 切换按钮图标
                    toggleBtn.innerHTML = '<svg t="1772301296890" class="icon" viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" p-id="2029"><path d="M112.064 896a48 48 0 1 1 0-96h800a48 48 0 0 1 0 96z m9.024-197.888a48.064 48.064 0 0 1-25.216-42.048v-288a48.128 48.128 0 0 1 74.112-40.32l224 143.872a48.192 48.192 0 0 1 0 80.896l-224 143.872a48.512 48.512 0 0 1-25.92 7.616 47.552 47.552 0 0 1-22.976-5.952zM192 568.128l87.168-56L192 456z m335.936 87.936a48 48 0 0 1 0-96h352a48 48 0 1 1 0 96z m0-192a48 48 0 0 1 0-96h352a48 48 0 1 1 0 96z m-416-240a48 48 0 1 1 0-96h800a48 48 0 0 1 0 96z" fill="#585858" p-id="2030"></path></svg>';
                }
            });

            function toggleSidebar() {
                const sidebar = document.getElementById('sidebar');
                const toggleBtn = document.querySelector('.toggle-btn');
                const welcomeInfo = document.querySelector('.welcome-info');
                const menuItems = document.querySelectorAll('.menu-item');
                const content = document.querySelector('.content');
                
                sidebar.classList.toggle('collapsed');
                content.classList.toggle('sidebar-collapsed');
                const isCollapsed = sidebar.classList.contains('collapsed');
                
                // 保存状态到localStorage
                localStorage.setItem('sidebarCollapsed', isCollapsed);
                
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
                <h3>用户管理</h3>
            </div>
            
            <div class="d-flex justify-content-end align-items-center mb-3">
                <div>
                    <a href="user_add.php" class="btn btn-success" style="padding: 8px 16px; font-size: 14px; border-radius: 4px; background: #28a745; border: none; color: white; cursor: pointer; transition: background 0.3s;">
                        <i class="fa fa-plus"></i> 添加用户
                    </a>
                </div>
            </div>

            <table class="table">
                <thead>
                    <tr>
                        <th>序号</th>
                        <th>账号</th>
                        <th>昵称</th>
                        <th>角色</th>
                        <th>状态</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $index = ($page - 1) * $page_size + 1; while($r = $res->fetch_assoc()){ ?>
                    <tr>
                        <td><?= $index++ ?></td>
                        <td><?=h($r['username'])?></td>
                        <td><?=h($r['nickname'])?> <?= empty($r['nickname']) ? '<span style="color:#999;">(未设置)</span>' : '' ?></td>
                        <td><?=$r['role']=='admin'?'管理员':'普通用户'?></td>
                        <td><?=$r['status']?'正常':'禁用'?></td>
                        <td>
                            <a href="user_edit.php?id=<?=h($r['id'])?>" class="btn btn-sm btn-primary">修改</a>
                            <?php if($r['role'] == 'admin') { ?>
                            <span class="btn btn-sm btn-warning" style="opacity: 0.6; cursor: not-allowed;">权限</span>
                            <?php } else { ?>
                            <a href="perm_set.php?user=<?=h($r['username'])?>" class="btn btn-sm btn-warning">权限</a>
                            <?php } ?>
                            <?php if($r['username']!='admin'){ ?>
                                <?php if($r['status'] == 1) { ?>
                                <a href="user_status.php?id=<?=h($r['id'])?>&action=ban" class="btn btn-sm btn-danger" onclick="return confirm('确定封禁该用户？')">封禁</a>
                                <?php } else { ?>
                                <a href="user_status.php?id=<?=h($r['id'])?>&action=unban" class="btn btn-sm btn-success" onclick="return confirm('确定解封该用户？')">解封</a>
                                <?php } ?>
                            <a href="user_del.php?id=<?=h($r['id'])?>" class="btn btn-sm btn-danger" onclick="return confirm('确定删除？')">删除</a>
                            <?php } ?>
                        </td>
                    </tr>
                    <?php } ?>
                </tbody>
            </table>
            
            <!-- 分页导航 -->
            <div class="mt-3">
                <!-- 页码显示靠右 -->
                <div class="d-flex justify-content-end align-items-center">
                    <span>共 <?= $total_pages ?> 页</span>
                    <form method="GET" class="ml-3" style="margin-right: 15px;">
                        <input type="hidden" name="page" value="1">
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
                                <a href="?page=1&page_size=<?= $page_size ?>">首页</a>
                            </li>
                            <li>
                                <a href="?page=<?= $page - 1 ?>&page_size=<?= $page_size ?>">
                                    <svg t="1772301714347" class="icon" viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" p-id="4888" width="16" height="16">
                                        <path d="M507.733333 490.666667L768 230.4 704 170.666667 384 490.666667l320 320 59.733333-59.733334-256-260.266666zM341.333333 170.666667H256v640h85.333333V170.666667z" fill="#444444" p-id="4889"></path>
                                    </svg>
                                </a>
                            </li>
                            <?php } ?>
                            
                            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++) { ?>
                            <li>
                                <a href="?page=<?= $i ?>&page_size=<?= $page_size ?>" class="<?= $i == $page ? 'btn-primary' : '' ?>"><?= $i ?></a>
                            </li>
                            <?php } ?>
                            
                            <?php if ($page < $total_pages) { ?>
                            <li>
                                <a href="?page=<?= $page + 1 ?>&page_size=<?= $page_size ?>">
                                    <svg t="1772301426562" class="icon" viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" p-id="3903" width="16" height="16">
                                        <path d="M516.266667 490.666667L256 230.4 315.733333 170.666667l320 320L315.733333 810.666667 256 750.933333l260.266667-260.266666zM678.4 170.666667h85.333333v640h-85.333333V170.666667z" fill="#444444" p-id="3904"></path>
                                    </svg>
                                </a>
                            </li>
                            <li>
                                <a href="?page=<?= $total_pages ?>&page_size=<?= $page_size ?>">末页</a>
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