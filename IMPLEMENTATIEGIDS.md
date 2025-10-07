# AI Auto Import - Implementatiegids

## 📦 Wat je hebt ontvangen

1. **AI-Auto-Import-ANALYSE.md** - Volledige code analyse met 11 kritieke issues
2. **TODO-AI-Auto-Import.md** - Complete TODO lijst met prioriteiten
3. **refactored/** map met gefixte bestanden:
   - `ai-auto-import.php` - Main plugin file (schoon, geen duplicatie)
   - `Database.php` - Gefixte database schema
   - `Plugin.php` - Gefixte init met AJAX handler
   - `RDWHandler.php` - Volledig werkende RDW API handler

## 🚀 Snelle Start - Implementatie Stappen

### Stap 1: Backup Maken
```bash
# Maak backup van huidige plugin
cd wp-content/plugins
cp -r ai-auto-import ai-auto-import-backup-$(date +%Y%m%d)
```

### Stap 2: Vervang Bestanden

**BELANGRIJK:** Vervang deze bestanden in je plugin map:

```bash
# In: wp-content/plugins/ai-auto-import/

# 1. Vervang main plugin file
ai-auto-import.php       → gebruik refactored/ai-auto-import.php

# 2. Vervang includes files
includes/Database.php    → gebruik refactored/Database.php
includes/Plugin.php      → gebruik refactored/Plugin.php
includes/RDWHandler.php  → gebruik refactored/RDWHandler.php
```

### Stap 3: Deactiveer en Activeer Plugin

1. Ga naar WordPress Admin → Plugins
2. Deactiveer "AI Auto Import"
3. Activeer "AI Auto Import" opnieuw

Dit zal:
- Nieuwe database tabellen aanmaken
- Missing kolommen toevoegen
- Cache clearing triggeren

### Stap 4: Controleer Database

Run deze query in phpMyAdmin of WP-CLI om te controleren:

```sql
DESCRIBE wp_ai_auto_import_vehicles;
```

Je zou deze kolommen moeten zien:
- ✅ id
- ✅ post_id (NIEUW!)
- ✅ license_plate (NIEUW!)
- ✅ title
- ✅ description
- ✅ brand
- ✅ model
- ... etc

### Stap 5: Test de Import

1. Ga naar **AI Auto Import** in admin menu
2. Test met kenteken: `34-TN-FT`
3. Controleer of:
   - RDW data wordt opgehaald
   - Post wordt aangemaakt
   - Geen errors in debug log

## 🔧 Nog Te Doen

De volgende bestanden moet je ZELF nog updaten (of ik kan ze maken):

### Hoge Prioriteit

1. **includes/AIHandler.php**
   - Voeg fallback toe voor wanneer OpenAI API niet werkt
   - Maak API key optional
   ```php
   public function __construct() {
       $this->api_key = get_option('ai_auto_import_openai_key', '');
       $this->use_fallback = empty($this->api_key);
   }
   ```

2. **includes/OCRHandler.php**
   - Update license plate patterns (zie analyse rapport)
   - Voeg support toe voor meer kenteken formaten

3. **admin/Admin.php**
   - Fix de render_history_table() method
   - Update SQL query om rdw_data te parsen voor kenteken

4. **includes/PostCreator.php**
   - Voeg input validatie toe
   - Maak meta fields theme-agnostic
   - Check duplicate kentekens

5. **includes/AjaxHandler.php**
   - Voeg rate limiting toe
   - Verbeter error handling

### Maak Nieuw

6. **admin/Settings.php** (NIEUW!)
   - Settings page voor API keys
   - Post type selectie
   - Cache instellingen

```php
<?php
namespace AIAutoImport;

class Settings {
    public function init() {
        add_action('admin_menu', [$this, 'add_settings_page']);
        add_action('admin_init', [$this, 'register_settings']);
    }
    
    public function add_settings_page() {
        add_submenu_page(
            'ai-auto-import',
            __('Instellingen', 'ai-auto-import'),
            __('Instellingen', 'ai-auto-import'),
            'manage_options',
            'ai-auto-import-settings',
            [$this, 'render_settings_page']
        );
    }
    
    public function register_settings() {
        register_setting('ai_auto_import', 'ai_auto_import_openai_key');
        register_setting('ai_auto_import', 'ai_auto_import_vision_key');
        register_setting('ai_auto_import', 'ai_auto_import_post_type');
        register_setting('ai_auto_import', 'ai_auto_import_post_status');
    }
    
    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('AI Auto Import Instellingen', 'ai-auto-import'); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('ai_auto_import');
                do_settings_sections('ai_auto_import');
                ?>
                
                <table class="form-table">
                    <tr>
                        <th><?php _e('OpenAI API Key', 'ai-auto-import'); ?></th>
                        <td>
                            <input type="text" name="ai_auto_import_openai_key" 
                                   value="<?php echo esc_attr(get_option('ai_auto_import_openai_key')); ?>" 
                                   class="regular-text">
                            <p class="description">
                                <?php _e('Laat leeg om basis beschrijvingen te gebruiken', 'ai-auto-import'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th><?php _e('Google Vision API Key', 'ai-auto-import'); ?></th>
                        <td>
                            <input type="text" name="ai_auto_import_vision_key" 
                                   value="<?php echo esc_attr(get_option('ai_auto_import_vision_key')); ?>" 
                                   class="regular-text">
                            <p class="description">
                                <?php _e('Voor kenteken herkenning via foto upload', 'ai-auto-import'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th><?php _e('Post Type', 'ai-auto-import'); ?></th>
                        <td>
                            <?php
                            $post_types = get_post_types(['public' => true], 'objects');
                            $selected = get_option('ai_auto_import_post_type', 'listings');
                            ?>
                            <select name="ai_auto_import_post_type">
                                <?php foreach ($post_types as $post_type): ?>
                                    <option value="<?php echo esc_attr($post_type->name); ?>" 
                                            <?php selected($selected, $post_type->name); ?>>
                                        <?php echo esc_html($post_type->label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><?php _e('Post Status', 'ai-auto-import'); ?></th>
                        <td>
                            <?php $status = get_option('ai_auto_import_post_status', 'draft'); ?>
                            <select name="ai_auto_import_post_status">
                                <option value="draft" <?php selected($status, 'draft'); ?>>
                                    <?php _e('Concept', 'ai-auto-import'); ?>
                                </option>
                                <option value="publish" <?php selected($status, 'publish'); ?>>
                                    <?php _e('Gepubliceerd', 'ai-auto-import'); ?>
                                </option>
                            </select>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(); ?>
            </form>
            
            <hr>
            
            <h2><?php _e('Cache Beheer', 'ai-auto-import'); ?></h2>
            <p>
                <button class="button" onclick="clearRDWCache()">
                    <?php _e('Wis RDW Cache', 'ai-auto-import'); ?>
                </button>
            </p>
            
            <script>
            function clearRDWCache() {
                if (confirm('<?php _e('Weet je zeker dat je de cache wilt wissen?', 'ai-auto-import'); ?>')) {
                    jQuery.post(ajaxurl, {
                        action: 'ai_auto_import_clear_cache',
                        nonce: '<?php echo wp_create_nonce('clear_cache'); ?>'
                    }, function(response) {
                        alert(response.data.message);
                        location.reload();
                    });
                }
            }
            </script>
        </div>
        <?php
    }
}
```

## 🔍 Debug Tips

### Enable Debug Logging

In `wp-config.php`:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

Logs staan in: `wp-content/debug.log`

### Test RDW API Direct

```php
// Test via WP-CLI
wp eval '$rdw = new AIAutoImport\RDWHandler(); print_r($rdw->get_vehicle_data("34TNFT"));'

// Of maak test page
add_action('init', function() {
    if (isset($_GET['test_rdw'])) {
        $rdw = new AIAutoImport\RDWHandler();
        $data = $rdw->get_vehicle_data($_GET['kenteken']);
        echo '<pre>'; print_r($data); echo '</pre>'; die;
    }
});
// Gebruik: yourdomain.com/?test_rdw&kenteken=34-TN-FT
```

### Check Database Tables

```sql
-- Check if tables exist
SHOW TABLES LIKE 'wp_ai_auto_import%';

-- Check structure
DESCRIBE wp_ai_auto_import_vehicles;

-- Check for data
SELECT * FROM wp_ai_auto_import_vehicles ORDER BY created_at DESC LIMIT 5;
```

## 📊 Verwachte Resultaten

Na implementatie zou je moeten kunnen:

✅ Kenteken invoeren (bijv. 34-TN-FT)  
✅ RDW data ophalen zonder errors  
✅ Auto post aanmaken in WordPress  
✅ Data zien in history table  
✅ Cache werkt (2e aanroep is sneller)  

## ⚠️ Bekende Beperkingen

Na deze refactor werken:
- ✅ RDW data ophalen
- ✅ Database opslag
- ✅ Basic post creation
- ✅ Caching

Nog niet volledig werkend:
- ⚠️ AI beschrijvingen (vereist OpenAI key)
- ⚠️ Foto OCR (vereist Google Vision key)
- ⚠️ Settings page (moet nog gemaakt worden)
- ⚠️ Rate limiting (moet nog geïmplementeerd)

## 🆘 Hulp Nodig?

Als je errors krijgt, check:

1. **Fatal error over classes**: Autoloader probleem
   - Oplossing: Check of namespace correct is
   
2. **Database errors**: Tabellen niet aangemaakt
   - Oplossing: Deactiveer/activeer plugin opnieuw
   
3. **RDW API errors**: Kenteken niet gevonden
   - Oplossing: Check kenteken format (XX-XX-XX of XXXXXX)
   
4. **No data in history**: Posts niet aangemaakt
   - Oplossing: Check error logs, mogelijk post type bestaat niet

## 🎯 Next Steps

1. Implementeer bovenstaande fixes
2. Test grondig met verschillende kentekens
3. Voeg Settings page toe
4. Implementeer resterende features uit TODO lijst

Wil je dat ik ook de andere bestanden refactor? Laat het weten!
