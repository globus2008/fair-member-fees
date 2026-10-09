/**
 * Editor side of the "Fee calculation explained" block (rendered by render.php).
 */
import { registerBlockType } from '@wordpress/blocks';
import { InspectorControls } from '@wordpress/block-editor';
import { PanelBody, TextControl, ToggleControl } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

import metadata from './block.json';
import { editorSettings, NumberControl, ServerPreview } from '../shared';

function Edit( { attributes, setAttributes } ) {
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
							editorSettings.currency
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
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __( 'Explain the discount', 'fair-member-fees' ) }
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
								onChange={ ( discountShare ) =>
									setAttributes( { discountShare } )
								}
							/>
							<NumberControl
								min={ 0 }
								max={ 100 }
								label={ __( 'Discount (%)', 'fair-member-fees' ) }
								value={ attributes.discountRate }
								onChange={ ( discountRate ) =>
									setAttributes( { discountRate } )
								}
							/>
						</>
					) }
				</PanelBody>
			</InspectorControls>
		</ServerPreview>
	);
}

registerBlockType( metadata.name, {
	edit: Edit,
	save: () => null,
} );
