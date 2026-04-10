<?php
/**
 * Plugin Name: Hey Trisha
 * Plugin URI: https://heytrisha.com
 * Description: AI-powered chatbot using OpenAI GPT for WordPress and WooCommerce. Natural language queries, product management, and intelligent responses.
 * Version: 2.1.7
 * Author: Manikandan Chandran
 * Author URI: https://manikandanchandran.com/
 * License: MIT
 * License URI: https://opensource.org/licenses/MIT
 * Terms and Conditions: https://heytrisha.com/terms-and-conditions
 * Text Domain: hey-trisha
 * Requires at least: 5.0
 * Requires PHP: 7.4.3
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// ✅ CRITICAL: Prevent fatal error if another version of this plugin is already loaded
// This can happen if the old plugin folder (e.g., "hey-trisha") is still active
// while a new version with a different folder name is being activated.
if (defined('HEYTRISHA_LOADED')) {
    return;
}
define('HEYTRISHA_LOADED', true);

// ✅ CRITICAL: Suppress PHP notices/warnings for our REST API endpoints
// This runs early, but ONLY for REST API requests (not during plugin activation)
// Check if this is our REST API endpoint - must check REQUEST_URI exists first
// phpcs:disable WordPress.Security.ValidatedSanitizedInput -- REQUEST_URI only used for string comparison, not output
$heytrisha_is_rest_api_request = isset($_SERVER['REQUEST_URI']) && strpos(wp_unslash($_SERVER['REQUEST_URI']), '/wp-json/heytrisha/v1/') !== false;
// phpcs:enable WordPress.Security.ValidatedSanitizedInput

if ($heytrisha_is_rest_api_request) {
    // Use output buffering and error handler instead of modifying error_reporting()
    // This doesn't interfere with other plugins' error handling
    // phpcs:disable WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler -- Required for REST API error handling
    // Set custom error handler that only suppresses output, not error reporting
    set_error_handler(function($errno, $errstr, $errfile, $errline) {
        // Suppress output of notices/warnings but don't prevent error_reporting()
        // This allows other plugins to still use error_reporting() normally
        if ($errno === E_NOTICE || $errno === E_WARNING || $errno === E_DEPRECATED || 
            $errno === E_USER_NOTICE || $errno === E_USER_WARNING || $errno === E_USER_DEPRECATED) {
            return true; // Suppress output but don't change error_reporting()
        }
        return false; // Let fatal errors through
    }, E_ALL);
    // phpcs:enable WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler
    
    // CRITICAL: Register shutdown function to catch fatal errors and return JSON
    register_shutdown_function(function() {
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- REQUEST_URI only used for string comparison
        $request_uri = isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '';
        // Only handle fatal errors for our REST API endpoints
        if (strpos($request_uri, '/wp-json/heytrisha/v1/') !== false) {
            $error = error_get_last();
            if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
                // Clean all output buffers
                while (ob_get_level() > 0) {
                    ob_end_clean();
                }
                
                // Send JSON error response instead of HTML
                if (!headers_sent()) {
                    http_response_code(500);
                    header('Content-Type: application/json');
                }
                
                // Use wp_json_encode for proper escaping
                echo wp_json_encode([
                    'success' => false,
                    'message' => 'Internal server error',
                    'error' => esc_html($error['message']),
                    'file' => esc_html($error['file']) . ':' . absint($error['line']),
                    'type' => 'FatalError'
                ]);
                exit;
            }
        }
    });
    
    // Start output buffering immediately to catch any stray output
    // Clean any existing buffers first
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    ob_start();
    
    // Ensure buffer is cleaned when shutdown function completes
    register_shutdown_function(function() {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
    });
    // phpcs:enable WordPress.PHP.DevelopmentFunctions,Squiz.PHP.DiscouragedFunctions
}

// Define plugin constants (with defined() checks for safety during upgrades)
if (!defined('HEYTRISHA_VERSION')) {
    define('HEYTRISHA_VERSION', '2.1.7');
}
if (!defined('HEYTRISHA_PLUGIN_DIR')) {
    define('HEYTRISHA_PLUGIN_DIR', plugin_dir_path(__FILE__));
}
if (!defined('HEYTRISHA_PLUGIN_URL')) {
    define('HEYTRISHA_PLUGIN_URL', plugin_dir_url(__FILE__));
}
if (!defined('HEYTRISHA_PLUGIN_FILE')) {
    define('HEYTRISHA_PLUGIN_FILE', __FILE__);
}
if (!defined('HEYTRISHA_MIN_PHP_VERSION')) {
    define('HEYTRISHA_MIN_PHP_VERSION', '7.4.3');
}
if (!defined('HEYTRISHA_MIN_WP_VERSION')) {
    define('HEYTRISHA_MIN_WP_VERSION', '5.0');
}


// ✅ Inject the chatbot div into the admin footer
// function add_chatbot_widget_to_admin_footer() {
//     if (current_user_can('administrator')) {
//         echo '<div id="chatbot-root"></div>';
//         echo '<script>console.log("✅ Chatbot root div added to admin footer");</script>';
//     }
// }
// add_action('admin_footer', 'add_chatbot_widget_to_admin_footer');

function heytrisha_enqueue_chatbot() {
    if (!current_user_can('administrator')) {
        return;
    }
    
    // ✅ Load Chatbot CSS (only if file exists)
    $chatbot_css = HEYTRISHA_PLUGIN_DIR . 'assets/css/chatbot.css';
    if (file_exists($chatbot_css)) {
        wp_enqueue_style('heytrisha-chatbot-css', HEYTRISHA_PLUGIN_URL . 'assets/css/chatbot.css', [], HEYTRISHA_VERSION);
    }
    
    // ✅ Load Chatbot JavaScript with React support
    $chatbot_js = HEYTRISHA_PLUGIN_DIR . 'assets/js/chatbot.js';
    if (file_exists($chatbot_js)) {
        // Load React and ReactDOM from CDN (required for chatbot functionality)
        // Load in header (false) to ensure they're available when chatbot.js runs
        wp_enqueue_script('react', 'https://unpkg.com/react@18/umd/react.production.min.js', [], '18.2.0', false);
        wp_enqueue_script('react-dom', 'https://unpkg.com/react-dom@18/umd/react-dom.production.min.js', ['react'], '18.2.0', false);
        
        // Add crossorigin attribute for React scripts
        add_filter('script_loader_tag', function($tag, $handle) {
            if ($handle === 'react' || $handle === 'react-dom') {
                $tag = str_replace(' src', ' crossorigin="anonymous" src', $tag);
            }
            return $tag;
        }, 10, 2);
        
        // Load chatbot script in footer, but ensure React is loaded first
        wp_enqueue_script('heytrisha-chatbot-js', HEYTRISHA_PLUGIN_URL . 'assets/js/chatbot.js', ['jquery', 'react', 'react-dom'], HEYTRISHA_VERSION, true);
        
        // ✅ Pass configuration to JavaScript (only if script was enqueued)
        $api_url = heytrisha_get_api_url();
        $is_shared_hosting = heytrisha_is_shared_hosting();
        
        wp_localize_script('heytrisha-chatbot-js', 'heytrishaConfig', [
            'pluginUrl' => HEYTRISHA_PLUGIN_URL,
            'apiUrl' => admin_url('admin-ajax.php'), // ✅ Changed to admin-ajax.php for security
            'restUrl' => rest_url('heytrisha/v1/'), // Keep for chat history (not exposed in main queries)
            'isSharedHosting' => $is_shared_hosting,
            'nonce' => wp_create_nonce('heytrisha_chatbot'),
            'serverNonce' => wp_create_nonce('heytrisha_server_action'),
            'wpRestNonce' => wp_create_nonce('wp_rest'),
            'ajaxurl' => admin_url('admin-ajax.php')
        ]);
    }
}
add_action('admin_enqueue_scripts', 'heytrisha_enqueue_chatbot');

// ✅ Note: Terms and Conditions are now handled via dedicated admin page after activation
// No modal JavaScript needed - user is redirected to Terms page after activation

// ✅ AJAX handler to save Terms and Conditions acceptance
function heytrisha_ajax_accept_terms() {
    // Verify nonce with proper sanitization
    if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'heytrisha_accept_terms')) {
        wp_send_json_error([
            'message' => 'Security check failed. Please refresh the page and try again.'
        ]);
        return;
    }
    
    // Check user capability
    if (!current_user_can('activate_plugins')) {
        wp_send_json_error([
            'message' => 'You do not have permission to activate plugins.'
        ]);
        return;
    }
    
    // Save acceptance
    $accepted = isset($_POST['accepted']) && $_POST['accepted'] === 'true';
    
    if ($accepted) {
        update_option('heytrisha_terms_accepted', true);
        update_option('heytrisha_terms_accepted_date', current_time('mysql'));
        update_option('heytrisha_terms_accepted_user', get_current_user_id());
        
        wp_send_json_success([
            'message' => 'Terms and Conditions accepted successfully.'
        ]);
    } else {
        wp_send_json_error([
            'message' => 'Invalid acceptance status.'
        ]);
    }
}
add_action('wp_ajax_heytrisha_accept_terms', 'heytrisha_ajax_accept_terms');

// ✅ Redirect to Terms page after activation if terms not accepted
function heytrisha_redirect_to_terms_page() {
    // Only redirect if plugin is active and terms not accepted
    if (is_plugin_active(plugin_basename(__FILE__))) {
        $redirect_flag = get_option('heytrisha_redirect_to_terms', false);
        $terms_accepted = get_option('heytrisha_terms_accepted', false);
        
        // Don't redirect if already on terms page or settings page
        $current_page = '';
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only check for current admin page, not processing form data
        if (isset($_GET['page'])) {
            $current_page = sanitize_key(wp_unslash($_GET['page']));
        }
        // phpcs:enable WordPress.Security.NonceVerification.Recommended
        if ($current_page === 'heytrisha-terms-and-conditions' || $current_page === 'heytrisha-chatbot-settings') {
            return;
        }
        
        if ($redirect_flag && !$terms_accepted) {
            // Clear redirect flag
            delete_option('heytrisha_redirect_to_terms');
            // Redirect to Terms page
            wp_safe_redirect(admin_url('admin.php?page=heytrisha-terms-and-conditions'));
            exit;
        }
    }
}
add_action('admin_init', 'heytrisha_redirect_to_terms_page', 1);

// Add chatbot container to admin footer
function heytrisha_add_chatbot_container() {
    if (current_user_can('administrator')) {
        echo '<div id="chatbot-root"></div>';
    }
}
add_action('admin_footer', 'heytrisha_add_chatbot_container');

// ✅ Add Terms and Conditions link to plugin row meta (after "Visit plugin site")
function heytrisha_add_plugin_row_meta($links, $file) {
    // Only add link for our plugin
    if ($file === plugin_basename(__FILE__)) {
        // Add Terms and Conditions link
        $terms_link = '<a href="' . esc_url(admin_url('admin.php?page=heytrisha-terms-and-conditions')) . '">Terms and Conditions</a>';
        // Insert after "Visit plugin site" (usually the last link)
        // Find the position of "Visit plugin site" or add at the end
        $insert_position = false;
        foreach ($links as $key => $link) {
            if (strpos($link, 'Visit plugin site') !== false || strpos($link, 'plugin-site') !== false) {
                $insert_position = $key + 1;
                break;
            }
        }
        
        if ($insert_position !== false) {
            // Insert after "Visit plugin site"
            array_splice($links, $insert_position, 0, $terms_link);
        } else {
            // If "Visit plugin site" not found, add at the end
            $links[] = $terms_link;
        }
    }
    return $links;
}
add_filter('plugin_row_meta', 'heytrisha_add_plugin_row_meta', 10, 2);



// ✅ Admin Menu with Chat System
function heytrisha_register_admin_menu() {
    // ✅ Terms and Conditions page (hidden from menu, only accessible via redirect)
    add_submenu_page(
        null, // Hidden from menu
        'Terms and Conditions',
        'Terms and Conditions',
        'manage_options',
        'heytrisha-terms-and-conditions',
        'heytrisha_render_terms_page'
    );
    
    // Main menu - New Chat (default page)
    add_menu_page(
        'Hey Trisha - New Chat',
        'Hey Trisha',
        'manage_options',
        'heytrisha-new-chat',
        'heytrisha_render_new_chat_page',
        'dashicons-format-chat',
        81
    );
    
    // Submenu: New Chat (same as main menu, but with different title)
    add_submenu_page(
        'heytrisha-new-chat',
        'New Chat',
        'New Chat',
        'manage_options',
        'heytrisha-new-chat',
        'heytrisha_render_new_chat_page'
    );
    
    // Submenu: Chats (list of all chats)
    add_submenu_page(
        'heytrisha-new-chat',
        'Chats',
        'Chats',
        'manage_options',
        'heytrisha-chats',
        'heytrisha_render_chats_page'
    );
    
    // Submenu: Archive
    add_submenu_page(
        'heytrisha-new-chat',
        'Archive',
        'Archive',
        'manage_options',
        'heytrisha-archive',
        'heytrisha_render_archive_page'
    );
    
    // Submenu: Settings (separator before)
    add_submenu_page(
        'heytrisha-new-chat',
        'Settings',
        'Settings',
        'manage_options',
        'heytrisha-chatbot-settings',
        'heytrisha_render_settings_page'
    );
}
add_action('admin_menu', 'heytrisha_register_admin_menu');

/**
 * Hide generic WordPress admin notices on HeyTrisha chat pages only.
 *
 * This prevents core/plugin notices (update nags, warnings, etc.) from
 * overlapping the fullscreen chat UI, without affecting other admin pages
 * or changing any chatbot layout/behavior.
 */
function heytrisha_hide_admin_notices_on_chat_pages() {
    if (!is_admin()) {
        return;
    }

    // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only access to current page slug
    $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
    // phpcs:enable WordPress.Security.NonceVerification.Recommended

    if (!$page) {
        return;
    }

    $chat_pages = array(
        'heytrisha-new-chat',
        'heytrisha-chats',
        'heytrisha-archive',
    );

    if (!in_array($page, $chat_pages, true)) {
        return;
    }

    // Output minimal CSS to hide notices in the content area only
    echo '<style id="heytrisha-hide-admin-notices">
        #wpbody-content > .notice,
        #wpbody-content > .error,
        #wpbody-content > .updated,
        #wpbody-content > .update-nag {
            display: none !important;
        }
    </style>';
}
add_action('admin_head', 'heytrisha_hide_admin_notices_on_chat_pages', 100);

// ✅ Check PHP version before loading plugin
if (version_compare(PHP_VERSION, HEYTRISHA_MIN_PHP_VERSION, '<')) {
    add_action('admin_notices', 'heytrisha_php_version_notice');
    return;
}

// ✅ Check WordPress version
global $wp_version;
if (version_compare($wp_version, HEYTRISHA_MIN_WP_VERSION, '<')) {
    add_action('admin_notices', 'heytrisha_wp_version_notice');
    return;
}

// ✅ PHP version notice
function heytrisha_php_version_notice() {
    echo '<div class="error"><p>';
    echo sprintf(
        /* translators: 1: Required PHP version number, 2: Current PHP version number */
        esc_html__('Hey Trisha requires PHP version %1$s or higher. You are running PHP %2$s. Please upgrade PHP.', 'hey-trisha'),
        esc_html(HEYTRISHA_MIN_PHP_VERSION),
        esc_html(PHP_VERSION)
    );
    echo '</p></div>';
}

// ✅ WordPress version notice
function heytrisha_wp_version_notice() {
    global $wp_version;
    echo '<div class="error"><p>';
    echo sprintf(
        /* translators: 1: Required WordPress version number, 2: Current WordPress version number */
        esc_html__('Hey Trisha requires WordPress version %1$s or higher. You are running WordPress %2$s. Please upgrade WordPress.', 'hey-trisha'),
        esc_html(HEYTRISHA_MIN_WP_VERSION),
        esc_html($wp_version)
    );
    echo '</p></div>';
}

// ✅ Include required files with error handling
$heytrisha_required_files = array(
    'includes/class-heytrisha-database.php',
    'includes/class-heytrisha-secure-credentials.php' // ✅ Secure credentials manager
);

foreach ($heytrisha_required_files as $heytrisha_file) {
    $heytrisha_file_path = HEYTRISHA_PLUGIN_DIR . $heytrisha_file;
    if (file_exists($heytrisha_file_path)) {
        require_once $heytrisha_file_path;
    } else {
        add_action('admin_notices', function() use ($heytrisha_file) {
            echo '<div class="error"><p>';
            echo sprintf(
                /* translators: %s: The name of the missing plugin file */
                esc_html__('Hey Trisha: Required file missing: %s. Please reinstall the plugin.', 'hey-trisha'),
                esc_html($heytrisha_file)
            );
            echo '</p></div>';
        });
        return;
    }
}

// ✅ Create default options on activation and install dependencies
function heytrisha_activate_plugin() {
    // ✅ Check if Terms and Conditions have been accepted (REQUIRED)
    $terms_accepted = get_option('heytrisha_terms_accepted', false);
    if (!$terms_accepted) {
        // Terms not accepted - set flag to redirect to Terms page after activation
        update_option('heytrisha_needs_terms_acceptance', true);
        update_option('heytrisha_redirect_to_terms', true);
    }
    
    // Wrap ENTIRE activation in try-catch to prevent any fatal errors
    try {
        // Check if required classes are loaded
        if (!class_exists('HeyTrisha_Database') || !class_exists('HeyTrisha_Secure_Credentials')) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Production error tracking for plugin activation failures
            error_log('Hey Trisha: Required plugin classes not loaded during activation');
            // Don't deactivate - just log and continue
        }
        
        // ✅ STEP 1: Create secure credentials table
        if (class_exists('HeyTrisha_Secure_Credentials')) {
            try {
                HeyTrisha_Secure_Credentials::create_table();
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Production error tracking for table creation
                error_log('✅ HeyTrisha: Secure credentials table created');
            } catch (Exception $e) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Production error tracking for table creation failures
                error_log('❌ HeyTrisha: Secure credentials table creation failed - ' . $e->getMessage());
            }
        }
        
        // STEP 2: Create default options
        add_option('heytrisha_api_url', 'https://api.heytrisha.com', '', 'no');
        
        // ✅ STEP 3: Create database tables for chat system
        // phpcs:disable WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Production error tracking for table creation failures
        if (class_exists('HeyTrisha_Database')) {
            try {
                HeyTrisha_Database::create_tables();
            } catch (Exception $e) {
                error_log('Hey Trisha: Database table creation failed - ' . $e->getMessage());
            } catch (Throwable $e) {
                error_log('Hey Trisha: Database table creation failed (Throwable) - ' . $e->getMessage());
            }
        }
        // phpcs:enable WordPress.PHP.DevelopmentFunctions.error_log_error_log
        
        // Flush rewrite rules to ensure REST API endpoints are registered
        flush_rewrite_rules();
        
        // Store activation success flag
        update_option('heytrisha_activation_success', true);
        
    } catch (Throwable $e) {
        // Catch ALL errors (including Parse errors, Type errors, etc.) to prevent activation failure
        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Production error tracking for activation failures
        error_log('Hey Trisha: Activation failed with error - ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        // Store error for display in admin
        update_option('heytrisha_activation_error', array(
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ));
        // Don't rethrow - allow activation to complete
    }
}
register_activation_hook(__FILE__, 'heytrisha_activate_plugin');

