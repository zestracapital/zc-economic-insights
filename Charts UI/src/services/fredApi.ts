// FRED API Service
// This file handles all FRED API interactions

const FRED_API_KEY = 'your_fred_api_key_here'; // Replace with your actual FRED API key
const FRED_BASE_URL = 'https://api.stlouisfed.org/fred';

// For WordPress integration, you'll need to create a PHP proxy file
// This is a client-side implementation for development
export const fetchFredData = async (seriesId: string) => {
  try {
    // In production, this should go through your WordPress PHP proxy
    const response = await fetch(
      `/api/fred-proxy.php?series=${seriesId}&api_key=${FRED_API_KEY}`
    );
    
    if (!response.ok) {
      throw new Error('Failed to fetch data');
    }
    
    const data = await response.json();
    
    if (data.error) {
      throw new Error(data.error);
    }
    
    return data.observations || [];
  } catch (error) {
    console.error('FRED API Error:', error);
    
    // Fallback to mock data for development
    return generateMockData(seriesId);
  }
};

export const searchFredSeries = async (query: string) => {
  try {
    // In production, this should go through your WordPress PHP proxy
    const response = await fetch(
      `/api/fred-proxy.php?search=${encodeURIComponent(query)}&api_key=${FRED_API_KEY}`
    );
    
    if (!response.ok) {
      throw new Error('Failed to search series');
    }
    
    const data = await response.json();
    
    if (data.error) {
      throw new Error(data.error);
    }
    
    return data.seriess || [];
  } catch (error) {
    console.error('FRED Search Error:', error);
    
    // Fallback to mock search results
    return getMockSearchResults(query);
  }
};

// Mock data generator for development/fallback
const generateMockData = (seriesId: string) => {
  const startDate = new Date('2020-01-01');
  const endDate = new Date();
  const data = [];
  
  let currentDate = new Date(startDate);
  let baseValue = Math.random() * 100 + 50;
  
  while (currentDate <= endDate) {
    // Add some realistic variation
    const variation = (Math.random() - 0.5) * 5;
    baseValue += variation;
    baseValue = Math.max(0, baseValue); // Ensure non-negative
    
    data.push({
      date: currentDate.toISOString().split('T')[0],
      value: baseValue.toFixed(2)
    });
    
    // Move to next month
    currentDate.setMonth(currentDate.getMonth() + 1);
  }
  
  return data;
};

const getMockSearchResults = (query: string) => {
  const mockResults = [
    { id: 'GDP', title: 'Gross Domestic Product' },
    { id: 'UNRATE', title: 'Unemployment Rate' },
    { id: 'CPIAUCSL', title: 'Consumer Price Index for All Urban Consumers: All Items in U.S. City Average' },
    { id: 'FEDFUNDS', title: 'Federal Funds Effective Rate' },
    { id: 'PAYEMS', title: 'All Employees, Total Nonfarm' },
    { id: 'HOUST', title: 'New Privately-Owned Housing Units Started: Total Units' },
    { id: 'INDPRO', title: 'Industrial Production: Total Index' },
    { id: 'RSAFS', title: 'Advance Retail Sales: Retail and Food Services, Total' }
  ];
  
  return mockResults.filter(result => 
    result.title.toLowerCase().includes(query.toLowerCase()) ||
    result.id.toLowerCase().includes(query.toLowerCase())
  );
};