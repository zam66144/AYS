<?php
session_start();
unset($_SESSION['admin_logged']);
session_destroy();
header('Location: login.php');
exit;
?>