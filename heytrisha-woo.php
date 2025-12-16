<?php
/*
Plugin Name: Hey Trisha Woocommerce Chatbot
Description: A chatbot plugin using React and Laravel backend.
Version: 1.0
Author: WIncredible Technologies
*/

// ✅ Inject the chatbot div into the admin footer
// function add_chatbot_widget_to_admin_footer() {
//     if (current_user_can('administrator')) {
//         echo '<div id="chatbot-root"></div>';
//         echo '<script>console.log("✅ Chatbot root div added to admin footer");</script>';
//     }
// }
// add_action('admin_footer', 'add_chatbot_widget_to_admin_footer');

function heytrisha_enqueue_chatbot() {
    if (current_user_can('administrator')) {
        echo '<div id="chatbot-root"></div>'; // Create chatbot container

        // ✅ Load React from CDN
        echo '
        <script src="https://unpkg.com/react@18/umd/react.production.min.js"></script>
        <script src="https://unpkg.com/react-dom@18/umd/react-dom.production.min.js"></script>';

        // ✅ Pass plugin URL to JavaScript
        $plugin_url = plugin_dir_url(__FILE__);
        echo '<script>window.heytrishaPluginUrl = "' . esc_js($plugin_url) . '";</script>';

        // ✅ Load Chatbot CSS
        wp_enqueue_style('heytrisha-chatbot-css', plugin_dir_url(__FILE__) . 'assets/css/chatbot.css', [], '1.0');

        // ✅ Load Chatbot JavaScript (without Webpack)
        echo '<script src="' . plugin_dir_url(__FILE__) . 'assets/js/chatbot.js"></script>';
    }
}
add_action('admin_footer', 'heytrisha_enqueue_chatbot');








// ✅ Admin Settings Page (OpenAI & Database Credentials)
function heytrisha_register_admin_menu() {
    add_menu_page(
        'HeyTrisha Settings',
        'HeyTrisha Chatbot',
        'manage_options',
        'heytrisha-chatbot-settings',
        'heytrisha_render_settings_page',
        'dashicons-admin-generic',
        81
    );
}
add_action('admin_menu', 'heytrisha_register_admin_menu');

// ✅ Include Server Manager and Dependency Installer
require_once plugin_dir_path(__FILE__) . 'includes/class-heytrisha-server-manager.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-heytrisha-dependency-installer.php';

// ✅ Create default options on activation and install dependencies
function heytrisha_activate_plugin() {
    // Create default options
    add_option('heytrisha_openai_api_key', '');
    add_option('heytrisha_db_host', '127.0.0.1');
    add_option('heytrisha_db_port', '3306');
    add_option('heytrisha_db_name', '');
    add_option('heytrisha_db_user', '');
    add_option('heytrisha_db_password', '');
    add_option('heytrisha_wordpress_api_url', get_site_url());
    add_option('heytrisha_wordpress_api_user', '');
    add_option('heytrisha_wordpress_api_password', '');
    if (!get_option('heytrisha_shared_token')) {
        add_option('heytrisha_shared_token', wp_generate_password(32, false, false));
    }
    
    // ✅ Install Laravel and React dependencies automatically
    $installer = new HeyTrisha_Dependency_Installer();
    $installation_result = $installer->install_all_dependencies();
    
    // Store installation result for display
    update_option('heytrisha_installation_result', $installation_result);
    update_option('heytrisha_installation_time', current_time('mysql'));
    
    // ✅ Auto-start Laravel server on activation (non-blocking)
    // Use wp_schedule_single_event to start server asynchronously
    if (!wp_next_scheduled('heytrisha_start_server_on_activation')) {
        wp_schedule_single_event(time() + 5, 'heytrisha_start_server_on_activation'); // Delay to allow dependencies to install
    }
}
register_activation_hook(__FILE__, 'heytrisha_activate_plugin');

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
    $server_manager = new HeyTrisha_Server_Manager();
    if ($server_manager->is_server_running()) {
        $server_manager->stop_server();
    }
}
register_deactivation_hook(__FILE__, 'heytrisha_deactivate_plugin');

