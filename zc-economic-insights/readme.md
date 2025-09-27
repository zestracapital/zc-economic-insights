# Zestra Capital Economic Analytics Dashboard

A comprehensive, modern React-based economic data visualization dashboard designed for WordPress plugin integration. This dashboard provides both dynamic and static chart modes with extensive customization options.

 🚀 Quick Start

 Installation

1. **Install Dependencies**
```bash
npm install chart.js chartjs-adapter-date-fns chartjs-plugin-zoom lucide-react
```

2. **Copy Dashboard Files**
Copy the entire `src/components/EconomicDashboard` folder to your project.

3. **Import and Use**
```jsx
import { EconomicDashboard, StaticChart } from './components/EconomicDashboard';

// Dynamic Dashboard
<EconomicDashboard 
  baseUrl="https://client.zestracapital.com/wp-json/zc-dmt/v1"
  accessKey="your-access-key"
/>

// Static Chart
<StaticChart
  indicator="gdp-growth"
  title="GDP Growth Rate"
  height={400}
/>
```

 📊 Dashboard Modes

 1. Dynamic Dashboard (Full Features)
Complete interactive dashboard with all features enabled:
- Search functionality
- Comparison system (up to 10 indicators)
- Theme switching
- Fullscreen mode
- All chart types and controls

 2. Static Chart (Embeddable)
Simplified chart for embedding in posts/pages:
- Configurable features
- Auto-load specific indicators
- Custom styling options
- Minimal UI footprint

 🔧 API Integration

 Your API Endpoints

The dashboard expects these endpoints from your WordPress plugin:

# 1. Data Endpoint
```
GET /wp-json/zc-dmt/v1/data/{slug}?access_key=YOUR_KEY
```

**Response Format:**
```json
{
  "status": "success",
  "data": {
    "indicator": {
      "id": 123,
      "name": "Gross Domestic Product",
      "slug": "gdp-growth",
      "source_type": "fred"
    },
    "series": [
      ["2023-01-01", 2.5],
      ["2023-02-01", 2.7],
      ["2023-03-01", 2.8]
    ]
  }
}
```

# 2. Search Endpoint
```
GET /wp-json/zc-dmt/v1/search?q=QUERY&access_key=YOUR_KEY
```

**Response Format:**
```json
{
  "status": "success",
  "indicators": [
    {
      "id": 1,
      "name": "Gross Domestic Product",
      "slug": "gdp-growth",
      "source_type": "fred"
    },
    {
      "id": 2,
      "name": "Unemployment Rate",
      "slug": "unemployment",
      "source_type": "fred"
    }
  ]
}
```

# 3. Validate Key Endpoint
```
POST /wp-json/zc-dmt/v1/validate-key
```

**Request Body:**
```json
{
  "access_key": "user-provided-key"
}
```

**Response:**
```json
{
  "status": "success",
  "valid": true
}
```

 🎛️ Configuration Options

 Dynamic Dashboard Props

```jsx
interface EconomicDashboardProps {
  baseUrl?: string;           // Your API base URL
  accessKey?: string;         // User's access key
  className?: string;         // Custom CSS classes
  fullWidth?: boolean;        // Full width mode
  config?: ChartConfig;       // Configuration object
}
```

 Static Chart Props

```jsx
interface StaticChartProps {
  baseUrl?: string;           // API base URL
  accessKey?: string;         // Access key
  indicator?: string;         // Auto-load indicator slug
  title?: string;             // Custom title
  description?: string;       // Custom description
  height?: number;            // Chart height (default: 400)
  chartType?: ChartType;      // 'line' | 'bar' | 'area' | 'scatter'
  timeRange?: TimeRange;      // '6M' | '1Y' | '2Y' | '3Y' | '5Y' | '10Y' | '15Y' | '20Y' | 'ALL'
  showHeader?: boolean;       // Show/hide header
  showTimeframes?: boolean;   // Show time period buttons
  showChartTypes?: boolean;   // Show chart type selector
  showStats?: boolean;        // Show statistics cards
  showZoomPan?: boolean;      // Enable zoom/pan
  className?: string;         // Custom CSS classes
}
```

 ChartConfig Interface

