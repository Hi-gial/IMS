<?php
// 禁用错误显示
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);

// 安装文件

// 检查是否已安装
function check_installation()
{
    // 检查安装锁定文件是否存在
    if (file_exists('../config/install.lock.php')) {
        // 尝试读取数据库配置并测试连接
        $install_lock = '../config/install.lock.php';
        $lines = file($install_lock, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $config = [];

        foreach ($lines as $line) {
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
                    'log',
                    'receive_unit'
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
                    $config_path = '../config/config.php';
                    $lock_path = '../config/install.lock.php';

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
                    echo '<!DOCTYPE html>';
                    echo '<html lang="zh-CN">';
                    echo '<head>';
                    echo '<meta charset="UTF-8">';
                    echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
                    echo '<title>捷顺ims - 安装页面</title>';
                    echo '<style>';
                    echo '    body { font-family: Arial, sans-serif; background-color: #f5f5f5; color: #333; }';
                    echo '    .container { max-width: 600px; margin: 100px auto; padding: 30px; background-color: white; border-radius: 8px; box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1); text-align: center; }';
                    echo '    h1 { color: #333; margin-bottom: 20px; }';
                    echo '    .error { background-color: #f2dede; color: #a94442; padding: 15px; border-radius: 6px; margin-bottom: 30px; border-left: 4px solid #a94442; }';
                    echo '    .btn { display: inline-block; padding: 10px 20px; background-color: #4CAF50; color: white; text-decoration: none; border-radius: 4px; font-weight: bold; transition: background-color 0.3s ease; }';
                    echo '    .btn:hover { background-color: #45a049; }';
                    echo '</style>';
                    echo '</head>';
                    echo '<body>';
                    echo '<div class="container">';
                    echo '<h1>数据库表不存在</h1>';
                    echo '<div class="error">';
                    echo '    <p>数据库表不存在或已被删除。</p>';
                    echo '    <br>';
                    echo '    <p>config.php和install.lock.php文件已设为可读写权限，如需重新安装系统，请删除 `config/install.lock.php` 文件后重新运行安装程序。</p>';
                    echo '    <br>';
                    echo '    <p>⚠️ 注意：重新安装会清空数据，重要数据请先通过 phpMyAdmin 等工具备份。</p>';
                    echo '    <br>';
                    echo '    <p>🔒 安全提醒：若非您手动删除，建议检查服务器是否存在安全风险。</p>';
                    echo '</div>';
                    echo '<a href="install.php" class="btn">重新安装</a>';
                    echo '</div>';
                    echo '</body>';
                    echo '</html>';
                    exit;
                }
            } else {
                // 数据库连接失败，提示重新安装
                echo '<!DOCTYPE html>';
                echo '<html lang="zh-CN">';
                echo '<head>';
                echo '<meta charset="UTF-8">';
                echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
                echo '<title>捷顺ims - 安装页面</title>';
                echo '<style>';
                echo '    body { font-family: Arial, sans-serif; background-color: #f5f5f5; color: #333; }';
                echo '    .container { max-width: 600px; margin: 100px auto; padding: 30px; background-color: white; border-radius: 8px; box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1); text-align: center; }';
                echo '    h1 { color: #333; margin-bottom: 20px; }';
                echo '    .error { background-color: #f2dede; color: #a94442; padding: 15px; border-radius: 6px; margin-bottom: 30px; border-left: 4px solid #a94442; }';
                echo '    .btn { display: inline-block; padding: 10px 20px; background-color: #4CAF50; color: white; text-decoration: none; border-radius: 4px; font-weight: bold; transition: background-color 0.3s ease; }';
                echo '    .btn:hover { background-color: #45a049; }';
                echo '</style>';
                echo '</head>';
                echo '<body>';
                echo '<div class="container">';
                echo '<h1>数据库连接失败</h1>';
                echo '<div class="error">';
                echo '    <p>数据库连接失败：' . mysqli_connect_error() . '</p>';
                echo '    <p>请删除 config/install.lock.php 文件并重新运行安装程序。</p>';
                echo '    <p>注意：重新安装可能会导致数据丢失，请谨慎操作。</p>';
                echo '    <p>如果数据重要，可通过 phpMyAdmin 等工具进行手动备份剩余数据库数据之后再重新安装程序。</p>';
                echo '</div>';
                echo '<a href="install.php" class="btn">重新安装</a>';
                echo '</div>';
                echo '</body>';
                echo '</html>';
                exit;
            }
        }
    }
    return false; // 未安装
}

