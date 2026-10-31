module.exports = function( api ) {
	api.cache( true );

	return {
		presets: [ [ '@babel/preset-env', { targets: '> 0.5%, last 2 versions, not dead' } ] ],
		sourceType: 'unambiguous',
		env: {
			production: {
				plugins: [
					[
						'@wordpress/babel-plugin-makepot',
						{
							output: 'i18n/wpreturns.po',
						},
					],
				],
			},
		},
	};
};
