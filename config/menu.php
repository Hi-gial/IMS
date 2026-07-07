<?php
if (!defined('IN_SYSTEM')) exit;
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">
  <div class="container">
    <a class="navbar-brand" href=" ">捷顺ims</a >
    <div class="collapse navbar-collapse">
      <ul class="navbar-nav me-auto">
        <li class="nav-item"><a class="nav-link" href="index.php">首页</a ></li>
        <li class="nav-item"><a class="nav-link" href="product_list.php">商品管理</a ></li>
        <li class="nav-item"><a class="nav-link" href="in.php">商品入库</a ></li>
        <li class="nav-item"><a class="nav-link" href="in_list.php">入库记录</a ></li>
        <li class="nav-item"><a class="nav-link" href="out.php">商品出库</a ></li>
        <li class="nav-item"><a class="nav-link" href="out_list.php">出库记录</a ></li>
        <li class="nav-item"><a class="nav-link" href="user_list.php">用户管理</a ></li>
        <li class="nav-item"><a class="nav-link" href="log_list.php">操作日志</a ></li>
      </ul>
      <span class="text-white me-2">欢迎：<?= $_SESSION['real_name'] ?></span>
      <a href="logout.php" class="btn btn-sm btn-outline-light">退出登录</a >
    </div>
  </div>
</nav>