```jsx
interface ChartConfig {
  mode: 'dynamic' | 'static';
  showHeader?: boolean;          // Show/hide header section
  showSearch?: boolean;          // Enable search functionality
  showComparison?: boolean;      // Enable comparison features
  showTimeframes?: boolean;      // Show time period buttons
  showChartTypes?: boolean;      // Show chart type selector
  showStats?: boolean;           // Show statistics cards
  showZoomPan?: boolean;         // Enable zoom/pan controls
  showFullscreen?: boolean;      // Show fullscreen button
  showThemeToggle?: boolean;     // Show theme switcher
  defaultTimeRange?: TimeRange;  // Default time period
  defaultChartType?: ChartType;  // Default chart type
  defaultIndicator?: string;     // Auto-load indicator
  height?: number;               // Custom height
  title?: string;                // Custom title
  description?: string;          // Custom description
}
```

 🔌 WordPress Plugin Integration

 Step 1: Create Shortcode Handler

```php
// In your main plugin file
function zestra_dashboard_shortcode($atts) {
    $atts = shortcode_atts(array(
        'access_key' => '',
        'base_url' => get_rest_url(null, 'zc-dmt/v1'),
        'mode' => 'dynamic',
        'indicator' => '',
        'title' => '',
        'height' => '600',
        'chart_type' => 'line',
        'time_range' => '5Y',
        'show_search' => 'true',
        'show_comparison' => 'true',
        'show_timeframes' => 'true',
        'show_chart_types' => 'true',
        'show_stats' => 'true',
        'show_zoom_pan' => 'true',
        'show_fullscreen' => 'true',
        'show_theme_toggle' => 'true',
        'class' => ''
    ), $atts);

    // Enqueue React dashboard
    wp_enqueue_script('zestra-dashboard');
    wp_enqueue_style('zestra-dashboard');

    // Generate unique ID
    $dashboard_id = 'zestra-dashboard-' . uniqid();
    
    // Convert string booleans to actual booleans
    $config = array(
        'mode' => $atts['mode'],
        'showHeader' => $atts['mode'] === 'dynamic',
        'showSearch' => filter_var($atts['show_search'], FILTER_VALIDATE_BOOLEAN),
        'showComparison' => filter_var($atts['show_comparison'], FILTER_VALIDATE_BOOLEAN),
        'showTimeframes' => filter_var($atts['show_timeframes'], FILTER_VALIDATE_BOOLEAN),
        'showChartTypes' => filter_var($atts['show_chart_types'], FILTER_VALIDATE_BOOLEAN),
        'showStats' => filter_var($atts['show_stats'], FILTER_VALIDATE_BOOLEAN),
        'showZoomPan' => filter_var($atts['show_zoom_pan'], FILTER_VALIDATE_BOOLEAN),
        'showFullscreen' => filter_var($atts['show_fullscreen'], FILTER_VALIDATE_BOOLEAN),
        'showThemeToggle' => filter_var($atts['show_theme_toggle'], FILTER_VALIDATE_BOOLEAN),
        'defaultTimeRange' => $atts['time_range'],
        'defaultChartType' => $atts['chart_type'],
        'defaultIndicator' => $atts['indicator'],
        'height' => intval($atts['height']),
        'title' => $atts['title']
    );

    ob_start();
    ?>
    <div id="<?php echo esc_attr($dashboard_id); ?>" 
         class="zestra-dashboard-container <?php echo esc_attr($atts['class']); ?>"
         data-config="<?php echo esc_attr(json_encode($config)); ?>"
         data-base-url="<?php echo esc_attr($atts['base_url']); ?>"
         data-access-key="<?php echo esc_attr($atts['access_key']); ?>">
        <div class="zestra-loading">Loading dashboard...</div>
    </div>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const container = document.getElementById('<?php echo $dashboard_id; ?>');
        if (container && window.ZestraReact) {
            const config = JSON.parse(container.dataset.config);
            const baseUrl = container.dataset.baseUrl;
            const accessKey = container.dataset.accessKey;
            
            if (config.mode === 'static') {
                window.ZestraReact.renderStaticChart(container, {
                    baseUrl: baseUrl,
                    accessKey: accessKey,
                    ...config
                });
            } else {
                window.ZestraReact.renderDashboard(container, {
                    baseUrl: baseUrl,
                    accessKey: accessKey,
                    config: config
                });
            }
        }
    });
    </script>
    <?php
    return ob_get_clean();
}

add_shortcode('zestra_dashboard', 'zestra_dashboard_shortcode');
add_shortcode('zestra_chart', 'zestra_dashboard_shortcode'); // Alias for static charts
```

 Step 2: Enqueue Scripts and Styles

