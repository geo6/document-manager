const path = require('path');
const MinifyPlugin = require('babel-minify-webpack-plugin');

module.exports = (env, options) => {
    return {
        entry: {
            'dm': './resources/javascript/main.js'
        },
        output: {
            filename: '[name].min.js',
            path: path.resolve(__dirname, 'public/js')
        },
        module: {
        },
        plugins: [
            // See https://github.com/mlwilkerson/uglify-es-terser-92percent-repro/
            new MinifyPlugin()
        ],
        // Production : see https://github.com/webpack-contrib/babel-minify-webpack-plugin/issues/68
        devtool: options.mode === 'development' ? 'eval-cheap-module-source-map' : false
    };
};
