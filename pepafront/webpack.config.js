const webpack = require('webpack'); //to access built-in plugins
const package = require('./package.json');

const path = require('path');
const CopyPlugin = require("copy-webpack-plugin");
const WebPackAngularTranslate = require("webpack-angular-translate");


const HtmlWebpackPlugin = require('html-webpack-plugin');
const {
    CleanWebpackPlugin
} = require('clean-webpack-plugin');

//const UglifyJsPlugin = require('uglifyjs-webpack-plugin');
const CompressionPlugin = require('compression-webpack-plugin');
const loader = require('sass-loader');

var DEV_SERVER = process.argv[1].indexOf('webpack-dev-server') !== -1;
var DEV = DEV_SERVER || process.env.DEV;



module.exports = {
    //    target: 'node',

    mode: DEV ? 'development' : 'production',
    //    context: __dirname + '/src',
    //  externals: [nodeExternals()],
    entry: {
        app: './src/app.module.ajs.ts',
        //vendor: ['angular']
        //vendor: getVendorPackages()

    },
    //    target:
    //    'electron-renderer',
    devServer: {
        static: path.resolve(__dirname, ''),

        compress: true,
        host: '0.0.0.0',
        open: false,
        //disableHostCheck: true,
        headers: {
            'Access-Control-Allow-Origin': 'https://drive.google.com',
        },
        proxy: {
            '/apiL': {
                target: 'http://localhost:8091/public',
                //                target: 'http://localhost:9002',
                headers: {
                    'host': 'localhost'
                },
                changeOrigin: true,
            },
            '/api3': {
                target: 'https://sancristobal.efaisa.com.ar/',
                secure: true,
                changeOrigin: true,
            },
            '/api': {   //Siderca
                target: 'http://10.8.0.3/',
                secure: true,
                changeOrigin: true,
            },
            '/apiSide': {   //Bomberos
                target: 'http://10.8.0.8/',
                secure: true,
                changeOrigin: true,
            },
            '/apiM': {   //Bouchard
                target: 'http://192.168.4.1/',
                secure: true,
                changeOrigin: true,
            },
            '/api9': { //Siderca GEAC Master
                target: 'http://10.8.0.9/',
                secure: true,
                changeOrigin: true,
            },
            '/apiX': { //FourSeasons
                target: 'http://10.8.0.7/',
                secure: true,
                changeOrigin: true,
            },

            '/vid': { //Siderca GEAC Master
                target: 'http://10.8.0.8/',
                secure: true,
                changeOrigin: true,
            },


            '/wssubX': {
                //                target: 'http://localhost:9002',
                target: 'wss://sancristobal.efaisa.com.ar/',
                ws: true,
                secure: true,
                changeOrigin: true,


            },
            '/wssub': {
                //                target: 'http://localhost:9002',
                target: 'ws://10.8.0.8/',
                ws: true,
                secure: false,
                changeOrigin: true,


            },

            /*
            '/wssub/pantalla/mi': {
//                target: 'http://localhost:9002',
                target: 'ws://localhost:8090/',

                ws: true,
                secure: false

            },
            '/wssub/pantalla/input/io/estados/display_area54/movcred/common': {
//                target: 'http://localhost:9002',
                target: 'ws://localhost:8090/',

                ws: true,
                secure: false

            },
*/
            /*
            '/wssub/pantalla/input/io/estados/display_area54/movcred/common': {
                target: 'http://localhost:9002',
                ws: true,
                secure: false

            },
            */
            /*
            '/ws': {
                target: 'ws://localhost:8090/',
                ws: true,
                secure: false
            },
            */
        },
        //        transportMode: 'ws',

        port: 9000,

    },

    output: {
        path: path.resolve(__dirname, 'dist'),
        publicPath: '',

        filename: '[name].bundle.js'
    },
    node: {
        __dirname: true,
    },
    module: {
        rules: [
            //            { test: /\.(ts|js)x?$/, exclude: /node_modules/, loader: 'babel-loader' },
            {
                test: [/\.tsx?$/],
                use: [{
                    loader: 'ts-loader',
                    options: {
                        transpileOnly: true,
                    }
                }],
            },

            {
                test: [/\.html$/],
                use: [{

                    loader: 'html-loader',
                    options: {
                        minimize: true,
                        attrs: ['source:src', 'img:src', 'use:xlink:href']
                    }
                },
                {
                    loader: WebPackAngularTranslate.htmlLoader()
                }],

            }, {
                test: /\.css$/,
                use: ['style-loader', 'css-loader'],
            },

            {
                test: /\.json$/, // Matches files with a .json extension
                use: [
                    {
                        loader: 'babel-loader',

                        options: {
                            name: '[name].[ext]', // Keeps the original filename and extension
                            outputPath: 'langs', // Optional: output to a subfolder within your output path
                        },
                    },
                ],
            },

            {
                test: [/\.png/, /\.svg/, /ui-grid\.svg/, /ui-grid\.eot/, /ui-grid\.ttf/, /ui-grid\.woff/, /ui-grid\.woff2/, /\.eot/, /\.ttf/, /\.woff/, /\.woff2/],
                use: [{
                    loader: 'file-loader',
                    options: {
                        outputPath: 'assets',
                    }
                }]
            },
            {
                test: [/\.(ogg|mp3|wav)$/i],
                use: [{
                    loader: 'file-loader',
                    options: {
                        outputPath: 'assets',
                    }
                }]
            },
            {
                test: /\.js/,
                loader: WebPackAngularTranslate.jsLoader(),
                options: {
                    parserOptions: {
                        sourceType: "module"
                    }
                }
            },

        ]
    },
    plugins: [
        new WebPackAngularTranslate.Plugin(),
        new webpack.DefinePlugin({
            VERSION: JSON.stringify(package.version),
        }),

        new CleanWebpackPlugin(),
        new HtmlWebpackPlugin({
            title: 'Prueba',
            filename: 'index.html',
            template: './Pages/app.html',
            inject: 'body',
            hash: true,
            /*            minify: {
                      removeComments: true,
                      collapseWhitespace: false
                  }
                  */
        }),
        new CopyPlugin({
            patterns: [
                { from: "langs", to: "langs" },
            ],
        }),
        new webpack.ProgressPlugin(),
        new webpack.ProvidePlugin({
            $: 'jquery',
            jQuery: 'jquery',
            'window.jQuery': 'jquery',
            'window.$': 'jquery',
            echarts: 'echarts',
            moment: 'moment',
            //      angular: 'angular',
        }),

        new CompressionPlugin({
            //            asset: '[path].gz[query]',
            algorithm: 'gzip',
            //algorithm: 'brotliCompress',
            test: /\.(js|jsx|css|html|svg|png|jpg|jpeg|ttf|eot)$/,
            threshold: 10240,
            minRatio: 0.8,
        }),
    ],
    resolve: {
        extensions: ['.ts', '.tsx', '.js']
    },

    optimization: {
        /*
                splitChunks: {
                    chunks: 'async',
                    minSize: 30000,
                    minRemainingSize: 0,
                    maxSize: 0,
                    minChunks: 1,
                    maxAsyncRequests: 6,
                    maxInitialRequests: 4,
                    automaticNameDelimiter: '~',
                    cacheGroups: {
                        defaultVendors: {
                            test: /[\\/]node_modules[\\/]/,
                            priority: -10
                        },
                        default: {
                            minChunks: 2,
                            priority: -20,
                            reuseExistingChunk: true
                        }
                    }
                },

                /*        minimizer: [new UglifyJsPlugin({
                        parallel: true,
                        cache: true,
                    }),],        
            */
        runtimeChunk: 'single',
        splitChunks: {
            chunks: 'all',
            maxInitialRequests: Infinity,
            minSize: 0,
            cacheGroups: {
                vendor: {
                    test: /[\\/]node_modules[\\/]/,
                    name: 'vendors',
                    /*
          name(module) {
              // get the name. E.g. node_modules/packageName/not/this/part.js
              // or node_modules/packageName
              const packageName = module.context.match(/[\\/]node_modules[\\/](.*?)([\\/]|$)/)[1];

              // npm package names are URL-safe, but some servers don't like @ symbols
              return `npm.${packageName.replace('@', '')}`;
          },*/
                },
            },
        },
    },
};
