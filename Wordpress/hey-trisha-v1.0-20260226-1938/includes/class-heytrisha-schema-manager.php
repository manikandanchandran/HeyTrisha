<?php
/**
 * HeyTrisha Schema Manager
 * Handles specification file uploads, NL ingestion, storage, and retrieval.
 *
 * Upload flow:
 *  1. Accept any text-based file (.txt, .md, .csv, .log, .sql, .json).
 *  2. Always save the raw text as specification-raw.txt.
 *  3. For structured files (JSON/SQL/Table: template) also parse into database-schema.json.
 *  4. POST raw text to the API /api/specification/ingest endpoint.
 *  5. On success store the returned allowlist + version; enable specification mode.
 */

class HeyTrisha_Schema_Manager {

    private static $instance = null;
    private $schema_dir;
    private $schema_file      = 'database-schema.json';
    private $raw_spec_file    = 'specification-raw.txt';

    // Allowed file extensions for upload
    private $allowed_extensions = array( 'json', 'txt', 'sql', 'md', 'csv', 'log' );

    public static function get_instance() {
        if ( self::$instance === null ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        $upload_dir        = wp_upload_dir();
        $this->schema_dir  = $upload_dir['basedir'] . '/heytrisha-schema/';

        if ( ! file_exists( $this->schema_dir ) ) {
            wp_mkdir_p( $this->schema_dir );
        }

        add_action( 'wp_ajax_heytrisha_upload_schema',   array( $this, 'handle_schema_upload' ) );
        add_action( 'wp_ajax_heytrisha_get_schema',      array( $this, 'handle_get_schema' ) );
        add_action( 'wp_ajax_heytrisha_delete_schema',   array( $this, 'handle_delete_schema' ) );
        add_action( 'wp_ajax_heytrisha_generate_schema', array( $this, 'handle_generate_schema' ) );
    }

    // -------------------------------------------------------------------------
    // Upload handler
    // -------------------------------------------------------------------------

    /**
     * Handle specification/schema file upload via AJAX.
     */
    public function handle_schema_upload() {
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'heytrisha_schema_nonce' ) ) {
            wp_send_json_error( array( 'message' => 'Security check failed' ) );
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
        }

        if ( ! isset( $_FILES['schema_file'] ) ) {
            wp_send_json_error( array( 'message' => 'No file uploaded' ) );
        }

        $file = $_FILES['schema_file']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput

        // 5 MB limit
        if ( $file['size'] > 5242880 ) {
            wp_send_json_error( array( 'message' => 'File too large (max 5MB)' ) );
        }

