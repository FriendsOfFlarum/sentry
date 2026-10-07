import bootstrapForum from '@flarum/jest-config/src/bootstrap/forum';
import app from 'flarum/forum/app';
import { getClient } from '@sentry/browser';
import waitFor from './waitFor';

beforeAll(async () => {
  // jsdom lacks the Performance Timeline API that browser tracing's web vitals read; every real browser has it.
  performance.getEntriesByType ??= () => [];

  await import('../../../src/forum/index');

  bootstrapForum({ 'fof-sentry': { dsn: 'https://public@example.ingest.sentry.io/1', tracesSampleRate: 0.25 } });
  app.boot();
});

describe('forum boot with frontend tracing on', () => {
  it('loads browser tracing on demand', async () => {
    expect(await waitFor(() => !!getClient()?.getIntegrationByName('BrowserTracing'))).toBe(true);
  });
});
