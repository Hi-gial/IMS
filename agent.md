# 捷顺ims 代码规范与书写要求

## 一、项目概述

捷顺ims是一个基于PHP的商品库存管理系统，采用前后端一体的开发模式，使用原生PHP + MySQL + HTML/CSS/JavaScript技术栈。

## 二、目录结构

```
stock/
├── assets/           # 静态资源（CSS、图片）
├── config/           # 配置文件（数据库连接、权限检查、日志记录）
├── functions/        # 公共函数（导出、编辑、删除）
├── install/          # 安装程序
├── pages/            # 页面文件
│   ├── admin/        # 管理员页面（用户管理、供货商、日志等）
│   ├── auth/         # 认证页面（登录、登出、密码修改）
│   ├── dashboard/    # 仪表盘/首页
│   ├── inventory/    # 库存页面（入库、出库、记录查询）
│   └── products/     # 商品管理页面
├── phpMyAdmin4.8.5/  # 数据库管理工具（第三方）
└── test/             # 测试脚本目录（批量导入等）
```

## 三、安全规范

### 3.1 错误显示控制

```php
// 所有页面顶部必须添加错误显示控制
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
```

### 3.2 非法访问防护

所有需要登录的页面必须定义 `IN_SYSTEM` 常量，并在被包含文件中检查：

```php
// 页面顶部
if (!defined('IN_SYSTEM')) define('IN_SYSTEM', true);

// 被包含文件（如 write_log.php、check_perm.php）
if (!defined('IN_SYSTEM')) {
    // 输出非法访问提示页面并退出
    echo '...';
    exit;
}
```

### 3.3 密码处理

- **存储**：必须使用 `password_hash()`
- **验证**：必须使用 `password_verify()`
- **禁止**：禁止使用 `md5(md5(...))` 等不安全的加密方式

```php
// 密码存储
$pwd = password_hash(input('password'), PASSWORD_DEFAULT);

// 密码验证
$password_match = password_verify($password, $user['password']);
```

### 3.4 SQL注入防护

**强制要求**：所有数据库查询必须使用预编译语句（prepared statements），禁止直接拼接SQL。

```php
// 正确：使用预编译语句
$stmt = $conn->prepare("SELECT * FROM product WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

// 错误：禁止直接拼接
// $sql = "SELECT * FROM product WHERE id = $id";
```

### 3.5 输出转义

所有输出到HTML的用户输入必须使用 `htmlspecialchars()` 转义：

```php
function h($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

// 输出时使用
echo h($row['name']);
```

## 四、数据库操作规范

### 4.1 连接管理

数据库连接统一通过 `config/config.php` 管理，页面通过 `include` 引入：

```php
include '../../config/config.php';
```

### 4.2 事务处理

涉及多个数据库操作的业务（如入库、出库、删除记录）必须使用事务：

```php
$conn->begin_transaction();
try {
    // 多个数据库操作
    $conn->commit();
} catch (Exception $e) {
    $conn->rollback();
}
```

### 4.3 预编译语句参数绑定

`bind_param()` 必须使用变量引用，禁止使用字符串字面量：

```php
// 正确
$stmt->bind_param("sssddi", $brand, $model, $category, $price, $cost_price, $warn_stock);

// 错误（会导致致命错误）
// $stmt->bind_param("sssddi", "品牌", "型号", "分类", 100.00, 50.00, 5);
```

### 4.4 字符集设置

```php
mysqli_set_charset($conn, "utf8");
```

## 五、页面结构规范

### 5.1 页面布局

所有管理页面采用统一布局：

- **左侧导航栏**（sidebar）：包含菜单和用户信息
- **右侧内容区**（content）：页面具体内容

### 5.2 导航栏折叠功能

导航栏支持折叠/展开，状态存储在 localStorage：

```javascript
// 折叠状态存储
localStorage.setItem('sidebarCollapsed', isCollapsed);

// 状态恢复
const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
```

### 5.3 页面标题格式

```html
<title>捷顺ims - 页面名称</title>
```

### 5.4 权限检查

所有页面必须进行登录状态检查和权限检查：

```php
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: ../auth/login.php");
    exit;
}

// 检查具体权限（非管理员）
if ($_SESSION['role'] != 1 && (!isset($perm['p_in']) || $perm['p_in'] != 1)) {
    header("Location: ../auth/no_permission.php");
    exit;
}
```

## 六、日志记录规范

### 6.1 日志函数

统一使用 `write_log()` 函数记录操作日志：

```php
include '../../config/write_log.php';
write_log($conn, $_SESSION['username'], "操作内容");
```

### 6.2 商品日志格式

商品相关操作（添加、入库、出库）的日志必须使用"品牌-型号"格式：

```php
// 正确
write_log($conn, $_SESSION['username'], "添加商品：爱普生-L805");
write_log($conn, $_SESSION['username'], "用户 admin 入库商品：爱普生-L805，数量：10");

// 错误（禁止使用空格分隔）
// write_log($conn, $_SESSION['username'], "添加商品：爱普生 L805");
```

