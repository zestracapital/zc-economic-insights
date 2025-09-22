# Zestra Capital Economic Dashboard

A professional economic indicators dashboard with TradingView-like features, built with React and TypeScript.

## Features

- 🔍 **Advanced Search**: Search through thousands of economic indicators
- 📊 **Multiple Chart Types**: Line and bar charts with smooth animations
- 🔄 **Comparison Mode**: Compare up to 10 indicators simultaneously
- 🌓 **Dark/Light Theme**: Professional themes for day and night use
- 📱 **Responsive Design**: Works perfectly on all devices
- ⚡ **Lightweight**: Optimized for fast loading and smooth performance
- 🔒 **Secure API**: Server-side FRED API integration

## WordPress Integration

### Step 1: Get FRED API Key
1. Visit [FRED API](https://fred.stlouisfed.org/docs/api/api_key.html)
2. Register for a free API key
3. Copy your API key

### Step 2: Install PHP Proxy
1. Copy `public/api/fred-proxy.php` to your WordPress root directory
2. Edit the file and replace `your_fred_api_key_here` with your actual FRED API key
3. Make sure the file is accessible at `yoursite.com/fred-proxy.php`

### Step 3: Build and Deploy
1. Run `npm run build` to create production files
2. Upload the `dist` folder contents to your WordPress site
3. Add the dashboard to any page using a Custom HTML widget

### Step 4: WordPress Integration Options

#### Option A: Custom HTML Widget
```html
<div id="economic-dashboard-root"></div>
<script src="/path/to/your/built/assets/index.js"></script>
<link rel="stylesheet" href="/path/to/your/built/assets/index.css">
```

#### Option B: Shortcode (Recommended)
Create a WordPress plugin with this shortcode:

```php
function zestra_economic_dashboard_shortcode() {
    wp_enqueue_script('zestra-dashboard', '/path/to/built/assets/index.js', array(), '1.0.0', true);
    wp_enqueue_style('zestra-dashboard', '/path/to/built/assets/index.css', array(), '1.0.0');
    
    return '<div id="economic-dashboard-root"></div>';
}
add_shortcode('economic_dashboard', 'zestra_economic_dashboard_shortcode');
```

Then use `[economic_dashboard]` in any post or page.

## Features Overview

### Search & Discovery
- Real-time search through FRED database
- Intelligent search suggestions
- Popular indicators quick access

### Chart Capabilities
- Interactive line and bar charts
- Zoom and pan functionality
- Professional styling with animations
- Responsive design for all screen sizes

### Comparison Tools
- Add up to 10 indicators for comparison
- Toggle visibility of comparison lines
- Different colors for each indicator
- Dual y-axis support for different scales

### Time Range Controls
- 6 months to 20+ years of historical data
- Quick time range buttons
- Historical change calculations (3M, 6M, 1Y)

### Professional Design
- Matches Zestra Capital branding
- Dark/light theme toggle
- Fullscreen mode
- Watermarked charts
- Loading states and error handling

## Technical Details

### Built With
- React 18 + TypeScript
- Chart.js for visualizations
- Tailwind CSS for styling
- Lucide React for icons

### Performance
- Lazy loading of chart data
- Debounced search queries
- Optimized re-renders
- Lightweight bundle size

### Browser Support
- Chrome/Edge 88+
- Firefox 85+
- Safari 14+
- Mobile browsers

## Customization

### Colors
Edit the color scheme in `src/components/Dashboard.tsx`:
```typescript
const colors = ['#00BCD4', '#FF5722', '#4CAF50', '#FF9800', '#9C27B0'];
```

### Default Indicators
Modify the default indicators in `src/components/Dashboard.tsx`:
```typescript
const defaultIndicators = [
  { id: 'GDP', name: 'Gross Domestic Product' },
  { id: 'UNRATE', name: 'Unemployment Rate' },
  // Add your preferred defaults
];
```

### Styling
The dashboard uses Tailwind CSS classes. Customize the appearance by modifying the className props throughout the components.

## Security Notes

- Never expose your FRED API key in client-side code
- The PHP proxy file handles all API requests securely
- Validate and sanitize all user inputs
- Use HTTPS in production

## Support

For issues or customization requests, please refer to the FRED API documentation or create an issue in this repository.

## License

This project is licensed under the MIT License.