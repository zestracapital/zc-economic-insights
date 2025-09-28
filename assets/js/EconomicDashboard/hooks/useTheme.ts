import { useState, useEffect } from 'react';
import { ThemeMode } from '../types';

export const useTheme = (defaultTheme: ThemeMode = 'light') => {
  const [theme, setTheme] = useState<ThemeMode>(() => {
    if (typeof window !== 'undefined') {
      const saved = localStorage.getItem('zestra-theme');
      if (saved === 'light' || saved === 'dark') {
        return saved as ThemeMode;
      }
      return defaultTheme;
    }
    return defaultTheme;
  });

  useEffect(() => {
    localStorage.setItem('zestra-theme', theme);
    document.body.className = theme;
  }, [theme]);

  const toggleTheme = () => {
    setTheme(prev => prev === 'light' ? 'dark' : 'light');
  };

  return { theme, toggleTheme };
};