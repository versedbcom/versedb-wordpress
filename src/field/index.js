import { useEffect, useMemo } from '@wordpress/element';
import { registerBlockType } from '@wordpress/blocks';
import {
	AlignmentToolbar,
	BlockControls,
	InspectorControls,
	useBlockProps,
} from '@wordpress/block-editor';
import {
	Disabled,
	PanelBody,
	RangeControl,
	SelectControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { fieldLabels, fieldsForType } from '../shared/template';
import metadata from './block.json';
import TextStyle, { textStyle } from '../shared/text-style';
import '../shared/style.scss';

function Edit( { attributes, setAttributes, context } ) {
	const { field, textAlign, imageWidth, imageHeight, imageFit } = attributes;
	const type = context[ 'versedb/type' ];
	const fields = useMemo( () => fieldsForType( type ), [ type ] );
	const content = context[ 'versedb/fields' ]?.[ field ];
	useEffect( () => {
		if ( type && ! fields.includes( field ) ) {
			const value = fields[ 0 ];
			setAttributes( {
				field: value,
				metadata: {
					...attributes.metadata,
					name: fieldLabels[ value ],
				},
			} );
		}
	}, [ type, field, fields, attributes.metadata, setAttributes ] );

	const isImage = [ 'cover', 'avatar', 'banner' ].includes( field );
	const style = {
		...textStyle( attributes.labelStyle, '--versedb-label-' ),
		textAlign,
		'--versedb-field-image-width': imageWidth
			? `${ imageWidth }px`
			: undefined,
		'--versedb-field-image-height': imageHeight
			? `${ imageHeight }px`
			: undefined,
		'--versedb-field-image-fit': imageFit,
	};
	const props = useBlockProps( {
		className: `versedb-field versedb-field--${ field }`,
		style,
	} );
	return (
		<>
			<BlockControls>
				<AlignmentToolbar
					value={ textAlign }
					onChange={ ( value ) =>
						setAttributes( { textAlign: value || '' } )
					}
				/>
			</BlockControls>
			<InspectorControls>
				<PanelBody title={ __( 'Display field', 'versedb' ) }>
					<SelectControl
						__nextHasNoMarginBottom
						__next40pxDefaultSize
						label={ __( 'Content', 'versedb' ) }
						value={ field }
						options={ fields.map( ( name ) => ( {
							value: name,
							label: fieldLabels[ name ],
						} ) ) }
						onChange={ ( value ) =>
							setAttributes( {
								field: value,
								metadata: {
									...attributes.metadata,
									name: fieldLabels[ value ],
								},
							} )
						}
					/>
					{ ! isImage && (
						<p>
							{ __(
								'Open Styles to change this text’s size, appearance, color and spacing.',
								'versedb'
							) }
						</p>
					) }
					{ isImage && (
						<>
							<RangeControl
								__nextHasNoMarginBottom
								__next40pxDefaultSize
								label={ __( 'Image width (px)', 'versedb' ) }
								help={ __(
									'Zero uses the available width.',
									'versedb'
								) }
								value={ imageWidth }
								min={ 0 }
								max={ 1200 }
								onChange={ ( value ) =>
									setAttributes( { imageWidth: value } )
								}
							/>
							<RangeControl
								__nextHasNoMarginBottom
								__next40pxDefaultSize
								label={ __( 'Image height (px)', 'versedb' ) }
								help={ __(
									'Zero preserves the image proportions.',
									'versedb'
								) }
								value={ imageHeight }
								min={ 0 }
								max={ 1200 }
								onChange={ ( value ) =>
									setAttributes( { imageHeight: value } )
								}
							/>
							<SelectControl
								__nextHasNoMarginBottom
								__next40pxDefaultSize
								label={ __( 'Image fit', 'versedb' ) }
								value={ imageFit }
								options={ [
									{
										label: __( 'Cover', 'versedb' ),
										value: 'cover',
									},
									{
										label: __( 'Contain', 'versedb' ),
										value: 'contain',
									},
								] }
								onChange={ ( value ) =>
									setAttributes( { imageFit: value } )
								}
							/>
						</>
					) }
				</PanelBody>
			</InspectorControls>
			{ type === 'reading-stats' && (
				<InspectorControls group="styles">
					<TextStyle
						title={ __( 'Statistic label', 'versedb' ) }
						value={ attributes.labelStyle }
						onChange={ ( labelStyle ) =>
							setAttributes( { labelStyle } )
						}
					/>
				</InspectorControls>
			) }
			<div { ...props }>
				{ content ? (
					<Disabled>
						<div dangerouslySetInnerHTML={ { __html: content } } />
					</Disabled>
				) : (
					<span className="versedb-field__placeholder">
						{ fieldLabels[ field ] ||
							__( 'Choose a display field', 'versedb' ) }
					</span>
				) }
			</div>
		</>
	);
}

registerBlockType( metadata.name, {
	...metadata,
	edit: Edit,
	save: () => null,
} );
