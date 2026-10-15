/**
 * Editor helpers shared by the Fair Member Fees blocks.
 */
import { useEffect } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import {
	useBlockProps,
	InspectorControls,
	PanelColorSettings,
} from '@wordpress/block-editor';
import {
	SelectControl,
	TextControl,
	ToggleControl,
	Disabled,
} from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import ServerSideRender from '@wordpress/server-side-render';

/**
 * Settings of the plugin, passed by PHP as block editor settings (famefe_block_editor_settings()).
 *
 * @return {Object} { currency, decimals, nameDisplay }.
 */
export function useEditorSettings() {
	return useSelect( ( select ) => {
		// The post and site editors keep all editor settings in core/editor; core/block-editor
		// receives only the keys it knows (the widget editor has no core/editor).
		const editor = select( 'core/editor' );
		return (
			editor?.getEditorSettings?.().famefe ||
			select( 'core/block-editor' ).getSettings().famefe || {
				currency: '',
				decimals: 0,
				nameDisplay: 'full',
			}
		);
	}, [] );
}

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
					label: __(
						'As in the plugin settings',
						'fair-member-fees'
					),
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
				{
					value: 'display',
					label: __(
						'Display name of the user account',
						'fair-member-fees'
					),
				},
				{
					value: 'masked',
					label: __(
						'Shortened for data protection: first 4 and last 4 characters (Janaková)',
						'fair-member-fees'
					),
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
				onChange={ ( value ) =>
					setAttributes( { periodStart: value } )
				}
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
 * Discount settings (discountEnabled, discountShare, discountMode, discountRate, discountTarget)
 * of the Membership fees and Fee calculation explained blocks.
 *
 * @param {Object}   props               Props.
 * @param {Object}   props.attributes    Attributes.
 * @param {Function} props.setAttributes Setter.
 * @param {string}   props.currency      Currency code.
 * @param {string}   props.label         Label of the toggle.
 * @param {string}   props.help          Help of the toggle.
 */
export function DiscountControls( {
	attributes,
	setAttributes,
	currency,
	label,
	help,
} ) {
	const percent = ( key ) => ( value ) =>
		setAttributes( { [ key ]: Math.min( 100, Math.max( 0, value ) ) } );
	return (
		<>
			<ToggleControl
				__nextHasNoMarginBottom
				label={ label }
				help={ help }
				checked={ attributes.discountEnabled }
				onChange={ ( discountEnabled ) =>
					setAttributes( { discountEnabled } )
				}
			/>
			{ attributes.discountEnabled && (
				<>
					<NumberControl
						min={ 0 }
						max={ 100 }
						label={ __(
							'Share of members with the lowest fees (%)',
							'fair-member-fees'
						) }
						value={ attributes.discountShare }
						onChange={ percent( 'discountShare' ) }
					/>
					<SelectControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Discount given by', 'fair-member-fees' ) }
						value={ attributes.discountMode }
						options={ [
							{
								value: 'rate',
								label: __( 'Percentage', 'fair-member-fees' ),
							},
							{
								value: 'total',
								label: __(
									'Total to collect',
									'fair-member-fees'
								),
							},
						] }
						onChange={ ( discountMode ) =>
							setAttributes( { discountMode } )
						}
					/>
					{ attributes.discountMode === 'total' ? (
						<NumberControl
							min={ 0 }
							step="any"
							label={ sprintf(
								/* translators: %s: currency code. */
								__( 'Total to collect (%s)', 'fair-member-fees' ),
								currency
							) }
							help={ __(
								'The discount is calculated so that the regular members pay this amount together. If their calculated fees do not exceed it, there is no discount.',
								'fair-member-fees'
							) }
							value={ attributes.discountTarget }
							onChange={ ( discountTarget ) =>
								setAttributes( {
									discountTarget: Math.max(
										0,
										discountTarget
									),
								} )
							}
						/>
					) : (
						<NumberControl
							min={ 0 }
							max={ 100 }
							label={ __( 'Discount (%)', 'fair-member-fees' ) }
							value={ attributes.discountRate }
							onChange={ percent( 'discountRate' ) }
						/>
					) }
				</>
			) }
		</>
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

/**
 * "Table colours" panel in the Styles tab: header and totals rows (famefe_table_colors_style()).
 *
 * @param {Object}   props               Props.
 * @param {Object}   props.attributes    Attributes with headerBackground, headerText, totalsBackground, totalsText.
 * @param {Function} props.setAttributes Setter.
 */
export function TableColors( { attributes, setAttributes } ) {
	return (
		<InspectorControls group="styles">
			<PanelColorSettings
				title={ __( 'Table colours', 'fair-member-fees' ) }
				initialOpen={ false }
				colorSettings={ [
					[
						'headerBackground',
						__( 'Header background', 'fair-member-fees' ),
					],
					[ 'headerText', __( 'Header text', 'fair-member-fees' ) ],
					[
						'totalsBackground',
						__( 'Totals rows background', 'fair-member-fees' ),
					],
					[
						'totalsText',
						__( 'Totals rows text', 'fair-member-fees' ),
					],
				].map( ( [ key, label ] ) => ( {
					label,
					value: attributes[ key ] || undefined,
					onChange: ( value ) =>
						setAttributes( { [ key ]: value || '' } ),
				} ) ) }
			/>
		</InspectorControls>
	);
}
