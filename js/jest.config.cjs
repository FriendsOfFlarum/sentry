const config = require('@flarum/jest-config')();

module.exports = {
  ...config,
  globals: {
    ...config.globals,
    // Build-time flags normally injected by webpack's DefinePlugin (see webpack.config.cjs).
    __SENTRY_DEBUG__: false,
  },
};
