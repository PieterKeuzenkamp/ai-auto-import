<?php
namespace AIAutoImport;

class OCRHandler {
    private $api_key;
    private $api_url;

    public function __construct() {
        $this->api_key = getenv('GOOGLE_VISION_API_KEY');
        $this->api_url = getenv('GOOGLE_VISION_API_URL');

        if (!$this->api_key) {
            throw new \Exception('Google Vision API key not found in environment variables');
        }
    }

    public function process_car_photo($photo) {
        try {
            $image_path = $photo['tmp_name'];
            $api_url = $this->api_url . '?key=' . $this->api_key;

            // Validate image
            if (!$this->validate_image($photo)) {
                throw new \Exception('Invalid image file');
            }

            $image_data = base64_encode(file_get_contents($image_path));
            $payload = json_encode([
                'requests' => [
                    [
                        'image' => ['content' => $image_data],
                        'features' => [
                            ['type' => 'TEXT_DETECTION'],
                            ['type' => 'OBJECT_LOCALIZATION'] // Also detect car features
                        ]
                    ]
                ]
            ]);

            $response = wp_remote_post($api_url, [
                'body' => $payload,
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json'
                ]
            ]);

            if (is_wp_error($response)) {
                throw new \Exception($response->get_error_message());
            }

            $body = json_decode(wp_remote_retrieve_body($response), true);
            
            // Extract license plate using pattern matching
            $license_plate = $this->extract_license_plate($body);
            
            if (!$license_plate) {
                throw new \Exception('No license plate detected in image');
            }

            return $license_plate;

        } catch (\Exception $e) {
            error_log('AI Auto Import OCR Error: ' . $e->getMessage());
            throw $e;
        }
    }

    private function validate_image($photo) {
        $allowed_types = ['image/jpeg', 'image/png'];
        $max_size = 5 * 1024 * 1024; // 5MB

        if (!in_array($photo['type'], $allowed_types)) {
            return false;
        }

        if ($photo['size'] > $max_size) {
            return false;
        }

        return true;
    }

    private function extract_license_plate($vision_response) {
        if (empty($vision_response['responses'][0]['textAnnotations'])) {
            return null;
        }

        $texts = $vision_response['responses'][0]['textAnnotations'];
        
        // Dutch license plate pattern
        $pattern = '/^[A-Z0-9]{1,3}-[A-Z0-9]{1,3}-[A-Z0-9]{1,3}$/';
        
        foreach ($texts as $text) {
            $potential_plate = str_replace(' ', '', $text['description']);
            if (preg_match($pattern, $potential_plate)) {
                return $potential_plate;
            }
        }

        return null;
    }
}
