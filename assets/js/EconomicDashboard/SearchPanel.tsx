import React, { useState, useEffect, useCallback, useRef } from 'react';
import { Search, X } from 'lucide-react';
import { Indicator } from './types';

interface SearchPanelProps {
  isOpen: boolean;
  onClose: () => void;
  onSelectIndicator: (indicator: Indicator) => void;
  onAddComparison: (indicator: Indicator) => void;
  baseUrl: string;
  accessKey: string;
}

const SearchPanel: React.FC<SearchPanelProps> = ({
  isOpen,
  onClose,
  onSelectIndicator,
  onAddComparison,
  baseUrl,
  accessKey
}) => {
  const [query, setQuery] = useState('');
  const [results, setResults] = useState<Indicator[]>([]);
  const [isSearching, setIsSearching] = useState(false);
  const [showComparisons, setShowComparisons] = useState<Record<number, boolean>>({});
  
  const inputRef = useRef<HTMLInputElement>(null);
  const debounceRef = useRef<NodeJS.Timeout>();

  const searchIndicators = useCallback(async (searchQuery: string) => {
    if (!searchQuery.trim()) {
      setResults([]);
      return;
    }

    setIsSearching(true);
    
    try {
      // This would be your actual search endpoint
      const url = `${baseUrl}/search?q=${encodeURIComponent(searchQuery)}${accessKey ? `&access_key=${accessKey}` : ''}`;
      const response = await fetch(url);
      
      if (!response.ok) {
        throw new Error('Search failed');
      }

      const data = await response.json();
      setResults(data.indicators || []);
    } catch (err) {
      console.error('Search error:', err);
      // For demo purposes, show some mock results
      const mockResults: Indicator[] = [
        { id: 1, name: 'Gross Domestic Product', slug: 'gdp', source_type: 'fred' },
        { id: 2, name: 'Unemployment Rate', slug: 'unemployment', source_type: 'fred' },
        { id: 3, name: 'Consumer Price Index', slug: 'cpi', source_type: 'fred' },
        { id: 4, name: 'Federal Funds Rate', slug: 'fed-funds', source_type: 'fred' },
        { id: 5, name: 'S&P 500 Index', slug: 'sp500', source_type: 'yahoo' }
      ].filter(item => 
        item.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
        item.slug.toLowerCase().includes(searchQuery.toLowerCase())
      );
      setResults(mockResults);
    } finally {
      setIsSearching(false);
    }
  }, [baseUrl, accessKey]);

  // Debounced search
  useEffect(() => {
    if (debounceRef.current) {
      clearTimeout(debounceRef.current);
    }

    debounceRef.current = setTimeout(() => {
      searchIndicators(query);
    }, 300);

    return () => {
      if (debounceRef.current) {
        clearTimeout(debounceRef.current);
      }
    };
  }, [query, searchIndicators]);

  // Focus input when panel opens
  useEffect(() => {
    if (isOpen && inputRef.current) {
      inputRef.current.focus();
    }
  }, [isOpen]);

  // Clear search when panel closes
  useEffect(() => {
    if (!isOpen) {
      setQuery('');
      setResults([]);
      setShowComparisons({});
    }
  }, [isOpen]);

  const handleSelectIndicator = (indicator: Indicator) => {
    onSelectIndicator(indicator);
    setQuery('');
  };

  const handleAddComparison = (indicator: Indicator) => {
    onAddComparison(indicator);
    setShowComparisons(prev => ({ ...prev, [indicator.id]: true }));
  };

  if (!isOpen) return null;

  return (
    <>
      <div className="search-overlay" onClick={onClose} />
      <div className="search-panel">
        <div className="search-header">
          <h3>Search Economic Indicators</h3>
          <button className="close-btn" onClick={onClose}>
            <X size={20} />
          </button>
        </div>
        
        <div className="search-input-container">
          <Search size={20} className="search-icon" />
          <input
            ref={inputRef}
            type="text"
            value={query}
            onChange={(e) => setQuery(e.target.value)}
            placeholder="Search for economic indicators..."
            className="search-input"
          />
          {query && (
            <button 
              className="clear-search-btn"
              onClick={() => setQuery('')}
            >
              <X size={16} />
            </button>
          )}
        </div>

        <div className="search-results">
          {isSearching && (
            <div className="search-loading">
              <div className="loading-spinner small"></div>
              <span>Searching...</span>
            </div>
          )}
          
          {!isSearching && results.length === 0 && query && (
            <div className="no-results">
              <p>No indicators found for "{query}"</p>
              <p className="suggestion">Try different keywords or check spelling</p>
            </div>
          )}
          
          {!isSearching && results.length === 0 && !query && (
            <div className="search-hint">
              <Search size={32} />
              <p>Start typing to search for economic indicators</p>
              <div className="popular-searches">
                <h4>Popular searches:</h4>
                <div className="search-tags">
                  {['GDP', 'Unemployment', 'Inflation', 'Interest Rates', 'S&P 500'].map(tag => (
                    <button 
                      key={tag}
                      className="search-tag"
                      onClick={() => setQuery(tag)}
                    >
                      {tag}
                    </button>
                  ))}
                </div>
              </div>
            </div>
          )}
          
          {!isSearching && results.map(indicator => (
            <div key={indicator.id} className="result-item">
              <div className="result-info">
                <h4 className="result-title">{indicator.name}</h4>
                <div className="result-meta">
                  <span className="result-source">{indicator.source_type.toUpperCase()}</span>
                  <span className="result-slug">{indicator.slug}</span>
                </div>
              </div>
              <div className="result-actions">
                <button
                  className="action-btn primary"
                  onClick={() => handleSelectIndicator(indicator)}
                >
                  Select
                </button>
                <button
                  className={`action-btn secondary ${showComparisons[indicator.id] ? 'added' : ''}`}
                  onClick={() => handleAddComparison(indicator)}
                  disabled={showComparisons[indicator.id]}
                >
                  {showComparisons[indicator.id] ? 'Added' : 'Compare'}
                </button>
              </div>
            </div>
          ))}
        </div>
      </div>
    </>
  );
};

export default SearchPanel;