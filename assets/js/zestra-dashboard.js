/**
 * Zestra Capital - Modern Economic Dashboard
 * WordPress-Native Integration of React Components
 * 
 * This file replaces the old chart UI with modern Zestra dashboard design
 * Converted from React/TypeScript to vanilla JavaScript for WordPress compatibility
 * 
 * Updated to use secure AJAX endpoints and proper error handling
 */

(function() {
    'use strict';

    // Ensure Chart.js is available
    if (typeof Chart === 'undefined') {
        console.error('ZestraDashboard: Chart.js is required');
        return;
    }

    /**
     * Main Zestra Dashboard Class
     * Mimics React patterns but uses vanilla JS for WordPress compatibility
     */
    class ZestraDashboard {
        constructor(containerId, config) {
            this.containerId = containerId;
            this.container = document.getElementById(containerId);
            this.config = this.normalizeConfig(config);
            this.state = {
                currentIndicator: null,
                chartData: null,
                loading: false,
                error: null,
                theme: this.config.theme || 'auto',
                chartType: this.config.defaultChartType || 'line',
                timeRange: this.config.defaultTimeRange || '5Y',
                comparisonIndicators: [],
                searchResults: [],
                showSearch: false
            };
            
            this.chart = null;
            this.searchTimeout = null;
            
            if (!this.container) {
                console.error('ZestraDashboard: Container not found:', containerId);
                return;
            }
            
            this.init();
        }

        normalizeConfig(config) {
            return {
                mode: config.mode || 'dynamic',
                height: parseInt(config.height) || 600,
                showHeader: config.showHeader !== false,
                showSearch: config.showSearch !== false,
                showComparison: config.showComparison !== false,
                showTimeframes: config.showTimeframes !== false,
                showChartTypes: config.showChartTypes !== false,
                showStats: config.showStats !== false,
                showFullscreen: config.showFullscreen !== false,
                showThemeToggle: config.showThemeToggle !== false,
                defaultTimeRange: config.defaultTimeRange || '5Y',
                defaultChartType: config.defaultChartType || 'line',
                defaultIndicator: config.defaultIndicator || '',
                theme: config.theme || 'auto',
                title: config.title || 'Economic Analytics Dashboard',
                description: config.description || 'Professional Economic Data Visualization',
                indicators: config.indicators || '',
                accessKey: config.accessKey || '', // For secure API access
                ...config
            };
        }

        init() {
            this.setupTheme();
            this.render();
            this.bindEvents();
            
            // Auto-load default indicator if specified
            if (this.config.defaultIndicator) {
                this.loadIndicator(this.config.defaultIndicator);
            }
            
            // Handle comparison indicators
            if (this.config.indicators) {
                const indicators = this.config.indicators.split(',').map(i => i.trim());
                if (indicators.length > 1) {
                    this.loadComparisonIndicators(indicators);
                }
            }
        }

        setupTheme() {
            const theme = this.config.theme === 'auto' ? 
                (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light') : 
                this.config.theme;
            
            this.state.theme = theme;
            this.container.setAttribute('data-theme', theme);
        }

        render() {
            this.container.innerHTML = this.getTemplate();
            this.bindUIEvents();
        }

        getTemplate() {
            return `
                <div class="zestra-dashboard" data-mode="${this.config.mode}" data-theme="${this.state.theme}">
                    ${this.config.showHeader ? this.getHeaderTemplate() : ''}
                    
                    <div class="dashboard-content">
                        ${this.config.showSearch ? this.getSearchTemplate() : ''}
                        
                        <div class="chart-section">
                            ${this.getControlsTemplate()}
                            <div class="chart-container">
                                <canvas id="${this.containerId}-chart" width="400" height="200"></canvas>
                                <div class="chart-loading" style="display: none;">
                                    <div class="loading-spinner"></div>
                                    <span>Loading chart data...</span>
                                </div>
                                <div class="chart-error" style="display: none;">
                                    <div class="error-icon">⚠️</div>
                                    <div class="error-message"></div>
                                </div>
                            </div>
                            ${this.config.showStats ? this.getStatsTemplate() : ''}
                        </div>
                        
                        ${this.config.showComparison ? this.getComparisonTemplate() : ''}
                    </div>
                </div>
            `;
        }

        getHeaderTemplate() {
            return `
                <div class="dashboard-header">
                    <div class="header-content">
                        <div class="header-text">
                            <h2 class="dashboard-title">${this.config.title}</h2>
                            <p class="dashboard-description">${this.config.description}</p>
                        </div>
                        <div class="header-actions">
                            ${this.config.showThemeToggle ? '<button class="btn-theme-toggle" title="Toggle Theme">🌓</button>' : ''}
                            ${this.config.showFullscreen ? '<button class="btn-fullscreen" title="Fullscreen">⛶</button>' : ''}
                        </div>
                    </div>
                </div>
            `;
        }

        getSearchTemplate() {
            return `
                <div class="search-panel" ${!this.state.showSearch ? 'style="display: none;"' : ''}>
                    <div class="search-input-wrapper">
                        <input type="text" class="search-input" placeholder="Search economic indicators..." />
                        <button class="search-toggle">🔍</button>
                    </div>
                    <div class="search-results" style="display: none;"></div>
                </div>
            `;
        }

        getControlsTemplate() {
            return `
                <div class="chart-controls">
                    ${this.config.showTimeframes ? this.getTimeframeControls() : ''}
                    ${this.config.showChartTypes ? this.getChartTypeControls() : ''}
                </div>
            `;
        }

        getTimeframeControls() {
            const timeframes = ['3M', '6M', '1Y', '2Y', '3Y', '5Y', '10Y', '15Y', '20Y', 'ALL'];
            return `
                <div class="control-group timeframe-controls">
                    <label>Time Range:</label>
                    <div class="button-group">
                        ${timeframes.map(tf => `
                            <button class="btn-timeframe ${tf === this.state.timeRange ? 'active' : ''}" data-range="${tf}">${tf}</button>
                        `).join('')}
                    </div>
                </div>
            `;
        }

        getChartTypeControls() {
            const types = [
                { key: 'line', label: 'Line', icon: '📈' },
                { key: 'bar', label: 'Bar', icon: '📊' },
                { key: 'area', label: 'Area', icon: '🏔️' }
            ];
            return `
                <div class="control-group chart-type-controls">
                    <label>Chart Type:</label>
                    <div class="button-group">
                        ${types.map(type => `
                            <button class="btn-chart-type ${type.key === this.state.chartType ? 'active' : ''}" data-type="${type.key}" title="${type.label}">
                                ${type.icon}
                            </button>
                        `).join('')}
                    </div>
                </div>
            `;
        }

        getStatsTemplate() {
            return `
                <div class="stats-panel" style="display: none;">
                    <div class="stats-grid">
                        <div class="stat-item">
                            <div class="stat-label">Current</div>
                            <div class="stat-value" data-stat="current">-</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-label">Change</div>
                            <div class="stat-value" data-stat="change">-</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-label">Min</div>
                            <div class="stat-value" data-stat="min">-</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-label">Max</div>
                            <div class="stat-value" data-stat="max">-</div>
                        </div>
                    </div>
                </div>
            `;
        }

        getComparisonTemplate() {
            return `
                <div class="comparison-sidebar">
                    <div class="comparison-header">
                        <h3>Compare Indicators</h3>
                        <button class="btn-add-comparison">+ Add</button>
                    </div>
                    <div class="comparison-list"></div>
                </div>
            `;
        }

        bindUIEvents() {
            // Theme toggle
            const themeToggle = this.container.querySelector('.btn-theme-toggle');
            if (themeToggle) {
                themeToggle.addEventListener('click', () => this.toggleTheme());
            }

            // Fullscreen toggle
            const fullscreenBtn = this.container.querySelector('.btn-fullscreen');
            if (fullscreenBtn) {
                fullscreenBtn.addEventListener('click', () => this.toggleFullscreen());
            }

            // Search functionality
            const searchInput = this.container.querySelector('.search-input');
            const searchToggle = this.container.querySelector('.search-toggle');
            
            if (searchInput) {
                searchInput.addEventListener('input', (e) => this.handleSearch(e.target.value));
            }
            
            if (searchToggle) {
                searchToggle.addEventListener('click', () => this.toggleSearch());
            }

            // Timeframe controls
            this.container.querySelectorAll('.btn-timeframe').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    this.setTimeRange(e.target.dataset.range);
                });
            });

            // Chart type controls
            this.container.querySelectorAll('.btn-chart-type').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    this.setChartType(e.target.dataset.type);
                });
            });

            // Comparison controls
            const addComparisonBtn = this.container.querySelector('.btn-add-comparison');
            if (addComparisonBtn) {
                addComparisonBtn.addEventListener('click', () => this.showIndicatorSearch());
            }
        }

        bindEvents() {
            // Window resize handler
            window.addEventListener('resize', () => {
                if (this.chart) {
                    this.chart.resize();
                }
            });
        }

        // API Methods - Secure data fetching using correct endpoint names
        async loadIndicator(slug) {
            if (!slug) return;
            
            this.setState({ loading: true, error: null });
            
            try {
                const data = await this.fetchIndicatorData(slug);
                
                if (data && data.series && data.series.length > 0) {
                    this.setState({ 
                        currentIndicator: data.indicator,
                        chartData: data,
                        loading: false 
                    });
                    
                    this.renderChart();
                    this.updateStats(data.series);
                } else {
                    throw new Error('No data available for this indicator');
                }
                
            } catch (error) {
                console.error('Error loading indicator:', error);
                this.setState({ 
                    loading: false, 
                    error: error.message || 'Failed to load indicator data' 
                });
                this.showError(this.state.error);
            }
        }

        async fetchIndicatorData(slug) {
            // Use secure WordPress AJAX endpoint
            const formData = new FormData();
            formData.append('action', 'zc_dmt_get_chart_data'); // Updated endpoint name
            formData.append('nonce', window.zcDmtConfig?.nonce || '');
            formData.append('slug', slug);
            formData.append('time_range', this.state.timeRange);
            
            // Include access key if provided
            if (this.config.accessKey) {
                formData.append('access_key', this.config.accessKey);
            }
            
            const response = await fetch(window.zcDmtConfig?.ajaxUrl || '/wp-admin/admin-ajax.php', {
                method: 'POST',
                body: formData,
                credentials: 'same-origin' // Important for WordPress nonce validation
            });
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            
            const result = await response.json();
            
            if (!result.success) {
                // Handle specific error cases
                if (result.data && typeof result.data === 'string') {
                    throw new Error(result.data);
                }
                throw new Error('Failed to fetch data');
            }
            
            return result.data;
        }

        async searchIndicators(query) {
            if (!query || query.length < 2) {
                this.setState({ searchResults: [] });
                return;
            }

            try {
                const formData = new FormData();
                formData.append('action', 'zc_dmt_search_indicators');
                formData.append('nonce', window.zcDmtConfig?.nonce || '');
                formData.append('query', query);
                
                // Include access key if provided
                if (this.config.accessKey) {
                    formData.append('access_key', this.config.accessKey);
                }
                
                const response = await fetch(window.zcDmtConfig?.ajaxUrl || '/wp-admin/admin-ajax.php', {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin'
                });
                
                const result = await response.json();
                
                if (result.success && result.data) {
                    // Handle both array format and object format
                    const searchData = Array.isArray(result.data) ? result.data : (result.data.indicators || []);
                    this.setState({ searchResults: searchData });
                    this.renderSearchResults();
                }
                
            } catch (error) {
                console.error('Search error:', error);
                this.setState({ searchResults: [] });
            }
        }

        // Chart rendering with Chart.js
        renderChart() {
            const canvas = this.container.querySelector(`#${this.containerId}-chart`);
            if (!canvas || !this.state.chartData) return;

            // Destroy existing chart
            if (this.chart) {
                this.chart.destroy();
            }

            const ctx = canvas.getContext('2d');
            const series = this.state.chartData.series || [];
            
            if (series.length === 0) {
                this.showError('No data points available');
                return;
            }
            
            // Prepare data for Chart.js
            const chartData = {
                labels: series.map(point => point[0]), // dates
                datasets: [{
                    label: this.state.currentIndicator?.name || 'Data',
                    data: series.map(point => ({ x: point[0], y: point[1] })),
                    borderColor: this.getThemeColor('primary'),
                    backgroundColor: this.state.chartType === 'area' ? 
                        this.getThemeColor('primaryAlpha') : 'transparent',
                    fill: this.state.chartType === 'area',
                    tension: this.state.chartType === 'line' ? 0.4 : 0
                }]
            };

            const chartConfig = {
                type: this.getChartJSType(),
                data: chartData,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top'
                        },
                        tooltip: {
                            mode: 'index',
                            intersect: false,
                            callbacks: {
                                title: function(tooltipItems) {
                                    return new Date(tooltipItems[0].parsed.x).toLocaleDateString();
                                },
                                label: function(context) {
                                    return context.dataset.label + ': ' + context.parsed.y.toFixed(2);
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            type: 'time',
                            time: {
                                unit: this.getTimeUnit(),
                                displayFormats: {
                                    day: 'MMM dd',
                                    month: 'MMM yyyy',
                                    year: 'yyyy'
                                }
                            },
                            grid: {
                                color: this.getThemeColor('grid')
                            },
                            ticks: {
                                color: this.getThemeColor('text')
                            }
                        },
                        y: {
                            beginAtZero: false,
                            grid: {
                                color: this.getThemeColor('grid')
                            },
                            ticks: {
                                color: this.getThemeColor('text'),
                                callback: function(value) {
                                    return value.toLocaleString();
                                }
                            }
                        }
                    },
                    interaction: {
                        mode: 'nearest',
                        axis: 'x',
                        intersect: false
                    }
                }
            };

            try {
                this.chart = new Chart(ctx, chartConfig);
                this.hideLoading();
            } catch (error) {
                console.error('Chart rendering error:', error);
                this.showError('Failed to render chart');
            }
        }

        getChartJSType() {
            const typeMap = {
                'line': 'line',
                'bar': 'bar',
                'area': 'line'
            };
            return typeMap[this.state.chartType] || 'line';
        }

        getTimeUnit() {
            const range = this.state.timeRange;
            if (['3M', '6M'].includes(range)) return 'day';
            if (['1Y', '2Y'].includes(range)) return 'month';
            return 'year';
        }

        getThemeColor(colorKey) {
            const colors = {
                light: {
                    primary: '#00bcd4',
                    primaryAlpha: 'rgba(0, 188, 212, 0.1)',
                    grid: '#e2e8f0',
                    text: '#1a202c'
                },
                dark: {
                    primary: '#00bcd4',
                    primaryAlpha: 'rgba(0, 188, 212, 0.2)',
                    grid: '#4a5568',
                    text: '#f7fafc'
                }
            };
            
            return colors[this.state.theme]?.[colorKey] || colors.light[colorKey];
        }

        // UI State management
        setState(newState) {
            Object.assign(this.state, newState);
            this.updateUI();
        }

        updateUI() {
            // Update loading state
            if (this.state.loading) {
                this.showLoading();
            } else {
                this.hideLoading();
            }
            
            // Update active states in controls
            this.updateControlStates();
        }

        updateControlStates() {
            // Update timeframe buttons
            this.container.querySelectorAll('.btn-timeframe').forEach(btn => {
                btn.classList.toggle('active', btn.dataset.range === this.state.timeRange);
            });
            
            // Update chart type buttons
            this.container.querySelectorAll('.btn-chart-type').forEach(btn => {
                btn.classList.toggle('active', btn.dataset.type === this.state.chartType);
            });
        }

        showLoading() {
            const loading = this.container.querySelector('.chart-loading');
            const error = this.container.querySelector('.chart-error');
            if (loading) loading.style.display = 'flex';
            if (error) error.style.display = 'none';
        }

        hideLoading() {
            const loading = this.container.querySelector('.chart-loading');
            if (loading) loading.style.display = 'none';
        }

        showError(message) {
            const error = this.container.querySelector('.chart-error');
            const errorMsg = this.container.querySelector('.error-message');
            const loading = this.container.querySelector('.chart-loading');
            
            if (error && errorMsg) {
                errorMsg.textContent = message;
                error.style.display = 'flex';
            }
            if (loading) loading.style.display = 'none';
        }

        // Event handlers
        toggleTheme() {
            const newTheme = this.state.theme === 'light' ? 'dark' : 'light';
            this.setState({ theme: newTheme });
            this.container.setAttribute('data-theme', newTheme);
            
            // Re-render chart with new theme colors
            if (this.chart && this.state.chartData) {
                this.renderChart();
            }
        }

        toggleFullscreen() {
            if (!document.fullscreenElement) {
                this.container.requestFullscreen?.();
            } else {
                document.exitFullscreen?.();
            }
        }

        toggleSearch() {
            this.setState({ showSearch: !this.state.showSearch });
            const searchPanel = this.container.querySelector('.search-panel');
            if (searchPanel) {
                searchPanel.style.display = this.state.showSearch ? 'block' : 'none';
            }
        }

        handleSearch(query) {
            clearTimeout(this.searchTimeout);
            this.searchTimeout = setTimeout(() => {
                this.searchIndicators(query);
            }, 300);
        }

        setTimeRange(range) {
            if (range !== this.state.timeRange) {
                this.setState({ timeRange: range });
                
                // Reload current indicator with new time range
                if (this.state.currentIndicator) {
                    this.loadIndicator(this.state.currentIndicator.slug);
                }
            }
        }

        setChartType(type) {
            if (type !== this.state.chartType) {
                this.setState({ chartType: type });
                
                // Re-render chart with new type
                if (this.chart && this.state.chartData) {
                    this.renderChart();
                }
            }
        }

        renderSearchResults() {
            const resultsContainer = this.container.querySelector('.search-results');
            if (!resultsContainer) return;
            
            if (this.state.searchResults.length === 0) {
                resultsContainer.style.display = 'none';
                return;
            }
            
            resultsContainer.innerHTML = this.state.searchResults.map(indicator => `
                <div class="search-result-item" data-slug="${indicator.slug}">
                    <div class="result-name">${indicator.name}</div>
                    <div class="result-slug">${indicator.slug}</div>
                </div>
            `).join('');
            
            // Bind click events
            resultsContainer.querySelectorAll('.search-result-item').forEach(item => {
                item.addEventListener('click', () => {
                    const slug = item.dataset.slug;
                    this.loadIndicator(slug);
                    resultsContainer.style.display = 'none';
                    this.container.querySelector('.search-input').value = '';
                });
            });
            
            resultsContainer.style.display = 'block';
        }

        updateStats(series) {
            if (!this.config.showStats || !series || series.length === 0) return;
            
            const values = series.map(point => parseFloat(point[1])).filter(v => !isNaN(v));
            if (values.length === 0) return;
            
            const current = values[values.length - 1];
            const previous = values.length > 1 ? values[values.length - 2] : current;
            const change = current - previous;
            const changePercent = previous !== 0 ? (change / previous) * 100 : 0;
            const min = Math.min(...values);
            const max = Math.max(...values);
            
            const statsPanel = this.container.querySelector('.stats-panel');
            if (statsPanel) {
                statsPanel.style.display = 'block';
                
                const currentStat = statsPanel.querySelector('[data-stat="current"]');
                const changeStat = statsPanel.querySelector('[data-stat="change"]');
                const minStat = statsPanel.querySelector('[data-stat="min"]');
                const maxStat = statsPanel.querySelector('[data-stat="max"]');
                
                if (currentStat) currentStat.textContent = this.formatNumber(current);
                if (changeStat) {
                    changeStat.textContent = `${change >= 0 ? '+' : ''}${this.formatNumber(change)} (${changePercent.toFixed(1)}%)`;
                    changeStat.className = `stat-value ${change >= 0 ? 'positive' : 'negative'}`;
                }
                if (minStat) minStat.textContent = this.formatNumber(min);
                if (maxStat) maxStat.textContent = this.formatNumber(max);
            }
        }

        formatNumber(num) {
            if (typeof num !== 'number' || isNaN(num)) return '-';
            
            if (Math.abs(num) >= 1e9) {
                return (num / 1e9).toFixed(1) + 'B';
            } else if (Math.abs(num) >= 1e6) {
                return (num / 1e6).toFixed(1) + 'M';
            } else if (Math.abs(num) >= 1e3) {
                return (num / 1e3).toFixed(1) + 'K';
            } else {
                return num.toFixed(2);
            }
        }

        // Comparison functionality
        loadComparisonIndicators(indicators) {
            indicators.forEach(slug => {
                if (slug && slug !== this.config.defaultIndicator) {
                    this.addComparison(slug);
                }
            });
        }

        async addComparison(slug) {
            try {
                const data = await this.fetchIndicatorData(slug);
                if (data && !this.state.comparisonIndicators.find(i => i.slug === slug)) {
                    this.state.comparisonIndicators.push({
                        slug: slug,
                        name: data.indicator.name,
                        data: data
                    });
                    
                    this.renderComparisonChart();
                    this.updateComparisonList();
                }
            } catch (error) {
                console.error('Error adding comparison:', error);
            }
        }

        renderComparisonChart() {
            if (this.state.comparisonIndicators.length === 0) {
                return this.renderChart(); // Fall back to single indicator
            }
            
            const canvas = this.container.querySelector(`#${this.containerId}-chart`);
            if (!canvas) return;

            if (this.chart) {
                this.chart.destroy();
            }

            const ctx = canvas.getContext('2d');
            const colors = ['#00bcd4', '#ff6b35', '#4caf50', '#9c27b0', '#ff9800'];
            
            const datasets = [];
            
            // Add main indicator
            if (this.state.chartData) {
                datasets.push({
                    label: this.state.currentIndicator?.name || 'Primary',
                    data: this.state.chartData.series.map(point => ({ x: point[0], y: point[1] })),
                    borderColor: colors[0],
                    backgroundColor: 'transparent',
                    tension: 0.4
                });
            }
            
            // Add comparison indicators
            this.state.comparisonIndicators.forEach((indicator, index) => {
                datasets.push({
                    label: indicator.name,
                    data: indicator.data.series.map(point => ({ x: point[0], y: point[1] })),
                    borderColor: colors[(index + 1) % colors.length],
                    backgroundColor: 'transparent',
                    tension: 0.4
                });
            });

            const chartConfig = {
                type: 'line',
                data: { datasets },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top'
                        }
                    },
                    scales: {
                        x: {
                            type: 'time',
                            time: {
                                unit: this.getTimeUnit()
                            }
                        },
                        y: {
                            beginAtZero: false
                        }
                    }
                }
            };

            this.chart = new Chart(ctx, chartConfig);
        }

        updateComparisonList() {
            const listContainer = this.container.querySelector('.comparison-list');
            if (!listContainer) return;
            
            listContainer.innerHTML = this.state.comparisonIndicators.map(indicator => `
                <div class="comparison-item" data-slug="${indicator.slug}">
                    <div class="comparison-name">${indicator.name}</div>
                    <button class="btn-remove-comparison">×</button>
                </div>
            `).join('');
            
            // Bind remove events
            listContainer.querySelectorAll('.btn-remove-comparison').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    const item = e.target.closest('.comparison-item');
                    const slug = item.dataset.slug;
                    this.removeComparison(slug);
                });
            });
        }

        removeComparison(slug) {
            this.state.comparisonIndicators = this.state.comparisonIndicators.filter(i => i.slug !== slug);
            this.renderComparisonChart();
            this.updateComparisonList();
        }

        showIndicatorSearch() {
            // Simple implementation - use existing search or create modal
            this.toggleSearch();
            const searchInput = this.container.querySelector('.search-input');
            if (searchInput) {
                searchInput.focus();
                searchInput.placeholder = 'Search indicators to compare...';
            }
        }
    }

    // Global initialization function
    window.ZCZestraDashboard = {
        init: function(containerId, config) {
            return new ZestraDashboard(containerId, config);
        },
        
        // Utility function for manual initialization
        create: function(containerId, config) {
            return this.init(containerId, config);
        }
    };

    // Auto-initialize dashboards on page load if needed
    document.addEventListener('DOMContentLoaded', function() {
        // Auto-initialize any containers with data-zestra-dashboard attribute
        document.querySelectorAll('[data-zestra-dashboard]').forEach(container => {
            try {
                const config = JSON.parse(container.dataset.config || '{}');
                window.ZCZestraDashboard.init(container.id, config);
            } catch (error) {
                console.error('Failed to auto-initialize dashboard:', error);
            }
        });
    });

})();