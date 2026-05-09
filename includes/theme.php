<?php
// Theme loader for GlobalHand
// This file loads and outputs CSS variables for the theme system

require_once 'config.php';

// Sanitize color values
function sanitizeColor($color) {
    // Remove any non-hex characters
    $color = preg_replace('/[^0-9a-fA-F#]/', '', $color);
    
    // Validate hex color format
    if (preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
        return $color;
    }
    
    // Return default if invalid
    return '#4f46e5';
}

// Sanitize size values
function sanitizeSize($size) {
    // Allow px values and common CSS units
    if (preg_match('/^[0-9]+(px|em|rem|%)$/', $size)) {
        return $size;
    }
    
    // Return default if invalid
    return '16px';
}

// Sanitize shadow values
function sanitizeShadow($shadow) {
    // Basic validation for CSS shadow
    if (preg_match('/^[0-9a-fA-F\s,\.rgba()]+$/', $shadow)) {
        return $shadow;
    }
    
    // Return default if invalid
    return '0 10px 30px rgba(16,24,40,.08)';
}

// Sanitize appearance mode
function sanitizeAppearance($mode) {
    return in_array($mode, ['light', 'dark']) ? $mode : 'light';
}

// Get theme settings with safe fallbacks
function getThemeSettings() {
    $appearance = sanitizeAppearance(getSetting('appearance_mode', 'light'));
    
    if ($appearance === 'dark') {
        return [
            'primary' => sanitizeColor(getSetting('theme_primary', '#4f46e5')),
            'secondary' => sanitizeColor(getSetting('theme_secondary', '#7c3aed')),
            'sidebar' => sanitizeColor(getSetting('theme_sidebar', '#020617')),
            'background' => sanitizeColor(getSetting('theme_background', '#0f172a')),
            'surface' => sanitizeColor(getSetting('theme_surface', '#111827')),
            'text' => sanitizeColor(getSetting('theme_text', '#f8fafc')),
            'border' => '#334155',
            'radius' => sanitizeSize(getSetting('theme_radius', '16px')),
            'shadow' => sanitizeShadow(getSetting('theme_shadow', '0 10px 30px rgba(0,0,0,.5)')),
            'appearance' => $appearance
        ];
    } else {
        return [
            'primary' => sanitizeColor(getSetting('theme_primary', '#4f46e5')),
            'secondary' => sanitizeColor(getSetting('theme_secondary', '#7c3aed')),
            'sidebar' => sanitizeColor(getSetting('theme_sidebar', '#101828')),
            'background' => sanitizeColor(getSetting('theme_background', '#f5f7fb')),
            'surface' => sanitizeColor(getSetting('theme_surface', '#ffffff')),
            'text' => sanitizeColor(getSetting('theme_text', '#101828')),
            'border' => '#e5e7eb',
            'radius' => sanitizeSize(getSetting('theme_radius', '16px')),
            'shadow' => sanitizeShadow(getSetting('theme_shadow', '0 10px 30px rgba(16,24,40,.08)')),
            'appearance' => $appearance
        ];
    }
}

// Generate theme CSS
function generateThemeCSS() {
    $theme = getThemeSettings();
    
    // Calculate derived colors
    $primaryDark = adjustColorBrightness($theme['primary'], -20);
    $sidebarSoft = adjustColorBrightness($theme['sidebar'], 10);
    $muted = adjustColorBrightness($theme['text'], -30);
    $radiusSm = adjustSizeValue($theme['radius'], -6);
    
    return "
        :root {
            --primary: {$theme['primary']};
            --primary-dark: {$primaryDark};
            --secondary: {$theme['secondary']};
            --success: #16a34a;
            --warning: #f59e0b;
            --danger: #dc2626;
            --info: #0284c7;
            
            --bg: {$theme['background']};
            --surface: {$theme['surface']};
            --sidebar: {$theme['sidebar']};
            --sidebar-soft: {$sidebarSoft};
            --text: {$theme['text']};
            --muted: {$muted};
            --border: {$theme['border']};
            
            --radius: {$theme['radius']};
            --radius-sm: {$radiusSm};
            --shadow: {$theme['shadow']};
            --shadow-soft: 0 4px 14px rgba(16,24,40,.06);
            --transition: .22s ease;
        }
        
        " . ($theme['appearance'] === 'dark' ? 
            'body { background: var(--bg); color: var(--text); }' . 
            '.login-container { background: var(--surface); }' .
            '.register-container { background: var(--surface); }' .
            '.auth-page { background: var(--bg); }'
            : '') . "
    ";
}

// Adjust color brightness
function adjustColorBrightness($color, $percent) {
    $color = ltrim($color, '#');
    $num = hexdec($color);
    $amt = round(2.55 * $percent);
    $r = max(0, min(255, ($num >> 16) + $amt));
    $g = max(0, min(255, (($num >> 8) & 0x00FF) + $amt));
    $b = max(0, min(255, ($num & 0x0000FF) + $amt));
    return '#' . sprintf('%02X%02X%02X', $r, $g, $b);
}

// Adjust size value
function adjustSizeValue($size, $pixels) {
    $value = (int) $size;
    $new_value = max(4, $value + $pixels);
    return $new_value . 'px';
}

// Output the theme CSS
echo generateThemeCSS();
?>
