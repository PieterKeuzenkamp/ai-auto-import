<?php
namespace AIAutoImport;

class Plugin {
    
    private $ajax_handler;
    
    public function init() {
        // Initialize components
        $this->init_hooks();
        
        // Initialize AJAX handler
        $this->ajax_handler = new AjaxHandler();
        $this->ajax_handler->init();
        
        // Initialize admin interface
        if (is_admin()) {
            $this->init_admin();
        }
        
        // Check for database updates
        add_action('admin_init', [Database::class, 'maybe_update_tables']);
        
        // Schedule cleanup cron
        if (!wp_next_scheduled('ai_auto_import_cleanup')) {
            wp_schedule_event(time(), 'daily', 'ai_auto_import_cleanup');
        }
    }

    private function init_hooks() {
        // Cleanup action
        add_action('ai_auto_import_cleanup', [Database::class, 'cleanup_old_logs']);
    }

    private function init_admin() {
        // Load admin class
        require_once AI_AUTO_IMPORT_PLUGIN_DIR . 'admin/Admin.php';
        $admin = new Admin();
        $admin->init();
        
        // Load settings class if exists
        if (file_exists(AI_AUTO_IMPORT_PLUGIN_DIR . 'admin/Settings.php')) {
            require_once AI_AUTO_IMPORT_PLUGIN_DIR . 'admin/Settings.php';
            $settings = new Settings();
            $settings->init();
        }
    }

    /**
     * Get plugin instance
     */
    public static function get_instance() {
        static $instance = null;
        
        if (null === $instance) {
            $instance = new self();
        }
        
        return $instance;
    }
}
