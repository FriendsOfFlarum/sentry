import bootstrapForum from '@flarum/jest-config/src/bootstrap/forum';
import app from 'flarum/forum/app';
import { getClient } from '@sentry/browser';
import waitFor from './waitFor';

beforeAll(async () => {
  await import('../../../src/forum/index');

  // Error-only replay is enough to need the replay integration.
  bootstrapForum({ 'fof-sentry': { dsn: 'https://public@example.ingest.sentry.io/1', replaysOnErrorSampleRate: 0.1 } });
  app.boot();
});

describe('forum boot with session replay on', () => {
  it('loads replay on demand', async () => {
    expect(await waitFor(() => !!getClient()?.getIntegrationByName('Replay'))).toBe(true);
  });
});
