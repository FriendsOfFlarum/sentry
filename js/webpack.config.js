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

const buildDist = (filename, env, define) => merge(
  config,
  {
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
  console.log('env', env);

  const noTracing = buildDist('[name].js', env, {
    __SENTRY_TRACING__: false,
  });
  const tracing = buildDist('[name].tracing.js', env);

  return [noTracing, tracing];
};
