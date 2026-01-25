<?php
/**
 * Plugin Name: Hey Trisha - AI-Powered WordPress & WooCommerce Chatbot
 * Plugin URI: https://heytrisha.com
 * Description: AI-powered chatbot using OpenAI GPT for WordPress and WooCommerce. Natural language queries, product management, and intelligent responses.
 * Version: 22.0.0
 * Author: HeyTrisha Team
 * Author URI: https://heytrisha.com
 * License: MIT
 * License URI: https://opensource.org/licenses/MIT
 * Terms and Conditions: https://heytrisha.com/terms-and-conditions
 * Text Domain: heytrisha-woo
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.4.3
 * Network: false
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// ✅ CRITICAL: Suppress PHP notices/warnings for our REST API endpoints
// This runs early, but ONLY for REST API requests (not during plugin activation)
// Check if this is our REST API endpoint - must check REQUEST_URI exists first
$is_rest_api_request = isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/wp-json/heytrisha/v1/') !== false;

if ($is_rest_api_request) {
    // Suppress display of all notices, warnings, and deprecation notices
    error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED & ~E_USER_NOTICE & ~E_USER_WARNING & ~E_USER_DEPRECATED);
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    
    // Set custom error handler that swallows notices/warnings immediately
    set_error_handler(function($errno, $errstr, $errfile, $errline) {
        // Suppress all notices, warnings, and deprecation notices
        if ($errno === E_NOTICE || $errno === E_WARNING || $errno === E_DEPRECATED || 
            $errno === E_USER_NOTICE || $errno === E_USER_WARNING || $errno === E_USER_DEPRECATED) {
            return true; // Suppress completely - don't output anything
        }
        return false; // Let fatal errors through
    }, E_ALL);
    
    // CRITICAL: Register shutdown function to catch fatal errors and return JSON
    register_shutdown_function(function() {
        $request_uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
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
                
                // Use wp_json_encode and sanitize error details to prevent information disclosure
                echo wp_json_encode([
                    'success' => false,
                    'message' => 'Internal server error',
                    'error' => esc_html(substr($error['message'], 0, 200)), // Limit error message length
                    'file' => esc_html(basename($error['file'])) . ':' . absint($error['line']), // Only show filename, not full path
                    'type' => 'FatalError'
                ]);
                exit;
            }
        }
    });
    
    // Start output buffering immediately to catch any stray output
    // Clean any existing buffers first, then start a fresh one
    // This buffer is closed by:
    // 1. The shutdown function above (on fatal errors)
    // 2. The rest_pre_serve_request filter (on successful responses)
    // 3. The heytrisha_proxy_laravel_api function (during API processing)
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    ob_start();
    
    // Register cleanup function to ensure buffer is always closed
    register_shutdown_function(function() {
        // Clean any remaining buffers on shutdown
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
    });
}

// Define plugin constants
define('HEYTRISHA_VERSION', '1.0.0');
define('HEYTRISHA_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('HEYTRISHA_PLUGIN_URL', plugin_dir_url(__FILE__));
define('HEYTRISHA_PLUGIN_FILE', __FILE__);
define('HEYTRISHA_MIN_PHP_VERSION', '7.4.3');
define('HEYTRISHA_MIN_WP_VERSION', '5.0');


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
    
    // ✅ Load React locally (bundled with plugin per WordPress guidelines)
    wp_enqueue_script('react', HEYTRISHA_PLUGIN_URL . 'assets/js/vendor/react.production.min.js', [], '18.0', true);
    wp_enqueue_script('react-dom', HEYTRISHA_PLUGIN_URL . 'assets/js/vendor/react-dom.production.min.js', ['react'], '18.0', true);
    
    // ✅ Load Chatbot CSS
    wp_enqueue_style('heytrisha-chatbot-css', HEYTRISHA_PLUGIN_URL . 'assets/css/chatbot.css', [], HEYTRISHA_VERSION);
    
    // ✅ Load Chatbot JavaScript
    wp_enqueue_script('heytrisha-chatbot-js', HEYTRISHA_PLUGIN_URL . 'assets/js/chatbot.js', ['react', 'react-dom'], HEYTRISHA_VERSION, true);
    
    // ✅ Pass configuration to JavaScript
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
add_action('admin_enqueue_scripts', 'heytrisha_enqueue_chatbot');

// ✅ Note: Terms and Conditions are now handled via dedicated admin page after activation
// No modal JavaScript needed - user is redirected to Terms page after activation

// ✅ AJAX handler to save Terms and Conditions acceptance
function heytrisha_ajax_accept_terms() {
    // Verify nonce (sanitized as per WordPress plugin guidelines)
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
        $current_page = isset($_GET['page']) ? $_GET['page'] : '';
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
        /* translators: 1: Required PHP version, 2: Current PHP version */
        esc_html__('Hey Trisha requires PHP version %1$s or higher. You are running PHP %2$s. Please upgrade PHP.', 'heytrisha-woo'),
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
        /* translators: 1: Required WordPress version, 2: Current WordPress version */
        esc_html__('Hey Trisha requires WordPress version %1$s or higher. You are running WordPress %2$s. Please upgrade WordPress.', 'heytrisha-woo'),
        esc_html(HEYTRISHA_MIN_WP_VERSION),
        esc_html($wp_version)
    );
    echo '</p></div>';
}

// ✅ Include required files with error handling
$required_files = array(
    'includes/class-heytrisha-database.php',
    'includes/class-heytrisha-secure-credentials.php', // ✅ Secure credentials manager
    'includes/class-heytrisha-security-filter.php', // ✅ Sensitive data protection
    'includes/class-heytrisha-dependency-installer.php',
    'includes/class-heytrisha-server-manager.php'
);

