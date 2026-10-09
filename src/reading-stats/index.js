import { InnerBlocks } from '@wordpress/block-editor';
import { registerBlockType } from '@wordpress/blocks';
import DisplayEditor from '../shared/editor';
import metadata from './block.json';
import '../shared/style.scss';

registerBlockType( metadata.name, {
	...metadata,
	edit: ( props ) => <DisplayEditor { ...props } type="reading-stats" />,
	save: () => <InnerBlocks.Content />,
} );
