<?php
namespace AIAutoImport;

class RDWHandler {
    
    private $api_url = 'https://opendata.rdw.nl';
    private $cache_duration = 7 * DAY_IN_SECONDS; // 7 dagen cache

    /**
     * Get complete vehicle data from RDW
     */
    public function get_vehicle_data($license_plate) {
        try {
            // Normalize license plate
            $license_plate = $this->normalize_license_plate($license_plate);
            
            // Try cache first
            $cached = $this->get_cached_data($license_plate);
            if ($cached !== false) {
                ai_auto_import_log("Using cached data for: {$license_plate}");
                return $cached;
            }

            // Fetch from RDW API
            $vehicle_data = $this->fetch_vehicle_info($license_plate);
            
            if (empty($vehicle_data)) {
                throw new \Exception(__('Voertuig niet gevonden in RDW database', 'ai-auto-import'));
            }
            
            // Cache the result
            $this->cache_data($license_plate, $vehicle_data);
            
            return $vehicle_data;

        } catch (\Exception $e) {
            ai_auto_import_log('RDW Error: ' . $e->getMessage(), 'error');
            Database::log('error', 'RDW API Error', [
                'license_plate' => $license_plate,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Normalize license plate format
     */
    private function normalize_license_plate($license_plate) {
        // Remove all non-alphanumeric characters and make uppercase
        return strtoupper(preg_replace('/[^A-Z0-9]/', '', $license_plate));
    }

    /**
     * Fetch vehicle information from RDW API
     */
    private function fetch_vehicle_info($license_plate) {
        $endpoint = $this->api_url . '/resource/m9d7-ebf2.json';
        $url = $endpoint . '?kenteken=' . urlencode($license_plate);

        ai_auto_import_log("Fetching RDW data from: {$url}");

        $response = wp_remote_get($url, [
            'timeout' => 15,
            'headers' => [
                'Accept' => 'application/json'
            ]
        ]);

        if (is_wp_error($response)) {
            throw new \Exception($response->get_error_message());
        }

        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            throw new \Exception(sprintf(
                __('RDW API geeft foutcode terug: %d', 'ai-auto-import'),
                $response_code
            ));
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception(__('Ongeldige JSON response van RDW API', 'ai-auto-import'));
        }

        if (empty($data) || !isset($data[0])) {
            return [];
        }

        $car = $data[0];
        
        // Map RDW data to our structure
        return $this->map_rdw_data($car, $license_plate);
    }

    /**
     * Map RDW API response to our internal structure
     */
    private function map_rdw_data($car, $license_plate) {
        return [
            // Basic info
            'license_plate' => $this->format_license_plate($license_plate),
            'brand' => $car['merk'] ?? '',
            'model' => $car['handelsbenaming'] ?? $car['type'] ?? '',
            'year' => isset($car['datum_eerste_toelating']) ? substr($car['datum_eerste_toelating'], 0, 4) : '',
            'first_registration' => $car['datum_eerste_toelating'] ?? '',
            
            // Technical specs
            'fuel_type' => $car['brandstof_omschrijving'] ?? '',
            'color' => $car['eerste_kleur'] ?? '',
            'type' => $car['voertuigsoort'] ?? '',
            'body_type' => $car['inrichting'] ?? '',
            'engine_size' => $car['cilinderinhoud'] ?? '',
            'transmission' => $this->determine_transmission($car),
            'doors' => $car['aantal_deuren'] ?? '',
            'seats' => $car['aantal_zitplaatsen'] ?? '',
            'power_kw' => $car['vermogen_massarijklaar'] ?? '',
            'weight' => $car['massa_rijklaar'] ?? $car['massa_ledig_voertuig'] ?? '',
            
            // Additional info
            'emission_class' => $car['emissieklasse'] ?? '',
            'co2_emission' => $car['nettomaximumvermogen'] ?? '',
            'catalog_price' => $car['catalogusprijs'] ?? '',
            
            // Raw data for reference
            'raw_rdw_data' => $car
        ];
    }

    /**
     * Format license plate to XX-XX-XX format
     */
    private function format_license_plate($plate) {
        $clean = $this->normalize_license_plate($plate);
        
        // If already 6 characters, format as XX-XX-XX
        if (strlen($clean) === 6) {
            return substr($clean, 0, 2) . '-' . substr($clean, 2, 2) . '-' . substr($clean, 4, 2);
        }
        
        // For other lengths, try common patterns
        if (strlen($clean) >= 5) {
            // Try to detect pattern and format accordingly
            // For now, return as-is with dashes every 2 chars
            return implode('-', str_split($clean, 2));
        }
        
        return $clean;
    }

    /**
     * Determine transmission type from RDW data
     */
    private function determine_transmission($car) {
        // Check various fields that might indicate transmission
        if (isset($car['transmissie_soort'])) {
            return $car['transmissie_soort'];
        }
        
        if (isset($car['typegoedkeuringsnummer'])) {
            $type = strtolower($car['typegoedkeuringsnummer']);
            if (strpos($type, 'automaat') !== false || strpos($type, 'automatic') !== false) {
                return 'Automaat';
            }
            if (strpos($type, 'handgeschakeld') !== false || strpos($type, 'manual') !== false) {
                return 'Handgeschakeld';
            }
        }
        
        return 'Onbekend';
    }

    /**
     * Get cached vehicle data
     */
    private function get_cached_data($license_plate) {
        $cache_key = 'rdw_data_' . md5($license_plate);
        return get_transient($cache_key);
    }

    /**
     * Cache vehicle data
     */
    private function cache_data($license_plate, $data) {
        $cache_key = 'rdw_data_' . md5($license_plate);
        set_transient($cache_key, $data, $this->cache_duration);
    }

    /**
     * Clear cache for specific license plate
     */
    public function clear_cache($license_plate) {
        $license_plate = $this->normalize_license_plate($license_plate);
        $cache_key = 'rdw_data_' . md5($license_plate);
        delete_transient($cache_key);
    }

    /**
     * Clear all RDW cache
     */
    public static function clear_all_cache() {
        global $wpdb;
        $wpdb->query(
            "DELETE FROM {$wpdb->options} 
            WHERE option_name LIKE '_transient_rdw_data_%' 
            OR option_name LIKE '_transient_timeout_rdw_data_%'"
        );
    }
}
