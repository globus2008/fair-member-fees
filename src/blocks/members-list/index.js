/**
 * Editor side of the "Members list" block (rendered by render.php).
 */
import { registerBlockType } from '@wordpress/blocks';
import { InspectorControls } from '@wordpress/block-editor';
import { PanelBody } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import metadata from './block.json';
import { NameDisplayControl, ServerPreview, Toggles } from '../shared';

function Edit( { attributes, setAttributes } ) {
	return (
		<ServerPreview name={ metadata.name } attributes={ attributes }>
			<InspectorControls>
				<PanelBody title={ __( 'Members', 'fair-member-fees' ) }>
					<Toggles
						attributes={ attributes }
						setAttributes={ setAttributes }
						toggles={ {
							showRegular: __( 'Regular members', 'fair-member-fees' ),
							showHonorary: __(
								'Honorary members',
								'fair-member-fees'
							),
							showFormer: __( 'Former members', 'fair-member-fees' ),
							showSearch: __(
								'Search by name',
								'fair-member-fees'
							),
							onlyLoggedIn: __(
								'Only for logged-in visitors',
								'fair-member-fees'
							),
						} }
					/>
				</PanelBody>
				<PanelBody
					title={ __( 'Columns', 'fair-member-fees' ) }
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
							showMemberType: __( 'Type', 'fair-member-fees' ),
							showMemberSince: __(
								'Member since',
								'fair-member-fees'
							),
							showLastChange: __( 'Last change', 'fair-member-fees' ),
							showChangeDate: __( 'Change date', 'fair-member-fees' ),
							showRecordedBy: __( 'Recorded by', 'fair-member-fees' ),
							showRecordedAt: __( 'Recorded on', 'fair-member-fees' ),
						} }
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
