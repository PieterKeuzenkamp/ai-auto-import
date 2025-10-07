<?php
/**
 * Plugin Name: AI Auto Import
 * Plugin URI: https://www.pieterkeuzenkamp.nl/
 * Description: Automatisch auto's importeren met behulp van AI en RDW API
 * Version: 1.1.0
 * Author: Pieter Keuzenkamp
 * Author URI: https://www.pieterkeuzenkamp.nl/
 * Text Domain: ai-auto-import
 * Domain Path: /languages
 * Requires PHP: 7.4
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('AI_AUTO_IMPORT_VERSION', '1.1.0');
define('AI_AUTO_IMPORT_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('AI_AUTO_IMPORT_PLUGIN_URL', plugin_dir_url(__FILE__));
define('AI_AUTO_IMPORT_DEBUG', defined('WP_DEBUG') && WP_DEBUG);

// Autoloader
spl_autoload_register(function ($class) {
    $prefix = 'AIAutoImport\\';
    $base_dir = AI_AUTO_IMPORT_PLUGIN_DIR . 'includes/';

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

// Initialize plugin
function ai_auto_import_init() {
    // Load textdomain first
    load_plugin_textdomain('ai-auto-import', false, dirname(plugin_basename(__FILE__)) . '/languages/');
    
    // Initialize main plugin class
    if (class_exists('AIAutoImport\\Plugin')) {
        $plugin = new AIAutoImport\Plugin();
        $plugin->init();
    }
}
add_action('plugins_loaded', 'ai_auto_import_init');

// Activation hook
register_activation_hook(__FILE__, function() {
    require_once AI_AUTO_IMPORT_PLUGIN_DIR . 'includes/Database.php';
    if (class_exists('AIAutoImport\Database')) {
        AIAutoImport\Database::create_tables();
    }
    
    // Flush rewrite rules
    flush_rewrite_rules();
});

// Deactivation hook
register_deactivation_hook(__FILE__, function() {
    // Cleanup scheduled events
    wp_clear_scheduled_hook('ai_auto_import_cleanup');
    
    // Flush rewrite rules
    flush_rewrite_rules();
});

// Debugging function
if (!function_exists('ai_auto_import_log')) {
    function ai_auto_import_log($message, $level = 'info') {
        if (!AI_AUTO_IMPORT_DEBUG) {
            return;
        }
        
        $log_message = sprintf(
            '[AI Auto Import] [%s] %s',
            strtoupper($level),
            is_array($message) || is_object($message) ? print_r($message, true) : $message
        );
        
        error_log($log_message);
    }
}
