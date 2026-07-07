<?php
// 禁用错误显示
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);

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
include '../../config/config.php'; // 已配置stock_db、stock用户名、123456密码

// 处理验证码刷新
if (isset($_GET['refresh_captcha'])) {
    if (isset($_GET['captcha_id']) && isset($_GET['captcha_url'])) {
        $_SESSION['captcha_id'] = $_GET['captcha_id'];
        $_SESSION['captcha_url'] = urldecode($_GET['captcha_url']);
    }
    exit;
}

// 生成验证码 - 每次访问登录页面都生成新的验证码
// 调用API获取验证码
$captcha_url = "https://v2.xxapi.cn/api/captcha?width=120&height=40&length=4&options=1&type=math";
$options = array(
    'http' => array(
        'method' => 'GET',
    )
);
$context = stream_context_create($options);
$result = file_get_contents($captcha_url, false, $context);

// 初始化错误变量
$captcha_error = '';
if ($result !== FALSE) {
    $response = json_decode($result, true);
    // 检查JSON解析是否成功
    if ($response === null) {
        // JSON解析失败
        $captcha_error = '验证码获取失败：JSON解析错误';
    } else if (isset($response['code']) && $response['code'] == 200) {
        // 检查必要字段是否存在
        if (isset($response['data']['id']) && isset($response['data']['url'])) {
            $_SESSION['captcha_id'] = $response['data']['id'];
            $_SESSION['captcha_url'] = $response['data']['url'];
        } else {
            $captcha_error = '验证码获取失败：API响应格式错误';
        }
    } else {
        // API返回错误
        $error_msg = isset($response['msg']) ? $response['msg'] : '未知错误';
        $captcha_error = '验证码获取失败：' . $error_msg;
    }
} else {
    $captcha_error = '验证码获取失败：网络请求错误';
}

// 如果已登录，直接跳首页
if (isset($_SESSION['username'])) {
    header("Location: ../dashboard/index.php");
    exit;
}

$error = '';
// 处理登录提交
if ($_POST) {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']); // 使用明文密码进行password_verify验证
    $captcha = trim($_POST['captcha']);
    $captcha_id = $_POST['captcha_id'];

    // 验证验证码
    if (empty($captcha)) {
        $error = '请输入验证码';
    } else {
        // 根据API文档，验证验证码时需要传递id和key参数
        $captcha_url = "https://v2.xxapi.cn/api/captcha?id={$captcha_id}&key={$captcha}&type=math";
        $options = array(
            'http' => array(
                'method' => 'GET',
            )
        );
        $context = stream_context_create($options);
        $result = file_get_contents($captcha_url, false, $context);
        
        if ($result === FALSE) {
            $error = '验证码错误，请重新输入';
        } else {
            $response = json_decode($result, true);
            // 检查JSON解析是否成功
            if ($response === null) {
                $error = '验证码错误，请重新输入';
            } else if (isset($response['code']) && $response['code'] != 200) {
                $error = '验证码错误，请重新输入';
            } else if (isset($response['code']) && $response['code'] == 200) {
                // 从admin表查询用户（适配管理员+普通用户）
                $sql = "SELECT * FROM `admin` WHERE `username` = ?";
                $stmt = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param($stmt, 's', $username);
                mysqli_stmt_execute($stmt);
                $res = mysqli_stmt_get_result($stmt);
                $user = mysqli_fetch_assoc($res);
                mysqli_stmt_close($stmt);

                // 检查账号是否存在
                if ($user) {
                    // 检查账号是否被禁用
                    if ($user['status'] != 1) {
                        $error = '账号已禁用！请联系管理员解封账号。';
                    } else {
                        // 检查密码 - 支持旧密码格式（md5(md5)）和新密码格式（password_hash）
                    $password_match = password_verify($password, $user['password']);
                    if (!$password_match && strlen($user['password']) == 32) {
                        $password_match = md5(md5($password)) == $user['password'];
                        if ($password_match) {
                            $new_hash = password_hash($password, PASSWORD_DEFAULT);
                            mysqli_query($conn, "UPDATE admin SET password = '$new_hash' WHERE id = {$user['id']}");
                        }
                    }
                    
                    if ($password_match) {
                        // 重置密码错误次数
                        mysqli_query($conn, "UPDATE admin SET failed_login_count = 0, last_failed_login = NULL WHERE id = {$user['id']}");
                        
                        // 保存用户信息到session（关键：记录角色）
                        $_SESSION['username'] = $user['username'];
                        $_SESSION['real_name'] = !empty($user['nickname']) ? $user['nickname'] : $user['username']; // 优先使用昵称，否则使用账号名
                        $_SESSION['role'] = $user['role'] == 'admin' ? 1 : 2; // 1=管理员，2=普通用户
                        $_SESSION['user_id'] = $user['id'];

                        // 检查是否首次登录（仅普通用户）
                        if ($user['role'] == 'user' && (isset($user['first_login']) ? $user['first_login'] : 1) == 1) {
                            // 标记为已登录
                            mysqli_query($conn, "UPDATE admin SET first_login = 0 WHERE id = {$user['id']}");
                            // 跳转到密码修改页面
                            header("Location: change_password.php?first_login=1");
                            exit;
                        }

                        // 记录登录日志（可选）
                        $ip = $_SERVER['REMOTE_ADDR'];
                        mysqli_query($conn, "INSERT INTO `log` (`username`, `content`) VALUES ('{$user['username']}', '用户登录')");

                        header("Location: ../dashboard/index.php");
                        exit;
                    } else {
                            // 密码错误，增加错误次数
                            $failed_count = isset($user['failed_login_count']) ? $user['failed_login_count'] : 0;
                            $failed_count++;
                            
                            // 检查是否超过错误次数阈值（5次）
                            if ($failed_count >= 5) {
                                // 封禁账号
                                mysqli_query($conn, "UPDATE admin SET status = 0, failed_login_count = $failed_count, last_failed_login = CURRENT_TIMESTAMP WHERE id = {$user['id']}");
                                $error = '密码错误次数过多，账号已被封禁！';
                            } else {
                                // 更新错误次数
                                mysqli_query($conn, "UPDATE admin SET failed_login_count = $failed_count, last_failed_login = CURRENT_TIMESTAMP WHERE id = {$user['id']}");
                                $error = '账号不存在/密码错误/账号已禁用！';
                            }
                        }
                    }
                } else {
                    $error = '账号不存在/密码错误/账号已禁用！';
                }
            } else {
                $error = '验证码错误，请重新输入';
            }
        }
    }
}?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>捷顺ims - 登录</title>
    <link rel="icon" href="../../assets/img/b5afc3a4b86e1.png" type="image/png">
    <link rel="stylesheet" href="../../assets/style.css">
