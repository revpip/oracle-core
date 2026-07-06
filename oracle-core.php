<?php
/**
 * Plugin Name: Oracle Core
 * Plugin URI: https://github.com/revpip/oracle-core
 * Description: A modular personal insight and self-reflection assessment platform foundation.
 * Version: 0.1.0
 * Author: Crown / Oracle Core
 * Text Domain: oracle-core
 * Requires PHP: 7.4
 * Requires at least: 6.0
 */

if (!defined('ABSPATH')) {
    exit;
}

define('ORACLE_CORE_VERSION', '0.1.0');
define('ORACLE_CORE_FILE', __FILE__);
define('ORACLE_CORE_PATH', plugin_dir_path(__FILE__));
define('ORACLE_CORE_URL', plugin_dir_url(__FILE__));

spl_autoload_register(function ($class) {
    $prefix = 'OracleCore\\';
    $base_dir = ORACLE_CORE_PATH . 'src/';

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

register_activation_hook(__FILE__, ['OracleCore\\Core\\Activator', 'activate']);
register_deactivation_hook(__FILE__, ['OracleCore\\Core\\Activator', 'deactivate']);

add_action('plugins_loaded', function () {
    $plugin = new OracleCore\Core\Plugin();
    $plugin->boot();
});
