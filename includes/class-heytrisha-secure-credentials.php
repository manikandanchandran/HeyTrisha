<?php
/**
 * Hey Trisha - Secure Credentials Manager
 * 
 * Manages encrypted credentials in a separate database table
 * Uses WordPress encryption with a secret key for security
 * 
 * @package HeyTrisha
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class HeyTrisha_Secure_Credentials {
    
    private static $instance = null;
    private $encryption_key = null;
    
    // Credential keys
    const KEY_OPENAI_API = 'openai_api_key';
    const KEY_DB_PASSWORD = 'db_password';
    const KEY_WP_API_PASSWORD = 'wordpress_api_password';
    const KEY_WC_CONSUMER_SECRET = 'woocommerce_consumer_secret';
    const KEY_SHARED_TOKEN = 'shared_token';
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Initialize encryption key
        $this->encryption_key = $this->get_encryption_key();
    }
    
    /**
     * Create secure credentials table
     */
    public static function create_table() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        $table_name = $wpdb->prefix . 'heytrisha_credentials';
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            credential_key varchar(100) NOT NULL,
            credential_value longtext NOT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY credential_key (credential_key)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // Log table creation
        error_log('✅ HeyTrisha: Secure credentials table created/verified');
    }
    
    /**
     * Get or generate encryption key
     */
    private function get_encryption_key() {
        // Try to get existing key
        $key = get_option('heytrisha_encryption_key');
        
        if (empty($key)) {
            // Generate new key using WordPress salts
            $key = wp_hash(AUTH_KEY . SECURE_AUTH_KEY . LOGGED_IN_KEY . NONCE_KEY);
            update_option('heytrisha_encryption_key', $key, false); // autoload = false for security
            error_log('✅ HeyTrisha: Generated new encryption key');
        }
        
        return $key;
    }
    
    /**
     * Encrypt a value
     */
    private function encrypt($value) {
        if (empty($value)) {
            return '';
        }
        
        // Use OpenSSL encryption (AES-256-CBC)
        $method = 'AES-256-CBC';
        $key = substr(hash('sha256', $this->encryption_key), 0, 32);
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length($method));
        
        $encrypted = openssl_encrypt($value, $method, $key, 0, $iv);
        
        // Combine IV and encrypted data (we need IV for decryption)
        return base64_encode($iv . '::' . $encrypted);
    }
    
    /**
     * Decrypt a value
     */
    private function decrypt($encrypted_value) {
        if (empty($encrypted_value)) {
            return '';
        }
        
        try {
            $method = 'AES-256-CBC';
            $key = substr(hash('sha256', $this->encryption_key), 0, 32);
            
            // Decode and split IV and encrypted data
            $decoded = base64_decode($encrypted_value);
            $parts = explode('::', $decoded, 2);
            
            if (count($parts) !== 2) {
                error_log('⚠️ HeyTrisha: Invalid encrypted value format');
                return '';
            }
            
            list($iv, $encrypted) = $parts;
            
            $decrypted = openssl_decrypt($encrypted, $method, $key, 0, $iv);
            
            return $decrypted;
        } catch (Exception $e) {
            error_log('❌ HeyTrisha: Decryption error - ' . $e->getMessage());
            return '';
        }
    }
    
    /**
     * Store encrypted credential
     */
    public function set_credential($key, $value) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'heytrisha_credentials';
        
        // Encrypt the value
        $encrypted_value = $this->encrypt($value);
        
        // Check if credential exists
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table_name WHERE credential_key = %s",
            $key
        ));
        
        if ($existing) {
            // Update existing
            $result = $wpdb->update(
                $table_name,
                array('credential_value' => $encrypted_value),
                array('credential_key' => $key),
                array('%s'),
                array('%s')
            );
        } else {
            // Insert new
            $result = $wpdb->insert(
                $table_name,
                array(
                    'credential_key' => $key,
                    'credential_value' => $encrypted_value
                ),
                array('%s', '%s')
            );
        }
        
        if ($result === false) {
            error_log('❌ HeyTrisha: Failed to store credential - ' . $wpdb->last_error);
            return false;
        }
        
        error_log('✅ HeyTrisha: Credential stored securely - ' . $key);
        return true;
    }
    
    /**
     * Get decrypted credential
     */
    public function get_credential($key, $default = '') {
        global $wpdb;
        $table_name = $wpdb->prefix . 'heytrisha_credentials';
        
        $encrypted_value = $wpdb->get_var($wpdb->prepare(
            "SELECT credential_value FROM $table_name WHERE credential_key = %s",
            $key
        ));
        
        if (empty($encrypted_value)) {
            return $default;
        }
        
        // Decrypt and return
        return $this->decrypt($encrypted_value);
    }
    
    /**
     * Delete credential
     */
    public function delete_credential($key) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'heytrisha_credentials';
        
        $result = $wpdb->delete(
            $table_name,
            array('credential_key' => $key),
            array('%s')
        );
        
        return $result !== false;
    }
    
    /**
     * Migrate credentials from wp_options to secure table
     */
    public static function migrate_from_options() {
        $instance = self::get_instance();
        
        // Ensure table exists
        self::create_table();
        
        $credentials_map = array(
            'heytrisha_openai_api_key' => self::KEY_OPENAI_API,
            'heytrisha_db_password' => self::KEY_DB_PASSWORD,
            'heytrisha_wordpress_api_password' => self::KEY_WP_API_PASSWORD,
            'heytrisha_woocommerce_consumer_secret' => self::KEY_WC_CONSUMER_SECRET,
            'heytrisha_shared_token' => self::KEY_SHARED_TOKEN,
        );
        
        $migrated = 0;
        foreach ($credentials_map as $option_name => $credential_key) {
            // Get from wp_options
            $value = get_option($option_name);
            
            if (!empty($value)) {
                // Store in secure table
                $result = $instance->set_credential($credential_key, $value);
                
                if ($result) {
                    $migrated++;
                    error_log("✅ HeyTrisha: Migrated {$option_name} to secure storage");
                    
                    // Delete from wp_options for security
                    delete_option($option_name);
                }
            }
        }
        
        error_log("✅ HeyTrisha: Migration complete - {$migrated} credentials migrated");
        return $migrated;
    }
    
    /**
     * Verify table and credentials exist
     */
    public static function verify_setup() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'heytrisha_credentials';
        
        // Check if table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
        
        if (!$table_exists) {
            error_log('⚠️ HeyTrisha: Secure credentials table does not exist');
            return false;
        }
        
        // Check if credentials are stored
        $count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        
        error_log("✅ HeyTrisha: Secure credentials table exists with {$count} credentials");
        return true;
    }
}



