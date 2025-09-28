/**
 * Zestra Capital - Modern Economic Dashboard
 * WordPress Integration - Secure Version
 * Built from index.js dist file with security enhancements
 */

(function() {
    'use strict';

    // WordPress configuration
    const wpConfig = window.zcDmtConfig || {};
    
    /**
     * Modern Economic Dashboard Class
     * Built with security best practices
     */
    class ZestraModernDashboard {
        constructor(container, config) {
            this.container = container;
            this.config = this.sanitizeConfig(config);
            this.chart = null;
            this.chartData = {
                primary: { data: [], label: '', lastUpdate: null },
                secondary: { data: [], label: '' }
            };
            this.currentView = {
                chartType: 'line',
                timeRange: '5Y',
                theme: 'light',
                comparison: null
            };
            this.searchTimeout = null;
            
            this.init();
        }

        /**
         * Sanitize and validate configuration
         */
        sanitizeConfig(config) {
            const defaults = {
                mode: 'dynamic',
                height: 600,
                showSearch: true,
                showComparison: true,
                showTimeframes: true,
                showChartTypes: true,
                showStats: true,
                showFullscreen: true,
                showThemeToggle: true,
                defaultTimeRange: '5Y',
                defaultChartType: 'line',
                defaultIndicator: '',
                theme: 'auto',
                title: 'Economic Analytics Dashboard',
                description: 'Professional Economic Data Visualization'
            };
            
            const sanitized = { ...defaults, ...config };
            
            // Validate and sanitize values
            sanitized.height = Math.max(400, Math.min(1200, parseInt(sanitized.height)));
            sanitized.mode = ['dynamic', 'static'].includes(sanitized.mode) ? sanitized.mode : 'dynamic';
            sanitized.theme = ['auto', 'light', 'dark'].includes(sanitized.theme) ? sanitized.theme : 'auto';
            sanitized.defaultChartType = ['line', 'bar'].includes(sanitized.defaultChartType) ? sanitized.defaultChartType : 'line';
            
            return sanitized;
        }

        /**
         * Initialize dashboard
         */
        init() {
            this.renderDashboard();
            this.setInitialState();
            this.bindEvents();
            this.loadDefaultData();
        }

        /**
         * Set initial state from configuration
         */
        setInitialState() {
            this.currentView.chartType = this.config.defaultChartType;
            this.currentView.timeRange = this.config.defaultTimeRange;
            
            // Apply theme
            if (this.config.theme === 'dark') {
                this.container.querySelector('.zc-dashboard').classList.add('dark-theme');
                this.currentView.theme = 'dark';
            }
            
            // Set active timeframe button
            this.updateActiveTimeframe(this.config.defaultTimeRange);
        }

        /**
         * Render dashboard HTML
         */
        renderDashboard() {
            const dashboardHTML = `
                <div class="zc-dashboard" data-theme="${this.currentView.theme}">
                    ${this.renderHeader()}
                    ${this.renderControls()}
                    ${this.config.showStats ? this.renderStats() : ''}
                    ${this.renderChart()}
                    ${this.config.showComparison ? this.renderComparison() : ''}
                </div>
            `;

            this.container.innerHTML = dashboardHTML;
        }

        /**
         * Render dashboard header
         */
        renderHeader() {
            return `
                <header class="dashboard-header">
                    <div class="header-content">
                        <div class="brand-section">
                            <h1>${this.escapeHtml(this.config.title)}</h1>
                            <span>${this.escapeHtml(this.config.description)}</span>
                        </div>
                        
                        <div class="header-controls">
                            ${this.config.showSearch ? this.renderSearchControl() : ''}
                            <div class="control-buttons">
                                ${this.config.showThemeToggle ? '<button class="control-btn theme-toggle" title="Toggle Theme"><span class="theme-icon">🌙</span></button>' : ''}
                                ${this.config.showFullscreen ? '<button class="control-btn fullscreen-toggle" title="Fullscreen">⛶</button>' : ''}
                            </div>
                        </div>
                    </div>
                </header>
            `;
        }

        /**
         * Render search control
         */
        renderSearchControl() {
            return `
                <div class="search-container">
                    <button class="search-toggle">🔍 Search Indicators</button>
                    <div class="search-panel" style="display: none;">
                        <input type="text" class="search-input" placeholder="Search economic indicators...">
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
                        <h2 class="chart-title">Loading...</h2>
                        <div class="chart-meta">
                            <span class="last-update">Preparing data...</span>
                        </div>
                    </div>
                    
                    <div class="control-group">
                        ${this.config.showChartTypes ? this.renderChartTypes() : ''}
                        ${this.config.showComparison ? '<button class="compare-btn">+ Compare</button>' : ''}
                    </div>
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
                `<button class="timeframe-btn" data-range="${tf}">${tf}</button>`
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
                            <span class="stat-label">3M Change</span>
                            <span class="stat-value change-3m">--</span>
                        </div>
                        <div class="stat-card">
                            <span class="stat-label">6M Change</span>
                            <span class="stat-value change-6m">--</span>
                        </div>
                        <div class="stat-card">
                            <span class="stat-label">1Y Change</span>
                            <span class="stat-value change-1y">--</span>
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
                        <div class="chart-watermark">Zestra Capital Analytics</div>
                        
                        <div class="chart-loading" style="display: flex;">
                            <div class="loading-backdrop"></div>
                            <div class="loading-content">
                                <div class="loading-spinner"></div>
                                <span>Loading economic data...</span>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }

        /**
         * Render comparison sidebar
         */
        renderComparison() {
            return `
                <div class="comparison-sidebar" style="display: none;">
                    <div class="sidebar-header">
                        <h3>Comparison</h3>
                        <button class="close-sidebar">×</button>
                    </div>
                    <div class="sidebar-content">
                        <div class="comparison-hint">
                            <p>Add indicators to compare trends</p>
                        </div>
                    </div>
                </div>
            `;
        }

        /**
         * Bind event listeners
         */
        bindEvents() {
            // Theme toggle
            const themeToggle = this.container.querySelector('.theme-toggle');
            if (themeToggle) {
                themeToggle.addEventListener('click', () => this.toggleTheme());
            }

            // Fullscreen toggle
            const fullscreenToggle = this.container.querySelector('.fullscreen-toggle');
            if (fullscreenToggle) {
                fullscreenToggle.addEventListener('click', () => this.toggleFullscreen());
            }

            // Search functionality
            this.bindSearchEvents();

            // Chart controls
            this.bindChartControls();

            // Timeframe controls
            this.bindTimeframeControls();
        }

        /**
         * Bind search events
         */
        bindSearchEvents() {
            const searchToggle = this.container.querySelector('.search-toggle');
            const searchPanel = this.container.querySelector('.search-panel');
            const searchInput = this.container.querySelector('.search-input');

            if (searchToggle && searchPanel) {
                searchToggle.addEventListener('click', (e) => {
                    e.stopPropagation();
                    const isVisible = searchPanel.style.display === 'block';
                    searchPanel.style.display = isVisible ? 'none' : 'block';
                    if (!isVisible && searchInput) {
                        searchInput.focus();
                    }
                });
            }

            if (searchInput) {
                searchInput.addEventListener('input', (e) => {
                    clearTimeout(this.searchTimeout);
                    this.searchTimeout = setTimeout(() => {
                        this.performSearch(e.target.value);
                    }, 300);
                });
            }

            // Close search when clicking outside
            document.addEventListener('click', (e) => {
                if (searchPanel && !searchPanel.contains(e.target) && e.target !== searchToggle) {
                    searchPanel.style.display = 'none';
                }
            });
        }

        /**
         * Bind chart control events
         */
        bindChartControls() {
            // Chart type buttons
            const chartTypeButtons = this.container.querySelectorAll('.chart-type-btn');
            chartTypeButtons.forEach(button => {
                button.addEventListener('click', () => {
                    chartTypeButtons.forEach(btn => btn.classList.remove('active'));
                    button.classList.add('active');
                    this.currentView.chartType = button.dataset.type;
                    this.updateChart();
                });
            });
        }

        /**
         * Bind timeframe control events
         */
        bindTimeframeControls() {
            const timeframeButtons = this.container.querySelectorAll('.timeframe-btn');
            timeframeButtons.forEach(button => {
                button.addEventListener('click', () => {
                    timeframeButtons.forEach(btn => btn.classList.remove('active'));
                    button.classList.add('active');
                    this.currentView.timeRange = button.dataset.range;
                    this.applyTimeframeFilter(button.dataset.range);
                });
            });
        }

        /**
         * Load default data
         */
        async loadDefaultData() {
            try {
                // Try to load default indicator
                if (this.config.defaultIndicator) {
                    await this.loadIndicator(this.config.defaultIndicator);
                } else {
                    // Load first available indicator
                    const indicators = wpConfig.indicators || [];
                    if (indicators.length > 0) {
                        await this.loadIndicator(indicators[0].slug);
                    } else {
                        this.showEmptyState();
                    }
                }
            } catch (error) {
                console.error('Error loading default data:', error);
                this.showError('Failed to load economic data');
            }
        }

        /**
         * Secure data fetching via WordPress AJAX (not REST API)
         */
        async fetchIndicatorData(slug) {
            try {
                const formData = new FormData();
                formData.append('action', 'zc_dmt_get_dashboard_data');
                formData.append('nonce', wpConfig.nonce);
                formData.append('slug', slug);
                
                // Add access key if provided in config
                if (this.config.accessKey) {
                    formData.append('access_key', this.config.accessKey);
                }

                const response = await fetch(wpConfig.ajaxUrl, {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();
                
                if (!data.success) {
                    throw new Error(data.data || 'Failed to fetch data');
                }

                return data.data;
            } catch (error) {
                console.error('Data fetch error:', error);
                throw error;
            }
        }

        /**
         * Load indicator data
         */
        async loadIndicator(slug) {
            const loading = this.container.querySelector('.chart-loading');
            const chartTitle = this.container.querySelector('.chart-title');
            const lastUpdate = this.container.querySelector('.last-update');

            try {
                if (loading) loading.style.display = 'flex';
                if (chartTitle) chartTitle.textContent = 'Loading...';

                const data = await this.fetchIndicatorData(slug);
                
                // Process and store data
                this.processIndicatorData(data);
                
                // Update UI
                if (chartTitle) chartTitle.textContent = data.indicator.name;
                if (lastUpdate) lastUpdate.textContent = `Last updated: ${this.formatDate(data.lastUpdate)}`;
                
                // Update chart
                this.updateChart();
                this.updateStats();
                
                if (loading) loading.style.display = 'none';
                
            } catch (error) {
                if (loading) loading.style.display = 'none';
                this.showError('Error loading indicator: ' + error.message);
            }
        }

        /**
         * Process indicator data
         */
        processIndicatorData(data) {
            const series = data.series || [];
            const processedData = series.map(point => ({
                x: new Date(point[0]),
                y: parseFloat(point[1])
            }));

            this.chartData.primary = {
                data: processedData,
                label: data.indicator.name,
                lastUpdate: new Date(data.lastUpdate)
            };
        }

        /**
         * Update chart display
         */
        updateChart() {
            const canvas = this.container.querySelector('.main-chart');
            if (!canvas || !this.chartData.primary.data.length) {
                return;
            }

            // Destroy existing chart
            if (this.chart) {
                this.chart.destroy();
            }

            const ctx = canvas.getContext('2d');
            const chartData = this.applyTimeframeFilter(this.chartData.primary.data);
            
            // Chart configuration
            const config = {
                type: this.currentView.chartType,
                data: {
                    datasets: [{
                        label: this.chartData.primary.label,
                        data: chartData,
                        borderColor: '#00BCD4',
                        backgroundColor: this.currentView.chartType === 'bar' 
                            ? 'rgba(0, 188, 212, 0.8)' 
                            : 'rgba(0, 188, 212, 0.1)',
                        borderWidth: 3,
                        fill: this.currentView.chartType === 'line' ? false : true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            backgroundColor: 'rgba(255, 255, 255, 0.95)',
                            titleColor: '#333',
                            bodyColor: '#333',
                            borderColor: '#ddd',
                            borderWidth: 1,
                            cornerRadius: 8
                        }
                    },
                    scales: {
                        x: {
                            type: 'time',
                            time: {
                                unit: 'month'
                            },
                            grid: {
                                color: 'rgba(0, 0, 0, 0.05)'
                            }
                        },
                        y: {
                            grid: {
                                color: 'rgba(0, 0, 0, 0.05)'
                            },
                            ticks: {
                                callback: function(value) {
                                    return value.toLocaleString();
                                }
                            }
                        }
                    }
                }
            };

            this.chart = new Chart(ctx, config);
        }

        /**
         * Apply timeframe filter to data
         */
        applyTimeframeFilter(data) {
            if (!data || data.length === 0) return [];
            if (this.currentView.timeRange === 'All') return data;
            
            const now = new Date();
            const timeRangeMap = {
                '6M': 6,
                '1Y': 12,
                '2Y': 24,
                '3Y': 36,
                '5Y': 60,
                '10Y': 120,
                '15Y': 180,
                '20Y': 240
            };
            
            const months = timeRangeMap[this.currentView.timeRange] || 60;
            const cutoffDate = new Date(now.getFullYear(), now.getMonth() - months, now.getDate());
            
            return data.filter(point => point.x >= cutoffDate);
        }

        /**
         * Update active timeframe button
         */
        updateActiveTimeframe(range) {
            const buttons = this.container.querySelectorAll('.timeframe-btn');
            buttons.forEach(btn => {
                btn.classList.remove('active');
                if (btn.dataset.range === range) {
                    btn.classList.add('active');
                }
            });
        }

        /**
         * Update statistics display
         */
        updateStats() {
            if (!this.config.showStats || !this.chartData.primary.data.length) {
                return;
            }

            const data = this.chartData.primary.data;
            const currentValue = data[data.length - 1]?.y || 0;
            
            const changes = this.calculatePercentageChanges(data, currentValue);
            
            const elements = {
                '3m': this.container.querySelector('.change-3m'),
                '6m': this.container.querySelector('.change-6m'),
                '1y': this.container.querySelector('.change-1y')
            };
            
            Object.keys(elements).forEach(period => {
                const element = elements[period];
                if (element) {
                    const change = changes[period];
                    element.textContent = change ? `${change > 0 ? '+' : ''}${change.toFixed(2)}%` : '--';
                    element.style.color = change > 0 ? '#4CAF50' : change < 0 ? '#F44336' : '#666';
                }
            });
        }

        /**
         * Calculate percentage changes
         */
        calculatePercentageChanges(data, currentValue) {
            const now = new Date();
            const periods = {
                '3m': 3,
                '6m': 6,
                '1y': 12
            };
            
            const changes = {};
            
            Object.keys(periods).forEach(period => {
                const months = periods[period];
                const targetDate = new Date(now.getFullYear(), now.getMonth() - months, now.getDate());
                
                const closestPoint = data.reduce((closest, point) => {
                    const timeDiff = Math.abs(point.x.getTime() - targetDate.getTime());
                    const closestTimeDiff = Math.abs(closest.x.getTime() - targetDate.getTime());
                    return timeDiff < closestTimeDiff ? point : closest;
                });
                
                if (closestPoint) {
                    changes[period] = ((currentValue - closestPoint.y) / closestPoint.y) * 100;
                }
            });
            
            return changes;
        }

        /**
         * Perform search
         */
        performSearch(query) {
            const resultsContainer = this.container.querySelector('.search-results');
            if (!resultsContainer || query.length < 2) {
                if (resultsContainer) resultsContainer.innerHTML = '';
                return;
            }

            const indicators = wpConfig.indicators || [];
            const filtered = indicators.filter(indicator => 
                indicator.name.toLowerCase().includes(query.toLowerCase()) ||
                indicator.slug.toLowerCase().includes(query.toLowerCase())
            );

            resultsContainer.innerHTML = '';

            if (filtered.length > 0) {
                filtered.slice(0, 20).forEach(indicator => {
                    const item = document.createElement('div');
                    item.className = 'search-result-item';
                    item.textContent = indicator.name;
                    item.addEventListener('click', () => {
                        this.loadIndicator(indicator.slug);
                        this.container.querySelector('.search-panel').style.display = 'none';
                        this.container.querySelector('.search-input').value = '';
                        resultsContainer.innerHTML = '';
                    });
                    resultsContainer.appendChild(item);
                });
            } else {
                const noResults = document.createElement('div');
                noResults.className = 'no-results';
                noResults.textContent = 'No results found';
                resultsContainer.appendChild(noResults);
            }
        }

        /**
         * Toggle theme
         */
        toggleTheme() {
            const dashboard = this.container.querySelector('.zc-dashboard');
            const isDark = dashboard.classList.toggle('dark-theme');
            this.currentView.theme = isDark ? 'dark' : 'light';
            
            const themeIcon = this.container.querySelector('.theme-icon');
            if (themeIcon) {
                themeIcon.textContent = isDark ? '☀️' : '🌙';
            }
            
            // Update chart with new theme
            this.updateChart();
        }

        /**
         * Toggle fullscreen
         */
        toggleFullscreen() {
            const dashboard = this.container.querySelector('.zc-dashboard');
            if (!document.fullscreenElement) {
                dashboard.requestFullscreen().catch(err => {
                    console.error('Fullscreen error:', err);
                });
            } else {
                document.exitFullscreen();
            }
        }

        /**
         * Show empty state
         */
        showEmptyState() {
            const chartTitle = this.container.querySelector('.chart-title');
            const lastUpdate = this.container.querySelector('.last-update');
            const loading = this.container.querySelector('.chart-loading');
            
            if (chartTitle) chartTitle.textContent = 'No Data Available';
            if (lastUpdate) lastUpdate.textContent = 'Use search to find economic indicators';
            if (loading) loading.style.display = 'none';
        }

        /**
         * Show error state
         */
        showError(message) {
            const chartTitle = this.container.querySelector('.chart-title');
            const lastUpdate = this.container.querySelector('.last-update');
            const loading = this.container.querySelector('.chart-loading');
            
            if (chartTitle) chartTitle.textContent = 'Error';
            if (lastUpdate) lastUpdate.textContent = message;
            if (loading) loading.style.display = 'none';
        }

        /**
         * Utility: Escape HTML
         */
        escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        /**
         * Utility: Format date
         */
        formatDate(date) {
            if (!date) return 'Unknown';
            const d = new Date(date);
            return d.toLocaleDateString();
        }

        /**
         * Destroy dashboard
         */
        destroy() {
            if (this.chart) {
                this.chart.destroy();
            }
            this.container.innerHTML = '';
        }
    }

    /**
     * Global Dashboard Manager - Secure Version
     */
    window.ZCZestraDashboard = {
        instances: new Map(),

        /**
         * Initialize dashboard with security checks
         */
        init(containerId, config = {}) {
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
                const instance = new ZestraModernDashboard(container, config);
                this.instances.set(containerId, instance);
                console.log('Modern dashboard initialized:', containerId);
                return instance;
            } catch (error) {
                console.error('Dashboard initialization failed:', error);
                return false;
            }
        },

        /**
         * Destroy dashboard instance
         */
        destroy(containerId) {
            const instance = this.instances.get(containerId);
            if (instance) {
                instance.destroy();
                this.instances.delete(containerId);
            }
        },

        /**
         * Get instance
         */
        getInstance(containerId) {
            return this.instances.get(containerId);
        }
    };

    /**
     * Auto-initialization with security
     */
    const initializeSecureDashboards = () => {
        const containers = document.querySelectorAll('.zc-zestra-dashboard-container[data-config]');
        
        containers.forEach(container => {
            if (!container.id) {
                console.warn('Dashboard container missing ID');
                return;
            }
            
            try {
                const config = JSON.parse(container.dataset.config);
                window.ZCZestraDashboard.init(container.id, config);
            } catch (error) {
                console.error('Failed to parse dashboard config:', error);
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