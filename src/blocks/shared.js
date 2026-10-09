/**
 * Editor helpers shared by the Fair Member Fees blocks.
 */
import { useEffect } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import { useBlockProps } from '@wordpress/block-editor';
import {
	SelectControl,
	TextControl,
	ToggleControl,
	Disabled,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import ServerSideRender from '@wordpress/server-side-render';

/**
 * Settings of the plugin passed by PHP (famefe_block_editor_settings()).
 */
export const editorSettings = window.famefeEditor || {
	currency: '',
	decimals: 0,
	nameDisplay: 'full',
};

/**
 * Give the block a permanent blockId, so that its form handler finds the block attributes in
 * the saved page. A copied block gets a new one.
 *
 * @param {Object}   props               Block props.
 * @param {string}   props.clientId      Client ID of the block.
 * @param {Object}   props.attributes    Attributes.
 * @param {Function} props.setAttributes Setter.
 */
export function useBlockId( { clientId, attributes, setAttributes } ) {
	const duplicate = useSelect(
		( select ) => {
			const editor = select( 'core/block-editor' );
			const id = attributes.blockId;
			return (
				!! id &&
				editor
					.getClientIdsWithDescendants()
					.some(
						( other ) =>
							other !== clientId &&
							editor.getBlockAttributes( other )?.blockId === id
					)
			);
		},
		[ clientId, attributes.blockId ]
	);
	useEffect( () => {
		if ( ! attributes.blockId || duplicate ) {
			setAttributes( {
				blockId: Math.random().toString( 36 ).slice( 2, 12 ),
			} );
		}
	}, [ attributes.blockId, duplicate ] ); // eslint-disable-line react-hooks/exhaustive-deps
}

/**
 * Select of the name display (default of the settings, full, short, initials).
 *
 * @param {Object}   props          Props.
 * @param {string}   props.value    Current value.
 * @param {Function} props.onChange Change handler.
 */
export function NameDisplayControl( { value, onChange } ) {
	return (
		<SelectControl
			__next40pxDefaultSize
			__nextHasNoMarginBottom
			label={ __( 'Names', 'fair-member-fees' ) }
			value={ value }
			options={ [
				{
					value: 'default',
					label: __( 'As in the plugin settings', 'fair-member-fees' ),
				},
				{
					value: 'full',
					label: __( 'Full name (Jane Smith)', 'fair-member-fees' ),
				},
				{
					value: 'short',
					label: __(
						'First name and initial (Jane S.)',
						'fair-member-fees'
					),
				},
				{
					value: 'initials',
					label: __( 'Initials (J. S.)', 'fair-member-fees' ),
				},
			] }
			onChange={ onChange }
		/>
	);
}

/**
 * Date fields of a period (empty = see help).
 *
 * @param {Object}   props               Props.
 * @param {Object}   props.attributes    Attributes with periodStart and periodEnd.
 * @param {Function} props.setAttributes Setter.
 * @param {string}   props.help          Help text below the fields.
 */
export function PeriodControls( { attributes, setAttributes, help } ) {
	return (
		<>
			<TextControl
				__next40pxDefaultSize
				__nextHasNoMarginBottom
				type="date"
				label={ __( 'From', 'fair-member-fees' ) }
				value={ attributes.periodStart }
				onChange={ ( value ) => setAttributes( { periodStart: value } ) }
			/>
			<TextControl
				__next40pxDefaultSize
				__nextHasNoMarginBottom
				type="date"
				label={ __( 'To', 'fair-member-fees' ) }
				value={ attributes.periodEnd }
				help={ help }
				onChange={ ( value ) => setAttributes( { periodEnd: value } ) }
			/>
		</>
	);
}

/**
 * Toggles for a set of boolean attributes.
 *
 * @param {Object}   props               Props.
 * @param {Object}   props.attributes    Attributes.
 * @param {Function} props.setAttributes Setter.
 * @param {Object}   props.toggles       { attributeName: label }.
 */
export function Toggles( { attributes, setAttributes, toggles } ) {
	return Object.entries( toggles ).map( ( [ key, label ] ) => (
		<ToggleControl
			__nextHasNoMarginBottom
			key={ key }
			label={ label }
			checked={ !! attributes[ key ] }
			onChange={ ( value ) => setAttributes( { [ key ]: value } ) }
		/>
	) );
}

/**
 * Number field that stores a number.
 *
 * @param {Object}   props          Props of TextControl plus value and onChange( number ).
 * @param {number}   props.value    Value.
 * @param {Function} props.onChange Change handler receiving a number.
 */
export function NumberControl( { value, onChange, ...props } ) {
	return (
		<TextControl
			__next40pxDefaultSize
			__nextHasNoMarginBottom
			type="number"
			{ ...props }
			value={ value }
			onChange={ ( next ) => onChange( parseFloat( next ) || 0 ) }
		/>
	);
}

/**
 * Block wrapper with the server render (forms are disabled in the editor).
 *
 * @param {Object} props            Props.
 * @param {string} props.name       Block name.
 * @param {Object} props.attributes Attributes.
 * @param {Object} props.children   Inspector controls.
 */
export function ServerPreview( { name, attributes, children } ) {
	const blockProps = useBlockProps();
	return (
		<div { ...blockProps }>
			{ children }
			<Disabled>
				<ServerSideRender
					block={ name }
					attributes={ attributes }
					skipBlockSupportAttributes
				/>
			</Disabled>
		</div>
	);
}
