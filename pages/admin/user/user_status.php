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
                mysqli_close($conn);
                return true; // 已安装且连接正常
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

// 检查是否是管理员
if ($_SESSION['role'] != 1) {
    header("Location: ../../auth/no_permission.php");
    exit;
}

// 处理封禁和解封
if (isset($_GET['id']) && isset($_GET['action'])) {
    $id = intval($_GET['id']);
    $action = $_GET['action'];
    
    // 防止操作admin账号
    $user_res = $conn->query("SELECT username FROM admin WHERE id = $id");
    $user = $user_res->fetch_assoc();
    if ($user && $user['username'] == 'admin') {
        header("Location: user_list.php");
        exit;
    }
    
    if ($action == 'ban') {
        // 封禁账号
        $conn->query("UPDATE admin SET status = 0 WHERE id = $id");
    } elseif ($action == 'unban') {
        // 解封账号并重置错误次数
        $conn->query("UPDATE admin SET status = 1, failed_login_count = 0, last_failed_login = NULL WHERE id = $id");
    }
    
    // 记录操作日志
    $username = $_SESSION['username'];
    $action_text = $action == 'ban' ? '封禁' : '解封';
    $conn->query("INSERT INTO `log` (`username`, `content`) VALUES ('$username', '{$action_text}用户ID: $id')");
    
    header("Location: user_list.php");
    exit;
}
?>