        $extension = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );
        if ( ! in_array( $extension, $this->allowed_extensions, true ) ) {
            wp_send_json_error( array( 'message' => 'Invalid file type. Allowed: ' . implode( ', ', array_map( 'strtoupper', $this->allowed_extensions ) ) ) );
        }

        // Read content
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
        $content = file_get_contents( $file['tmp_name'] );
        if ( $content === false || ! $this->is_valid_text_content( $content ) ) {
            wp_send_json_error( array( 'message' => 'File does not appear to be a valid text file' ) );
        }

        // Phase 1: always save raw specification text
        $this->save_raw_specification( $content );
        $schema_revision = heytrisha_bump_schema_revision();

        // Phase 2: attempt structured parse (JSON / SQL / Table: template)
        $schema        = $this->parse_schema( $content, $extension );
        $tables_count  = 0;

        if ( $schema ) {
            $this->save_schema( $schema );
            $tables_count = count( $schema );
        }

        // Phase 3: call API ingest to process natural-language content
        $ingest_result = $this->call_api_ingest( $content );

        if ( is_wp_error( $ingest_result ) ) {
            // Ingest failed — save what we have but surface the error
            $error_msg = $ingest_result->get_error_message();
            update_option( 'heytrisha_spec_ingest_error', $error_msg );
            delete_option( 'heytrisha_specification_active' );

            wp_send_json_success( array(
                'message'         => $schema
                    ? 'File saved and parsed (' . $tables_count . ' tables). AI indexing failed: ' . $error_msg . '. Chat constraints will not apply until indexing succeeds.'
                    : 'File saved as specification. AI indexing failed: ' . $error_msg . '. Chat constraints will not apply until indexing succeeds.',
                'tables'          => $tables_count,
                'ingest_status'   => 'failed',
                'ingest_error'    => $error_msg,
                'schema'          => $schema ?: null,
                'schema_revision' => $schema_revision,
            ) );
            return;
        }

        // Ingest succeeded — store allowlist + activate specification mode
        $allowlist        = $ingest_result['allowlist']         ?? array();
        $version          = $ingest_result['version']           ?? md5( $content );
        $rules_summary    = $ingest_result['rules_summary']     ?? '';

        update_option( 'heytrisha_specification_active',  true );
        update_option( 'heytrisha_spec_allowlist',        wp_json_encode( $allowlist ) );
        update_option( 'heytrisha_spec_version',          $version );
        update_option( 'heytrisha_spec_rules_summary',    $rules_summary );
        update_option( 'heytrisha_spec_upload_date',      current_time( 'mysql' ) );
        delete_option( 'heytrisha_spec_ingest_error' );

        wp_send_json_success( array(
            'message'          => 'Specification uploaded and indexed successfully.',
            'tables'           => $tables_count,
            'ingest_status'    => 'ok',
            'spec_version'     => $version,
            'schema'           => $schema ?: null,
            'schema_revision'  => $schema_revision,
        ) );
    }

    // -------------------------------------------------------------------------
    // API ingest call
    // -------------------------------------------------------------------------

    /**
     * POST raw specification text to the API /api/specification/ingest endpoint.
     *
     * @param string $raw_text
     * @return array|WP_Error  Parsed allowlist data on success, WP_Error on failure.
     */
    private function call_api_ingest( $raw_text ) {
        $api_url = get_option( 'heytrisha_api_url', '' );
        $api_key = $this->get_api_key();

        if ( empty( $api_url ) || empty( $api_key ) ) {
            return new WP_Error( 'no_api', 'HeyTrisha API URL or API key is not configured.' );
        }

        $endpoint = rtrim( $api_url, '/' ) . '/api/specification/ingest';

        $ingest_headers = array_merge(
            array(
                'Authorization' => 'Bearer ' . $api_key,
            ),
            array(
                'Content-Type' => 'application/json',
            )
        );
        if ( class_exists( 'HeyTrisha_Secure_Credentials' ) && function_exists( 'heytrisha_get_credential' ) ) {
            $openai_plugin = heytrisha_get_credential( HeyTrisha_Secure_Credentials::KEY_OPENAI_API, 'heytrisha_openai_api_key', '' );
            if ( $openai_plugin === '' ) {
                $openai_plugin = heytrisha_get_credential( HeyTrisha_Secure_Credentials::KEY_OPENAI_API, 'heytrisha_openai_key', '' );
            }
            if ( $openai_plugin !== '' ) {
                $ingest_headers['X-HeyTrisha-OpenAI-Key'] = $openai_plugin;
            }
        }

        $response = wp_remote_post( $endpoint, array(
            'headers' => $ingest_headers,
            'body'      => wp_json_encode( array(
                'text' => $raw_text,
                'site' => get_site_url(),
            ) ),
            'timeout'   => 90,
            'sslverify' => false,
        ) );

        if ( is_wp_error( $response ) ) {
            return new WP_Error( 'request_failed', 'API request failed: ' . $response->get_error_message() );
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( $code !== 200 ) {
            $msg = isset( $data['message'] ) ? $data['message'] : 'HTTP ' . $code;
            return new WP_Error( 'api_error', $msg );
        }

        if ( ! isset( $data['success'] ) || ! $data['success'] ) {
            $msg = isset( $data['message'] ) ? $data['message'] : 'Unknown error from API';
            return new WP_Error( 'ingest_failed', $msg );
        }

        return $data;
    }

    /**
     * Retrieve stored API key through the secure credentials helper if available.
     */
    private function get_api_key() {
        if ( function_exists( 'heytrisha_get_credential' ) && defined( 'HeyTrisha_Secure_Credentials::KEY_API_TOKEN' ) ) {
            return heytrisha_get_credential( HeyTrisha_Secure_Credentials::KEY_API_TOKEN, 'heytrisha_api_key', '' );
        }
        return get_option( 'heytrisha_api_key', '' );
    }

    // -------------------------------------------------------------------------
    // Raw specification persistence
    // -------------------------------------------------------------------------

    /**
     * Save raw specification text (always, regardless of parse success).
     */
    private function save_raw_specification( $content ) {
        $file_path = $this->schema_dir . $this->raw_spec_file;

        // Ensure writable
        if ( file_exists( $file_path ) ) {
            chmod( $file_path, 0644 );
        }

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
        file_put_contents( $file_path, $content );
        chmod( $file_path, 0444 );
    }

    /**
     * Return the stored raw specification text, or null if none.
     */
    public function get_raw_specification() {
        $file_path = $this->schema_dir . $this->raw_spec_file;

        if ( ! file_exists( $file_path ) ) {
            return null;
        }

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
        $content = file_get_contents( $file_path );
        return $content !== false ? $content : null;
    }

    // -------------------------------------------------------------------------
    // Allowlist access (used by effective-schema function)
    // -------------------------------------------------------------------------

    /**
     * Return the stored specification allowlist, or null if none.
     *
     * @return array|null  Map of table_suffix => [ column, ... ] (suffixes, without WP prefix)
     */
    public function get_spec_allowlist() {
        $json = get_option( 'heytrisha_spec_allowlist', '' );
        if ( empty( $json ) ) {
            return null;
        }
        $data = json_decode( $json, true );
        return ( json_last_error() === JSON_ERROR_NONE && is_array( $data ) ) ? $data : null;
    }

    /**
     * Whether specification mode is active (file uploaded and indexed).
     */
    public function is_specification_active() {
        return (bool) get_option( 'heytrisha_specification_active', false );
    }

    // -------------------------------------------------------------------------
    // Validation helpers
    // -------------------------------------------------------------------------

    /**
     * Basic heuristic: reject binary content (null bytes in first 512 bytes).
     */
    private function is_valid_text_content( $content ) {
        return strpos( substr( $content, 0, 512 ), "\0" ) === false;
    }

    // -------------------------------------------------------------------------
    // Structured schema parsing (unchanged behaviour for JSON/SQL/template)
    // -------------------------------------------------------------------------

    private function parse_schema( $content, $extension ) {
        // Try JSON first
        $json = json_decode( $content, true );
        if ( json_last_error() === JSON_ERROR_NONE && is_array( $json ) ) {
            return $this->normalize_schema( $json );
        }

        // Try SQL CREATE TABLE
        if ( $extension === 'sql' || strpos( $content, 'CREATE TABLE' ) !== false ) {
            return $this->parse_sql_schema( $content );
        }

        // Try Table: / - column (type) template
        return $this->parse_text_schema( $content );
    }

    private function parse_sql_schema( $sql_content ) {
        $schema = array();

        preg_match_all( '/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?[`"]?(\w+)[`"]?\s*\((.*?)\);/is', $sql_content, $matches );

        if ( empty( $matches[1] ) ) {
            return null;
        }

        foreach ( $matches[1] as $index => $table_name ) {
            $table_def = $matches[2][ $index ];
            $columns   = array();

            preg_match_all( '/[`"]?(\w+)[`"]?\s+([A-Z]+(?:\([^)]*\))?)[^,]*/i', $table_def, $col_matches );

            foreach ( $col_matches[1] as $col_index => $col_name ) {
                $columns[ $col_name ] = array(
                    'type'     => $col_matches[2][ $col_index ],
                    'nullable' => true,
                );
            }

            if ( ! empty( $columns ) ) {
                $schema[ $table_name ] = $columns;
            }
        }

        return ! empty( $schema ) ? $schema : null;
    }

    private function parse_text_schema( $text_content ) {
        $schema        = array();
        $lines         = explode( "\n", $text_content );
        $current_table = null;

        foreach ( $lines as $line ) {
            $line = trim( $line );

            if ( empty( $line ) || strpos( $line, '#' ) === 0 ) {
                continue;
            }

            if ( preg_match( '/^Table:\s*(\w+)/i', $line, $matches ) ) {
                $current_table            = $matches[1];
                $schema[ $current_table ] = array();
                continue;
            }

            if ( $current_table && preg_match( '/^-\s*(\w+)\s*\(([^)]+)\)/i', $line, $matches ) ) {
                $schema[ $current_table ][ $matches[1] ] = array(
                    'type'     => $matches[2],
                    'nullable' => true,
                );
            }
        }

        return ! empty( $schema ) ? $schema : null;
    }

    private function normalize_schema( $data ) {
        $schema = array();

        if ( isset( $data['tables'] ) ) {
            $data = $data['tables'];
        }

        foreach ( $data as $table_name => $columns ) {
            if ( is_array( $columns ) ) {
                $schema[ $table_name ] = array();

                foreach ( $columns as $col_name => $col_info ) {
                    if ( is_string( $col_info ) ) {
                        $schema[ $table_name ][ $col_name ] = array( 'type' => $col_info, 'nullable' => true );
                    } elseif ( is_array( $col_info ) ) {
                        $schema[ $table_name ][ $col_name ] = $col_info;
                    }
                }
            }
        }

        return ! empty( $schema ) ? $schema : null;
    }

    // -------------------------------------------------------------------------
    // Schema persistence
    // -------------------------------------------------------------------------

    private function save_schema( $schema_data ) {
        try {
            $file_path = $this->schema_dir . $this->schema_file;
            $json_data = wp_json_encode( $schema_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );

            if ( file_exists( $file_path ) ) {
                chmod( $file_path, 0644 );
            }

            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
            $result = file_put_contents( $file_path, $json_data );

            if ( $result === false ) {
                return false;
            }

            chmod( $file_path, 0444 );

            update_option( 'heytrisha_schema_uploaded',     true );
            update_option( 'heytrisha_schema_upload_date',  current_time( 'mysql' ) );
            update_option( 'heytrisha_schema_tables_count', count( $schema_data ) );

            return true;
        } catch ( Exception $e ) {
            error_log( 'HeyTrisha Schema Save Error: ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
            return false;
        }
    }

    // -------------------------------------------------------------------------
    // AJAX: get schema
    // -------------------------------------------------------------------------

    public function handle_get_schema() {
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'heytrisha_schema_nonce' ) ) {
            wp_send_json_error( array( 'message' => 'Security check failed' ) );
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
        }

        $schema = $this->get_schema();

        if ( $schema ) {
            wp_send_json_success( array(
                'schema'          => $schema,
                'tables'          => count( $schema ),
                'uploaded'        => get_option( 'heytrisha_schema_uploaded', false ),
                'upload_date'     => get_option( 'heytrisha_schema_upload_date', '' ),
                'schema_revision' => heytrisha_get_schema_revision(),
            ) );
        } else {
            wp_send_json_error( array( 'message' => 'No schema uploaded' ) );
        }
    }

    public function get_schema() {
        try {
            $file_path = $this->schema_dir . $this->schema_file;

            if ( ! file_exists( $file_path ) ) {
                return null;
            }

            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
            $content = file_get_contents( $file_path );
            $schema  = json_decode( $content, true );

            return $schema ?: null;
        } catch ( Exception $e ) {
            error_log( 'HeyTrisha Schema Read Error: ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
            return null;
        }
    }

    // -------------------------------------------------------------------------
    // AJAX: delete schema
    // -------------------------------------------------------------------------

    public function handle_delete_schema() {
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'heytrisha_schema_nonce' ) ) {
            wp_send_json_error( array( 'message' => 'Security check failed' ) );
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
        }

        if ( $this->delete_schema() ) {
            $rev = heytrisha_bump_schema_revision();
            wp_send_json_success( array( 'message' => 'Specification deleted successfully', 'schema_revision' => $rev ) );
        } else {
            wp_send_json_error( array( 'message' => 'Failed to delete specification' ) );
        }
    }

    private function delete_schema() {
        try {
            // Delete structured schema
            $schema_path = $this->schema_dir . $this->schema_file;
            if ( file_exists( $schema_path ) ) {
                chmod( $schema_path, 0644 );
                unlink( $schema_path );
            }

            // Delete raw specification
            $raw_path = $this->schema_dir . $this->raw_spec_file;
            if ( file_exists( $raw_path ) ) {
                chmod( $raw_path, 0644 );
                unlink( $raw_path );
            }

            // Clear all specification-related options
            delete_option( 'heytrisha_schema_uploaded' );
            delete_option( 'heytrisha_schema_upload_date' );
            delete_option( 'heytrisha_schema_tables_count' );
            delete_option( 'heytrisha_specification_active' );
            delete_option( 'heytrisha_spec_allowlist' );
            delete_option( 'heytrisha_spec_version' );
            delete_option( 'heytrisha_spec_rules_summary' );
            delete_option( 'heytrisha_spec_upload_date' );
            delete_option( 'heytrisha_spec_ingest_error' );

            return true;
        } catch ( Exception $e ) {
            error_log( 'HeyTrisha Schema Delete Error: ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
            return false;
        }
    }

    // -------------------------------------------------------------------------
    // AJAX: generate schema from live database
    // -------------------------------------------------------------------------

    public function handle_generate_schema() {
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'heytrisha_schema_nonce' ) ) {
            wp_send_json_error( array( 'message' => 'Security check failed' ) );
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
        }

        $schema = $this->generate_schema_from_database();

        if ( $schema ) {
            if ( $this->save_schema( $schema ) ) {
                $rev = heytrisha_bump_schema_revision();
                wp_send_json_success( array(
                    'message'         => 'Schema generated and saved successfully',
                    'tables'          => count( $schema ),
                    'schema'          => $schema,
                    'schema_revision' => $rev,
                ) );
            } else {
                wp_send_json_error( array( 'message' => 'Failed to save generated schema' ) );
            }
        } else {
            wp_send_json_error( array( 'message' => 'Failed to generate schema from database' ) );
        }
    }

    public function generate_schema_from_database() {
        global $wpdb;

        try {
            $schema = array();
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $tables = $wpdb->get_results( 'SHOW TABLES', ARRAY_N );

            foreach ( $tables as $table_row ) {
                $table_name  = $table_row[0];
                $safe_name   = str_replace( '`', '``', $table_name );
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $table_cols  = $wpdb->get_results( "DESCRIBE `{$safe_name}`" );
                $columns     = array();

                foreach ( $table_cols as $column ) {
                    $columns[ $column->Field ] = array(
                        'type'     => $column->Type,
                        'nullable' => $column->Null === 'YES',
                        'key'      => $column->Key,
                        'default'  => $column->Default,
                    );
                }

                $schema[ $table_name ] = $columns;
            }

            return ! empty( $schema ) ? $schema : null;
        } catch ( Exception $e ) {
            error_log( 'HeyTrisha Schema Generation Error: ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
            return null;
        }
    }

    // -------------------------------------------------------------------------
    // AI context helper (kept for backward compat)
    // -------------------------------------------------------------------------

    public function get_schema_for_ai() {
        $schema = $this->get_schema();

        if ( ! $schema ) {
            return '';
        }

        $schema_text = "Database Schema:\n\n";

        foreach ( $schema as $table_name => $columns ) {
            $schema_text .= "Table: $table_name\nColumns:\n";

            foreach ( $columns as $col_name => $col_info ) {
                $type     = is_array( $col_info ) ? $col_info['type'] : $col_info;
                $nullable = is_array( $col_info ) ? ( $col_info['nullable'] ? 'NULL' : 'NOT NULL' ) : 'NULL';
                $schema_text .= "  - $col_name ($type, $nullable)\n";
            }

            $schema_text .= "\n";
        }

        return $schema_text;
    }
}

// Initialise Schema Manager
HeyTrisha_Schema_Manager::get_instance();
