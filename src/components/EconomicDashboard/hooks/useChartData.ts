import { useState, useCallback } from 'react';
import { ChartData } from '../types';

export const useChartData = (baseUrl: string, accessKey: string) => {
  const [chartData, setChartData] = useState<ChartData | null>(null);
  const [isLoading, setIsLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const fetchIndicatorData = useCallback(async (slug: string) => {
    setIsLoading(true);
    setError(null);

    try {
      const url = `${baseUrl}/data/${slug}${accessKey ? `?access_key=${accessKey}` : ''}`;
      const response = await fetch(url);
      
      if (!response.ok) {
        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
      }

      const data = await response.json();
      
      if (data.status !== 'success') {
        throw new Error(data.message || 'Failed to fetch data');
      }

      setChartData(data.data);
    } catch (err) {
      const errorMessage = err instanceof Error ? err.message : 'Unknown error occurred';
      setError(errorMessage);
      console.error('Error fetching chart data:', err);
    } finally {
      setIsLoading(false);
    }
  }, [baseUrl, accessKey]);

  return {
    chartData,
    isLoading,
    error,
    fetchIndicatorData
  };
};