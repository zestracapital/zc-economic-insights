/**
 * Zestra Capital - Secure Economic Dashboard
 * WordPress Integration with Enhanced Security
 * 
 * This replaces zestra-dashboard.js with a more secure implementation
 * that prevents data exposure in browser developer tools.
 */

(function() {
    'use strict';

    // Secure configuration management
    const SecureConfig = {
        // Store sensitive data in closure, not in global scope
        _config: {},
        _indicators: new Map(),
        _cache: new Map(),
        
        init: function(wpConfig) {
            // Only store non-sensitive configuration
            this._config = {
                ajaxUrl: wpConfig.ajaxUrl || '/wp-admin/admin-ajax.php',
                nonce: wpConfig.nonce || '',
                strings: wpConfig.strings || {},
                features: wpConfig.features || {},
                security: wpConfig.security || {}
            };
            
            // Cache safe indicators list in memory only
            if (wpConfig.indicators) {
                wpConfig.indicators.forEach(indicator => {
                    this._indicators.set(indicator.slug, {
                        id: indicator.id,
                        name: indicator.name,
                        slug: indicator.slug
                        // No sensitive data stored
                    });
                });
            }
            
            // Clear wpConfig from global scope for security
            if (typeof window.zcDmtConfig !== 'undefined') {
                delete window.zcDmtConfig;
            }
        },
        
        get: function(key, defaultValue = null) {
            return this._config[key] || defaultValue;
        },
        
        getIndicators: function() {
            return Array.from(this._indicators.values());
        },
        
        getIndicator: function(slug) {
            return this._indicators.get(slug) || null;
        }
    };

    /**
     * Secure Data Manager - No direct data exposure
     */
    class SecureDataManager {
        constructor() {
            this.requestQueue = new Map();
            this.rateLimiter = new Map();
        }

        /**
         * Secure data fetching with rate limiting and validation
         */
        async fetchIndicatorData(slug, accessKey = '') {
            // Rate limiting check
            const rateLimitKey = `fetch_${slug}_${Date.now()}`;
            if (this.isRateLimited(rateLimitKey)) {
                throw new Error(SecureConfig.get('strings').rateLimitExceeded || 'Too many requests');
            }
            
            // Prevent duplicate requests
            if (this.requestQueue.has(slug)) {
                return await this.requestQueue.get(slug);
            }

            // Validate indicator exists in our safe list
            const indicator = SecureConfig.getIndicator(slug);
            if (!indicator) {
                throw new Error('Indicator not found');
            }

            const requestPromise = this._performSecureRequest(slug, accessKey);
            this.requestQueue.set(slug, requestPromise);
            
            try {
                const result = await requestPromise;
                this.requestQueue.delete(slug);
                return result;
            } catch (error) {
                this.requestQueue.delete(slug);
                throw error;
            }
        }

        /**
         * Perform secure AJAX request with validation
         */
        async _performSecureRequest(slug, accessKey) {
            const formData = new FormData();
            formData.append('action', 'zc_dmt_get_secure_dashboard_data');
            formData.append('nonce', SecureConfig.get('nonce'));
            formData.append('slug', this._sanitizeSlug(slug));
            
            // Add access key if provided
            if (accessKey && typeof accessKey === 'string') {
                formData.append('access_key', accessKey);
            }
            
            // Add request timestamp and signature for additional security
            const timestamp = Date.now();
            formData.append('timestamp', timestamp.toString());
            formData.append('signature', this._generateSignature(slug, timestamp));

            const response = await fetch(SecureConfig.get('ajaxUrl'), {
                method: 'POST',
                body: formData,
                credentials: 'same-origin',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const data = await response.json();
            
            if (!data.success) {
                throw new Error(data.data || 'Request failed');
            }

            // Validate response structure
            if (!this._validateResponseData(data.data)) {
                throw new Error('Invalid response format');
            }

            return data.data;
        }

        /**
         * Generate request signature for additional security
         */
        _generateSignature(slug, timestamp) {
            // Simple signature based on available client data
            const baseString = `${slug}_${timestamp}_${SecureConfig.get('nonce')}`;
            return btoa(baseString).substring(0, 16);
        }

        /**
         * Sanitize slug input
         */
        _sanitizeSlug(slug) {
            return slug.replace(/[^a-zA-Z0-9_-]/g, '').toLowerCase();
        }

        /**
         * Validate response data structure
         */
        _validateResponseData(data) {
            return data && 
                   data.indicator && 
                   typeof data.indicator.name === 'string' &&
                   Array.isArray(data.series) &&
                   data.series.every(point => Array.isArray(point) && point.length >= 2);
        }

        /**
         * Rate limiting implementation
         */
        isRateLimited(key) {
            const now = Date.now();
            const window = 60000; // 1 minute
            const limit = 10; // 10 requests per minute
            
            if (!this.rateLimiter.has(key)) {
                this.rateLimiter.set(key, []);
            }
            
            const requests = this.rateLimiter.get(key);
            // Clean old requests
            while (requests.length > 0 && requests[0] < now - window) {
                requests.shift();
            }
            
            if (requests.length >= limit) {
                return true;
            }
            
            requests.push(now);
            return false;
        }

        /**
         * Search indicators securely
         */
        searchIndicators(query) {
            if (!query || query.length < 2) {
                return [];
            }
            
            const searchTerm = query.toLowerCase().trim();
            return SecureConfig.getIndicators()
                .filter(indicator => 
                    indicator.name.toLowerCase().includes(searchTerm) ||
                    indicator.slug.toLowerCase().includes(searchTerm)
                )
                .slice(0, 20); // Limit results
        }
    }

    /**
     * Secure Dashboard UI Manager
     */
    class SecureDashboard {
        constructor(container, config) {
            this.container = container;
            this.config = this._sanitizeConfig(config);
            this.dataManager = new SecureDataManager();
            this.chart = null;
            this.currentData = null;
            this.searchTimeout = null;
            this.isDestroyed = false;
            
            // Secure event handlers
            this.handlers = new Map();
            
            this.init();
        }

        /**
         * Sanitize and validate configuration
         */
        _sanitizeConfig(config) {
            const defaults = {
                mode: 'dynamic',
                height: 600,
                showSearch: true,
                showComparison: false, // Disabled by default for security
                showTimeframes: true,
                showChartTypes: true,
                showStats: true,
                showFullscreen: false, // Disabled by default for security
                showThemeToggle: true,
                defaultTimeRange: '5Y',
                defaultChartType: 'line',
                defaultIndicator: '',
                theme: 'light',
                title: 'Economic Analytics Dashboard',
                description: 'Professional Economic Data Visualization'
            };
            
            const sanitized = Object.assign({}, defaults);
            
            // Only copy safe configuration values
            const safeKeys = Object.keys(defaults);
            safeKeys.forEach(key => {
                if (config.hasOwnProperty(key)) {
                    sanitized[key] = this._sanitizeConfigValue(key, config[key]);
                }
            });
            
            return sanitized;
        }

        /**
         * Sanitize individual config values
         */
        _sanitizeConfigValue(key, value) {
            switch (key) {
                case 'height':
                    return Math.max(300, Math.min(1500, parseInt(value) || 600));
                case 'mode':
                    return ['dynamic', 'static'].includes(value) ? value : 'dynamic';
                case 'defaultChartType':
                    return ['line', 'bar'].includes(value) ? value : 'line';
                case 'defaultTimeRange':
                    return ['6M','1Y','2Y','3Y','5Y','10Y','15Y','20Y','All'].includes(value) ? value : '5Y';
                case 'theme':
                    return ['light', 'dark', 'auto'].includes(value) ? value : 'light';
                case 'title':
                case 'description':
                case 'defaultIndicator':
                    return (value || '').toString().substring(0, 200); // Limit length
                default:
                    return !!value; // Convert to boolean for flags
            }
        }

        /**
         * Initialize secure dashboard
         */
        init() {
            if (this.isDestroyed) return;
            
            try {
                this.renderSecureDashboard();
                this.bindSecureEvents();
                this.loadInitialData();
            } catch (error) {
                console.error('Dashboard initialization failed:', error);
                this.showError('Dashboard initialization failed');
            }
        }

        /**
         * Render secure dashboard HTML
         */
        renderSecureDashboard() {
            const dashboardHTML = `
                <div class="zc-secure-dashboard" data-theme="${this.config.theme}">
                    ${this.renderHeader()}
                    ${this.renderControls()}
                    ${this.config.showStats ? this.renderStats() : ''}
                    ${this.renderChart()}
                </div>
            `;

            this.container.innerHTML = dashboardHTML;
            
            // Remove any data attributes that might expose information
            this.container.removeAttribute('data-config');
            this.container.removeAttribute('data-access-key');
        }

        /**
         * Render header section
         */
        renderHeader() {
            if (!this.config.showSearch && !this.config.showThemeToggle) {
                return '';
            }
            
            return `
                <header class="dashboard-header">
                    <div class="header-content">
                        <div class="brand-section">
                            <h1>${this._escapeHtml(this.config.title)}</h1>
                            <span>${this._escapeHtml(this.config.description)}</span>
                        </div>
                        
                        <div class="header-controls">
                            ${this.config.showSearch ? this.renderSearchButton() : ''}
                            ${this.config.showThemeToggle ? '<button class="control-btn theme-toggle" title="Toggle Theme"><span class="theme-icon">🌙</span></button>' : ''}
                        </div>
                    </div>
                </header>
            `;
        }

        /**
         * Render search button and panel
         */
        renderSearchButton() {
            return `
                <div class="search-container">
                    <button class="search-toggle">🔍 Search Indicators</button>
                    <div class="search-panel" style="display: none;">
                        <input type="text" class="search-input" placeholder="Search economic indicators..." maxlength="50">
                        <div class="search-results"></div>
                    </div>
                </div>
            `;
        }

        /**
         * Render chart controls
         */
        renderControls() {
            return `
                <div class="chart-controls">
                    <div class="chart-info">
                        <h2 class="chart-title">Select an indicator</h2>
                        <div class="chart-meta">
                            <span class="last-update">Ready to load data...</span>
                        </div>
                    </div>
                    
                    ${this.config.showChartTypes ? this.renderChartTypes() : ''}
                </div>
                
                ${this.config.showTimeframes ? this.renderTimeframes() : ''}
            `;
        }

        /**
         * Render chart type controls
         */
        renderChartTypes() {
            return `
                <div class="chart-types">
                    <button class="chart-type-btn active" data-type="line">📈</button>
                    <button class="chart-type-btn" data-type="bar">📊</button>
                </div>
            `;
        }

        /**
         * Render timeframe controls
         */
        renderTimeframes() {
            const timeframes = ['6M', '1Y', '2Y', '3Y', '5Y', '10Y', '15Y', '20Y', 'All'];
            const buttons = timeframes.map(tf => 
                `<button class="timeframe-btn ${tf === this.config.defaultTimeRange ? 'active' : ''}" data-range="${tf}">${tf}</button>`
            ).join('');
            
            return `
                <div class="timeframe-section">
                    <label>Time Period:</label>
                    <div class="timeframe-buttons">
                        ${buttons}
                    </div>
                </div>
            `;
        }

        /**
         * Render statistics cards
         */
        renderStats() {
            return `
                <div class="stats-section">
                    <div class="stats-grid">
                        <div class="stat-card">
                            <span class="stat-label">Current</span>
                            <span class="stat-value current-value">--</span>
                        </div>
                        <div class="stat-card">
                            <span class="stat-label">Min</span>
                            <span class="stat-value min-value">--</span>
                        </div>
                        <div class="stat-card">
                            <span class="stat-label">Max</span>
                            <span class="stat-value max-value">--</span>
                        </div>
                    </div>
                </div>
            `;
        }

        /**
         * Render chart container
         */
        renderChart() {
            return `
                <div class="chart-section">
                    <div class="chart-container">
                        <canvas class="main-chart" style="height: ${this.config.height}px;"></canvas>
                        <div class="chart-watermark">Zestra Capital</div>
                        
                        <div class="chart-loading" style="display: none;">
                            <div class="loading-spinner"></div>
                            <span>Loading data...</span>
                        </div>
                        
                        <div class="chart-empty" style="display: flex;">
                            <div class="empty-content">
                                <span class="empty-icon">📊</span>
                                <h3>No Data Selected</h3>
                                <p>Search for an economic indicator to get started</p>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }

        /**
         * Bind secure event listeners
         */
        bindSecureEvents() {
            if (this.isDestroyed) return;
            
            // Theme toggle
            this._bindEvent('.theme-toggle', 'click', () => this.toggleTheme());
            
            // Search functionality
            this._bindEvent('.search-toggle', 'click', (e) => {
                e.stopPropagation();
                const panel = this.container.querySelector('.search-panel');
                if (panel) {
                    const isVisible = panel.style.display === 'block';
                    panel.style.display = isVisible ? 'none' : 'block';
                    if (!isVisible) {
                        const input = panel.querySelector('.search-input');
                        if (input) input.focus();
                    }
                }
            });
            
            // Search input
            this._bindEvent('.search-input', 'input', (e) => {
                clearTimeout(this.searchTimeout);
                this.searchTimeout = setTimeout(() => {
                    this.performSearch(e.target.value);
                }, 300);
            });
            
            // Chart controls
            this._bindEvent('.chart-type-btn', 'click', (e) => {
                const buttons = this.container.querySelectorAll('.chart-type-btn');
                buttons.forEach(btn => btn.classList.remove('active'));
                e.target.classList.add('active');
                this.updateChart();
            });
            
            // Timeframe controls
            this._bindEvent('.timeframe-btn', 'click', (e) => {
                const buttons = this.container.querySelectorAll('.timeframe-btn');
                buttons.forEach(btn => btn.classList.remove('active'));
                e.target.classList.add('active');
                this.applyTimeframeFilter(e.target.dataset.range);
            });
            
            // Close search when clicking outside
            const closeSearchHandler = (e) => {
                const panel = this.container.querySelector('.search-panel');
                const toggle = this.container.querySelector('.search-toggle');
                if (panel && !panel.contains(e.target) && e.target !== toggle) {
                    panel.style.display = 'none';
                }
            };
            
            document.addEventListener('click', closeSearchHandler);
            this.handlers.set('document-click', closeSearchHandler);
        }

        /**
         * Secure event binding helper
         */
        _bindEvent(selector, event, handler) {
            const elements = this.container.querySelectorAll(selector);
            elements.forEach(element => {
                const boundHandler = handler.bind(this);
                element.addEventListener(event, boundHandler);
                
                // Store for cleanup
                if (!this.handlers.has(element)) {
                    this.handlers.set(element, new Map());
                }
                this.handlers.get(element).set(event, boundHandler);
            });
        }

        /**
         * Load initial data securely
         */
        async loadInitialData() {
            if (this.config.defaultIndicator) {
                await this.loadIndicator(this.config.defaultIndicator);
            }
        }

        /**
         * Load indicator data securely
         */
        async loadIndicator(slug, accessKey = '') {
            if (this.isDestroyed) return;
            
            const loading = this.container.querySelector('.chart-loading');
            const empty = this.container.querySelector('.chart-empty');
            const title = this.container.querySelector('.chart-title');
            const lastUpdate = this.container.querySelector('.last-update');
            
            try {
                if (loading) loading.style.display = 'flex';
                if (empty) empty.style.display = 'none';
                if (title) title.textContent = 'Loading...';
                
                const data = await this.dataManager.fetchIndicatorData(slug, accessKey);
                this.currentData = data;
                
                // Update UI with safe data only
                if (title) title.textContent = data.indicator.name || 'Economic Indicator';
                if (lastUpdate) {
                    const lastDate = data.series && data.series.length > 0 ? 
                        new Date(data.series[data.series.length - 1][0]).toLocaleDateString() : 
                        'Unknown';
                    lastUpdate.textContent = `Last updated: ${lastDate}`;
                }
                
                this.updateChart();
                this.updateStats();
                
            } catch (error) {
                console.error('Error loading indicator:', error);
                this.showError(error.message || 'Failed to load data');
            } finally {
                if (loading) loading.style.display = 'none';
            }
        }

        /**
         * Update chart display
         */
        updateChart() {
            if (!this.currentData || this.isDestroyed) return;
            
            const canvas = this.container.querySelector('.main-chart');
            if (!canvas) return;
            
            // Destroy existing chart
            if (this.chart) {
                this.chart.destroy();
            }
            
            const ctx = canvas.getContext('2d');
            const chartData = this._processChartData(this.currentData);
            const activeType = this._getActiveChartType();
            
            const config = {
                type: activeType,
                data: {
                    datasets: [{
                        label: this.currentData.indicator.name,
                        data: chartData,
                        borderColor: '#00BCD4',
                        backgroundColor: activeType === 'bar' ? 
                            'rgba(0, 188, 212, 0.8)' : 
                            'rgba(0, 188, 212, 0.1)',
                        borderWidth: 2,
                        fill: activeType === 'line' ? false : true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        intersect: false
                    },
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            backgroundColor: 'rgba(255, 255, 255, 0.95)',
                            titleColor: '#333',
                            bodyColor: '#333',
                            borderColor: '#ddd',
                            borderWidth: 1
                        }
                    },
                    scales: {
                        x: {
                            type: 'time',
                            time: {
                                unit: 'month'
                            }
                        },
                        y: {
                            ticks: {
                                callback: function(value) {
                                    return typeof value === 'number' ? value.toLocaleString() : value;
                                }
                            }
                        }
                    }
                }
            };
            
            try {
                this.chart = new Chart(ctx, config);
            } catch (error) {
                console.error('Chart creation failed:', error);
                this.showError('Chart rendering failed');
            }
        }

        /**
         * Process chart data securely
         */
        _processChartData(data) {
            if (!data.series || !Array.isArray(data.series)) {
                return [];
            }
            
            return data.series
                .filter(point => Array.isArray(point) && point.length >= 2)
                .map(point => ({
                    x: new Date(point[0]),
                    y: parseFloat(point[1]) || 0
                }))
                .filter(point => !isNaN(point.y)); // Remove invalid values
        }

        /**
         * Get active chart type safely
         */
        _getActiveChartType() {
            const activeBtn = this.container.querySelector('.chart-type-btn.active');
            return activeBtn ? activeBtn.dataset.type || 'line' : 'line';
        }

        /**
         * Update statistics display
         */
        updateStats() {
            if (!this.config.showStats || !this.currentData) return;
            
            const chartData = this._processChartData(this.currentData);
            if (chartData.length === 0) return;
            
            const values = chartData.map(point => point.y);
            const current = values[values.length - 1];
            const min = Math.min(...values);
            const max = Math.max(...values);
            
            const currentEl = this.container.querySelector('.current-value');
            const minEl = this.container.querySelector('.min-value');
            const maxEl = this.container.querySelector('.max-value');
            
            if (currentEl) currentEl.textContent = current.toLocaleString();
            if (minEl) minEl.textContent = min.toLocaleString();
            if (maxEl) maxEl.textContent = max.toLocaleString();
        }

        /**
         * Perform secure search
         */
        performSearch(query) {
            const resultsContainer = this.container.querySelector('.search-results');
            if (!resultsContainer) return;
            
            if (!query || query.length < 2) {
                resultsContainer.innerHTML = '';
                return;
            }
            
            try {
                const results = this.dataManager.searchIndicators(query);
                resultsContainer.innerHTML = '';
                
                if (results.length > 0) {
                    results.forEach(indicator => {
                        const item = document.createElement('div');
                        item.className = 'search-result-item';
                        item.textContent = indicator.name;
                        item.addEventListener('click', () => {
                            this.selectIndicator(indicator);
                        });
                        resultsContainer.appendChild(item);
                    });
                } else {
                    const noResults = document.createElement('div');
                    noResults.className = 'no-results';
                    noResults.textContent = 'No results found';
                    resultsContainer.appendChild(noResults);
                }
            } catch (error) {
                console.error('Search failed:', error);
                resultsContainer.innerHTML = '<div class="search-error">Search failed</div>';
            }
        }

        /**
         * Select indicator securely
         */
        async selectIndicator(indicator) {
            try {
                await this.loadIndicator(indicator.slug);
                
                // Close search panel
                const panel = this.container.querySelector('.search-panel');
                if (panel) panel.style.display = 'none';
                
                // Clear search input
                const input = this.container.querySelector('.search-input');
                if (input) input.value = '';
                
                // Clear search results
                const results = this.container.querySelector('.search-results');
                if (results) results.innerHTML = '';
                
            } catch (error) {
                console.error('Error selecting indicator:', error);
                this.showError('Failed to load selected indicator');
            }
        }

        /**
         * Apply timeframe filter
         */
        applyTimeframeFilter(range) {
            // This would filter the current data and re-render the chart
            // Implementation depends on the specific filtering logic needed
            this.updateChart();
        }

        /**
         * Toggle theme
         */
        toggleTheme() {
            const dashboard = this.container.querySelector('.zc-secure-dashboard');
            if (!dashboard) return;
            
            const isDark = dashboard.dataset.theme === 'dark';
            const newTheme = isDark ? 'light' : 'dark';
            
            dashboard.dataset.theme = newTheme;
            this.config.theme = newTheme;
            
            // Update theme icon
            const icon = this.container.querySelector('.theme-icon');
            if (icon) {
                icon.textContent = newTheme === 'dark' ? '☀️' : '🌙';
            }
            
            // Re-render chart with new theme
            this.updateChart();
        }

        /**
         * Show error state
         */
        showError(message) {
            const loading = this.container.querySelector('.chart-loading');
            const empty = this.container.querySelector('.chart-empty');
            const title = this.container.querySelector('.chart-title');
            const lastUpdate = this.container.querySelector('.last-update');
            
            if (loading) loading.style.display = 'none';
            if (empty) {
                empty.style.display = 'flex';
                const content = empty.querySelector('.empty-content');
                if (content) {
                    content.innerHTML = `
                        <span class="empty-icon">⚠️</span>
                        <h3>Error</h3>
                        <p>${this._escapeHtml(message)}</p>
                    `;
                }
            }
            if (title) title.textContent = 'Error';
            if (lastUpdate) lastUpdate.textContent = message;
        }

        /**
         * Escape HTML to prevent XSS
         */
        _escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        /**
         * Destroy dashboard and cleanup
         */
        destroy() {
            this.isDestroyed = true;
            
            // Clear timeouts
            if (this.searchTimeout) {
                clearTimeout(this.searchTimeout);
            }
            
            // Destroy chart
            if (this.chart) {
                this.chart.destroy();
                this.chart = null;
            }
            
            // Clean up event listeners
            this.handlers.forEach((eventMap, element) => {
                if (element === document) {
                    document.removeEventListener('click', eventMap);
                } else {
                    eventMap.forEach((handler, event) => {
                        element.removeEventListener(event, handler);
                    });
                }
            });
            this.handlers.clear();
            
            // Clear container
            if (this.container) {
                this.container.innerHTML = '';
            }
            
            // Clear data references
            this.currentData = null;
            this.dataManager = null;
        }
    }

    /**
     * Secure Dashboard Manager
     */
    window.ZCSecureDashboard = {
        instances: new Map(),
        
        /**
         * Initialize secure dashboard
         */
        init: function(containerId, config = {}) {
            const container = document.getElementById(containerId);
            if (!container) {
                console.error('Dashboard container not found:', containerId);
                return false;
            }
            
            // Prevent duplicate initialization
            if (this.instances.has(containerId)) {
                console.warn('Dashboard already initialized:', containerId);
                return this.instances.get(containerId);
            }
            
            try {
                // Extract access key from container and remove it
                const accessKey = container.getAttribute('data-access-key') || '';
                container.removeAttribute('data-access-key');
                
                // Add access key to config if present
                if (accessKey) {
                    config.accessKey = accessKey;
                }
                
                const instance = new SecureDashboard(container, config);
                this.instances.set(containerId, instance);
                
                return instance;
            } catch (error) {
                console.error('Secure dashboard initialization failed:', error);
                return false;
            }
        },
        
        /**
         * Destroy dashboard instance
         */
        destroy: function(containerId) {
            const instance = this.instances.get(containerId);
            if (instance) {
                instance.destroy();
                this.instances.delete(containerId);
            }
        },
        
        /**
         * Get instance
         */
        getInstance: function(containerId) {
            return this.instances.get(containerId);
        }
    };
    
    /**
     * Auto-initialization with security
     */
    const initializeSecureDashboards = () => {
        // Initialize SecureConfig with WordPress data
        if (typeof window.zcDmtConfig !== 'undefined') {
            SecureConfig.init(window.zcDmtConfig);
        }
        
        // Initialize dashboards
        const containers = document.querySelectorAll('.zc-zestra-dashboard-container[data-config]');
        
        containers.forEach(container => {
            if (!container.id) {
                console.warn('Dashboard container missing ID');
                return;
            }
            
            try {
                const config = JSON.parse(container.dataset.config || '{}');
                window.ZCSecureDashboard.init(container.id, config);
                
                // Remove config from DOM for security
                container.removeAttribute('data-config');
            } catch (error) {
                console.error('Failed to initialize secure dashboard:', error);
            }
        });
    };
    
    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeSecureDashboards);
    } else {
        initializeSecureDashboards();
    }
    
})();