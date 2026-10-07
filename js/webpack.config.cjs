const webpack = require('webpack');
const config = require('flarum-webpack-config')();

module.exports = {
  ...config,
  plugins: [
    ...config.plugins,
    new webpack.DefinePlugin({
      __SENTRY_DEBUG__: false,
    }),
  ],
  optimization: {
    ...config.optimization,
    splitChunks: {
      ...config.optimization.splitChunks,
      cacheGroups: {
        ...config.optimization.splitChunks.cacheGroups,
        // Otherwise code shared by the tracing and replay chunks lands in an unnamed root chunk
        // that jsDirectory() never publishes.
        default: false,
      },
    },
  },
};
