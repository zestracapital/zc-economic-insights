import React, { useState, useEffect, useRef, useMemo } from 'react';
import { 
  Search, 
  Moon, 
  Sun, 
  Maximize, 
  Minimize, 
  Plus, 
  X, 
  BarChart3, 
  LineChart, 
  AreaChart, 
  ScatterChart,
  ZoomIn,
  ZoomOut,
  Download,
  Eye,
  EyeOff,
  Trash2
} from 'lucide-react';
import Chart from './Chart';
import SearchPanel from './SearchPanel';
import ComparisonSidebar from './ComparisonSidebar';
import { ChartData, Indicator, TimeRange, ChartType, ThemeMode } from './types';
import { useChartData } from './hooks/useChartData';
import { useTheme } from './hooks/useTheme';
import './EconomicDashboard.css';

const TIME_RANGES: { value: TimeRange; label: string }[] = [
  { value: '6M', label: '6M' },
  { value: '1Y', label: '1Y' },
  { value: '2Y', label: '2Y' },
  { value: '3Y', label: '3Y' },
  { value: '5Y', label: '5Y' },
  { value: '10Y', label: '10Y' },
  { value: '15Y', label: '15Y' },
  { value: '20Y', label: '20Y' },
  { value: 'ALL', label: 'All' }
];

const CHART_TYPES: { value: ChartType; label: string; icon: React.ReactNode }[] = [
  { value: 'line', label: 'Line', icon: <LineChart size={18} /> },
  { value: 'bar', label: 'Bar', icon: <BarChart3 size={18} /> },
  { value: 'area', label: 'Area', icon: <AreaChart size={18} /> },
  { value: 'scatter', label: 'Scatter', icon: <ScatterChart size={18} /> }
];

interface EconomicDashboardProps {
  baseUrl?: string;
  accessKey?: string;
  className?: string;
  fullWidth?: boolean;
  config?: ChartConfig;
}

