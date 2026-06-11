import { describe, expect, it } from 'vitest';
import { STRINGS } from '../src/widget/strings';

describe('widget STRINGS', () => {
  it('ships the English and Russian locales', () => {
    expect(Object.keys(STRINGS)).toEqual(expect.arrayContaining(['en', 'ru']));
  });

  it('ships all required locales', () => {
    expect(Object.keys(STRINGS)).toEqual(
      expect.arrayContaining(['en', 'ru', 'es', 'pt', 'zh', 'fr', 'de', 'sr']),
    );
  });

  it('defines the same keys in every locale (no missing translations)', () => {
    const reference = Object.keys(STRINGS.en!).sort();

    for (const [locale, strings] of Object.entries(STRINGS)) {
      const localeKeys = Object.keys(strings).sort();
      expect(localeKeys, `locale ${locale}`).toEqual(reference);
    }
  });

  it('has no empty string values in any locale', () => {
    for (const [locale, strings] of Object.entries(STRINGS)) {
      for (const [key, value] of Object.entries(strings)) {
        expect(value, `locale ${locale}, key ${key}`).not.toBe('');
      }
    }
  });

  it('preserves the toggle glyph in every locale', () => {
    for (const [locale, strings] of Object.entries(STRINGS)) {
      expect(strings.toggle, `locale ${locale} toggle`).toBe('⇪');
    }
  });

  it('includes the :name placeholder in the uploading string for every locale', () => {
    for (const [locale, strings] of Object.entries(STRINGS)) {
      expect(strings.uploading, `locale ${locale} uploading`).toContain(':name');
    }
  });
});
