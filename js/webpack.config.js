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

const buildDist = (filename, env, define, buildAdmin = false) => merge(
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
      define && new webpack.DefinePlugin(define),
      env.analyze && new BundleAnalyzerPlugin({
        analyzerPort: 'auto',
      }),
    ].filter(Boolean),
  }
);

module.exports = env => {
  const plain = buildDist('[name].js', env, {
    __SENTRY_TRACING__: false,
  }, true);

  const tracing = buildDist('[name].tracing.js', env, {
    __SENTRY_TRACING__: true,
  });

  return [plain, tracing];
};
