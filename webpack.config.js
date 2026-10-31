/** @format */
/**
 * External dependencies
 */
const MiniCssExtractPlugin = require( 'mini-css-extract-plugin' );
const TerserPlugin = require( 'terser-webpack-plugin' );
const CssMinimizerPlugin = require( 'css-minimizer-webpack-plugin' );

const NODE_ENV = process.env.NODE_ENV || 'development';
const IS_PRODUCTION = NODE_ENV === 'production';
const JS_ENTRY_POINTS = {
	front : './src/front/index.js',
	admin : './src/admin/index.js',
	blocks: './src/blocks/index.js',
};

module.exports = {
	mode : NODE_ENV,
	entry: JS_ENTRY_POINTS,
	output: {
		filename: 'assets/js/[name].js',
		path    : __dirname,
	},
	externals: {
		'@wordpress/blocks'              : [ 'wp', 'blocks' ],
		'@wordpress/element'             : [ 'wp', 'element' ],
		'@wordpress/i18n'                : [ 'wp', 'i18n' ],
		'@wordpress/server-side-render'  : [ 'wp', 'serverSideRender' ],
		'@wordpress/block-editor'        : [ 'wp', 'blockEditor' ],
	},
	externalsType: 'window',
	module: {
		rules: [
			{
				test   : /\.js$/,
				loader : 'babel-loader',
				exclude: /node_modules/,
			},
			{
				test: /\.(less|css)$/,
				use : [
					MiniCssExtractPlugin.loader,
					{ loader: 'css-loader', options: { url: false, sourceMap: false } },
					{ loader: 'less-loader', options: { sourceMap: false } },
				],
			},
		],
	},
	plugins: [
		new MiniCssExtractPlugin( { filename: 'assets/css/[name].css' } ),
	],
	optimization: {
		minimize : IS_PRODUCTION,
		minimizer: [ new TerserPlugin( { extractComments: false } ), new CssMinimizerPlugin() ],
	},
	devtool: IS_PRODUCTION ? false : 'inline-source-map',
};
