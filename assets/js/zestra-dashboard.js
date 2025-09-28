/**
 * Zestra Capital - Modern Dashboard JavaScript (EXACT WORKING VERSION)
 * WordPress Integration Version
 */

(function() {
    'use strict';

    // WordPress configuration
    const wpConfig = window.zcDmtConfig || {};
    
    /**
     * Zestra Dashboard Class
     */
    class ZestraDashboard {
        constructor(container, config) {
            this.container = container;
            this.config = config;
            this.chart = null;
            this.chartDataStore = {
                primary: { full: [], current: [], title: '', lastUpdate: null },
                secondary: { full: [], current: [], title: '' }
            };
            this.currentChartType = 'line';
            this.currentTheme = 'light';
            this.compareItems = [];
            this.searchDebounceTimer = null;
            
            this.init();
        }

        init() {
            this.render();
            // Apply default timeframe from shortcode before any loads
            this.setInitialTimeframeFromConfig();
            this.bindEvents();
            this.loadDefaultIndicator();
        }

        // Map config defaultTimeRange (e.g., 6M, 2Y, ALL) to UI state
        setInitialTimeframeFromConfig() {
            const raw = (this.config && this.config.defaultTimeRange ? String(this.config.defaultTimeRange) : '').toUpperCase();
            if (!raw) return;
            let match = null;
            if (raw === 'ALL') match = 'all';
            else if (raw.endsWith('Y')) match = String(parseInt(raw, 10) || 5);
            else if (raw.endsWith('M')) {
                const m = parseInt(raw, 10) || 6;
                match = (m / 12).toString();
            }
            if (!match) return;
            const btns = this.container.querySelectorAll('.zd-tf-btn');
            btns.forEach(b => b.classList.remove('active'));
            const target = this.container.querySelector(`.zd-tf-btn[data-range="${match}"]`);
            if (target) target.classList.add('active');
        }

        render() {
            const dashboardHTML = `
                <div class="zc-zestra-dashboard" data-theme="${this.currentTheme}">
                    <!-- Header -->
                    <header class="zd-header">
                        <div class="zd-header-content">
                            <div class="zd-brand-section">
                                <div class="zd-brand-text">
                                    <h1>${this.config.title || 'Zestra Capital - Economic Analytics'}</h1>
                                    <span>${this.config.description || 'Professional Economic Data Visualization & Analysis Platform'}</span>
                                </div>
                            </div>
                            
                            <div class="zd-header-controls">
                                ${this.config.showSearch !== false ? this.renderSearchControl() : ''}
                                
                                <div class="zd-control-buttons">
                                    ${this.config.showThemeToggle !== false ? '<button class="zd-control-btn" id="zd-theme-toggle" title="Toggle Theme"><svg class="zd-sun-icon" viewBox="0 0 24 24" fill="currentColor"><path d="M12 7c-2.76 0-5 2.24-5 5s2.24 5 5 5 5-2.24 5-5-2.24-5-5-5z"/></svg><svg class="zd-moon-icon" style="display: none;" viewBox="0 0 24 24" fill="currentColor"><path d="M12.009 24a12.067 12.067 0 0 1-8.466-3.543"/></svg></button>' : ''}
                                    ${this.config.showFullscreen !== false ? '<button class="zd-control-btn" id="zd-fullscreen-toggle" title="Fullscreen"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M7 14H5v5h5v-2H7v-3zm-2-4h2V7h3V5H5v5zm12 7h-3v2h5v-5h-2v3zM14 5v2h3v3h2V5h-5z"/></svg></button>' : ''}
                                </div>
                            </div>
                        </div>
                    </header>

                    <!-- Main Content -->
                    <main class="zd-main">
                        <div class="zd-chart-wrapper">
                            <!-- Chart Controls -->
                            <div class="zd-chart-controls">
                                <div class="zd-chart-info">
                                    <h2 id="zd-chart-title">Loading Default Indicator...</h2>
                                    <div class="zd-chart-meta">
                                        <span id="zd-last-update" class="zd-last-update">Loading data...</span>
                                    </div>
                                </div>
                                
                                <div class="zd-control-group">
                                    ${this.config.showChartTypes !== false ? this.renderChartTypes() : ''}
                                    ${this.config.showComparison !== false ? '<button id="zd-compare-btn" class="zd-compare-btn"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2z"/></svg>Add Comparison</button>' : ''}
                                </div>
                            </div>

                            <!-- Historical Change Cards -->
                            <div class="zd-stats-section">
                                <div class="zd-stats-grid">
                                    <div class="zd-stat-card">
                                        <span class="zd-stat-label">3M Change</span>
                                        <span id="zd-3m-change" class="zd-stat-value">--</span>
                                    </div>
                                    <div class="zd-stat-card">
                                        <span class="zd-stat-label">6M Change</span>
                                        <span id="zd-6m-change" class="zd-stat-value">--</span>
                                    </div>
                                    <div class="zd-stat-card">
                                        <span class="zd-stat-label">1Y Change</span>
                                        <span id="zd-1y-change" class="zd-stat-value">--</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Timeframe Selection -->
                            ${this.config.showTimeframes !== false ? this.renderTimeframes() : ''}

                            <!-- Chart Container -->
                            <div class="zd-chart-container">
                                <canvas id="zd-main-chart" class="zd-main-chart"></canvas>
                                <div class="zd-watermark">Zestra Capital Analytics</div>
                                
                                <div id="zd-loading" class="zd-loading" style="display: flex;">
                                    <div class="zd-loading-backdrop"></div>
                                    <div class="zd-loading-content">
                                        <div class="zd-spinner"></div>
                                        <span class="zd-loading-text">Loading economic indicator...</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Comparison Sidebar -->
                        ${this.config.showComparison !== false ? this.renderComparisonSidebar() : ''}
                    </main>

                    <!-- Compare Modal -->
                    ${this.config.showComparison !== false ? this.renderCompareModal() : ''}
                </div>
            `;

            this.container.innerHTML = dashboardHTML;
        }

        renderSearchControl() {
            return `
                <div class="zd-search-container">
                    <button id="zd-search-toggle" class="zd-search-btn">
                        <svg viewBox="0 0 24 24" fill="currentColor">
                            <path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/>
                        </svg>
                        <span>Search Indicators</span>
                    </button>
                    
                    <div id="zd-search-panel" class="zd-search-panel">
                        <div class="zd-search-box">
                            <input type="text" id="zd-search-input" class="zd-search-input" placeholder="Search economic indicators...">
                            <button class="zd-search-clear">×</button>
                        </div>
                        <div id="zd-search-results" class="zd-search-results"></div>
                    </div>
                </div>
            `;
        }

        renderChartTypes() {
            return `
                <div class="zd-chart-types">
                    <button id="zd-line-chart" class="zd-chart-type active" data-type="line" title="Line Chart">
                        <svg viewBox="0 0 24 24" fill="currentColor">
                            <path d="M3.5 18.49l6-6.01 4 4L22 6.92l-1.41-1.41-7.09 7.97-4-4L2 16.99z"/>
                        </svg>
                    </button>
                    <button id="zd-bar-chart" class="zd-chart-type" data-type="bar" title="Bar Chart">
                        <svg viewBox="0 0 24 24" fill="currentColor">
                            <path d="M5 9.2h3V19H5zM10.6 5h2.8v14h-2.8zm5.6 8H19v6h-2.8z"/>
                        </svg>
                    </button>
                </div>
            `;
        }

        renderTimeframes() {
            return `
                <div class="zd-timeframe-section">
                    <label class="zd-timeframe-label">Time Period:</label>
                    <div class="zd-timeframe-buttons">
                        <button class="zd-tf-btn" data-range="0.5">6M</button>
                        <button class="zd-tf-btn" data-range="1">1Y</button>
                        <button class="zd-tf-btn" data-range="2">2Y</button>
                        <button class="zd-tf-btn" data-range="3">3Y</button>
                        <button class="zd-tf-btn active" data-range="5">5Y</button>
                        <button class="zd-tf-btn" data-range="10">10Y</button>
                        <button class="zd-tf-btn" data-range="15">15Y</button>
                        <button class="zd-tf-btn" data-range="20">20Y</button>
                        <button class="zd-tf-btn" data-range="all">All</button>
                    </div>
                </div>
            `;
        }

        renderComparisonSidebar() {
            return `
                <aside id="zd-comparison-sidebar" class="zd-comparison-sidebar">
                    <div class="zd-sidebar-header">
                        <h3>Comparison Data</h3>
                        <button id="zd-close-sidebar" class="zd-close-sidebar">×</button>
                    </div>
                    <div class="zd-sidebar-content">
                        <div id="zd-comparison-list" class="zd-comparison-list">
                            <div class="zd-comparison-hint">
                                <svg viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/>
                                </svg>
                                <span>Add a comparison indicator to analyze trends</span>
                            </div>
                        </div>
                    </div>
                </aside>
            `;
        }

        renderCompareModal() {
            return `
                <div id="zd-compare-modal" class="zd-modal" style="display: none;">
                    <div class="zd-modal-overlay"></div>
                    <div class="zd-modal-content">
                        <div class="zd-modal-header">
                            <h3>Add Comparison Indicator</h3>
                            <button id="zd-close-compare-modal" class="zd-modal-close">×</button>
                        </div>
                        <div class="zd-modal-body">
                            <input type="text" id="zd-compare-search-input" class="zd-compare-search-input" placeholder="Search for indicators to compare...">
                            <ul id="zd-compare-search-results" class="zd-modal-results"></ul>
                        </div>
                    </div>
                </div>
            `;
        }

        bindEvents() {
            // Theme toggle
            const themeToggle = this.container.querySelector('#zd-theme-toggle');
            if (themeToggle) {
                themeToggle.addEventListener('click', () => {
                    const dashboard = this.container.querySelector('.zc-zestra-dashboard');
                    dashboard.classList.toggle('dark-theme');
                    const isDark = dashboard.classList.contains('dark-theme');
                    
                    const sunIcon = this.container.querySelector('.zd-sun-icon');
                    const moonIcon = this.container.querySelector('.zd-moon-icon');
                    if (sunIcon) sunIcon.style.display = isDark ? 'none' : 'block';
                    if (moonIcon) moonIcon.style.display = isDark ? 'block' : 'none';
                    
                    this.currentTheme = isDark ? 'dark' : 'light';
                    if (this.chart) this.createOrUpdateChart();
                });
            }

            // Fullscreen toggle
            const fullscreenToggle = this.container.querySelector('#zd-fullscreen-toggle');
            if (fullscreenToggle) {
                fullscreenToggle.addEventListener('click', () => {
                    const dashboard = this.container.querySelector('.zc-zestra-dashboard');
                    if (!document.fullscreenElement) {
                        dashboard.requestFullscreen().catch(err => console.error('Fullscreen error:', err));
                    } else {
                        document.exitFullscreen();
                    }
                });
            }

            // Search functionality
            this.bindSearchEvents();

            // Chart type controls
            this.bindChartTypeEvents();

            // Timeframe controls
            this.bindTimeframeEvents();

            // Comparison functionality
            this.bindComparisonEvents();
        }

        bindSearchEvents() {
            const searchToggle = this.container.querySelector('#zd-search-toggle');
            const searchPanel = this.container.querySelector('#zd-search-panel');
            const searchInput = this.container.querySelector('#zd-search-input');
            const searchResults = this.container.querySelector('#zd-search-results');
            const searchClear = this.container.querySelector('.zd-search-clear');

            if (searchToggle && searchPanel && searchInput) {
                searchToggle.addEventListener('click', (e) => {
                    e.stopPropagation();
                    searchPanel.classList.toggle('active');
                    if (searchPanel.classList.contains('active')) {
                        searchInput.focus();
                    }
                });

                searchInput.addEventListener('input', () => {
                    clearTimeout(this.searchDebounceTimer);
                    this.searchDebounceTimer = setTimeout(() => {
                        this.performSearch(searchInput.value, false);
                    }, 300);
                });

                if (searchClear) {
                    searchClear.addEventListener('click', () => {
                        searchInput.value = '';
                        searchResults.innerHTML = '';
                    });
                }
            }
        }

        bindChartTypeEvents() {
            const lineChart = this.container.querySelector('#zd-line-chart');
            const barChart = this.container.querySelector('#zd-bar-chart');

            if (lineChart) {
                lineChart.addEventListener('click', () => {
                    this.container.querySelectorAll('.zd-chart-type').forEach(btn => btn.classList.remove('active'));
                    lineChart.classList.add('active');
                    this.currentChartType = 'line';
                    this.createOrUpdateChart();
                });
            }

            if (barChart) {
                barChart.addEventListener('click', () => {
                    this.container.querySelectorAll('.zd-chart-type').forEach(btn => btn.classList.remove('active'));
                    barChart.classList.add('active');
                    this.currentChartType = 'bar';
                    this.createOrUpdateChart();
                });
            }
        }

        bindTimeframeEvents() {
            const timeframeButtons = this.container.querySelectorAll('.zd-tf-btn');
            timeframeButtons.forEach(button => {
                button.addEventListener('click', () => {
                    timeframeButtons.forEach(btn => btn.classList.remove('active'));
                    button.classList.add('active');
                    this.applyTimeframeFilter(button.dataset.range);
                });
            });
        }

        bindComparisonEvents() {
            const compareBtn = this.container.querySelector('#zd-compare-btn');
            const compareModal = this.container.querySelector('#zd-compare-modal');
            const closeCompareModal = this.container.querySelector('#zd-close-compare-modal');
            const compareSearchInput = this.container.querySelector('#zd-compare-search-input');
            const closeSidebar = this.container.querySelector('#zd-close-sidebar');

            if (compareBtn && compareModal) {
                compareBtn.addEventListener('click', () => {
                    compareModal.style.display = 'flex';
                    if (compareSearchInput) compareSearchInput.focus();
                });
            }

            if (closeCompareModal && compareModal) {
                closeCompareModal.addEventListener('click', () => {
                    compareModal.style.display = 'none';
                });
            }

            if (closeSidebar) {
                closeSidebar.addEventListener('click', () => {
                    const sidebar = this.container.querySelector('#zd-comparison-sidebar');
                    if (sidebar) sidebar.classList.remove('active');
                });
            }

            if (compareSearchInput) {
                compareSearchInput.addEventListener('input', () => {
                    clearTimeout(this.searchDebounceTimer);
                    this.searchDebounceTimer = setTimeout(() => {
                        this.performSearch(compareSearchInput.value, true);
                    }, 300);
                });
            }

            // Close modal when clicking outside
            document.addEventListener('click', (e) => {
                const searchPanel = this.container.querySelector('#zd-search-panel');
                const searchToggle = this.container.querySelector('#zd-search-toggle');
                
                if (searchPanel && !searchPanel.contains(e.target) && e.target !== searchToggle) {
                    searchPanel.classList.remove('active');
                }
                
                if (compareModal && (e.target === compareModal || e.target.classList.contains('zd-modal-overlay'))) {
                    compareModal.style.display = 'none';
                }
            });
        }

        async loadDefaultIndicator() {
            // Build a list of candidates: defaultIndicator (if any) then all available slugs
            const candidates = [];
            if (this.config && this.config.defaultIndicator) {
                candidates.push(String(this.config.defaultIndicator));
            }
            const list = Array.isArray(wpConfig.indicators) ? wpConfig.indicators : [];
            list.forEach(i => {
                if (i && i.slug && !candidates.includes(i.slug)) candidates.push(i.slug);
            });

            // Try each candidate until one returns data
            for (let i = 0; i < candidates.length; i++) {
                const slug = candidates[i];
                const ok = await this.tryLoadIndicator(slug);
                if (ok) return;
            }

            // Nothing worked — show empty state instead of hard error
            this.showEmptyState();
        }

        async fetchIndicatorData(slug) {
            // Construct REST API URL
            // Expected format: wpConfig.restUrl + 'data/' + slug + '?access_key=' + wpConfig.apiKey
            const accessKey = this.config.accessKey || wpConfig.apiKey; // Fallback to wpConfig.apiKey if not in shortcode config
            if (!accessKey) {
                throw new Error('API key is required to fetch data via REST API but none was provided.');
            }
            const restUrl = wpConfig.restUrl + `data/${slug}?access_key=${encodeURIComponent(accessKey)}`;
            
            const response = await fetch(restUrl, {
                method: 'GET',
                headers: { 
                    'Content-Type': 'application/json'
                }
            });
            
            const json = await response.json();
            // Check for 'status' => 'success' instead of 'success' => true
            if (!json || json.status !== 'success' || !json.data || !Array.isArray(json.data.series) || json.data.series.length === 0) {
                // Log the full response for debugging if it fails the check
                console.error('API response did not meet success criteria:', json);
                throw new Error('No data');
            }
            return json.data;
        }

        async tryLoadIndicator(slug) {
            const loading = this.container.querySelector('#zd-loading');
            const chartTitle = this.container.querySelector('#zd-chart-title');
            try {
                if (loading) loading.style.display = 'flex';
                if (chartTitle) chartTitle.textContent = 'Loading...';

                const data = await this.fetchIndicatorData(slug);
                const indicator = data.indicator;
                const series = data.series;

                const formattedData = series.map(point => ({
                    x: new Date(point[0]),
                    y: parseFloat(point[1])
                }));

                this.chartDataStore.primary = {
                    full: formattedData,
                    current: [...formattedData],
                    title: indicator.name,
                    lastUpdate: formattedData[formattedData.length - 1].x
                };

                // Clear secondary and comparisons when switching primary
                this.chartDataStore.secondary = { full: [], current: [], title: '' };
                this.compareItems = [];
                this.updateComparisonSidebar();

                // Apply current timeframe
                const activeBtn = this.container.querySelector('.zd-tf-btn.active');
                if (activeBtn) {
                    this.applyTimeframeFilter(activeBtn.dataset.range);
                }

                this.createOrUpdateChart();
                if (loading) loading.style.display = 'none';
                return true;
            } catch (e) {
                if (loading) loading.style.display = 'none';
                console.error('Error loading indicator:', e);
                return false;
            }
        }

        async loadIndicator(slug) {
            // First, try the requested slug
            const ok = await this.tryLoadIndicator(slug);
            if (ok) return;

            // Fallback: try other available indicators from config
            const candidates = [];
            const list = Array.isArray(wpConfig.indicators) ? wpConfig.indicators : [];
            list.forEach(i => {
                if (i && i.slug && i.slug !== slug && !candidates.includes(i.slug)) {
                    candidates.push(i.slug);
                }
            });

            for (let i = 0; i < candidates.length; i++) {
                if (await this.tryLoadIndicator(candidates[i])) return;
            }

            // Still nothing — show empty state and a concise error
            const chartTitle = this.container.querySelector('#zd-chart-title');
            if (chartTitle) chartTitle.textContent = 'Error loading data: No data available for this indicator';
            this.showEmptyState();
        }

        async performSearch(query, isCompare = false) {
            if (query.length < 2) return;

            const indicators = wpConfig.indicators || [];
            const filtered = indicators.filter(indicator => 
                indicator.name.toLowerCase().includes(query.toLowerCase()) ||
                indicator.slug.toLowerCase().includes(query.toLowerCase()) ||
                indicator.source_type.toLowerCase().includes(query.toLowerCase())
            );

            const resultsContainer = isCompare ? 
                this.container.querySelector('#zd-compare-search-results') :
                this.container.querySelector('#zd-search-results');

            if (!resultsContainer) return;

            resultsContainer.innerHTML = '';

            if (filtered.length > 0) {
                filtered.slice(0, 20).forEach(indicator => {
                    const li = document.createElement('li');
                    li.textContent = indicator.name;
                    li.addEventListener('click', () => {
                        if (isCompare) {
                            this.addComparison(indicator.slug, indicator.name);
                            const modal = this.container.querySelector('#zd-compare-modal');
                            if (modal) modal.style.display = 'none';
                            const input = this.container.querySelector('#zd-compare-search-input');
                            if (input) input.value = '';
                        } else {
                            this.loadIndicator(indicator.slug);
                            const panel = this.container.querySelector('#zd-search-panel');
                            if (panel) panel.classList.remove('active');
                            const input = this.container.querySelector('#zd-search-input');
                            if (input) input.value = '';
                        }
                    });
                    resultsContainer.appendChild(li);
                });
            } else {
                const li = document.createElement('li');
                li.textContent = 'No results found';
                li.style.opacity = '0.6';
                resultsContainer.appendChild(li);
            }
        }

        async addComparison(slug, name) {
            // Only allow 1 comparison (like working version)
            if (this.compareItems.length >= 1) {
                alert('Only 1 comparison indicator allowed');
                return;
            }

            try {
                // Construct REST API URL for comparison
                const accessKey = this.config.accessKey || wpConfig.apiKey; // Fallback to wpConfig.apiKey if not in shortcode config
                if (!accessKey) {
                    throw new Error('API key is required to fetch data via REST API but none was provided.');
                }
                const restUrl = wpConfig.restUrl + `data/${slug}?access_key=${encodeURIComponent(accessKey)}`;
                
                const response = await fetch(restUrl, {
                    method: 'GET',
                    headers: {
                        'Content-Type': 'application/json',
                    }
                });

                const data = await response.json();
                
                // Check for 'status' => 'success' instead of 'success' => true
                if (data.status === 'success' && data.data.series && data.data.series.length > 0) {
                    const series = data.data.series;
                    const formattedData = series.map(point => ({
                        x: new Date(point[0]),
                        y: parseFloat(point[1])
                    }));

                    this.chartDataStore.secondary = {
                        full: formattedData,
                        current: [...formattedData],
                        title: name
                    };

                    this.compareItems = [{ title: name, seriesId: slug, visible: true }];

                    // Apply current timeframe
                    const activeBtn = this.container.querySelector('.zd-tf-btn.active');
                    if (activeBtn) {
                        this.applyTimeframeFilter(activeBtn.dataset.range);
                    }

                    this.updateComparisonSidebar();
                    this.createOrUpdateChart();
                } else {
                    // Log the full response for debugging if it fails the check
                    console.error('Comparison API response did not meet success criteria:', data);
                }
            } catch (error) {
                console.error('Error adding comparison:', error);
            }
        }

        updateComparisonSidebar() {
            const sidebar = this.container.querySelector('#zd-comparison-sidebar');
            const comparisonList = this.container.querySelector('#zd-comparison-list');
            
            if (!sidebar || !comparisonList) return;

            if (this.compareItems.length > 0) {
                sidebar.classList.add('active');
                comparisonList.innerHTML = '';
                
                this.compareItems.forEach((item, index) => {
                    const div = document.createElement('div');
                    div.className = 'zd-comparison-item';
                    div.innerHTML = `
                        <span style="opacity: ${item.visible ? 1 : 0.5}">${item.title}</span>
                        <div class="zd-comparison-actions">
                            <button class="zd-comparison-action" data-action="toggle" data-index="${index}" title="${item.visible ? 'Hide' : 'Show'}">
                                <svg viewBox="0 0 24 24" fill="currentColor">
                                    <path d="${item.visible ? 'M12 7c2.76 0 5 2.24 5 5 0 .65-.13 1.26-.36 1.83l2.92 2.92c1.51-1.26 2.7-2.89 3.43-4.75-1.73-4.39-6-7.5-11-7.5-1.4 0-2.74.25-3.98.7l2.16 2.16C10.74 7.13 11.35 7 12 7z' : 'M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z'}"/>
                                </svg>
                            </button>
                            <button class="zd-comparison-action" data-action="remove" data-index="${index}" title="Remove">
                                <svg viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
                                </svg>
                            </button>
                        </div>
                    `;
                    
                    // Bind action events
                    div.querySelectorAll('.zd-comparison-action').forEach(btn => {
                        btn.addEventListener('click', (e) => {
                            const action = e.currentTarget.dataset.action;
                            const index = parseInt(e.currentTarget.dataset.index);
                            
                            if (action === 'toggle') {
                                this.compareItems[index].visible = !this.compareItems[index].visible;
                                if (this.compareItems[index].visible) {
                                    this.createOrUpdateChart();
                                } else {
                                    this.chartDataStore.secondary = { full: [], current: [], title: '' };
                                    this.createOrUpdateChart();
                                }
                                this.updateComparisonSidebar();
                            } else if (action === 'remove') {
                                this.compareItems.splice(index, 1);
                                this.chartDataStore.secondary = { full: [], current: [], title: '' };
                                this.updateComparisonSidebar();
                                this.createOrUpdateChart();
                            }
                        });
                    });
                    
                    comparisonList.appendChild(div);
                });
            } else {
                sidebar.classList.remove('active');
            }
        }

        applyTimeframeFilter(rangeInYears) {
            const filterData = (fullData) => {
                if (fullData.length === 0) return [];
                if (rangeInYears === 'all') return [...fullData];
                
                const months = parseFloat(rangeInYears) * 12;
                const lastDataPointDate = fullData[fullData.length - 1].x;
                const startDate = new Date(lastDataPointDate);
                startDate.setMonth(startDate.getMonth() - months);
                
                return fullData.filter(d => d.x >= startDate);
            };

            if (this.chartDataStore.primary.full.length > 0) {
                this.chartDataStore.primary.current = filterData(this.chartDataStore.primary.full);
            }

            if (this.chartDataStore.secondary.full.length > 0) {
                this.chartDataStore.secondary.current = filterData(this.chartDataStore.secondary.full);
            }
            
            this.createOrUpdateChart();
        }

        createOrUpdateChart() {
            const canvas = this.container.querySelector('#zd-main-chart');
            const chartTitle = this.container.querySelector('#zd-chart-title');
            const lastUpdate = this.container.querySelector('#zd-last-update');
            const loading = this.container.querySelector('#zd-loading');
            
            if (!canvas) return;

            const primaryData = this.chartDataStore.primary.current;
            const secondaryData = this.chartDataStore.secondary.current;
            const hasSecondary = secondaryData && secondaryData.length > 0;
            
            if (!primaryData || primaryData.length === 0) {
                if (chartTitle) chartTitle.textContent = 'No data available';
                if (loading) loading.style.display = 'none';
                return;
            }

            // Destroy existing chart
            if (this.chart) {
                this.chart.destroy();
            }

            // Update UI elements
            if (chartTitle) chartTitle.textContent = this.chartDataStore.primary.title;
            if (lastUpdate) this.updateLastUpdate(this.chartDataStore.primary.lastUpdate);
            this.updateHistoricalStats(primaryData);

            // Get theme colors
            const themes = {
                light: {
                    gridColor: 'rgba(0, 0, 0, 0.05)',
                    textColor: '#5b7083',
                    tooltipBg: 'rgba(255, 255, 255, 0.95)',
                    tooltipText: '#14171a',
                    line1: '#00BCD4',
                    line2: '#FF5722',
                    barBg: 'rgba(0, 188, 212, 0.8)'
                },
                dark: {
                    gridColor: 'rgba(255, 255, 255, 0.08)',
                    textColor: '#8899a6',
                    tooltipBg: 'rgba(21, 32, 43, 0.95)',
                    tooltipText: '#ffffff',
                    line1: '#26C6DA',
                    line2: '#FF7043',
                    barBg: 'rgba(38, 198, 218, 0.8)'
                }
            };

            const currentTheme = themes[this.currentTheme] || themes.light;

            function getPaddedRange(dataArray) {
                if (!dataArray || dataArray.length === 0) return { min: 0, max: 0 };
                
                const values = dataArray.map(item => parseFloat(item.y));
                const dataMin = Math.min(...values);
                const dataMax = Math.max(...values);
                const range = dataMax - dataMin;
                const padding = Math.max(range * 0.08, Math.abs(dataMax) * 0.02);
                
                return {
                    min: dataMin - padding,
                    max: dataMax + padding
                };
            }

            const primaryRange = getPaddedRange(primaryData);
            const secondaryRange = hasSecondary ? getPaddedRange(secondaryData) : null;

            const datasets = [{
                label: this.chartDataStore.primary.title,
                data: primaryData,
                borderColor: currentTheme.line1,
                backgroundColor: this.currentChartType === 'bar' ? currentTheme.barBg : `${currentTheme.line1}15`,
                borderWidth: 3,
                fill: false,
                type: this.currentChartType === 'bar' ? 'bar' : 'line',
                yAxisID: 'y',
                pointRadius: 0,
                pointHoverRadius: 6,
                tension: 0.3,
            }];

            if (hasSecondary) {
                datasets.push({
                    label: this.chartDataStore.secondary.title,
                    data: secondaryData,
                    borderColor: currentTheme.line2,
                    backgroundColor: `${currentTheme.line2}15`,
                    borderWidth: 3,
                    fill: false,
                    type: 'line',
                    yAxisID: 'y1',
                    pointRadius: 0,
                    pointHoverRadius: 6,
                    tension: 0.3,
                });
            }

            const scales = {
                x: {
                    type: 'time',
                    time: { 
                        unit: 'month', 
                        displayFormats: { month: 'MMM yyyy' }
                    },
                    grid: { 
                        color: currentTheme.gridColor, 
                        drawBorder: false 
                    },
                    ticks: { 
                        color: currentTheme.textColor,
                        maxTicksLimit: 10,
                        font: { size: 11, weight: '500' }
                    },
                    border: { display: false }
                },
                y: {
                    min: primaryRange.min,
                    max: primaryRange.max,
                    grid: { 
                        color: currentTheme.gridColor, 
                        drawBorder: false 
                    },
                    ticks: { 
                        color: currentTheme.textColor,
                        font: { size: 11, weight: '500' },
                        callback: function(value) {
                            return value.toLocaleString();
                        }
                    },
                    position: 'left',
                    border: { display: false }
                }
            };

            if (hasSecondary) {
                scales.y1 = {
                    type: 'linear',
                    display: true,
                    position: 'right',
                    min: secondaryRange.min,
                    max: secondaryRange.max,
                    grid: { drawOnChartArea: false },
                    ticks: { 
                        color: currentTheme.textColor,
                        font: { size: 11, weight: '500' },
                        callback: function(value) {
                            return value.toLocaleString();
                        }
                    },
                    border: { display: false }
                };
            }

            // Create chart
            const ctx = canvas.getContext('2d');
            this.chart = new Chart(ctx, {
                type: this.currentChartType === 'bar' ? 'bar' : 'line',
                data: { datasets },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    plugins: {
                        legend: {
                            display: hasSecondary,
                            position: 'top',
                            align: 'start',
                            labels: { 
                                color: currentTheme.textColor,
                                usePointStyle: true,
                                padding: 20,
                                font: { size: 12, weight: '600' }
                            }
                        },
                        tooltip: {
                            backgroundColor: currentTheme.tooltipBg,
                            titleColor: currentTheme.tooltipText,
                            bodyColor: currentTheme.tooltipText,
                            borderWidth: 0,
                            cornerRadius: 12,
                            padding: 16,
                            titleFont: { size: 13, weight: '600' },
                            bodyFont: { size: 12, weight: '500' },
                            callbacks: {
                                label: function(context) {
                                    return `${context.dataset.label}: ${context.parsed.y.toLocaleString()}`;
                                }
                            }
                        }
                    },
                    scales,
                    elements: {
                        line: { tension: 0.3 },
                        point: { hoverBorderWidth: 3 }
                    }
                }
            });

            if (loading) loading.style.display = 'none';
        }

        updateHistoricalStats(data) {
            const change3M = this.container.querySelector('#zd-3m-change');
            const change6M = this.container.querySelector('#zd-6m-change');
            const change1Y = this.container.querySelector('#zd-1y-change');

            if (!data || data.length === 0) {
                if (change3M) change3M.textContent = '--';
                if (change6M) change6M.textContent = '--';
                if (change1Y) change1Y.textContent = '--';
                return;
            }

            const currentDate = new Date();
            const current = data[data.length - 1].y;
            
            // Find values for 3M, 6M, 1Y ago
            const threeMonthsAgo = new Date(currentDate.setMonth(currentDate.getMonth() - 3));
            const sixMonthsAgo = new Date(currentDate.setMonth(currentDate.getMonth() - 3)); // -6 from original
            const oneYearAgo = new Date(currentDate.setMonth(currentDate.getMonth() - 6)); // -12 from original
            
            function findValueForDate(targetDate) {
                let closest = data[0];
                let minDiff = Math.abs(new Date(data[0].x) - targetDate);
                
                for (let i = 1; i < data.length; i++) {
                    const diff = Math.abs(new Date(data[i].x) - targetDate);
                    if (diff < minDiff) {
                        minDiff = diff;
                        closest = data[i];
                    }
                }
                return closest.y;
            }
            
            const threeMonthValue = findValueForDate(threeMonthsAgo);
            const sixMonthValue = findValueForDate(sixMonthsAgo);
            const oneYearValue = findValueForDate(oneYearAgo);
            
            // Calculate percentage changes
            const change3MPercent = ((current - threeMonthValue) / threeMonthValue * 100);
            const change6MPercent = ((current - sixMonthValue) / sixMonthValue * 100);
            const change1YPercent = ((current - oneYearValue) / oneYearValue * 100);
            
            if (change3M) {
                change3M.textContent = `${change3MPercent >= 0 ? '+' : ''}${change3MPercent.toFixed(2)}%`;
                change3M.style.color = change3MPercent >= 0 ? '#4CAF50' : '#F44336';
            }
            
            if (change6M) {
                change6M.textContent = `${change6MPercent >= 0 ? '+' : ''}${change6MPercent.toFixed(2)}%`;
                change6M.style.color = change6MPercent >= 0 ? '#4CAF50' : '#F44336';
            }
            
            if (change1Y) {
                change1Y.textContent = `${change1YPercent >= 0 ? '+' : ''}${change1YPercent.toFixed(2)}%`;
                change1Y.style.color = change1YPercent >= 0 ? '#4CAF50' : '#F44336';
            }
        }


        updateLastUpdate(dateStr) {
            const lastUpdate = this.container.querySelector('#zd-last-update');
            if (!lastUpdate || !dateStr) return;
            
            const date = new Date(dateStr);
            const now = new Date();
            const diffTime = Math.abs(now - date);
            const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
            
            let updateText = 'Last updated: ';
            if (diffDays === 1) {
                updateText += 'Yesterday';
            } else if (diffDays < 7) {
                updateText += `${diffDays} days ago`;
            } else {
                updateText += date.toLocaleDateString();
            }
            
            lastUpdate.textContent = updateText;
        }

        showEmptyState() {
            const chartTitle = this.container.querySelector('#zd-chart-title');
            const lastUpdate = this.container.querySelector('#zd-last-update');
            const loading = this.container.querySelector('#zd-loading');
            
            if (chartTitle) chartTitle.textContent = 'No Indicator Selected';
            if (lastUpdate) lastUpdate.textContent = 'Use the search button above to find indicators';
            if (loading) loading.style.display = 'none';
        }

        destroy() {
            if (this.chart) {
                this.chart.destroy();
            }
            this.container.innerHTML = '';
        }
    }

    /**
     * Global Dashboard Manager
     */
    window.ZCZestraDashboard = {
        instances: new Map(),

        init(containerId, config = {}) {
            const container = document.getElementById(containerId);
            if (!container) {
                console.error('Zestra Dashboard: Container not found:', containerId);
                return false;
            }

            // Prevent duplicate initialization
            if (this.instances.has(containerId)) {
                console.warn('Zestra Dashboard: Already initialized for container:', containerId);
                return this.instances.get(containerId);
            }

            try {
                const instance = new ZestraDashboard(container, config);
                this.instances.set(containerId, instance);
                console.log('Zestra Dashboard initialized:', containerId);
                return instance;
            } catch (error) {
                console.error('Zestra Dashboard initialization failed:', error);
                return false;
            }
        },

        destroy(containerId) {
            const instance = this.instances.get(containerId);
            if (instance) {
                instance.destroy();
                this.instances.delete(containerId);
            }
        }
    };

    // Auto-initialize on page load
    // Use a flag to ensure initialization happens only once per container even if DOMContentLoaded fires multiple times
    const initializedContainers = new Set();
    function initializeDashboardContainers() {
        const containers = document.querySelectorAll('.zc-zestra-dashboard-container[data-config]');
        
        containers.forEach(container => {
            if (initializedContainers.has(container.id)) {
                // Skip if already initialized
                return;
            }
            try {
                const config = JSON.parse(container.dataset.config);
                const instance = window.ZCZestraDashboard.init(container.id, config);
                if (instance) {
                    initializedContainers.add(container.id);
                }
            } catch (error) {
                console.error('Auto-initialization failed for container:', container.id, error);
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeDashboardContainers);
    } else {
        // If DOM is already loaded, run immediately
        initializeDashboardContainers();
    }

})();