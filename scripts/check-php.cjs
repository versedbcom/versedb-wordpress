const fs = require( 'node:fs' );
const path = require( 'node:path' );
const { execFileSync } = require( 'node:child_process' );
const Engine = require( 'php-parser' );
const parser = new Engine( { parser: { version: '7.4', suppressErrors: false } } );

function check( entry ) {
	if ( ! fs.existsSync( entry ) ) {
		return;
	}
	if ( fs.statSync( entry ).isDirectory() ) {
		for ( const child of fs.readdirSync( entry ) ) {
			check( path.join( entry, child ) );
		}
	} else if ( entry.endsWith( '.php' ) ) {
		parser.parseCode( fs.readFileSync( entry, 'utf8' ), entry );
		process.stdout.write( execFileSync( 'php', [ '-l', entry ] ) );
	}
}

for ( const entry of [ 'versedb.php', 'uninstall.php', 'includes', 'src', 'tests', 'build' ] ) {
	check( entry );
}
