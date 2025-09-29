import React from 'react';
import { Eye, EyeOff, Trash2, X } from 'lucide-react';
import { Indicator } from './types';

interface ComparisonSidebarProps {
  isOpen: boolean;
  items: Indicator[];
  onRemove: (slug: string) => void;
  onToggle: (slug: string) => void;
  onClose: () => void;
}

const ComparisonSidebar: React.FC<ComparisonSidebarProps> = ({
  isOpen,
  items,
  onRemove,
  onToggle,
  onClose
}) => {
  if (!isOpen || items.length === 0) return null;

  return (
    <aside className="comparison-sidebar">
      <div className="sidebar-header">
        <h3>Comparison Indicators</h3>
        <button 
          className="close-sidebar-btn"
          onClick={onClose}
          aria-label="Close comparison sidebar"
        >
          <X size={20} />
        </button>
      </div>
      
      <div className="sidebar-content">
        {items.map((item) => (
          <div key={item.slug} className={`comparison-item ${!item.visible ? 'hidden' : ''}`}>
            <div className="comparison-info">
              <h4 className="comparison-title">{item.name}</h4>
              <div className="comparison-meta">
                <span className="comparison-source">{item.source_type.toUpperCase()}</span>
                <span className="comparison-slug">{item.slug}</span>
              </div>
            </div>
            
            <div className="comparison-actions">
              <button
                className="comparison-action-btn"
                onClick={() => onToggle(item.slug)}
                aria-label={item.visible ? 'Hide indicator' : 'Show indicator'}
              >
                {item.visible ? <Eye size={16} /> : <EyeOff size={16} />}
              </button>
              <button
                className="comparison-action-btn remove"
                onClick={() => onRemove(item.slug)}
                aria-label="Remove indicator"
              >
                <Trash2 size={16} />
              </button>
            </div>
          </div>
        ))}
        
        <div className="comparison-hint">
          <p>{items.length}/10 indicators added</p>
        </div>
      </div>
    </aside>
  );
};

export default ComparisonSidebar;