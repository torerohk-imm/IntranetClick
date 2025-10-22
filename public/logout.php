<?php
session_start();

require __DIR__ . '/../vendor/autoload.php';

use App\Auth;

Auth::logout();

header('Location: login.php');
exit;
