<?php
/**
 * Database handler for Hey Trisha Chat system
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Note: This class uses direct database queries because we're working with
 * custom plugin tables, not WordPress core tables. Direct $wpdb usage is
 * the standard and recommended approach for custom tables.
 * 
 * phpcs:disable WordPress.DB.DirectDatabaseQuery -- Custom plugin tables require direct database access
 */
class HeyTrisha_Database {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Create database tables on plugin activation
     */
    public static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        $table_chats = $wpdb->prefix . 'heytrisha_chats';
        $table_messages = $wpdb->prefix . 'heytrisha_messages';
        
        // Create chats table
        $sql_chats = "CREATE TABLE IF NOT EXISTS $table_chats (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            title varchar(255) NOT NULL DEFAULT 'New Chat',
            user_id bigint(20) UNSIGNED NOT NULL,
            is_archived tinyint(1) NOT NULL DEFAULT 0,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY is_archived (is_archived),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        // Create messages table (without foreign key for compatibility)
        $sql_messages = "CREATE TABLE IF NOT EXISTS $table_messages (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            chat_id bigint(20) UNSIGNED NOT NULL,
            role varchar(20) NOT NULL COMMENT 'user or assistant',
            content longtext NOT NULL,
            metadata longtext DEFAULT NULL COMMENT 'JSON data for SQL queries, etc.',
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY chat_id (chat_id),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_chats);
        dbDelta($sql_messages);
    }
    
    /**
     * Get all chats for current user
     */
    public function get_chats($archived = false, $limit = 50, $offset = 0) {
        global $wpdb;
        
        // Ensure tables exist
        self::create_tables();
        
        $table_chats = $wpdb->prefix . 'heytrisha_chats';
        $user_id = get_current_user_id();
        
        $where = $wpdb->prepare("user_id = %d AND is_archived = %d", $user_id, $archived ? 1 : 0);
        
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name ($table_chats) and WHERE clause ($where) use $wpdb->prefix (sanitized by WordPress core) and prepared user_id
        $chats = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_chats WHERE $where ORDER BY updated_at DESC LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

            $limit,
            $offset
        ));
        
        if ($wpdb->last_error) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug logging for development
            error_log('HeyTrisha: get_chats error - ' . $wpdb->last_error);
            return array();
        }
        
        return $chats ? $chats : array();
    }
    
    /**
     * Get single chat by ID
     */
    public function get_chat($chat_id) {
        global $wpdb;
        $table_chats = $wpdb->prefix . 'heytrisha_chats';
        $user_id = get_current_user_id();
        
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name ($table_chats) uses $wpdb->prefix which is sanitized by WordPress core. Table names cannot be parameterized in SQL. $user_id comes from get_current_user_id() (WordPress core function) and is properly parameterized with %d placeholder.
        $chat = $wpdb->get_row($wpdb->prepare(
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name ($table_chats) cannot be parameterized
            "SELECT * FROM $table_chats 
            WHERE id = %d AND user_id = %d",
            $chat_id,
            $user_id
        ));
        
        return $chat;
    }
    
    /**
     * Create new chat
     */
    public function create_chat($title = 'New Chat') {
        global $wpdb;
        $table_chats = $wpdb->prefix . 'heytrisha_chats';
        $user_id = get_current_user_id();
        
        // Ensure tables exist
        self::create_tables();
        
        $result = $wpdb->insert(
            $table_chats,
            array(
                'title' => sanitize_text_field($title),
                'user_id' => $user_id,
                'is_archived' => 0
            ),
            array('%s', '%d', '%d')
        );
        
        if ($result === false) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Production error tracking for database operations
            error_log('HeyTrisha: Failed to create chat. Error: ' . $wpdb->last_error);
            return false;
        }
        
        return $wpdb->insert_id;
    }
    
    /**
     * Update chat
     */
    public function update_chat($chat_id, $data) {
        global $wpdb;
        $table_chats = $wpdb->prefix . 'heytrisha_chats';
        $user_id = get_current_user_id();
        
        $allowed_fields = array('title', 'is_archived');
        $update_data = array();
        $format = array();
        
        foreach ($allowed_fields as $field) {
            if (isset($data[$field])) {
                $update_data[$field] = $field === 'title' ? sanitize_text_field($data[$field]) : intval($data[$field]);
                $format[] = $field === 'title' ? '%s' : '%d';
            }
        }
        
        // If no data to update, just update the timestamp
        if (empty($update_data)) {
            $update_data = array('updated_at' => current_time('mysql'));
            $format = array('%s');
        }
        
        $result = $wpdb->update(
            $table_chats,
            $update_data,
            array('id' => $chat_id, 'user_id' => $user_id),
            $format,
            array('%d', '%d')
        );
        
        return $result !== false;
    }
    
    /**
     * Delete chat
     * 
     * @param int $chat_id Chat ID to delete
     * @return bool True on success, false on failure
     */
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Database class method. Nonce verification handled by REST API endpoints (permission_callback + X-WP-Nonce).
    public function delete_chat($chat_id) {
        global $wpdb;
        $table_chats = $wpdb->prefix . 'heytrisha_chats';
        $user_id = get_current_user_id();
        
        $result = $wpdb->delete(
            $table_chats,
            array('id' => $chat_id, 'user_id' => $user_id),
            array('%d', '%d')
        );
        
        return $result !== false;
    }
    
    /**
     * Get messages for a chat
     */
    public function get_messages($chat_id, $limit = 100) {
        global $wpdb;
        $table_messages = $wpdb->prefix . 'heytrisha_messages';
        $table_chats = $wpdb->prefix . 'heytrisha_chats';
        $user_id = get_current_user_id();
        
        // Verify chat belongs to user
        $chat = $this->get_chat($chat_id);
        if (!$chat) {
            return array();
        }
        
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name ($table_messages) uses $wpdb->prefix which is sanitized by WordPress core. Table names cannot be parameterized in SQL. $chat_id and $limit are properly parameterized with %d placeholders.
        $messages = $wpdb->get_results($wpdb->prepare(
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name ($table_messages) cannot be parameterized
            "SELECT * FROM $table_messages 
            WHERE chat_id = %d 
            ORDER BY created_at ASC 
            LIMIT %d",
            $chat_id,
            $limit
        ));
        
        return $messages;
    }
    
    /**
     * Add message to chat
     */
    public function add_message($chat_id, $role, $content, $metadata = null) {
        global $wpdb;
        $table_messages = $wpdb->prefix . 'heytrisha_messages';
        
        // Verify chat belongs to user
        $chat = $this->get_chat($chat_id);
        if (!$chat) {
            return false;
        }
        
        $data = array(
            'chat_id' => $chat_id,
            'role' => sanitize_text_field($role),
            'content' => wp_kses_post($content),
            'metadata' => $metadata ? json_encode($metadata) : null
        );
        
        $format = array('%d', '%s', '%s', '%s');
        
        $result = $wpdb->insert($table_messages, $data, $format);
        
        // Update chat's updated_at timestamp
        if ($result) {
            $this->update_chat($chat_id, array()); // Empty update just to trigger updated_at
        }
        
        return $result ? $wpdb->insert_id : false;
    }
    
    /**
     * Archive/Unarchive chat
     */
    public function archive_chat($chat_id, $archive = true) {
        return $this->update_chat($chat_id, array('is_archived' => $archive ? 1 : 0));
    }
}
// phpcs:enable WordPress.DB.DirectDatabaseQuery

