/**
 * Editor side of the "Membership fees" block (rendered by render.php).
 */
import { registerBlockType } from '@wordpress/blocks';
import { InspectorControls } from '@wordpress/block-editor';
import { PanelBody, ToggleControl } from '@wordpress/components';
import { useEffect } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

import metadata from './block.json';
import {
	useEditorSettings,
	useBlockId,
	NameDisplayControl,
	NumberControl,
	PeriodControls,
	ServerPreview,
	Toggles,
} from '../shared';

function Edit( props ) {
	const { attributes, setAttributes } = props;
	const settings = useEditorSettings();
	useBlockId( props );

	// A new block starts with the current calendar year as the period.
	useEffect( () => {
		if ( ! attributes.periodStart && ! attributes.periodEnd ) {
			const year = new Date().getFullYear();
			setAttributes( {
				periodStart: `${ year }-01-01`,
				periodEnd: `${ year }-12-31`,
			} );
		}
	}, [] ); // eslint-disable-line react-hooks/exhaustive-deps

	return (
		<ServerPreview name={ metadata.name } attributes={ attributes }>
			<InspectorControls>
				<PanelBody title={ __( 'Period and fee', 'fair-member-fees' ) }>
					<PeriodControls
						attributes={ attributes }
						setAttributes={ setAttributes }
						help={ __(
							'Members of this period pay; their hours of this period lower the fee. Payments are recorded for exactly this period.',
							'fair-member-fees'
						) }
					/>
					<NumberControl
						min={ 0 }
						step="any"
						label={ sprintf(
							/* translators: %s: currency code. */
							__( 'Base fee (%s)', 'fair-member-fees' ),
							settings.currency
						) }
						help={ __(
							'The fee of a regular member when nobody volunteers. All regular members together pay the base fee times their number.',
							'fair-member-fees'
						) }
						value={ attributes.baseFee }
						onChange={ ( baseFee ) => setAttributes( { baseFee } ) }
					/>
				</PanelBody>
				<PanelBody
					title={ __( 'Discount', 'fair-member-fees' ) }
					initialOpen={ attributes.discountEnabled }
				>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __(
							'Discount for the lowest fees',
							'fair-member-fees'
						) }
						help={ __(
							'Members who volunteered the most pay even less. The discount is not added to the fees of the others.',
							'fair-member-fees'
						) }
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
									setAttributes( {
										discountShare: Math.min(
											100,
											Math.max( 0, discountShare )
										),
									} )
								}
							/>
							<NumberControl
								min={ 0 }
								max={ 100 }
								label={ __( 'Discount (%)', 'fair-member-fees' ) }
								value={ attributes.discountRate }
								onChange={ ( discountRate ) =>
									setAttributes( {
										discountRate: Math.min(
											100,
											Math.max( 0, discountRate )
										),
									} )
								}
							/>
						</>
					) }
				</PanelBody>
				<PanelBody
					title={ __( 'Columns', 'fair-member-fees' ) }
					initialOpen={ false }
				>
					<Toggles
						attributes={ attributes }
						setAttributes={ setAttributes }
						toggles={ {
							showMemberType: __(
								'Membership type',
								'fair-member-fees'
							),
							showHours: __( 'Hours', 'fair-member-fees' ),
							showShare: __( 'Share of hours', 'fair-member-fees' ),
							showGross: __( 'Unreduced fee', 'fair-member-fees' ),
							showDiscount: __(
								'Calculated fee and discount (when the discount is on)',
								'fair-member-fees'
							),
							showPaidOn: __( 'Paid on', 'fair-member-fees' ),
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
							showHonorary: __(
								'List honorary members',
								'fair-member-fees'
							),
							summaryAbove: __(
								'Period and base fee above the table',
								'fair-member-fees'
							),
							showTotals: __(
								'Totals rows at the end of the table',
								'fair-member-fees'
							),
							highlightCurrent: __(
								'Highlight the logged-in member',
								'fair-member-fees'
							),
							showPayButton: __(
								'Online payment button',
								'fair-member-fees'
							),
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
