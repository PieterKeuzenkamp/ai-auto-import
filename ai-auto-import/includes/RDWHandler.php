<?php
namespace AIAutoImport;

class RDWHandler {
    private $api_key;
    private $api_url;

    public function __construct() {
        $this->api_key = getenv('RDW_API_KEY');
        $this->api_url = getenv('RDW_API_URL');

        if (!$this->api_key) {
            throw new \Exception('RDW API key not found in environment variables');
        }
    }

    public function get_vehicle_data($license_plate) {
        try {
            // Remove any dashes and convert to uppercase
            $license_plate = strtoupper(str_replace('-', '', $license_plate));

            // First, get basic vehicle info
            $basic_info = $this->fetch_basic_info($license_plate);
            
            // Then get additional details
            $technical_info = $this->fetch_technical_info($license_plate);
            
            // Combine the data
            return array_merge($basic_info, $technical_info);

        } catch (\Exception $e) {
            error_log('AI Auto Import RDW Error: ' . $e->getMessage());
            throw $e;
        }
    }

    private function fetch_basic_info($license_plate) {
        $endpoint = $this->api_url . '/resource/m9d7-ebf2.json';
        $query = http_build_query([
            'kenteken' => $license_plate,
            '$$app_token' => $this->api_key
        ]);

        $response = wp_remote_get($endpoint . '?' . $query);

        if (is_wp_error($response)) {
            throw new \Exception($response->get_error_message());
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);

        if (empty($data)) {
            throw new \Exception('Vehicle not found in RDW database');
        }

        return [
            'brand' => $data[0]['merk'] ?? '',
            'model' => $data[0]['handelsbenaming'] ?? '',
            'year' => $data[0]['datum_eerste_toelating'] ?? '',
            'fuel_type' => $data[0]['brandstof_omschrijving'] ?? '',
            'color' => $data[0]['eerste_kleur'] ?? '',
            'type' => $data[0]['voertuigsoort'] ?? '',
            'license_plate' => $license_plate
        ];
    }

    private function fetch_technical_info($license_plate) {
        $endpoint = $this->api_url . '/resource/vezc-m2t6.json';
        $query = http_build_query([
            'kenteken' => $license_plate,
            '$$app_token' => $this->api_key
        ]);

        $response = wp_remote_get($endpoint . '?' . $query);

        if (is_wp_error($response)) {
            throw new \Exception($response->get_error_message());
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);

        if (empty($data)) {
            return []; // Return empty array if no technical data found
        }

        return [
            'engine_size' => $data[0]['cilinderinhoud'] ?? '',
            'power_kw' => $data[0]['vermogen_massarijklaar'] ?? '',
            'transmission' => $data[0]['typegoedkeuring_transmissie_snelh'] ?? '',
            'weight' => $data[0]['massa_rijklaar'] ?? '',
            'doors' => $data[0]['aantal_deuren'] ?? '',
            'seats' => $data[0]['aantal_zitplaatsen'] ?? '',
            'emission_class' => $data[0]['emissiecode_omschrijving'] ?? ''
        ];
    }
}
