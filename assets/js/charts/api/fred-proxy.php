<?php
/**
 * FRED API Proxy for WordPress
 * 
 * This file should be placed in your WordPress root directory or in a custom plugin
 * It handles FRED API requests securely from the server-side
 */

// CORS headers for frontend requests
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Your FRED API key - KEEP THIS SECURE!
// Get your free API key from: https://fred.stlouisfed.org/docs/api/api_key.html
$FRED_API_KEY = 'your_fred_api_key_here'; // Replace with your actual API key

$FRED_BASE_URL = 'https://api.stlouisfed.org/fred';

// Get request parameters
$series_id = isset($_GET['series']) ? sanitize_text_field($_GET['series']) : '';
$search_query = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';

try {
    if (!empty($series_id)) {
        // Fetch series data
        $url = $FRED_BASE_URL . '/series/observations';
        $params = array(
            'series_id' => $series_id,
            'api_key' => $FRED_API_KEY,
            'file_type' => 'json',
            'limit' => 1000,
            'sort_order' => 'asc'
        );
        
        $response = wp_remote_get($url . '?' . http_build_query($params));
        
        if (is_wp_error($response)) {
            throw new Exception('Failed to fetch data from FRED API');
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (isset($data['error_message'])) {
            throw new Exception($data['error_message']);
        }
        
        echo json_encode($data);
        
    } elseif (!empty($search_query)) {
        // Search series
        $url = $FRED_BASE_URL . '/series/search';
        $params = array(
            'search_text' => $search_query,
            'api_key' => $FRED_API_KEY,
            'file_type' => 'json',
            'limit' => 20,
            'sort_order' => 'search_rank'
        );
        
        $response = wp_remote_get($url . '?' . http_build_query($params));
        
        if (is_wp_error($response)) {
            throw new Exception('Failed to search FRED API');
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (isset($data['error_message'])) {
            throw new Exception($data['error_message']);
        }
        
        // Return search results
        echo json_encode($data['seriess'] ?? []);
        
    } else {
        throw new Exception('Missing required parameters');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(array('error' => $e->getMessage()));
}

// Helper function for WordPress compatibility
if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($str) {
        return htmlspecialchars(strip_tags($str), ENT_QUOTES, 'UTF-8');
    }
}

// Helper function for WordPress compatibility  
if (!function_exists('wp_remote_get')) {
    function wp_remote_get($url) {
        $context = stream_context_create([
            'http' => [
                'timeout' => 30,
                'user_agent' => 'Zestra Capital Economic Dashboard'
            ]
        ]);
        
        $response = file_get_contents($url, false, $context);
        
        if ($response === false) {
            return new WP_Error('http_request_failed', 'Request failed');
        }
        
        return array('body' => $response);
    }
}

if (!function_exists('wp_remote_retrieve_body')) {
    function wp_remote_retrieve_body($response) {
        return is_array($response) ? $response['body'] : $response;
    }
}

if (!function_exists('is_wp_error')) {
    function is_wp_error($thing) {
        return $thing instanceof WP_Error;
    }
}

class WP_Error {
    public $errors = array();
    
    public function __construct($code, $message) {
        $this->errors[$code] = array($message);
    }
}
?>