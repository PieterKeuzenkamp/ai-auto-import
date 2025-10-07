<?php
namespace AIAutoImport;

class Plugin {
    public function init() {
        // Initialize components
        $this->init_hooks();
        
        if (is_admin()) {
            $this->init_admin();
        }
    }

    private function init_hooks() {
        add_action('init', [$this, 'load_textdomain']);
    }

    private function init_admin() {
        require_once AI_AUTO_IMPORT_PLUGIN_DIR . 'admin/Admin.php';
        $admin = new Admin();
        $admin->init();
    }

    public function load_textdomain() {
        load_plugin_textdomain('ai-auto-import', false, dirname(plugin_basename(AI_AUTO_IMPORT_PLUGIN_DIR)) . '/languages/');
    }
}
