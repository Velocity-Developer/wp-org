<?php

/**
 * Plugin Name: Velocity Organisasi
 * Description: Plugin manajemen organisasi untuk login, register, anggota, dan pendaftaran.
 * Version: 0.1.16
 * Author: Velocitydeveloper
 * Text Domain: wp-org
 */

if (!defined('ABSPATH')) {
    exit;
}

define('WP_ORG_VERSION', '0.1.16');
define('WP_ORG_PATH', plugin_dir_path(__FILE__));
define('WP_ORG_URL', plugin_dir_url(__FILE__));

$composer_autoload = WP_ORG_PATH . 'vendor/autoload.php';
if (file_exists($composer_autoload)) {
    require_once $composer_autoload;
}

spl_autoload_register(function ($class) {
    $prefix = 'WpOrg\\';
    $base_dir = WP_ORG_PATH . 'src/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

function wp_org_init()
{
    $plugin = new \WpOrg\Core\Plugin();
    $plugin->run();
}

add_action('plugins_loaded', 'wp_org_init');

register_activation_hook(__FILE__, function () {
    \WpOrg\Core\Installer::activate();
    flush_rewrite_rules();
});

register_deactivation_hook(__FILE__, function () {
    flush_rewrite_rules();
});
