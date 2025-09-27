<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('ZC_DMT_Formula_Parser')) {

    class ZC_DMT_Formula_Parser {

        private $functions;
        private $data_context;

        public function __construct() {
            $this->functions = array(
                // Basic functions
                'SUM' => array($this, 'func_sum'),
                'AVG' => array($this, 'func_avg'),
                'MIN' => array($this, 'func_min'),
                'MAX' => array($this, 'func_max'),
                'COUNT' => array($this, 'func_count'),
                
                // Technical indicators
                'ROC' => array($this, 'func_roc'),
                'MA' => array($this, 'func_ma'),
                'RSI' => array($this, 'func_rsi'),
                'MOMENTUM' => array($this, 'func_momentum'),
                
                // Advanced functions
                'CORRELATION' => array($this, 'func_correlation'),
                'REGRESSION' => array($this, 'func_regression'),
            );
        }

        /**
         * Parse and execute a formula
         * @param string $formula Formula string
         * @param array $data_context Array of indicator data
         * @return array|WP_Error Result or error
         */
        public function parse_and_execute($formula, $data_context = array()) {
            $this->data_context = $data_context;
            
            try {
                // Clean and validate formula
                $formula = trim($formula);
                if (empty($formula)) {
                    throw new Exception('Formula cannot be empty');
                }

                // Parse and execute the formula
                $result = $this->evaluate_expression($formula);
                
                return $result;
            } catch (Exception $e) {
                return new WP_Error('formula_error', $e->getMessage());
            }
        }

        /**
         * Evaluate a formula expression
         * @param string $expression Expression to evaluate
         * @return mixed Result
         */
        private function evaluate_expression($expression) {
            // Remove whitespace
            $expression = preg_replace('/\s+/', '', $expression);
            
            // Check if it's a function call
            if (preg_match('/^([A-Z_]+)\((.*)\)$/', $expression, $matches)) {
                $function_name = $matches[1];
                $params_string = $matches[2];
                
                if (!isset($this->functions[$function_name])) {
                    throw new Exception("Unknown function: {$function_name}");
                }
                
                // Parse parameters
                $params = $this->parse_parameters($params_string);
                
                // Call the function
                return call_user_func($this->functions[$function_name], $params);
            }
            
            // Check if it's a simple indicator reference
            if (preg_match('/^[A-Z_][A-Z0-9_]*$/', $expression)) {
                return $this->get_indicator_data($expression);
            }
            
            // Check if it's a numeric value
            if (is_numeric($expression)) {
                return floatval($expression);
            }
            
            throw new Exception("Invalid expression: {$expression}");
        }

        /**
         * Parse function parameters
         * @param string $params_string Parameters string
         * @return array Array of parsed parameters
         */
        private function parse_parameters($params_string) {
            if (empty($params_string)) {
                return array();
            }
            
            $params = array();
            $current_param = '';
            $paren_depth = 0;
            $in_quotes = false;
            
            for ($i = 0; $i < strlen($params_string); $i++) {
                $char = $params_string[$i];
                
                if ($char === '"' && ($i === 0 || $params_string[$i-1] !== '\\')) {
                    $in_quotes = !$in_quotes;
                    $current_param .= $char;
                } elseif (!$in_quotes && $char === '(') {
                    $paren_depth++;
                    $current_param .= $char;
                } elseif (!$in_quotes && $char === ')') {
                    $paren_depth--;
                    $current_param .= $char;
                } elseif (!$in_quotes && $char === ',' && $paren_depth === 0) {
                    $params[] = $this->evaluate_expression(trim($current_param));
                    $current_param = '';
                } else {
                    $current_param .= $char;
                }
            }
            
            if (!empty($current_param)) {
                $params[] = $this->evaluate_expression(trim($current_param));
            }
            
            return $params;
        }

        /**
         * Get indicator data by slug
         * @param string $slug Indicator slug
         * @return array Series data
         */
        private function get_indicator_data($slug) {
            $slug = strtolower($slug);
            if (!isset($this->data_context[$slug])) {
                throw new Exception("Indicator not found: {$slug}");
            }
            
            return $this->data_context[$slug];
        }

        /**
         * Extract values from series data
         * @param array $series Series data [[date, value], ...]
         * @return array Array of values
         */
        private function extract_values($series) {
            if (!is_array($series)) {
                throw new Exception("Invalid series data");
            }
            
            $values = array();
            foreach ($series as $point) {
                if (is_array($point) && count($point) >= 2 && is_numeric($point[1])) {
                    $values[] = floatval($point[1]);
                }
            }
            
            return $values;
        }

        // Basic Functions

        /**
         * SUM function
         */
        public function func_sum($params) {
            if (count($params) !== 1) {
                throw new Exception("SUM function requires exactly 1 parameter");
            }
            
            $values = $this->extract_values($params[0]);
            return array_sum($values);
        }

        /**
         * AVG function
         */
        public function func_avg($params) {
            if (count($params) !== 1) {
                throw new Exception("AVG function requires exactly 1 parameter");
            }
            
            $values = $this->extract_values($params[0]);
            if (empty($values)) {
                return 0;
            }
            
            return array_sum($values) / count($values);
        }

        /**
         * MIN function
         */
        public function func_min($params) {
            if (count($params) !== 1) {
                throw new Exception("MIN function requires exactly 1 parameter");
            }
            
            $values = $this->extract_values($params[0]);
            if (empty($values)) {
                return 0;
            }
            
            return min($values);
        }

        /**
         * MAX function
         */
        public function func_max($params) {
            if (count($params) !== 1) {
                throw new Exception("MAX function requires exactly 1 parameter");
            }
            
            $values = $this->extract_values($params[0]);
            if (empty($values)) {
                return 0;
            }
            
            return max($values);
        }

        /**
         * COUNT function
         */
        public function func_count($params) {
            if (count($params) !== 1) {
                throw new Exception("COUNT function requires exactly 1 parameter");
            }
            
            $values = $this->extract_values($params[0]);
            return count($values);
        }

        // Technical Indicators

        /**
         * Rate of Change (ROC) function
         */
        public function func_roc($params) {
            if (count($params) !== 2) {
                throw new Exception("ROC function requires exactly 2 parameters: series, periods");
            }
            
            $series = $params[0];
            $periods = intval($params[1]);
            
            if ($periods <= 0) {
                throw new Exception("ROC periods must be positive");
            }
            
            $values = $this->extract_values($series);
            if (count($values) <= $periods) {
                return array();
            }
            
            $result = array();
            for ($i = $periods; $i < count($values); $i++) {
                $current = $values[$i];
                $previous = $values[$i - $periods];
                
                if ($previous != 0) {
                    $roc = (($current - $previous) / $previous) * 100;
                    $result[] = array($series[$i][0], $roc);
                }
            }
            
            return $result;
        }

        /**
         * Moving Average (MA) function
         */
        public function func_ma($params) {
            if (count($params) !== 2) {
                throw new Exception("MA function requires exactly 2 parameters: series, periods");
            }
            
            $series = $params[0];
            $periods = intval($params[1]);
            
            if ($periods <= 0) {
                throw new Exception("MA periods must be positive");
            }
            
            $values = $this->extract_values($series);
            if (count($values) < $periods) {
                return array();
            }
            
            $result = array();
            for ($i = $periods - 1; $i < count($values); $i++) {
                $sum = 0;
                for ($j = $i - $periods + 1; $j <= $i; $j++) {
                    $sum += $values[$j];
                }
                $ma = $sum / $periods;
                $result[] = array($series[$i][0], $ma);
            }
            
            return $result;
        }

        /**
         * RSI function
         */
        public function func_rsi($params) {
            if (count($params) !== 2) {
                throw new Exception("RSI function requires exactly 2 parameters: series, periods");
            }
            
            $series = $params[0];
            $periods = intval($params[1]);
            
            if ($periods <= 0) {
                throw new Exception("RSI periods must be positive");
            }
            
            $values = $this->extract_values($series);
            if (count($values) <= $periods) {
                return array();
            }
            
            $gains = array();
            $losses = array();
            
            // Calculate gains and losses
            for ($i = 1; $i < count($values); $i++) {
                $change = $values[$i] - $values[$i - 1];
                $gains[] = $change > 0 ? $change : 0;
                $losses[] = $change < 0 ? abs($change) : 0;
            }
            
            $result = array();
            
            // Calculate RSI
            for ($i = $periods - 1; $i < count($gains); $i++) {
                $avg_gain = array_sum(array_slice($gains, $i - $periods + 1, $periods)) / $periods;
                $avg_loss = array_sum(array_slice($losses, $i - $periods + 1, $periods)) / $periods;
                
                if ($avg_loss == 0) {
                    $rsi = 100;
                } else {
                    $rs = $avg_gain / $avg_loss;
                    $rsi = 100 - (100 / (1 + $rs));
                }
                
                $result[] = array($series[$i + 1][0], $rsi);
            }
            
            return $result;
        }

        /**
         * Momentum function
         */
        public function func_momentum($params) {
            if (count($params) !== 2) {
                throw new Exception("MOMENTUM function requires exactly 2 parameters: series, periods");
            }
            
            $series = $params[0];
            $periods = intval($params[1]);
            
            if ($periods <= 0) {
                throw new Exception("MOMENTUM periods must be positive");
            }
            
            $values = $this->extract_values($series);
            if (count($values) <= $periods) {
                return array();
            }
            
            $result = array();
            for ($i = $periods; $i < count($values); $i++) {
                $momentum = $values[$i] - $values[$i - $periods];
                $result[] = array($series[$i][0], $momentum);
            }
            
            return $result;
        }

        // Advanced Functions

        /**
         * Correlation function
         */
        public function func_correlation($params) {
            if (count($params) !== 2) {
                throw new Exception("CORRELATION function requires exactly 2 parameters: series1, series2");
            }
            
            $values1 = $this->extract_values($params[0]);
            $values2 = $this->extract_values($params[1]);
            
            $n = min(count($values1), count($values2));
            if ($n < 2) {
                return 0;
            }
            
            // Trim to same length
            $values1 = array_slice($values1, -$n);
            $values2 = array_slice($values2, -$n);
            
            $mean1 = array_sum($values1) / $n;
            $mean2 = array_sum($values2) / $n;
            
            $numerator = 0;
            $sum_sq1 = 0;
            $sum_sq2 = 0;
            
            for ($i = 0; $i < $n; $i++) {
                $diff1 = $values1[$i] - $mean1;
                $diff2 = $values2[$i] - $mean2;
                
                $numerator += $diff1 * $diff2;
                $sum_sq1 += $diff1 * $diff1;
                $sum_sq2 += $diff2 * $diff2;
            }
            
            $denominator = sqrt($sum_sq1 * $sum_sq2);
            
            if ($denominator == 0) {
                return 0;
            }
            
            return $numerator / $denominator;
        }

        /**
         * Linear regression function
         */
        public function func_regression($params) {
            if (count($params) !== 1) {
                throw new Exception("REGRESSION function requires exactly 1 parameter: series");
            }
            
            $series = $params[0];
            $values = $this->extract_values($series);
            $n = count($values);
            
            if ($n < 2) {
                return array();
            }
            
            // Calculate linear regression
            $sum_x = 0;
            $sum_y = 0;
            $sum_xy = 0;
            $sum_x2 = 0;
            
            for ($i = 0; $i < $n; $i++) {
                $x = $i + 1; // Time index
                $y = $values[$i];
                
                $sum_x += $x;
                $sum_y += $y;
                $sum_xy += $x * $y;
                $sum_x2 += $x * $x;
            }
            
            $slope = ($n * $sum_xy - $sum_x * $sum_y) / ($n * $sum_x2 - $sum_x * $sum_x);
            $intercept = ($sum_y - $slope * $sum_x) / $n;
            
            // Generate regression line
            $result = array();
            for ($i = 0; $i < $n; $i++) {
                $x = $i + 1;
                $y = $slope * $x + $intercept;
                $result[] = array($series[$i][0], $y);
            }
            
            return $result;
        }
    }
}
