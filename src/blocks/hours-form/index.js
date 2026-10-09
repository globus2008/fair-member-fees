/**
 * Editor side of the "Volunteer hours form" block (rendered by render.php).
 */
import { registerBlockType } from '@wordpress/blocks';
import { InspectorControls } from '@wordpress/block-editor';
import { PanelBody, TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import metadata from './block.json';
import { useBlockId, NumberControl, ServerPreview, Toggles } from '../shared';

function Edit( props ) {
	const { attributes, setAttributes } = props;
	useBlockId( props );
	return (
		<ServerPreview name={ metadata.name } attributes={ attributes }>
			<InspectorControls>
				<PanelBody title={ __( 'Form', 'fair-member-fees' ) }>
					<NumberControl
						min={ 0.5 }
						max={ 24 }
						step={ 0.5 }
						label={ __( 'Maximum hours per entry', 'fair-member-fees' ) }
						value={ attributes.maxHours }
						onChange={ ( maxHours ) =>
							setAttributes( {
								maxHours: Math.min( 24, Math.max( 0.5, maxHours ) ),
							} )
						}
					/>
					<NumberControl
						min={ 0.1 }
						max={ 4 }
						step={ 0.1 }
						label={ __( 'Step of the hours', 'fair-member-fees' ) }
						value={ attributes.step }
						onChange={ ( step ) =>
							setAttributes( {
								step: Math.min( 4, Math.max( 0.1, step ) ),
							} )
						}
					/>
					<NumberControl
						min={ 0 }
						max={ 366 }
						label={ __( 'Days back', 'fair-member-fees' ) }
						help={ __(
							'How old the date of the work may be. 0 = only today.',
							'fair-member-fees'
						) }
						value={ attributes.daysBack }
						onChange={ ( daysBack ) =>
							setAttributes( {
								daysBack: Math.min(
									366,
									Math.max( 0, Math.round( daysBack ) )
								),
							} )
						}
					/>
					<TextControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Button text', 'fair-member-fees' ) }
						placeholder={ __( 'Add hours', 'fair-member-fees' ) }
						value={ attributes.buttonText }
						onChange={ ( buttonText ) =>
							setAttributes( { buttonText } )
						}
					/>
					<Toggles
						attributes={ attributes }
						setAttributes={ setAttributes }
						toggles={ {
							requireDescription: __(
								'Description of the work is required',
								'fair-member-fees'
							),
							showRecent: __(
								'Show my recent entries',
								'fair-member-fees'
							),
						} }
					/>
					{ attributes.showRecent && (
						<NumberControl
							min={ 1 }
							max={ 50 }
							label={ __(
								'Number of recent entries',
								'fair-member-fees'
							) }
							value={ attributes.recentCount }
							onChange={ ( recentCount ) =>
								setAttributes( {
									recentCount: Math.min(
										50,
										Math.max( 1, Math.round( recentCount ) )
									),
								} )
							}
						/>
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
