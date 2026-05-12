<?php
require_once '../config.php';
startSession();
session_destroy();
header('Location: '. SITE_URL . '/admin/login.php');
exit;