// 检查安装状态
if (check_installation()) {
    echo '<!DOCTYPE html>';
    echo '<html lang="zh-CN">';
    echo '<head>';
    echo '<meta charset="UTF-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
    echo '<title>捷顺ims - 安装页面</title>';
    echo '<style>';
    echo '    body { font-family: Arial, sans-serif; background-color: #f5f5f5; color: #333; }';
    echo '    .container { max-width: 600px; margin: 100px auto; padding: 30px; background-color: white; border-radius: 8px; box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1); text-align: center; }';
    echo '    h1 { color: #333; margin-bottom: 20px; }';
    echo '    .info { background-color: #d9edf7; color: #31708f; padding: 15px; border-radius: 6px; margin-bottom: 30px; border-left: 4px solid #31708f; }';
    echo '    .btn { display: inline-block; padding: 10px 20px; background-color: #4CAF50; color: white; text-decoration: none; border-radius: 4px; font-weight: bold; transition: background-color 0.3s ease; }';
    echo '    .btn:hover { background-color: #45a049; }';
    echo '</style>';
    echo '</head>';
    echo '<body>';
    echo '<div class="container">';
    echo '<h1>系统已安装</h1>';
    echo '<div class="info">';
    echo '    <p>捷顺ims已经安装成功！</p>';
    echo '    <p>您可以直接访问系统主页，无需重新安装。</p>';
    echo '</div>';
    echo '<a href="../pages/dashboard/index.php" class="btn">返回主页</a>';
    echo '</div>';
    echo '</body>';
    echo '</html>';
    exit;
}

