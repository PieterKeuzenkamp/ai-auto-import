<?php
namespace AIAutoImport;

class AjaxHandler {
    public function init() {
        add_action('wp_ajax_ai_auto_import_process', [$this, 'process_upload']);
        add_action('wp_ajax_ai_auto_import_retry', [$this, 'retry_import']);
    }

    public function process_upload() {
        try {
            if (!check_ajax_referer('ai-auto-import-nonce', 'nonce', false)) {
                throw new \Exception('Invalid nonce');
            }

            if (!current_user_can('manage_options')) {
                throw new \Exception('Insufficient permissions');
            }

            if (!isset($_FILES['car_photo'])) {
                throw new \Exception('Geen foto geüpload');
            }

            // Process the photo using OCR
            $ocr = new OCRHandler();
            $license_plate = $ocr->process_car_photo($_FILES['car_photo']);

            // Get vehicle data from RDW
            $rdw = new RDWHandler();
            $vehicle_data = $rdw->get_vehicle_data($license_plate);

            // Generate AI content
            $ai = new AIHandler();
            $ai_content = $ai->generate_vehicle_description($vehicle_data);

            // Create the post
            $post_creator = new PostCreator($vehicle_data, $ai_content);
            $post_id = $post_creator->create_vehicle_post();

            wp_send_json_success([
                'license_plate' => $license_plate,
                'brand' => $vehicle_data['brand'],
                'model' => $vehicle_data['model'],
                'year' => $vehicle_data['year'],
                'edit_url' => get_edit_post_link($post_id, 'url')
            ]);

        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => $e->getMessage()
            ]);
        }
    }

    public function retry_import() {
        try {
            if (!check_ajax_referer('ai-auto-import-nonce', 'nonce', false)) {
                throw new \Exception('Invalid nonce');
            }

            if (!current_user_can('manage_options')) {
                throw new \Exception('Insufficient permissions');
            }

            $id = intval($_POST['id']);
            
            global $wpdb;
            $table_name = $wpdb->prefix . 'ai_auto_import_vehicles';
            
            $vehicle = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$table_name} WHERE id = %d",
                $id
            ));

            if (!$vehicle) {
                throw new \Exception('Vehicle not found');
            }

            // Get fresh vehicle data from RDW
            $rdw = new RDWHandler();
            $vehicle_data = $rdw->get_vehicle_data($vehicle->license_plate);

            // Generate new AI content
            $ai = new AIHandler();
            $ai_content = $ai->generate_vehicle_description($vehicle_data);

            // Create new post
            $post_creator = new PostCreator($vehicle_data, $ai_content);
            $post_id = $post_creator->create_vehicle_post();

            // Update database record
            $wpdb->update(
                $table_name,
                [
                    'post_id' => $post_id,
                    'rdw_data' => json_encode($vehicle_data),
                    'ai_analysis' => json_encode($ai_content),
                    'status' => 'draft',
                    'updated_at' => current_time('mysql')
                ],
                ['id' => $id]
            );

            wp_send_json_success();

        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => $e->getMessage()
            ]);
        }
    }
}
