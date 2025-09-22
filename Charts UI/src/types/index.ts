export interface ChartData {
  id: string;
  title: string;
  data: DataPoint[];
  lastUpdate: Date;
}

export interface DataPoint {
  date: string;
  value: string;
}

export interface ComparisonItem {
  id: string;
  title: string;
  data: ChartData;
  visible: boolean;
  color: string;
}

export type TimeRange = '6M' | '1Y' | '2Y' | '3Y' | '5Y' | '10Y' | '15Y' | '20Y' | 'All';

export interface SearchResult {
  id: string;
  title: string;
  units?: string;
  frequency?: string;
  seasonal_adjustment?: string;
}