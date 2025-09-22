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
  ChartOptions,
  TooltipItem
} from 'chart.js';
import { Line, Bar } from 'react-chartjs-2';
import 'chartjs-adapter-date-fns';
import { ChartData, ComparisonItem, TimeRange } from '../types';

ChartJS.register(
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  BarElement,
  Title,
  Tooltip,
  Legend,
  TimeScale
);

interface ChartProps {
  primaryData: ChartData;
  comparisonItems: ComparisonItem[];
  chartType: 'line' | 'bar';
  timeRange: TimeRange;
  isDarkMode: boolean;
}

const Chart: React.FC<ChartProps> = ({
  primaryData,
  comparisonItems,
  chartType,
  timeRange,
  isDarkMode
}) => {
  const chartRef = useRef<ChartJS>(null);

  const filterDataByTimeRange = (data: any[], range: TimeRange) => {
    if (range === 'All' || !data.length) return data;
    
    const now = new Date();
    const months = range === '6M' ? 6 : 
                  range === '1Y' ? 12 :
                  range === '2Y' ? 24 :
                  range === '3Y' ? 36 :
                  range === '5Y' ? 60 :
                  range === '10Y' ? 120 :
                  range === '15Y' ? 180 :
                  range === '20Y' ? 240 : 60;
    
    const cutoffDate = new Date(now);
    cutoffDate.setMonth(cutoffDate.getMonth() - months);
    
    return data.filter(point => new Date(point.date) >= cutoffDate);
  };

  const getChartData = () => {
    const filteredPrimaryData = filterDataByTimeRange(primaryData.data, timeRange);
    
    const datasets = [
      {
        label: primaryData.title,
        data: filteredPrimaryData.map(point => ({
          x: new Date(point.date),
          y: parseFloat(point.value)
        })),
        borderColor: '#00BCD4',
        backgroundColor: chartType === 'bar' ? 'rgba(0, 188, 212, 0.8)' : 'rgba(0, 188, 212, 0.1)',
        borderWidth: 3,
        fill: chartType === 'line',
        tension: 0.3,
        pointRadius: 0,
        pointHoverRadius: 6,
        yAxisID: 'y'
      }
    ];

    // Add comparison datasets
    comparisonItems.forEach((item, index) => {
      const filteredData = filterDataByTimeRange(item.data.data, timeRange);
      datasets.push({
        label: item.title,
        data: filteredData.map(point => ({
          x: new Date(point.date),
          y: parseFloat(point.value)
        })),
        borderColor: item.color,
        backgroundColor: `${item.color}20`,
        borderWidth: 2,
        fill: false,
        tension: 0.3,
        pointRadius: 0,
        pointHoverRadius: 6,
        yAxisID: index === 0 ? 'y1' : 'y'
      });
    });

    return { datasets };
  };

  const getChartOptions = (): ChartOptions<'line' | 'bar'> => {
    const gridColor = isDarkMode ? 'rgba(255, 255, 255, 0.08)' : 'rgba(0, 0, 0, 0.05)';
    const textColor = isDarkMode ? '#8899a6' : '#5b7083';
    const tooltipBg = isDarkMode ? 'rgba(21, 32, 43, 0.95)' : 'rgba(255, 255, 255, 0.95)';
    const tooltipText = isDarkMode ? '#ffffff' : '#14171a';

    const scales: any = {
      x: {
        type: 'time',
        time: {
          unit: 'month',
          displayFormats: {
            month: 'MMM yyyy'
          }
        },
        grid: {
          color: gridColor,
          drawBorder: false
        },
        ticks: {
          color: textColor,
          maxTicksLimit: 10,
          font: {
            size: 11,
            weight: '500'
          }
        },
        border: {
          display: false
        }
      },
      y: {
        grid: {
          color: gridColor,
          drawBorder: false
        },
        ticks: {
          color: textColor,
          font: {
            size: 11,
            weight: '500'
          },
          callback: function(value: any) {
            return value.toLocaleString();
          }
        },
        position: 'left',
        border: {
          display: false
        }
      }
    };

    // Add secondary y-axis if there are comparisons
    if (comparisonItems.length > 0) {
      scales.y1 = {
        type: 'linear',
        display: true,
        position: 'right',
        grid: {
          drawOnChartArea: false
        },
        ticks: {
          color: textColor,
          font: {
            size: 11,
            weight: '500'
          },
          callback: function(value: any) {
            return value.toLocaleString();
          }
        },
        border: {
          display: false
        }
      };
    }

    return {
      responsive: true,
      maintainAspectRatio: false,
      interaction: {
        mode: 'index',
        intersect: false
      },
      plugins: {
        legend: {
          display: comparisonItems.length > 0,
          position: 'top',
          align: 'start',
          labels: {
            color: textColor,
            usePointStyle: true,
            padding: 20,
            font: {
              size: 12,
              weight: '600'
            }
          }
        },
        tooltip: {
          backgroundColor: tooltipBg,
          titleColor: tooltipText,
          bodyColor: tooltipText,
          borderWidth: 0,
          cornerRadius: 12,
          padding: 16,
          titleFont: {
            size: 13,
            weight: '600'
          },
          bodyFont: {
            size: 12,
            weight: '500'
          },
          callbacks: {
            label: function(context: TooltipItem<'line' | 'bar'>) {
              return `${context.dataset.label}: ${context.parsed.y.toLocaleString()}`;
            }
          }
        }
      },
      scales,
      elements: {
        line: {
          tension: 0.3
        },
        point: {
          hoverBorderWidth: 3
        }
      }
    };
  };

  const chartData = getChartData();
  const chartOptions = getChartOptions();

  if (chartType === 'bar') {
    return <Bar ref={chartRef} data={chartData} options={chartOptions} />;
  }

  return <Line ref={chartRef} data={chartData} options={chartOptions} />;
};

export default Chart;