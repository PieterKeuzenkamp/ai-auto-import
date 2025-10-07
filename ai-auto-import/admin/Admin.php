<?php
namespace AIAutoImport;

class Admin {
    public function init() {
        add_action('admin_menu', [$this, 'add_menu_page']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
    }

    public function add_menu_page() {
        add_menu_page(
            'AI Auto Import',
            'AI Auto Import',
            'manage_options',
            'ai-auto-import',
            [$this, 'render_admin_page'],
            'dashicons-car',
            20
        );
    }

    public function enqueue_scripts($hook) {
        if ('toplevel_page_ai-auto-import' !== $hook) {
            return;
        }

        wp_enqueue_style('ai-auto-import-admin', plugins_url('assets/css/admin.css', dirname(__FILE__)));
        wp_enqueue_script('ai-auto-import-admin', plugins_url('assets/js/admin.js', dirname(__FILE__)), ['jquery'], false, true);
        
        wp_localize_script('ai-auto-import-admin', 'aiAutoImport', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ai-auto-import-nonce')
        ]);
    }

    public function render_admin_page() {
        ?>
        <div class="wrap">
            <h1>AI Auto Import</h1>
            
            <div class="ai-auto-import-container">
                <div class="ai-auto-import-upload">
                    <h2>Upload Kentekenfoto</h2>
                    <form id="ai-auto-import-form" method="post" enctype="multipart/form-data">
                        <?php wp_nonce_field('ai-auto-import-upload', 'ai-auto-import-nonce'); ?>
                        
                        <div class="upload-area" id="upload-area">
                            <input type="file" name="car_photo" id="car-photo" accept="image/*" required>
                            <label for="car-photo">
                                <span class="dashicons dashicons-upload"></span>
                                <span>Sleep een foto hier naartoe of klik om te uploaden</span>
                            </label>
                        </div>

                        <div id="preview-area" style="display: none;">
                            <img id="photo-preview" src="" alt="Preview">
                            <button type="button" id="remove-photo" class="button">Verwijder foto</button>
                        </div>

                        <button type="submit" class="button button-primary">Verwerk Foto</button>
                    </form>
                </div>

                <div id="results-area" style="display: none;">
                    <h2>Resultaten</h2>
                    <div class="results-container">
                        <div class="loading">
                            <span class="spinner is-active"></span>
                            <span>Bezig met verwerken...</span>
                        </div>
                        <div class="results-content"></div>
                    </div>
                </div>

                <div class="ai-auto-import-history">
                    <h2>Recente Imports</h2>
                    <?php $this->render_history_table(); ?>
                </div>
            </div>
        </div>
        <?php
    }

    private function render_history_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ai_auto_import_vehicles';
        
        $items = $wpdb->get_results("
            SELECT * FROM {$table_name}
            ORDER BY created_at DESC
            LIMIT 10
        ");

        if (empty($items)) {
            echo '<p>Geen recente imports gevonden.</p>';
            return;
        }

        ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Kenteken</th>
                    <th>Status</th>
                    <th>Post</th>
                    <th>Datum</th>
                    <th>Acties</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                    <tr>
                        <td><?php echo esc_html($item->license_plate); ?></td>
                        <td><?php echo esc_html($item->status); ?></td>
                        <td>
                            <?php if ($item->post_id): ?>
                                <a href="<?php echo get_edit_post_link($item->post_id); ?>">
                                    Bewerk Post
                                </a>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html($item->created_at); ?></td>
                        <td>
                            <button class="button retry-import" data-id="<?php echo esc_attr($item->id); ?>">
                                Opnieuw Proberen
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }
}