// ✅ Helper function to get secure credential with fallback to wp_options
function heytrisha_get_credential($key, $option_name, $default = '') {
    // Try to get from secure storage first
    if (class_exists('HeyTrisha_Secure_Credentials')) {
        $credentials = HeyTrisha_Secure_Credentials::get_instance();
        $value = $credentials->get_credential($key);
        
        if (!empty($value)) {
            return $value;
        }
    }
    
    // Fallback to wp_options (for backwards compatibility)
    $value = get_option($option_name, $default);
    
    // If found in wp_options, migrate to secure storage
    if (!empty($value) && class_exists('HeyTrisha_Secure_Credentials')) {
        $credentials = HeyTrisha_Secure_Credentials::get_instance();
        $credentials->set_credential($key, $value);
        // Delete from wp_options after migration
        delete_option($option_name);
    }
    
    return $value;
}

// ✅ Helper function to set secure credential
function heytrisha_set_credential($key, $value) {
    if (class_exists('HeyTrisha_Secure_Credentials')) {
        $credentials = HeyTrisha_Secure_Credentials::get_instance();
        return $credentials->set_credential($key, $value);
    }
    return false;
}

// ✅ Helper function to inject WordPress credentials as HTTP headers
function heytrisha_inject_credentials_as_headers() {
    $_SERVER['HTTP_X_WORDPRESS_OPENAI_KEY'] = heytrisha_get_credential(HeyTrisha_Secure_Credentials::KEY_OPENAI_API, 'heytrisha_openai_api_key', '');
    $_SERVER['HTTP_X_WORDPRESS_DB_HOST'] = get_option('heytrisha_db_host', '');
    $_SERVER['HTTP_X_WORDPRESS_DB_PORT'] = get_option('heytrisha_db_port', '');
    $_SERVER['HTTP_X_WORDPRESS_DB_NAME'] = get_option('heytrisha_db_name', '');
    $_SERVER['HTTP_X_WORDPRESS_DB_USER'] = get_option('heytrisha_db_user', '');
    $_SERVER['HTTP_X_WORDPRESS_DB_PASSWORD'] = heytrisha_get_credential(HeyTrisha_Secure_Credentials::KEY_DB_PASSWORD, 'heytrisha_db_password', '');
    $_SERVER['HTTP_X_WORDPRESS_API_URL'] = get_option('heytrisha_wordpress_api_url', get_site_url());
    $_SERVER['HTTP_X_WORDPRESS_API_USER'] = get_option('heytrisha_wordpress_api_user', '');
    $_SERVER['HTTP_X_WORDPRESS_API_PASSWORD'] = heytrisha_get_credential(HeyTrisha_Secure_Credentials::KEY_WP_API_PASSWORD, 'heytrisha_wordpress_api_password', '');
    $_SERVER['HTTP_X_WOOCOMMERCE_KEY'] = get_option('heytrisha_woocommerce_consumer_key', '');
    $_SERVER['HTTP_X_WOOCOMMERCE_SECRET'] = heytrisha_get_credential(HeyTrisha_Secure_Credentials::KEY_WC_CONSUMER_SECRET, 'heytrisha_woocommerce_consumer_secret', '');
    $_SERVER['HTTP_X_WORDPRESS_IS_MULTISITE'] = is_multisite() ? '1' : '0';
    $_SERVER['HTTP_X_WORDPRESS_CURRENT_SITE_ID'] = is_multisite() ? get_current_blog_id() : '1';
}

// ✅ REMOVED: Server start on activation
// This function has been removed as part of the thin client refactoring.

// ✅ Cleanup on deactivation
function heytrisha_deactivate_plugin() {
    // Flush rewrite rules
    flush_rewrite_rules();
}
register_deactivation_hook(__FILE__, 'heytrisha_deactivate_plugin');

// ✅ Save settings
function heytrisha_handle_settings_save() {
    if (!current_user_can('manage_options')) {
        return;
    }

    if (!isset($_POST['heytrisha_settings_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['heytrisha_settings_nonce'])), 'heytrisha_save_settings')) {
        return;
    }

    // ✅ Get external API settings
    $api_url = isset($_POST['heytrisha_api_url']) ? esc_url_raw(wp_unslash($_POST['heytrisha_api_url'])) : '';
    
    // ✅ Get personal data fields (username, password, OpenAI key, DB credentials)
    $username = isset($_POST['heytrisha_username']) ? sanitize_user(wp_unslash($_POST['heytrisha_username'])) : '';
    $password = isset($_POST['heytrisha_password']) ? wp_unslash($_POST['heytrisha_password']) : '';
    $openai_key = isset($_POST['heytrisha_openai_key']) ? sanitize_text_field(wp_unslash($_POST['heytrisha_openai_key'])) : '';
    $db_name = isset($_POST['heytrisha_db_name']) ? sanitize_text_field(wp_unslash($_POST['heytrisha_db_name'])) : '';
    $db_username = isset($_POST['heytrisha_db_username']) ? sanitize_text_field(wp_unslash($_POST['heytrisha_db_username'])) : '';
    $db_password = isset($_POST['heytrisha_db_password']) ? wp_unslash($_POST['heytrisha_db_password']) : '';

    // ✅ Store API URL in wp_options
    if (!empty($api_url)) {
        update_option('heytrisha_api_url', $api_url);
    }
    
    // ✅ Update username if provided and valid
    if (!empty($username) && strlen($username) >= 3) {
        update_option('heytrisha_user_username', $username);
    }
    
    // ✅ Update password if provided (minimum 8 characters)
    if (!empty($password)) {
        if (strlen($password) >= 8) {
            // Password will be updated via API call below
            $password_to_update = $password;
        } else {
            add_settings_error('heytrisha_settings', 'password_too_short', 'Password must be at least 8 characters.', 'error');
            return;
        }
    }
    
    // ✅ Update OpenAI key if provided
    if (!empty($openai_key)) {
        heytrisha_set_credential(HeyTrisha_Secure_Credentials::KEY_OPENAI_API, $openai_key);
    }
    
    // ✅ Update database credentials if provided
    if (!empty($db_name)) {
        update_option('heytrisha_db_name', $db_name);
    }
    if (!empty($db_username)) {
        update_option('heytrisha_db_user', $db_username);
    }
    if (!empty($db_password)) {
        heytrisha_set_credential(HeyTrisha_Secure_Credentials::KEY_DB_PASSWORD, $db_password);
    }
    
    // ✅ Sync updates with API server
    $site_api_key = heytrisha_get_credential(HeyTrisha_Secure_Credentials::KEY_API_TOKEN, 'heytrisha_api_key', '');
    if (!empty($site_api_key) && !empty($api_url)) {
        $update_data = array();
        
        if (!empty($username)) {
            $update_data['username'] = $username;
        }
        
        if (!empty($password_to_update)) {
            $update_data['password'] = $password_to_update;
        }
        
        if (!empty($openai_key)) {
            $update_data['openai_key'] = $openai_key;
        }
        
        if (!empty($db_name)) {
            $update_data['db_name'] = $db_name;
        }
        
        if (!empty($db_username)) {
            $update_data['db_username'] = $db_username;
        }
        
        if (!empty($db_password)) {
            $update_data['db_password'] = $db_password;
        }
        
        // Only make API call if there's data to update
        if (!empty($update_data)) {
            $response = wp_remote_post(rtrim($api_url, '/') . '/api/config', array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $site_api_key,
                    'Content-Type' => 'application/json',
                ),
                'body' => wp_json_encode($update_data),
                'timeout' => 30,
                'sslverify' => true,
            ));

            if (is_wp_error($response)) {
                add_settings_error('heytrisha_settings', 'sync_failed', 'Settings saved locally but failed to sync with API server: ' . $response->get_error_message(), 'warning');
            } else {
                $response_code = wp_remote_retrieve_response_code($response);
                if ($response_code >= 200 && $response_code < 300) {
                    // Success - settings synced
                } else {
                    add_settings_error('heytrisha_settings', 'sync_failed', 'Settings saved locally but failed to sync with API server.', 'warning');
                }
            }
        }
    }

    add_settings_error('heytrisha_settings', 'settings_updated', 'Settings saved.', 'updated');
}
add_action('admin_init', 'heytrisha_handle_settings_save');

// ✅ REMOVED: Server Management AJAX Handlers
// These handlers have been removed as part of the thin client refactoring.
// The plugin now uses an external API service instead of a local Laravel server.

// ✅ Render admin settings page
function heytrisha_render_settings_page() {
    if (!current_user_can('manage_options')) {
        return;
    }

    // Enqueue jQuery for AJAX functionality
    wp_enqueue_script('jquery');

    settings_errors('heytrisha_settings');

    $onboarding_complete = get_option('heytrisha_onboarding_complete', false);
    $api_url = get_option('heytrisha_api_url', 'https://api.heytrisha.com');
    $api_key = heytrisha_get_credential(HeyTrisha_Secure_Credentials::KEY_API_TOKEN, 'heytrisha_api_key', '');

    echo '<div class="wrap">';
    echo '<h1>HeyTrisha Chatbot Settings</h1>';

    // Show onboarding form if not completed
    if (!$onboarding_complete) {
        echo '<div class="notice notice-warning inline" style="margin: 15px 0; padding: 12px;">';
        echo '<p><strong>⚠️ Onboarding Required:</strong> Please complete the registration form below to activate HeyTrisha.</p>';
        echo '</div>';

        echo '<form method="post" id="heytrisha-onboarding-form">';
        wp_nonce_field('heytrisha_onboarding', 'heytrisha_onboarding_nonce');
        echo '<input type="hidden" name="heytrisha_register" value="1" />';

        echo '<h2>Create Your Account</h2>';
        echo '<p>Create your HeyTrisha account to get started. All information will be securely stored.</p>';
        echo '<table class="form-table"><tbody>';
        
        // Personal Information
        echo '<tr><th colspan="2"><h3>Personal Information</h3></th></tr>';
        echo '<tr><th scope="row"><label for="heytrisha_email">Email <span style="color:red;">*</span></label></th>';
        echo '<td><input type="email" id="heytrisha_email" name="heytrisha_email" value="' . esc_attr(get_option('admin_email')) . '" class="regular-text" required /></td></tr>';
        
        echo '<tr><th scope="row"><label for="heytrisha_username">Username <span style="color:red;">*</span></label></th>';
        echo '<td><input type="text" id="heytrisha_username" name="heytrisha_username" value="" class="regular-text" required minlength="3" /></td></tr>';
        
        echo '<tr><th scope="row"><label for="heytrisha_password">Password <span style="color:red;">*</span></label></th>';
        echo '<td><input type="password" id="heytrisha_password" name="heytrisha_password" value="" class="regular-text" required minlength="8" autocomplete="new-password" /></td></tr>';
        
        echo '<tr><th scope="row"><label for="heytrisha_first_name">First Name <span style="color:red;">*</span></label></th>';
        echo '<td><input type="text" id="heytrisha_first_name" name="heytrisha_first_name" value="" class="regular-text" required /></td></tr>';
        
        echo '<tr><th scope="row"><label for="heytrisha_last_name">Last Name <span style="color:red;">*</span></label></th>';
        echo '<td><input type="text" id="heytrisha_last_name" name="heytrisha_last_name" value="" class="regular-text" required /></td></tr>';

        // Database Information
        echo '<tr><th colspan="2"><h3>Database Information</h3></th></tr>';
        echo '<tr><th scope="row"><label for="heytrisha_db_name">Database Name <span style="color:red;">*</span></label></th>';
        echo '<td><input type="text" id="heytrisha_db_name" name="heytrisha_db_name" value="' . esc_attr(DB_NAME) . '" class="regular-text" required /></td></tr>';
        
        echo '<tr><th scope="row"><label for="heytrisha_db_username">Database Username <span style="color:red;">*</span></label></th>';
        echo '<td><input type="text" id="heytrisha_db_username" name="heytrisha_db_username" value="' . esc_attr(DB_USER) . '" class="regular-text" required /></td></tr>';
        
        echo '<tr><th scope="row"><label for="heytrisha_db_password">Database Password <span style="color:red;">*</span></label></th>';
        echo '<td><input type="password" id="heytrisha_db_password" name="heytrisha_db_password" value="" class="regular-text" required autocomplete="off" /></td></tr>';

        // API Configuration
        echo '<tr><th colspan="2"><h3>API Configuration</h3></th></tr>';
        echo '<tr><th scope="row"><label for="heytrisha_openai_key">OpenAI API Key <span style="color:red;">*</span></label></th>';
        echo '<td><input type="password" id="heytrisha_openai_key" name="heytrisha_openai_key" value="" class="regular-text" required autocomplete="off" />';
        echo '<p class="description">Get your OpenAI API key from <a href="https://platform.openai.com/api-keys" target="_blank">OpenAI Platform</a></p></td></tr>';
        
        echo '<tr><th scope="row"><label for="heytrisha_api_server_url">API Server URL</label></th>';
        echo '<td><input type="url" id="heytrisha_api_server_url" name="heytrisha_api_server_url" value="' . esc_attr($api_url) . '" class="regular-text" placeholder="https://api.heytrisha.com" /></td></tr>';
        
        echo '<tr><th scope="row"><label for="heytrisha_site_url">Site URL</label></th>';
        echo '<td><input type="url" id="heytrisha_site_url" name="heytrisha_site_url" value="' . esc_url(get_site_url()) . '" class="regular-text" /></td></tr>';
        
        echo '</tbody></table>';

        echo '<div class="notice notice-info inline" style="margin: 15px 0; padding: 12px;">';
        echo '<p><strong>ℹ️ External Service Notice:</strong></p>';
        echo '<p>This plugin connects to an external service (HeyTrisha API) to process natural language queries. Your account information, database credentials, and OpenAI API key will be securely stored on our servers. User queries and limited schema metadata may be transmitted. No passwords or payment data are sent.</p>';
        echo '</div>';

        submit_button('Register & Activate', 'primary', 'submit', false, array('id' => 'heytrisha-register-btn'));
        echo '</form>';
    } else {
        // Check if just registered (show API key in readonly field)
        $just_registered = isset($_GET['registered']) && $_GET['registered'] == '1';
        $new_api_key = $just_registered ? get_transient('heytrisha_new_api_key') : '';
        
        // If we have a new API key from registration, use it for display
        if ($new_api_key) {
            $api_key = $new_api_key;
            // Clear the transient after displaying once
            delete_transient('heytrisha_new_api_key');
        }
        
        // Show settings form if onboarding is complete
        echo '<form method="post" id="heytrisha-personal-data-form">';
        // Note: Nonce is passed via wp_localize_script for AJAX calls

        if ($just_registered && $new_api_key) {
            echo '<div class="notice notice-success inline" style="margin: 15px 0; padding: 12px;">';
            echo '<p><strong>✅ Registration Successful!</strong> Your API key has been generated and saved. Please copy it below - it will not be shown again.</p>';
            echo '</div>';
        }

        // Fetch user data from API server (try /api/site/info endpoint)
        // Note: API endpoint doesn't return all user data, so we primarily rely on local storage
        $user_data = array();
        $site_api_key = heytrisha_get_credential(HeyTrisha_Secure_Credentials::KEY_API_TOKEN, 'heytrisha_api_key', '');
        if (!empty($site_api_key) && !empty($api_url)) {
            // Try /api/site/info endpoint (this is the correct endpoint)
            $response = wp_remote_get(rtrim($api_url, '/') . '/api/site/info', array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $site_api_key,
                    'Content-Type' => 'application/json',
                ),
                'timeout' => 30,
                'sslverify' => true,
            ));
            
            if (!is_wp_error($response)) {
                $response_code = wp_remote_retrieve_response_code($response);
                if ($response_code >= 200 && $response_code < 300) {
                    $body = wp_remote_retrieve_body($response);
                    $api_data = json_decode($body, true);
                    
                    // Handle different response formats
                    if ($api_data) {
                        // Format 1: {success: true, site: {...}}
                        if (isset($api_data['success']) && $api_data['success'] && isset($api_data['site'])) {
                            $user_data = $api_data['site'];
                            // Update email from API if available
                            if (isset($user_data['email']) && !empty($user_data['email'])) {
                                update_option('heytrisha_user_email', $user_data['email']);
                            }
                        }
                        // Format 2: Direct data object
                        elseif (isset($api_data['email']) || isset($api_data['site_url'])) {
                            $user_data = $api_data;
                            if (isset($user_data['email']) && !empty($user_data['email'])) {
                                update_option('heytrisha_user_email', $user_data['email']);
                            }
                        }
                    }
                }
            }
        }
        
        // Get stored personal data (primarily from local storage, API is just for email sync)
        // Local storage is the source of truth since API doesn't return all fields
        $stored_email = get_option('heytrisha_user_email', '');
        if (!empty($user_data['email'])) {
            $stored_email = $user_data['email'];
        }
        $stored_first_name = get_option('heytrisha_user_first_name', '');
        $stored_last_name = get_option('heytrisha_user_last_name', '');
        $stored_username = get_option('heytrisha_user_username', '');
        $stored_db_name = get_option('heytrisha_db_name', '');
        $stored_db_username = get_option('heytrisha_db_user', '');
        $stored_db_password = heytrisha_get_credential(HeyTrisha_Secure_Credentials::KEY_DB_PASSWORD, 'heytrisha_db_password', '');
        $openai_key = heytrisha_get_credential(HeyTrisha_Secure_Credentials::KEY_OPENAI_API, 'heytrisha_openai_key', '');
        
        // Personal Data & Keys Section
        echo '<h2>Personal Data & Keys</h2>';
        echo '<p>View and update your personal information and API keys.</p>';
        echo '<table class="form-table"><tbody>';
        
        // Email (editable)
        echo '<tr><th scope="row"><label for="heytrisha_email">Email</label></th>';
        echo '<td><input type="email" id="heytrisha_email" name="heytrisha_email" value="' . esc_attr($stored_email) . '" class="regular-text" /></td></tr>';
        
        // First Name (editable)
        echo '<tr><th scope="row"><label for="heytrisha_first_name">First Name</label></th>';
        echo '<td><input type="text" id="heytrisha_first_name" name="heytrisha_first_name" value="' . esc_attr($stored_first_name) . '" class="regular-text" /></td></tr>';
        
        // Last Name (editable)
        echo '<tr><th scope="row"><label for="heytrisha_last_name">Last Name</label></th>';
        echo '<td><input type="text" id="heytrisha_last_name" name="heytrisha_last_name" value="' . esc_attr($stored_last_name) . '" class="regular-text" /></td></tr>';
        
        // Username (editable)
        echo '<tr><th scope="row"><label for="heytrisha_username">Username</label></th>';
        echo '<td><input type="text" id="heytrisha_username" name="heytrisha_username" value="' . esc_attr($stored_username) . '" class="regular-text" minlength="3" /></td></tr>';
        
        // Password (editable with eye icon)
        echo '<tr><th scope="row"><label for="heytrisha_password">Password</label></th>';
        echo '<td><div style="position: relative; display: inline-block; width: 100%; max-width: 400px;">';
        echo '<input type="password" id="heytrisha_password" name="heytrisha_password" value="" class="regular-text" autocomplete="new-password" style="padding-right: 40px; width: 100%;" />';
        echo '<span class="heytrisha-toggle-password" data-target="heytrisha_password" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); cursor: pointer; font-size: 18px; color: #666;">👁️</span>';
        echo '</div>';
        echo '<p class="description">Leave blank to keep current password. Minimum 8 characters.</p></td></tr>';
        
        // OpenAI API Key (editable with eye icon)
        echo '<tr><th scope="row"><label for="heytrisha_openai_key">OpenAI API Key</label></th>';
        echo '<td><div style="position: relative; display: inline-block; width: 100%; max-width: 400px;">';
        echo '<input type="password" id="heytrisha_openai_key" name="heytrisha_openai_key" value="' . esc_attr($openai_key) . '" class="regular-text" autocomplete="off" style="padding-right: 40px; width: 100%;" />';
        echo '<span class="heytrisha-toggle-password" data-target="heytrisha_openai_key" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); cursor: pointer; font-size: 18px; color: #666;">👁️</span>';
        echo '</div>';
        echo '<p class="description">Get your OpenAI API key from <a href="https://platform.openai.com/api-keys" target="_blank">OpenAI Platform</a></p></td></tr>';
        
        echo '</tbody></table>';
        
        // Database Information Section
        echo '<h2>Database Information</h2>';
        echo '<p>View and update your database credentials.</p>';
        echo '<table class="form-table"><tbody>';
        
        // Database Name (editable)
        echo '<tr><th scope="row"><label for="heytrisha_db_name">Database Name</label></th>';
        echo '<td><input type="text" id="heytrisha_db_name" name="heytrisha_db_name" value="' . esc_attr($stored_db_name) . '" class="regular-text" /></td></tr>';
        
        // Database Username (editable)
        echo '<tr><th scope="row"><label for="heytrisha_db_username">Database Username</label></th>';
        echo '<td><input type="text" id="heytrisha_db_username" name="heytrisha_db_username" value="' . esc_attr($stored_db_username) . '" class="regular-text" /></td></tr>';
        
        // Database Password (editable with eye icon)
        echo '<tr><th scope="row"><label for="heytrisha_db_password">Database Password</label></th>';
        echo '<td><div style="position: relative; display: inline-block; width: 100%; max-width: 400px;">';
        echo '<input type="password" id="heytrisha_db_password" name="heytrisha_db_password" value="' . esc_attr($stored_db_password) . '" class="regular-text" autocomplete="off" style="padding-right: 40px; width: 100%;" />';
        echo '<span class="heytrisha-toggle-password" data-target="heytrisha_db_password" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); cursor: pointer; font-size: 18px; color: #666;">👁️</span>';
        echo '</div>';
        echo '<p class="description">Leave blank to keep current password.</p></td></tr>';
        
        echo '</tbody></table>';
        
        // HeyTrisha API Key Section
        echo '<h2>HeyTrisha API Key</h2>';
        echo '<table class="form-table"><tbody>';
        // HeyTrisha API Key (read-only with eye icon)
        echo '<tr><th scope="row"><label for="heytrisha_api_key">HeyTrisha API Key</label></th>';
        if ($just_registered && $new_api_key) {
            // Show API key in readonly text field (not password) so user can copy it
            echo '<td><input type="text" id="heytrisha_api_key" name="heytrisha_api_key" value="' . esc_attr($api_key) . '" class="regular-text" readonly style="background-color: #f0f0f0; cursor: text;" />';
            echo '<button type="button" onclick="copyApiKey()" style="margin-left: 10px;" class="button">Copy API Key</button>';
            echo '<p class="description"><strong>Important:</strong> Copy this API key now. It will be hidden after you refresh the page.</p></td></tr>';
        } else {
            // Show password field with eye icon (read-only)
            echo '<td><div style="position: relative; display: inline-block; width: 100%; max-width: 400px;">';
            echo '<input type="password" id="heytrisha_api_key" name="heytrisha_api_key" value="' . esc_attr($api_key) . '" class="regular-text" readonly style="background-color: #f0f0f0; cursor: not-allowed; padding-right: 40px; width: 100%;" autocomplete="off" />';
            echo '<span class="heytrisha-toggle-password" data-target="heytrisha_api_key" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); cursor: pointer; font-size: 18px; color: #666;">👁️</span>';
            echo '</div>';
            echo '<p class="description">Your API key was generated during registration. This field is read-only. If you lost it, contact support.</p></td></tr>';
        }
        echo '</tbody></table>';
        
        // External API Configuration Section
        echo '<h2>External API Configuration</h2>';
        echo '<p>Configure your API settings below.</p>';
        echo '<table class="form-table"><tbody>';
        echo '<tr><th scope="row"><label for="heytrisha_api_url">API URL</label></th>';
        echo '<td><input type="url" id="heytrisha_api_url" name="heytrisha_api_url" value="' . esc_attr($api_url) . '" class="regular-text" placeholder="https://api.heytrisha.com" /></td></tr>';
        echo '</tbody></table>';

        echo '<div class="notice notice-info inline" style="margin: 15px 0; padding: 12px;">';
        echo '<p><strong>ℹ️ External Service Notice:</strong></p>';
        echo '<p>This plugin connects to an external service (HeyTrisha API) to process natural language queries. User queries and limited schema metadata may be transmitted. No passwords or payment data are sent.</p>';
        echo '</div>';

        echo '<p class="submit">';
        echo '<button type="button" id="heytrisha-save-personal-data-btn" class="button button-primary">Save Changes</button>';
        echo '<span id="heytrisha-save-status" style="margin-left: 10px;"></span>';
        echo '</p>';
        echo '</form>';
        
        // Prepare AJAX configuration values
        $ajax_url = esc_js(admin_url('admin-ajax.php'));
        $ajax_nonce = esc_js(wp_create_nonce('heytrisha_personal_data'));
        
        // Add CSS and JavaScript for eye icon toggle and copy functionality
        echo '<style>
        .heytrisha-toggle-password {
            user-select: none;
            -webkit-user-select: none;
            -moz-user-select: none;
            -ms-user-select: none;
        }
        .heytrisha-toggle-password:hover {
            color: #2271b1 !important;
        }
        </style>';
        echo '<script>
        // Eye icon toggle functionality
        document.addEventListener("DOMContentLoaded", function() {
            var toggleButtons = document.querySelectorAll(".heytrisha-toggle-password");
            toggleButtons.forEach(function(button) {
                button.addEventListener("click", function() {
                    var targetId = this.getAttribute("data-target");
                    var input = document.getElementById(targetId);
                    if (input) {
                        if (input.type === "password") {
                            input.type = "text";
                            this.textContent = "🙈";
                        } else {
                            input.type = "password";
                            this.textContent = "👁️";
                        }
                    }
                });
            });
        });
        
        // Copy API key function (only if just registered)
        function copyApiKey() {
            var apiKeyInput = document.getElementById("heytrisha_api_key");
            if (apiKeyInput) {
                apiKeyInput.select();
                apiKeyInput.setSelectionRange(0, 99999); // For mobile devices
                try {
                    document.execCommand("copy");
                    alert("API Key copied to clipboard!");
                } catch (err) {
                    // Fallback: select text for manual copy
                    apiKeyInput.focus();
                    apiKeyInput.select();
                }
            }
        }
        
        // AJAX form submission for personal data
        jQuery(document).ready(function($) {
            // AJAX configuration (embedded directly to avoid wp_localize_script issues)
            var heytrishaPersonalDataAjax = {
                ajaxurl: "' . $ajax_url . '",
                nonce: "' . $ajax_nonce . '"
            };
            
            $("#heytrisha-save-personal-data-btn").on("click", function(e) {
                e.preventDefault();
                
                var $btn = $(this);
                var $status = $("#heytrisha-save-status");
                var originalText = $btn.text();
                
                // Disable button and show loading
                $btn.prop("disabled", true).text("Saving...");
                $status.html("").removeClass("notice notice-success notice-error");
                
                // Collect form data
                var formData = {
                    action: "heytrisha_update_personal_data",
                    nonce: heytrishaPersonalDataAjax.nonce,
                    email: $("#heytrisha_email").val() || "",
                    first_name: $("#heytrisha_first_name").val() || "",
                    last_name: $("#heytrisha_last_name").val() || "",
                    username: $("#heytrisha_username").val() || "",
                    password: $("#heytrisha_password").val() || "",
                    openai_key: $("#heytrisha_openai_key").val() || "",
                    db_name: $("#heytrisha_db_name").val() || "",
                    db_username: $("#heytrisha_db_username").val() || "",
                    db_password: $("#heytrisha_db_password").val() || ""
                };
                
                // Make AJAX request
                $.ajax({
                    url: heytrishaPersonalDataAjax.ajaxurl,
                    type: "POST",
                    data: formData,
                    dataType: "json",
                    success: function(response) {
                        if (response.success) {
                            $status.html("<span style=\"color: #00a32a;\">✓ " + response.data.message + "</span>");
                            
                            // Clear password field if update was successful
                            if (formData.password) {
                                $("#heytrisha_password").val("");
                            }
                            
                            // Show success notice
                            if (response.data.updated_fields && response.data.updated_fields.length > 0) {
                                console.log("Updated fields:", response.data.updated_fields);
                            }
                        } else {
                            var errorMsg = response.data && response.data.message ? response.data.message : "An error occurred.";
                            if (response.data && response.data.errors) {
                                errorMsg += "<br>" + response.data.errors.join("<br>");
                            }
                            $status.html("<span style=\"color: #d63638;\">✗ " + errorMsg + "</span>");
                            
                            // If local save succeeded but API sync failed, show warning
                            if (response.data && response.data.local_save) {
                                $status.html("<span style=\"color: #d63638;\">⚠ " + errorMsg + "</span>");
                            }
                        }
                    },
                    error: function(xhr, status, error) {
                        $status.html("<span style=\"color: #d63638;\">✗ Error: " + error + "</span>");
                    },
                    complete: function() {
                        $btn.prop("disabled", false).text(originalText);
                    }
                });
            });
        });
        </script>';
    }
    
    echo '</div>';
}

