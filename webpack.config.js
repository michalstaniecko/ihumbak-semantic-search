/**
 * Webpack configuration for ihumbak-semantic-search
 */

const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const path = require( 'path' );

module.exports = {
	...defaultConfig,
	entry: {
		'semantic-search': path.resolve(
			process.cwd(),
			'src-webpack',
			'index.js'
		),
	},
};
