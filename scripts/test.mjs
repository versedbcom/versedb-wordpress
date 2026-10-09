import { runCLI } from '@wp-playground/cli';
import path from 'node:path';

const instance = await runCLI( {
	command: 'server',
	wp: process.env.WP_VERSION || '6.8',
	php: process.env.PHP_VERSION || '7.4',
	port: 9416,
	workers: 1,
	verbosity: 'quiet',
	mount: [ { hostPath: path.resolve( '.' ), vfsPath: '/wordpress/wp-content/plugins/versedb' } ],
} );
try {
	const activation = await instance.playground.run( {
		code: `<?php
			require '/wordpress/wp-load.php';
			require_once '/wordpress/wp-admin/includes/plugin.php';
			$result = activate_plugin('versedb/versedb.php');
			if (is_wp_error($result)) { throw new RuntimeException($result->get_error_message()); }`,
	} );
	if ( activation.exitCode !== 0 || activation.errors ) {
		throw new Error( activation.errors || 'Plugin activation failed.' );
	}
	const response = await instance.playground.run( {
		code: `<?php
			require '/wordpress/wp-load.php';
			require_once '/wordpress/wp-admin/includes/plugin.php';
			require '/wordpress/wp-content/plugins/versedb/tests/integration.php';`,
	} );
	const output = Buffer.from( response.bytes ).toString();
	if ( response.exitCode !== 0 || response.errors || ! output.includes( 'All integration checks passed.' ) ) {
		throw new Error( response.errors || output || 'Integration checks failed.' );
	}
	process.stdout.write( output );
} finally {
	await instance[ Symbol.asyncDispose ]();
}