</head>
<body style="display: flex; justify-content: center; align-items: center; height: 100vh;">
    <div class="card" style="width: 400px;">
        <h3 style="text-align: center; margin-bottom: 20px; color: #2c3e50;">捷顺ims</h3>
        
        <!-- 错误提示 -->
        <?php if (isset($captcha_error) && $captcha_error) { ?>
            <div style="color: #e74c3c; text-align: center; margin-bottom: 15px; padding: 10px; background: #fef0f0; border-radius: 4px;">
                <?= $captcha_error ?>
            </div>
        <?php } ?>
        <?php if ($error) { ?>
            <div style="color: #e74c3c; text-align: center; margin-bottom: 15px; padding: 10px; background: #fef0f0; border-radius: 4px;">
                <?= $error ?>
            </div>
        <?php } ?>

        <!-- 登录表单 -->
        <form method="post">
            <div class="mb-3">
                <label style="display: block; margin-bottom: 5px; color: #333;">账号</label>
                <input type="text" name="username" class="form-control" placeholder="请输入登录账号" required>
            </div>
            <div class="mb-3">
                <label style="display: block; margin-bottom: 5px; color: #333;">密码</label>
                <input type="password" name="password" class="form-control" placeholder="请输入登录密码" required>
            </div>
            <div class="mb-3">
                <label style="display: block; margin-bottom: 5px; color: #333;">验证码</label>
                <div style="display: flex; gap: 10px;">
                    <input type="text" name="captcha" class="form-control" placeholder="请输入验证码" required style="flex: 1;">
                    <div style="display: flex; align-items: center;">
                        <img id="captcha-image" src="<?= $_SESSION['captcha_url'] ?>" alt="验证码" style="height: 40px; border-radius: 4px; cursor: pointer;">
                        <input type="hidden" name="captcha_id" value="<?= $_SESSION['captcha_id'] ?>">
                    </div>
                </div>
            </div>
            <div style="margin-top: 20px;">
                <button class="btn btn-primary" style="width: 100%; padding: 10px; font-size: 16px;">登录</button>
            </div>
            <script>
                // 页面加载时清空表单输入值
                window.onload = function() {
                    // 清空表单输入框
                    document.querySelector('input[name="username"]').value = '';
                    document.querySelector('input[name="password"]').value = '';
                    document.querySelector('input[name="captcha"]').value = '';
                };
                
                // 刷新验证码
                document.getElementById('captcha-image').addEventListener('click', function() {
                    // 调用API获取新的验证码
                    fetch('https://v2.xxapi.cn/api/captcha?width=120&height=40&length=4&options=1&type=math')
                        .then(response => {
                            if (!response.ok) {
                                throw new Error('网络请求失败');
                            }
                            return response.json();
                        })
                        .then(data => {
                            if (data && data.code === 200 && data.data && data.data.url && data.data.id) {
                                // 更新验证码图片
                                document.getElementById('captcha-image').src = data.data.url;
                                // 更新隐藏字段中的captcha_id
                                document.querySelector('input[name="captcha_id"]').value = data.data.id;
                                // 同时更新session中的值
                                fetch('login.php?refresh_captcha=1&captcha_id=' + data.data.id + '&captcha_url=' + encodeURIComponent(data.data.url))
                                    .catch(error => console.error('更新session失败:', error));
                            } else {
                                throw new Error('API响应格式错误');
                            }
                        })
                        .catch(error => {
                            console.error('刷新验证码失败:', error);
                            // 可以添加用户提示
                            alert('刷新验证码失败，请重试');
                        });
                });
            </script>

        </form>
    </div>
</body>
</html>