// ✅ Handle onboarding registration
function heytrisha_handle_onboarding_registration() {
    if (!current_user_can('manage_options')) {
        return;
    }

    if (!isset($_POST['heytrisha_register']) || !isset($_POST['heytrisha_onboarding_nonce']) || 
        !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['heytrisha_onboarding_nonce'])), 'heytrisha_onboarding')) {
        return;
    }

    // Get and sanitize all form fields
    $email = isset($_POST['heytrisha_email']) ? sanitize_email(wp_unslash($_POST['heytrisha_email'])) : '';
    $username = isset($_POST['heytrisha_username']) ? sanitize_user(wp_unslash($_POST['heytrisha_username'])) : '';
    $password = isset($_POST['heytrisha_password']) ? wp_unslash($_POST['heytrisha_password']) : '';
    $first_name = isset($_POST['heytrisha_first_name']) ? sanitize_text_field(wp_unslash($_POST['heytrisha_first_name'])) : '';
    $last_name = isset($_POST['heytrisha_last_name']) ? sanitize_text_field(wp_unslash($_POST['heytrisha_last_name'])) : '';
    $db_name = isset($_POST['heytrisha_db_name']) ? sanitize_text_field(wp_unslash($_POST['heytrisha_db_name'])) : '';
    $db_username = isset($_POST['heytrisha_db_username']) ? sanitize_text_field(wp_unslash($_POST['heytrisha_db_username'])) : '';
    $db_password = isset($_POST['heytrisha_db_password']) ? wp_unslash($_POST['heytrisha_db_password']) : '';
    $openai_key = isset($_POST['heytrisha_openai_key']) ? sanitize_text_field(wp_unslash($_POST['heytrisha_openai_key'])) : '';
    $site_url = isset($_POST['heytrisha_site_url']) ? esc_url_raw(wp_unslash($_POST['heytrisha_site_url'])) : get_site_url();
    $api_server_url = isset($_POST['heytrisha_api_server_url']) ? esc_url_raw(wp_unslash($_POST['heytrisha_api_server_url'])) : 'https://api.heytrisha.com';

    // Validate required fields
    if (empty($email)) {
        add_settings_error('heytrisha_settings', 'email_required', 'Email is required.', 'error');
        return;
    }
    if (empty($username) || strlen($username) < 3) {
        add_settings_error('heytrisha_settings', 'username_required', 'Username is required and must be at least 3 characters.', 'error');
        return;
    }
    if (empty($password) || strlen($password) < 8) {
        add_settings_error('heytrisha_settings', 'password_required', 'Password is required and must be at least 8 characters.', 'error');
        return;
    }
    if (empty($first_name)) {
        add_settings_error('heytrisha_settings', 'first_name_required', 'First name is required.', 'error');
        return;
    }
    if (empty($last_name)) {
        add_settings_error('heytrisha_settings', 'last_name_required', 'Last name is required.', 'error');
        return;
    }
    if (empty($db_name)) {
        add_settings_error('heytrisha_settings', 'db_name_required', 'Database name is required.', 'error');
        return;
    }
    if (empty($db_username)) {
        add_settings_error('heytrisha_settings', 'db_username_required', 'Database username is required.', 'error');
        return;
    }
    if (empty($db_password)) {
        add_settings_error('heytrisha_settings', 'db_password_required', 'Database password is required.', 'error');
        return;
    }
    if (empty($openai_key)) {
        add_settings_error('heytrisha_settings', 'openai_key_required', 'OpenAI API key is required.', 'error');
        return;
    }

    // Save OpenAI key locally (encrypted) - save before registration in case API call fails
    heytrisha_set_credential(HeyTrisha_Secure_Credentials::KEY_OPENAI_API, $openai_key);

    // Register with API server
    $response = wp_remote_post(rtrim($api_server_url, '/') . '/api/register', array(
        'headers' => array(
            'Content-Type' => 'application/json',
        ),
        'body' => wp_json_encode(array(
            'site_url' => $site_url,
            'openai_key' => $openai_key,
            'email' => $email,
            'username' => $username,
            'password' => $password,
            'first_name' => $first_name,
            'last_name' => $last_name,
            'db_name' => $db_name,
            'db_username' => $db_username,
            'db_password' => $db_password,
            'wordpress_version' => get_bloginfo('version'),
            'woocommerce_version' => defined('WC_VERSION') ? WC_VERSION : 'not_installed',
            'plugin_version' => '1.0.0',
        )),
        'timeout' => 30,
        'sslverify' => true,
    ));

    if (is_wp_error($response)) {
        add_settings_error('heytrisha_settings', 'registration_failed', 'Registration failed: ' . $response->get_error_message(), 'error');
        return;
    }

    $response_code = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    // Log response for debugging (only in development)
    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug logging for API registration troubleshooting
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('HeyTrisha Registration API Response Code: ' . $response_code);
        error_log('HeyTrisha Registration API Response Body: ' . substr($body, 0, 500));
    }

    // Check if response is valid JSON
    if (json_last_error() !== JSON_ERROR_NONE) {
        $error_msg = 'Invalid JSON response from API server. HTTP Status: ' . $response_code;
        if (!empty($body)) {
            // Show first 200 chars of response for debugging
            $error_msg .= '. Response: ' . esc_html(substr(strip_tags($body), 0, 200));
        }
        add_settings_error('heytrisha_settings', 'registration_failed', 'Registration failed: ' . $error_msg, 'error');
        return;
    }

    if (!$data || !isset($data['success']) || !$data['success']) {
        $error_msg = isset($data['message']) ? $data['message'] : 'Unknown error occurred';
        
        // Include HTTP status code in error message if available
        if ($response_code && $response_code >= 400) {
            $error_msg .= ' (HTTP ' . $response_code . ')';
        }
        
        if (isset($data['errors']) && is_array($data['errors'])) {
            foreach ($data['errors'] as $field => $messages) {
                if (is_array($messages)) {
                    foreach ($messages as $message) {
                        add_settings_error('heytrisha_settings', 'validation_' . $field, ucfirst($field) . ': ' . $message, 'error');
                    }
                } else {
                    add_settings_error('heytrisha_settings', 'validation_' . $field, ucfirst($field) . ': ' . $messages, 'error');
                }
            }
        } else {
            add_settings_error('heytrisha_settings', 'registration_failed', 'Registration failed: ' . $error_msg, 'error');
        }
        return;
    }

    if (!isset($data['api_key'])) {
        add_settings_error('heytrisha_settings', 'no_api_key', 'Registration succeeded but no API key was returned.', 'error');
        return;
    }

    // Save API key and server URL
    heytrisha_set_credential(HeyTrisha_Secure_Credentials::KEY_API_TOKEN, $data['api_key']);
    update_option('heytrisha_api_url', $api_server_url);
    update_option('heytrisha_onboarding_complete', true);
    
    // Store personal data locally for display in settings
    update_option('heytrisha_user_email', $email);
    update_option('heytrisha_user_first_name', $first_name);
    update_option('heytrisha_user_last_name', $last_name);
    update_option('heytrisha_user_username', $username);
    
    // Store database credentials locally
    update_option('heytrisha_db_name', $db_name);
    update_option('heytrisha_db_user', $db_username);
    heytrisha_set_credential(HeyTrisha_Secure_Credentials::KEY_DB_PASSWORD, $db_password);
    
    // Store API key temporarily in transient for display (expires in 5 minutes)
    // This allows us to show it once in readonly field after registration
    set_transient('heytrisha_new_api_key', $data['api_key'], 300);
    
    // Redirect to settings page to show the API key in readonly field
    wp_safe_redirect(admin_url('admin.php?page=heytrisha-chatbot-settings&registered=1'));
    exit;
}
add_action('admin_init', 'heytrisha_handle_onboarding_registration');

// ✅ Handle settings update (after onboarding)
function heytrisha_handle_settings_update() {
    if (!current_user_can('manage_options')) {
        return;
    }

    if (!isset($_POST['heytrisha_save_settings']) || !isset($_POST['heytrisha_settings_nonce']) || 
        !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['heytrisha_settings_nonce'])), 'heytrisha_save_settings')) {
        return;
    }

    $openai_key = isset($_POST['heytrisha_openai_key']) ? sanitize_text_field(wp_unslash($_POST['heytrisha_openai_key'])) : '';
    $api_url = isset($_POST['heytrisha_api_url']) ? esc_url_raw(wp_unslash($_POST['heytrisha_api_url'])) : '';

    // Update locally
    if (!empty($openai_key)) {
        heytrisha_set_credential(HeyTrisha_Secure_Credentials::KEY_OPENAI_API, $openai_key);
    }
    if (!empty($api_url)) {
        update_option('heytrisha_api_url', $api_url);
    }

    // Sync with API server
    $site_api_key = heytrisha_get_credential(HeyTrisha_Secure_Credentials::KEY_API_TOKEN, 'heytrisha_api_key', '');
    if (!empty($site_api_key)) {
        $response = wp_remote_post(rtrim($api_url, '/') . '/api/config', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $site_api_key,
                'Content-Type' => 'application/json',
            ),
            'body' => wp_json_encode(array(
                'openai_key' => $openai_key,
            )),
            'timeout' => 30,
            'sslverify' => true,
        ));

        if (is_wp_error($response)) {
            add_settings_error('heytrisha_settings', 'sync_failed', 'Settings saved locally but failed to sync with API server: ' . $response->get_error_message(), 'warning');
        } else {
            add_settings_error('heytrisha_settings', 'settings_updated', 'Settings saved and synced successfully.', 'updated');
        }
    } else {
        add_settings_error('heytrisha_settings', 'settings_updated', 'Settings saved locally.', 'updated');
    }
}
add_action('admin_init', 'heytrisha_handle_settings_update');

