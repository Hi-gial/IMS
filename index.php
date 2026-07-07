<?php
// 禁用错误显示
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);

// 根目录 index.php

// 检查是否已安装
function check_installation() {
    // 检查安装锁定文件是否存在
    if (file_exists('config/install.lock.php')) {
        // 尝试读取数据库配置并测试连接
        $install_lock = 'config/install.lock.php';
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
                    $config_path = 'config/config.php';
                    $lock_path = 'config/install.lock';
                    
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
if (check_installation()) {
    // 已安装，跳转到首页
    header("Location: pages/dashboard/index.php");
} else {
    // 未安装，跳转到安装页面
    header("Location: install/install.php");
}
exit;
?>