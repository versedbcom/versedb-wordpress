const config = require( '@wordpress/scripts/config/webpack.config' );

module.exports = {
	...config,
	optimization: {
		...config.optimization,
		splitChunks: {
			...config.optimization.splitChunks,
			cacheGroups: {
				...config.optimization.splitChunks.cacheGroups,
				style: {
					...config.optimization.splitChunks.cacheGroups.style,
					name: 'shared/style',
				},
			},
		},
	},
};