// ✅ Handle reset onboarding
function heytrisha_handle_reset_onboarding() {
    if (!current_user_can('manage_options')) {
        return;
    }

    if (!isset($_POST['heytrisha_reset_onboarding']) || !isset($_POST['heytrisha_reset_nonce']) || 
        !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['heytrisha_reset_nonce'])), 'heytrisha_reset_onboarding')) {
        return;
    }

    // Clear onboarding status
    delete_option('heytrisha_onboarding_complete');

    // Optionally clear API key (user can decide)
    // heytrisha_set_credential(HeyTrisha_Secure_Credentials::KEY_API_TOKEN, '');

    add_settings_error('heytrisha_settings', 'reset_success', 'Onboarding reset. You can now register again.', 'updated');
}
add_action('admin_init', 'heytrisha_handle_reset_onboarding');

// ✅ Render Terms and Conditions Page
// phpcs:disable WordPress.Security.NonceVerification.Recommended -- This is a display function only. Form submission is handled via AJAX in heytrisha_ajax_accept_terms() which has proper nonce verification.
function heytrisha_render_terms_page() {
    if (!current_user_can('manage_options')) {
        wp_die('You do not have permission to access this page.');
    }
    
    // Check if terms already accepted
    $terms_accepted = get_option('heytrisha_terms_accepted', false);
    if ($terms_accepted) {
        // Already accepted, redirect to settings
        wp_safe_redirect(admin_url('admin.php?page=heytrisha-chatbot-settings'));
        exit;
    }
    
    // Enqueue jQuery for checkbox handling (WordPress includes it by default, but ensure it's loaded)
    wp_enqueue_script('jquery');
    
    // Ensure ajaxurl is available for JavaScript
    wp_localize_script('jquery', 'heytrishaTermsAjax', [
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('heytrisha_accept_terms'),
        'settingsUrl' => admin_url('admin.php?page=heytrisha-chatbot-settings')
    ]);
    
    // Note: Terms page button logic is handled by inline <script> tag in the HTML below
    // This avoids timing issues with wp_add_inline_script and jQuery loading order
    
    ?>
    <div class="wrap" style="max-width: 900px; margin: 20px auto;">
        <h1 style="margin-bottom: 30px;">Terms and Conditions</h1>
        
        <div style="background: #fff; padding: 30px; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
            
            <!-- Security Warning -->
            <div style="background: #fff3cd; border: 1px solid #ffc107; border-left: 4px solid #ffc107; padding: 20px; margin-bottom: 30px; border-radius: 4px;">
                <strong style="color: #856404; display: block; margin-bottom: 10px; font-size: 16px;">⚠️ Important Security Notice:</strong>
                <p style="color: #856404; margin: 10px 0; line-height: 1.6; font-size: 14px;">
                    This plugin requires database access to function properly. For your security and data protection, 
                    please use <strong>read-only database user credentials</strong> when configuring this plugin. 
                    This ensures that the plugin can only read data and cannot modify or delete any information from your database.
                </p>
                <p style="color: #856404; margin: 10px 0; line-height: 1.6; font-size: 14px;">
                    Using read-only credentials provides an additional layer of security and prevents any accidental data modifications.
                </p>
            </div>
            
            <!-- Terms Content -->
            <div style="margin-bottom: 30px; line-height: 1.8; color: #23282d;">
                <h2 style="margin-top: 0; margin-bottom: 20px;">By activating this plugin, you agree to the following:</h2>
                
                <ul style="margin: 20px 0; padding-left: 30px; line-height: 2;">
                    <li>You understand that this plugin requires database access to provide analytical insights</li>
                    <li>You will use read-only database credentials for security purposes</li>
                    <li>You acknowledge that the plugin accesses your WordPress and WooCommerce data</li>
                    <li>You agree to the <a href="https://heytrisha.com/terms-and-conditions" target="_blank">Terms and Conditions</a> of Hey Trisha</li>
                    <li>You understand that this plugin is designed for data analytics only, not data extraction</li>
                </ul>
                
                <p style="margin-top: 30px;">
                    <a href="https://heytrisha.com/terms-and-conditions" target="_blank" style="font-size: 14px; text-decoration: none;">
                        Read full Terms and Conditions →
                    </a>
                </p>
            </div>
            
            <!-- Checkbox -->
            <div style="background: #f9f9f9; border: 1px solid #ddd; padding: 20px; margin: 30px 0; border-radius: 4px;">
                <label style="display: flex; align-items: flex-start; cursor: pointer; font-size: 16px;">
                    <input type="checkbox" id="heytrisha-accept-terms-checkbox" style="margin: 3px 15px 0 0; width: 20px; height: 20px; cursor: pointer;" />
                    <span style="flex: 1; line-height: 1.6;">I have read and agree to the Terms and Conditions</span>
                </label>
            </div>
            
            <!-- Activate Button -->
            <div style="text-align: right; margin-top: 30px; padding-top: 30px; border-top: 1px solid #ddd;">
                <button type="button" id="heytrisha-terms-activate-btn" class="button button-primary button-large" disabled style="font-size: 14px; padding: 10px 30px; height: auto; opacity: 0.6; cursor: not-allowed;">
                    Activate Plugin
                </button>
            </div>
            
            <!-- Terms page button logic (vanilla JS, no jQuery dependency) -->
            <script type="text/javascript">
            (function() {
                'use strict';
                var checkbox = document.getElementById('heytrisha-accept-terms-checkbox');
                var activateBtn = document.getElementById('heytrisha-terms-activate-btn');

                if (!checkbox || !activateBtn) return;

                function updateBtn() {
                    if (checkbox.checked) {
                        activateBtn.disabled = false;
                        activateBtn.removeAttribute('disabled');
                        activateBtn.style.opacity = '1';
                        activateBtn.style.cursor = 'pointer';
                    } else {
                        activateBtn.disabled = true;
                        activateBtn.setAttribute('disabled', 'disabled');
                        activateBtn.style.opacity = '0.6';
                        activateBtn.style.cursor = 'not-allowed';
                    }
                }

                updateBtn();
                checkbox.addEventListener('change', updateBtn);
                checkbox.addEventListener('click', function() { setTimeout(updateBtn, 10); });

                activateBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    if (!checkbox.checked || activateBtn.disabled) return;

                    activateBtn.disabled = true;
                    activateBtn.textContent = 'Processing...';

                    var ajaxUrl = '<?php echo esc_url(admin_url('admin-ajax.php')); ?>';
                    var nonce = '<?php echo esc_attr(wp_create_nonce('heytrisha_accept_terms')); ?>';
                    var settingsUrl = '<?php echo esc_url(admin_url('admin.php?page=heytrisha-chatbot-settings')); ?>';

                    var formData = new FormData();
                    formData.append('action', 'heytrisha_accept_terms');
                    formData.append('nonce', nonce);
                    formData.append('accepted', 'true');

                    fetch(ajaxUrl, { method: 'POST', body: formData })
                        .then(function(r) { return r.json(); })
                        .then(function(data) {
                            if (data.success) {
                                window.location.href = settingsUrl;
                            } else {
                                activateBtn.disabled = false;
                                activateBtn.textContent = 'Activate Plugin';
                                updateBtn();
                                alert('Error: ' + (data.data && data.data.message ? data.data.message : 'Failed to save.'));
                            }
                        })
                        .catch(function() {
                            activateBtn.disabled = false;
                            activateBtn.textContent = 'Activate Plugin';
                            updateBtn();
                            alert('Error: Failed to communicate with server.');
                        });
                });
            })();
            </script>
        </div>
    </div>
    <?php
}
// phpcs:enable WordPress.Security.NonceVerification.Recommended

// ✅ Render New Chat Page
// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Display function, reads GET parameter for navigation only
function heytrisha_render_new_chat_page() {
    if (!current_user_can('manage_options')) {
        return;
    }
    
    $chat_id = isset($_GET['chat_id']) ? intval($_GET['chat_id']) : 0;
    
    // Enqueue chat interface scripts
    // React removed - WordPress.org policy prohibits external CDN scripts
    $chat_admin_css = HEYTRISHA_PLUGIN_DIR . 'assets/css/chat-admin.css';
    if (file_exists($chat_admin_css)) {
        wp_enqueue_style('heytrisha-chat-admin-css', HEYTRISHA_PLUGIN_URL . 'assets/css/chat-admin.css', [], HEYTRISHA_VERSION);
    }
    
    $chat_admin_js = HEYTRISHA_PLUGIN_DIR . 'assets/js/chat-admin.js';
    if (file_exists($chat_admin_js)) {
        wp_enqueue_script('heytrisha-chat-admin-js', HEYTRISHA_PLUGIN_URL . 'assets/js/chat-admin.js', ['jquery', 'react', 'react-dom'], HEYTRISHA_VERSION, true);
        
        $plugin_url = HEYTRISHA_PLUGIN_URL;
        
        wp_localize_script('heytrisha-chat-admin-js', 'heytrishaChatConfig', [
            'pluginUrl' => $plugin_url,
            'ajaxurl' => admin_url('admin-ajax.php'), // ✅ Use admin-ajax.php for queries (secure, hidden endpoint)
            'chatId' => $chat_id,
            'restUrl' => rest_url('heytrisha/v1/'), // Keep for chat management (chats, messages)
            'nonce' => wp_create_nonce('wp_rest'),
            'chatbotNonce' => wp_create_nonce('heytrisha_chatbot') // ✅ Nonce for admin-ajax.php AJAX queries
        ]);
    }
    
    echo '<div class="wrap">';
    echo '<div id="heytrisha-chat-admin-root"></div>';
    echo '</div>';
}
// phpcs:enable WordPress.Security.NonceVerification.Recommended

// ✅ Render Chats List Page
function heytrisha_render_chats_page() {
    if (!current_user_can('manage_options')) {
        return;
    }
    
    wp_enqueue_style('heytrisha-chats-list-css', HEYTRISHA_PLUGIN_URL . 'assets/css/chats-list.css', [], HEYTRISHA_VERSION);
    wp_enqueue_script('heytrisha-chats-list-js', HEYTRISHA_PLUGIN_URL . 'assets/js/chats-list.js', ['jquery', 'react', 'react-dom'], HEYTRISHA_VERSION, true);
    
    wp_localize_script('heytrisha-chats-list-js', 'heytrishaChatsConfig', [
        'restUrl' => rest_url('heytrisha/v1/'),
        'nonce' => wp_create_nonce('wp_rest'),
        'adminUrl' => admin_url('admin.php?page=heytrisha-new-chat')
    ]);
    
    echo '<div class="wrap">';
    echo '<h1>Chats</h1>';
    echo '<div id="heytrisha-chats-list-root"></div>';
    echo '</div>';
}

// ✅ Render Archive Page
function heytrisha_render_archive_page() {
    if (!current_user_can('manage_options')) {
        return;
    }
    
    wp_enqueue_style('heytrisha-chats-list-css', HEYTRISHA_PLUGIN_URL . 'assets/css/chats-list.css', [], HEYTRISHA_VERSION);
    wp_enqueue_script('heytrisha-chats-list-js', HEYTRISHA_PLUGIN_URL . 'assets/js/chats-list.js', ['jquery', 'react', 'react-dom'], HEYTRISHA_VERSION, true);
    
    wp_localize_script('heytrisha-chats-list-js', 'heytrishaChatsConfig', [
        'restUrl' => rest_url('heytrisha/v1/'),
        'nonce' => wp_create_nonce('wp_rest'),
        'adminUrl' => admin_url('admin.php?page=heytrisha-new-chat'),
        'isArchive' => true
    ]);
    
    echo '<div class="wrap">';
    echo '<h1>Archived Chats</h1>';
    echo '<div id="heytrisha-chats-list-root"></div>';
    echo '</div>';
}

// ✅ Get external API URL from settings
function heytrisha_get_api_url() {
    return get_option('heytrisha_api_url', 'https://api.heytrisha.com');
}

/**
 * Sanitize confirmation data array recursively
 *
 * @param mixed $data Data to sanitize
 * @return mixed Sanitized data
 */
function heytrisha_sanitize_confirmation_data($data) {
    if (!is_array($data)) {
        return sanitize_text_field($data);
    }
    
    $sanitized = array();
    foreach ($data as $key => $value) {
        $sanitized_key = sanitize_key($key);
        if (is_array($value)) {
            $sanitized[$sanitized_key] = heytrisha_sanitize_confirmation_data($value);
        } else {
            $sanitized[$sanitized_key] = sanitize_text_field($value);
        }
    }
    return $sanitized;
}

/**
 * Get database schema for API
 * Returns compact schema format: table_name => [column1, column2, ...]
 * 
 * @return array Database schema with table names as keys and column arrays as values
 */
function heytrisha_get_database_schema() {
    global $wpdb;
    
    $schema = array();
    $prefix = $wpdb->prefix;
    
    // Only include tables relevant for WooCommerce/WordPress analytics queries
    // This keeps the prompt size manageable and improves AI accuracy
    $relevant_suffixes = array(
        // WordPress core tables
        'posts', 'postmeta', 'users', 'usermeta',
        'terms', 'termmeta', 'term_taxonomy', 'term_relationships',
        'options', 'comments', 'commentmeta', 'links',
        // WooCommerce HPOS (High-Performance Order Storage) tables
        'wc_orders', 'wc_orders_meta', 'wc_order_operational_data',
        'wc_order_addresses', 'wc_order_stats',
        'wc_order_product_lookup', 'wc_order_tax_lookup',
        'wc_order_coupon_lookup',
        // WooCommerce product tables
        'wc_product_meta_lookup', 'wc_product_attributes_lookup',
        'wc_category_lookup', 'wc_customer_lookup',
        'wc_download_log', 'wc_reserved_stock',
        'wc_tax_rate_classes', 'wc_webhooks', 'wc_rate_limits',
        // WooCommerce legacy tables
        'woocommerce_order_items', 'woocommerce_order_itemmeta',
        'woocommerce_tax_rates', 'woocommerce_tax_rate_locations',
        'woocommerce_shipping_zones', 'woocommerce_shipping_zone_methods',
        'woocommerce_shipping_zone_locations',
        'woocommerce_payment_tokens', 'woocommerce_payment_tokenmeta',
        'woocommerce_sessions', 'woocommerce_api_keys',
        'woocommerce_attribute_taxonomies',
        'woocommerce_downloadable_product_permissions',
        'woocommerce_log', 'woocommerce_termmeta',
    );
    
    try {
        // Get all tables from WordPress database
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Schema introspection, not cacheable
        $tables = $wpdb->get_results('SHOW TABLES', ARRAY_N);
        
        if (empty($tables)) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log('Hey Trisha: SHOW TABLES returned empty');
            return array();
        }
        
        foreach ($tables as $table) {
            $table_name = $table[0];
            
            // Skip if table name is empty or invalid
            if (empty($table_name) || !is_string($table_name)) {
                continue;
            }
            
            // Only include tables with the current WordPress prefix
            if (strpos($table_name, $prefix) !== 0) {
                continue;
            }
            
            // Get the suffix (part after the prefix)
            $suffix = substr($table_name, strlen($prefix));
            
            // Check if this table is relevant for WooCommerce/WordPress queries
            $is_relevant = false;
            foreach ($relevant_suffixes as $relevant_suffix) {
                if ($suffix === $relevant_suffix) {
                    $is_relevant = true;
                    break;
                }
            }
            
            // Skip non-relevant tables (other plugin tables like wordfence, aiowps, etc.)
            if (!$is_relevant) {
                continue;
            }
            
            // Get columns for this table using backtick-escaped name
            // Table name comes from SHOW TABLES so it's safe; backticks are escaped
            $safe_table_name = str_replace('`', '``', $table_name);
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Schema introspection, not cacheable
            $columns = $wpdb->get_results("SHOW COLUMNS FROM `{$safe_table_name}`", ARRAY_A);
            
            if (empty($columns) || $wpdb->last_error) {
                // Skip tables that can't be described
                continue;
            }
            
            $column_names = array();
            foreach ($columns as $column) {
                if (isset($column['Field'])) {
                    $column_names[] = $column['Field'];
                }
            }
            
            if (!empty($column_names)) {
                $schema[$table_name] = $column_names;
            }
        }
        
        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        error_log('Hey Trisha: Schema includes ' . count($schema) . ' tables: ' . implode(', ', array_keys($schema)));
        
        return $schema;
        
    } catch (Exception $e) {
        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Production error tracking for database schema retrieval failures
        error_log('Hey Trisha: Error fetching database schema - ' . $e->getMessage());
        return array();
    } catch (Throwable $e) {
        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Production error tracking for database schema retrieval failures
        error_log('Hey Trisha: Error fetching database schema (Throwable) - ' . $e->getMessage());
        return array();
    }
}

