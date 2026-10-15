/**
 * Editor side of the "Fee calculation explained" block (rendered by render.php).
 */
import { registerBlockType } from '@wordpress/blocks';
import { InspectorControls } from '@wordpress/block-editor';
import { PanelBody, TextControl } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

import metadata from './block.json';
import {
	useEditorSettings,
	DiscountControls,
	NumberControl,
	ServerPreview,
} from '../shared';

function Edit( { attributes, setAttributes } ) {
	const settings = useEditorSettings();
	return (
		<ServerPreview name={ metadata.name } attributes={ attributes }>
			<InspectorControls>
				<PanelBody title={ __( 'Example', 'fair-member-fees' ) }>
					<NumberControl
						min={ 0 }
						step="any"
						label={ sprintf(
							/* translators: %s: currency code. */
							__( 'Base fee (%s)', 'fair-member-fees' ),
							settings.currency
						) }
						value={ attributes.baseFee }
						onChange={ ( baseFee ) => setAttributes( { baseFee } ) }
					/>
					<TextControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __(
							'Hours of the regular members',
							'fair-member-fees'
						) }
						help={ __(
							'One number per member, separated by commas or spaces.',
							'fair-member-fees'
						) }
						value={ attributes.regularHours }
						onChange={ ( regularHours ) =>
							setAttributes( { regularHours } )
						}
					/>
					<TextControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __(
							'Hours of the honorary members',
							'fair-member-fees'
						) }
						value={ attributes.honoraryHours }
						onChange={ ( honoraryHours ) =>
							setAttributes( { honoraryHours } )
						}
					/>
				</PanelBody>
				<PanelBody
					title={ __( 'Discount', 'fair-member-fees' ) }
					initialOpen={ attributes.discountEnabled }
				>
					<DiscountControls
						attributes={ attributes }
						setAttributes={ setAttributes }
						currency={ settings.currency }
						label={ __( 'Explain the discount', 'fair-member-fees' ) }
					/>
				</PanelBody>
			</InspectorControls>
		</ServerPreview>
	);
}

registerBlockType( metadata.name, {
	edit: Edit,
	save: () => null,
} );
