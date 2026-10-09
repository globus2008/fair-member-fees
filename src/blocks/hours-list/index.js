/**
 * Editor side of the "Volunteer hours list" block (rendered by render.php).
 */
import { registerBlockType } from '@wordpress/blocks';
import { InspectorControls } from '@wordpress/block-editor';
import { PanelBody } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import metadata from './block.json';
import {
	useBlockId,
	NameDisplayControl,
	NumberControl,
	PeriodControls,
	ServerPreview,
	Toggles,
} from '../shared';

function Edit( props ) {
	const { attributes, setAttributes } = props;
	useBlockId( props );
	return (
		<ServerPreview name={ metadata.name } attributes={ attributes }>
			<InspectorControls>
				<PanelBody title={ __( 'Period', 'fair-member-fees' ) }>
					<PeriodControls
						attributes={ attributes }
						setAttributes={ setAttributes }
						help={ __(
							'Leave empty for no limit.',
							'fair-member-fees'
						) }
					/>
					<NumberControl
						min={ 1 }
						max={ 1000 }
						label={ __( 'Maximum entries', 'fair-member-fees' ) }
						value={ attributes.count }
						onChange={ ( count ) =>
							setAttributes( {
								count: Math.min(
									1000,
									Math.max( 1, Math.round( count ) )
								),
							} )
						}
					/>
					<Toggles
						attributes={ attributes }
						setAttributes={ setAttributes }
						toggles={ {
							onlyMine: __(
								'Only the hours of the logged-in visitor',
								'fair-member-fees'
							),
						} }
					/>
				</PanelBody>
				<PanelBody
					title={ __( 'Display', 'fair-member-fees' ) }
					initialOpen={ false }
				>
					<NameDisplayControl
						value={ attributes.nameDisplay }
						onChange={ ( nameDisplay ) =>
							setAttributes( { nameDisplay } )
						}
					/>
					<Toggles
						attributes={ attributes }
						setAttributes={ setAttributes }
						toggles={ {
							showName: __( 'Name', 'fair-member-fees' ),
							showDescription: __( 'Work done', 'fair-member-fees' ),
							showRecordedAt: __(
								'Recorded on',
								'fair-member-fees'
							),
							showTotal: __( 'Total below', 'fair-member-fees' ),
						} }
					/>
				</PanelBody>
				<PanelBody
					title={ __( 'Deleting', 'fair-member-fees' ) }
					initialOpen={ false }
				>
					<Toggles
						attributes={ attributes }
						setAttributes={ setAttributes }
						toggles={ {
							allowDelete: __(
								'Allow deleting hours',
								'fair-member-fees'
							),
						} }
					/>
					{ attributes.allowDelete && (
						<>
							<NumberControl
								min={ 0 }
								max={ 366 }
								label={ __(
									'Members: days after recording',
									'fair-member-fees'
								) }
								help={ __(
									'Members delete only hours they recorded themselves, e.g. after a mistake. 0 = only on the day of recording.',
									'fair-member-fees'
								) }
								value={ attributes.memberDeleteDays }
								onChange={ ( value ) =>
									setAttributes( {
										memberDeleteDays: Math.min(
											366,
											Math.max( 0, Math.round( value ) )
										),
									} )
								}
							/>
							<NumberControl
								min={ 0 }
								max={ 3660 }
								label={ __(
									'Administrators and editors: days after recording',
									'fair-member-fees'
								) }
								help={ __(
									'They may delete any entry within this time. Older entries can still be deleted in Member Fees → Volunteer hours.',
									'fair-member-fees'
								) }
								value={ attributes.managerDeleteDays }
								onChange={ ( value ) =>
									setAttributes( {
										managerDeleteDays: Math.min(
											3660,
											Math.max( 0, Math.round( value ) )
										),
									} )
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
