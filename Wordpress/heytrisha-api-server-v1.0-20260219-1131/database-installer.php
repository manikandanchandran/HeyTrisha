<?php
/**
 * HeyTrisha API Database Installer
 * 
 * This file can be uploaded to your shared hosting and run via web browser
 * to set up the database without using command line.
 * 
 * SECURITY: Delete this file after setup is complete!
 */

// Security check - only allow if not in production or with password protection
// IMPORTANT: Change this password before uploading to your server!
$installer_password = 'heytrisha_setup_2026_CHANGE_ME'; // CHANGE THIS PASSWORD!
$require_password = true; // Set to false to disable password protection (NOT RECOMMENDED)

if ($require_password) {
    session_start();
    
    // Check if password is provided
    if (isset($_POST['installer_password'])) {
        if ($_POST['installer_password'] === $installer_password) {
            $_SESSION['installer_authenticated'] = true;
        } else {
            $error = 'Invalid password';
        }
    }
    
    // Check if authenticated
    if (!isset($_SESSION['installer_authenticated']) || !$_SESSION['installer_authenticated']) {
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <title>HeyTrisha Database Installer - Authentication</title>
            <style>
                body { font-family: Arial, sans-serif; max-width: 500px; margin: 50px auto; padding: 20px; }
                .form-group { margin-bottom: 15px; }
                label { display: block; margin-bottom: 5px; font-weight: bold; }
                input[type="password"] { width: 100%; padding: 8px; font-size: 14px; }
                button { background: #0073aa; color: white; padding: 10px 20px; border: none; cursor: pointer; }
                .error { color: red; margin-top: 10px; }
            </style>
        </head>
        <body>
            <h1>HeyTrisha Database Installer</h1>
            <p>Please enter the installer password to continue.</p>
            <?php if (isset($error)): ?>
                <div class="error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <form method="POST">
                <div class="form-group">
                    <label>Installer Password:</label>
                    <input type="password" name="installer_password" required />
                </div>
                <button type="submit">Continue</button>
            </form>
        </body>
        </html>
        <?php
        exit;
    }
}

// Database configuration form
if (!isset($_POST['db_host'])) {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>HeyTrisha Database Installer</title>
        <style>
            body { font-family: Arial, sans-serif; max-width: 700px; margin: 50px auto; padding: 20px; background: #f5f5f5; }
            .container { background: white; padding: 30px; border-radius: 5px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
            h1 { color: #333; border-bottom: 2px solid #0073aa; padding-bottom: 10px; }
            .form-group { margin-bottom: 20px; }
            label { display: block; margin-bottom: 5px; font-weight: bold; color: #555; }
            input[type="text"], input[type="password"] { width: 100%; padding: 10px; font-size: 14px; border: 1px solid #ddd; border-radius: 3px; }
            button { background: #0073aa; color: white; padding: 12px 30px; border: none; cursor: pointer; font-size: 16px; border-radius: 3px; }
            button:hover { background: #005a87; }
            .info { background: #e7f3ff; padding: 15px; border-left: 4px solid #0073aa; margin-bottom: 20px; }
            .warning { background: #fff3cd; padding: 15px; border-left: 4px solid #ffc107; margin-bottom: 20px; }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>HeyTrisha API Database Installer</h1>
            
            <div class="info">
                <strong>Instructions:</strong><br>
                1. Enter your database credentials below<br>
                2. Click "Install Database" to create tables<br>
                3. <strong>Delete this file after installation for security!</strong>
            </div>
            
            <div class="warning">
                <strong>Security Warning:</strong> This installer creates database tables. Make sure you have proper database credentials and delete this file after setup.
            </div>
            
            <form method="POST">
                <div class="form-group">
                    <label>Database Host:</label>
                    <input type="text" name="db_host" value="localhost" required />
                </div>
                
                <div class="form-group">
                    <label>Database Port:</label>
                    <input type="text" name="db_port" value="3306" required />
                </div>
                
                <div class="form-group">
                    <label>Database Name:</label>
                    <input type="text" name="db_name" value="heytrisha_api" required />
                    <small style="color: #666;">Database will be created if it doesn't exist</small>
                </div>
                
                <div class="form-group">
                    <label>Database Username:</label>
                    <input type="text" name="db_user" required />
                </div>
                
                <div class="form-group">
                    <label>Database Password:</label>
                    <input type="password" name="db_pass" required />
                </div>
                
                <button type="submit">Install Database</button>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Process installation
$db_host = $_POST['db_host'];
$db_port = $_POST['db_port'];
$db_name = $_POST['db_name'];
$db_user = $_POST['db_user'];
$db_pass = $_POST['db_pass'];

$errors = [];
$success = [];

try {
    // Connect to MySQL server (without database)
    $conn = new mysqli($db_host, $db_user, $db_pass, '', $db_port);
    
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    
    $success[] = "✓ Connected to MySQL server";
    
    // Create database if it doesn't exist
    $sql = "CREATE DATABASE IF NOT EXISTS `" . $conn->real_escape_string($db_name) . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
    if ($conn->query($sql)) {
        $success[] = "✓ Database '$db_name' created/verified";
    } else {
        throw new Exception("Failed to create database: " . $conn->error);
    }
    
    // Select database
    $conn->select_db($db_name);
    $success[] = "✓ Database selected";
    
    // Create sites table directly (embedded SQL)
    $create_sites_table = "
    CREATE TABLE IF NOT EXISTS `sites` (
      `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      `site_url` VARCHAR(255) NOT NULL,
      `api_key_hash` VARCHAR(64) NOT NULL,
      `openai_key` TEXT NOT NULL COMMENT 'Encrypted OpenAI API key',
      `email` VARCHAR(255) DEFAULT NULL,
      `username` VARCHAR(255) DEFAULT NULL,
      `password` VARCHAR(255) DEFAULT NULL COMMENT 'Hashed password',
      `first_name` VARCHAR(255) DEFAULT NULL,
      `last_name` VARCHAR(255) DEFAULT NULL,
      `db_name` VARCHAR(255) DEFAULT NULL COMMENT 'WordPress database name',
      `db_username` VARCHAR(255) DEFAULT NULL COMMENT 'WordPress database username',
      `db_password` TEXT DEFAULT NULL COMMENT 'Encrypted WordPress database password',
      `wordpress_version` VARCHAR(50) DEFAULT NULL,
      `woocommerce_version` VARCHAR(50) DEFAULT NULL,
      `plugin_version` VARCHAR(50) DEFAULT NULL,
      `is_active` TINYINT(1) NOT NULL DEFAULT 1,
      `query_count` INT NOT NULL DEFAULT 0,
      `last_query_at` TIMESTAMP NULL DEFAULT NULL,
      `created_at` TIMESTAMP NULL DEFAULT NULL,
      `updated_at` TIMESTAMP NULL DEFAULT NULL,
      PRIMARY KEY (`id`),
      UNIQUE KEY `sites_site_url_unique` (`site_url`),
      UNIQUE KEY `sites_api_key_hash_unique` (`api_key_hash`),
      UNIQUE KEY `sites_username_unique` (`username`),
      KEY `sites_is_active_index` (`is_active`),
      KEY `sites_created_at_index` (`created_at`),
      KEY `sites_username_index` (`username`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    
    if ($conn->query($create_sites_table)) {
        $success[] = "✓ Table 'sites' created";
    } else {
        if (strpos($conn->error, 'already exists') !== false) {
            $success[] = "ℹ Table 'sites' already exists (skipped)";
        } else {
            $errors[] = "Error creating sites table: " . $conn->error;
        }
    }
    
    // Create migrations table
    $create_migrations_table = "
    CREATE TABLE IF NOT EXISTS `migrations` (
      `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
      `migration` VARCHAR(255) NOT NULL,
      `batch` INT NOT NULL,
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    
    if ($conn->query($create_migrations_table)) {
        $success[] = "✓ Table 'migrations' created";
    } else {
        if (strpos($conn->error, 'already exists') !== false) {
            $success[] = "ℹ Table 'migrations' already exists (skipped)";
        } else {
            $errors[] = "Error creating migrations table: " . $conn->error;
        }
    }
    
    // Verify tables were created
    $result = $conn->query("SHOW TABLES");
    $tables = [];
    while ($row = $result->fetch_array()) {
        $tables[] = $row[0];
    }
    
    $conn->close();
    
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>HeyTrisha Database Installer - Results</title>
        <style>
            body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; background: #f5f5f5; }
            .container { background: white; padding: 30px; border-radius: 5px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
            h1 { color: #333; border-bottom: 2px solid #0073aa; padding-bottom: 10px; }
            .success { color: #28a745; margin: 10px 0; padding: 10px; background: #d4edda; border-left: 4px solid #28a745; }
            .error { color: #dc3545; margin: 10px 0; padding: 10px; background: #f8d7da; border-left: 4px solid #dc3545; }
            .info { background: #e7f3ff; padding: 15px; border-left: 4px solid #0073aa; margin: 20px 0; }
            .warning { background: #fff3cd; padding: 15px; border-left: 4px solid #ffc107; margin: 20px 0; }
            .table-list { background: #f8f9fa; padding: 15px; margin: 20px 0; border-radius: 3px; }
            .table-list ul { margin: 10px 0; padding-left: 20px; }
            code { background: #f4f4f4; padding: 2px 6px; border-radius: 3px; font-family: monospace; }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>Installation Results</h1>
            
            <?php if (!empty($success)): ?>
                <h2>Success Messages:</h2>
                <?php foreach ($success as $msg): ?>
                    <div class="success"><?php echo htmlspecialchars($msg); ?></div>
                <?php endforeach; ?>
            <?php endif; ?>
            
            <?php if (!empty($errors)): ?>
                <h2>Errors:</h2>
                <?php foreach ($errors as $error): ?>
                    <div class="error"><?php echo htmlspecialchars($error); ?></div>
                <?php endforeach; ?>
            <?php endif; ?>
            
            <?php if (!empty($tables)): ?>
                <div class="table-list">
                    <h3>Created Tables:</h3>
                    <ul>
                        <?php foreach ($tables as $table): ?>
                            <li><code><?php echo htmlspecialchars($table); ?></code></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <?php if (empty($errors) && !empty($tables)): ?>
                <div class="info">
                    <h3>✅ Database Setup Complete!</h3>
                    <p><strong>Next Steps:</strong></p>
                    <ol>
                        <li>Update your Laravel <code>.env</code> file with these database credentials:
                            <pre style="background: #f4f4f4; padding: 10px; margin: 10px 0; border-radius: 3px;">
DB_CONNECTION=mysql
DB_HOST=<?php echo htmlspecialchars($db_host); ?>

DB_PORT=<?php echo htmlspecialchars($db_port); ?>

DB_DATABASE=<?php echo htmlspecialchars($db_name); ?>

DB_USERNAME=<?php echo htmlspecialchars($db_user); ?>

DB_PASSWORD=<?php echo htmlspecialchars($db_pass); ?></pre>
                        </li>
                        <li>Run: <code>php artisan key:generate</code> (if you have SSH access)</li>
                        <li><strong>DELETE THIS FILE (database-installer.php) FOR SECURITY!</strong></li>
                    </ol>
                </div>
            <?php endif; ?>
            
            <div class="warning">
                <strong>⚠️ Security Warning:</strong> Please delete <code>database-installer.php</code> immediately after installation to prevent unauthorized access.
            </div>
        </div>
    </body>
    </html>
    <?php
    
} catch (Exception $e) {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>HeyTrisha Database Installer - Error</title>
        <style>
            body { font-family: Arial, sans-serif; max-width: 700px; margin: 50px auto; padding: 20px; }
            .error { color: #dc3545; padding: 15px; background: #f8d7da; border-left: 4px solid #dc3545; }
        </style>
    </head>
    <body>
        <div class="error">
            <h2>Installation Failed</h2>
            <p><strong>Error:</strong> <?php echo htmlspecialchars($e->getMessage()); ?></p>
            <p>Please check your database credentials and try again.</p>
        </div>
    </body>
    </html>
    <?php
}