const EconomicDashboard: React.FC<EconomicDashboardProps> = ({
  baseUrl = 'https://client.zestracapital.com/wp-json/zc-dmt/v1',
  accessKey = '',
  className = '',
  fullWidth = false,
  config = {
    mode: 'dynamic',
    showHeader: true,
    showSearch: true,
    showComparison: true,
    showTimeframes: true,
    showChartTypes: true,
    showStats: true,
    showZoomPan: true,
    showFullscreen: true,
    showThemeToggle: true,
    defaultTimeRange: '5Y',
    defaultChartType: 'line',
    height: 600
  }
}) => {
  const [isFullscreen, setIsFullscreen] = useState(false);
  const [showSearch, setShowSearch] = useState(false);
  const [showComparison, setShowComparison] = useState(false);
  const [activeTimeRange, setActiveTimeRange] = useState<TimeRange>('5Y');
  const [activeChartType, setActiveChartType] = useState<ChartType>('line');
  const [comparisonItems, setComparisonItems] = useState<Indicator[]>([]);
  const [selectedIndicator, setSelectedIndicator] = useState<Indicator | null>(null);
  
  const dashboardRef = useRef<HTMLDivElement>(null);
  const { theme, toggleTheme } = useTheme();
  const { chartData, isLoading, error, fetchIndicatorData } = useChartData(baseUrl, accessKey);

  // Handle fullscreen
  const toggleFullscreen = async () => {
    if (!document.fullscreenElement) {
      if (dashboardRef.current?.requestFullscreen) {
        await dashboardRef.current.requestFullscreen();
        setIsFullscreen(true);
      }
    } else {
      await document.exitFullscreen();
      setIsFullscreen(false);
    }
  };

  // Handle escape key
  useEffect(() => {
    const handleEscape = (event: KeyboardEvent) => {
      if (event.key === 'Escape') {
        setShowSearch(false);
      }
    };

    document.addEventListener('keydown', handleEscape);
    return () => document.removeEventListener('keydown', handleEscape);
  }, []);

  // Handle fullscreen change
  useEffect(() => {
    const handleFullscreenChange = () => {
      setIsFullscreen(!!document.fullscreenElement);
    };

    document.addEventListener('fullscreenchange', handleFullscreenChange);
    return () => document.removeEventListener('fullscreenchange', handleFullscreenChange);
  }, []);

  // Select indicator
  const handleSelectIndicator = async (indicator: Indicator) => {
    setSelectedIndicator(indicator);
    setShowSearch(false);
    await fetchIndicatorData(indicator.slug);
  };

  // Add comparison
  const handleAddComparison = (indicator: Indicator) => {
    if (!comparisonItems.find(item => item.slug === indicator.slug) && 
        comparisonItems.length < 10) {
      setComparisonItems(prev => [...prev, indicator]);
      setShowComparison(true);
    }
  };

  // Remove comparison
  const handleRemoveComparison = (slug: string) => {
    setComparisonItems(prev => prev.filter(item => item.slug !== slug));
    if (comparisonItems.length <= 1) {
      setShowComparison(false);
    }
  };

  // Toggle comparison visibility
  const handleToggleComparison = (slug: string) => {
    setComparisonItems(prev => 
      prev.map(item => 
        item.slug === slug 
          ? { ...item, visible: !item.visible }
          : item
      )
    );
  };

  // Filter chart data based on time range
  const filteredChartData = useMemo(() => {
    if (!chartData) return null;
    
    if (activeTimeRange === 'ALL') return chartData;
    
    const now = new Date();
    let startDate = new Date();
    
    switch (activeTimeRange) {
      case '6M':
        startDate.setMonth(now.getMonth() - 6);
        break;
      case '1Y':
        startDate.setFullYear(now.getFullYear() - 1);
        break;
      case '2Y':
        startDate.setFullYear(now.getFullYear() - 2);
        break;
      case '3Y':
        startDate.setFullYear(now.getFullYear() - 3);
        break;
      case '5Y':
        startDate.setFullYear(now.getFullYear() - 5);
        break;
      case '10Y':
        startDate.setFullYear(now.getFullYear() - 10);
        break;
      case '15Y':
        startDate.setFullYear(now.getFullYear() - 15);
        break;
      case '20Y':
        startDate.setFullYear(now.getFullYear() - 20);
        break;
    }
    
    return {
      ...chartData,
      series: chartData.series.filter(([date]) => new Date(date) >= startDate)
    };
  }, [chartData, activeTimeRange]);

  // Calculate description stats
  const descriptionStats = useMemo(() => {
    if (!filteredChartData?.series.length) return null;
    
    const values = filteredChartData.series.map(([, value]) => value);
    const latest = values[values.length - 1];
    const min = Math.min(...values);
    const max = Math.max(...values);
    const avg = values.reduce((sum, val) => sum + val, 0) / values.length;
    
    return {
      latest: latest.toLocaleString(),
      min: min.toLocaleString(),
      max: max.toLocaleString(),
      avg: avg.toLocaleString(),
      dataPoints: values.length
    };
  }, [filteredChartData]);

  return (
    <div 
      ref={dashboardRef}
      className={`economic-dashboard ${theme} ${config.mode === 'static' ? 'static-mode' : ''} ${className}`}
      data-fullscreen={isFullscreen}
      style={{ height: config.mode === 'static' ? `${config.height || 400}px` : 'auto' }}
    >
      {/* Header */}
      {config.showHeader !== false && (
      <header className="dashboard-header">
        <div className="header-content">
          <div className="brand-section">
            <h1>{config.title || 'Zestra Capital - Economic Analytics'}</h1>
            <p>{config.description || 'Professional Economic Data Visualization & Analysis Platform'}</p>
          </div>
          
          <div className="header-controls">
            {config.showSearch !== false && (
            <button
              className="control-btn search-btn"
              onClick={() => setShowSearch(!showSearch)}
              aria-label="Search indicators"
            >
              <Search size={20} />
              <span>Search Indicators</span>
            </button>
            )}
            
            {config.showThemeToggle !== false && (
            <button
              className="control-btn"
              onClick={toggleTheme}
              aria-label="Toggle theme"
            >
              {theme === 'dark' ? <Sun size={20} /> : <Moon size={20} />}
            </button>
            )}
            
            {config.showFullscreen !== false && (
            <button
              className="control-btn"
              onClick={toggleFullscreen}
              aria-label="Toggle fullscreen"
            >
              {isFullscreen ? <Minimize size={20} /> : <Maximize size={20} />}
            </button>
            )}
          </div>
        </div>
      </header>
      )}

      {/* Search Panel */}
      {config.showSearch !== false && (
      <SearchPanel
        isOpen={showSearch}
        onClose={() => setShowSearch(false)}
        onSelectIndicator={handleSelectIndicator}
        onAddComparison={handleAddComparison}
        baseUrl={baseUrl}
        accessKey={accessKey}
      />
      )}

      {/* Main Content */}
      <main className="dashboard-main">
        <div className="chart-section">
          {/* Chart Header */}
          <div className="chart-header">
            <div className="chart-info">
              <h2 className="chart-title">
                {selectedIndicator?.name || 'Select an Economic Indicator'}
              </h2>
              {chartData && (
                <div className="chart-meta">
                  <span className="last-update">
                    Last updated: {new Date(chartData.series[chartData.series.length - 1]?.[0]).toLocaleDateString()}
                  </span>
                  <span className="source-type">
                    Source: {chartData.indicator.source_type.toUpperCase()}
                  </span>
                </div>
              )}
            </div>
            
            {config.mode === 'dynamic' && (
            <div className="chart-controls">
              {/* Chart Type Selector */}
              {config.showChartTypes !== false && (
              <div className="chart-type-selector">
                {CHART_TYPES.map(({ value, label, icon }) => (
                  <button
                    key={value}
                    className={`chart-type-btn ${activeChartType === value ? 'active' : ''}`}
                    onClick={() => setActiveChartType(value)}
                    title={`${label} Chart`}
                  >
                    {icon}
                  </button>
                ))}
              </div>
              )}
              
              {/* Add Comparison Button */}
              {config.showComparison !== false && (
              <button
                className="add-comparison-btn"
                onClick={() => setShowSearch(true)}
                disabled={comparisonItems.length >= 10}
              >
                <Plus size={18} />
                Add Comparison
              </button>
              )}
            </div>
            )}
          </div>

          {/* Description/Stats Section */}
          {config.showStats !== false && descriptionStats && (
            <div className="description-section">
              <div className="stats-grid">
                <div className="stat-item">
                  <span className="stat-label">Latest Value</span>
                  <span className="stat-value">{descriptionStats.latest}</span>
                </div>
                <div className="stat-item">
                  <span className="stat-label">Period Min</span>
                  <span className="stat-value">{descriptionStats.min}</span>
                </div>
                <div className="stat-item">
                  <span className="stat-label">Period Max</span>
                  <span className="stat-value">{descriptionStats.max}</span>
                </div>
                <div className="stat-item">
                  <span className="stat-label">Average</span>
                  <span className="stat-value">{descriptionStats.avg}</span>
                </div>
                <div className="stat-item">
                  <span className="stat-label">Data Points</span>
                  <span className="stat-value">{descriptionStats.dataPoints}</span>
                </div>
              </div>
            </div>
          )}

          {/* Time Range Selector */}
          {config.showTimeframes !== false && (
          <div className="time-range-section">
            <label className="section-label">Time Period:</label>
            <div className="time-range-buttons">
              {TIME_RANGES.map(({ value, label }) => (
                <button
                  key={value}
                  className={`time-range-btn ${activeTimeRange === value ? 'active' : ''}`}
                  onClick={() => setActiveTimeRange(value)}
                >
                  {label}
                </button>
              ))}
            </div>
          </div>
          )}

          {/* Chart Container */}
          <div className="chart-container" style={{ height: config.height || 600 }}>
            {isLoading && (
              <div className="loading-overlay">
                <div className="loading-spinner"></div>
                <span>Loading economic data...</span>
              </div>
            )}
            
            {error && (
              <div className="error-message">
                <p>Error loading data: {error}</p>
                <button onClick={() => window.location.reload()}>Retry</button>
              </div>
            )}
            
            {filteredChartData && !isLoading && (
              <Chart
                data={filteredChartData}
                chartType={activeChartType}
                theme={theme}
                comparisonData={comparisonItems.filter(item => item.visible)}
                config={config}
              />
            )}
            
            {!selectedIndicator && !isLoading && config.mode === 'dynamic' && (
              <div className="empty-state">
                <Search size={48} />
                <h3>No Indicator Selected</h3>
                <p>Use the search button above to find and select an economic indicator to visualize.</p>
                {config.showSearch !== false && (
                <button
                  className="search-btn-large"
                  onClick={() => setShowSearch(true)}
                >
                  Search Indicators
                </button>
                )}
              </div>
            )}
          </div>
        </div>

        {/* Comparison Sidebar */}
        {config.showComparison !== false && config.mode === 'dynamic' && (
        <ComparisonSidebar
          isOpen={showComparison && comparisonItems.length > 0}
          items={comparisonItems}
          onRemove={handleRemoveComparison}
          onToggle={handleToggleComparison}
          onClose={() => setShowComparison(false)}
        />
        )}
      </main>
    </div>
  );
};

export default EconomicDashboard;