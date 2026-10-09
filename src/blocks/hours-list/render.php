<?php
/**
 * Server render of the "Volunteer hours list" block.
 *
 * @var array $attributes Block attributes.
 *
 * @package fair-member-fees
 */

if (!defined('ABSPATH')) {
	exit;
}

$famefe_user_id = get_current_user_id();
if ($attributes['onlyMine'] && $famefe_user_id <= 0) {
	printf(
		'<div %1$s><p>%2$s <a href="%3$s">%4$s</a></p></div>',
		get_block_wrapper_attributes(['class' => 'famefe-block famefe-hours-list']), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		esc_html__('Log in to see your volunteer hours.', 'fair-member-fees'),
		esc_url(wp_login_url((string) get_permalink())),
		esc_html__('Log in', 'fair-member-fees')
	);
	return;
}

$famefe_filter = [
	'start' => famefe_valid_date($attributes['periodStart']),
	'end' => famefe_valid_date($attributes['periodEnd']),
	'user_id' => $attributes['onlyMine'] ? $famefe_user_id : 0,
];
$famefe_entries = famefe_get_hours($famefe_filter + ['limit' => min(1000, max(1, intval($attributes['count'])))]);
$famefe_names = famefe_name_mode((string) $attributes['nameDisplay']);
$famefe_show_name = $attributes['showName'] && !$attributes['onlyMine'];

// Deleting: members their own entries, managers any entry, within the limits of the block (checked again on the server).
$famefe_block_id = famefe_block_id($attributes);
$famefe_deletable = [];
if ($attributes['allowDelete'] && $famefe_user_id > 0 && $famefe_block_id !== '') {
	$famefe_limits = famefe_hours_list_delete_limits($attributes);
	foreach ($famefe_entries as $famefe_entry) {
		if (famefe_hours_delete_denied($famefe_entry, $famefe_limits) === '') {
			$famefe_deletable[intval($famefe_entry->id)] = true;
		}
	}
}
?>
<div <?php echo get_block_wrapper_attributes(['class' => 'famefe-block famefe-hours-list']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<?php echo famefe_block_notice($famefe_block_id); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the function. ?>
	<?php if (!$famefe_entries) : ?>
		<p><?php esc_html_e('No hours recorded.', 'fair-member-fees'); ?></p>
	<?php else : ?>
		<?php if ($famefe_deletable) : ?>
			<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
			<?php echo famefe_block_form_fields('famefe_block_delete_hours', $famefe_block_id); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the function. ?>
		<?php endif; ?>
		<div class="famefe-table-wrap">
			<table class="famefe-table">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e('Date', 'fair-member-fees'); ?></th>
						<?php if ($famefe_show_name) : ?>
							<th scope="col"><?php esc_html_e('Name', 'fair-member-fees'); ?></th>
						<?php endif; ?>
						<th scope="col" class="famefe-col-hours"><?php esc_html_e('Hours', 'fair-member-fees'); ?></th>
						<?php if ($attributes['showDescription']) : ?>
							<th scope="col"><?php esc_html_e('Work done', 'fair-member-fees'); ?></th>
						<?php endif; ?>
						<?php if ($attributes['showRecordedAt']) : ?>
							<th scope="col"><?php esc_html_e('Recorded on', 'fair-member-fees'); ?></th>
						<?php endif; ?>
						<?php if ($famefe_deletable) : ?>
							<th scope="col"><span class="screen-reader-text"><?php esc_html_e('Actions', 'fair-member-fees'); ?></span></th>
						<?php endif; ?>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($famefe_entries as $famefe_entry) : ?>
						<tr>
							<td><?php echo esc_html(famefe_format_date($famefe_entry->work_date)); ?></td>
							<?php if ($famefe_show_name) : ?>
								<td><?php echo esc_html(famefe_hours_name($famefe_entry, $famefe_names)); ?></td>
							<?php endif; ?>
							<td class="famefe-col-hours"><?php echo esc_html(famefe_format_hours(floatval($famefe_entry->hours))); ?></td>
							<?php if ($attributes['showDescription']) : ?>
								<td><?php echo esc_html($famefe_entry->description); ?></td>
							<?php endif; ?>
							<?php if ($attributes['showRecordedAt']) : ?>
								<td><?php echo esc_html(famefe_format_date($famefe_entry->recorded_at, true)); ?></td>
							<?php endif; ?>
							<?php if ($famefe_deletable) : ?>
								<td class="famefe-col-action">
									<?php if (isset($famefe_deletable[intval($famefe_entry->id)])) : ?>
										<button type="submit" name="entry_id" value="<?php echo intval($famefe_entry->id); ?>" class="famefe-delete-button"
											onclick="return confirm(this.dataset.confirm)"
											data-confirm="<?php esc_attr_e('Delete these hours?', 'fair-member-fees'); ?>"><?php esc_html_e('Delete', 'fair-member-fees'); ?></button>
									<?php endif; ?>
								</td>
							<?php endif; ?>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php if ($famefe_deletable) : ?>
			</form>
		<?php endif; ?>
		<?php if ($attributes['showTotal']) : ?>
			<p class="famefe-total">
				<?php
				$famefe_summary = famefe_hours_summary($famefe_filter);
				echo esc_html(sprintf(
					/* translators: 1: number of hours, 2: number of entries. */
					_n('Total: %1$s hours in %2$d entry', 'Total: %1$s hours in %2$d entries', $famefe_summary['count'], 'fair-member-fees'),
					famefe_format_hours($famefe_summary['hours']),
					$famefe_summary['count']
				));
				?>
			</p>
		<?php endif; ?>
	<?php endif; ?>
</div>
