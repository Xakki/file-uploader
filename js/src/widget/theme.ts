import type { WidgetTheme } from './types';

const DARK_QUERY = '(prefers-color-scheme: dark)';

function prefersDark(): boolean {
  return (
    typeof window !== 'undefined' &&
    typeof window.matchMedia === 'function' &&
    window.matchMedia(DARK_QUERY).matches
  );
}

/** Resolve a theme choice to the concrete class the root element should carry. */
function themeClass(theme: WidgetTheme): 'fu-theme-light' | 'fu-theme-dark' {
  const resolved = theme === 'auto' ? (prefersDark() ? 'dark' : 'light') : theme;

  return resolved === 'dark' ? 'fu-theme-dark' : 'fu-theme-light';
}

/**
 * Owns the root element's `fu-theme-*` class and (for `'auto'`) a live
 * subscription to the OS colour-scheme media query. Re-entrant: `apply()` swaps
 * the class and re-wires the listener; `teardown()` detaches it (idempotent).
 */
export function createThemeManager(root: HTMLElement, initial: WidgetTheme) {
  let media: MediaQueryList | null = null;
  let onChange: ((e: MediaQueryListEvent) => void) | null = null;

  function detachMedia(): void {
    if (media && onChange) {
      // Older engines expose removeListener; modern ones removeEventListener.
      if (typeof media.removeEventListener === 'function') {
        media.removeEventListener('change', onChange);
      } else if (typeof media.removeListener === 'function') {
        media.removeListener(onChange);
      }
    }
    media = null;
    onChange = null;
  }

  function swapClass(theme: WidgetTheme): void {
    root.classList.remove('fu-theme-light', 'fu-theme-dark');
    root.classList.add(themeClass(theme));
  }

  function apply(theme: WidgetTheme): void {
    detachMedia();
    swapClass(theme);
    if (theme === 'auto' && typeof window !== 'undefined' && typeof window.matchMedia === 'function') {
      media = window.matchMedia(DARK_QUERY);
      onChange = () => swapClass('auto');
      if (typeof media.addEventListener === 'function') {
        media.addEventListener('change', onChange);
      } else if (typeof media.addListener === 'function') {
        media.addListener(onChange);
      }
    }
  }

  apply(initial);

  return {
    set: apply,
    teardown: detachMedia,
  };
}
