<?php

require_once 'config/database.php';
require_once 'config/security.php';
require_once 'config/auth.php';

session_start();
logoutUser();
header('Location: login.php');
exit;
