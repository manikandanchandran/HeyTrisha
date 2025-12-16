<?php
/**
 * Hey Trisha Dependency Installer
 * 
 * Automatically installs Laravel and React dependencies on plugin activation
 */

if (!defined('ABSPATH')) {
    exit;
}

class HeyTrisha_Dependency_Installer
{
    private $plugin_path;
    private $api_path;
    private $react_path;

    public function __construct()
    {
        $this->plugin_path = plugin_dir_path(dirname(__FILE__));
        $this->api_path = $this->plugin_path . 'api';
        $this->react_path = $this->plugin_path . 'assets/js/chatbot-react-app';
    }

    /**
     * Install all dependencies (Laravel and React)
     * 
     * @return array Result with success status and messages
     */
    public function install_all_dependencies()
    {
        $results = [
            'success' => true,
            'messages' => [],
            'errors' => []
        ];

        // Check prerequisites
        $prerequisites = $this->check_prerequisites();
        if (!$prerequisites['success']) {
            return $prerequisites;
        }

        // Install Laravel dependencies
        $laravel_result = $this->install_laravel_dependencies();
        $results['messages'] = array_merge($results['messages'], $laravel_result['messages']);
        if (!$laravel_result['success']) {
            $results['success'] = false;
            $results['errors'] = array_merge($results['errors'], $laravel_result['errors']);
        }

        // Generate Laravel app key if needed
        $key_result = $this->generate_laravel_key();
        $results['messages'] = array_merge($results['messages'], $key_result['messages']);
        if (!$key_result['success'] && !empty($key_result['errors'])) {
            $results['errors'] = array_merge($results['errors'], $key_result['errors']);
        }

        // Install React dependencies (optional - React is loaded from CDN)
        $react_result = $this->install_react_dependencies();
        $results['messages'] = array_merge($results['messages'], $react_result['messages']);
        if (!$react_result['success'] && !empty($react_result['errors'])) {
            // React dependencies are optional, so don't fail the whole installation
            $results['messages'][] = 'Note: React dependencies installation skipped (React is loaded from CDN)';
        }

        // Run database migrations
        $migration_result = $this->run_database_migrations();
        $results['messages'] = array_merge($results['messages'], $migration_result['messages']);
        if (!$migration_result['success'] && !empty($migration_result['errors'])) {
            // Migrations might fail if database is not configured yet, so don't fail installation
            $results['messages'][] = 'Note: Database migrations skipped (configure database settings first)';
        }

        return $results;
    }

    /**
     * Check if prerequisites are met (Composer, npm, PHP version)
     * 
     * @return array
     */
    private function check_prerequisites()
    {
        $results = [
            'success' => true,
            'messages' => [],
            'errors' => []
        ];

        // Check PHP version
        if (version_compare(PHP_VERSION, '8.1.0', '<')) {
            $results['success'] = false;
            $results['errors'][] = 'PHP 8.1 or higher is required. Current version: ' . PHP_VERSION;
        } else {
            $results['messages'][] = '✓ PHP version check passed (' . PHP_VERSION . ')';
        }

        // Check if Composer is available
        $composer_path = $this->find_composer();
        if (!$composer_path) {
            $results['success'] = false;
            $results['errors'][] = 'Composer is not found. Please install Composer to install Laravel dependencies.';
        } else {
            $results['messages'][] = '✓ Composer found: ' . $composer_path;
        }

        // Check if npm is available (optional for React)
        $npm_path = $this->find_npm();
        if (!$npm_path) {
            $results['messages'][] = '⚠ npm not found (optional - React is loaded from CDN)';
        } else {
            $results['messages'][] = '✓ npm found: ' . $npm_path;
        }

        return $results;
    }

    /**
     * Find Composer executable
     * 
     * @return string|false
     */
    private function find_composer()
    {
        // Check common locations
        $possible_paths = [
            'composer', // In PATH
            'composer.phar', // In PATH
            $this->api_path . '/composer.phar', // Local composer.phar
        ];

        foreach ($possible_paths as $path) {
            if ($this->command_exists($path)) {
                return $path;
            }
        }

        return false;
    }