foreach ($required_files as $file) {
    $file_path = HEYTRISHA_PLUGIN_DIR . $file;
    if (file_exists($file_path)) {
        require_once $file_path;
    } else {
        add_action('admin_notices', function() use ($file) {
            echo '<div class="error"><p>';
            echo sprintf(
                esc_html__('Hey Trisha: Required file missing: %s. Please reinstall the plugin.', 'heytrisha-woo'),
                esc_html($file)
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
        // Check if classes are loaded
        if (!class_exists('HeyTrisha_Database') || !class_exists('HeyTrisha_Secure_Credentials') || 
            !class_exists('HeyTrisha_Dependency_Installer') || !class_exists('HeyTrisha_Server_Manager')) {
            error_log('Hey Trisha: Required plugin classes not loaded during activation');
            // Don't deactivate - just log and continue
            // The plugin can still function, but some features may not work until classes are loaded
        }
        
        // ✅ STEP 1: Create secure credentials table FIRST
        if (class_exists('HeyTrisha_Secure_Credentials')) {
            try {
                HeyTrisha_Secure_Credentials::create_table();
                error_log('✅ HeyTrisha: Secure credentials table created');
            } catch (Exception $e) {
                error_log('❌ HeyTrisha: Secure credentials table creation failed - ' . $e->getMessage());
            }
        }
        
        // STEP 2: Create default options (only if they don't exist - for backwards compatibility)
        add_option('heytrisha_openai_api_key', '', '', 'no');
        add_option('heytrisha_db_host', '127.0.0.1', '', 'no');
        add_option('heytrisha_db_port', '3306', '', 'no');
        add_option('heytrisha_db_name', '', '', 'no');
        add_option('heytrisha_db_user', '', '', 'no');
        add_option('heytrisha_db_password', '', '', 'no');
        add_option('heytrisha_wordpress_api_url', get_site_url(), '', 'no');
        add_option('heytrisha_wordpress_api_user', '', '', 'no');
        add_option('heytrisha_wordpress_api_password', '', '', 'no');
        add_option('heytrisha_woocommerce_consumer_key', '', '', 'no');
        add_option('heytrisha_woocommerce_consumer_secret', '', '', 'no');
        
        // Generate shared token if it doesn't exist
        if (!get_option('heytrisha_shared_token')) {
            add_option('heytrisha_shared_token', wp_generate_password(32, false, false), '', 'no');
        }
        
        // ✅ STEP 3: Migrate credentials to secure table
        if (class_exists('HeyTrisha_Secure_Credentials')) {
            try {
                $migrated = HeyTrisha_Secure_Credentials::migrate_from_options();
                error_log("✅ HeyTrisha: Migrated {$migrated} credentials to secure storage");
            } catch (Exception $e) {
                error_log('❌ HeyTrisha: Credential migration failed - ' . $e->getMessage());
            }
        }
        
        // ✅ STEP 4: Create database tables for chat system
        if (class_exists('HeyTrisha_Database')) {
            try {
                HeyTrisha_Database::create_tables();
            } catch (Exception $e) {
                error_log('Hey Trisha: Database table creation failed - ' . $e->getMessage());
                // Don't fail activation, but log the error
            } catch (Throwable $e) {
                error_log('Hey Trisha: Database table creation failed (Throwable) - ' . $e->getMessage());
            }
        }
        
        // ✅ Install Laravel and React dependencies automatically (non-blocking)
        if (class_exists('HeyTrisha_Dependency_Installer')) {
            try {
                $installer = new HeyTrisha_Dependency_Installer();
                $installation_result = $installer->install_all_dependencies();
                
                // Store installation result for display
                update_option('heytrisha_installation_result', $installation_result);
                update_option('heytrisha_installation_time', current_time('mysql'));
            } catch (Exception $e) {
                error_log('Hey Trisha: Dependency installation failed - ' . $e->getMessage());
                // Don't fail activation, dependencies can be installed later
                update_option('heytrisha_installation_result', array(
                    'success' => false,
                    'messages' => array('Dependency installation will be attempted later.'),
                    'errors' => array($e->getMessage())
                ));
            } catch (Throwable $e) {
                error_log('Hey Trisha: Dependency installation failed (Throwable) - ' . $e->getMessage());
                update_option('heytrisha_installation_result', array(
                    'success' => false,
                    'messages' => array('Dependency installation will be attempted later.'),
                    'errors' => array($e->getMessage())
                ));
            }
        }
        
        // ✅ Auto-start Laravel server on activation (non-blocking, only if not shared hosting)
        if (function_exists('heytrisha_is_shared_hosting') && !heytrisha_is_shared_hosting()) {
            if (!wp_next_scheduled('heytrisha_start_server_on_activation')) {
                wp_schedule_single_event(time() + 10, 'heytrisha_start_server_on_activation'); // Delay to allow dependencies to install
            }
        }
        
        // Flush rewrite rules to ensure REST API endpoints are registered
        flush_rewrite_rules();
        
        // Store activation success flag
        update_option('heytrisha_activation_success', true);
        
    } catch (Throwable $e) {
        // Catch ALL errors (including Parse errors, Type errors, etc.) to prevent activation failure
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

// ✅ Helper function to sanitize confirmation_data array recursively
function heytrisha_sanitize_confirmation_data($data) {
    if (!is_array($data)) {
        return is_string($data) ? sanitize_text_field($data) : $data;
    }
    
    $sanitized = array();
    foreach ($data as $key => $value) {
        $safe_key = sanitize_key($key);
        if (is_array($value)) {
            $sanitized[$safe_key] = heytrisha_sanitize_confirmation_data($value);
        } elseif (is_string($value)) {
            $sanitized[$safe_key] = sanitize_text_field($value);
        } elseif (is_numeric($value)) {
            $sanitized[$safe_key] = is_float($value) ? floatval($value) : intval($value);
        } elseif (is_bool($value)) {
            $sanitized[$safe_key] = (bool) $value;
        } else {
            $sanitized[$safe_key] = $value;
        }
    }
    return $sanitized;
}

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

// ✅ Async server start on activation
function heytrisha_start_server_on_activation() {
    $server_manager = new HeyTrisha_Server_Manager();
    if (!$server_manager->is_server_running()) {
        $server_manager->start_server();
    }
}
add_action('heytrisha_start_server_on_activation', 'heytrisha_start_server_on_activation');

// ✅ Stop server on deactivation
function heytrisha_deactivate_plugin() {
    // Clear scheduled events
    wp_clear_scheduled_hook('heytrisha_start_server_on_activation');
    
    // Stop server if running (only if class exists and not shared hosting)
    if (class_exists('HeyTrisha_Server_Manager') && !heytrisha_is_shared_hosting()) {
        try {
            $server_manager = new HeyTrisha_Server_Manager();
            if ($server_manager->is_server_running()) {
                $server_manager->stop_server();
            }
        } catch (Exception $e) {
            error_log('Hey Trisha: Server stop failed on deactivation - ' . $e->getMessage());
        }
    }
    
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

    $openai_api_key = isset($_POST['heytrisha_openai_api_key']) ? sanitize_text_field(wp_unslash($_POST['heytrisha_openai_api_key'])) : '';
    $db_host = isset($_POST['heytrisha_db_host']) ? sanitize_text_field(wp_unslash($_POST['heytrisha_db_host'])) : '';
    $db_port = isset($_POST['heytrisha_db_port']) ? sanitize_text_field(wp_unslash($_POST['heytrisha_db_port'])) : '';
    $db_name = isset($_POST['heytrisha_db_name']) ? sanitize_text_field(wp_unslash($_POST['heytrisha_db_name'])) : '';
    $db_user = isset($_POST['heytrisha_db_user']) ? sanitize_text_field(wp_unslash($_POST['heytrisha_db_user'])) : '';
    $db_password = isset($_POST['heytrisha_db_password']) ? wp_unslash($_POST['heytrisha_db_password']) : '';
    $wordpress_api_url = isset($_POST['heytrisha_wordpress_api_url']) ? esc_url_raw(wp_unslash($_POST['heytrisha_wordpress_api_url'])) : '';
    $wordpress_api_user = isset($_POST['heytrisha_wordpress_api_user']) ? sanitize_text_field(wp_unslash($_POST['heytrisha_wordpress_api_user'])) : '';
    $wordpress_api_password = isset($_POST['heytrisha_wordpress_api_password']) ? wp_unslash($_POST['heytrisha_wordpress_api_password']) : '';
    $woocommerce_consumer_key = isset($_POST['heytrisha_woocommerce_consumer_key']) ? sanitize_text_field(wp_unslash($_POST['heytrisha_woocommerce_consumer_key'])) : '';
    $woocommerce_consumer_secret = isset($_POST['heytrisha_woocommerce_consumer_secret']) ? wp_unslash($_POST['heytrisha_woocommerce_consumer_secret']) : '';
    $shared_token = isset($_POST['heytrisha_shared_token']) ? sanitize_text_field(wp_unslash($_POST['heytrisha_shared_token'])) : '';

    // ✅ Store sensitive credentials in secure encrypted table
    if (!empty($openai_api_key)) {
        heytrisha_set_credential(HeyTrisha_Secure_Credentials::KEY_OPENAI_API, $openai_api_key);
    }
    if (!empty($db_password)) {
        heytrisha_set_credential(HeyTrisha_Secure_Credentials::KEY_DB_PASSWORD, $db_password);
    }
    if (!empty($wordpress_api_password)) {
        heytrisha_set_credential(HeyTrisha_Secure_Credentials::KEY_WP_API_PASSWORD, $wordpress_api_password);
    }
    if (!empty($woocommerce_consumer_secret)) {
        heytrisha_set_credential(HeyTrisha_Secure_Credentials::KEY_WC_CONSUMER_SECRET, $woocommerce_consumer_secret);
    }
    if (!empty($shared_token)) {
        heytrisha_set_credential(HeyTrisha_Secure_Credentials::KEY_SHARED_TOKEN, $shared_token);
    }
    
    // ✅ Store non-sensitive settings in wp_options
    update_option('heytrisha_db_host', $db_host);
    update_option('heytrisha_db_port', $db_port);
    update_option('heytrisha_db_name', $db_name);
    update_option('heytrisha_db_user', $db_user);
    update_option('heytrisha_wordpress_api_url', $wordpress_api_url);
    update_option('heytrisha_wordpress_api_user', $wordpress_api_user);
    update_option('heytrisha_woocommerce_consumer_key', $woocommerce_consumer_key);

    add_settings_error('heytrisha_settings', 'settings_updated', 'Settings saved.', 'updated');
}
add_action('admin_init', 'heytrisha_handle_settings_save');

// ✅ Server Management AJAX Handlers
function heytrisha_ajax_start_server() {
    check_ajax_referer('heytrisha_server_action', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    $server_manager = new HeyTrisha_Server_Manager();
    $result = $server_manager->start_server();
    
    if ($result['success']) {
        wp_send_json_success($result);
    } else {
        wp_send_json_error($result);
    }
}
add_action('wp_ajax_heytrisha_start_server', 'heytrisha_ajax_start_server');

function heytrisha_ajax_stop_server() {
    check_ajax_referer('heytrisha_server_action', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    $server_manager = new HeyTrisha_Server_Manager();
    $result = $server_manager->stop_server();
    
    wp_send_json_success($result);
}
add_action('wp_ajax_heytrisha_stop_server', 'heytrisha_ajax_stop_server');

function heytrisha_ajax_restart_server() {
    check_ajax_referer('heytrisha_server_action', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    $server_manager = new HeyTrisha_Server_Manager();
    $result = $server_manager->restart_server();
    
    if ($result['success']) {
        wp_send_json_success($result);
    } else {
        wp_send_json_error($result);
    }
}
add_action('wp_ajax_heytrisha_restart_server', 'heytrisha_ajax_restart_server');

function heytrisha_ajax_reinstall_dependencies() {
    check_ajax_referer('heytrisha_server_action', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    $installer = new HeyTrisha_Dependency_Installer();
    $result = $installer->install_all_dependencies();
    
    // Store installation result
    update_option('heytrisha_installation_result', $result);
    update_option('heytrisha_installation_time', current_time('mysql'));
    
    if ($result['success']) {
        wp_send_json_success([
            'message' => 'Dependencies installed successfully!',
            'details' => $result
        ]);
    } else {
        wp_send_json_error([
            'message' => 'Dependencies installation completed with errors. Check the status below.',
            'details' => $result
        ]);
    }
}
add_action('wp_ajax_heytrisha_reinstall_dependencies', 'heytrisha_ajax_reinstall_dependencies');

// ✅ Generate Laravel APP_KEY via AJAX (for shared hosting)
function heytrisha_ajax_generate_app_key() {
    check_ajax_referer('heytrisha_server_action', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    $api_path = HEYTRISHA_PLUGIN_DIR . 'api';
    $env_file = $api_path . '/.env';
    $env_example = $api_path . '/.env.example';
    
    // Create .env if it doesn't exist
    if (!file_exists($env_file) && file_exists($env_example)) {
        copy($env_example, $env_file);
    }
    
    if (!file_exists($env_file)) {
        wp_send_json_error(['message' => '.env file not found and could not be created']);
    }
    
    $env_content = file_get_contents($env_file);
    
    // Check if APP_KEY already exists and is valid
    if (preg_match('/^APP_KEY=base64:[A-Za-z0-9+\/]+={0,2}$/m', $env_content)) {
        wp_send_json_success(['message' => 'APP_KEY already exists and is valid']);
    }
    
    // Generate a random 32-byte key and encode it as base64
    $key = 'base64:' . base64_encode(random_bytes(32));
    
    // Replace or add APP_KEY
    if (preg_match('/^APP_KEY=.*$/m', $env_content)) {
        $env_content = preg_replace('/^APP_KEY=.*$/m', 'APP_KEY=' . $key, $env_content);
    } else {
        if (preg_match('/^(APP_NAME=.*)$/m', $env_content)) {
            $env_content = preg_replace('/^(APP_NAME=.*)$/m', '$1' . "\n" . 'APP_KEY=' . $key, $env_content);
        } else {
            $env_content = 'APP_KEY=' . $key . "\n" . $env_content;
        }
    }
    
    if (file_put_contents($env_file, $env_content) !== false) {
        wp_send_json_success(['message' => 'APP_KEY generated successfully!']);
    } else {
        wp_send_json_error(['message' => 'Failed to write APP_KEY to .env file. Check file permissions.']);
    }
}
add_action('wp_ajax_heytrisha_generate_app_key', 'heytrisha_ajax_generate_app_key');

// ✅ Auto-start server on admin page load (if not running) - Non-blocking
function heytrisha_auto_start_server() {
    if (!current_user_can('manage_options')) {
        return;
    }
    
    // Only start on settings page, and do it asynchronously via JavaScript
    // This prevents blocking the page load
    $screen = get_current_screen();
    if ($screen && $screen->id === 'toplevel_page_heytrisha-chatbot-settings') {
        // Server will be started via AJAX call to avoid blocking
        // This is handled in the settings page JavaScript
    }
}
add_action('admin_head', 'heytrisha_auto_start_server');

// ✅ Render admin settings page
function heytrisha_render_settings_page() {
    if (!current_user_can('manage_options')) {
        return;
    }

    settings_errors('heytrisha_settings');

    // ✅ Get credentials - secure ones from encrypted storage, others from wp_options
    $openai_api_key = heytrisha_get_credential(HeyTrisha_Secure_Credentials::KEY_OPENAI_API, 'heytrisha_openai_api_key', '');
    $db_host = get_option('heytrisha_db_host', '127.0.0.1');
    $db_port = get_option('heytrisha_db_port', '3306');
    $db_name = get_option('heytrisha_db_name', '');
    $db_user = get_option('heytrisha_db_user', '');
    $db_password = heytrisha_get_credential(HeyTrisha_Secure_Credentials::KEY_DB_PASSWORD, 'heytrisha_db_password', '');
    $wordpress_api_url = get_option('heytrisha_wordpress_api_url', get_site_url());
    $wordpress_api_user = get_option('heytrisha_wordpress_api_user', '');
    $wordpress_api_password = heytrisha_get_credential(HeyTrisha_Secure_Credentials::KEY_WP_API_PASSWORD, 'heytrisha_wordpress_api_password', '');
    $woocommerce_consumer_key = get_option('heytrisha_woocommerce_consumer_key', '');
    $woocommerce_consumer_secret = heytrisha_get_credential(HeyTrisha_Secure_Credentials::KEY_WC_CONSUMER_SECRET, 'heytrisha_woocommerce_consumer_secret', '');
    $shared_token = heytrisha_get_credential(HeyTrisha_Secure_Credentials::KEY_SHARED_TOKEN, 'heytrisha_shared_token', '');

    echo '<div class="wrap">';
    echo '<h1>HeyTrisha Chatbot Settings</h1>';
    echo '<form method="post">';
    wp_nonce_field('heytrisha_save_settings', 'heytrisha_settings_nonce');

    echo '<h2>OpenAI</h2>';
    echo '<table class="form-table"><tbody>';
    echo '<tr><th scope="row"><label for="heytrisha_openai_api_key">OpenAI API Key</label></th>';
    echo '<td><input type="password" id="heytrisha_openai_api_key" name="heytrisha_openai_api_key" value="' . esc_attr($openai_api_key) . '" class="regular-text" autocomplete="off" /></td></tr>';
    echo '</tbody></table>';

    echo '<h2>Database</h2>';
    echo '<table class="form-table"><tbody>';
    echo '<tr><th scope="row"><label for="heytrisha_db_host">Host</label></th>';
    echo '<td><input type="text" id="heytrisha_db_host" name="heytrisha_db_host" value="' . esc_attr($db_host) . '" class="regular-text" /></td></tr>';
    echo '<tr><th scope="row"><label for="heytrisha_db_port">Port</label></th>';
    echo '<td><input type="text" id="heytrisha_db_port" name="heytrisha_db_port" value="' . esc_attr($db_port) . '" class="regular-text" /></td></tr>';
    echo '<tr><th scope="row"><label for="heytrisha_db_name">Database Name</label></th>';
    echo '<td><input type="text" id="heytrisha_db_name" name="heytrisha_db_name" value="' . esc_attr($db_name) . '" class="regular-text" /></td></tr>';
    echo '<tr><th scope="row"><label for="heytrisha_db_user">Username</label></th>';
    echo '<td><input type="text" id="heytrisha_db_user" name="heytrisha_db_user" value="' . esc_attr($db_user) . '" class="regular-text" /></td></tr>';
    echo '<tr><th scope="row"><label for="heytrisha_db_password">Password</label></th>';
    echo '<td><input type="password" id="heytrisha_db_password" name="heytrisha_db_password" value="' . esc_attr($db_password) . '" class="regular-text" autocomplete="new-password" /></td></tr>';
    echo '</tbody></table>';

    echo '<h2>WordPress API</h2>';
    echo '<p>WordPress REST API credentials for the Laravel backend to interact with WordPress.</p>';
    echo '<table class="form-table"><tbody>';
    echo '<tr><th scope="row"><label for="heytrisha_wordpress_api_url">WordPress API URL</label></th>';
    echo '<td><input type="url" id="heytrisha_wordpress_api_url" name="heytrisha_wordpress_api_url" value="' . esc_attr($wordpress_api_url) . '" class="regular-text" placeholder="' . esc_attr(get_site_url()) . '" /></td></tr>';
    echo '<tr><th scope="row"><label for="heytrisha_wordpress_api_user">WordPress API Username</label></th>';
    echo '<td><input type="text" id="heytrisha_wordpress_api_user" name="heytrisha_wordpress_api_user" value="' . esc_attr($wordpress_api_user) . '" class="regular-text" /></td></tr>';
    echo '<tr><th scope="row"><label for="heytrisha_wordpress_api_password">WordPress API Password</label></th>';
    echo '<td><input type="password" id="heytrisha_wordpress_api_password" name="heytrisha_wordpress_api_password" value="' . esc_attr($wordpress_api_password) . '" class="regular-text" autocomplete="new-password" /></td></tr>';
    echo '<tr><th scope="row"><label>Application Password</label></th>';
    echo '<td><p class="description">Generate an Application Password from <a href="' . admin_url('profile.php') . '#application-passwords" target="_blank">Users → Your Profile → Application Passwords</a></p></td></tr>';
    echo '</tbody></table>';

    echo '<h2>WooCommerce</h2>';
    echo '<p>WooCommerce REST API credentials for the chatbot to access your store data.</p>';
    echo '<table class="form-table"><tbody>';
    echo '<tr><th scope="row"><label for="heytrisha_woocommerce_consumer_key">WooCommerce Consumer Key</label></th>';
    echo '<td><input type="text" id="heytrisha_woocommerce_consumer_key" name="heytrisha_woocommerce_consumer_key" value="' . esc_attr($woocommerce_consumer_key) . '" class="regular-text" /></td></tr>';
    echo '<tr><th scope="row"><label for="heytrisha_woocommerce_consumer_secret">WooCommerce Consumer Secret</label></th>';
    echo '<td><input type="password" id="heytrisha_woocommerce_consumer_secret" name="heytrisha_woocommerce_consumer_secret" value="' . esc_attr($woocommerce_consumer_secret) . '" class="regular-text" autocomplete="new-password" /></td></tr>';
    echo '<tr><th scope="row"><label>Generate API Keys</label></th>';
    echo '<td><p class="description">Generate WooCommerce API keys from <a href="' . admin_url('admin.php?page=wc-settings&tab=advanced&section=keys') . '" target="_blank">WooCommerce → Settings → Advanced → REST API</a></p></td></tr>';
    echo '</tbody></table>';

    echo '<h2>Integration</h2>';
    echo '<p>This token allows your Laravel API to fetch credentials from the WordPress site securely.</p>';
    echo '<table class="form-table"><tbody>';
    echo '<tr><th scope="row"><label for="heytrisha_shared_token">Shared Access Token</label></th>';
    echo '<td><input type="text" id="heytrisha_shared_token" name="heytrisha_shared_token" value="' . esc_attr($shared_token) . '" class="regular-text" /></td></tr>';
    echo '</tbody></table>';

    // ✅ Dependency Installation Status
    $installer = new HeyTrisha_Dependency_Installer();
    $install_status = $installer->get_installation_status();
    $installation_result = get_option('heytrisha_installation_result', null);
    
    echo '<h2>Dependency Installation Status</h2>';
    echo '<div class="heytrisha-install-status" style="background: #f0f0f1; padding: 15px; border-radius: 4px; margin-bottom: 15px;">';
    
    if ($installation_result) {
        echo '<h3 style="margin-top: 0;">Last Installation Result</h3>';
        if ($installation_result['success']) {
            echo '<p style="color: #00a32a; font-weight: bold;">✓ Installation completed successfully</p>';
        } else {
            echo '<p style="color: #d63638; font-weight: bold;">⚠ Installation completed with some warnings</p>';
        }
        
        if (!empty($installation_result['messages'])) {
            echo '<ul style="margin-left: 20px;">';
            foreach ($installation_result['messages'] as $message) {
                echo '<li>' . esc_html($message) . '</li>';
            }
            echo '</ul>';
        }
        
        if (!empty($installation_result['errors'])) {
            echo '<h4 style="color: #d63638;">Errors:</h4>';
            echo '<ul style="margin-left: 20px; color: #d63638;">';
            foreach ($installation_result['errors'] as $error) {
                echo '<li>' . esc_html($error) . '</li>';
            }
            echo '</ul>';
        }
    }
    
    echo '<h3>Current Status</h3>';
    echo '<ul style="margin-left: 20px;">';
    echo '<li><strong>Laravel Dependencies:</strong> ' . ($install_status['laravel_installed'] ? '<span style="color: #00a32a;">✓ Installed</span>' : '<span style="color: #d63638;">✗ Not Installed</span>') . '</li>';
    $laravel_key_exists = $install_status['laravel_key_exists'];
    echo '<li><strong>Laravel App Key:</strong> ' . ($laravel_key_exists ? '<span style="color: #00a32a;">✓ Generated</span>' : '<span style="color: #d63638;">✗ Not Generated</span>');
    if (!$laravel_key_exists) {
        echo ' <button type="button" class="button button-secondary" onclick="heytrishaGenerateAppKey()" style="margin-left: 10px;">🔑 Generate APP_KEY</button>';
    }
    echo '</li>';
    echo '<li><strong>React Dependencies:</strong> ' . ($install_status['react_installed'] ? '<span style="color: #00a32a;">✓ Installed</span>' : '<span style="color: #d63638;">✗ Not Installed (Optional - React loaded from CDN)</span>') . '</li>';
    echo '<li><strong>Composer Available:</strong> ' . ($install_status['composer_available'] ? '<span style="color: #00a32a;">✓ Yes</span>' : '<span style="color: #d63638;">✗ No</span>') . '</li>';
    echo '<li><strong>npm Available:</strong> ' . ($install_status['npm_available'] ? '<span style="color: #00a32a;">✓ Yes</span>' : '<span style="color: #d63638;">✗ No (Optional)</span>') . '</li>';
    echo '</ul>';
    
    echo '<p>';
    echo '<button type="button" class="button button-secondary" onclick="heytrishaReinstallDependencies()">🔄 Reinstall Dependencies</button>';
    if (!$laravel_key_exists) {
        echo ' <button type="button" class="button button-primary" onclick="heytrishaGenerateAppKey()">🔑 Generate APP_KEY</button>';
    }
    echo '</p>';
    
    echo '</div>';

    // ✅ API Configuration Section
    $is_shared_hosting = heytrisha_is_shared_hosting();
    $api_url = heytrisha_get_api_url();
    
    echo '<h2>API Configuration</h2>';
    echo '<div class="notice notice-info inline" style="margin: 15px 0; padding: 12px;">';
    
    if ($is_shared_hosting) {
        echo '<p><strong>🌐 Environment:</strong> Shared Hosting (Automatic)</p>';
        echo '<p><strong>API URL:</strong> <code>' . esc_html($api_url) . '/api/query</code></p>';
        echo '<p class="description">✅ The chatbot works automatically via your web server. No server management needed!</p>';
        
        // Add diagnostic links (using WordPress REST API)
        $health_url = rest_url('heytrisha/v1/api/health');
        $diagnostic_url = rest_url('heytrisha/v1/api/diagnostic');
        
        echo '<p><strong>🔍 Diagnostic Tools:</strong></p>';
        echo '<ul style="margin-left: 20px;">';
        echo '<li><a href="' . esc_url($health_url) . '" target="_blank">Health Check</a> - Check Laravel status</li>';
        echo '<li><a href="' . esc_url($diagnostic_url) . '" target="_blank">Full Diagnostic</a> - Detailed system check</li>';
        echo '</ul>';
    } else {
        echo '<p><strong>💻 Environment:</strong> Development/VPS</p>';
        echo '<p><strong>API URL:</strong> <code>' . esc_html($api_url) . '/api/query</code></p>';
        echo '<p class="description">⚠️  For development: Run <code>cd api && php artisan serve</code> in your terminal.</p>';
    }
    
    echo '</div>';
    
    echo '<script>
    function heytrishaReinstallDependencies() {
        if (!confirm("This will reinstall all Laravel and React dependencies. This may take a few minutes. Continue?")) return;
        jQuery.post(ajaxurl, {
            action: "heytrisha_reinstall_dependencies",
            nonce: "' . wp_create_nonce('heytrisha_server_action') . '"
        }, function(response) {
            if (response.success) {
                alert("Dependencies installation completed! Check the status below.");
                location.reload();
            } else {
                alert("Error: " + (response.data.message || "Unknown error"));
            }
        });
    }
    function heytrishaGenerateAppKey() {
        if (!confirm("This will generate a new Laravel APP_KEY. Continue?")) return;
        jQuery.post(ajaxurl, {
            action: "heytrisha_generate_app_key",
            nonce: "' . wp_create_nonce('heytrisha_server_action') . '"
        }, function(response) {
            if (response.success) {
                alert("APP_KEY generated successfully! The page will reload.");
                location.reload();
            } else {
                alert("Error: " + (response.data && response.data.message ? response.data.message : "Failed to generate APP_KEY"));
            }
        }).fail(function() {
            alert("Error: Failed to communicate with server. Please check your connection.");
        });
    }
    </script>';

    submit_button('Save Changes');
    echo '</form>';
    echo '</div>';
}

// ✅ Render Terms and Conditions Page
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
                <button type="button" id="heytrisha-terms-activate-btn" class="button button-primary button-large" disabled style="font-size: 14px; padding: 10px 30px; height: auto;">
                    Activate Plugin
                </button>
            </div>
        </div>
    </div>
    
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        var $checkbox = $('#heytrisha-accept-terms-checkbox');
        var $activateBtn = $('#heytrisha-terms-activate-btn');
        
        // Enable/disable button based on checkbox
        $checkbox.on('change', function() {
            $activateBtn.prop('disabled', !this.checked);
        });
        
        // Handle activate button click
        $activateBtn.on('click', function() {
            if (!$checkbox.is(':checked')) {
                alert('Please check the box to accept the Terms and Conditions.');
                return;
            }
            
            var $btn = $(this);
            $btn.prop('disabled', true).text('Processing...');
            
            // Save acceptance via AJAX
            var ajaxUrl = typeof heytrishaTermsAjax !== 'undefined' ? heytrishaTermsAjax.ajaxurl : '<?php echo esc_url(admin_url('admin-ajax.php')); ?>';
            var nonce = typeof heytrishaTermsAjax !== 'undefined' ? heytrishaTermsAjax.nonce : '<?php echo wp_create_nonce('heytrisha_accept_terms'); ?>';
            var settingsUrl = typeof heytrishaTermsAjax !== 'undefined' ? heytrishaTermsAjax.settingsUrl : '<?php echo esc_url(admin_url('admin.php?page=heytrisha-chatbot-settings')); ?>';
            
            $.ajax({
                url: ajaxUrl,
                type: 'POST',
                data: {
                    action: 'heytrisha_accept_terms',
                    nonce: nonce,
                    accepted: 'true'
                },
                success: function(response) {
                    if (response.success) {
                        // Redirect to settings page
                        window.location.href = settingsUrl;
                    } else {
                        $btn.prop('disabled', false).text('Activate Plugin');
                        alert('Error: ' + (response.data && response.data.message ? response.data.message : 'Failed to save acceptance.'));
                    }
                },
                error: function() {
                    $btn.prop('disabled', false).text('Activate Plugin');
                    alert('Error: Failed to communicate with server. Please try again.');
                }
            });
        });
    });
    </script>
    <?php
}

// ✅ Render New Chat Page
function heytrisha_render_new_chat_page() {
    if (!current_user_can('manage_options')) {
        return;
    }
    
    $chat_id = isset($_GET['chat_id']) ? intval($_GET['chat_id']) : 0;
    
    // Enqueue chat interface scripts (React bundled locally per WordPress guidelines)
    wp_enqueue_script('react', HEYTRISHA_PLUGIN_URL . 'assets/js/vendor/react.production.min.js', [], '18.0', true);
    wp_enqueue_script('react-dom', HEYTRISHA_PLUGIN_URL . 'assets/js/vendor/react-dom.production.min.js', ['react'], '18.0', true);
    wp_enqueue_style('heytrisha-chat-admin-css', HEYTRISHA_PLUGIN_URL . 'assets/css/chat-admin.css', [], HEYTRISHA_VERSION);
    wp_enqueue_script('heytrisha-chat-admin-js', HEYTRISHA_PLUGIN_URL . 'assets/js/chat-admin.js', ['react', 'react-dom'], HEYTRISHA_VERSION, true);
    
    $plugin_url = HEYTRISHA_PLUGIN_URL;
    
    wp_localize_script('heytrisha-chat-admin-js', 'heytrishaChatConfig', [
        'pluginUrl' => $plugin_url,
        'ajaxurl' => admin_url('admin-ajax.php'), // ✅ Use admin-ajax.php for queries (secure, hidden endpoint)
        'chatId' => $chat_id,
        'restUrl' => rest_url('heytrisha/v1/'), // Keep for chat management (chats, messages)
        'nonce' => wp_create_nonce('wp_rest')
    ]);
    
    echo '<div class="wrap">';
    echo '<div id="heytrisha-chat-admin-root"></div>';
    echo '</div>';
}

// ✅ Render Chats List Page
function heytrisha_render_chats_page() {
    if (!current_user_can('manage_options')) {
        return;
    }
    
    wp_enqueue_style('heytrisha-chats-list-css', HEYTRISHA_PLUGIN_URL . 'assets/css/chats-list.css', [], HEYTRISHA_VERSION);
    wp_enqueue_script('heytrisha-chats-list-js', HEYTRISHA_PLUGIN_URL . 'assets/js/chats-list.js', ['jquery'], HEYTRISHA_VERSION, true);
    
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
    wp_enqueue_script('heytrisha-chats-list-js', HEYTRISHA_PLUGIN_URL . 'assets/js/chats-list.js', ['jquery'], HEYTRISHA_VERSION, true);
    
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

// ✅ Get API URL based on environment
function heytrisha_get_api_url() {
    // Always use WordPress REST API endpoint for security
    // WordPress blocks direct PHP execution in plugin directories
    // Route: /wp-json/heytrisha/v1/api/{endpoint}
    return rest_url('heytrisha/v1/api/');
}

// ✅ Proxy function to execute Laravel API internally through WordPress
function heytrisha_proxy_laravel_api($request) {
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
    $original_method = $_SERVER['REQUEST_METHOD'];
    $original_uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
    $original_path_info = isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : '';
    $original_script_name = isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '';
    $original_query_string = isset($_SERVER['QUERY_STRING']) ? $_SERVER['QUERY_STRING'] : '';
    
    // Set up environment for Laravel
    // Laravel routes are under /api prefix, so prepend it
    $_SERVER['REQUEST_URI'] = '/api/' . $endpoint;
    $_SERVER['PATH_INFO'] = '/api/' . $endpoint;
    $_SERVER['SCRIPT_NAME'] = '/index.php';
    $_SERVER['REQUEST_METHOD'] = 'POST'; // Always POST for Laravel API
    $_SERVER['QUERY_STRING'] = '';
    
    // ✅ Build request body for Laravel (from request object or POST data)
    // All inputs are sanitized before use
    $request_body = array();
    
    // ✅ Extract query - prioritize request object, then POST, then body (all sanitized)
    if ($query !== null && $query !== '') {
        $request_body['query'] = sanitize_text_field($query);
    } elseif (isset($request->body['query']) && $request->body['query'] !== '') {
        $request_body['query'] = sanitize_text_field($request->body['query']);
    } elseif (isset($_POST['query']) && $_POST['query'] !== '') {
        $request_body['query'] = sanitize_text_field(wp_unslash($_POST['query']));
    }
    
    // ✅ Extract confirmed flag (boolean validation)
    if ($confirmed !== false && $confirmed !== null) {
        $request_body['confirmed'] = (bool) $confirmed;
    } elseif (isset($request->body['confirmed'])) {
        $request_body['confirmed'] = (bool) $request->body['confirmed'];
    } elseif (isset($_POST['confirmed'])) {
        $request_body['confirmed'] = filter_var(wp_unslash($_POST['confirmed']), FILTER_VALIDATE_BOOLEAN);
    }
    
    // ✅ Extract confirmation_data (sanitized)
    if ($confirmation_data !== null) {
        $request_body['confirmation_data'] = is_array($confirmation_data) 
            ? heytrisha_sanitize_confirmation_data($confirmation_data) 
            : sanitize_text_field($confirmation_data);
    } elseif (isset($request->body['confirmation_data'])) {
        $request_body['confirmation_data'] = is_array($request->body['confirmation_data']) 
            ? heytrisha_sanitize_confirmation_data($request->body['confirmation_data']) 
            : sanitize_text_field($request->body['confirmation_data']);
    } elseif (isset($_POST['confirmation_data'])) {
        // If it's a JSON string, decode and sanitize it
        if (is_string($_POST['confirmation_data'])) {
            $decoded = json_decode(wp_unslash($_POST['confirmation_data']), true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $request_body['confirmation_data'] = heytrisha_sanitize_confirmation_data($decoded);
            } else {
                $request_body['confirmation_data'] = sanitize_text_field(wp_unslash($_POST['confirmation_data']));
            }
        } else {
            $request_body['confirmation_data'] = is_array($_POST['confirmation_data']) 
                ? heytrisha_sanitize_confirmation_data($_POST['confirmation_data']) 
                : sanitize_text_field(wp_unslash($_POST['confirmation_data']));
        }
    }
    
    // ✅ If still no query, try to get from php://input (sanitized)
    if (empty($request_body['query'])) {
        $input = file_get_contents('php://input');
        if (!empty($input)) {
            $decoded = json_decode($input, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                if (isset($decoded['query']) && $decoded['query'] !== '') {
                    $request_body['query'] = sanitize_text_field($decoded['query']);
                }
                // Merge other fields if not already set (sanitized)
                if (!isset($request_body['confirmed']) && isset($decoded['confirmed'])) {
                    $request_body['confirmed'] = (bool) $decoded['confirmed'];
                }
                if (!isset($request_body['confirmation_data']) && isset($decoded['confirmation_data'])) {
                    $request_body['confirmation_data'] = is_array($decoded['confirmation_data']) 
                        ? heytrisha_sanitize_confirmation_data($decoded['confirmation_data']) 
                        : sanitize_text_field($decoded['confirmation_data']);
                }
            }
        }
    }
    
    // Validation handled - no debug logging of sensitive data
    
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
    
    // CRITICAL: Suppress PHP notices/warnings from other plugins before capturing output
    // This prevents notices from interfering with JSON responses
    $original_error_reporting = error_reporting();
    $original_display_errors = ini_get('display_errors');
    $original_error_handler = set_error_handler(null);
    
    // Suppress all error display
    error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED & ~E_USER_NOTICE & ~E_USER_WARNING & ~E_USER_DEPRECATED);
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    
    // Custom error handler that completely swallows notices/warnings
    set_error_handler(function($errno, $errstr, $errfile, $errline) {
        // Suppress all notices, warnings, and deprecation notices
        if ($errno === E_NOTICE || $errno === E_WARNING || $errno === E_DEPRECATED || 
            $errno === E_USER_NOTICE || $errno === E_USER_WARNING || $errno === E_USER_DEPRECATED) {
            return true; // Suppress the error completely - don't output anything
        }
        return false; // Let fatal errors through
    }, E_ALL);
    
    // Capture Laravel output (this will also capture any stray output from other plugins)
    // Clean any existing buffers first, then start fresh
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    ob_start();
    
    try {
        // Define ABSPATH before including Laravel so it knows it's being called from WordPress
        // Use a string value (WordPress convention) instead of boolean
        if (!defined('ABSPATH')) {
            define('ABSPATH', dirname(__FILE__) . '/');
        }
        
        // ✅ CRITICAL: Inject WordPress configuration as HTTP headers for Laravel
        // This allows Laravel to access WordPress settings without needing to fetch from REST API
        heytrisha_inject_credentials_as_headers();
        $_SERVER['HTTP_X_WORDPRESS_SHARED_TOKEN'] = heytrisha_get_credential(HeyTrisha_Secure_Credentials::KEY_SHARED_TOKEN, 'heytrisha_shared_token', '');
        $_SERVER['HTTP_X_WORDPRESS_URL'] = get_site_url();
        
        // Include Laravel bootstrap
        require_once $laravel_path;
        
        // Get output and clean ALL buffers (there may be multiple levels from other plugins)
        $output = '';
        while (ob_get_level() > 0) {
            $output = ob_get_clean();
        }
        
        // Restore error reporting and error handler
        error_reporting($original_error_reporting);
        ini_set('display_errors', $original_display_errors);
        if ($original_error_handler !== null) {
            set_error_handler($original_error_handler);
        } else {
            restore_error_handler();
        }
        
        // Restore original server variables
        $_SERVER['REQUEST_URI'] = $original_uri;
        $_SERVER['PATH_INFO'] = $original_path_info;
        $_SERVER['SCRIPT_NAME'] = $original_script_name;
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
        
        // Restore error reporting and error handler
        error_reporting($original_error_reporting);
        ini_set('display_errors', $original_display_errors);
        if (isset($original_error_handler) && $original_error_handler !== null) {
            set_error_handler($original_error_handler);
        } else {
            restore_error_handler();
        }
        
        // Restore original server variables
        $_SERVER['REQUEST_URI'] = $original_uri;
        $_SERVER['PATH_INFO'] = $original_path_info;
        $_SERVER['SCRIPT_NAME'] = $original_script_name;
        $_SERVER['QUERY_STRING'] = $original_query_string;
        
        // Provide detailed error information for debugging
        $errorMessage = $e->getMessage();
        $errorFile = $e->getFile();
        $errorLine = $e->getLine();
        
        // Log the error for debugging
        error_log('Hey Trisha Laravel Error: ' . $errorMessage . ' in ' . $errorFile . ':' . $errorLine);
        
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
        
        // Restore error reporting and error handler
        error_reporting($original_error_reporting);
        ini_set('display_errors', $original_display_errors);
        if (isset($original_error_handler) && $original_error_handler !== null) {
            set_error_handler($original_error_handler);
        } else {
            restore_error_handler();
        }
        
        // Restore original server variables
        $_SERVER['REQUEST_URI'] = $original_uri;
        $_SERVER['PATH_INFO'] = $original_path_info;
        $_SERVER['SCRIPT_NAME'] = $original_script_name;
        $_SERVER['QUERY_STRING'] = $original_query_string;
        
        // Provide detailed error information for debugging
        $errorMessage = $e->getMessage();
        $errorFile = $e->getFile();
        $errorLine = $e->getLine();
        
        // Log the error for debugging
        error_log('Hey Trisha Laravel Error: ' . $errorMessage . ' in ' . $errorFile . ':' . $errorLine);
        
        return new WP_Error('laravel_error', $errorMessage, array(
            'status' => 500,
            'file' => $errorFile,
            'line' => $errorLine
        ));
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
if (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/wp-json/heytrisha/v1/') !== false) {
    // Start output buffering at the absolute earliest point
    if (ob_get_level() === 0) {
        ob_start();
    }
}

// ✅ CRITICAL: Suppress PHP notices/warnings for our REST API endpoints
// This must run VERY early to prevent notices from other plugins from interfering
function heytrisha_suppress_api_errors() {
    // Check if this is our REST API endpoint
    $request_uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
    if (strpos($request_uri, '/wp-json/heytrisha/v1/') !== false) {
        // Suppress display of all notices, warnings, and deprecation notices
        error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED & ~E_USER_NOTICE & ~E_USER_WARNING & ~E_USER_DEPRECATED);
        ini_set('display_errors', '0');
        ini_set('display_startup_errors', '0');
        
        // Set custom error handler that swallows notices/warnings
        set_error_handler(function($errno, $errstr, $errfile, $errline) {
            // Suppress all notices, warnings, and deprecation notices
            if ($errno === E_NOTICE || $errno === E_WARNING || $errno === E_DEPRECATED || 
                $errno === E_USER_NOTICE || $errno === E_USER_WARNING || $errno === E_USER_DEPRECATED) {
                return true; // Suppress completely
            }
            return false; // Let fatal errors through
        }, E_ALL);
        
        // Ensure output buffering is active
        if (ob_get_level() === 0) {
            ob_start();
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
    $request_uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
    if (strpos($request_uri, '/wp-json/heytrisha/v1/') !== false) {
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
                'message' => is_string($message) ? sanitize_text_field($message) : 'Internal server error',
            ];
            
            // Include error details if available
            if (is_wp_error($message)) {
                $error_data['error'] = sanitize_text_field($message->get_error_message());
                $error_data['code'] = sanitize_key($message->get_error_code());
            }
            
            // Use wp_json_encode for proper escaping
            echo wp_json_encode($error_data);
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
        // Suppress errors for our API endpoints
        error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED & ~E_USER_NOTICE & ~E_USER_WARNING & ~E_USER_DEPRECATED);
        ini_set('display_errors', '0');
        ini_set('display_startup_errors', '0');
        
        // Set error handler
        set_error_handler(function($errno, $errstr, $errfile, $errline) {
            if ($errno === E_NOTICE || $errno === E_WARNING || $errno === E_DEPRECATED || 
                $errno === E_USER_NOTICE || $errno === E_USER_WARNING || $errno === E_USER_DEPRECATED) {
                return true;
            }
            return false;
        }, E_ALL);
        
        // Ensure output buffering is active
        // Clean any existing buffers first
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        ob_start();
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
            error_log('HeyTrisha: Cleaned stray output (' . strlen($buffered_output) . ' bytes): ' . substr($buffered_output, 0, 200));
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
            // CRITICAL: Suppress errors and start output buffering BEFORE calling proxy
            // This must happen here because notices are output during REST API init
            $original_error_reporting = error_reporting();
            $original_display_errors = ini_get('display_errors');
            $original_error_handler = set_error_handler(null);
            
            // Suppress all error display
            error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED & ~E_USER_NOTICE & ~E_USER_WARNING & ~E_USER_DEPRECATED);
            ini_set('display_errors', '0');
            ini_set('display_startup_errors', '0');
            
            // Custom error handler that completely swallows notices/warnings
            set_error_handler(function($errno, $errstr, $errfile, $errline) {
                // Suppress all notices, warnings, and deprecation notices
                if ($errno === E_NOTICE || $errno === E_WARNING || $errno === E_DEPRECATED || 
                    $errno === E_USER_NOTICE || $errno === E_USER_WARNING || $errno === E_USER_DEPRECATED) {
                    return true; // Suppress the error completely
                }
                return false; // Let fatal errors through
            }, E_ALL);
            
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
                
                // Restore error reporting
                error_reporting($original_error_reporting);
                ini_set('display_errors', $original_display_errors);
                if ($original_error_handler !== null) {
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
                
                // Restore error reporting
                error_reporting($original_error_reporting);
                ini_set('display_errors', $original_display_errors);
                if ($original_error_handler !== null) {
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

// ✅ Admin-Ajax handler for Laravel API proxy (replaces REST API)
// This hides the endpoint from public view in Network tab
function heytrisha_ajax_query_handler() {
    // CRITICAL: Suppress errors and start output buffering
    $original_error_reporting = error_reporting();
    $original_display_errors = ini_get('display_errors');
    
    // Suppress all error display
    error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED & ~E_USER_NOTICE & ~E_USER_WARNING & ~E_USER_DEPRECATED);
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    
    // Custom error handler
    set_error_handler(function($errno, $errstr, $errfile, $errline) {
        if ($errno === E_NOTICE || $errno === E_WARNING || $errno === E_DEPRECATED || 
            $errno === E_USER_NOTICE || $errno === E_USER_WARNING || $errno === E_USER_DEPRECATED) {
            return true;
        }
        return false;
    }, E_ALL);
    
    // Clean existing buffers and start fresh
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    ob_start();
    
    try {
        // ✅ Get the request body (prioritize POST data for admin-ajax.php, fallback to JSON)
        $request_data = array();
        
        // First try POST data (standard WordPress AJAX) - sanitize all inputs
        if (!empty($_POST)) {
            // Sanitize each field individually
            if (isset($_POST['query'])) {
                $request_data['query'] = sanitize_text_field(wp_unslash($_POST['query']));
            }
            if (isset($_POST['endpoint'])) {
                $request_data['endpoint'] = sanitize_text_field(wp_unslash($_POST['endpoint']));
            }
            if (isset($_POST['confirmed'])) {
                $request_data['confirmed'] = filter_var(wp_unslash($_POST['confirmed']), FILTER_VALIDATE_BOOLEAN);
            }
            if (isset($_POST['action'])) {
                $request_data['action'] = sanitize_key($_POST['action']);
            }
            
            // If confirmation_data is a JSON string, decode and validate it
            if (isset($_POST['confirmation_data']) && is_string($_POST['confirmation_data'])) {
                $decoded = json_decode(wp_unslash($_POST['confirmation_data']), true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    // Sanitize decoded confirmation data
                    $request_data['confirmation_data'] = heytrisha_sanitize_confirmation_data($decoded);
                }
            }
        } else {
            // Fallback: Try JSON body (for backward compatibility)
            $json_body = file_get_contents('php://input');
            if (!empty($json_body)) {
                $decoded = json_decode($json_body, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    // Sanitize each field
                    if (isset($decoded['query'])) {
                        $request_data['query'] = sanitize_text_field($decoded['query']);
                    }
                    if (isset($decoded['endpoint'])) {
                        $request_data['endpoint'] = sanitize_text_field($decoded['endpoint']);
                    }
                    if (isset($decoded['confirmed'])) {
                        $request_data['confirmed'] = filter_var($decoded['confirmed'], FILTER_VALIDATE_BOOLEAN);
                    }
                    if (isset($decoded['confirmation_data']) && is_array($decoded['confirmation_data'])) {
                        $request_data['confirmation_data'] = heytrisha_sanitize_confirmation_data($decoded['confirmation_data']);
                    }
                }
            }
        }
        
        // ✅ Validate query exists before proceeding
        if (empty($request_data['query']) || !is_string($request_data['query']) || trim($request_data['query']) === '') {
            // Return error response (no sensitive data logging)
            wp_send_json(array(
                'success' => false,
                'message' => 'Please provide a valid query.'
            ));
            return;
        }
        
        // Create a WP_REST_Request compatible object
        $request = new stdClass();
        $request->endpoint = isset($request_data['endpoint']) ? $request_data['endpoint'] : 'query';
        
        // Store the sanitized request body
        $request->body = $request_data;
        
        // For compatibility with the proxy function, add these as direct properties
        $request->query = $request_data['query'];
        if (isset($request_data['confirmed'])) {
            $request->confirmed = $request_data['confirmed'];
        }
        if (isset($request_data['confirmation_data'])) {
            $request->confirmation_data = $request_data['confirmation_data'];
        }
        
        // ✅ Final validation - ensure query is set
        if (empty($request->query)) {
            wp_send_json(array(
                'success' => false,
                'message' => 'Query parameter is missing.'
            ));
            return;
        }
        
        // Inject WordPress configuration as HTTP headers for Laravel
        heytrisha_inject_credentials_as_headers();
        
        // Call the proxy function
        $result = heytrisha_proxy_laravel_api($request);
        
        // Clean all buffers
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        
        // Restore error reporting
        error_reporting($original_error_reporting);
        ini_set('display_errors', $original_display_errors);
        restore_error_handler();
        
        // Return JSON response
        wp_send_json($result);
        
    } catch (Exception $e) {
        // Clean buffers on error
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        
        // Restore error reporting
        error_reporting($original_error_reporting);
        ini_set('display_errors', $original_display_errors);
        restore_error_handler();
        
        wp_send_json_error(array(
            'message' => 'Request failed: ' . $e->getMessage()
        ));
    } catch (Throwable $e) {
        // Clean buffers on error
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        
        // Restore error reporting
        error_reporting($original_error_reporting);
        ini_set('display_errors', $original_display_errors);
        restore_error_handler();
        
        wp_send_json_error(array(
            'message' => 'Request failed: ' . $e->getMessage()
        ));
    }
}

// Register for both logged-in and non-logged-in users
add_action('wp_ajax_heytrisha_query', 'heytrisha_ajax_query_handler');
add_action('wp_ajax_nopriv_heytrisha_query', 'heytrisha_ajax_query_handler');

// ✅ Register Chat REST API endpoints
function heytrisha_register_chat_rest_routes() {
    // Ensure database class is loaded
    if (!class_exists('HeyTrisha_Database')) {
        require_once HEYTRISHA_PLUGIN_DIR . 'includes/class-heytrisha-database.php';
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
                error_log('HeyTrisha REST API Exception: ' . $e->getMessage());
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
                error_log('HeyTrisha REST API: Failed to create chat - ' . $error_msg);
                return new WP_Error('creation_failed', 'Failed to create chat: ' . $error_msg, array('status' => 500));
            } catch (Exception $e) {
                error_log('HeyTrisha REST API Exception: ' . $e->getMessage());
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
                return rest_ensure_response(end($messages));
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
//         wp_enqueue_script('react', HEYTRISHA_PLUGIN_URL . 'assets/js/vendor/react.production.min.js', [], '18.0', true);
//         wp_enqueue_script('react-dom', HEYTRISHA_PLUGIN_URL . 'assets/js/vendor/react-dom.production.min.js', ['react'], '18.0', true);

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





