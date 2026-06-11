import { defineConfig } from 'vitest/config';

export default defineConfig({
  test: {
    coverage: {
      provider: 'v8',
      include: ['src/**'],
      reporter: ['text', 'lcov'],
      thresholds: {
        lines: 89,
        functions: 88,
        statements: 89,
        branches: 84,
      },
    },
  },
});
