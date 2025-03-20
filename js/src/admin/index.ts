import app from 'flarum/admin/app';

export { default as extend } from './extend';

app.initializers.add('fof/sentry', () => {
  app.registry.for('fof-sentry');
});
