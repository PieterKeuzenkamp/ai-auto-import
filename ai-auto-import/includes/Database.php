<?php
namespace AIAutoImport;

class Database {
    
    /**
     * Create necessary database tables
     */
    public static function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        // Main vehicles table
        $sql = "CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}ai_auto_import_vehicles` (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            post_id bigint(20) DEFAULT NULL,
            license_plate varchar(20) DEFAULT NULL,
            title varchar(255) NOT NULL,
            description longtext,
            brand varchar(100),
            model varchar(100),
            year int(4),
            price decimal(10,2),
            mileage int(11),
            fuel_type varchar(50),
            transmission varchar(50),
            rdw_data longtext,
            ai_analysis longtext,
            status varchar(20) DEFAULT 'pending',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY license_plate (license_plate),
            KEY post_id (post_id),
            KEY status (status)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        // Logs table for debugging and audit
        $sql_logs = "CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}ai_auto_import_logs` (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            level varchar(20) NOT NULL,
            message text NOT NULL,
            context longtext,
            user_id bigint(20),
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY level (level),
            KEY created_at (created_at)
        ) $charset_collate;";

        dbDelta($sql_logs);

        // Failed imports table for retry functionality
        $sql_failed = "CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}ai_auto_import_failed` (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            license_plate varchar(20),
            error_message text,
            error_code varchar(50),
            raw_data longtext,
            retry_count int(11) DEFAULT 0,
            last_retry_at datetime,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY license_plate (license_plate)
        ) $charset_collate;";

        dbDelta($sql_failed);

        // Update database version
        update_option('ai_auto_import_db_version', '1.1.0');
    }

    /**
     * Check if tables need update
     */
    public static function maybe_update_tables() {
        $current_version = get_option('ai_auto_import_db_version', '0');
        
        if (version_compare($current_version, '1.1.0', '<')) {
            self::create_tables();
        }
    }

    /**
     * Log an entry to the database
     */
    public static function log($level, $message, $context = []) {
        global $wpdb;
        
        $wpdb->insert(
            $wpdb->prefix . 'ai_auto_import_logs',
            [
                'level' => $level,
                'message' => $message,
                'context' => json_encode($context),
                'user_id' => get_current_user_id(),
                'created_at' => current_time('mysql')
            ],
            ['%s', '%s', '%s', '%d', '%s']
        );
    }

    /**
     * Save failed import for later retry
     */
    public static function save_failed_import($license_plate, $error_message, $error_code = '', $raw_data = []) {
        global $wpdb;
        
        $wpdb->insert(
            $wpdb->prefix . 'ai_auto_import_failed',
            [
                'license_plate' => $license_plate,
                'error_message' => $error_message,
                'error_code' => $error_code,
                'raw_data' => json_encode($raw_data),
                'created_at' => current_time('mysql')
            ],
            ['%s', '%s', '%s', '%s', '%s']
        );
    }

    /**
     * Clean old log entries (older than 30 days)
     */
    public static function cleanup_old_logs() {
        global $wpdb;
        
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->prefix}ai_auto_import_logs 
                WHERE created_at < %s",
                date('Y-m-d H:i:s', strtotime('-30 days'))
            )
        );
    }
}
