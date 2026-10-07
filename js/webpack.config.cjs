const webpack = require('webpack');
const { BundleAnalyzerPlugin } = require('webpack-bundle-analyzer');
const flarumConfig = require('flarum-webpack-config');

const buildDist = (filename, env, define = {}, buildAdmin = false, clean = false) => {
  const config = flarumConfig();

  return {
    ...config,
    // No need to build admin JS multiple times
    entry: buildAdmin ? config.entry : { forum: config.entry.forum },
    output: {
      ...config.output,
      filename,
      clean,
    },
    // flarum-webpack-config returns the same module-level plugins array on every call, so copy it.
    // Its own ANALYZER=true adds one analyzer shared by every variant; --env analyze gives one per variant.
    plugins: [
      ...config.plugins,
      new webpack.DefinePlugin({
        __SENTRY_DEBUG__: false,
        __SENTRY_SESSION_REPLAY__: false,
        __SENTRY_TRACING__: false,
        ...define,
      }),
      env.analyze && new BundleAnalyzerPlugin({
        analyzerPort: 'auto',
      }),
    ].filter(Boolean),
  };
};

module.exports = env => {
  // The four builds run in parallel into the same dist, so cleaning must spare the variants or it can delete a sibling's output.
  const plain = buildDist('[name].js', env, {}, true, { keep: /^forum\.(tracing|replay)/ });

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