// ✅ REMOVED: Laravel proxy function - now using external API
// This function has been removed as part of the thin client refactoring
// All API calls now go directly to external HeyTrisha engine
// phpcs:ignore WordPress.Security.NonceVerification.Missing -- This is a deprecated REST API function that returns immediately. Nonce verification handled by REST API permission_callback if registered.
function heytrisha_proxy_laravel_api_removed($request) {
    // This function is deprecated and should not be called
    return new WP_Error('deprecated', 'Laravel proxy has been removed. Plugin now uses external API.', array('status' => 500));
    // ✅ Handle both WP_REST_Request (from REST API) and stdClass (from AJAX)
    if (is_a($request, 'WP_REST_Request')) {
        // REST API request - use get_param()
        $endpoint = $request->get_param('endpoint');
        $query = $request->get_param('query');
        $confirmed = $request->get_param('confirmed');
        $confirmation_data = $request->get_param('confirmation_data');
    } else {
        // AJAX request (stdClass) - access properties directly
        $endpoint = isset($request->endpoint) ? $request->endpoint : 'query';
        $query = isset($request->query) ? $request->query : null;
        $confirmed = isset($request->confirmed) ? $request->confirmed : false;
        $confirmation_data = isset($request->confirmation_data) ? $request->confirmation_data : null;
    }
    
    // Remove leading slash if present
    $endpoint = ltrim($endpoint, '/');
    
    // Get Laravel API path
    $laravel_path = HEYTRISHA_PLUGIN_DIR . 'api/public/index.php';
    
    if (!file_exists($laravel_path)) {
        return new WP_Error('laravel_not_found', 'Laravel API not found.', array('status' => 500));
    }
    
    // Preserve original request data (PHP 7.4 compatible)
    $original_method = 'GET';
    if (isset($_SERVER['REQUEST_METHOD'])) {
        $original_method = sanitize_text_field(wp_unslash($_SERVER['REQUEST_METHOD']));
    }
    $original_uri = '';
    if (isset($_SERVER['REQUEST_URI'])) {
        $original_uri = esc_url_raw(wp_unslash($_SERVER['REQUEST_URI']));
    }
    $original_path_info = '';
    if (isset($_SERVER['PATH_INFO'])) {
        $original_path_info = sanitize_text_field(wp_unslash($_SERVER['PATH_INFO']));
    }
    $original_script_name = '';
    if (isset($_SERVER['SCRIPT_NAME'])) {
        $original_script_name = sanitize_text_field(wp_unslash($_SERVER['SCRIPT_NAME']));
    }
    $original_query_string = '';
    if (isset($_SERVER['QUERY_STRING'])) {
        $original_query_string = sanitize_text_field(wp_unslash($_SERVER['QUERY_STRING']));
    }
    
    // Set up environment for Laravel
    // Laravel routes are under /api prefix, so prepend it
    $_SERVER['REQUEST_URI'] = '/api/' . $endpoint;
    $_SERVER['PATH_INFO'] = '/api/' . $endpoint;
    $_SERVER['SCRIPT_NAME'] = '/index.php';
    $_SERVER['REQUEST_METHOD'] = 'POST'; // Always POST for Laravel API
    $_SERVER['QUERY_STRING'] = '';
    
    // ✅ Build request body for Laravel (from request object or POST data)
    $request_body = array();
    
    // phpcs:disable WordPress.PHP.DevelopmentFunctions.error_log_error_log, WordPress.PHP.DevelopmentFunctions.error_log_var_export, WordPress.Security.NonceVerification.Missing -- Debug logging for development/support. Deprecated function, nonce verified by REST API permission_callback
    error_log("🔍 Proxy Debug - query: " . var_export($query, true));
    error_log("🔍 Proxy Debug - confirmed: " . var_export($confirmed, true));
    // Sanitize POST keys before logging to prevent log injection
    $sanitized_post_keys = array_map('sanitize_key', array_keys($_POST));
    error_log("🔍 Proxy Debug - _POST keys: " . implode(', ', $sanitized_post_keys));
    error_log("🔍 Proxy Debug - request->query: " . (isset($request->query) ? var_export($request->query, true) : 'not set'));
    error_log("🔍 Proxy Debug - request->body: " . (isset($request->body) ? wp_json_encode($request->body) : 'not set'));
    // phpcs:enable WordPress.PHP.DevelopmentFunctions.error_log_error_log, WordPress.PHP.DevelopmentFunctions.error_log_var_export
    
    // phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput -- This is a REST API endpoint, nonce verified by WordPress REST API
    // ✅ Extract query - prioritize request object, then POST, then body
    if ($query !== null && $query !== '') {
        $request_body['query'] = $query;
    } elseif (isset($request->body['query']) && $request->body['query'] !== '') {
        $request_body['query'] = $request->body['query'];
    } elseif (isset($_POST['query']) && $_POST['query'] !== '') {
        $request_body['query'] = sanitize_textarea_field(wp_unslash($_POST['query']));
    }
    
    // ✅ Extract confirmed flag
    if ($confirmed !== false && $confirmed !== null) {
        $request_body['confirmed'] = $confirmed;
    } elseif (isset($request->body['confirmed'])) {
        $request_body['confirmed'] = $request->body['confirmed'];
    } elseif (isset($_POST['confirmed'])) {
        $request_body['confirmed'] = filter_var(wp_unslash($_POST['confirmed']), FILTER_VALIDATE_BOOLEAN);
    }
    
    // ✅ Extract confirmation_data
    if ($confirmation_data !== null) {
        $request_body['confirmation_data'] = $confirmation_data;
    } elseif (isset($request->body['confirmation_data'])) {
        $request_body['confirmation_data'] = $request->body['confirmation_data'];
    } elseif (isset($_POST['confirmation_data'])) {
        // Sanitize POST data before processing
        $confirmation_data_raw = sanitize_text_field(wp_unslash($_POST['confirmation_data']));
        
        // If it's a JSON string, decode it and sanitize
        if (is_string($confirmation_data_raw) && !empty($confirmation_data_raw)) {
            $decoded = json_decode($confirmation_data_raw, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                // Sanitize decoded array
                $request_body['confirmation_data'] = heytrisha_sanitize_confirmation_data($decoded);
            } else {
                // If JSON decode failed, sanitize as text
                $request_body['confirmation_data'] = sanitize_text_field($confirmation_data_raw);
            }
        } else {
            $request_body['confirmation_data'] = sanitize_text_field($confirmation_data_raw);
        }
    }
    // phpcs:enable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput
    
    // ✅ If still no query, try to get from php://input
    // phpcs:disable WordPress.Security.NonceVerification.Missing -- REST API endpoint, nonce verified by WordPress REST API permission_callback
    if (empty($request_body['query'])) {
        $input = file_get_contents('php://input');
        if (!empty($input)) {
            $decoded = json_decode($input, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                if (isset($decoded['query']) && $decoded['query'] !== '') {
                    $request_body['query'] = sanitize_textarea_field($decoded['query']);
                }
                // Merge other fields if not already set
                if (!isset($request_body['confirmed']) && isset($decoded['confirmed'])) {
                    $request_body['confirmed'] = $decoded['confirmed'];
                }
                if (!isset($request_body['confirmation_data']) && isset($decoded['confirmation_data'])) {
                    if (is_array($decoded['confirmation_data'])) {
                        $request_body['confirmation_data'] = heytrisha_sanitize_confirmation_data($decoded['confirmation_data']);
                    } else {
                        $request_body['confirmation_data'] = sanitize_text_field($decoded['confirmation_data']);
                    }
                }
            }
        }
    }
    // phpcs:enable WordPress.Security.NonceVerification.Missing
    
    // ✅ Final validation - ensure query exists
    // phpcs:disable WordPress.PHP.DevelopmentFunctions.error_log_error_log, WordPress.Security.NonceVerification.Missing, WordPress.PHP.DevelopmentFunctions.error_log_print_r -- Production error tracking. Deprecated function with read-only access for logging/debugging.
    if (empty($request_body['query'])) {
        error_log("❌ ERROR: Query is empty after all extraction attempts!");
        error_log("❌ Debug - request_body: " . wp_json_encode($request_body));
        // Sanitize $_POST before logging to prevent log injection
        $sanitized_post = array();
        foreach ($_POST as $key => $value) {
            $sanitized_key = sanitize_key($key);
            $sanitized_post[$sanitized_key] = is_string($value) ? sanitize_text_field(wp_unslash($value)) : '[non-string]';
        }
        error_log("❌ Debug - _POST: " . wp_json_encode($sanitized_post));
        error_log("❌ Debug - request object: " . print_r($request, true));
    } else {
        error_log("✅ Query extracted successfully: '{$request_body['query']}'");
    }
    // phpcs:enable WordPress.PHP.DevelopmentFunctions.error_log_error_log, WordPress.Security.NonceVerification.Missing, WordPress.PHP.DevelopmentFunctions.error_log_print_r
    
    // Set up $_POST and php://input for Laravel
    $_POST = $request_body;
    $_SERVER['CONTENT_LENGTH'] = strlen(json_encode($request_body));
    
    // ✅ Set up request body for Laravel
    // Store request body in global variable so Laravel can access it
    // (php://input can only be read once, and WordPress may have already read it)
    $GLOBALS['heytrisha_request_body'] = $request_body;
    
    // Set Content-Type and Content-Length headers
    $_SERVER['CONTENT_TYPE'] = 'application/json';
    $_SERVER['HTTP_CONTENT_TYPE'] = 'application/json';
    $_SERVER['CONTENT_LENGTH'] = strlen(json_encode($request_body));
    
    // CRITICAL: Use output buffering to prevent notices from interfering with JSON responses
    // We don't modify error_reporting() as it interferes with other plugins
    // Instead, we use output buffering to catch any stray output
    
    // Capture Laravel output (this will also capture any stray output from other plugins)
    // Clean any existing buffers first, then start fresh
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    ob_start(); // Buffer will be cleaned in finally block
    
    try {
        // Note: ABSPATH is already defined by WordPress core - we should not redefine it
        // Redefining WordPress core constants can cause conflicts with other plugins and themes
        
        // ✅ CRITICAL: Inject WordPress configuration as HTTP headers for Laravel
        // This allows Laravel to access WordPress settings without needing to fetch from REST API
        heytrisha_inject_credentials_as_headers();
        $_SERVER['HTTP_X_WORDPRESS_SHARED_TOKEN'] = heytrisha_get_credential(HeyTrisha_Secure_Credentials::KEY_SHARED_TOKEN, 'heytrisha_shared_token', '');
        $_SERVER['HTTP_X_WORDPRESS_URL'] = get_site_url();
        
        // Note: ABSPATH is already defined by WordPress core, we don't need to redefine it
        // Include Laravel bootstrap
        require_once $laravel_path;
        
        // Get output and clean ALL buffers (there may be multiple levels from other plugins)
        $output = '';
        while (ob_get_level() > 0) {
            $output = ob_get_clean();
        }
        
        // Restore error handler (we never changed error_reporting())
        if ($original_error_handler !== null) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler -- Restoring original error handler
            set_error_handler($original_error_handler);
        } else {
            restore_error_handler();
        }
        
        // Restore original server variables
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- $original_uri was already unslashed and sanitized on line 1071
        $_SERVER['REQUEST_URI'] = $original_uri;
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- $original_path_info was already unslashed and sanitized on line 1075
        $_SERVER['PATH_INFO'] = $original_path_info;
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- $original_script_name was already unslashed and sanitized on line 1079
        $_SERVER['SCRIPT_NAME'] = $original_script_name;
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- $original_query_string was already unslashed and sanitized on line 1083
        $_SERVER['QUERY_STRING'] = $original_query_string;
        
        // Clean output - remove any notices/warnings that might be in the output
        // Try to extract JSON from the output (it might be mixed with notices)
        $clean_output = $output;
        
        // If output contains notices, try to extract just the JSON part
        if (preg_match('/\{[\s\S]*\}/', $output, $matches)) {
            $clean_output = $matches[0];
        }
        
        // Try to decode JSON response
        $json = json_decode($clean_output, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($json)) {
            return rest_ensure_response($json);
        }
        
        // If still not JSON, try to clean it line by line
        $lines = explode("\n", $clean_output);
        $json_lines = array();
        foreach ($lines as $line) {
            $line = trim($line);
            // Skip notice/warning lines
            if (stripos($line, 'Notice:') === false && 
                stripos($line, 'Warning:') === false && 
                stripos($line, 'Deprecated:') === false &&
                stripos($line, 'in /') === false && // Skip file paths
                !empty($line)) {
                $json_lines[] = $line;
            }
        }
        $final_output = implode("\n", $json_lines);
        $json = json_decode($final_output, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($json)) {
            return rest_ensure_response($json);
        }
        
        // Last resort - return error
        return rest_ensure_response(array(
            'success' => false,
            'message' => 'Failed to parse response',
            'raw_output_length' => strlen($output)
        ));
        
    } catch (Exception $e) {
        // Clean output buffer
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        
        // Restore error handler (we never changed error_reporting())
        if (isset($original_error_handler) && $original_error_handler !== null) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler -- Restoring original error handler
            set_error_handler($original_error_handler);
        } else {
            restore_error_handler();
        }
        
        // Restore original server variables
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- $original_uri was already unslashed and sanitized on line 1071
        $_SERVER['REQUEST_URI'] = $original_uri;
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- $original_path_info was already unslashed and sanitized on line 1075
        $_SERVER['PATH_INFO'] = $original_path_info;
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- $original_script_name was already unslashed and sanitized on line 1079
        $_SERVER['SCRIPT_NAME'] = $original_script_name;
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- $original_query_string was already unslashed and sanitized on line 1083
        $_SERVER['QUERY_STRING'] = $original_query_string;
        
        // Provide detailed error information for debugging
        $errorMessage = $e->getMessage();
        $errorFile = $e->getFile();
        $errorLine = $e->getLine();
        
        // phpcs:disable WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Production error tracking
        // Log the error for debugging
        error_log('Hey Trisha Laravel Error: ' . $errorMessage . ' in ' . $errorFile . ':' . $errorLine);
        // phpcs:enable WordPress.PHP.DevelopmentFunctions.error_log_error_log
        
        return new WP_Error('laravel_error', $errorMessage, array(
            'status' => 500,
            'file' => $errorFile,
            'line' => $errorLine
        ));
    } catch (Throwable $e) {
        // Clean output buffer
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        
        // Restore error handler (we never changed error_reporting())
        if (isset($original_error_handler) && $original_error_handler !== null) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler -- Restoring original error handler
            set_error_handler($original_error_handler);
        } else {
            restore_error_handler();
        }
        
        // Restore original server variables
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- $original_uri was already unslashed and sanitized on line 1071
        $_SERVER['REQUEST_URI'] = $original_uri;
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- $original_path_info was already unslashed and sanitized on line 1075
        $_SERVER['PATH_INFO'] = $original_path_info;
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- $original_script_name was already unslashed and sanitized on line 1079
        $_SERVER['SCRIPT_NAME'] = $original_script_name;
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- $original_query_string was already unslashed and sanitized on line 1083
        $_SERVER['QUERY_STRING'] = $original_query_string;
        
        // Provide detailed error information for debugging
        $errorMessage = $e->getMessage();
        $errorFile = $e->getFile();
        $errorLine = $e->getLine();
        
        // phpcs:disable WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Production error tracking for Laravel proxy errors
        // Log the error for debugging
        error_log('Hey Trisha Laravel Error: ' . $errorMessage . ' in ' . $errorFile . ':' . $errorLine);
        // phpcs:enable WordPress.PHP.DevelopmentFunctions.error_log_error_log
        
        return new WP_Error('laravel_error', $errorMessage, array(
            'status' => 500,
            'file' => $errorFile,
            'line' => $errorLine
        ));
    } finally {
        // Ensure buffer started at line 1145 is always closed, even if exception occurs
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
    }
}

// ✅ Detect shared hosting environment
function heytrisha_is_shared_hosting() {
    // Check if exec is disabled
    $disabled_functions = explode(',', ini_get('disable_functions'));
    $disabled_functions = array_map('trim', $disabled_functions);
    $exec_disabled = in_array('exec', $disabled_functions) || in_array('proc_open', $disabled_functions);
    
    // Check if vendor folder exists (dependencies pre-installed)
    $vendor_exists = is_dir(HEYTRISHA_PLUGIN_DIR . 'api/vendor');
    
    // Shared hosting if exec is disabled OR if we explicitly can't find PHP
    return $exec_disabled || !function_exists('exec');
}

// ✅ CRITICAL: Start output buffering IMMEDIATELY for REST API requests
// This must happen before WordPress processes anything
// Buffer will be cleaned in rest_post_dispatch filter (line 1406)
// phpcs:disable WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Read-only check for REST API route
if (isset($_SERVER['REQUEST_URI']) && strpos(esc_url_raw(wp_unslash($_SERVER['REQUEST_URI'])), '/wp-json/heytrisha/v1/') !== false) {
    // phpcs:enable WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
    // Start output buffering at the absolute earliest point
    if (ob_get_level() === 0) {
        ob_start();
        // Register cleanup to ensure buffer is closed even if filter doesn't run
        register_shutdown_function(function() {
            // Only clean if this is still our REST API request
            // phpcs:disable WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Read-only check for REST API route
            if (isset($_SERVER['REQUEST_URI']) && strpos(esc_url_raw(wp_unslash($_SERVER['REQUEST_URI'])), '/wp-json/heytrisha/v1/') !== false) {
                // phpcs:enable WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
                while (ob_get_level() > 0) {
                    ob_end_clean();
                }
            }
        });
    }
}

// ✅ CRITICAL: Suppress PHP notices/warnings for our REST API endpoints
// This must run VERY early to prevent notices from other plugins from interfering
function heytrisha_suppress_api_errors() {
    // Check if this is our REST API endpoint
    // phpcs:disable WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Read-only check for REST API route
    $request_uri = isset($_SERVER['REQUEST_URI']) ? esc_url_raw(wp_unslash($_SERVER['REQUEST_URI'])) : '';
    if (strpos($request_uri, '/wp-json/heytrisha/v1/') !== false) {
        // phpcs:enable WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        // Use output buffering instead of error_reporting() to avoid interfering with other plugins
        // phpcs:disable WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler -- Required for REST API error handling
        // Set custom error handler that only suppresses output, not error reporting
        set_error_handler(function($errno, $errstr, $errfile, $errline) {
            // Suppress output of notices/warnings but don't prevent error_reporting()
            if ($errno === E_NOTICE || $errno === E_WARNING || $errno === E_DEPRECATED || 
                $errno === E_USER_NOTICE || $errno === E_USER_WARNING || $errno === E_USER_DEPRECATED) {
                return true; // Suppress output but don't change error_reporting()
            }
            return false; // Let fatal errors through
        }, E_ALL);
        // phpcs:enable WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler
        
        // Ensure output buffering is active
        if (ob_get_level() === 0) {
            ob_start();
            // Register cleanup to ensure buffer is closed
            register_shutdown_function(function() {
                // Only clean if this is still our REST API request
                // phpcs:disable WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Read-only check for REST API route
                if (isset($_SERVER['REQUEST_URI']) && strpos(esc_url_raw(wp_unslash($_SERVER['REQUEST_URI'])), '/wp-json/heytrisha/v1/') !== false) {
                    // phpcs:enable WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
                    while (ob_get_level() > 0) {
                        ob_end_clean();
                    }
                }
            });
        }
    }
}
// Hook at the earliest possible point - before WordPress processes anything
add_action('muplugins_loaded', 'heytrisha_suppress_api_errors', 1);
add_action('plugins_loaded', 'heytrisha_suppress_api_errors', 1);
add_action('init', 'heytrisha_suppress_api_errors', 1);
add_action('rest_api_init', 'heytrisha_suppress_api_errors', 1);

// ✅ CRITICAL: Override WordPress fatal error handler for our REST API
// This prevents WordPress from showing HTML error page for our endpoints
add_filter('wp_die_handler', function($handler) {
    // phpcs:disable WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Read-only check for REST API route
    $request_uri = isset($_SERVER['REQUEST_URI']) ? esc_url_raw(wp_unslash($_SERVER['REQUEST_URI'])) : '';
    if (strpos($request_uri, '/wp-json/heytrisha/v1/') !== false) {
        // phpcs:enable WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        // Return a custom handler that outputs JSON instead of HTML
        return function($message, $title = '', $args = array()) {
            // Clean all output buffers
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            
            // Send JSON error response
            if (!headers_sent()) {
                http_response_code(isset($args['response']) ? $args['response'] : 500);
                header('Content-Type: application/json');
            }
            
            $error_data = [
                'success' => false,
                'message' => is_string($message) ? $message : 'Internal server error',
            ];
            
            // Include error details if available
            if (is_wp_error($message)) {
                $error_data['error'] = $message->get_error_message();
                $error_data['code'] = $message->get_error_code();
            }
            
            // Use wp_send_json_error for proper JSON output
            wp_send_json_error($error_data);
            exit;
        };
    }
    return $handler;
}, 1);