## 七、文件命名规范

| 类型 | 命名规则 | 示例 |
|------|----------|------|
| 列表页 | xxx_list.php | product_list.php, supplier_list.php |
| 添加页 | xxx_add.php | product_add.php, supplier_add.php |
| 编辑页 | xxx_edit.php | product_edit.php, user_edit.php |
| 删除页 | xxx_del.php | product_del.php, supplier_del.php |

## 八、常用函数

### 8.1 input()

获取表单输入并去除首尾空白：

```php
function input($name) {
    return isset($_POST[$name]) ? trim($_POST[$name]) : '';
}

// 使用
$brand = input('brand');
```

### 8.2 h()

HTML输出转义：

```php
function h($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

// 使用
echo h($row['name']);
```

## 九、权限管理规范

### 9.1 角色定义

| 角色值 | 角色名称 | 说明 |
|--------|----------|------|
| 1 | 管理员 | 拥有所有权限 |
| 2 | 普通用户 | 根据权限配置访问 |

### 9.2 权限字段

权限存储在 `user_perm` 表中：

| 字段 | 说明 |
|------|------|
| p_index | 首页访问权限 |
| p_in | 入库操作权限 |
| p_out | 出库操作权限 |
| p_in_list | 入库记录查看权限 |
| p_out_list | 出库记录查看权限 |
| p_export | 数据导出权限 |
| p_search | 搜索功能权限 |

## 十、数据导出规范

### 10.1 导出格式

- 文件格式：`.xls`（HTML表格格式）
- 编码：UTF-8 BOM（支持中文）
- 按钮文字："导出表格"

### 10.2 表格样式

导出的HTML表格必须包含：

- `border='1'`
- 表头背景色 `#f8f9fa`
- 居中、加粗、16pt标题，带有10px padding和适当colspan

```php
// 导出头部设置
header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
header('Content-Disposition: attachment; filename="xxx_' . date('Ymd') . '.xls"');

// 输出BOM（解决Excel中文乱码）
echo "\xEF\xBB\xBF";

// 表格结构
echo '<table border="1" cellspacing="0" cellpadding="5">';
echo '<tr><td colspan="8" style="text-align: center; font-weight: bold; font-size: 16px; padding: 10px;">标题</td></tr>';
echo '<thead><tr style="background-color: #f8f9fa; font-weight: bold;">...</tr></thead>';
```

## 十一、安装程序规范

### 11.1 安装检测

所有页面（除安装页外）必须检查安装状态：

```php
function check_installation() {
    if (file_exists('../../config/install.lock.php')) {
        // 读取配置并测试连接
        // 检查必要的数据库表是否存在
        return true;
    }
    return false;
}

if (!check_installation()) {
    header("Location: ../../install/install.php");
    exit;
}
```

### 11.2 必要数据表

安装程序必须创建以下表：

- product（商品表）
- stock_in（入库记录表）
- stock_out（出库记录表）
- admin（管理员表）
- user_perm（用户权限表）
- log（操作日志表）
- receive_unit（领取单位表）
- supplier（供货商表）

### 11.3 配置文件权限

安装完成后，`config.php` 和 `install.lock.php` 必须设置为只读权限：

```php
// Windows系统
icacls "config.php" /inheritance:r /grant:r Everyone:R

// Linux/Unix系统
chmod config.php 0444
```

## 十二、新增页面开发流程

1. **创建页面文件**：按命名规范创建文件
2. **添加错误控制**：页面顶部添加错误显示控制
3. **检查安装状态**：调用 `check_installation()`
4. **检查登录状态**：检查 `$_SESSION['username']`
5. **引入配置文件**：`include '../../config/config.php'`
6. **检查权限**：根据页面功能检查对应权限
7. **实现业务逻辑**：使用预编译语句操作数据库
8. **记录操作日志**：调用 `write_log()`
9. **编写HTML页面**：使用统一布局和样式

## 十三、数据库表结构要求

### 13.1 product 表

必须包含以下字段：
- id, pname, brand, model, category, price, cost_price, stock, warn_stock, created_at

### 13.2 stock_in 表

必须包含以下字段：
- id, pid, num, unit, operator, supplier_id, supplier_name, remark, ctime

### 13.3 stock_out 表

必须包含以下字段：
- id, pid, num, unit, operator, receive_unit_id, receive_unit_name, remark, ctime

## 十四、注意事项

1. **禁止修改现有列表页**：`in_list.php`、`out_list.php` 等现有列表页不得修改
2. **测试脚本位置**：批量导入等测试脚本必须放在 `test/` 目录
3. **商品标识**：商品标识必须使用品牌+型号（`brand-model`），禁止使用商品名称
4. **分类显示**：列表页中缺失值显示为 `-` 或 `(未设置)`
5. **库存预警**：库存低于预警值时显示红色背景和白色粗体文字
6. **分页功能**：列表页必须支持分页，默认15条/页，支持10/15/20/50条切换，分页大小记忆到localStorage