    /**
     * Find npm executable
     * 
     * @return string|false
     */
    private function find_npm()
    {
        if ($this->command_exists('npm')) {
            return 'npm';
        }

        return false;
    }

    /**
     * Check if a command exists
     * 
     * @param string $command
     * @return bool
     */
    private function command_exists($command)
    {
        $output = [];
        $return_var = 0;
        
        // Try to run the command with --version flag
        $test_command = escapeshellcmd($command) . ' --version 2>&1';
        
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            // Windows
            @exec($test_command, $output, $return_var);
        } else {
            // Unix/Linux
            @exec($test_command . ' 2>&1', $output, $return_var);
        }

        return $return_var === 0 && !empty($output);
    }

    /**
     * Install Laravel dependencies using Composer
     * 
     * @return array
     */
    private function install_laravel_dependencies()
    {
        $results = [
            'success' => false,
            'messages' => [],
            'errors' => []
        ];

        $composer_path = $this->find_composer();
        if (!$composer_path) {
            $results['errors'][] = 'Composer not found';
            return $results;
        }

        // Check if vendor directory already exists
        if (is_dir($this->api_path . '/vendor')) {
            $results['messages'][] = '✓ Laravel dependencies already installed (vendor directory exists)';
            $results['success'] = true;
            return $results;
        }

        // Check if composer.json exists
        if (!file_exists($this->api_path . '/composer.json')) {
            $results['errors'][] = 'composer.json not found in api directory';
            return $results;
        }

        $results['messages'][] = 'Installing Laravel dependencies...';

        // Run composer install
        $command = escapeshellcmd($composer_path) . ' install --no-dev --optimize-autoloader --no-interaction --working-dir=' . escapeshellarg($this->api_path) . ' 2>&1';
        
        $output = [];
        $return_var = 0;
        
        @exec($command, $output, $return_var);
        
        if ($return_var === 0) {
            $results['success'] = true;
            $results['messages'][] = '✓ Laravel dependencies installed successfully';
        } else {
            $results['errors'][] = 'Failed to install Laravel dependencies';
            $results['errors'][] = 'Command output: ' . implode("\n", $output);
        }

        return $results;
    }

    /**
     * Generate Laravel application key if needed
     * 
     * @return array
     */
    private function generate_laravel_key()
    {
        $results = [
            'success' => true,
            'messages' => [],
            'errors' => []
        ];

        $env_file = $this->api_path . '/.env';
        $env_example = $this->api_path . '/.env.example';

        // Create .env from .env.example if it doesn't exist
        if (!file_exists($env_file) && file_exists($env_example)) {
            copy($env_example, $env_file);
            $results['messages'][] = '✓ Created .env file from .env.example';
        }

        // Check if APP_KEY is already set
        if (file_exists($env_file)) {
            $env_content = file_get_contents($env_file);
            if (strpos($env_content, 'APP_KEY=base64:') !== false && strpos($env_content, 'APP_KEY=base64:') < strpos($env_content, 'APP_KEY=')) {
                $results['messages'][] = '✓ Laravel app key already exists';
                return $results;
            }
        }

        // Generate Laravel app key
        $php_path = $this->find_php();
        if (!$php_path) {
            $results['errors'][] = 'PHP executable not found';
            $results['success'] = false;
            return $results;
        }

        $artisan_path = $this->api_path . '/artisan';
        if (!file_exists($artisan_path)) {
            $results['messages'][] = '⚠ Laravel artisan not found, skipping key generation';
            return $results;
        }

        $command = escapeshellcmd($php_path) . ' ' . escapeshellarg($artisan_path) . ' key:generate --force 2>&1';
        
        $output = [];
        $return_var = 0;
        
        @exec($command, $output, $return_var);
        
        if ($return_var === 0) {
            $results['messages'][] = '✓ Laravel application key generated';
        } else {
            $results['messages'][] = '⚠ Could not generate Laravel key (may need manual configuration)';
            // Don't fail - key can be generated later
        }

        return $results;
    }

    /**
     * Install React dependencies using npm
     * 
     * @return array
     */
    private function install_react_dependencies()
    {
        $results = [
            'success' => true,
            'messages' => [],
            'errors' => []
        ];

        $npm_path = $this->find_npm();
        if (!$npm_path) {
            $results['messages'][] = '⚠ npm not found - React dependencies skipped (React is loaded from CDN)';
            return $results;
        }

        // Check if node_modules already exists
        if (is_dir($this->react_path . '/node_modules')) {
            $results['messages'][] = '✓ React dependencies already installed (node_modules exists)';
            return $results;
        }

        // Check if package.json exists
        if (!file_exists($this->react_path . '/package.json')) {
            $results['messages'][] = '⚠ package.json not found - React dependencies skipped';
            return $results;
        }

        $results['messages'][] = 'Installing React dependencies...';

        // Run npm install
        $command = escapeshellcmd($npm_path) . ' install --production --prefix ' . escapeshellarg($this->react_path) . ' 2>&1';
        
        $output = [];
        $return_var = 0;
        
        @exec($command, $output, $return_var);
        
        if ($return_var === 0) {
            $results['messages'][] = '✓ React dependencies installed successfully';
        } else {
            $results['messages'][] = '⚠ React dependencies installation skipped (optional - React is loaded from CDN)';
            // Don't fail - React is loaded from CDN anyway
        }

        return $results;
    }

    /**
     * Run Laravel database migrations
     * 
     * @return array
     */
    private function run_database_migrations()
    {
        $results = [
            'success' => true,
            'messages' => [],
            'errors' => []
        ];

        $php_path = $this->find_php();
        if (!$php_path) {
            $results['errors'][] = 'PHP executable not found';
            $results['success'] = false;
            return $results;
        }

        $artisan_path = $this->api_path . '/artisan';
        if (!file_exists($artisan_path)) {
            $results['messages'][] = '⚠ Laravel artisan not found, skipping migrations';
            return $results;
        }

        // Check if .env exists and has database config
        $env_file = $this->api_path . '/.env';
        if (!file_exists($env_file)) {
            $results['messages'][] = '⚠ .env file not found, skipping migrations (configure database first)';
            return $results;
        }

        $results['messages'][] = 'Running database migrations...';

        // Run migrations
        $command = escapeshellcmd($php_path) . ' ' . escapeshellarg($artisan_path) . ' migrate --force 2>&1';
        
        $output = [];
        $return_var = 0;
        
        @exec($command, $output, $return_var);
        
        if ($return_var === 0) {
            $results['messages'][] = '✓ Database migrations completed';
        } else {
            $results['messages'][] = '⚠ Database migrations skipped (database may not be configured yet)';
            // Don't fail - database can be configured later
        }

        return $results;
    }

    /**
     * Find PHP executable
     * 
     * @return string|false
     */
    private function find_php()
    {
        $possible_paths = [
            'php', // In PATH
            PHP_BINARY, // Current PHP binary
        ];

        foreach ($possible_paths as $path) {
            if ($this->command_exists($path)) {
                return $path;
            }
        }

        return PHP_BINARY; // Fallback to current PHP binary
    }

    /**
     * Get installation status
     * 
     * @return array
     */
    public function get_installation_status()
    {
        $status = [
            'laravel_installed' => is_dir($this->api_path . '/vendor'),
            'react_installed' => is_dir($this->react_path . '/node_modules'),
            'laravel_key_exists' => false,
            'composer_available' => $this->find_composer() !== false,
            'npm_available' => $this->find_npm() !== false,
        ];

        // Check if Laravel key exists
        $env_file = $this->api_path . '/.env';
        if (file_exists($env_file)) {
            $env_content = file_get_contents($env_file);
            $status['laravel_key_exists'] = strpos($env_content, 'APP_KEY=base64:') !== false;
        }

        return $status;
    }
}

