<?php
require __DIR__ . '/lib/bootstrap.php';
logout_user();
header('Location: login.php');
exit;
