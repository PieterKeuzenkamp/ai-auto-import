<?php
namespace AIAutoImport;

class AIHandler {
    private $api_key;
    private $api_url;

    public function __construct() {
        $this->api_key = getenv('OPENAI_API_KEY');
        $this->api_url = getenv('OPENAI_API_URL');

        if (!$this->api_key) {
            throw new \Exception('OpenAI API key not found in environment variables');
        }
    }

    public function generate_vehicle_description($vehicle_data) {
        try {
            $prompt = $this->create_prompt($vehicle_data);
            
            $response = wp_remote_post($this->api_url . '/v1/chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->api_key,
                    'Content-Type' => 'application/json'
                ],
                'body' => json_encode([
                    'model' => 'gpt-4',
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => 'Je bent een ervaren autoverkoper die aantrekkelijke en informatieve beschrijvingen schrijft voor occasions. Schrijf in een professionele maar toegankelijke stijl.'
                        ],
                        [
                            'role' => 'user',
                            'content' => $prompt
                        ]
                    ],
                    'temperature' => 0.7,
                    'max_tokens' => 500
                ])
            ]);

            if (is_wp_error($response)) {
                throw new \Exception($response->get_error_message());
            }

            $data = json_decode(wp_remote_retrieve_body($response), true);
            
            if (isset($data['error'])) {
                throw new \Exception($data['error']['message']);
            }

            return [
                'description' => $data['choices'][0]['message']['content'] ?? '',
                'meta_description' => $this->generate_meta_description($vehicle_data),
                'keywords' => $this->generate_keywords($vehicle_data)
            ];

        } catch (\Exception $e) {
            error_log('AI Auto Import OpenAI Error: ' . $e->getMessage());
            throw $e;
        }
    }

    private function create_prompt($vehicle_data) {
        return sprintf(
            "Schrijf een aantrekkelijke beschrijving voor deze auto:\n\n" .
            "Merk: %s\n" .
            "Model: %s\n" .
            "Bouwjaar: %s\n" .
            "Brandstof: %s\n" .
            "Kleur: %s\n" .
            "Motor: %s cc\n" .
            "Vermogen: %s kW\n" .
            "Transmissie: %s\n" .
            "Aantal deuren: %s\n" .
            "Aantal zitplaatsen: %s\n\n" .
            "Schrijf een wervende tekst van ongeveer 250 woorden die de belangrijkste kenmerken en voordelen van de auto beschrijft. " .
            "Gebruik een professionele maar toegankelijke toon.",
            $vehicle_data['brand'],
            $vehicle_data['model'],
            $vehicle_data['year'],
            $vehicle_data['fuel_type'],
            $vehicle_data['color'],
            $vehicle_data['engine_size'],
            $vehicle_data['power_kw'],
            $vehicle_data['transmission'],
            $vehicle_data['doors'],
            $vehicle_data['seats']
        );
    }

    private function generate_meta_description($vehicle_data) {
        try {
            $response = wp_remote_post($this->api_url . '/v1/chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->api_key,
                    'Content-Type' => 'application/json'
                ],
                'body' => json_encode([
                    'model' => 'gpt-4',
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => 'Genereer een SEO-vriendelijke meta beschrijving van maximaal 155 karakters.'
                        ],
                        [
                            'role' => 'user',
                            'content' => sprintf(
                                "Maak een meta description voor deze %s %s uit %s met %s motor.",
                                $vehicle_data['brand'],
                                $vehicle_data['model'],
                                $vehicle_data['year'],
                                $vehicle_data['fuel_type']
                            )
                        ]
                    ],
                    'temperature' => 0.7,
                    'max_tokens' => 100
                ])
            ]);

            $data = json_decode(wp_remote_retrieve_body($response), true);
            return $data['choices'][0]['message']['content'] ?? '';

        } catch (\Exception $e) {
            error_log('AI Auto Import Meta Description Error: ' . $e->getMessage());
            return '';
        }
    }

    private function generate_keywords($vehicle_data) {
        try {
            $response = wp_remote_post($this->api_url . '/v1/chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->api_key,
                    'Content-Type' => 'application/json'
                ],
                'body' => json_encode([
                    'model' => 'gpt-4',
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => 'Genereer een lijst van 10 relevante zoekwoorden, gescheiden door komma\'s.'
                        ],
                        [
                            'role' => 'user',
                            'content' => sprintf(
                                "Genereer zoekwoorden voor deze auto: %s %s %s %s",
                                $vehicle_data['brand'],
                                $vehicle_data['model'],
                                $vehicle_data['year'],
                                $vehicle_data['fuel_type']
                            )
                        ]
                    ],
                    'temperature' => 0.7,
                    'max_tokens' => 100
                ])
            ]);

            $data = json_decode(wp_remote_retrieve_body($response), true);
            return $data['choices'][0]['message']['content'] ?? '';

        } catch (\Exception $e) {
            error_log('AI Auto Import Keywords Error: ' . $e->getMessage());
            return '';
        }
    }
}
