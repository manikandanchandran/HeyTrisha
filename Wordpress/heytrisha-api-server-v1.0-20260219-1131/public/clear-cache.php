<?php
/**
 * Laravel Cache Clearer
 * 
 * This file clears Laravel's bootstrap cache files
 * 
 * SECURITY: Delete this file after clearing cache!
 */

// Security check
$clear_password = 'heytrisha_cache_2026'; // CHANGE THIS PASSWORD!
$require_password = true;

if ($require_password) {
    session_start();
    
    if (isset($_POST['clear_password'])) {
        if ($_POST['clear_password'] === $clear_password) {
            $_SESSION['clear_authenticated'] = true;
        } else {
            $error = 'Invalid password';
        }
    }
    
    if (!isset($_SESSION['clear_authenticated']) || !$_SESSION['clear_authenticated']) {
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <title>Laravel Cache Clearer - Authentication</title>
            <style>
                body {
                    font-family: Arial, sans-serif;
                    max-width: 500px;
                    margin: 50px auto;
                    padding: 20px;
                    background: #f5f5f5;
                }
                .form-container {
                    background: white;
                    padding: 30px;
                    border-radius: 8px;
                    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                }
                h1 {
                    color: #333;
                    margin-top: 0;
                }
                input[type="password"] {
                    width: 100%;
                    padding: 10px;
                    margin: 10px 0;
                    border: 1px solid #ddd;
                    border-radius: 4px;
                    box-sizing: border-box;
                }
                button {
                    background: #007cba;
                    color: white;
                    padding: 10px 20px;
                    border: none;
                    border-radius: 4px;
                    cursor: pointer;
                    font-size: 16px;
                }
                button:hover {
                    background: #005a87;
                }
                .error {
                    color: red;
                    margin: 10px 0;
                }
            </style>
        </head>
        <body>
            <div class="form-container">
                <h1>Laravel Cache Clearer</h1>
                <?php if (isset($error)): ?>
                    <div class="error"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                <form method="POST">
                    <label for="clear_password">Password:</label>
                    <input type="password" id="clear_password" name="clear_password" required>
                    <button type="submit">Authenticate</button>
                </form>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
}

// Get the base path (parent directory of public/ where this file is located)
$basePath = dirname(__DIR__);

// Files to clear - including all route cache variations
$cacheFiles = [
    'bootstrap/cache/config.php',
    'bootstrap/cache/routes.php',
    'bootstrap/cache/routes-v7.php',
    'bootstrap/cache/routes-v8.php',
    'bootstrap/cache/services.php',
    'bootstrap/cache/packages.php',
    'bootstrap/cache/compiled.php',
];

$cleared = [];
$errors = [];

foreach ($cacheFiles as $file) {
    $fullPath = $basePath . '/' . $file;
    if (file_exists($fullPath)) {
        if (unlink($fullPath)) {
            $cleared[] = $file;
        } else {
            $errors[] = "Failed to delete: $file";
        }
    }
}

// Also try to clear application cache (from parent directory)
$cachePath = $basePath . '/bootstrap/cache';
if (is_dir($cachePath)) {
    $files = glob($cachePath . '/*.php');
    foreach ($files as $file) {
        if (is_file($file) && basename($file) !== '.gitignore') {
            if (unlink($file)) {
                $cleared[] = basename($file);
            } else {
                $errors[] = "Failed to delete: " . basename($file);
            }
        }
    }
}

// Also clear storage/framework/cache if it exists
$storageCachePath = $basePath . '/storage/framework/cache';
if (is_dir($storageCachePath)) {
    $cacheFiles = glob($storageCachePath . '/data/*');
    foreach ($cacheFiles as $file) {
        if (is_file($file)) {
            if (unlink($file)) {
                $cleared[] = 'storage/framework/cache/data/' . basename($file);
            }
        }
    }
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Cache Cleared</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 600px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .result-container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            margin-top: 0;
        }
        .success {
            color: #4caf50;
            margin: 10px 0;
        }
        .error {
            color: #f44336;
            margin: 10px 0;
        }
        ul {
            list-style-type: none;
            padding-left: 0;
        }
        li {
            padding: 5px 0;
        }
        .warning {
            background: #fff3cd;
            border: 1px solid #ffc107;
            padding: 15px;
            border-radius: 4px;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="result-container">
        <h1>Cache Clear Results</h1>
        
        <?php if (!empty($cleared)): ?>
            <div class="success">
                <strong>Successfully cleared <?php echo count($cleared); ?> cache file(s):</strong>
                <ul>
                    <?php foreach ($cleared as $file): ?>
                        <li>✓ <?php echo htmlspecialchars($file); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php else: ?>
            <div class="error">No cache files found to clear.</div>
        <?php endif; ?>
        
        <?php if (!empty($errors)): ?>
            <div class="error">
                <strong>Errors:</strong>
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li>✗ <?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <div class="warning">
            <strong>⚠️ Security Warning:</strong> Delete this file (clear-cache.php) after clearing the cache!
        </div>
        
        <p><a href="clear-cache.php">Clear Again</a> | <a href="index.php">Back to API</a></p>
    </div>
</body>
</html>

