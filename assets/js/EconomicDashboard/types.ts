export interface Indicator {
  id: number;
  name: string;
  slug: string;
  source_type: string;
  visible?: boolean;
}

export interface ChartData {
  indicator: Indicator;
  series: [string, number][];
}

export type TimeRange = '6M' | '1Y' | '2Y' | '3Y' | '5Y' | '10Y' | '15Y' | '20Y' | 'ALL';
export type ChartType = 'line' | 'bar' | 'area' | 'scatter';
export type ThemeMode = 'light' | 'dark';
export type ChartMode = 'dynamic' | 'static';

export interface ApiResponse {
  status: string;
  data: ChartData;
}

export interface SearchResult {
  indicators: Indicator[];
}

export interface ChartConfig {
  mode: ChartMode;
  showHeader?: boolean;
  showSearch?: boolean;
  showComparison?: boolean;
  showTimeframes?: boolean;
  showChartTypes?: boolean;
  showStats?: boolean;
  showZoomPan?: boolean;
  showFullscreen?: boolean;
  showThemeToggle?: boolean;
  defaultTimeRange?: TimeRange;
  defaultChartType?: ChartType;
  defaultIndicator?: string;
  height?: number;
  title?: string;
  description?: string;
}

export interface ChartConfig {
  mode: ChartMode;
  showHeader?: boolean;
  showSearch?: boolean;
  showComparison?: boolean;
  showTimeframes?: boolean;
  showChartTypes?: boolean;
  showStats?: boolean;
  showZoomPan?: boolean;
  showFullscreen?: boolean;
  showThemeToggle?: boolean;
  defaultTimeRange?: TimeRange;
  defaultChartType?: ChartType;
  defaultIndicator?: string;
  height?: number;
  title?: string;
  description?: string;
}