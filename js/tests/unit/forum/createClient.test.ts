import { jest } from '@jest/globals';
import path from 'path';
import bootstrapForum from '@flarum/jest-config/src/bootstrap/forum';
import app from 'flarum/forum/app';

// Keep the real SDK but swap `showReportDialog` for a spy, so no dialog
// script is injected and calls can be asserted.
const showReportDialog = jest.fn();

jest.unstable_mockModule('@sentry/browser', async () => {
  const actual = await import(path.resolve('node_modules/@sentry/browser/build/npm/cjs/prod/index.js'));

  return { ...actual, showReportDialog };
});

let Sentry: typeof import('@sentry/browser');
let sentry: any;

// No DSN: the client never sends anything over the network.
const baseConfig = { environment: 'test', release: '1.0.0', tracesSampleRate: 0 };

beforeAll(async () => {
  Sentry = await import('@sentry/browser');
  await import('../../../src/forum/index');
  sentry = (window as any).Sentry;

  bootstrapForum();
  app.boot();

  app.store.pushPayload({
    data: {
      type: 'users',
      id: '1',
      attributes: { username: 'admin', displayName: 'Admin Person', email: 'admin@machine.local' },
      relationships: { groups: { data: [{ type: 'groups', id: '1' }] } },
    },
    included: [{ type: 'groups', id: '1', attributes: { nameSingular: 'Admin', namePlural: 'Admins' } }],
  } as any);
});

beforeEach(() => {
  showReportDialog.mockClear();
  delete app.data['fof-sentry.scrub-emails'];
});

const currentUser = () => Sentry.getIsolationScope().getUser();
const beforeSendOf = (client: any) => client.getOptions().beforeSend;

describe('createClient', () => {
  it('exposes the BC API on window.Sentry', () => {
    expect(Object.keys(sentry).sort()).toEqual(['createClient', 'getClient', 'getUserData', 'setUser', 'showReportDialog']);
  });

  it('binds the new client as the current client', () => {
    const client = sentry.createClient(baseConfig);

    expect(Sentry.getClient()).toBe(client);
    expect(sentry.getClient()).toBe(client);
  });

  it('still allows the foot script to call init()', () => {
    const client = sentry.createClient(baseConfig);

    expect(() => client.init()).not.toThrow();
    expect(Sentry.getClient()).toBe(client);
  });

  it('sets the logged-in user with id, username and groups', () => {
    sentry.createClient(baseConfig);

    expect(currentUser()).toMatchObject({
      id: '1',
      username: 'Admin Person',
      username_slug: 'admin',
      email: 'admin@machine.local',
      groups: 'Admin',
    });
  });

  it('omits the email when emails are scrubbed', () => {
    app.data['fof-sentry.scrub-emails'] = true;
    sentry.createClient(baseConfig);

    expect(currentUser()).toHaveProperty('id', '1');
    expect(currentUser()).not.toHaveProperty('email');
  });
});

describe('getUserData for guests', () => {
  let user: any;

  beforeEach(() => {
    user = app.session.user;
  });

  afterEach(() => {
    app.session.user = user;
    app.data.session.userId = 1;
  });

  it('returns an empty object for a guest', () => {
    app.session.user = null;
    app.data.session.userId = 0;

    expect(sentry.getUserData()).toEqual({});
  });

  it('falls back to the session user id when the user model is unavailable', () => {
    app.session.user = null;
    app.data.session.userId = 5;

    expect(sentry.getUserData()).toEqual({ id: 5 });
  });
});

describe('beforeSend', () => {
  it('sets the logger and merges configured tags', () => {
    const client = sentry.createClient({ ...baseConfig, tags: { site: 'forum' } });
    const event = beforeSendOf(client)({ tags: { existing: 'yes' } }, {});

    expect(event.logger).toBe('javascript');
    expect(event.tags).toEqual({ existing: 'yes', site: 'forum' });
  });

  it('strips the user email when scrubEmails is set', () => {
    const client = sentry.createClient({ ...baseConfig, scrubEmails: true });
    const event = beforeSendOf(client)({ user: { id: '1', email: 'a@b.c' } }, {});

    expect(event.user).toEqual({ id: '1' });
  });

  it('keeps the user email when scrubEmails is not set', () => {
    const client = sentry.createClient(baseConfig);
    const event = beforeSendOf(client)({ user: { id: '1', email: 'a@b.c' } }, {});

    expect(event.user.email).toBe('a@b.c');
  });

  it('shows the report dialog with name and email for exceptions when feedback is on', () => {
    const client = sentry.createClient({ ...baseConfig, showFeedback: true });
    beforeSendOf(client)({ event_id: 'abc', exception: { values: [] } }, {});

    expect(showReportDialog).toHaveBeenCalledTimes(1);
    expect(showReportDialog).toHaveBeenCalledWith({
      eventId: 'abc',
      user: { name: 'Admin Person', email: 'admin@machine.local' },
    });
  });

  it('omits the email from the report dialog when emails are scrubbed', () => {
    app.data['fof-sentry.scrub-emails'] = true;
    const client = sentry.createClient({ ...baseConfig, showFeedback: true });
    beforeSendOf(client)({ event_id: 'abc', exception: { values: [] } }, {});

    expect(showReportDialog).toHaveBeenCalledWith({ eventId: 'abc', user: { name: 'Admin Person' } });
  });

  it('does not show the report dialog for non-exception events', () => {
    const client = sentry.createClient({ ...baseConfig, showFeedback: true });
    beforeSendOf(client)({ event_id: 'abc', message: 'hi' }, {});

    expect(showReportDialog).not.toHaveBeenCalled();
  });

  it('does not show the report dialog when feedback is off', () => {
    const client = sentry.createClient(baseConfig);
    beforeSendOf(client)({ event_id: 'abc', exception: { values: [] } }, {});

    expect(showReportDialog).not.toHaveBeenCalled();
  });
});
