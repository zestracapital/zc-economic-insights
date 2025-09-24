import React from 'react';
import { EconomicDashboard, StaticChart } from './components/EconomicDashboard';
import './App.css';

function App() {
  return (
    <div className="App">
      {/* Dynamic Dashboard Example */}
      <EconomicDashboard />
      
      {/* Static Chart Examples */}
      <div style={{ padding: '2rem', display: 'flex', flexDirection: 'column', gap: '2rem' }}>
        <h2>Static Chart Examples</h2>
        
        {/* Basic Static Chart */}
        <StaticChart
          indicator="gdp-growth"
          title="GDP Growth Rate"
          height={300}
          chartType="line"
          timeRange="3Y"
          showStats={true}
          showZoomPan={false}
        />
        
        {/* Static Chart with Controls */}
        <StaticChart
          indicator="unemployment"
          title="Unemployment Rate"
          height={400}
          chartType="area"
          timeRange="5Y"
          showTimeframes={true}
          showChartTypes={true}
          showStats={true}
          showZoomPan={true}
        />
      </div>
    </div>
  );
}

export default App;