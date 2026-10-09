<?php
/**
 * Server render of the "Members list" block: the membership register with the last change
 * of each member, when it took effect, who recorded it and when.
 *
 * @var array $attributes Block attributes.
 *
 * @package fair-member-fees
 */

if (!defined('ABSPATH')) {
	exit;
}

if ($attributes['onlyLoggedIn'] && !is_user_logged_in()) {
	printf(
		'<div %1$s><p>%2$s <a href="%3$s">%4$s</a></p></div>',
		get_block_wrapper_attributes(['class' => 'famefe-block famefe-members']), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		esc_html__('The list of members is available to logged-in users.', 'fair-member-fees'),
		esc_url(wp_login_url((string) get_permalink())),
		esc_html__('Log in', 'fair-member-fees')
	);
	return;
}

$famefe_types = [];
if ($attributes['showRegular']) {
	$famefe_types[] = 'regular';
}
if ($attributes['showHonorary']) {
	$famefe_types[] = 'honorary';
}
$famefe_members = $famefe_types ? famefe_get_members(['status' => $attributes['showFormer'] ? 'all' : 'active', 'types' => $famefe_types]) : [];
$famefe_last = famefe_last_changes();
$famefe_names = famefe_name_mode((string) $attributes['nameDisplay']);
$famefe_type_labels = famefe_member_types();
$famefe_change_labels = famefe_change_types();

$famefe_columns = ['name' => __('Name', 'fair-member-fees')];
$famefe_optional = [
	'showMemberType' => ['type', __('Type', 'fair-member-fees')],
	'showMemberSince' => ['since', __('Member since', 'fair-member-fees')],
	'showLastChange' => ['change', __('Last change', 'fair-member-fees')],
	'showChangeDate' => ['change_date', __('Change date', 'fair-member-fees')],
	'showRecordedBy' => ['recorded_by', __('Recorded by', 'fair-member-fees')],
	'showRecordedAt' => ['recorded_at', __('Recorded on', 'fair-member-fees')],
];
foreach ($famefe_optional as $famefe_attr => [$famefe_key, $famefe_label]) {
	if ($attributes[$famefe_attr]) {
		$famefe_columns[$famefe_key] = $famefe_label;
	}
}
?>
<div <?php echo get_block_wrapper_attributes(['class' => 'famefe-block famefe-members']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<?php if (!$famefe_members) : ?>
		<p><?php esc_html_e('No members found.', 'fair-member-fees'); ?></p>
	<?php else : ?>
		<div class="famefe-table-wrap">
			<table class="famefe-table">
				<thead>
					<tr>
						<?php foreach ($famefe_columns as $famefe_key => $famefe_label) : ?>
							<th scope="col"><?php echo esc_html($famefe_label); ?></th>
						<?php endforeach; ?>
					</tr>
				</thead>
				<tbody>
					<?php
					foreach ($famefe_members as $famefe_member) :
						$famefe_entry = $famefe_last[intval($famefe_member->id)] ?? null;
						$famefe_former = $famefe_member->status === 'left';
						$famefe_cells = [
							'name' => famefe_member_name($famefe_member, $famefe_names),
							'type' => $famefe_former ? __('Former member', 'fair-member-fees') : ($famefe_type_labels[$famefe_member->member_type] ?? ''),
							'since' => famefe_format_date($famefe_member->member_since),
							'change' => $famefe_entry ? ($famefe_change_labels[$famefe_entry->change_type] ?? '') : '',
							'change_date' => $famefe_entry ? famefe_format_date($famefe_entry->change_date) : '',
							'recorded_by' => $famefe_entry ? famefe_user_name(intval($famefe_entry->recorded_by), $famefe_names) : '',
							'recorded_at' => $famefe_entry ? famefe_format_date($famefe_entry->recorded_at, true) : '',
						];
						?>
						<tr<?php echo $famefe_former ? ' class="famefe-former"' : ''; ?>>
							<?php foreach (array_keys($famefe_columns) as $famefe_key) : ?>
								<td><?php echo esc_html($famefe_cells[$famefe_key]); ?></td>
							<?php endforeach; ?>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	<?php endif; ?>
</div>