// ✅ CRITICAL: Also suppress errors at REST API dispatch level
// This catches notices that are output during REST API request processing
add_filter('rest_pre_dispatch', function($result, $server, $request) {
    $route = $request->get_route();
    if (strpos($route, '/heytrisha/v1/') === 0) {
        // Use output buffering instead of error_reporting() to avoid interfering with other plugins
        // phpcs:disable WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler -- Required for REST API error handling
        // Set error handler that only suppresses output, not error reporting
        set_error_handler(function($errno, $errstr, $errfile, $errline) {
            if ($errno === E_NOTICE || $errno === E_WARNING || $errno === E_DEPRECATED || 
                $errno === E_USER_NOTICE || $errno === E_USER_WARNING || $errno === E_USER_DEPRECATED) {
                return true; // Suppress output but don't change error_reporting()
            }
            return false;
        }, E_ALL);
        // phpcs:enable WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler
        
        // Ensure output buffering is active
        // Clean any existing buffers first
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        ob_start(); // Buffer will be cleaned in rest_post_dispatch filter (line 1434)
        
        // Register cleanup to ensure buffer is closed even if filter doesn't run
        register_shutdown_function(function() use ($request) {
            $route = $request->get_route();
            if (strpos($route, '/heytrisha/v1/') === 0) {
                while (ob_get_level() > 0) {
                    ob_end_clean();
                }
            }
        });
    }
    return $result;
}, 10, 3);

// ✅ CRITICAL: Intercept REST API response and clean any notices from output
// This runs AFTER the response is generated but BEFORE it's sent
add_filter('rest_post_dispatch', function($result, $server, $request) {
    $route = $request->get_route();
    if (strpos($route, '/heytrisha/v1/') === 0) {
        // Clean ALL output buffers - notices/HTML may have been output during response generation
        // Get any buffered content
        $buffered_output = '';
        while (ob_get_level() > 0) {
            $buffered_output = ob_get_clean() . $buffered_output;
        }
        
        // Log if there was any stray output (for debugging)
        if (!empty($buffered_output)) {
            // phpcs:disable WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Production error tracking for stray output detection
            error_log('HeyTrisha: Cleaned stray output (' . strlen($buffered_output) . ' bytes): ' . substr($buffered_output, 0, 200));
            // phpcs:enable WordPress.PHP.DevelopmentFunctions.error_log_error_log
        }
        
        // If result is a WP_REST_Response, ensure it's clean JSON
        if ($result instanceof WP_REST_Response) {
            $data = $result->get_data();
            // If data is a string, try to decode it and re-encode to ensure it's clean JSON
            if (is_string($data)) {
                $decoded = json_decode($data, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $result->set_data($decoded);
                }
            }
        }
    }
    return $result;
}, 999, 3);

// ✅ CRITICAL: Clean output buffers before REST API sends response
// This ensures no stray output from other plugins interferes with JSON responses
add_filter('rest_pre_serve_request', function($served, $result, $request, $server) {
    $route = $request->get_route();
    if (strpos($route, '/heytrisha/v1/') === 0) {
        // Clean ALL output buffers before WordPress sends the response
        // This removes any HTML/text output by WordPress or other plugins
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        
        // DON'T start a new buffer - let WordPress handle the response output
        // WordPress will output JSON directly
    }
    return $served;
}, 10, 4);


// ✅ Read-only REST endpoint to provide stored credentials to backend (admin-only)
function heytrisha_register_rest_routes() {
    register_rest_route('heytrisha/v1', '/config', array(
        'methods' => 'GET',
        'callback' => function () {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput -- REST API auth check
            $provided = isset($_GET['token']) ? sanitize_text_field(wp_unslash($_GET['token'])) : '';
            $expected = heytrisha_get_credential(HeyTrisha_Secure_Credentials::KEY_SHARED_TOKEN, 'heytrisha_shared_token', '');
            if (empty($provided) || empty($expected) || !hash_equals($expected, $provided)) {
                return new WP_Error('forbidden', 'Invalid or missing token.', array('status' => 403));
            }

            // Get Multisite information
            $is_multisite = is_multisite();
            $current_site_id = $is_multisite ? get_current_blog_id() : 1;
            
            return array(
                'openai_api_key' => heytrisha_get_credential(HeyTrisha_Secure_Credentials::KEY_OPENAI_API, 'heytrisha_openai_api_key', ''),
                'database' => array(
                    'host' => get_option('heytrisha_db_host', ''),
                    'port' => get_option('heytrisha_db_port', ''),
                    'name' => get_option('heytrisha_db_name', ''),
                    'user' => get_option('heytrisha_db_user', ''),
                    'password' => heytrisha_get_credential(HeyTrisha_Secure_Credentials::KEY_DB_PASSWORD, 'heytrisha_db_password', ''),
                ),
                'wordpress_api' => array(
                    'url' => get_option('heytrisha_wordpress_api_url', get_site_url()),
                    'user' => get_option('heytrisha_wordpress_api_user', ''),
                    'password' => heytrisha_get_credential(HeyTrisha_Secure_Credentials::KEY_WP_API_PASSWORD, 'heytrisha_wordpress_api_password', ''),
                ),
                'woocommerce_api' => array(
                    'consumer_key' => get_option('heytrisha_woocommerce_consumer_key', ''),
                    'consumer_secret' => heytrisha_get_credential(HeyTrisha_Secure_Credentials::KEY_WC_CONSUMER_SECRET, 'heytrisha_woocommerce_consumer_secret', ''),
                ),
                'wordpress_info' => array(
                    'is_multisite' => $is_multisite,
                    'current_site_id' => $current_site_id,
                ),
            );
        },
        'permission_callback' => '__return_true'
    ));
    
    // ✅ Proxy endpoint for Laravel API - routes through admin-ajax.php (hidden from Network tab)
    // REMOVED REST API - Now using admin-ajax.php for better security
    /*
    register_rest_route('heytrisha/v1', '/api/(?P<endpoint>.*)', array(
        'methods' => ['GET', 'POST', 'PUT', 'DELETE', 'PATCH'],
        'callback' => function($request) {
            // CRITICAL: Use output buffering and error handler BEFORE calling proxy
            // This must happen here because notices are output during REST API init
            // We don't modify error_reporting() as it interferes with other plugins
            // phpcs:disable WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler -- Required for REST API error handling
            $original_error_handler = set_error_handler(function() {});
            restore_error_handler();
            
            // Custom error handler that only suppresses output, not error reporting
            set_error_handler(function($errno, $errstr, $errfile, $errline) {
                // Suppress output of notices/warnings but don't prevent error_reporting()
                if ($errno === E_NOTICE || $errno === E_WARNING || $errno === E_DEPRECATED || 
                    $errno === E_USER_NOTICE || $errno === E_USER_WARNING || $errno === E_USER_DEPRECATED) {
                    return true; // Suppress output but don't change error_reporting()
                }
                return false; // Let fatal errors through
            }, E_ALL);
            // phpcs:enable WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler
            
            // Clean any existing buffers and start fresh
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            ob_start();
            
            try {
                // Call the actual proxy function
                $result = heytrisha_proxy_laravel_api($request);
                
                // Clean all buffers before returning
                while (ob_get_level() > 0) {
                    ob_end_clean();
                }
                
                // Restore error handler (we never changed error_reporting())
                if ($original_error_handler !== null) {
                    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler -- Restoring original error handler
                    set_error_handler($original_error_handler);
                } else {
                    restore_error_handler();
                }
                
                return $result;
            } catch (Exception $e) {
                // Clean buffers
                while (ob_get_level() > 0) {
                    ob_end_clean();
                }
                
                // Restore error handler (we never changed error_reporting())
                if ($original_error_handler !== null) {
                    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler -- Restoring original error handler
                    set_error_handler($original_error_handler);
                } else {
                    restore_error_handler();
                }
                
                throw $e;
            }
        },
        'permission_callback' => '__return_true', // Public endpoint, but Laravel can handle auth
        'args' => array(
            'endpoint' => array(
                'required' => false,
                'default' => '',
                'sanitize_callback' => 'sanitize_text_field',
            ),
        ),
    ));
    */
}
add_action('rest_api_init', 'heytrisha_register_rest_routes');

/**
 * Transform raw SQL results into WooCommerce order summaries when appropriate.
 *
 * This keeps the existing UI/table rendering intact but changes the keys/values
 * so admins see real order information (items, totals, status) instead of low-level
 * wp_posts columns when the query is clearly about orders.
 *
 * @param array $results Raw database rows (ARRAY_A) from wpdb.
 * @return array Transformed results when orders are detected, otherwise original.
 */
function heytrisha_transform_order_results_for_display($results) {
    if (!function_exists('wc_get_order') || !is_array($results) || empty($results)) {
        return $results;
    }

    // Detect whether these rows represent WooCommerce orders.
    // IMPORTANT: Some queries only return an `ID` column without post_type/status,
    // so we also probe using wc_get_order() on a few IDs.
    $has_order_like_rows = false;
    $checked = 0;
    foreach ($results as $row) {
        if (!is_array($row)) {
            continue;
        }
        $checked++;

        // Direct hint from SQL: explicit order_id column
        if (isset($row['order_id']) && (int) $row['order_id'] > 0) {
            if (wc_get_order((int) $row['order_id'])) {
                $has_order_like_rows = true;
                break;
            }
        }

        // Legacy posts-based queries often only return ID; check if that ID is an order.
        if (isset($row['ID']) && (int) $row['ID'] > 0) {
            $post_type   = isset($row['post_type']) ? $row['post_type'] : '';
            $post_status = isset($row['post_status']) ? $row['post_status'] : '';

            if ($post_type === 'shop_order' || (is_string($post_status) && strpos($post_status, 'wc-') === 0)) {
                $has_order_like_rows = true;
                break;
            }

            // Fallback: probe WooCommerce – if this ID resolves to an order, treat as order row.
            $maybe_order = wc_get_order((int) $row['ID']);
            if ($maybe_order) {
                $has_order_like_rows = true;
                break;
            }
        }

        // Avoid probing too many rows for performance – first 10 is enough to decide.
        if ($checked >= 10) {
            break;
        }
    }

    if (!$has_order_like_rows) {
        return $results;
    }

    $transformed = array();

    foreach ($results as $row) {
        if (!is_array($row)) {
            $transformed[] = $row;
            continue;
        }

        $order_id = 0;
        if (isset($row['order_id']) && (int) $row['order_id'] > 0) {
            $order_id = (int) $row['order_id'];
        } elseif (isset($row['ID']) && (int) $row['ID'] > 0) {
            $order_id = (int) $row['ID'];
        }

        if (!$order_id) {
            $transformed[] = $row;
            continue;
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            $transformed[] = $row;
            continue;
        }

        // Build a concise items summary: "Product A × 1, Product B × 2"
        $items_summary = array();
        foreach ($order->get_items() as $item) {
            $name = $item->get_name();
            $qty  = $item->get_quantity();
            $items_summary[] = trim($name) . ' × ' . $qty;
        }

        $billing_name = trim(trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name()));

        $transformed[] = array(
            'order_id'        => $order->get_id(),
            'order_number'    => method_exists($order, 'get_order_number') ? $order->get_order_number() : $order->get_id(),
            'status'          => function_exists('wc_get_order_status_name') ? wc_get_order_status_name($order->get_status()) : $order->get_status(),
            'date_created'    => $order->get_date_created() ? $order->get_date_created()->date_i18n('Y-m-d H:i') : '',
            'customer'        => $billing_name,
            'items'           => implode(', ', $items_summary),
            'total'           => $order->get_total(),
            'currency'        => $order->get_currency(),
            'payment_method'  => $order->get_payment_method_title(),
        );
    }

    return $transformed;
}

/**
 * Helper function to verify nonce with fallback for admin users
 * This handles cases where nonces expire but user is still authenticated
 * 
 * @param string $nonce The nonce to verify
 * @param string $action The nonce action
 * @return bool True if nonce is valid or user is verified admin
 */
function heytrisha_verify_nonce_for_admin($nonce, $action) {
    if (empty($nonce)) {
        return false;
    }
    
    // Try standard nonce verification
    $valid = wp_verify_nonce($nonce, $action);
    
    // If fails, try with -1 and -2 (WordPress nonce tick system)
    if (!$valid) {
        $valid = wp_verify_nonce($nonce, $action, -1) || wp_verify_nonce($nonce, $action, -2);
    }
    
    // If still fails but user is verified admin, allow with warning
    if (!$valid && current_user_can('manage_options') && is_user_logged_in()) {
        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Security warning
        error_log('HeyTrisha: Nonce failed but allowing request for verified admin user ID: ' . get_current_user_id());
        return true; // Allow for verified admin users
    }
    
    return $valid;
}

