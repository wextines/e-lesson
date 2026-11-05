<?php
session_start();
unset(
    $_SESSION['admin_logged_in'],
    $_SESSION['admin_id'],
    $_SESSION['admin_username'],
    $_SESSION['admin_role'],
    $_SESSION['admin_tenant_id']
);
header("Location: login.php");
exit();
