import {
	BlockContextProvider,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis -- Core uses this lightweight repeating-block preview API; available in the WordPress 6.8 baseline.
	__experimentalUseBlockPreview as useBlockPreview,
	InnerBlocks,
	useBlockProps,
} from '@wordpress/block-editor';
import { Button, Notice } from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { useSelect } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import { textStyle } from './text-style';

function ItemPreview( { blocks } ) {
	const props = useBlockPreview( {
		blocks,
		props: { className: 'versedb-template-item' },
	} );
	return <div { ...props } />;
}

export default function LayoutEditor( {
	attributes,
	type,
	showHelp,
	clientId,
} ) {
	const blocks = useSelect(
		( select ) => select( 'core/block-editor' ).getBlocks( clientId ),
		[ clientId ]
	);
	const repeated = ! [
		'profile',
		'reading-goal',
		'reading-stats',
		'reading-calendar',
	].includes( type );
	const columns = Math.max( 1, Math.min( 6, attributes.columns || 4 ) );
	const mobileColumns = Math.max(
		1,
		Math.min( 3, columns, attributes.mobileColumns || 2 )
	);
	const [ preview, setPreview ] = useState( 0 );
	const [ result, setResult ] = useState( {
		fields: {},
		items: [],
		hasData: false,
	} );
	const [ error, setError ] = useState( false );
	const [ loading, setLoading ] = useState( true );
	const query = JSON.stringify( attributes );
	useEffect( () => {
		let active = true;
		setLoading( true );
		apiFetch( {
			path: '/versedb/v1/editor-preview',
			method: 'POST',
			data: { type, attributes: JSON.parse( query ) },
		} )
			.then( ( data ) => {
				if ( active ) {
					setResult( data );
					setError( false );
					setLoading( false );
				}
			} )
			.catch( () => {
				if ( active ) {
					setResult( { fields: {}, items: [], hasData: false } );
					setError( true );
					setLoading( false );
				}
			} );
		return () => {
			active = false;
		};
	}, [ query, type, preview ] );
	return (
		<div
			{ ...useBlockProps( {
				className: 'versedb-display versedb-layout-editor',
				style: {
					'--versedb-columns': columns,
					'--versedb-mobile-columns': mobileColumns,
					'--versedb-gap': `${ attributes.gap ?? 16 }px`,
					'--versedb-cell-size': `${ attributes.cellSize || 12 }px`,
					'--versedb-cell-gap': `${ attributes.cellGap ?? 3 }px`,
				},
			} ) }
		>
			{ attributes.title && (
				<h2
					className="versedb-display__title"
					style={ textStyle( attributes.headingStyle ) }
				>
					{ attributes.title }
				</h2>
			) }
			{ showHelp && (
				<p className="versedb-layout-editor__help">
					{ repeated
						? __(
								'Edit the fields in the first item. Your changes apply to every item in this preview.',
								'versedb'
							)
						: __(
								'Move, add or remove fields. Select a field to change its content and styles.',
								'versedb'
							) }
				</p>
			) }
			{ ! loading && ! result.hasData && (
				<Notice
					status={ error ? 'warning' : 'info' }
					isDismissible={ false }
				>
					{ error
						? __(
								'The preview could not load. Your saved layout is unchanged.',
								'versedb'
							)
						: __(
								'No cached preview is available. Field labels are shown until the next refresh.',
								'versedb'
							) }
				</Notice>
			) }
			<div
				className={
					repeated
						? `versedb-items versedb-items--${ attributes.layout === 'list' ? 'list' : 'grid' } versedb-editor-items`
						: 'versedb-editor-single'
				}
			>
				<div className="versedb-template-item versedb-editor-item">
					<BlockContextProvider
						value={ {
							'versedb/type': type,
							'versedb/fields': result.fields,
						} }
					>
						<InnerBlocks
							allowedBlocks={ [
								'versedb/field',
								'core/group',
								'core/columns',
								'core/column',
								'core/paragraph',
								'core/heading',
								'core/spacer',
								'core/separator',
							] }
							templateLock={ false }
						/>
					</BlockContextProvider>
				</div>
				{ repeated &&
					( result.items || [] )
						.slice( 1 )
						.map( ( fields, index ) => (
							<BlockContextProvider
								key={ index }
								value={ {
									'versedb/type': type,
									'versedb/fields': fields,
								} }
							>
								<ItemPreview blocks={ blocks } />
							</BlockContextProvider>
						) ) }
			</div>

			{ result.credit && (
				<div dangerouslySetInnerHTML={ { __html: result.credit } } />
			) }
			<Button
				variant="secondary"
				onClick={ () => setPreview( preview + 1 ) }
			>
				{ __( 'Refresh preview', 'versedb' ) }
			</Button>
		</div>
	);
}
