import { runCLI } from '@wp-playground/cli';
import path from 'node:path';

const instance = await runCLI( {
	command: 'server', wp: '6.8', php: '7.4', port: Number( process.env.PORT || 9417 ), workers: 1,
	login: true, verbosity: 'quiet',
	mount: [ { hostPath: path.resolve( '.' ), vfsPath: '/wordpress/wp-content/plugins/versedb' } ],
} );
await instance.playground.mkdirTree( '/wordpress/wp-content/mu-plugins' );
await instance.playground.writeFile( '/wordpress/wp-content/mu-plugins/versedb-preview.php', `<?php define('VERSEDB_TEST_PREVIEW', true); require '/wordpress/wp-content/plugins/versedb/tests/fixtures.php';` );
const response = await instance.playground.run( { code: `<?php
require '/wordpress/wp-load.php';
require_once '/wordpress/wp-admin/includes/plugin.php';
activate_plugin('versedb/versedb.php');
versedb_register_blocks();
versedb_accept_token(str_repeat('sample_token_', 4));
$content = '';
foreach (array('profile','collection','wishlist','pull-list','reading','currently-reading','reading-goal','lists','list','reading-stats','reading-calendar') as $type) {
  $attrs = array('listId'=>9, 'year'=>2026, 'title'=>ucwords(str_replace('-', ' ', $type)), 'showStats'=>true);
  $content .= '<!-- wp:versedb/' . $type . ' ' . wp_json_encode($attrs) . ' /-->\n';
}
$id = wp_insert_post(array('post_type'=>'page','post_status'=>'publish','post_title'=>'VerseDB displays','post_content'=>$content));
do_blocks($content);
for($i=0;$i<4;++$i) { versedb_refresh(); }
echo $id;
` } );
if ( response.errors || response.exitCode ) { throw new Error( response.errors ); }
console.log( `Synthetic preview: ${ instance.serverUrl }/?page_id=${ Buffer.from( response.bytes ).toString().trim() }` );
console.log( `Editor and settings: ${ instance.serverUrl }/wp-admin/` );
