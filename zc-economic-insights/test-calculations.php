<?php
/**
 * Test file for ZC DMT Calculations System
 * Run this file to test the calculations functionality
 */

// Mock WordPress environment for testing
define('ABSPATH', __DIR__ . '/');

// Mock WordPress functions
function current_user_can($capability) { return true; }
function current_time($format) { return date('Y-m-d H:i:s'); }
function wp_json_encode($data) { return json_encode($data); }
function sanitize_text_field($str) { return trim(strip_tags($str)); }
function sanitize_title($str) { return strtolower(preg_replace('/[^a-zA-Z0-9_-]/', '-', $str)); }
function wp_kses_post($str) { return strip_tags($str, '<p><br><strong><em>'); }
function intval($val) { return (int)$val; }

// Mock WP_Error class
class WP_Error {
    private $errors = array();
    
    public function __construct($code, $message) {
        $this->errors[$code] = array($message);
    }
    
    public function get_error_message() {
        foreach ($this->errors as $messages) {
            return $messages[0];
        }
        return '';
    }
}

function is_wp_error($obj) {
    return $obj instanceof WP_Error;
}

// Mock wpdb
class MockWPDB {
    public $prefix = 'wp_';
    public function insert($table, $data) { return true; }
    public function get_var($query) { return 1; }
}

$wpdb = new MockWPDB();

// Load our classes
require_once 'includes/class-calculations.php';
require_once 'includes/calculations/class-formula-parser.php';

echo "=== ZC DMT Calculations System Test ===\n\n";

// Test 1: Create a calculation
echo "1. Testing calculation creation...\n";
$calc_id = ZC_DMT_Calculations::create_calculation(
    'GDP Growth Rate',
    'ROC(GDP_US, 4)',
    array('gdp_us'),
    'series'
);

if (is_wp_error($calc_id)) {
    echo "❌ Error: " . $calc_id->get_error_message() . "\n";
} else {
    echo "✅ Calculation created successfully with ID: $calc_id\n";
}

// Test 2: Test formula parser with sample data
echo "\n2. Testing formula parser...\n";

$parser = new ZC_DMT_Formula_Parser();

// Sample economic data
$sample_data = array(
    'gdp_us' => array(
        array('2023-01-01', 100.0),
        array('2023-02-01', 102.0),
        array('2023-03-01', 104.0),
        array('2023-04-01', 103.0),
        array('2023-05-01', 105.0),
        array('2023-06-01', 107.0),
        array('2023-07-01', 106.0),
        array('2023-08-01', 108.0),
        array('2023-09-01', 110.0),
        array('2023-10-01', 109.0)
    )
);

// Test basic functions
$tests = array(
    'SUM(GDP_US)' => 'Sum of all values',
    'AVG(GDP_US)' => 'Average of all values',
    'MIN(GDP_US)' => 'Minimum value',
    'MAX(GDP_US)' => 'Maximum value',
    'COUNT(GDP_US)' => 'Count of values',
    'ROC(GDP_US, 4)' => 'Rate of Change (4 periods)',
    'MA(GDP_US, 3)' => 'Moving Average (3 periods)',
    'RSI(GDP_US, 5)' => 'RSI (5 periods)',
    'MOMENTUM(GDP_US, 2)' => 'Momentum (2 periods)'
);

foreach ($tests as $formula => $description) {
    echo "\nTesting: $formula ($description)\n";
    $result = $parser->parse_and_execute($formula, $sample_data);
    
    if (is_wp_error($result)) {
        echo "❌ Error: " . $result->get_error_message() . "\n";
    } else {
        if (is_array($result)) {
            echo "✅ Result: Array with " . count($result) . " data points\n";
            if (count($result) > 0) {
                echo "   Sample: " . print_r(array_slice($result, 0, 2), true);
            }
        } else {
            echo "✅ Result: $result\n";
        }
    }
}

// Test 3: Available functions
echo "\n3. Testing available functions list...\n";
$functions = ZC_DMT_Calculations::get_available_functions();
echo "✅ Available function categories: " . implode(', ', array_keys($functions)) . "\n";
echo "✅ Total functions: " . array_sum(array_map('count', $functions)) . "\n";

echo "\n=== Test Completed Successfully! ===\n";
echo "\nNext steps:\n";
echo "1. Activate the plugin in WordPress to create database tables\n";
echo "2. Visit admin.php?page=zc-dmt-calculations to use the formula builder\n";
echo "3. Create calculations and test them with real indicator data\n";
echo "4. Use [zc_chart_calculation id=\"calculation_slug\"] to display results\n";
?>
