import { describe, expect, it } from 'vitest';
import { STRINGS } from '../src/widget/strings';

describe('widget STRINGS', () => {
  it('ships the English and Russian locales', () => {
    expect(Object.keys(STRINGS)).toEqual(expect.arrayContaining(['en', 'ru']));
  });

  it('defines the same keys in every locale (no missing translations)', () => {
    const reference = Object.keys(STRINGS.en!).sort();

    for (const [locale, strings] of Object.entries(STRINGS)) {
      expect(Object.keys(strings).sort(), `locale ${locale}`).toEqual(reference);
    }
  });
});
