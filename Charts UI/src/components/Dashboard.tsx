import React, { useState, useEffect, useRef } from 'react';
import { Search, TrendingUp, BarChart3, LineChart, Moon, Sun, Maximize2, Plus, X, Eye, EyeOff } from 'lucide-react';
import Chart from './Chart';
import { fetchFredData, searchFredSeries } from '../services/fredApi';
import { ChartData, ComparisonItem, TimeRange } from '../types';

const Dashboard: React.FC = () => {
  const [isDarkMode, setIsDarkMode] = useState(false);
  const [isFullscreen, setIsFullscreen] = useState(false);
  const [chartType, setChartType] = useState<'line' | 'bar'>('line');
  const [timeRange, setTimeRange] = useState<TimeRange>('5Y');
  const [isLoading, setIsLoading] = useState(true);
  const [searchOpen, setSearchOpen] = useState(false);
  const [compareModalOpen, setCompareModalOpen] = useState(false);
  const [searchQuery, setSearchQuery] = useState('');
  const [compareQuery, setCompareQuery] = useState('');
  const [searchResults, setSearchResults] = useState<any[]>([]);
  const [compareResults, setCompareResults] = useState<any[]>([]);
  
  const [primaryData, setPrimaryData] = useState<ChartData | null>(null);
  const [comparisonItems, setComparisonItems] = useState<ComparisonItem[]>([]);
  const [historicalStats, setHistoricalStats] = useState({
    '3M': '--',
    '6M': '--',
    '1Y': '--'
  });

  const dashboardRef = useRef<HTMLDivElement>(null);
  const searchTimeoutRef = useRef<NodeJS.Timeout>();

  const defaultIndicators = [
    { id: 'GDP', name: 'Gross Domestic Product' },
    { id: 'UNRATE', name: 'Unemployment Rate' },
    { id: 'CPIAUCSL', name: 'Consumer Price Index' },
    { id: 'FEDFUNDS', name: 'Federal Funds Rate' }
  ];

  const timeRanges = [
    { label: '6M', value: '6M' as TimeRange },
    { label: '1Y', value: '1Y' as TimeRange },
    { label: '2Y', value: '2Y' as TimeRange },
    { label: '3Y', value: '3Y' as TimeRange },
    { label: '5Y', value: '5Y' as TimeRange },
    { label: '10Y', value: '10Y' as TimeRange },
    { label: '15Y', value: '15Y' as TimeRange },
    { label: '20Y', value: '20Y' as TimeRange },
    { label: 'All', value: 'All' as TimeRange }
  ];

  // Load default indicator on mount
  useEffect(() => {
    loadDefaultIndicator();
  }, []);

  // Handle search with debouncing
  useEffect(() => {
    if (searchTimeoutRef.current) {
      clearTimeout(searchTimeoutRef.current);
    }

    if (searchQuery.length >= 2) {
      searchTimeoutRef.current = setTimeout(() => {
        performSearch(searchQuery, false);
      }, 300);
    } else {
      setSearchResults([]);
    }

    return () => {
      if (searchTimeoutRef.current) {
        clearTimeout(searchTimeoutRef.current);
      }
    };
  }, [searchQuery]);

  // Handle compare search with debouncing
  useEffect(() => {
    if (searchTimeoutRef.current) {
      clearTimeout(searchTimeoutRef.current);
    }

    if (compareQuery.length >= 2) {
      searchTimeoutRef.current = setTimeout(() => {
        performSearch(compareQuery, true);
      }, 300);
    } else {
      setCompareResults([]);
    }

    return () => {
      if (searchTimeoutRef.current) {
        clearTimeout(searchTimeoutRef.current);
      }
    };
  }, [compareQuery]);

  const loadDefaultIndicator = async () => {
    setIsLoading(true);
    
    for (const indicator of defaultIndicators) {
      try {
        const data = await fetchFredData(indicator.id);
        if (data && data.length > 0) {
          const chartData: ChartData = {
            id: indicator.id,
            title: indicator.name,
            data: data,
            lastUpdate: new Date(data[data.length - 1].date)
          };
          setPrimaryData(chartData);
          calculateHistoricalStats(data);
          setIsLoading(false);
          return;
        }
      } catch (error) {
        console.error(`Failed to load ${indicator.id}:`, error);
        continue;
      }
    }
    
    setIsLoading(false);
  };

  const performSearch = async (query: string, isCompare: boolean) => {
    try {
      const results = await searchFredSeries(query);
      if (isCompare) {
        setCompareResults(results);
      } else {
        setSearchResults(results);
      }
    } catch (error) {
      console.error('Search error:', error);
      if (isCompare) {
        setCompareResults([]);
      } else {
        setSearchResults([]);
      }
    }
  };

  const calculateHistoricalStats = (data: any[]) => {
    if (!data || data.length === 0) return;

    const current = parseFloat(data[data.length - 1].value);
    const currentDate = new Date();
    
    const findValueForMonthsAgo = (months: number) => {
      const targetDate = new Date(currentDate);
      targetDate.setMonth(targetDate.getMonth() - months);
      
      let closest = data[0];
      let minDiff = Math.abs(new Date(data[0].date).getTime() - targetDate.getTime());
      
      for (const point of data) {
        const diff = Math.abs(new Date(point.date).getTime() - targetDate.getTime());
        if (diff < minDiff) {
          minDiff = diff;
          closest = point;
        }
      }
      
      return parseFloat(closest.value);
    };

    const threeMonthValue = findValueForMonthsAgo(3);
    const sixMonthValue = findValueForMonthsAgo(6);
    const oneYearValue = findValueForMonthsAgo(12);

    const change3M = ((current - threeMonthValue) / threeMonthValue * 100);
    const change6M = ((current - sixMonthValue) / sixMonthValue * 100);
    const change1Y = ((current - oneYearValue) / oneYearValue * 100);

    setHistoricalStats({
      '3M': `${change3M >= 0 ? '+' : ''}${change3M.toFixed(2)}%`,
      '6M': `${change6M >= 0 ? '+' : ''}${change6M.toFixed(2)}%`,
      '1Y': `${change1Y >= 0 ? '+' : ''}${change1Y.toFixed(2)}%`
    });
  };

  const handleIndicatorSelect = async (seriesId: string, title: string, isCompare: boolean = false) => {
    try {
      const data = await fetchFredData(seriesId);
      if (data && data.length > 0) {
        const chartData: ChartData = {
          id: seriesId,
          title: title,
          data: data,
          lastUpdate: new Date(data[data.length - 1].date)
        };

        if (isCompare) {
          const newItem: ComparisonItem = {
            id: seriesId,
            title: title,
            data: chartData,
            visible: true,
            color: getNextComparisonColor()
          };
          setComparisonItems(prev => [...prev, newItem]);
          setCompareModalOpen(false);
          setCompareQuery('');
        } else {
          setPrimaryData(chartData);
          calculateHistoricalStats(data);
          setComparisonItems([]);
          setSearchOpen(false);
          setSearchQuery('');
        }
      }
    } catch (error) {
      console.error('Error loading indicator:', error);
    }
  };

  const getNextComparisonColor = () => {
    const colors = ['#FF5722', '#4CAF50', '#FF9800', '#9C27B0', '#607D8B'];
    return colors[comparisonItems.length % colors.length];
  };

  const toggleComparison = (index: number) => {
    setComparisonItems(prev => 
      prev.map((item, i) => 
        i === index ? { ...item, visible: !item.visible } : item
      )
    );
  };

  const removeComparison = (index: number) => {
    setComparisonItems(prev => prev.filter((_, i) => i !== index));
  };

  const toggleFullscreen = () => {
    if (!document.fullscreenElement && dashboardRef.current) {
      dashboardRef.current.requestFullscreen();
      setIsFullscreen(true);
    } else if (document.fullscreenElement) {
      document.exitFullscreen();
      setIsFullscreen(false);
    }
  };

  const formatLastUpdate = (date: Date) => {
    const now = new Date();
    const diffTime = Math.abs(now.getTime() - date.getTime());
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
    
    if (diffDays === 1) return 'Updated yesterday';
    if (diffDays < 7) return `Updated ${diffDays} days ago`;
    return `Updated ${date.toLocaleDateString()}`;
  };

  return (
    <div 
      ref={dashboardRef}
      className={`min-h-screen transition-colors duration-300 ${
        isDarkMode 
          ? 'bg-slate-900 text-white' 
          : 'bg-gray-50 text-gray-900'
      }`}
    >
      {/* Header */}
      <header className={`sticky top-0 z-50 border-b backdrop-blur-sm ${
        isDarkMode 
          ? 'bg-slate-800/90 border-slate-700' 
          : 'bg-white/90 border-gray-200'
      }`}>
        <div className="max-w-7xl mx-auto px-6 py-4">
          <div className="flex items-center justify-between">
            <div>
              <h1 className="text-xl font-bold text-cyan-500">
                Zestra Capital - Economic Analytics
              </h1>
              <p className="text-sm text-gray-500 mt-1">
                Professional Economic Data Visualization & Analysis Platform
              </p>
            </div>
            
            <div className="flex items-center gap-4">
              {/* Search */}
              <div className="relative">
                <button
                  onClick={() => setSearchOpen(!searchOpen)}
                  className={`flex items-center gap-2 px-4 py-2 rounded-lg border transition-colors ${
                    isDarkMode
                      ? 'bg-slate-700 border-slate-600 hover:bg-cyan-500 hover:border-cyan-500'
                      : 'bg-gray-100 border-gray-300 hover:bg-cyan-500 hover:border-cyan-500 hover:text-white'
                  }`}
                >
                  <Search className="w-4 h-4" />
                  <span className="text-sm font-medium">Search Indicators</span>
                </button>
                
                {searchOpen && (
                  <div className={`absolute top-full right-0 mt-2 w-96 rounded-lg border shadow-lg z-50 ${
                    isDarkMode ? 'bg-slate-800 border-slate-700' : 'bg-white border-gray-200'
                  }`}>
                    <div className="p-4">
                      <input
                        type="text"
                        value={searchQuery}
                        onChange={(e) => setSearchQuery(e.target.value)}
                        placeholder="Search economic indicators..."
                        className={`w-full px-3 py-2 rounded-lg border text-sm ${
                          isDarkMode
                            ? 'bg-slate-700 border-slate-600 text-white placeholder-gray-400'
                            : 'bg-gray-50 border-gray-300 text-gray-900 placeholder-gray-500'
                        }`}
                        autoFocus
                      />
                    </div>
                    
                    {searchResults.length > 0 && (
                      <div className="max-h-64 overflow-y-auto border-t border-gray-200 dark:border-slate-700">
                        {searchResults.map((result, index) => (
                          <button
                            key={index}
                            onClick={() => handleIndicatorSelect(result.id, result.title)}
                            className={`w-full text-left px-4 py-3 text-sm border-b last:border-b-0 transition-colors ${
                              isDarkMode
                                ? 'border-slate-700 hover:bg-slate-700'
                                : 'border-gray-100 hover:bg-gray-50'
                            }`}
                          >
                            {result.title}
                          </button>
                        ))}
                      </div>
                    )}
                  </div>
                )}
              </div>
              
              {/* Controls */}
              <div className="flex items-center gap-2">
                <button
                  onClick={() => setIsDarkMode(!isDarkMode)}
                  className={`p-2 rounded-lg border transition-colors ${
                    isDarkMode
                      ? 'bg-slate-700 border-slate-600 hover:bg-cyan-500'
                      : 'bg-gray-100 border-gray-300 hover:bg-cyan-500 hover:text-white'
                  }`}
                >
                  {isDarkMode ? <Sun className="w-4 h-4" /> : <Moon className="w-4 h-4" />}
                </button>
                
                <button
                  onClick={toggleFullscreen}
                  className={`p-2 rounded-lg border transition-colors ${
                    isDarkMode
                      ? 'bg-slate-700 border-slate-600 hover:bg-cyan-500'
                      : 'bg-gray-100 border-gray-300 hover:bg-cyan-500 hover:text-white'
                  }`}
                >
                  <Maximize2 className="w-4 h-4" />
                </button>
              </div>
            </div>
          </div>
        </div>
      </header>

      {/* Main Content */}
      <main className="max-w-7xl mx-auto p-6">
        <div className="flex gap-6">
          {/* Chart Section */}
          <div className="flex-1">
            <div className={`rounded-xl shadow-lg overflow-hidden ${
              isDarkMode ? 'bg-slate-800' : 'bg-white'
            }`}>
              {/* Chart Controls */}
              <div className="p-6 border-b border-gray-200 dark:border-slate-700">
                <div className="flex items-start justify-between mb-4">
                  <div>
                    <h2 className="text-2xl font-bold mb-2">
                      {isLoading ? 'Loading Default Indicator...' : primaryData?.title || 'No Data'}
                    </h2>
                    {primaryData && (
                      <p className="text-sm text-gray-500">
                        {formatLastUpdate(primaryData.lastUpdate)}
                      </p>
                    )}
                  </div>
                  
                  <div className="flex items-center gap-3">
                    {/* Chart Type Toggle */}
                    <div className={`flex rounded-lg p-1 ${
                      isDarkMode ? 'bg-slate-700' : 'bg-gray-100'
                    }`}>
                      <button
                        onClick={() => setChartType('line')}
                        className={`p-2 rounded-md transition-colors ${
                          chartType === 'line'
                            ? 'bg-cyan-500 text-white'
                            : isDarkMode ? 'text-gray-400 hover:text-white' : 'text-gray-600 hover:text-gray-900'
                        }`}
                      >
                        <LineChart className="w-4 h-4" />
                      </button>
                      <button
                        onClick={() => setChartType('bar')}
                        className={`p-2 rounded-md transition-colors ${
                          chartType === 'bar'
                            ? 'bg-cyan-500 text-white'
                            : isDarkMode ? 'text-gray-400 hover:text-white' : 'text-gray-600 hover:text-gray-900'
                        }`}
                      >
                        <BarChart3 className="w-4 h-4" />
                      </button>
                    </div>
                    
                    {/* Add Comparison */}
                    <button
                      onClick={() => setCompareModalOpen(true)}
                      className="flex items-center gap-2 px-4 py-2 bg-cyan-500 text-white rounded-lg hover:bg-cyan-600 transition-colors"
                    >
                      <Plus className="w-4 h-4" />
                      Add Comparison
                    </button>
                  </div>
                </div>
                
                {/* Historical Stats */}
                <div className="grid grid-cols-3 gap-4 mb-4">
                  {Object.entries(historicalStats).map(([period, change]) => (
                    <div key={period} className={`p-3 rounded-lg text-center ${
                      isDarkMode ? 'bg-slate-700' : 'bg-gray-50'
                    }`}>
                      <div className="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">
                        {period} Change
                      </div>
                      <div className={`text-lg font-bold ${
                        change.startsWith('+') ? 'text-green-500' : 
                        change.startsWith('-') ? 'text-red-500' : 'text-cyan-500'
                      }`}>
                        {change}
                      </div>
                    </div>
                  ))}
                </div>
                
                {/* Time Range Selector */}
                <div className="flex items-center gap-2">
                  <span className="text-sm font-medium text-gray-500 mr-2">Time Period:</span>
                  {timeRanges.map((range) => (
                    <button
                      key={range.value}
                      onClick={() => setTimeRange(range.value)}
                      className={`px-3 py-1 text-xs font-semibold rounded-md transition-colors ${
                        timeRange === range.value
                          ? 'bg-cyan-500 text-white'
                          : isDarkMode
                            ? 'bg-slate-700 text-gray-300 hover:bg-slate-600'
                            : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
                      }`}
                    >
                      {range.label}
                    </button>
                  ))}
                </div>
              </div>
              
              {/* Chart */}
              <div className="p-6">
                <div className="h-96 relative">
                  {isLoading ? (
                    <div className="absolute inset-0 flex items-center justify-center">
                      <div className="flex flex-col items-center gap-4">
                        <div className="w-8 h-8 border-4 border-cyan-500 border-t-transparent rounded-full animate-spin"></div>
                        <p className="text-sm text-gray-500">Loading economic data...</p>
                      </div>
                    </div>
                  ) : primaryData ? (
                    <Chart
                      primaryData={primaryData}
                      comparisonItems={comparisonItems.filter(item => item.visible)}
                      chartType={chartType}
                      timeRange={timeRange}
                      isDarkMode={isDarkMode}
                    />
                  ) : (
                    <div className="absolute inset-0 flex items-center justify-center">
                      <div className="text-center">
                        <TrendingUp className="w-12 h-12 text-gray-400 mx-auto mb-4" />
                        <p className="text-gray-500">No data available. Use search to find indicators.</p>
                      </div>
                    </div>
                  )}
                  
                  {/* Watermark */}
                  <div className="absolute inset-0 flex items-center justify-center pointer-events-none">
                    <div className="text-4xl font-bold text-cyan-500 opacity-5 transform -rotate-12">
                      Zestra Capital Analytics
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          
          {/* Comparison Sidebar */}
          {comparisonItems.length > 0 && (
            <div className={`w-80 rounded-xl shadow-lg ${
              isDarkMode ? 'bg-slate-800' : 'bg-white'
            }`}>
              <div className="p-4 border-b border-gray-200 dark:border-slate-700">
                <h3 className="text-lg font-semibold">Comparison Data</h3>
              </div>
              
              <div className="p-4 space-y-3">
                {comparisonItems.map((item, index) => (
                  <div
                    key={item.id}
                    className={`flex items-center justify-between p-3 rounded-lg ${
                      isDarkMode ? 'bg-slate-700' : 'bg-gray-50'
                    }`}
                  >
                    <div className="flex items-center gap-3 flex-1 min-w-0">
                      <div
                        className="w-3 h-3 rounded-full flex-shrink-0"
                        style={{ backgroundColor: item.color }}
                      />
                      <span className={`text-sm truncate ${
                        item.visible ? '' : 'opacity-50'
                      }`}>
                        {item.title}
                      </span>
                    </div>
                    
                    <div className="flex items-center gap-1">
                      <button
                        onClick={() => toggleComparison(index)}
                        className={`p-1 rounded hover:bg-gray-200 dark:hover:bg-slate-600 ${
                          item.visible ? 'text-gray-600 dark:text-gray-300' : 'text-gray-400'
                        }`}
                      >
                        {item.visible ? <Eye className="w-4 h-4" /> : <EyeOff className="w-4 h-4" />}
                      </button>
                      <button
                        onClick={() => removeComparison(index)}
                        className="p-1 rounded hover:bg-gray-200 dark:hover:bg-slate-600 text-gray-400 hover:text-red-500"
                      >
                        <X className="w-4 h-4" />
                      </button>
                    </div>
                  </div>
                ))}
              </div>
            </div>
          )}
        </div>
      </main>
      
      {/* Compare Modal */}
      {compareModalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50 backdrop-blur-sm">
          <div className={`w-full max-w-md mx-4 rounded-xl shadow-xl ${
            isDarkMode ? 'bg-slate-800' : 'bg-white'
          }`}>
            <div className="p-6 border-b border-gray-200 dark:border-slate-700">
              <div className="flex items-center justify-between">
                <h3 className="text-lg font-semibold">Add Comparison Indicator</h3>
                <button
                  onClick={() => setCompareModalOpen(false)}
                  className="p-1 rounded hover:bg-gray-200 dark:hover:bg-slate-700"
                >
                  <X className="w-5 h-5" />
                </button>
              </div>
            </div>
            
            <div className="p-6">
              <input
                type="text"
                value={compareQuery}
                onChange={(e) => setCompareQuery(e.target.value)}
                placeholder="Search for indicators to compare..."
                className={`w-full px-3 py-2 rounded-lg border mb-4 ${
                  isDarkMode
                    ? 'bg-slate-700 border-slate-600 text-white placeholder-gray-400'
                    : 'bg-gray-50 border-gray-300 text-gray-900 placeholder-gray-500'
                }`}
                autoFocus
              />
              
              {compareResults.length > 0 && (
                <div className="max-h-64 overflow-y-auto space-y-1">
                  {compareResults.map((result, index) => (
                    <button
                      key={index}
                      onClick={() => handleIndicatorSelect(result.id, result.title, true)}
                      className={`w-full text-left p-3 text-sm rounded-lg transition-colors ${
                        isDarkMode
                          ? 'hover:bg-slate-700'
                          : 'hover:bg-gray-50'
                      }`}
                    >
                      {result.title}
                    </button>
                  ))}
                </div>
              )}
            </div>
          </div>
        </div>
      )}
    </div>
  );
};

export default Dashboard;