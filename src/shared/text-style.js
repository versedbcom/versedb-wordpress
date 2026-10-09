import {
	Button,
	ColorPalette,
	PanelBody,
	SelectControl,
	TextControl,
} from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { __ } from '@wordpress/i18n';

export function textStyle( value = {}, prefix = '' ) {
	return Object.fromEntries(
		Object.entries( value )
			.filter( ( [ , item ] ) => item !== '' && item !== undefined )
			.map( ( [ key, item ] ) => [
				prefix
					? `${ prefix }${ key.replace( /[A-Z]/g, ( letter ) => `-${ letter.toLowerCase() }` ) }`
					: key,
				[ 'fontSize', 'letterSpacing' ].includes( key )
					? `${ item }px`
					: item,
			] )
	);
}

export default function TextStyle( { title, value = {}, onChange } ) {
	const colors = useSelect(
		( select ) => select( 'core/block-editor' ).getSettings().colors || [],
		[]
	);
	const update = ( key, item ) => onChange( { ...value, [ key ]: item } );
	const number = ( key, label, min, max, step = 1 ) => (
		<TextControl
			key={ key }
			__nextHasNoMarginBottom
			__next40pxDefaultSize
			type="number"
			label={ label }
			min={ min }
			max={ max }
			step={ step }
			value={ value[ key ] ?? '' }
			onChange={ ( item ) =>
				update(
					key,
					item === ''
						? ''
						: Math.max( min, Math.min( max, Number( item ) ) )
				)
			}
		/>
	);
	const select = ( key, label, options ) => (
		<SelectControl
			key={ key }
			__nextHasNoMarginBottom
			__next40pxDefaultSize
			label={ label }
			value={ value[ key ] || '' }
			options={ [
				{ label: __( 'Default', 'versedb' ), value: '' },
				...options.map( ( [ option, name ] ) => ( {
					value: option,
					label: name,
				} ) ),
			] }
			onChange={ ( item ) => update( key, item ) }
		/>
	);
	return (
		<PanelBody title={ title } initialOpen={ false }>
			{ number( 'fontSize', __( 'Font size (px)', 'versedb' ), 8, 144 ) }
			<TextControl
				__nextHasNoMarginBottom
				__next40pxDefaultSize
				label={ __( 'Font family', 'versedb' ) }
				help={ __(
					'Use a font available on your website. Leave blank to use your theme.',
					'versedb'
				) }
				value={ value.fontFamily || '' }
				onChange={ ( item ) => update( 'fontFamily', item ) }
			/>
			{ select( 'fontWeight', __( 'Font weight', 'versedb' ), [
				[ '300', __( 'Light', 'versedb' ) ],
				[ '400', __( 'Regular', 'versedb' ) ],
				[ '500', __( 'Medium', 'versedb' ) ],
				[ '600', __( 'Semibold', 'versedb' ) ],
				[ '700', __( 'Bold', 'versedb' ) ],
				[ '800', __( 'Extra bold', 'versedb' ) ],
			] ) }
			{ select( 'fontStyle', __( 'Font style', 'versedb' ), [
				[ 'normal', __( 'Normal', 'versedb' ) ],
				[ 'italic', __( 'Italic', 'versedb' ) ],
			] ) }
			{ number(
				'lineHeight',
				__( 'Line height', 'versedb' ),
				0.5,
				4,
				0.1
			) }
			{ number(
				'letterSpacing',
				__( 'Letter spacing (px)', 'versedb' ),
				-5,
				20,
				0.1
			) }
			{ select( 'textTransform', __( 'Letter case', 'versedb' ), [
				[ 'none', __( 'Original', 'versedb' ) ],
				[ 'uppercase', __( 'Uppercase', 'versedb' ) ],
				[ 'lowercase', __( 'Lowercase', 'versedb' ) ],
				[ 'capitalize', __( 'Capitalize', 'versedb' ) ],
			] ) }
			{ select( 'textDecoration', __( 'Decoration', 'versedb' ), [
				[ 'none', __( 'None', 'versedb' ) ],
				[ 'underline', __( 'Underline', 'versedb' ) ],
				[ 'line-through', __( 'Strikethrough', 'versedb' ) ],
			] ) }
			{ select( 'textAlign', __( 'Alignment', 'versedb' ), [
				[ 'left', __( 'Left', 'versedb' ) ],
				[ 'center', __( 'Center', 'versedb' ) ],
				[ 'right', __( 'Right', 'versedb' ) ],
			] ) }
			<p>{ __( 'Text color', 'versedb' ) }</p>
			<ColorPalette
				colors={ colors }
				value={ value.color }
				onChange={ ( color ) => update( 'color', color || '' ) }
			/>
			<Button variant="tertiary" onClick={ () => onChange( {} ) }>
				{ __( 'Reset text style', 'versedb' ) }
			</Button>
		</PanelBody>
	);
}
