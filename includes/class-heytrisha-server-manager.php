<?php
/**
 * Hey Trisha Server Manager
 * Manages the Laravel API server process
 */

if (!defined('ABSPATH')) {
    exit;
}

class HeyTrisha_Server_Manager
{
    private $api_port = 8000;
    private $api_path;
    private $process_id_file;
    private $log_file;

    public function __construct()
    {
        $this->api_path = plugin_dir_path(__FILE__) . '../api';
        $this->process_id_file = plugin_dir_path(__FILE__) . '../api/storage/server.pid';
        $this->log_file = plugin_dir_path(__FILE__) . '../api/storage/logs/server.log';
        
        // Ensure storage directory exists
        $storage_dir = plugin_dir_path(__FILE__) . '../api/storage';
        if (!file_exists($storage_dir)) {
            wp_mkdir_p($storage_dir);
        }
        if (!file_exists($storage_dir . '/logs')) {
            wp_mkdir_p($storage_dir . '/logs');
        }
    }

    /**
     * Check if the server is running
     */
    public function is_server_running()
    {
        // Check if port is in use
        if ($this->is_port_in_use($this->api_port)) {
            return true;
        }
        
        // Check PID file
        if (file_exists($this->process_id_file)) {
            $pid = trim(file_get_contents($this->process_id_file));
            if ($pid && $this->is_process_running($pid)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Start the Laravel server
     */
    public function start_server()
    {
        if ($this->is_server_running()) {
            return [
                'success' => false,
                'message' => 'Server is already running'
            ];
        }

        $api_path = $this->api_path;
        
        // Check if Laravel exists
        if (!file_exists($api_path . '/artisan')) {
            return [
                'success' => false,
                'message' => 'Laravel API not found. Please ensure the api directory exists.'
            ];
        }

        // Build command based on OS
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            // Windows
            $command = sprintf(
                'cd /d "%s" && start /B php artisan serve --port=%d > "%s" 2>&1',
                $api_path,
                $this->api_port,
                $this->log_file
            );
            $pid = $this->start_windows_process($command);
        } else {
            // Linux/Unix
            $command = sprintf(
                'cd "%s" && nohup php artisan serve --port=%d > "%s" 2>&1 & echo $!',
                $api_path,
                $this->api_port,
                $this->log_file
            );
            $pid = shell_exec($command);
            $pid = trim($pid);
        }

        if ($pid) {
            // Save PID
            file_put_contents($this->process_id_file, $pid);
            
            // Quick check - don't wait too long (non-blocking)
            // Server will be ready shortly, but we return success immediately
            // The frontend will handle retries if needed
            usleep(500000); // Wait 0.5 seconds only
            
            if ($this->is_server_running()) {
                return [
                    'success' => true,
                    'message' => 'Server started successfully',
                    'pid' => $pid,
                    'port' => $this->api_port
                ];
            } else {
                // Server process started but may need a moment
                return [
                    'success' => true, // Return success anyway - server is starting
                    'message' => 'Server is starting. It will be ready in a few seconds.',
                    'pid' => $pid,
                    'port' => $this->api_port
                ];
            }
        }

        return [
            'success' => false,
            'message' => 'Failed to start server. Check PHP and Laravel installation.'
        ];
    }

    /**
     * Stop the Laravel server
     */
    public function stop_server()
    {
        if (!$this->is_server_running()) {
            return [
                'success' => false,
                'message' => 'Server is not running'
            ];
        }

        // Get PID from file
        if (file_exists($this->process_id_file)) {
            $pid = trim(file_get_contents($this->process_id_file));
            if ($pid) {
                $this->kill_process($pid);
            }
        }

        // Kill any process using the port
        $this->kill_port_process($this->api_port);

        // Remove PID file
        if (file_exists($this->process_id_file)) {
            unlink($this->process_id_file);
        }

        sleep(1);

        if (!$this->is_server_running()) {
            return [
                'success' => true,
                'message' => 'Server stopped successfully'
            ];
        }

        return [
            'success' => false,
            'message' => 'Failed to stop server completely'
        ];
    }

    /**
     * Restart the server
     */
    public function restart_server()
    {
        $stop_result = $this->stop_server();
        sleep(2);
        $start_result = $this->start_server();
        
        return [
            'success' => $start_result['success'],
            'message' => $start_result['success'] ? 'Server restarted successfully' : 'Failed to restart server',
            'stop' => $stop_result,
            'start' => $start_result
        ];
    }

    /**
     * Get server status
     */
    public function get_server_status()
    {
        $is_running = $this->is_server_running();
        $pid = file_exists($this->process_id_file) ? trim(file_get_contents($this->process_id_file)) : null;
        
        return [
            'running' => $is_running,
            'port' => $this->api_port,
            'pid' => $pid,
            'url' => 'http://localhost:' . $this->api_port,
            'log_file' => $this->log_file
        ];
    }

    /**
     * Check if port is in use
     */
    private function is_port_in_use($port)
    {
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            // Windows: Use netstat
            $command = "netstat -ano | findstr :{$port}";
            $output = shell_exec($command);
            return !empty($output);
        } else {
            // Linux/Unix: Use lsof or netstat
            $command = "lsof -i :{$port} 2>/dev/null || netstat -an | grep :{$port}";
            $output = shell_exec($command);
            return !empty($output);
        }
    }

    /**
     * Check if process is running
     */
    private function is_process_running($pid)
    {
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $command = "tasklist /FI \"PID eq {$pid}\" 2>NUL | find \"{$pid}\"";
            $output = shell_exec($command);
            return !empty($output);
        } else {
            return posix_kill($pid, 0);
        }
    }

    /**
     * Start Windows process and get PID
     */
    private function start_windows_process($command)
    {
        $descriptorspec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w']
        ];

        $process = proc_open($command, $descriptorspec, $pipes);
        
        if (is_resource($process)) {
            // Close pipes
            fclose($pipes[0]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            
            // Get process info
            $status = proc_get_status($process);
            proc_close($process);
            
            // For Windows, we need to find the process by port
            sleep(1);
            return $this->get_process_by_port($this->api_port);
        }
        
        return null;
    }

    /**
     * Get process ID by port (Windows)
     */
    private function get_process_by_port($port)
    {
        $command = "netstat -ano | findstr :{$port}";
        $output = shell_exec($command);
        
        if (preg_match('/\s+(\d+)\s*$/', $output, $matches)) {
            return $matches[1];
        }
        
        return null;
    }

    /**
     * Kill process by PID
     */
    private function kill_process($pid)
    {
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            exec("taskkill /F /PID {$pid} 2>NUL");
        } else {
            exec("kill -9 {$pid} 2>/dev/null");
        }
    }

    /**
     * Kill process using port
     */
    private function kill_port_process($port)
    {
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $command = "for /f \"tokens=5\" %a in ('netstat -ano ^| findstr :{$port}') do taskkill /F /PID %a";
            exec($command);
        } else {
            $command = "lsof -ti :{$port} | xargs kill -9 2>/dev/null";
            exec($command);
        }
    }

    /**
     * Check if server is ready to accept connections
     */
    private function is_server_ready()
    {
        $url = 'http://localhost:' . $this->api_port . '/api/health';
        
        // Try a simple health check with very short timeout
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 1, // Very short timeout for quick check
                'ignore_errors' => true
            ]
        ]);
        
        $result = @file_get_contents($url, false, $context);
        
        // If we get any response, server is ready
        return $result !== false;
    }
}

