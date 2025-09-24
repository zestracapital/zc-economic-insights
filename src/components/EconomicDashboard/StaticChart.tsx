import React from 'react';
import EconomicDashboard from './EconomicDashboard';
import { ChartConfig, TimeRange, ChartType } from './types';

interface StaticChartProps {
  baseUrl?: string;
  accessKey?: string;
  indicator?: string;
  title?: string;
  description?: string;
  height?: number;
  chartType?: ChartType;
  timeRange?: TimeRange;
  showHeader?: boolean;
  showTimeframes?: boolean;
  showChartTypes?: boolean;
  showStats?: boolean;
  showZoomPan?: boolean;
  className?: string;
}

const StaticChart: React.FC<StaticChartProps> = ({
  baseUrl,
  accessKey,
  indicator,
  title,
  description,
  height = 400,
  chartType = 'line',
  timeRange = '5Y',
  showHeader = false,
  showTimeframes = false,
  showChartTypes = false,
  showStats = true,
  showZoomPan = true,
  className = ''
}) => {
  const config: ChartConfig = {
    mode: 'static',
    showHeader,
    showSearch: false,
    showComparison: false,
    showTimeframes,
    showChartTypes,
    showStats,
    showZoomPan,
    showFullscreen: false,
    showThemeToggle: false,
    defaultTimeRange: timeRange,
    defaultChartType: chartType,
    defaultIndicator: indicator,
    height,
    title,
    description
  };

  return (
    <EconomicDashboard
      baseUrl={baseUrl}
      accessKey={accessKey}
      config={config}
      className={`static-chart ${className}`}
    />
  );
};

export default StaticChart;