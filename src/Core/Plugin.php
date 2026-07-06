<?php
namespace OracleCore\Core;

use OracleCore\Admin\Admin;
use OracleCore\Assessment\Shortcode;

if (!defined('ABSPATH')) {
    exit;
}

final class Plugin
{
    public function boot(): void
    {
        add_action('init', [$this, 'loadTextdomain']);
        add_action('wp_enqueue_scripts', [$this, 'registerFrontendAssets']);
        add_action('admin_enqueue_scripts', [$this, 'registerAdminAssets']);

        (new Admin())->register();
        (new Shortcode())->register();
    }

    public function loadTextdomain(): void
    {
        load_plugin_textdomain('oracle-core', false, dirname(plugin_basename(ORACLE_CORE_FILE)) . '/languages');
    }

    public function registerFrontendAssets(): void
    {
        wp_register_style('oracle-core-frontend', ORACLE_CORE_URL . 'assets/css/frontend.css', [], ORACLE_CORE_VERSION);
        wp_register_script('oracle-core-frontend', ORACLE_CORE_URL . 'assets/js/frontend.js', [], ORACLE_CORE_VERSION, true);
    }

    public function registerAdminAssets(string $hook): void
    {
        if (strpos($hook, 'oracle-core') === false) {
            return;
        }

        wp_enqueue_style('oracle-core-admin', ORACLE_CORE_URL . 'assets/css/admin.css', [], ORACLE_CORE_VERSION);
    }
}