ob_start();
?>
<!DOCTYPE html>
<html lang="zh-CN">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>捷顺ims - 安装页面</title>
    <link rel="icon" href="../assets/img/b5afc3a4b86e1.png" type="image/png">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            background-color: #f5f5f5;
            color: #333;
        }

        .container {
            max-width: 800px;
            margin: 50px auto;
            padding: 30px;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        h1 {
            text-align: center;
            color: #333;
            margin-bottom: 30px;
            font-size: 24px;
        }

        .step {
            margin-bottom: 30px;
        }

        .step-title {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 20px;
            color: #555;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
        }

        .form-group {
            margin-bottom: 20px;
            transition: all 0.3s ease;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #555;
        }

        .form-control {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 16px;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: #4CAF50;
            box-shadow: 0 0 0 3px rgba(76, 175, 80, 0.1);
        }

        .form-text {
            display: block;
            margin-top: 5px;
            font-size: 14px;
            color: #777;
        }

        .radio-group {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .radio-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .radio-item input[type="radio"] {
            width: auto;
            margin: 0;
        }

        .radio-item label {
            font-weight: normal;
            margin: 0;
            cursor: pointer;
        }

        .form-actions {
            margin-top: 30px;
            text-align: center;
        }

        .btn-submit {
            background-color: #4CAF50;
            color: white;
            padding: 14px 30px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 16px;
            font-weight: bold;
            transition: background-color 0.3s ease, transform 0.1s ease;
        }

        .btn-submit:hover {
            background-color: #45a049;
            transform: translateY(-2px);
        }

        .btn-submit:active {
            transform: translateY(0);
        }

        .success {
            color: #4CAF50;
            background-color: #dff0d8;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
            border-left: 4px solid #4CAF50;
        }

        .error {
            color: #f44336;
            background-color: #f2dede;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
            border-left: 4px solid #f44336;
        }

        .info {
            background-color: #d9edf7;
            color: #31708f;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
            border-left: 4px solid #31708f;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        table,
        th,
        td {
            border: 1px solid #ddd;
        }

        th,
        td {
            padding: 10px;
            text-align: left;
        }

        th {
            background-color: #f2f2f2;
            font-weight: bold;
        }

        #root_user_group,
        #root_pwd_group {
            opacity: 0;
            transition: opacity 0.3s ease;
        }
    </style>
</head>

<body>
    <div class="container">
        <h1>捷顺ims安装</h1>

        <?php
        // 步骤1：检查环境
        if (!isset($_POST['step'])) {
            echo '<div class="step">';
            echo '<div class="step-title">步骤1：环境检查</div>';

            // 检查PHP版本
            $php_version = phpversion();
            $php_ok = version_compare($php_version, '5.6.0', '>=');

            // 检查MySQL扩展
            $mysql_ok = extension_loaded('mysqli');

            // 检查目录权限
            $config_writable = is_writable('../config');

            echo '<table>';
            echo '<tr><th>项目</th><th>状态</th><th>说明</th></tr>';
            echo '<tr><td>PHP版本</td><td>' . ($php_ok ? '<span style="color: green;">通过</span>' : '<span style="color: red;">不通过</span>') . '</td><td>需要PHP 5.6.0或更高版本，当前版本：' . $php_version . '</td></tr>';
            echo '<tr><td>MySQL扩展</td><td>' . ($mysql_ok ? '<span style="color: green;">通过</span>' : '<span style="color: red;">不通过</span>') . '</td><td>需要MySQLi扩展支持</td></tr>';
            echo '<tr><td>配置目录权限</td><td>' . ($config_writable ? '<span style="color: green;">通过</span>' : '<span style="color: red;">不通过</span>') . '</td><td>config目录需要可写权限</td></tr>';
            echo '</table>';

            if ($php_ok && $mysql_ok && $config_writable) {
                echo '<form method="post" style="text-align: center; margin-top: 30px;">';
                echo '<input type="hidden" name="step" value="2">';
                echo '<input type="submit" value="进入下一步" class="btn-submit">';
                echo '</form>';
            } else {
                echo '<div class="error">环境检查未通过，请解决以上问题后重试。</div>';
            }
            echo '</div>';
        }

        // 步骤2：数据库配置
        elseif ($_POST['step'] == 2) {
            echo '<div class="step">';
            echo '<div class="step-title">步骤2：数据库配置</div>';

            echo '<div class="info">';
            echo '<p>请先手动创建数据库，然后填写以下信息：</p>';
            echo '<p>1. 登录数据库管理工具（如 phpMyAdmin）</p>';
            echo '<p>2. 创建一个新的数据库（建议使用 stock_db）</p>';
            echo '<p>3. 确保数据库用户具有该数据库的所有权限</p>';
            echo '</div>';

            echo '<form method="post" class="db-config-form">';
            echo '<input type="hidden" name="step" value="3">';

            echo '<div class="form-group">';
            echo '<label for="db_host">数据库地址</label>';
            echo '<input type="text" id="db_host" name="db_host" value="localhost" required class="form-control">';
            echo '<small class="form-text">通常为 localhost，无需修改</small>';
            echo '</div>';

            echo '<div class="form-group">';
            echo '<label for="db_user">数据库用户名</label>';
            echo '<input type="text" id="db_user" name="db_user" value="stock" required class="form-control">';
            echo '<small class="form-text">用于访问数据库的用户名</small>';
            echo '</div>';

            echo '<div class="form-group">';
            echo '<label for="db_pwd">数据库密码</label>';
            echo '<input type="password" id="db_pwd" name="db_pwd" value="123456" required class="form-control">';
            echo '<small class="form-text">用于访问数据库的密码</small>';
            echo '</div>';

            echo '<div class="form-group">';
            echo '<label for="db_name">数据库名</label>';
            echo '<input type="text" id="db_name" name="db_name" value="stock_db" required class="form-control">';
            echo '<small class="form-text">您已创建的数据库名称</small>';
            echo '</div>';

            echo '<div class="form-actions">';
            echo '<input type="submit" value="测试连接并安装" class="btn-submit">';
            echo '</div>';
            echo '</form>';
            echo '</div>';
        }

        // 步骤3：执行安装
        elseif ($_POST['step'] == 3) {
            echo '<div class="step">';
            echo '<div class="step-title">步骤3：执行安装</div>';

            // 获取表单数据
            $db_host = $_POST['db_host'];
            $db_user = $_POST['db_user'];
            $db_pwd = $_POST['db_pwd'];
            $db_name = $_POST['db_name'];

            // 先连接到MySQL服务器（不指定数据库）
            $conn = mysqli_connect($db_host, $db_user, $db_pwd);
            if (!$conn) {
                echo '<div class="error">数据库服务器连接失败：' . mysqli_connect_error() . '</div>';
                echo '<div class="info">请确保：<br>1. 数据库服务器已启动<br>2. 数据库用户名和密码正确</div>';
                echo '<div style="text-align: center; margin-top: 30px;">';
                echo '<a href="install.php" class="btn-submit" style="display: inline-block; text-decoration: none; color: white; padding: 14px 30px; border-radius: 6px; font-weight: bold; transition: background-color 0.3s ease, transform 0.1s ease;">返回上一步</a>';
                echo '</div>';
                echo '</div>';
                exit;
            }

            // 检查数据库是否存在
            $result = mysqli_query($conn, "SHOW DATABASES LIKE '$db_name'");
            if (mysqli_num_rows($result) == 0) {
                echo '<div class="error">数据库不存在：' . $db_name . '</div>';
                echo '<div class="info">请先手动创建数据库，然后再继续安装。<br>1. 登录数据库管理工具（如 phpMyAdmin）<br>2. 创建一个名为 "' . $db_name . '" 的数据库<br>3. 确保数据库用户具有该数据库的所有权限</div>';
                echo '<div style="text-align: center; margin-top: 30px;">';
                echo '<a href="install.php" class="btn-submit" style="display: inline-block; text-decoration: none; color: white; padding: 14px 30px; border-radius: 6px; font-weight: bold; transition: background-color 0.3s ease, transform 0.1s ease;">返回上一步</a>';
                echo '</div>';
                echo '</div>';
                mysqli_close($conn);
                exit;
            }

            // 关闭临时连接
            mysqli_close($conn);

            // 连接到指定数据库
            $conn = mysqli_connect($db_host, $db_user, $db_pwd, $db_name);
            if (!$conn) {
                echo '<div class="error">数据库连接失败：' . mysqli_connect_error() . '</div>';
                echo '<div class="info">请确保：<br>1. 数据库服务器已启动<br>2. 您已创建了数据库<br>3. 数据库用户名和密码正确<br>4. 该用户具有访问数据库的权限</div>';
                echo '<div style="text-align: center; margin-top: 30px;">';
                echo '<a href="install.php" class="btn-submit" style="display: inline-block; text-decoration: none; color: white; padding: 14px 30px; border-radius: 6px; font-weight: bold; transition: background-color 0.3s ease, transform 0.1s ease;">返回上一步</a>';
                echo '</div>';
                echo '</div>';
                exit;
            }

            // 生成默认账号的密码哈希
            $default_password_hash = password_hash('123456', PASSWORD_DEFAULT);
            
            // 创建表结构
            $tables_sql = "
                -- 商品表
                DROP TABLE IF EXISTS product;
                CREATE TABLE product (
                  id INT PRIMARY KEY AUTO_INCREMENT,
                  pname VARCHAR(100) NOT NULL,
                  category VARCHAR(50) DEFAULT '',
                  price DECIMAL(10,2) DEFAULT 0.00,
                  stock INT DEFAULT 0,
                  warn_stock INT DEFAULT 5,
                  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
                
                -- 入库记录表
                DROP TABLE IF EXISTS stock_in;
                CREATE TABLE stock_in (
                  id INT PRIMARY KEY AUTO_INCREMENT,
                  pid INT NOT NULL,
                  num INT NOT NULL,
                  operator VARCHAR(50) NOT NULL DEFAULT 'system',
                  supplier_id INT DEFAULT NULL COMMENT '供货商ID',
                  supplier_name VARCHAR(100) DEFAULT NULL COMMENT '供货商名称',
                  purchase_unit VARCHAR(100) DEFAULT NULL COMMENT '采购单位（兼容旧数据）',
                  remark TEXT DEFAULT NULL COMMENT '备注',
                  ctime TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
                
                -- 出库记录表
                DROP TABLE IF EXISTS stock_out;
                CREATE TABLE stock_out (
                  id INT PRIMARY KEY AUTO_INCREMENT,
                  pid INT NOT NULL,
                  num INT NOT NULL,
                  operator VARCHAR(50) NOT NULL DEFAULT 'system',
                  receive_unit VARCHAR(100) DEFAULT NULL COMMENT '领取单位',
                  receive_unit_id INT DEFAULT NULL COMMENT '领取单位ID',
                  receive_unit_name VARCHAR(100) DEFAULT NULL COMMENT '领取单位名称',
                  remark TEXT DEFAULT NULL COMMENT '备注',
                  ctime TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
                
                -- 管理员表
                DROP TABLE IF EXISTS admin;
                CREATE TABLE admin (
                  id INT PRIMARY KEY AUTO_INCREMENT,
                  username VARCHAR(50) NOT NULL UNIQUE,
                  password VARCHAR(60) NOT NULL,
                  nickname VARCHAR(50) DEFAULT NULL COMMENT '昵称',
                  role VARCHAR(20) DEFAULT 'user',
                  status INT DEFAULT 1,
                  first_login INT DEFAULT 1,
                  failed_login_count INT DEFAULT 0,
                  last_failed_login TIMESTAMP NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
                
                -- 用户权限表
                DROP TABLE IF EXISTS user_perm;
                CREATE TABLE user_perm (
                  id INT PRIMARY KEY AUTO_INCREMENT,
                  username VARCHAR(50) NOT NULL UNIQUE,
                  p_index INT DEFAULT 1,
                  p_in INT DEFAULT 0,
                  p_out INT DEFAULT 0,
                  p_in_list INT DEFAULT 0,
                  p_out_list INT DEFAULT 0,
                  p_export INT DEFAULT 0,
                  p_search INT DEFAULT 1
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
                
                -- 操作日志表
                DROP TABLE IF EXISTS log;
                CREATE TABLE log (
                  id INT PRIMARY KEY AUTO_INCREMENT,
                  username VARCHAR(50) NOT NULL,
                  content VARCHAR(255) NOT NULL,
                  ctime TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
                
                -- 备份记录表
                DROP TABLE IF EXISTS backup;
                CREATE TABLE backup (
                  id INT PRIMARY KEY AUTO_INCREMENT,
                  filename VARCHAR(255),
                  ctime TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
                
                -- 供货商表
                DROP TABLE IF EXISTS supplier;
                CREATE TABLE supplier (
                  id INT PRIMARY KEY AUTO_INCREMENT,
                  name VARCHAR(100) NOT NULL COMMENT '供货商名称',
                  contact VARCHAR(50) DEFAULT NULL COMMENT '联系人',
                  phone VARCHAR(20) DEFAULT NULL COMMENT '联系电话',
                  remark TEXT DEFAULT NULL COMMENT '备注',
                  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
                
                -- 领取单位表
                DROP TABLE IF EXISTS receive_unit;
                CREATE TABLE receive_unit (
                  id INT PRIMARY KEY AUTO_INCREMENT,
                  name VARCHAR(100) NOT NULL COMMENT '领取单位名称',
                  contact VARCHAR(50) DEFAULT NULL COMMENT '联系人',
                  phone VARCHAR(20) DEFAULT NULL COMMENT '联系电话',
                  remark TEXT DEFAULT NULL COMMENT '备注',
                  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
                
                -- 初始化默认账号 (密码: 123456)
                INSERT INTO admin (username, password, role, status, first_login) VALUES 
                ('admin', '$default_password_hash', 'admin', 1, 0);
            ";

            // 执行SQL语句
            $sql_statements = explode(';', $tables_sql);
            foreach ($sql_statements as $sql) {
                $sql = trim($sql);
                if (!empty($sql)) {
                    if (!mysqli_query($conn, $sql)) {
                        echo '<div class="error">执行SQL失败：' . mysqli_error($conn) . '</div>';
                        echo '<a href="install.php" class="btn-submit">返回上一步</a>';
                        echo '</div>';
                        exit;
                    }
                }
            }
            // 生成安装锁定文件，包含数据库连接信息
            $install_lock_content = "<?php exit; ?>.\n"
                . date('Y-m-d H:i:s') . "\n"
                . "db_host=$db_host\n"
                . "db_user=$db_user\n"
                . "db_pwd=$db_pwd\n"
                . "db_name=$db_name";

            if (file_put_contents('../config/install.lock.php', $install_lock_content)) {
                echo '<div class="success">安装锁定文件生成成功！</div>';

                // 生成config.php文件
                $config_content = '<?php
// 禁用错误显示
ini_set(\'display_errors\', \'0\');
ini_set(\'display_startup_errors\', \'0\');
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);

// 从install.lock.php文件读取数据库配置
function get_db_config() {
    $install_lock = __DIR__ . \'/install.lock.php\';
    if (!file_exists($install_lock)) {
        die("系统未安装，请先运行安装程序");
    }
    
    $lines = file($install_lock, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $config = [];
    
    foreach ($lines as $line) {
        // 跳过PHP exit行
        if (strpos($line, \'<?php exit; ?>\') !== false) {
            continue;
        }
        if (strpos($line, \'=\') !== false) {
            list($key, $value) = explode(\'=\', $line, 2);
            $config[trim($key)] = trim($value);
        }
    }
    
    return $config;
}

// 获取数据库配置
$db_config = get_db_config();
$db_host = $db_config[\'db_host\'];
$db_user = $db_config[\'db_user\'];
$db_pwd = $db_config[\'db_pwd\'];
$db_name = $db_config[\'db_name\'];

// 连接数据库
$conn = mysqli_connect($db_host, $db_user, $db_pwd, $db_name);
if (!$conn) {
    die("数据库连接失败：" . mysqli_connect_error() . "<br><br>请删除 config/install.lock.php 文件并重新运行安装程序。<br><br>注意：重新安装可能会导致数据丢失，请谨慎操作。<br><br>如果数据重要，可通过 phpMyAdmin 等工具进行手动备份剩余数据库数据之后再重新安装程序。");
}

// 设置字符集（避免中文乱码）
mysqli_set_charset($conn, "utf8");

// 检查必要的数据库表是否存在
function check_required_tables($conn) {
    $required_tables = [
        \'product\',
        \'stock_in\',
        \'stock_out\',
        \'admin\',
        \'user_perm\',
        \'log\',
        \'receive_unit\'
    ];
    
    foreach ($required_tables as $table) {
        $result = mysqli_query($conn, "SHOW TABLES LIKE \'$table\'");
        if (mysqli_num_rows($result) == 0) {
            return false; // 表不存在
        }
    }
    return true; // 所有表都存在
}

// 检查数据库表是否存在
if (!check_required_tables($conn)) {
    // 检测服务器系统
    $os = PHP_OS;
    $config_path = __DIR__ . \'/config.php\';
    $lock_path = __DIR__ . \'/install.lock.php\';
    
    // 取消只读权限，设为所有人可读写
    if (strtoupper(substr($os, 0, 3)) === \'WIN\') {
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
    
    die("数据库表不存在或已被删除。<br><br>config.php和install.lock.php文件已设为可读写权限，如需重新安装系统，请删除 `config/install.lock.php` 文件后重新运行安装程序。<br><br>⚠️ 注意：重新安装会清空数据，重要数据请先通过 phpMyAdmin 等工具备份。<br><br>🔒 安全提醒：若非您手动删除，建议检查服务器是否存在安全风险。");
}
?>';

                if (file_put_contents('../config/config.php', $config_content)) {
                    echo '<div class="success">配置文件生成成功！</div>';
                } else {
                    echo '<div class="error">配置文件生成失败，请手动创建 config/config.php 文件</div>';
                }

                // 检测服务器系统
                $os = PHP_OS;
                $config_path = '../config/config.php';
                $lock_path = '../config/install.lock.php';

                // 设置文件权限为只读
                if (strtoupper(substr($os, 0, 3)) === 'WIN') {
                    // Windows系统
                    $cmd1 = "icacls \"$config_path\" /inheritance:r /grant:r Everyone:R";
                    $cmd2 = "icacls \"$lock_path\" /inheritance:r /grant:r Everyone:R";
                    @exec($cmd1);
                    @exec($cmd2);
                } else {
                    // Linux/Unix系统
                    @chmod($config_path, 0444);
                    @chmod($lock_path, 0444);
                }
                echo '<div class="info">已将 config.php 和 install.lock.php 文件配置为只读权限。</div>';
                echo '<div class="info">如需重新安装，请手动修改文件权限后，删除这两个文件，重新执行安装程序。</div>';
            } else {
                echo '<div class="error">安装锁定文件生成失败，请手动创建 config/install.lock.php 文件</div>';
            }

            // 关闭数据库连接
            mysqli_close($conn);

            echo '<div class="success">安装成功！</div>';
            echo '<div class="info">默认账号：<br>管理员：admin / 123456<br>普通用户：user / 123456</div>';
            echo '<div style="text-align: center; margin-top: 30px;">';
            echo '<a href="../pages/dashboard/index.php" class="btn-submit" style="display: inline-block; text-decoration: none; color: white; padding: 14px 30px; border-radius: 6px; font-weight: bold; transition: background-color 0.3s ease, transform 0.1s ease;">进入系统</a>';
            echo '</div>';
            echo '</div>';
        }
        ?>
    </div>
</body>

</html>
<?php
ob_end_flush();
?>