```php
function zestra_enqueue_dashboard_assets() {
    // Register React and dependencies
    wp_enqueue_script('react', 'https://unpkg.com/react@18/umd/react.production.min.js', array(), '18.0.0', true);
    wp_enqueue_script('react-dom', 'https://unpkg.com/react-dom@18/umd/react-dom.production.min.js', array('react'), '18.0.0', true);
    wp_enqueue_script('chart-js', 'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js', array(), '4.4.1', true);
    wp_enqueue_script('chartjs-adapter', 'https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns@3.0.0/dist/chartjs-adapter-date-fns.bundle.min.js', array('chart-js'), '3.0.0', true);
    wp_enqueue_script('chartjs-zoom', 'https://cdn.jsdelivr.net/npm/chartjs-plugin-zoom@2.2.0/dist/chartjs-plugin-zoom.min.js', array('chart-js'), '2.2.0', true);
    
    // Your compiled dashboard bundle
    wp_enqueue_script('zestra-dashboard', plugin_dir_url(__FILE__) . 'assets/js/dashboard.min.js', array('react', 'react-dom', 'chart-js'), '1.0.0', true);
    wp_enqueue_style('zestra-dashboard', plugin_dir_url(__FILE__) . 'assets/css/dashboard.min.css', array(), '1.0.0');
}

add_action('wp_enqueue_scripts', 'zestra_enqueue_dashboard_assets');
```

 Step 3: Build Dashboard Bundle

Create a build script to compile your React dashboard:

```javascript
// webpack.config.js
const path = require('path');

module.exports = {
    entry: './src/dashboard-entry.js',
    output: {
        path: path.resolve(__dirname, 'assets/js'),
        filename: 'dashboard.min.js',
        library: 'ZestraReact',
        libraryTarget: 'window'
    },
    externals: {
        'react': 'React',
        'react-dom': 'ReactDOM',
        'chart.js': 'Chart'
    },
    module: {
        rules: [
            {
                test: /\.(js|jsx|ts|tsx)$/,
                exclude: /node_modules/,
                use: {
                    loader: 'babel-loader',
                    options: {
                        presets: ['@babel/preset-react', '@babel/preset-typescript']
                    }
                }
            },
            {
                test: /\.css$/,
                use: ['style-loader', 'css-loader']
            }
        ]
    },
    resolve: {
        extensions: ['.js', '.jsx', '.ts', '.tsx']
    }
};
```

```javascript
// src/dashboard-entry.js
import React from 'react';
import ReactDOM from 'react-dom/client';
import { EconomicDashboard, StaticChart } from './components/EconomicDashboard';

window.ZestraReact = {
    renderDashboard: (container, props) => {
        const root = ReactDOM.createRoot(container);
        root.render(React.createElement(EconomicDashboard, props));
    },
    
    renderStaticChart: (container, props) => {
        const root = ReactDOM.createRoot(container);
        root.render(React.createElement(StaticChart, props));
    }
};
```

 📝 Shortcode Examples

 Dynamic Dashboard
```
[zestra_dashboard access_key="user-key" show_search="true" show_comparison="true"]
```

 Static Chart - Basic
```
[zestra_chart indicator="gdp-growth" title="GDP Growth Rate" height="400"]
```

 Static Chart - Advanced
```
[zestra_chart 
  indicator="unemployment" 
  title="Unemployment Rate" 
  height="500"
  chart_type="area"
  time_range="10Y"
  show_timeframes="true"
  show_chart_types="false"
  show_stats="true"
  show_zoom_pan="true"
  class="custom-chart-class"
]
```

 Embedded in Posts