// ✅ Save settings
function heytrisha_handle_settings_save() {
    if (!current_user_can('manage_options')) {
        return;
    }

    if (!isset($_POST['heytrisha_settings_nonce']) || !wp_verify_nonce($_POST['heytrisha_settings_nonce'], 'heytrisha_save_settings')) {
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
    $shared_token = isset($_POST['heytrisha_shared_token']) ? sanitize_text_field(wp_unslash($_POST['heytrisha_shared_token'])) : '';

    update_option('heytrisha_openai_api_key', $openai_api_key);
    update_option('heytrisha_db_host', $db_host);
    update_option('heytrisha_db_port', $db_port);
    update_option('heytrisha_db_name', $db_name);
    update_option('heytrisha_db_user', $db_user);
    update_option('heytrisha_db_password', $db_password);
    update_option('heytrisha_wordpress_api_url', $wordpress_api_url);
    update_option('heytrisha_wordpress_api_user', $wordpress_api_user);
    update_option('heytrisha_wordpress_api_password', $wordpress_api_password);
    if (!empty($shared_token)) {
        update_option('heytrisha_shared_token', $shared_token);
    }

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

    $openai_api_key = get_option('heytrisha_openai_api_key', '');
    $db_host = get_option('heytrisha_db_host', '127.0.0.1');
    $db_port = get_option('heytrisha_db_port', '3306');
    $db_name = get_option('heytrisha_db_name', '');
    $db_user = get_option('heytrisha_db_user', '');
    $db_password = get_option('heytrisha_db_password', '');
    $wordpress_api_url = get_option('heytrisha_wordpress_api_url', get_site_url());
    $wordpress_api_user = get_option('heytrisha_wordpress_api_user', '');
    $wordpress_api_password = get_option('heytrisha_wordpress_api_password', '');
    $shared_token = get_option('heytrisha_shared_token', '');

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
    echo '<li><strong>Laravel App Key:</strong> ' . ($install_status['laravel_key_exists'] ? '<span style="color: #00a32a;">✓ Generated</span>' : '<span style="color: #d63638;">✗ Not Generated</span>') . '</li>';
    echo '<li><strong>React Dependencies:</strong> ' . ($install_status['react_installed'] ? '<span style="color: #00a32a;">✓ Installed</span>' : '<span style="color: #d63638;">✗ Not Installed (Optional - React loaded from CDN)</span>') . '</li>';
    echo '<li><strong>Composer Available:</strong> ' . ($install_status['composer_available'] ? '<span style="color: #00a32a;">✓ Yes</span>' : '<span style="color: #d63638;">✗ No</span>') . '</li>';
    echo '<li><strong>npm Available:</strong> ' . ($install_status['npm_available'] ? '<span style="color: #00a32a;">✓ Yes</span>' : '<span style="color: #d63638;">✗ No (Optional)</span>') . '</li>';
    echo '</ul>';
    
    echo '<p>';
    echo '<button type="button" class="button button-secondary" onclick="heytrishaReinstallDependencies()">🔄 Reinstall Dependencies</button>';
    echo '</p>';
    
    echo '</div>';

    // ✅ Server Management Section
    echo '<h2>API Server Management</h2>';
    $server_manager = new HeyTrisha_Server_Manager();
    $server_status = $server_manager->get_server_status();
    
    echo '<div class="heytrisha-server-status" style="background: #f0f0f1; padding: 15px; border-radius: 4px; margin-bottom: 15px;">';
    echo '<h3 style="margin-top: 0;">Server Status</h3>';
    echo '<p><strong>Status:</strong> <span style="color: ' . ($server_status['running'] ? '#00a32a' : '#d63638') . '; font-weight: bold;">' . ($server_status['running'] ? '🟢 Running' : '🔴 Stopped') . '</span></p>';
    echo '<p><strong>Port:</strong> ' . esc_html($server_status['port']) . '</p>';
    echo '<p><strong>URL:</strong> <a href="' . esc_url($server_status['url']) . '" target="_blank">' . esc_html($server_status['url']) . '</a></p>';
    if ($server_status['pid']) {
        echo '<p><strong>Process ID:</strong> ' . esc_html($server_status['pid']) . '</p>';
    }
    echo '</div>';
    
    echo '<p>';
    if ($server_status['running']) {
        echo '<button type="button" class="button button-secondary" onclick="heytrishaRestartServer()">🔄 Restart Server</button> ';
        echo '<button type="button" class="button button-secondary" onclick="heytrishaStopServer()">⏹️ Stop Server</button>';
    } else {
        echo '<button type="button" class="button button-primary" onclick="heytrishaStartServer()">▶️ Start Server</button>';
    }
    echo '</p>';
    
    echo '<script>
    function heytrishaStartServer() {
        if (!confirm("Start the Laravel API server?")) return;
        jQuery.post(ajaxurl, {
            action: "heytrisha_start_server",
            nonce: "' . wp_create_nonce('heytrisha_server_action') . '"
        }, function(response) {
            alert(response.data.message);
            if (response.success) {
                location.reload();
            }
        });
    }
    function heytrishaStopServer() {
        if (!confirm("Stop the Laravel API server?")) return;
        jQuery.post(ajaxurl, {
            action: "heytrisha_stop_server",
            nonce: "' . wp_create_nonce('heytrisha_server_action') . '"
        }, function(response) {
            alert(response.data.message);
            location.reload();
        });
    }
    function heytrishaRestartServer() {
        if (!confirm("Restart the Laravel API server?")) return;
        jQuery.post(ajaxurl, {
            action: "heytrisha_restart_server",
            nonce: "' . wp_create_nonce('heytrisha_server_action') . '"
        }, function(response) {
            alert(response.data.message);
            if (response.success) {
                location.reload();
            }
        });
    }
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
    </script>';

    submit_button('Save Changes');
    echo '</form>';
    echo '</div>';
}

// ✅ Read-only REST endpoint to provide stored credentials to backend (admin-only)
function heytrisha_register_rest_routes() {
    register_rest_route('heytrisha/v1', '/config', array(
        'methods' => 'GET',
        'callback' => function () {
            $provided = isset($_GET['token']) ? sanitize_text_field(wp_unslash($_GET['token'])) : '';
            $expected = get_option('heytrisha_shared_token', '');
            if (empty($provided) || empty($expected) || !hash_equals($expected, $provided)) {
                return new WP_Error('forbidden', 'Invalid or missing token.', array('status' => 403));
            }

            return array(
                'openai_api_key' => get_option('heytrisha_openai_api_key', ''),
                'database' => array(
                    'host' => get_option('heytrisha_db_host', ''),
                    'port' => get_option('heytrisha_db_port', ''),
                    'name' => get_option('heytrisha_db_name', ''),
                    'user' => get_option('heytrisha_db_user', ''),
                    'password' => get_option('heytrisha_db_password', ''),
                ),
                'wordpress_api' => array(
                    'url' => get_option('heytrisha_wordpress_api_url', get_site_url()),
                    'user' => get_option('heytrisha_wordpress_api_user', ''),
                    'password' => get_option('heytrisha_wordpress_api_password', ''),
                ),
            );
        },
        'permission_callback' => '__return_true'
    ));
}
add_action('rest_api_init', 'heytrisha_register_rest_routes');

// function heytrisha_enqueue_chatbot_scripts() {
//     // Enqueue React and ReactDOM only for admin users
//     if (current_user_can('administrator')) {

//         // Enqueue React and ReactDOM from CDN (for admin only)
//         wp_enqueue_script('react', 'https://unpkg.com/react@17/umd/react.production.min.js', [], null, true);
//         wp_enqueue_script('react-dom', 'https://unpkg.com/react-dom@17/umd/react-dom.production.min.js', ['react'], null, true);

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





