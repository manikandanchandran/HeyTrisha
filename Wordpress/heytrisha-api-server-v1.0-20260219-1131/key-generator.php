<?php
/**
 * Laravel Application Key Generator
 * 
 * This file generates a Laravel APP_KEY for your .env file
 * 
 * SECURITY: Delete this file after generating your key!
 */

// Security check
$generator_password = 'heytrisha_keygen_2026'; // CHANGE THIS PASSWORD!
$require_password = true;

if ($require_password) {
    session_start();
    
    if (isset($_POST['generator_password'])) {
        if ($_POST['generator_password'] === $generator_password) {
            $_SESSION['generator_authenticated'] = true;
        } else {
            $error = 'Invalid password';
        }
    }
    
    if (!isset($_SESSION['generator_authenticated']) || !$_SESSION['generator_authenticated']) {
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <title>Laravel Key Generator - Authentication</title>
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
            <h1>Laravel Key Generator</h1>
            <p>Please enter the password to continue.</p>
            <?php if (isset($error)): ?>
                <div class="error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <form method="POST">
                <div class="form-group">
                    <label>Password:</label>
                    <input type="password" name="generator_password" required />
                </div>
                <button type="submit">Continue</button>
            </form>
        </body>
        </html>
        <?php
        exit;
    }
}

// Generate key
if (isset($_POST['generate']) || !isset($_GET['key'])) {
    $key = 'base64:' . base64_encode(random_bytes(32));
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Laravel Key Generator</title>
        <style>
            body { font-family: Arial, sans-serif; max-width: 700px; margin: 50px auto; padding: 20px; background: #f5f5f5; }
            .container { background: white; padding: 30px; border-radius: 5px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
            h1 { color: #333; border-bottom: 2px solid #0073aa; padding-bottom: 10px; }
            .key-box { background: #f4f4f4; padding: 15px; border-radius: 3px; margin: 20px 0; font-family: monospace; word-break: break-all; }
            button { background: #0073aa; color: white; padding: 10px 20px; border: none; cursor: pointer; margin: 5px; }
            .info { background: #e7f3ff; padding: 15px; border-left: 4px solid #0073aa; margin: 20px 0; }
            .warning { background: #fff3cd; padding: 15px; border-left: 4px solid #ffc107; margin: 20px 0; }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>Laravel Application Key Generated</h1>
            
            <div class="info">
                <strong>Copy this key to your .env file:</strong>
            </div>
            
            <div class="key-box">
                APP_KEY=<?php echo htmlspecialchars($key); ?>
            </div>
            
            <button onclick="copyToClipboard()">Copy Key</button>
            <form method="POST" style="display: inline;">
                <button type="submit" name="generate" value="1">Generate New Key</button>
            </form>
            
            <div class="info">
                <strong>Instructions:</strong>
                <ol>
                    <li>Copy the key above</li>
                    <li>Open your <code>.env</code> file</li>
                    <li>Find or add the line: <code>APP_KEY=</code></li>
                    <li>Paste the generated key after the equals sign</li>
                    <li>Save the file</li>
                </ol>
            </div>
            
            <div class="warning">
                <strong>⚠️ Security Warning:</strong> Delete this file (key-generator.php) after generating your key!
            </div>
        </div>
        
        <script>
            function copyToClipboard() {
                const keyText = 'APP_KEY=<?php echo htmlspecialchars($key); ?>';
                navigator.clipboard.writeText(keyText).then(function() {
                    alert('Key copied to clipboard!');
                });
            }
        </script>
    </body>
    </html>
    <?php
    exit;
}