```
Here's the latest GDP data:

[zestra_chart indicator="gdp" height="300" show_timeframes="false" show_stats="false"]

As you can see from the chart above...
```

 🎨 Customization

 Custom CSS Classes

```css
/* Custom styling for your charts */
.custom-chart-class {
    border-radius: 16px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
}

.custom-chart-class .chart-container {
    background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
}
```

 Theme Customization

```css
/* Override default theme colors */
.economic-dashboard {
    --primary: #your-brand-color;
    --secondary: #your-secondary-color;
    --surface: #your-surface-color;
}
```

 🔒 Security Considerations

 Access Key Validation

```php
function validate_zestra_access_key($access_key) {
    // Your validation logic
    $user_id = get_current_user_id();
    $stored_key = get_user_meta($user_id, 'zestra_access_key', true);
    
    return hash_equals($stored_key, $access_key);
}
```

 Rate Limiting

```php
function zestra_rate_limit_check($access_key) {
    $transient_key = 'zestra_rate_limit_' . md5($access_key);
    $requests = get_transient($transient_key) ?: 0;
    
    if ($requests >= 100) { // 100 requests per hour
        return false;
    }
    
    set_transient($transient_key, $requests + 1, HOUR_IN_SECONDS);
    return true;
}
```

 🐛 Troubleshooting

 Common Issues

1. **Charts not loading**
   - Check if Chart.js is properly loaded
   - Verify API endpoints are accessible
   - Check browser console for errors

2. **Access key errors**
   - Ensure access key is valid
   - Check rate limiting
   - Verify user permissions

3. **Styling issues**
   - Check CSS conflicts
   - Verify theme compatibility
   - Test responsive breakpoints

 Debug Mode

Enable debug mode in your plugin:

```php
define('ZESTRA_DEBUG', true);

if (ZESTRA_DEBUG) {
    add_action('wp_footer', function() {
        ?>
        <script>
        window.ZestraDebug = true;
        console.log('Zestra Dashboard Debug Mode Enabled');
        </script>
        <?php
    });
}
```

 📱 Responsive Design

The dashboard is fully responsive and works on:
- Desktop (1200px+)
- Tablet (768px - 1199px)
- Mobile (320px - 767px)

 Mobile Optimizations
- Collapsible sidebar
- Touch-friendly controls
- Optimized chart sizing
- Simplified navigation

 🚀 Performance Tips

1. **Lazy Loading**
   - Load charts only when visible
   - Use intersection observer

2. **Data Caching**
   - Cache API responses
   - Implement client-side caching

3. **Bundle Optimization**
   - Code splitting
   - Tree shaking
   - Minification

 📊 Analytics Integration

Track chart usage:

```javascript
// Add to your dashboard
function trackChartView(indicator, chartType) {
    if (typeof gtag !== 'undefined') {
        gtag('event', 'chart_view', {
            'indicator': indicator,
            'chart_type': chartType
        });
    }
}
```

 🔄 Updates and Maintenance

 Version Management

```php
define('ZESTRA_DASHBOARD_VERSION', '1.0.0');

function zestra_check_version() {
    $current_version = get_option('zestra_dashboard_version');
    
    if (version_compare($current_version, ZESTRA_DASHBOARD_VERSION, '<')) {
        // Run update procedures
        zestra_update_dashboard();
        update_option('zestra_dashboard_version', ZESTRA_DASHBOARD_VERSION);
    }
}
```

 📞 Support

For integration support:
1. Check this documentation
2. Review example implementations
3. Test with provided sample data
4. Contact plugin support team

 🎯 Best Practices

1. **Always validate access keys**
2. **Implement proper error handling**
3. **Use appropriate chart types for data**
4. **Test on multiple devices**
5. **Monitor performance metrics**
6. **Keep dependencies updated**
7. **Follow WordPress coding standards**

---

This dashboard provides a complete solution for economic data visualization with maximum flexibility for your WordPress plugin integration needs.