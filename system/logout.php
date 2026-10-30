<?php
require __DIR__ . '/inc/bootstrap.php';
if (is_post() && user()) {
    check_csrf();
    log_action('Signed out');
}
$_SESSION = [];
session_destroy();
redirect('login.php');
