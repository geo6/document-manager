const path = require('path');
const MinifyPlugin = require('babel-minify-webpack-plugin');

module.exports = (env, options) => {
    return {
        entry: {
            'fa': './resources/javascript/fontawesome.js',
            'dm': './resources/javascript/main.js',
        },
        output: {
            filename: '[name].min.js',
            path: path.resolve(__dirname, 'public/js')
        },
        module: {
            rules: [{
                test: /\.css$/,
                use: [{
                    loader: 'style-loader'
                }, {
                    loader: 'css-loader'
                }]
            }]
        },
        plugins: [
            // See https://github.com/mlwilkerson/uglify-es-terser-92percent-repro/
            new MinifyPlugin()
        ],
        devtool: options.mode === 'development' ? 'eval-cheap-module-source-map' : 'source-map',
    };
};
