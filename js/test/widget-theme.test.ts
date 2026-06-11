// @vitest-environment happy-dom
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { createForm, createWidget } from '../src/widget/widget';

describe('widget theming', () => {
  // Default fetch stub so createWidget's mount-time fetchFiles() (allowList on) never hits
  // the real network — keeps these theming tests offline and the log clean.
  beforeEach(() => {
    vi.stubGlobal(
      'fetch',
      vi.fn(async () => ({ ok: true, status: 200, json: async () => ({ data: { files: [] } }) })),
    );
  });

  afterEach(() => {
    document.body.innerHTML = '';
    vi.unstubAllGlobals();
  });

  it('defaults the root to the light theme', () => {
    const widget = createWidget();
    expect(widget.root.classList.contains('fu-theme-light')).toBe(true);
    expect(widget.root.classList.contains('fu-theme-dark')).toBe(false);
  });

  it('honors theme: "dark" at construction', () => {
    const widget = createWidget({ theme: 'dark' });
    expect(widget.root.classList.contains('fu-theme-dark')).toBe(true);
    expect(widget.root.classList.contains('fu-theme-light')).toBe(false);
    // The widget root still carries its base class.
    expect(widget.root.classList.contains('fu-widget')).toBe(true);
  });

  it('setTheme("dark") swaps the root class at runtime', () => {
    const widget = createWidget();
    expect(widget.root.classList.contains('fu-theme-light')).toBe(true);

    widget.setTheme('dark');
    expect(widget.root.classList.contains('fu-theme-dark')).toBe(true);
    expect(widget.root.classList.contains('fu-theme-light')).toBe(false);

    widget.setTheme('light');
    expect(widget.root.classList.contains('fu-theme-light')).toBe(true);
    expect(widget.root.classList.contains('fu-theme-dark')).toBe(false);
  });

  it('applies the theme to the form template root too', () => {
    const host = document.createElement('div');
    document.body.appendChild(host);

    const form = createForm({ container: host, theme: 'dark', allowList: false });
    expect(host.classList.contains('fu-theme-dark')).toBe(true);
  });

  it('setTheme("auto") does not throw when matchMedia is unavailable', () => {
    const widget = createWidget();
    expect(() => widget.setTheme('auto')).not.toThrow();
    // With no matchMedia, auto resolves to light.
    expect(widget.root.classList.contains('fu-theme-light')).toBe(true);
  });
});
