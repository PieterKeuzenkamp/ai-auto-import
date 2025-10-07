<?php
namespace AIAutoImport;

class PostCreator {
    private $vehicle_data;
    private $ai_content;

    public function __construct($vehicle_data, $ai_content) {
        $this->vehicle_data = $vehicle_data;
        $this->ai_content = $ai_content;
    }

    public function create_vehicle_post() {
        try {
            // Create post object
            $post_data = [
                'post_title'    => $this->generate_title(),
                'post_content'  => $this->ai_content['description'],
                'post_status'   => 'draft',
                'post_type'     => 'listings', // Motors Theme post type
                'meta_input'    => $this->generate_meta_fields()
            ];

            // Insert the post into the database
            $post_id = wp_insert_post($post_data);

            if (is_wp_error($post_id)) {
                throw new \Exception($post_id->get_error_message());
            }

            // Set taxonomies
            $this->set_taxonomies($post_id);

            // Add to database for tracking
            global $wpdb;
            $wpdb->insert(
                $wpdb->prefix . 'ai_auto_import_vehicles',
                [
                    'post_id' => $post_id,
                    'license_plate' => $this->vehicle_data['license_plate'],
                    'rdw_data' => json_encode($this->vehicle_data),
                    'ai_analysis' => json_encode($this->ai_content),
                    'status' => 'draft'
                ]
            );

            return $post_id;

        } catch (\Exception $e) {
            error_log('AI Auto Import Post Creation Error: ' . $e->getMessage());
            throw $e;
        }
    }

    private function generate_title() {
        return sprintf(
            '%s %s %s - %s',
            $this->vehicle_data['brand'],
            $this->vehicle_data['model'],
            $this->vehicle_data['year'],
            $this->vehicle_data['fuel_type']
        );
    }

    private function generate_meta_fields() {
        return [
            // Motors Theme specific meta fields
            'price' => '', // To be set manually
            'mileage' => '', // To be set manually
            'fuel' => $this->vehicle_data['fuel_type'],
            'engine' => $this->vehicle_data['engine_size'],
            'transmission' => $this->vehicle_data['transmission'],
            'doors' => $this->vehicle_data['doors'],
            'body' => $this->vehicle_data['type'],
            'drive' => '',
            'color' => $this->vehicle_data['color'],
            'interior_color' => '',
            'stock_number' => $this->vehicle_data['license_plate'],
            'vin' => '',
            'car_year' => substr($this->vehicle_data['year'], 0, 4),
            'power' => $this->vehicle_data['power_kw'],
            
            // SEO meta fields
            '_yoast_wpseo_metadesc' => $this->ai_content['meta_description'],
            '_yoast_wpseo_focuskw' => $this->vehicle_data['brand'] . ' ' . $this->vehicle_data['model'],
            '_yoast_wpseo_keywordsynonyms' => $this->ai_content['keywords'],
            
            // Custom fields for our plugin
            '_ai_auto_import_rdw_data' => json_encode($this->vehicle_data),
            '_ai_auto_import_ai_content' => json_encode($this->ai_content)
        ];
    }

    private function set_taxonomies($post_id) {
        // Set make (brand)
        wp_set_object_terms($post_id, $this->vehicle_data['brand'], 'make');
        
        // Set model
        wp_set_object_terms($post_id, $this->vehicle_data['model'], 'serie');
        
        // Set fuel type
        wp_set_object_terms($post_id, $this->vehicle_data['fuel_type'], 'fuel');
        
        // Set body type
        wp_set_object_terms($post_id, $this->vehicle_data['type'], 'body');
        
        // Set transmission
        wp_set_object_terms($post_id, $this->vehicle_data['transmission'], 'transmission');
        
        // Set condition (default to used)
        wp_set_object_terms($post_id, 'used', 'condition');
    }
}
