const webpack = require('webpack');
const { merge } = require('webpack-merge');
const { BundleAnalyzerPlugin } = require('webpack-bundle-analyzer');

const config = merge(
  require('flarum-webpack-config')(),
  {
    plugins: [
      new webpack.DefinePlugin({
        __SENTRY_DEBUG__: false,
      }),
    ],
  }
);

const buildDist = (filename, env, define = {}, buildAdmin = false) => merge(
  config,
  {
    entry: () => {
      const entries = {
        forum: config.entry.forum,
      };

      // No need to build admin JS multiple times
      if (buildAdmin) {
        entries.admin = config.entry.admin;
      }

      return entries;
    },
    output: {
      filename,
    },
    plugins: [
      new webpack.DefinePlugin({
        __SENTRY_SESSION_REPLAY__: false,
        __SENTRY_TRACING__: false,
        ...define,
      }),
      env.analyze && new BundleAnalyzerPlugin({
        analyzerPort: 'auto',
      }),
    ].filter(Boolean),
  }
);

module.exports = env => {
  const plain = buildDist('[name].js', env, {}, true);

  const tracing = buildDist('[name].tracing.js', env, {
    __SENTRY_TRACING__: true,
  });

  const replay = buildDist('[name].replay.js', env, {
    __SENTRY_SESSION_REPLAY__: true,
  });

  const tracingAndReplay = buildDist('[name].tracing.replay.js', env, {
    __SENTRY_TRACING__: true,
    __SENTRY_SESSION_REPLAY__: true,
  });

  return [plain, tracing, replay, tracingAndReplay];
};