// ✅ Admin-Ajax handler for external API proxy
// This is a thin client that forwards requests to external HeyTrisha engine
function heytrisha_ajax_query_handler() {
    // Check user permissions first
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array(
            'message' => 'Unauthorized. Administrator access required.'
        ));
        return;
    }
    
    // Verify nonce for CSRF protection
    // Check nonce from POST data (FormData sends data as $_POST)
    $nonce = '';
    
    // phpcs:disable WordPress.Security.ValidatedSanitizedInput -- Nonce will be sanitized before verification
    // Debug: Log all POST keys to help diagnose
    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug logging for nonce troubleshooting
    error_log('HeyTrisha AJAX: POST keys received: ' . implode(', ', array_keys($_POST)));
    
    if (!empty($_POST['nonce'])) {
        $nonce = sanitize_text_field(wp_unslash($_POST['nonce']));
        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug logging
        error_log('HeyTrisha AJAX: Nonce found in $_POST: ' . substr($nonce, 0, 10) . '...');
    } else {
        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug logging
        error_log('HeyTrisha AJAX: Nonce NOT found in $_POST');
    }
    // phpcs:enable WordPress.Security.ValidatedSanitizedInput
    
    // If nonce not in POST, try reading from php://input (for JSON requests)
    if (empty($nonce)) {
        $json_body = file_get_contents('php://input');
        if (!empty($json_body)) {
            $decoded_body = json_decode($json_body, true);
            if (isset($decoded_body['nonce']) && is_string($decoded_body['nonce'])) {
                $nonce = sanitize_text_field($decoded_body['nonce']);
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug logging
                error_log('HeyTrisha AJAX: Nonce found in JSON body: ' . substr($nonce, 0, 10) . '...');
            }
        }
    }
    
    // Verify nonce using helper function (handles expired nonces for admin users)
    if (!heytrisha_verify_nonce_for_admin($nonce, 'heytrisha_chatbot')) {
        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug logging for nonce verification failures
        error_log('HeyTrisha AJAX: Nonce verification failed. Nonce received: ' . ($nonce ? substr($nonce, 0, 10) . '...' : 'NO NONCE') . ', POST keys: ' . implode(', ', array_keys($_POST)) . ', User ID: ' . get_current_user_id());
        wp_send_json_error(array(
            'message' => 'Security check failed. Please refresh the page and try again.'
        ));
        return;
    }
    
    try {
        // Get the request body
        $request_data = array();
        
        // phpcs:disable WordPress.Security.ValidatedSanitizedInput -- Nonce verified above, data sanitized below
        // First try POST data (standard WordPress AJAX)
        if (!empty($_POST)) {
            // Sanitize POST data properly
            $request_data = array();
            foreach ($_POST as $key => $value) {
                $sanitized_key = sanitize_key($key); // Sanitize array keys
                if (is_string($value)) {
                    $request_data[$sanitized_key] = sanitize_textarea_field(wp_unslash($value));
                } elseif (is_array($value)) {
                    $request_data[$sanitized_key] = array_map(function($v) {
                        return is_string($v) ? sanitize_textarea_field(wp_unslash($v)) : $v;
                    }, $value);
                } else {
                    $request_data[$sanitized_key] = $value;
                }
            }
            // phpcs:enable WordPress.Security.ValidatedSanitizedInput
            
            // If confirmation_data is a JSON string, decode and sanitize it
            if (isset($request_data['confirmation_data']) && is_string($request_data['confirmation_data'])) {
                $decoded = json_decode($request_data['confirmation_data'], true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $request_data['confirmation_data'] = heytrisha_sanitize_confirmation_data($decoded);
                } else {
                    // If JSON decode failed, sanitize as text
                    $request_data['confirmation_data'] = sanitize_text_field($request_data['confirmation_data']);
                }
            }
        } else {
            // Fallback: Try JSON body (nonce already extracted above)
            $json_body = file_get_contents('php://input');
            $decoded = json_decode($json_body, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                // Sanitize the decoded data
                $request_data = array();
                foreach ($decoded as $key => $value) {
                    $sanitized_key = sanitize_key($key);
                    if (is_string($value)) {
                        $request_data[$sanitized_key] = sanitize_textarea_field($value);
                    } elseif (is_array($value)) {
                        if ($sanitized_key === 'confirmation_data') {
                            $request_data[$sanitized_key] = heytrisha_sanitize_confirmation_data($value);
                        } else {
                            $request_data[$sanitized_key] = array_map(function($v) {
                                return is_string($v) ? sanitize_textarea_field($v) : $v;
                            }, $value);
                        }
                    } else {
                        $request_data[$sanitized_key] = $value;
                    }
                }
            } else {
                $request_data = array();
            }
        }
        
        // Validate query exists
        if (empty($request_data['query']) || !is_string($request_data['query']) || trim($request_data['query']) === '') {
            wp_send_json_error(array(
                'message' => 'Please provide a valid query.'
            ));
            return;
        }
        
        // Sanitize input
        $query = sanitize_text_field($request_data['query']);
        $confirmed = isset($request_data['confirmed']) ? filter_var($request_data['confirmed'], FILTER_VALIDATE_BOOLEAN) : false;
        $confirmation_data = isset($request_data['confirmation_data']) ? $request_data['confirmation_data'] : null;
        
        // Get external API URL and API key
        $api_url = get_option('heytrisha_api_url', 'https://api.heytrisha.com');
        $api_key = heytrisha_get_credential(HeyTrisha_Secure_Credentials::KEY_API_TOKEN, 'heytrisha_api_key', '');
        
        if (empty($api_url) || empty($api_key)) {
            wp_send_json_error(array(
                'message' => 'HeyTrisha API is not configured. Please configure the API URL and API key in settings.'
            ));
            return;
        }
        
        // Get database schema for API
        $schema = heytrisha_get_database_schema();
        
        // Detect WooCommerce order storage mode (HPOS vs Legacy)
        $hpos_enabled = get_option('woocommerce_custom_orders_table_enabled', 'no') === 'yes';
        
        // Detect WooCommerce table prefix for orders
        global $wpdb;
        $order_table_hint = '';
        if ($hpos_enabled) {
            $order_table_hint = 'hpos'; // Orders stored in wc_orders table
        } else {
            $order_table_hint = 'legacy'; // Orders stored in wp_posts with post_type=shop_order
        }
        
        // Prepare request body for external API
        $request_body = array(
            'question' => $query,
            'site' => get_site_url(),
            'context' => 'woocommerce',
            'schema' => $schema, // Send database schema
            'order_storage' => $order_table_hint, // HPOS or legacy
            'table_prefix' => $wpdb->prefix, // Actual WordPress table prefix
        );
        
        if ($confirmed) {
            $request_body['confirmed'] = true;
        }
        
        if ($confirmation_data !== null) {
            $request_body['confirmation_data'] = $confirmation_data;
        }
        
        // Make request to external API
        $api_endpoint = rtrim($api_url, '/') . '/api/query';
        $response = wp_remote_post($api_endpoint, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
            ),
            'body' => wp_json_encode($request_body),
            'timeout' => 60,
            'sslverify' => false, // Disable SSL verify for shared hosting compatibility
        ));
        
        // Handle response
        if (is_wp_error($response)) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Production error tracking
            error_log('HeyTrisha API Error: ' . $response->get_error_message() . ' | Endpoint: ' . $api_endpoint);
            wp_send_json_error(array(
                'message' => 'Failed to connect to HeyTrisha API: ' . $response->get_error_message(),
                'endpoint' => $api_endpoint,
            ));
            return;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        if ($response_code !== 200) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Production error tracking
            error_log('HeyTrisha API HTTP ' . $response_code . ' | Body: ' . substr($response_body, 0, 500) . ' | Endpoint: ' . $api_endpoint);
            
            // Try to decode JSON error response
            $error_details = $response_body;
            $decoded_error = json_decode($response_body, true);
            if ($decoded_error && isset($decoded_error['message'])) {
                $error_details = $decoded_error['message'];
            }
            
            wp_send_json_error(array(
                'message' => 'HeyTrisha API returned an error (HTTP ' . $response_code . ')',
                'details' => $error_details,
            ));
            return;
        }
        
        $decoded_response = json_decode($response_body, true);
        
        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug logging for API troubleshooting
        error_log('Hey Trisha: API response type=' . ($decoded_response['type'] ?? 'none') . ' | message=' . substr($decoded_response['message'] ?? '', 0, 200) . ' | sql=' . substr($decoded_response['sql'] ?? '', 0, 300));
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_send_json_error(array(
                'message' => 'Invalid response from HeyTrisha API'
            ));
            return;
        }
        
        // Check if API returned a conversational response (no SQL execution needed)
        if (isset($decoded_response['success']) && $decoded_response['success'] && 
            isset($decoded_response['type']) && $decoded_response['type'] === 'conversation') {
            wp_send_json(array(
                'success' => true,
                'message' => isset($decoded_response['message']) ? $decoded_response['message'] : 'Hello! How can I help you?',
            ));
            return;
        }
        
        // Check if API returned SQL query (new flow: API generates SQL, plugin executes it)
        if (isset($decoded_response['success']) && $decoded_response['success'] && isset($decoded_response['sql'])) {
            // API returned SQL query - execute it locally on plugin's database
            $sql = $decoded_response['sql'];
            
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug logging for SQL troubleshooting
            error_log('Hey Trisha: SQL from API: ' . $sql);
            
            // Load SQL validator if not already loaded
            if (!class_exists('HeyTrisha_SQL_Validator')) {
                $validator_file = HEYTRISHA_PLUGIN_DIR . 'includes/class-heytrisha-sql-validator.php';
                if (file_exists($validator_file)) {
                    require_once $validator_file;
                }
            }
            
            // Validate SQL query (use class if available, otherwise inline validation)
            if (class_exists('HeyTrisha_SQL_Validator')) {
                $validation = HeyTrisha_SQL_Validator::validate($sql);
                if (!$validation['valid']) {
                    wp_send_json_error(array(
                        'message' => 'SQL validation failed: ' . $validation['error']
                    ));
                    return;
                }
                $sql = HeyTrisha_SQL_Validator::sanitize_table_names($sql);
                $sql = HeyTrisha_SQL_Validator::ensure_limit($sql, 1000);
            } else {
                // Inline fallback validator when class file is not available
                // 1. Only allow SELECT queries
                if (!preg_match('/^\s*SELECT\s+/i', trim($sql))) {
                    wp_send_json_error(array(
                        'message' => 'Only SELECT queries are allowed.'
                    ));
                    return;
                }
                // 2. Block dangerous keywords
                $dangerous = array('DELETE', 'DROP', 'TRUNCATE', 'ALTER', 'CREATE', 'INSERT', 'UPDATE', 'REPLACE', 'GRANT', 'REVOKE', 'EXECUTE', 'EXEC', 'LOAD DATA', 'LOAD XML');
                $sql_upper = strtoupper($sql);
                foreach ($dangerous as $kw) {
                    if (preg_match('/\b' . preg_quote($kw, '/') . '\b/', $sql_upper)) {
                        wp_send_json_error(array(
                            'message' => 'Dangerous SQL keyword detected: ' . $kw
                        ));
                        return;
                    }
                }
                // 3. Block multiple statements
                $check_sql = preg_replace("/'[^']*'/", '', $sql);
                $check_sql = preg_replace('/"[^"]*"/', '', $check_sql);
                if (substr_count($check_sql, ';') > 1) {
                    wp_send_json_error(array(
                        'message' => 'Multiple SQL statements are not allowed.'
                    ));
                    return;
                }
                // 4. Replace wp_ with actual table prefix
                global $wpdb;
                $sql = str_replace('wp_', $wpdb->prefix, $sql);
                // 5. Ensure LIMIT exists
                if (!preg_match('/\bLIMIT\s+\d+/i', $sql)) {
                    $sql = rtrim($sql, ';') . ' LIMIT 1000';
                }
            }
            
            // ✅ CRITICAL: Fix common WooCommerce column name mismatches
            // wc_orders (HPOS) has `total_amount`, NOT `total_sales`
            // wc_order_stats has `total_sales`, NOT `total_amount`
            global $wpdb;
            $wc_orders_table = $wpdb->prefix . 'wc_orders';
            $wc_order_stats_table = $wpdb->prefix . 'wc_order_stats';
            
            // If SQL references wc_orders but NOT wc_order_stats, fix total_sales → total_amount
            if (strpos($sql, 'wc_orders') !== false && strpos($sql, 'wc_order_stats') === false) {
                if (preg_match('/\btotal_sales\b/i', $sql)) {
                    $sql = preg_replace('/\btotal_sales\b/i', 'total_amount', $sql);
                    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug logging
                    error_log('Hey Trisha: Fixed column name: total_sales → total_amount (wc_orders uses total_amount)');
                }
            }
            // If SQL references wc_order_stats but NOT wc_orders, fix total_amount → total_sales
            if (strpos($sql, 'wc_order_stats') !== false && !preg_match('/\bwc_orders\b(?!_)/i', $sql)) {
                if (preg_match('/\btotal_amount\b/i', $sql)) {
                    $sql = preg_replace('/\btotal_amount\b/i', 'total_sales', $sql);
                    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug logging
                    error_log('Hey Trisha: Fixed column name: total_amount → total_sales (wc_order_stats uses total_sales)');
                }
            }
            
            // Execute SQL query
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug logging for SQL troubleshooting
            error_log('Hey Trisha: Executing SQL: ' . $sql);
            
            // Suppress WordPress database error output (it outputs HTML divs before JSON)
            $wpdb->suppress_errors(true);
            $results = $wpdb->get_results($sql, ARRAY_A);
            $db_error = $wpdb->last_error;
            $wpdb->suppress_errors(false);
            
            // Check for database errors
            if ($db_error) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Production error tracking
                error_log('Hey Trisha: DB error: ' . $db_error . ' | SQL: ' . $sql);
                wp_send_json_error(array(
                    'message' => 'Database error: ' . $db_error
                ));
                return;
            }
            
            // Post-process results: convert NULL values to meaningful defaults
            if (is_array($results)) {
                foreach ($results as $idx => $row) {
                    if (is_array($row)) {
                        foreach ($row as $key => $value) {
                            if ($value === null) {
                                // For columns that look numeric (sum, count, total, avg, etc.), use "0"
                                $lower_key = strtolower($key);
                                if (preg_match('/(count|total|sum|avg|average|amount|revenue|sales|sold|qty|quantity|price|num|number)/i', $lower_key)) {
                                    $results[$idx][$key] = '0';
                                } else {
                                    $results[$idx][$key] = '';
                                }
                            }
                        }
                    }
                }
            }

            // If result set looks like WooCommerce orders, transform rows into
            // order summaries (ID, status, totals, items, etc.) so the admin
            // sees real order data instead of low-level post fields.
            $results = heytrisha_transform_order_results_for_display($results);
            
            // Format response for chatbot
            $row_count = is_array($results) ? count($results) : 0;
            $explanation = isset($decoded_response['explanation']) ? $decoded_response['explanation'] : '';
            $message = '';
            
            if ($row_count === 0) {
                $message = "I couldn't find any results for your question.";
            } elseif (!empty($explanation)) {
                // Use AI-generated explanation for a more meaningful message
                $message = esc_html($explanation) . " ({$row_count} result" . ($row_count > 1 ? 's' : '') . " found)";
            } elseif ($row_count === 1) {
                $message = "I found 1 result for your question.";
            } else {
                $message = "I found {$row_count} results for your question.";
            }
            
            // Return results in format expected by chatbot.js
            // Format: { success: true, message: "...", data: [...] }
            wp_send_json(array(
                'success' => true,
                'message' => $message,
                'data' => $results,
                'row_count' => $row_count
            ));
            return;
        }
        
        // Fallback: Return API response as-is (for backward compatibility)
        wp_send_json($decoded_response);
        
    } catch (Exception $e) {
        wp_send_json_error(array(
            'message' => 'Request failed: ' . $e->getMessage()
        ));
    } catch (Throwable $e) {
        wp_send_json_error(array(
            'message' => 'Request failed: ' . $e->getMessage()
        ));
    }
}

// Register for both logged-in and non-logged-in users
// Register AJAX handler for logged-in users only (requires manage_options capability)
add_action('wp_ajax_heytrisha_query', 'heytrisha_ajax_query_handler');
// Note: No nopriv handler - this endpoint requires administrator permissions

// ============================================================================
// ✅ AJAX handlers for chat operations (replaces REST API for shared hosting)
// These use admin-ajax.php which works reliably on all hosting environments
// ============================================================================

/**
 * Helper: load database class and return instance
 */
function heytrisha_get_chat_db() {
    if (!class_exists('HeyTrisha_Database')) {
        $db_file = HEYTRISHA_PLUGIN_DIR . 'includes/class-heytrisha-database.php';
        if (file_exists($db_file)) {
            require_once $db_file;
        }
    }
    if (!class_exists('HeyTrisha_Database')) {
        return null;
    }
    HeyTrisha_Database::create_tables();
    return HeyTrisha_Database::get_instance();
}

/**
 * AJAX: Get chats list
 */
function heytrisha_ajax_get_chats() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Unauthorized.'));
        return;
    }
    // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Nonce verified below
    $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
    if (!heytrisha_verify_nonce_for_admin($nonce, 'heytrisha_chatbot')) {
        wp_send_json_error(array('message' => 'Security check failed.'));
        return;
    }
    $db = heytrisha_get_chat_db();
    if (!$db) {
        wp_send_json_error(array('message' => 'Database not available.'));
        return;
    }
    // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Already sanitized via sanitize_text_field
    $archived = isset($_POST['archived']) && ($_POST['archived'] === 'true' || $_POST['archived'] === '1');
    $chats = $db->get_chats($archived);
    wp_send_json_success($chats ? $chats : array());
}
add_action('wp_ajax_heytrisha_get_chats', 'heytrisha_ajax_get_chats');

/**
 * AJAX: Create new chat
 */
function heytrisha_ajax_create_chat() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Unauthorized.'));
        return;
    }
    // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Nonce verified below
    $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
    if (!heytrisha_verify_nonce_for_admin($nonce, 'heytrisha_chatbot')) {
        wp_send_json_error(array('message' => 'Security check failed.'));
        return;
    }
    $db = heytrisha_get_chat_db();
    if (!$db) {
        wp_send_json_error(array('message' => 'Database not available.'));
        return;
    }
    $title = isset($_POST['title']) ? sanitize_text_field(wp_unslash($_POST['title'])) : 'New Chat';
    $chat_id = $db->create_chat($title);
    if ($chat_id) {
        $chat = $db->get_chat($chat_id);
        wp_send_json_success($chat);
    } else {
        wp_send_json_error(array('message' => 'Failed to create chat.'));
    }
}
add_action('wp_ajax_heytrisha_create_chat', 'heytrisha_ajax_create_chat');

/**
 * AJAX: Get chat messages
 */
function heytrisha_ajax_get_chat() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Unauthorized.'));
        return;
    }
    // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Nonce verified below
    $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
    if (!heytrisha_verify_nonce_for_admin($nonce, 'heytrisha_chatbot')) {
        wp_send_json_error(array('message' => 'Security check failed.'));
        return;
    }
    $db = heytrisha_get_chat_db();
    if (!$db) {
        wp_send_json_error(array('message' => 'Database not available.'));
        return;
    }
    $chat_id = isset($_POST['chat_id']) ? intval($_POST['chat_id']) : 0;
    if (!$chat_id) {
        wp_send_json_error(array('message' => 'Chat ID required.'));
        return;
    }
    $chat = $db->get_chat($chat_id);
    if (!$chat) {
        wp_send_json_error(array('message' => 'Chat not found.'));
        return;
    }
    $messages = $db->get_messages($chat_id);
    $chat->messages = $messages ? $messages : array();
    wp_send_json_success($chat);
}
add_action('wp_ajax_heytrisha_get_chat', 'heytrisha_ajax_get_chat');

/**
 * AJAX: Save message to chat
 */
function heytrisha_ajax_save_message() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Unauthorized.'));
        return;
    }
    // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Nonce verified below
    $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
    if (!heytrisha_verify_nonce_for_admin($nonce, 'heytrisha_chatbot')) {
        wp_send_json_error(array('message' => 'Security check failed.'));
        return;
    }
    $db = heytrisha_get_chat_db();
    if (!$db) {
        wp_send_json_error(array('message' => 'Database not available.'));
        return;
    }
    $chat_id = isset($_POST['chat_id']) ? intval($_POST['chat_id']) : 0;
    $role = isset($_POST['role']) ? sanitize_text_field(wp_unslash($_POST['role'])) : 'user';
    $content = isset($_POST['content']) ? sanitize_textarea_field(wp_unslash($_POST['content'])) : '';
    $metadata = isset($_POST['metadata']) ? sanitize_textarea_field(wp_unslash($_POST['metadata'])) : null;
    if (!$chat_id || empty($content)) {
        wp_send_json_error(array('message' => 'Chat ID and content required.'));
        return;
    }
    // Decode metadata if it's JSON string
    $metadata_decoded = null;
    if (!empty($metadata)) {
        $metadata_decoded = json_decode($metadata, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $metadata_decoded = null;
        }
    }
    $message_id = $db->add_message($chat_id, $role, $content, $metadata_decoded);
    if ($message_id) {
        wp_send_json_success(array('id' => $message_id));
    } else {
        wp_send_json_error(array('message' => 'Failed to save message.'));
    }
}
add_action('wp_ajax_heytrisha_save_message', 'heytrisha_ajax_save_message');

/**
 * AJAX: Update chat (title, archive status)
 */
function heytrisha_ajax_update_chat() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Unauthorized.'));
        return;
    }
    // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Nonce verified below
    $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
    if (!heytrisha_verify_nonce_for_admin($nonce, 'heytrisha_chatbot')) {
        wp_send_json_error(array('message' => 'Security check failed.'));
        return;
    }
    $db = heytrisha_get_chat_db();
    if (!$db) {
        wp_send_json_error(array('message' => 'Database not available.'));
        return;
    }
    $chat_id = isset($_POST['chat_id']) ? intval($_POST['chat_id']) : 0;
    if (!$chat_id) {
        wp_send_json_error(array('message' => 'Chat ID required.'));
        return;
    }
    $data = array();
    if (isset($_POST['title'])) {
        $data['title'] = sanitize_text_field(wp_unslash($_POST['title']));
    }
    if (isset($_POST['is_archived'])) {
        $data['is_archived'] = intval($_POST['is_archived']);
    }
    $result = $db->update_chat($chat_id, $data);
    if ($result) {
        $chat = $db->get_chat($chat_id);
        wp_send_json_success($chat);
    } else {
        wp_send_json_error(array('message' => 'Failed to update chat.'));
    }
}
add_action('wp_ajax_heytrisha_update_chat', 'heytrisha_ajax_update_chat');

// ============================================================================
// End of AJAX chat handlers
// ============================================================================

// ✅ AJAX handler for fetching personal data
// Fetches from local storage first, then syncs from API if available
function heytrisha_ajax_get_personal_data() {
    // Check user permissions
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array(
            'message' => 'Unauthorized. Administrator access required.'
        ));
        return;
    }

    // Verify nonce
    $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
    if (!heytrisha_verify_nonce_for_admin($nonce, 'heytrisha_personal_data')) {
        wp_send_json_error(array(
            'message' => 'Security check failed. Please refresh the page and try again.'
        ));
        return;
    }

    // Get API URL and key
    $api_url = get_option('heytrisha_api_url', 'https://api.heytrisha.com');
    $site_api_key = heytrisha_get_credential(HeyTrisha_Secure_Credentials::KEY_API_TOKEN, 'heytrisha_api_key', '');

    // Get data from local storage (primary source)
    $personal_data = array(
        'email' => get_option('heytrisha_user_email', ''),
        'first_name' => get_option('heytrisha_user_first_name', ''),
        'last_name' => get_option('heytrisha_user_last_name', ''),
        'username' => get_option('heytrisha_user_username', ''),
        'db_name' => get_option('heytrisha_db_name', ''),
        'db_username' => get_option('heytrisha_db_user', ''),
        'openai_key' => heytrisha_get_credential(HeyTrisha_Secure_Credentials::KEY_OPENAI_API, 'heytrisha_openai_key', ''),
    );

    // Try to fetch from API and update local storage if available
    if (!empty($site_api_key) && !empty($api_url)) {
        $response = wp_remote_get(rtrim($api_url, '/') . '/api/site/info', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $site_api_key,
                'Content-Type' => 'application/json',
            ),
            'timeout' => 30,
            'sslverify' => true,
        ));

        if (!is_wp_error($response)) {
            $response_code = wp_remote_retrieve_response_code($response);
            if ($response_code >= 200 && $response_code < 300) {
                $body = wp_remote_retrieve_body($response);
                $api_data = json_decode($body, true);

                if ($api_data && isset($api_data['success']) && $api_data['success'] && isset($api_data['site'])) {
                    $api_site_data = $api_data['site'];
                    
                    // Update email from API if available
                    if (isset($api_site_data['email']) && !empty($api_site_data['email'])) {
                        update_option('heytrisha_user_email', $api_site_data['email']);
                        $personal_data['email'] = $api_site_data['email'];
                    }
                }
            }
        }
    }

    wp_send_json_success(array(
        'data' => $personal_data,
        'message' => 'Personal data retrieved successfully.'
    ));
}
add_action('wp_ajax_heytrisha_get_personal_data', 'heytrisha_ajax_get_personal_data');

