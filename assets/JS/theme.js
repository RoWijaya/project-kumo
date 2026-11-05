(function () {
  'use strict';
  const root = document.documentElement;
  let preferences = {};
  try { preferences = JSON.parse(localStorage.getItem('kumoPreferences') || '{}'); } catch (_) {}
  const systemDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)');
  const serverValue = (name) => document.querySelector(`meta[name="kumo-${name}"]`)?.content;
  const preference = (name, fallback) => {
    const keys = { theme: ['theme', 'kumoTheme'], accent: ['accent_color', 'kumoAccent'], density: ['density', 'kumoDensity'], 'reduce-motion': ['reduce_motion', 'kumoReduceMotion'] };
    const [storedKey, localKey] = keys[name];
    return serverValue(name) ?? preferences[storedKey] ?? localStorage.getItem(localKey) ?? fallback;
  };
  // Missing / invalid preference resolves to light — never OS dark by default.
  const normalizeTheme = (value) => ['light', 'dark', 'system'].includes(value) ? value : 'light';
  function apply() {
    const raw = preference('theme', 'light');
    const theme = normalizeTheme(raw);
    root.dataset.themePreference = theme;
    root.dataset.theme = theme === 'system' ? ((systemDark && systemDark.matches) ? 'dark' : 'light') : theme;
    const accent = preference('accent', 'sage');
    const density = preference('density', 'comfortable');
    const reduceMotion = preference('reduce-motion', preferences.reduce_motion ?? false);
    root.dataset.accent = ['sage', 'forest', 'ocean', 'lavender'].includes(accent) ? accent : 'sage';
    root.dataset.density = ['comfortable', 'compact'].includes(density) ? density : 'comfortable';
    root.classList.toggle('reduce-motion', reduceMotion === true || reduceMotion === 1 || reduceMotion === '1');
  }
  apply();
  if (systemDark && typeof systemDark.addEventListener === 'function') {
    systemDark.addEventListener('change', () => {
      if (normalizeTheme(preference('theme', 'light')) === 'system') apply();
    });
  }
})();
