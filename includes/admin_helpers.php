<?php

if (!function_exists('admin_back_button')) {
    function admin_back_button($url, $label = 'Back') {
        echo '<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" class="admin-back-btn">&larr; ' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</a>';
    }
}