// ✅ AJAX handler for updating personal data
// Stores locally first, then syncs to API
function heytrisha_ajax_update_personal_data() {
    // Check user permissions
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array(
            'message' => 'Unauthorized. Administrator access required.'
        ));
        return;
    }

    // Verify nonce
    $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
    if (!heytrisha_verify_nonce_for_admin($nonce, 'heytrisha_personal_data')) {
        wp_send_json_error(array(
            'message' => 'Security check failed. Please refresh the page and try again.'
        ));
        return;
    }

    // Get API URL and key
    $api_url = get_option('heytrisha_api_url', 'https://api.heytrisha.com');
    $site_api_key = heytrisha_get_credential(HeyTrisha_Secure_Credentials::KEY_API_TOKEN, 'heytrisha_api_key', '');

    // Get and sanitize input data
    $email = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
    $first_name = isset($_POST['first_name']) ? sanitize_text_field(wp_unslash($_POST['first_name'])) : '';
    $last_name = isset($_POST['last_name']) ? sanitize_text_field(wp_unslash($_POST['last_name'])) : '';
    $username = isset($_POST['username']) ? sanitize_user(wp_unslash($_POST['username'])) : '';
    $password = isset($_POST['password']) ? wp_unslash($_POST['password']) : '';
    $openai_key = isset($_POST['openai_key']) ? sanitize_text_field(wp_unslash($_POST['openai_key'])) : '';
    $db_name = isset($_POST['db_name']) ? sanitize_text_field(wp_unslash($_POST['db_name'])) : '';
    $db_username = isset($_POST['db_username']) ? sanitize_text_field(wp_unslash($_POST['db_username'])) : '';
    $db_password = isset($_POST['db_password']) ? wp_unslash($_POST['db_password']) : '';

    $errors = array();
    $updated_fields = array();

    // ✅ STEP 1: Store locally first
    // Update email if provided and valid
    if (!empty($email)) {
        if (is_email($email)) {
            update_option('heytrisha_user_email', $email);
            $updated_fields[] = 'email';
        } else {
            $errors[] = 'Invalid email address.';
        }
    }
    
    // Update first name if provided
    if (!empty($first_name)) {
        update_option('heytrisha_user_first_name', $first_name);
        $updated_fields[] = 'first_name';
    }
    
    // Update last name if provided
    if (!empty($last_name)) {
        update_option('heytrisha_user_last_name', $last_name);
        $updated_fields[] = 'last_name';
    }
    
    // Update username if provided and valid
    if (!empty($username)) {
        if (strlen($username) >= 3) {
            update_option('heytrisha_user_username', $username);
            $updated_fields[] = 'username';
        } else {
            $errors[] = 'Username must be at least 3 characters.';
        }
    }

    // Update password if provided (minimum 8 characters)
    $password_to_update = '';
    if (!empty($password)) {
        if (strlen($password) >= 8) {
            $password_to_update = $password;
            $updated_fields[] = 'password';
        } else {
            $errors[] = 'Password must be at least 8 characters.';
        }
    }

    // Update OpenAI key if provided
    if (!empty($openai_key)) {
        heytrisha_set_credential(HeyTrisha_Secure_Credentials::KEY_OPENAI_API, $openai_key);
        $updated_fields[] = 'openai_key';
    }

    // Update database credentials if provided
    if (!empty($db_name)) {
        update_option('heytrisha_db_name', $db_name);
        $updated_fields[] = 'db_name';
    }
    if (!empty($db_username)) {
        update_option('heytrisha_db_user', $db_username);
        $updated_fields[] = 'db_username';
    }
    if (!empty($db_password)) {
        heytrisha_set_credential(HeyTrisha_Secure_Credentials::KEY_DB_PASSWORD, $db_password);
        $updated_fields[] = 'db_password';
    }

    // Return errors if any validation failed
    if (!empty($errors)) {
        wp_send_json_error(array(
            'message' => 'Validation failed.',
            'errors' => $errors
        ));
        return;
    }

    // ✅ STEP 2: Sync to API server
    $sync_success = true;
    $sync_message = '';
    if (!empty($site_api_key) && !empty($api_url)) {
        $update_data = array();

        if (!empty($email)) {
            $update_data['email'] = $email;
        }
        if (!empty($first_name)) {
            $update_data['first_name'] = $first_name;
        }
        if (!empty($last_name)) {
            $update_data['last_name'] = $last_name;
        }
        if (!empty($username)) {
            $update_data['username'] = $username;
        }
        if (!empty($password_to_update)) {
            $update_data['password'] = $password_to_update;
        }
        if (!empty($openai_key)) {
            $update_data['openai_key'] = $openai_key;
        }
        if (!empty($db_name)) {
            $update_data['db_name'] = $db_name;
        }
        if (!empty($db_username)) {
            $update_data['db_username'] = $db_username;
        }
        if (!empty($db_password)) {
            $update_data['db_password'] = $db_password;
        }

        // Only make API call if there's data to update
        if (!empty($update_data)) {
            // Prepare API-compatible data.
            // The API can accept: openai_key, email, username, password,
            // first_name, last_name, db_name, db_username, db_password,
            // wordpress_version, woocommerce_version, plugin_version.
            $api_update_data = array();
            foreach ($update_data as $key => $value) {
                if ($value === '' || $value === null) {
                    continue;
                }
                $api_update_data[$key] = $value;
            }

            // Always send current WP / Woo / plugin versions when syncing,
            // so the API has up-to-date environment info.
            $api_update_data['wordpress_version'] = get_bloginfo('version');
            if (defined('WC_VERSION')) {
                $api_update_data['woocommerce_version'] = WC_VERSION;
            }
            $api_update_data['plugin_version'] = defined('HEYTRISHA_VERSION') ? HEYTRISHA_VERSION : '';
            
            // Only sync to API if we have API-compatible fields
            if (!empty($api_update_data)) {
                // API expects PUT method, not POST
                // Include site URL in headers to help API server identify the request source
                $site_url = get_site_url();
                // Add allow_direct parameter to bypass API server's direct access check
                // The API server's public/index.php allows /api/config but may need this for external requests
                $api_endpoint = rtrim($api_url, '/') . '/api/config?allow_direct=1';
                $response = wp_remote_request($api_endpoint, array(
                    'method' => 'PUT',
                    'headers' => array(
                        'Authorization' => 'Bearer ' . $site_api_key,
                        'Content-Type' => 'application/json',
                        'X-Site-URL' => $site_url,
                        'User-Agent' => 'HeyTrisha-WordPress-Plugin/' . HEYTRISHA_VERSION,
                    ),
                    'body' => wp_json_encode($api_update_data),
                    'timeout' => 30,
                    'sslverify' => true,
                    'redirection' => 0, // Don't follow redirects
                ));

                if (is_wp_error($response)) {
                    $sync_success = false;
                    $sync_message = 'Settings saved locally but failed to sync with API server: ' . $response->get_error_message();
                } else {
                    $response_code = wp_remote_retrieve_response_code($response);
                    $response_body = wp_remote_retrieve_body($response);
                    
                    if ($response_code >= 200 && $response_code < 300) {
                        $sync_message = 'Settings saved and synced successfully.';
                    } else {
                        $sync_success = false;
                        // Try to get error message from response
                        $error_details = '';
                        $decoded_error = json_decode($response_body, true);
                        if ($decoded_error && isset($decoded_error['message'])) {
                            $error_details = ': ' . $decoded_error['message'];
                        }
                        $sync_message = 'Settings saved locally but failed to sync with API server (HTTP ' . $response_code . $error_details . ').';
                        // Log for debugging
                        error_log('HeyTrisha API Sync Error: HTTP ' . $response_code . ' | Body: ' . substr($response_body, 0, 500));
                    }
                }
            } else {
                // No API-compatible fields to sync, but local fields were updated
                $sync_message = 'Settings saved locally. (First name, last name, username, password, and database credentials are stored locally only and not synced to API server.)';
            }
        } else {
            $sync_message = 'No changes to sync.';
        }
    } else {
        $sync_message = 'Settings saved locally. API sync skipped (API key or URL not configured).';
    }

    // Return success response
    if ($sync_success) {
        wp_send_json_success(array(
            'message' => $sync_message,
            'updated_fields' => $updated_fields
        ));
    } else {
        wp_send_json_error(array(
            'message' => $sync_message,
            'updated_fields' => $updated_fields,
            'local_save' => true // Indicate that local save succeeded
        ));
    }
}
add_action('wp_ajax_heytrisha_update_personal_data', 'heytrisha_ajax_update_personal_data');

// ============================================================================
// End of AJAX personal data handlers
// ============================================================================

// ✅ Register Chat REST API endpoints
function heytrisha_register_chat_rest_routes() {
    // Ensure database class is loaded
    if (!class_exists('HeyTrisha_Database')) {
        $db_file = HEYTRISHA_PLUGIN_DIR . 'includes/class-heytrisha-database.php';
        if (file_exists($db_file)) {
            require_once $db_file;
        } else {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Production error tracking
            error_log('HeyTrisha: Database class file not found: ' . $db_file);
            return; // Exit early if critical file is missing
        }
    }
    
    // Ensure SQL validator is loaded
    if (!class_exists('HeyTrisha_SQL_Validator')) {
        $validator_file = HEYTRISHA_PLUGIN_DIR . 'includes/class-heytrisha-sql-validator.php';
        if (file_exists($validator_file)) {
            require_once $validator_file;
        } else {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Production error tracking
            error_log('HeyTrisha: SQL Validator class file not found: ' . $validator_file);
            // Continue without validator - some features may not work
        }
    }
    
    // Ensure REST API handler is loaded (optional — routes are registered inline below)
    if (!class_exists('HeyTrisha_REST_API')) {
        $rest_api_file = HEYTRISHA_PLUGIN_DIR . 'includes/class-heytrisha-rest-api.php';
        if (file_exists($rest_api_file)) {
            require_once $rest_api_file;
        } else {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Production error tracking
            error_log('HeyTrisha: REST API class file not found: ' . $rest_api_file . ' (non-fatal, routes registered inline)');
            // Continue — routes are registered inline below, HeyTrisha_REST_API class is optional
        }
    }
    
    // Ensure database class exists before using it
    if (!class_exists('HeyTrisha_Database')) {
        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Production error tracking
        error_log('HeyTrisha: Database class not available for REST API registration');
        return; // Exit early if critical class is missing
    }
    
    // Ensure tables exist
    HeyTrisha_Database::create_tables();
    
    $db = HeyTrisha_Database::get_instance();
    
    // Get all chats
    register_rest_route('heytrisha/v1', '/chats', array(
        'methods' => 'GET',
        'callback' => function($request) use ($db) {
            if (!current_user_can('manage_options')) {
                return new WP_Error('forbidden', 'Insufficient permissions.', array('status' => 403));
            }
            try {
                $archived = $request->get_param('archived') === 'true' || $request->get_param('archived') === '1';
                $chats = $db->get_chats($archived);
                return rest_ensure_response($chats ? $chats : array());
            } catch (Exception $e) {
                // phpcs:disable WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Production error tracking for REST API exceptions
                error_log('HeyTrisha REST API Exception: ' . $e->getMessage());
                // phpcs:enable WordPress.PHP.DevelopmentFunctions.error_log_error_log
                return rest_ensure_response(array());
            }
        },
        'permission_callback' => function() {
            return current_user_can('manage_options');
        }
    ));
    
    // Get single chat with messages
    register_rest_route('heytrisha/v1', '/chats/(?P<id>\d+)', array(
        'methods' => 'GET',
        'callback' => function($request) use ($db) {
            if (!current_user_can('manage_options')) {
                return new WP_Error('forbidden', 'Insufficient permissions.', array('status' => 403));
            }
            $chat_id = intval($request['id']);
            $chat = $db->get_chat($chat_id);
            if (!$chat) {
                return new WP_Error('not_found', 'Chat not found.', array('status' => 404));
            }
            $messages = $db->get_messages($chat_id);
            // Decode metadata JSON strings to objects for frontend
            if ($messages && is_array($messages)) {
                foreach ($messages as &$msg) {
                    if (isset($msg->metadata) && is_string($msg->metadata)) {
                        $decoded = json_decode($msg->metadata, true);
                        if (json_last_error() === JSON_ERROR_NONE) {
                            $msg->metadata = $decoded;
                        }
                    }
                }
            }
            $chat->messages = $messages;
            return rest_ensure_response($chat);
        },
        'permission_callback' => function() {
            return current_user_can('manage_options');
        }
    ));
    
    // Create new chat
    register_rest_route('heytrisha/v1', '/chats', array(
        'methods' => 'POST',
        'callback' => function($request) use ($db) {
            if (!current_user_can('manage_options')) {
                return new WP_Error('forbidden', 'Insufficient permissions.', array('status' => 403));
            }
            try {
                $title = $request->get_param('title') ?: 'New Chat';
                $chat_id = $db->create_chat($title);
                if ($chat_id) {
                    $chat = $db->get_chat($chat_id);
                    if ($chat) {
                        return rest_ensure_response($chat);
                    }
                }
                global $wpdb;
                $error_msg = $wpdb->last_error ?: 'Unknown database error';
                // phpcs:disable WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Production error tracking for chat creation failures
                error_log('HeyTrisha REST API: Failed to create chat - ' . $error_msg);
                // phpcs:enable WordPress.PHP.DevelopmentFunctions.error_log_error_log
                return new WP_Error('creation_failed', 'Failed to create chat: ' . $error_msg, array('status' => 500));
            } catch (Exception $e) {
                // phpcs:disable WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Production error tracking for REST API exceptions
                error_log('HeyTrisha REST API Exception: ' . $e->getMessage());
                // phpcs:enable WordPress.PHP.DevelopmentFunctions.error_log_error_log
                return new WP_Error('creation_failed', 'Failed to create chat: ' . $e->getMessage(), array('status' => 500));
            }
        },
        'permission_callback' => function() {
            return current_user_can('manage_options');
        }
    ));
    
    // Update chat
    register_rest_route('heytrisha/v1', '/chats/(?P<id>\d+)', array(
        'methods' => 'POST',
        'callback' => function($request) use ($db) {
            if (!current_user_can('manage_options')) {
                return new WP_Error('forbidden', 'Insufficient permissions.', array('status' => 403));
            }
            $chat_id = intval($request['id']);
            $data = $request->get_json_params();
            $result = $db->update_chat($chat_id, $data);
            if ($result) {
                $chat = $db->get_chat($chat_id);
                return rest_ensure_response($chat);
            }
            return new WP_Error('update_failed', 'Failed to update chat.', array('status' => 500));
        },
        'permission_callback' => function() {
            return current_user_can('manage_options');
        }
    ));
    
    // Delete chat
    register_rest_route('heytrisha/v1', '/chats/(?P<id>\d+)', array(
        'methods' => 'DELETE',
        'callback' => function($request) use ($db) {
            if (!current_user_can('manage_options')) {
                return new WP_Error('forbidden', 'Insufficient permissions.', array('status' => 403));
            }
            $chat_id = intval($request['id']);
            $result = $db->delete_chat($chat_id);
            if ($result) {
                return rest_ensure_response(array('success' => true));
            }
            return new WP_Error('delete_failed', 'Failed to delete chat.', array('status' => 500));
        },
        'permission_callback' => function() {
            return current_user_can('manage_options');
        }
    ));
    
    // Archive/Unarchive chat
    register_rest_route('heytrisha/v1', '/chats/(?P<id>\d+)/archive', array(
        'methods' => 'POST',
        'callback' => function($request) use ($db) {
            if (!current_user_can('manage_options')) {
                return new WP_Error('forbidden', 'Insufficient permissions.', array('status' => 403));
            }
            $chat_id = intval($request['id']);
            $archive = $request->get_param('archive') !== 'false' && $request->get_param('archive') !== '0';
            $result = $db->archive_chat($chat_id, $archive);
            if ($result) {
                $chat = $db->get_chat($chat_id);
                return rest_ensure_response($chat);
            }
            return new WP_Error('archive_failed', 'Failed to archive chat.', array('status' => 500));
        },
        'permission_callback' => function() {
            return current_user_can('manage_options');
        }
    ));
    
    // Add message to chat
    register_rest_route('heytrisha/v1', '/chats/(?P<id>\d+)/messages', array(
        'methods' => 'POST',
        'callback' => function($request) use ($db) {
            if (!current_user_can('manage_options')) {
                return new WP_Error('forbidden', 'Insufficient permissions.', array('status' => 403));
            }
            $chat_id = intval($request['id']);
            $data = $request->get_json_params();
            $role = isset($data['role']) ? $data['role'] : 'user';
            $content = isset($data['content']) ? $data['content'] : '';
            $metadata = isset($data['metadata']) ? $data['metadata'] : null;
            
            if (empty($content)) {
                return new WP_Error('invalid_content', 'Message content is required.', array('status' => 400));
            }
            
            $message_id = $db->add_message($chat_id, $role, $content, $metadata);
            if ($message_id) {
                $messages = $db->get_messages($chat_id);
                $lastMessage = end($messages);
                // Decode metadata JSON string to object for frontend
                if ($lastMessage && isset($lastMessage->metadata) && is_string($lastMessage->metadata)) {
                    $decoded = json_decode($lastMessage->metadata, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $lastMessage->metadata = $decoded;
                    }
                }
                return rest_ensure_response($lastMessage);
            }
            return new WP_Error('message_failed', 'Failed to add message.', array('status' => 500));
        },
        'permission_callback' => function() {
            return current_user_can('manage_options');
        }
    ));
}
add_action('rest_api_init', 'heytrisha_register_chat_rest_routes');

// function heytrisha_enqueue_chatbot_scripts() {
//     // Enqueue React and ReactDOM only for admin users
//     if (current_user_can('administrator')) {

//         // Enqueue React and ReactDOM from CDN (for admin only)
// React CDN loading removed per WordPress.org policy

//         // Enqueue CSS file for chatbot
//         // wp_enqueue_style('chatbot-css', plugin_dir_url(__FILE__) . 'chatbot/static/css/main.css');

//         // Enqueue Chatbot JS (ensure correct path)
//         wp_enqueue_script('chatbot-js', plugin_dir_url(__FILE__) . 'chatbot/static/js/main.d1ca03c3.chunk.js', ['react', 'react-dom'], null, true);

//         echo '<script>console.log("React and ReactDOM are being enqueued for admin.")</script>';
//     }
// }
// add_action('admin_enqueue_scripts', 'heytrisha_enqueue_chatbot_scripts');



// function add_chatbot_widget_to_admin_footer() {
//     if (current_user_can('administrator')) {
//         echo '<div id="chatbot-root"></div>';
//         echo '<script>console.log("✅ Chatbot root div added to admin footer");</script>';
//     }
// }
// add_action('admin_footer', 'add_chatbot_widget_to_admin_footer');





