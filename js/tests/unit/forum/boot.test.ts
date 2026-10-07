import bootstrapForum from '@flarum/jest-config/src/bootstrap/forum';
import app from 'flarum/forum/app';
import { BrowserClient, getClient, getIsolationScope } from '@sentry/browser';

const dsn = 'https://public@example.ingest.sentry.io/1';

beforeAll(async () => {
  await import('../../../src/forum/index');

  // The backend puts the browser config in the forum payload (SentryJavaScript.php).
  bootstrapForum({ 'fof-sentry': { dsn, environment: 'test', release: '1.0.0', tracesSampleRate: 0 } });
  app.boot();
});

describe('forum boot', () => {
  it('starts Sentry from the payload as the current client', () => {
    const client = getClient();

    expect(client).toBeInstanceOf(BrowserClient);
    expect(client?.getOptions().dsn).toBe(dsn);
  });

  it('identifies the logged-in user once the session exists', () => {
    // The bootstrap logs in user 1, "Admin".
    expect(getIsolationScope().getUser()).toMatchObject({ id: '1', username: 'Admin' });
  });

  it('loads neither optional integration while their rates are 0', async () => {
    // Give any (wrongly started) dynamic import the time it would need to resolve.
    await new Promise((resolve) => setTimeout(resolve, 100));

    expect(getClient()?.getIntegrationByName('BrowserTracing')).toBeUndefined();
    expect(getClient()?.getIntegrationByName('Replay')).toBeUndefined();
  });

  it('records the visitor IP, like the backend does', () => {
    // Sent with every envelope; Relay only fills in the IP when this is "auto".
    expect(getClient()?.getSdkMetadata()?.sdk?.settings?.infer_ip).toBe('auto');
  });

  it('collects no cookies, request bodies or database values', () => {
    // Opting into user info must not opt into everything else the SDK can collect.
    expect(getClient()?.getDataCollectionOptions()).toMatchObject({ cookies: false, httpBodies: [], databaseQueryData: false });
  });
});
