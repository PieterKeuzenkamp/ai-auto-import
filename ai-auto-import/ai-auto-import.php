<?php
/**
 * Plugin Name: AI Auto Import
 * Plugin URI: https://www.pieterkeuzenkamp.nl/
 * Description: Automatisch auto's importeren met behulp van AI en RDW API
 * Version: 1.0.1
 * Author: Pieter Keuzenkamp
 * Author URI: https://www.pieterkeuzenkamp.nl/
 * Text Domain: ai-auto-import
 * Domain Path: /languages
 * Requires PHP: 7.4
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('AI_AUTO_IMPORT_VERSION', '1.0.1');
define('AI_AUTO_IMPORT_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('AI_AUTO_IMPORT_PLUGIN_URL', plugin_dir_url(__FILE__));

// Autoloader
spl_autoload_register(function ($class) {
    $prefix = 'AIAutoImport\\';
    $base_dir = AI_AUTO_IMPORT_PLUGIN_DIR . 'includes/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

// Initialize plugin
function ai_auto_import_init() {
    // Initialize main plugin class
    if (class_exists('AIAutoImport\\Plugin')) {
        $plugin = new AIAutoImport\Plugin();
        $plugin->init();
    }
}

function ai_auto_import_load_textdomain() {
    load_plugin_textdomain('ai-auto-import', false, dirname(plugin_basename(__FILE__)) . '/languages/');
}

add_action('plugins_loaded', 'ai_auto_import_init');
add_action('init', 'ai_auto_import_load_textdomain');

// Activation hook
register_activation_hook(__FILE__, function() {
    // Create necessary database tables
    require_once AI_AUTO_IMPORT_PLUGIN_DIR . 'includes/Database.php';
    if (class_exists('AIAutoImport\Database')) {
        AIAutoImport\Database::create_tables();
    }
});

// Debugging function to log messages
function ai_auto_import_debug_log($message) {
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('[AI Auto Import] ' . (is_array($message) || is_object($message) ? print_r($message, true) : $message));
    }
}

// Fetch car data from RDW API
function ai_auto_import_fetch_car_data($kenteken) {
    // Zorg ervoor dat $kenteken niet leeg is
    if (empty($kenteken)) {
        ai_auto_import_debug_log(__('Kenteken is niet opgegeven.', 'ai-auto-import'));
        return array('success' => false, 'message' => 'Kenteken is niet opgegeven');
    }

    // Verwijder spaties en maak hoofdletters
    $kenteken = strtoupper(str_replace([' ', '-'], '', $kenteken));

    // Correcte opbouw van de API URL
    $api_url = 'https://opendata.rdw.nl/resource/m9d7-ebf2.json?kenteken=' . urlencode($kenteken);
    ai_auto_import_debug_log('API URL: ' . $api_url);

    $response = wp_remote_get($api_url, array(
        'timeout' => 15,
        'headers' => array(
            'Accept' => 'application/json'
        )
    ));

    // Controleer op fouten in de API-aanroep
    if (is_wp_error($response)) {
        ai_auto_import_debug_log('Fout bij het ophalen van gegevens: ' . $response->get_error_message());
        return array('success' => false, 'message' => $response->get_error_message());
    }

    $response_code = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);
    
    ai_auto_import_debug_log('Response code: ' . $response_code);
    ai_auto_import_debug_log('Response body: ' . $body);

    if ($response_code !== 200) {
        ai_auto_import_debug_log('API response code: ' . $response_code);
        return array('success' => false, 'message' => 'API fout: ' . $response_code);
    }

    $car_data = json_decode($body, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        ai_auto_import_debug_log('JSON decode error: ' . json_last_error_msg());
        return array('success' => false, 'message' => 'JSON decode error');
    }

    if (!empty($car_data) && is_array($car_data) && isset($car_data[0])) {
        $post_id = ai_auto_import_create_car_post($car_data[0]);
        
        if ($post_id && !is_wp_error($post_id)) {
            return array('success' => true, 'post_id' => $post_id, 'message' => 'Auto succesvol geïmporteerd');
        } else {
            $error_msg = is_wp_error($post_id) ? $post_id->get_error_message() : 'Onbekende fout bij aanmaken post';
            return array('success' => false, 'message' => $error_msg);
        }
    } else {
        ai_auto_import_debug_log(__('Geen gegevens gevonden voor kenteken: ', 'ai-auto-import') . esc_html($kenteken));
        return array('success' => false, 'message' => 'Geen gegevens gevonden voor dit kenteken');
    }
}

// Process license plate image using Google Cloud Vision API
function ai_auto_import_process_license_plate_image($image_path) {
    // Check if Google Cloud Vision library is available
    if (!class_exists('Google\Cloud\Vision\V1\ImageAnnotatorClient')) {
        ai_auto_import_debug_log('Google Cloud Vision library not found');
        return false;
    }

    try {
        // Google Cloud Vision API setup
        $credentials_path = AI_AUTO_IMPORT_PLUGIN_DIR . 'credentials.json';
        
        if (!file_exists($credentials_path)) {
            ai_auto_import_debug_log('Credentials file not found: ' . $credentials_path);
            return false;
        }

        $vision = new Google\Cloud\Vision\V1\ImageAnnotatorClient([
            'credentials' => json_decode(file_get_contents($credentials_path), true)
        ]);

        // Read image
        if (!file_exists($image_path)) {
            ai_auto_import_debug_log('Image file not found: ' . $image_path);
            return false;
        }

        $image = file_get_contents($image_path);
        $response = $vision->textDetection($image);
        $texts = $response->getTextAnnotations();

        if ($texts) {
            $license_plate = $texts[0]->getDescription();
            // Clean up the license plate text (remove spaces, newlines, etc.)
            $license_plate = preg_replace('/[^A-Z0-9]/', '', strtoupper($license_plate));
            
            return $license_plate;
        }
    } catch (Exception $e) {
        ai_auto_import_debug_log('Vision API error: ' . $e->getMessage());
        return false;
    }

    return false;
}

// Create car post
function ai_auto_import_create_car_post($car_data) {
    // Valideer dat we de minimale vereiste gegevens hebben
    if (!is_array($car_data)) {
        ai_auto_import_debug_log('Car data is geen array');
        return new WP_Error('invalid_data', 'Car data is geen array');
    }

    // Bepaal titel op basis van beschikbare gegevens
    $title_parts = array();
    
    if (!empty($car_data['merk'])) {
        $title_parts[] = $car_data['merk'];
    }
    
    if (!empty($car_data['handelsbenaming'])) {
        $title_parts[] = $car_data['handelsbenaming'];
    } elseif (!empty($car_data['model'])) {
        $title_parts[] = $car_data['model'];
    }
    
    if (!empty($car_data['kenteken'])) {
        $title_parts[] = '(' . $car_data['kenteken'] . ')';
    }

    $post_title = !empty($title_parts) ? implode(' ', $title_parts) : 'Onbekende auto';

    $post_data = array(
        'post_title'    => wp_strip_all_tags($post_title),
        'post_content'  => ai_auto_import_generate_car_description($car_data),
        'post_status'   => 'draft',
        'post_type'     => 'listings'
    );

    ai_auto_import_debug_log('Creating post with data: ' . print_r($post_data, true));

    $post_id = wp_insert_post($post_data, true);

    if (is_wp_error($post_id)) {
        ai_auto_import_debug_log('Error creating post: ' . $post_id->get_error_message());
        return $post_id;
    }

    if ($post_id) {
        // Add car details as post meta
        foreach ($car_data as $key => $value) {
            if (!empty($value)) {
                update_post_meta($post_id, 'stm_' . sanitize_key($key), sanitize_text_field($value));
            }
        }
        
        ai_auto_import_debug_log('Post created successfully with ID: ' . $post_id);
    }

    return $post_id;
}

// Generate car description
function ai_auto_import_generate_car_description($car_data) {
    $description = '';
    
    // Bouw een basis beschrijving op basis van beschikbare data
    $fields_to_include = array(
        'merk' => 'Merk',
        'handelsbenaming' => 'Model',
        'kenteken' => 'Kenteken',
        'datum_eerste_toelating' => 'Datum eerste toelating',
        'voertuigsoort' => 'Voertuigsoort',
        'inrichting' => 'Inrichting',
        'aantal_deuren' => 'Aantal deuren',
        'aantal_zitplaatsen' => 'Aantal zitplaatsen',
        'brandstof_omschrijving' => 'Brandstof',
        'transmissie_soort' => 'Transmissie',
        'cilinderinhoud' => 'Cilinderinhoud',
        'massa_ledig_voertuig' => 'Massa ledig voertuig',
        'kleur' => 'Kleur'
    );

    foreach ($fields_to_include as $key => $label) {
        if (!empty($car_data[$key])) {
            $description .= '<strong>' . $label . ':</strong> ' . esc_html($car_data[$key]) . '<br>';
        }
    }

    return $description;
}

// Admin test interface
add_action('admin_menu', function() {
    add_submenu_page(
        'tools.php',
        'AI Auto Import Test',
        'AI Auto Import Test',
        'manage_options',
        'ai-auto-import-test',
        'ai_auto_import_test_page'
    );
});

function ai_auto_import_test_page() {
    if (!current_user_can('manage_options')) {
        return;
    }

    $result = '';
    if (isset($_POST['test_kenteken']) && check_admin_referer('ai_auto_import_test')) {
        $kenteken = sanitize_text_field($_POST['kenteken']);
        $result = ai_auto_import_fetch_car_data($kenteken);
    }

    ?>
    <div class="wrap">
        <h1>AI Auto Import Test</h1>
        <form method="post">
            <?php wp_nonce_field('ai_auto_import_test'); ?>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="kenteken">Kenteken</label>
                    </th>
                    <td>
                        <input type="text" name="kenteken" id="kenteken" value="34-TN-FT" class="regular-text">
                        <p class="description">Voer een Nederlands kenteken in (bijv. 34-TN-FT)</p>
                    </td>
                </tr>
            </table>
            <?php submit_button('Test Import', 'primary', 'test_kenteken'); ?>
        </form>
        
        <?php if (!empty($result)): ?>
            <div class="notice notice-<?php echo $result['success'] ? 'success' : 'error'; ?>">
                <p><strong>Resultaat:</strong> <?php echo esc_html($result['message']); ?></p>
                <?php if ($result['success'] && isset($result['post_id'])): ?>
                    <p><a href="<?php echo get_edit_post_link($result['post_id']); ?>">Bekijk geïmporteerde auto</a></p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
    <?php
}