<?php
require_once __DIR__ . '/config.php';

// Destroy user session
session_unset();
session_destroy();

// Redirect to login
redirect('login.php');