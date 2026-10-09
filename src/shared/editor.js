import { createBlocksFromInnerBlocksTemplate } from '@wordpress/blocks';
import { useDispatch, useSelect } from '@wordpress/data';
import { InspectorControls } from '@wordpress/block-editor';
import {
	PanelBody,
	RangeControl,
	SelectControl,
	TextControl,
	ToggleControl,
} from '@wordpress/components';
import { useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import LayoutEditor from './layout-editor';
import TextStyle from './text-style';
import { defaultTemplate } from './template';

export default function DisplayEditor( {
	attributes,
	setAttributes,
	type,
	clientId,
} ) {
	const showHelp = useSelect(
		( select ) =>
			select( 'core/block-editor' ).isBlockSelected( clientId ) ||
			select( 'core/block-editor' ).hasSelectedInnerBlock(
				clientId,
				true
			),
		[ clientId ]
	);
	const { replaceInnerBlocks } = useDispatch( 'core/block-editor' );
	useEffect( () => {
		if ( attributes.layoutVersion >= 1 ) {
			return;
		}
		if ( ! attributes.customLayout ) {
			replaceInnerBlocks(
				clientId,
				createBlocksFromInnerBlocksTemplate(
					defaultTemplate( type, attributes )
				),
				false
			);
		}
		setAttributes( { layoutVersion: 1 } );
	}, [ attributes, clientId, type, replaceInnerBlocks, setAttributes ] );
	const isProfile = type === 'profile';
	const isReading = [
		'reading-goal',
		'reading-stats',
		'reading-calendar',
	].includes( type );
	const isGrid = attributes.layout === 'grid';
	const isLists = type === 'lists';
	const isPro = !! window.verseDBSettings?.isPro;
	const text = ( name, label, help ) => (
		<TextControl
			key={ name }
			__nextHasNoMarginBottom
			__next40pxDefaultSize
			label={ label }
			help={ help }
			value={ attributes[ name ] || '' }
			onChange={ ( value ) => setAttributes( { [ name ]: value } ) }
		/>
	);
	const toggle = ( name, label ) => (
		<ToggleControl
			key={ name }
			__nextHasNoMarginBottom
			label={ label }
			checked={ attributes[ name ] }
			onChange={ ( value ) => setAttributes( { [ name ]: value } ) }
		/>
	);
	const availability = ( name, label ) => (
		<SelectControl
			key={ name }
			__nextHasNoMarginBottom
			__next40pxDefaultSize
			label={ label }
			value={ attributes[ name ] }
			options={ [
				{ label: __( 'Any', 'versedb' ), value: '' },
				{ label: __( 'Yes', 'versedb' ), value: '1' },
				{ label: __( 'No', 'versedb' ), value: '0' },
			] }
			onChange={ ( value ) => setAttributes( { [ name ]: value } ) }
		/>
	);
	const range = ( name, label, min, max ) => (
		<RangeControl
			key={ name }
			__nextHasNoMarginBottom
			__next40pxDefaultSize
			label={ label }
			value={ Math.min( max, Math.max( min, attributes[ name ] ) ) }
			min={ min }
			max={ max }
			onChange={ ( value ) => setAttributes( { [ name ]: value } ) }
		/>
	);
	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Display settings', 'versedb' ) }>
					{ ! isReading &&
						toggle(
							'enableLinks',
							__( 'Enable links to VerseDB', 'versedb' )
						) }
					{ ! isProfile &&
						text( 'title', __( 'Heading', 'versedb' ) ) }
					{ isReading && (
						<TextControl
							__nextHasNoMarginBottom
							__next40pxDefaultSize
							type="number"
							min="1900"
							max={ new Date().getUTCFullYear() }
							label={ __( 'Year', 'versedb' ) }
							help={ __(
								'Leave blank for the current year.',
								'versedb'
							) }
							value={ attributes.year || '' }
							onChange={ ( value ) =>
								setAttributes( {
									year: value ? Number( value ) : 0,
								} )
							}
						/>
					) }
					{ ! isProfile && ! isReading && (
						<>
							<SelectControl
								__nextHasNoMarginBottom
								__next40pxDefaultSize
								label={ __( 'Layout', 'versedb' ) }
								value={ attributes.layout }
								options={ [
									{
										label: __( 'Grid', 'versedb' ),
										value: 'grid',
									},
									{
										label: __( 'List', 'versedb' ),
										value: 'list',
									},
								] }
								onChange={ ( layout ) =>
									setAttributes( { layout } )
								}
							/>
							{ isGrid &&
								range(
									'columns',
									__( 'Grid columns', 'versedb' ),
									1,
									6
								) }
							{ isGrid &&
								range(
									'mobileColumns',
									__( 'Mobile grid columns', 'versedb' ),
									1,
									Math.min( 3, attributes.columns )
								) }
							{ range(
								'limit',
								__( 'Number of items', 'versedb' ),
								1,
								type === 'currently-reading' ? 50 : 100
							) }
							{ range(
								'gap',
								__( 'Item spacing (px)', 'versedb' ),
								0,
								64
							) }
							{ ! isLists &&
								toggle(
									'showNsfw',
									__(
										'Show explicit covers without blur',
										'versedb'
									)
								) }
						</>
					) }
					{ type === 'list' && (
						<TextControl
							__nextHasNoMarginBottom
							__next40pxDefaultSize
							type="number"
							min="1"
							label={ __( 'Public list ID', 'versedb' ) }
							help={ __(
								'Use the ID from your list URL. Only your public, published lists can appear.',
								'versedb'
							) }
							value={ attributes.listId || '' }
							onChange={ ( value ) =>
								setAttributes( {
									listId: Number( value ) || 0,
								} )
							}
						/>
					) }
					{ type === 'reading-calendar' && (
						<>
							{ range(
								'cellSize',
								__( 'Day cell size (px)', 'versedb' ),
								8,
								24
							) }
							{ range(
								'cellGap',
								__( 'Day cell spacing (px)', 'versedb' ),
								0,
								8
							) }
						</>
					) }
				</PanelBody>
				{ type === 'collection' && (
					<PanelBody
						title={ __( 'Collection filters', 'versedb' ) }
						initialOpen={ false }
					>
						{ text( 'search', __( 'Search', 'versedb' ) ) }
						<SelectControl
							__nextHasNoMarginBottom
							__next40pxDefaultSize
							label={ __( 'Status', 'versedb' ) }
							value={ attributes.status }
							options={ [
								{
									label: __( 'Owned', 'versedb' ),
									value: 'owned',
								},
								{
									label: __( 'For sale', 'versedb' ),
									value: 'for_sale',
								},
								{
									label: __( 'Sold', 'versedb' ),
									value: 'sold',
								},
								{ label: __( 'All', 'versedb' ), value: 'all' },
							] }
							onChange={ ( status ) =>
								setAttributes( { status } )
							}
						/>
						{ availability(
							'for_sale',
							__( 'Available for sale', 'versedb' )
						) }
						{ availability(
							'for_trade',
							__( 'Available for trade', 'versedb' )
						) }
						{ text( 'format', __( 'Format', 'versedb' ) ) }
						{ text( 'condition', __( 'Condition', 'versedb' ) ) }
						{ text(
							'publisher_id',
							__( 'Publisher ID', 'versedb' )
						) }
						{ text( 'series_id', __( 'Series ID', 'versedb' ) ) }
						<SelectControl
							__nextHasNoMarginBottom
							__next40pxDefaultSize
							label={ __( 'Graded copies', 'versedb' ) }
							value={ attributes.graded }
							options={ [
								{
									label: __( 'All copies', 'versedb' ),
									value: '',
								},
								{
									label: __( 'Graded', 'versedb' ),
									value: '1',
								},
								{
									label: __( 'Not graded', 'versedb' ),
									value: '0',
								},
							] }
							onChange={ ( graded ) =>
								setAttributes( { graded } )
							}
						/>
						<SelectControl
							__nextHasNoMarginBottom
							__next40pxDefaultSize
							label={ __( 'Read status', 'versedb' ) }
							value={ attributes.read_status }
							options={ [
								{ label: __( 'Any', 'versedb' ), value: '' },
								{
									label: __( 'Read', 'versedb' ),
									value: 'read',
								},
								{
									label: __( 'Unread', 'versedb' ),
									value: 'unread',
								},
							] }
							onChange={ ( readStatus ) =>
								setAttributes( { read_status: readStatus } )
							}
						/>
						<SelectControl
							__nextHasNoMarginBottom
							__next40pxDefaultSize
							label={ __( 'Review filter', 'versedb' ) }
							value={ attributes.review }
							options={ [
								{ label: __( 'Any', 'versedb' ), value: '' },
								{
									label: __( 'Not reviewed', 'versedb' ),
									value: 'not_reviewed',
								},
								...Array.from( { length: 10 }, ( _, index ) => {
									const rating = String( ( index + 1 ) / 2 );
									return { label: rating, value: rating };
								} ),
							] }
							onChange={ ( review ) =>
								setAttributes( { review } )
							}
						/>
						<SelectControl
							__nextHasNoMarginBottom
							__next40pxDefaultSize
							label={ __( 'Order by', 'versedb' ) }
							value={ attributes.sort_by }
							options={ [
								{
									label: __( 'Date added', 'versedb' ),
									value: 'date_added',
								},
								{
									label: __( 'Name', 'versedb' ),
									value: 'title',
								},
								{
									label: __( 'Release date', 'versedb' ),
									value: 'release_date',
								},
							] }
							onChange={ ( sortBy ) =>
								setAttributes( { sort_by: sortBy } )
							}
						/>
						<SelectControl
							__nextHasNoMarginBottom
							__next40pxDefaultSize
							label={ __( 'Order', 'versedb' ) }
							value={ attributes.sort_order }
							options={ [
								{
									label: __( 'Descending', 'versedb' ),
									value: 'desc',
								},
								{
									label: __( 'Ascending', 'versedb' ),
									value: 'asc',
								},
							] }
							onChange={ ( sortOrder ) =>
								setAttributes( { sort_order: sortOrder } )
							}
						/>
						{ isPro && (
							<>
								<SelectControl
									__nextHasNoMarginBottom
									__next40pxDefaultSize
									label={ __(
										'Signed copies (Pro)',
										'versedb'
									) }
									value={ attributes.is_signed }
									options={ [
										{
											label: __(
												'All copies',
												'versedb'
											),
											value: '',
										},
										{
											label: __( 'Signed', 'versedb' ),
											value: '1',
										},
										{
											label: __(
												'Not signed',
												'versedb'
											),
											value: '0',
										},
									] }
									onChange={ ( isSigned ) =>
										setAttributes( { is_signed: isSigned } )
									}
								/>
								{ text(
									'grade_min',
									__( 'Minimum grade (Pro)', 'versedb' )
								) }
								{ text(
									'grade_max',
									__( 'Maximum grade (Pro)', 'versedb' )
								) }
								{ text(
									'grading_company',
									__( 'Grading company (Pro)', 'versedb' )
								) }
								{ text(
									'genre_id',
									__( 'Genre ID (Pro)', 'versedb' )
								) }
								{ text(
									'creator_id',
									__( 'Creator ID (Pro)', 'versedb' )
								) }
								{ text(
									'character_id',
									__( 'Character ID (Pro)', 'versedb' )
								) }
							</>
						) }
					</PanelBody>
				) }
			</InspectorControls>
			<InspectorControls group="styles">
				{ ! isProfile && (
					<TextStyle
						title={ __( 'Heading text', 'versedb' ) }
						value={ attributes.headingStyle }
						onChange={ ( headingStyle ) =>
							setAttributes( { headingStyle } )
						}
					/>
				) }
				<TextStyle
					title={ __( 'Display credit text', 'versedb' ) }
					value={ attributes.creditStyle }
					onChange={ ( creditStyle ) =>
						setAttributes( { creditStyle } )
					}
				/>
			</InspectorControls>
			<LayoutEditor
				attributes={ attributes }
				type={ type }
				showHelp={ showHelp }
				clientId={ clientId }
			/>
		</>
	);
}
