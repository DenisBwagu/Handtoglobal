<?php
require_once 'config.php';

// Destroy user session
session_unset();
session_destroy();

// Redirect to login
redirect('login.php');