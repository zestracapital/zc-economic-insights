import React, { useEffect, useRef } from 'react';
import {
  Chart as ChartJS,
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  BarElement,
  Title,
  Tooltip,
  Legend,
  TimeScale,
  Filler
} from 'chart.js';
import 'chartjs-adapter-date-fns';
import zoomPlugin from 'chartjs-plugin-zoom';
import { ChartData, ChartType, ThemeMode, Indicator, ChartConfig } from './types';

ChartJS.register(
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  BarElement,
  Title,
  Tooltip,
  Legend,
  TimeScale,
  Filler,
  zoomPlugin
);

interface ChartProps {
  data: ChartData;
  chartType: ChartType;
  theme: ThemeMode;
  comparisonData?: Indicator[];
  config?: ChartConfig;
}

const Chart: React.FC<ChartProps> = ({ data, chartType, theme, comparisonData = [], config }) => {
  const canvasRef = useRef<HTMLCanvasElement>(null);
  const chartRef = useRef<ChartJS | null>(null);

  const themeConfig = {
    light: {
      backgroundColor: '#ffffff',
      borderColor: '#e1e8ed',
      textColor: '#5b7083',
      gridColor: 'rgba(0, 0, 0, 0.05)',
      tooltipBg: 'rgba(255, 255, 255, 0.95)',
      primary: '#00BCD4',
      secondary: '#FF5722',
      accent: '#4CAF50',
      warning: '#FF9800',
      error: '#F44336',
      colors: ['#00BCD4', '#FF5722', '#4CAF50', '#FF9800', '#9C27B0', '#673AB7', '#3F51B5', '#2196F3', '#00BCD4', '#009688']
    },
    dark: {
      backgroundColor: '#1e2732',
      borderColor: '#38444d',
      textColor: '#8899a6',
      gridColor: 'rgba(255, 255, 255, 0.08)',
      tooltipBg: 'rgba(30, 39, 50, 0.95)',
      primary: '#26C6DA',
      secondary: '#FF7043',
      accent: '#66BB6A',
      warning: '#FFB74D',
      error: '#EF5350',
      colors: ['#26C6DA', '#FF7043', '#66BB6A', '#FFB74D', '#AB47BC', '#7E57C2', '#5C6BC0', '#42A5F5', '#26C6DA', '#26A69A']
    }
  };

  const currentTheme = themeConfig[theme];

  useEffect(() => {
    if (!canvasRef.current || !data?.series?.length) return;

    // Destroy existing chart
    if (chartRef.current) {
      chartRef.current.destroy();
    }

    const ctx = canvasRef.current.getContext('2d');
    if (!ctx) return;

    // Prepare main dataset
    const chartData = data.series.map(([date, value]) => ({
      x: new Date(date),
      y: value
    }));

    const datasets = [{
      label: data.indicator.name,
      data: chartData,
      borderColor: currentTheme.primary,
      backgroundColor: chartType === 'area' 
        ? `${currentTheme.primary}20` 
        : chartType === 'bar' 
        ? `${currentTheme.primary}80`
        : currentTheme.primary,
      borderWidth: 2.5,
      fill: chartType === 'area',
      tension: chartType === 'line' ? 0.4 : 0,
      pointRadius: chartType === 'scatter' ? 4 : 0,
      pointHoverRadius: 6,
      pointBackgroundColor: currentTheme.primary,
      pointBorderColor: '#ffffff',
      pointBorderWidth: 2,
    }];

    // Add comparison datasets
    comparisonData.forEach((indicator, index) => {
      if (indicator.visible) {
        // In a real implementation, you would fetch data for each comparison indicator
        // For now, we'll create mock data based on the main dataset
        const mockData = chartData.map(point => ({
          x: point.x,
          y: point.y * (0.8 + Math.random() * 0.4) // Mock variation
        }));

        datasets.push({
          label: indicator.name,
          data: mockData,
          borderColor: currentTheme.colors[index + 1] || currentTheme.secondary,
          backgroundColor: chartType === 'area' 
            ? `${currentTheme.colors[index + 1] || currentTheme.secondary}20`
            : chartType === 'bar'
            ? `${currentTheme.colors[index + 1] || currentTheme.secondary}80`
            : currentTheme.colors[index + 1] || currentTheme.secondary,
          borderWidth: 2,
          fill: chartType === 'area',
          tension: chartType === 'line' ? 0.4 : 0,
          pointRadius: chartType === 'scatter' ? 4 : 0,
          pointHoverRadius: 6,
          pointBackgroundColor: currentTheme.colors[index + 1] || currentTheme.secondary,
          pointBorderColor: '#ffffff',
          pointBorderWidth: 2,
        });
      }
    });

    const chartConfig = {
      type: chartType === 'area' ? 'line' : chartType,
      data: { datasets },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: {
          mode: 'index' as const,
          intersect: false,
        },
        plugins: {
          legend: {
            display: datasets.length > 1,
            position: 'top' as const,
            align: 'start' as const,
            labels: {
              usePointStyle: true,
              pointStyle: 'circle',
              padding: 20,
              font: {
                size: 12,
                weight: '500' as const,
              },
              color: currentTheme.textColor,
              generateLabels: (chart: any) => {
                const labels = ChartJS.defaults.plugins.legend.labels.generateLabels(chart);
                return labels.map((label: any, index: number) => ({
                  ...label,
                  pointStyle: 'circle',
                  fillStyle: currentTheme.colors[index] || currentTheme.primary,
                  strokeStyle: currentTheme.colors[index] || currentTheme.primary,
                }));
              }
            }
          },
          tooltip: {
            backgroundColor: currentTheme.tooltipBg,
            titleColor: currentTheme.textColor,
            bodyColor: currentTheme.textColor,
            borderColor: currentTheme.borderColor,
            borderWidth: 1,
            cornerRadius: 12,
            padding: 16,
            displayColors: true,
            titleFont: {
              size: 13,
              weight: '600' as const,
            },
            bodyFont: {
              size: 12,
              weight: '500' as const,
            },
            callbacks: {
              title: (context: any) => {
                return new Date(context[0].parsed.x).toLocaleDateString('en-US', {
                  year: 'numeric',
                  month: 'long',
                  day: 'numeric'
                });
              },
              label: (context: any) => {
                return `${context.dataset.label}: ${context.parsed.y.toLocaleString()}`;
              }
            }
          },
          zoom: config?.showZoomPan !== false ? {
            zoom: {
              wheel: {
                enabled: true,
              },
              pinch: {
                enabled: true,
              },
              drag: {
                enabled: true,
                backgroundColor: 'rgba(0, 188, 212, 0.1)',
              },
              mode: 'x' as const,
            },
            pan: {
              enabled: true,
              mode: 'x' as const,
            },
          } : undefined
        },
        scales: {
          x: {
            type: 'time',
            time: {
              unit: 'month',
              displayFormats: {
                month: 'MMM yyyy'
              }
            },
            grid: {
              color: currentTheme.gridColor,
              drawBorder: false,
              drawTicks: false,
            },
            ticks: {
              color: currentTheme.textColor,
              font: {
                size: 11,
                weight: '500' as const,
              },
              maxTicksLimit: 8,
              padding: 10,
            },
            border: {
              display: false,
            }
          },
          y: {
            grid: {
              color: currentTheme.gridColor,
              drawBorder: false,
              drawTicks: false,
            },
            ticks: {
              color: currentTheme.textColor,
              font: {
                size: 11,
                weight: '500' as const,
              },
              padding: 15,
              callback: function(value: any) {
                if (typeof value === 'number') {
                  if (value >= 1000000) {
                    return (value / 1000000).toFixed(1) + 'M';
                  } else if (value >= 1000) {
                    return (value / 1000).toFixed(1) + 'K';
                  }
                  return value.toLocaleString();
                }
                return value;
              }
            },
            border: {
              display: false,
            }
          }
        },
        elements: {
          line: {
            tension: 0.4,
          },
          point: {
            hoverBorderWidth: 3,
          }
        }
      }
    };

    chartRef.current = new ChartJS(ctx, chartConfig);

    return () => {
      if (chartRef.current) {
        chartRef.current.destroy();
        chartRef.current = null;
      }
    };
  }, [data, chartType, theme, comparisonData]);

  return (
    <div className="chart-canvas-container">
      <canvas ref={canvasRef} />
      <div className="chart-watermark">
        Zestra Capital Analytics
      </div>
    </div>
  );
};

export default Chart;