<?php
/*
Plugin Name: Hey Trisha Woocommerce Chatbot
Description: A chatbot plugin using React and Laravel backend.
Version: 1.0
Author: Manikandan Chandran
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

// ✅ Create default options on activation
function heytrisha_activate_plugin() {
    add_option('heytrisha_openai_api_key', '');
    add_option('heytrisha_db_host', '127.0.0.1');
    add_option('heytrisha_db_port', '3306');
    add_option('heytrisha_db_name', '');
    add_option('heytrisha_db_user', '');
    add_option('heytrisha_db_password', '');
    if (!get_option('heytrisha_shared_token')) {
        add_option('heytrisha_shared_token', wp_generate_password(32, false, false));
    }
}
register_activation_hook(__FILE__, 'heytrisha_activate_plugin');

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
    $shared_token = isset($_POST['heytrisha_shared_token']) ? sanitize_text_field(wp_unslash($_POST['heytrisha_shared_token'])) : '';

    update_option('heytrisha_openai_api_key', $openai_api_key);
    update_option('heytrisha_db_host', $db_host);
    update_option('heytrisha_db_port', $db_port);
    update_option('heytrisha_db_name', $db_name);
    update_option('heytrisha_db_user', $db_user);
    update_option('heytrisha_db_password', $db_password);
    if (!empty($shared_token)) {
        update_option('heytrisha_shared_token', $shared_token);
    }

    add_settings_error('heytrisha_settings', 'settings_updated', 'Settings saved.', 'updated');
}
add_action('admin_init', 'heytrisha_handle_settings_save');

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

    echo '<h2>Integration</h2>';
    echo '<p>This token allows your Laravel API to fetch credentials from the WordPress site securely.</p>';
    echo '<table class="form-table"><tbody>';
    echo '<tr><th scope="row"><label for="heytrisha_shared_token">Shared Access Token</label></th>';
    echo '<td><input type="text" id="heytrisha_shared_token" name="heytrisha_shared_token" value="' . esc_attr($shared_token) . '" class="regular-text" /></td></tr>';
    echo '</tbody></table>';

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





