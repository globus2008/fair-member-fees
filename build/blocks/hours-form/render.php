<?php
/**
 * Server render of the "Volunteer hours form" block. The form posts to admin-post.php
 * (famefe_handle_block_hours), which reads the limits from the saved block again.
 *
 * @var array $attributes Block attributes.
 *
 * @package fair-member-fees
 */

if (!defined('ABSPATH')) {
	exit;
}

$famefe_block_id = famefe_block_id($attributes);
$famefe_user_id = get_current_user_id();
$famefe_limits = famefe_hours_block_limits($attributes);
$famefe_step = min($famefe_limits['max_hours'], max(0.1, floatval($attributes['step'])));
$famefe_me = famefe_get_member($famefe_user_id);
$famefe_is_member = $famefe_me && $famefe_me->status === 'active';
$famefe_manage = famefe_can_manage();
$famefe_button = trim((string) $attributes['buttonText']) ?: __('Add hours', 'fair-member-fees');
$famefe_uid = 'famefe-' . ($famefe_block_id ?: wp_unique_id());
// Members record hours only for today unless the block allows another date; managers may always choose it.
$famefe_date_fixed = !$famefe_manage && empty($attributes['allowDateChange']);
$famefe_my_name = $famefe_is_member ? famefe_member_name($famefe_me) : famefe_user_name($famefe_user_id);

if ($famefe_user_id <= 0) {
	printf(
		'<div %1$s><p>%2$s <a href="%3$s">%4$s</a></p></div>',
		get_block_wrapper_attributes(['class' => 'famefe-block famefe-hours-form']), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		esc_html__('Log in to record your volunteer hours.', 'fair-member-fees'),
		esc_url(wp_login_url((string) get_permalink())),
		esc_html__('Log in', 'fair-member-fees')
	);
	return;
}
if (!$famefe_is_member && !$famefe_manage && !famefe_settings('hours_non_members')) {
	echo famefe_block_message(__('Only members can record volunteer hours.', 'fair-member-fees'), 'famefe-hours-form'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the function.
	return;
}

$famefe_options = [];
for ($famefe_h = $famefe_step; $famefe_h <= $famefe_limits['max_hours'] + 0.0001; $famefe_h += $famefe_step) {
	$famefe_options[] = round($famefe_h, 1);
}
$famefe_recent = $attributes['showRecent'] ? famefe_get_hours(['user_id' => $famefe_user_id, 'limit' => max(1, intval($attributes['recentCount']))]) : [];
?>
<div <?php echo get_block_wrapper_attributes(['class' => 'famefe-block famefe-hours-form']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<?php echo famefe_block_notice($famefe_block_id); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the function. ?>
	<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="famefe-hours-entry">
		<?php echo famefe_block_form_fields('famefe_block_hours', $famefe_block_id); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the function. ?>
		<?php
		// The form is a one-row table: the labels form the header line, styled like the headers of the other tables.
		// On narrow screens every cell shows its label (data-label) above the field.
		$famefe_label_member = __('Member', 'fair-member-fees');
		$famefe_label_date = __('Date', 'fair-member-fees');
		$famefe_label_hours = __('Hours', 'fair-member-fees');
		$famefe_label_work = __('Work done', 'fair-member-fees');
		?>
		<div class="famefe-table-wrap">
			<table class="famefe-table famefe-entry-table">
				<thead>
					<tr>
						<th scope="col"><label for="<?php echo esc_attr($famefe_uid); ?>-member"><?php echo esc_html($famefe_label_member); ?></label></th>
						<th scope="col"><label for="<?php echo esc_attr($famefe_uid); ?>-date"><?php echo esc_html($famefe_label_date); ?></label></th>
						<th scope="col"><label for="<?php echo esc_attr($famefe_uid); ?>-hours"><?php echo esc_html($famefe_label_hours); ?></label></th>
						<th scope="col" class="famefe-entry-wide"><label for="<?php echo esc_attr($famefe_uid); ?>-description"><?php echo esc_html($famefe_label_work); ?></label></th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td data-label="<?php echo esc_attr($famefe_label_member); ?>">
							<?php if ($famefe_manage) : ?>
								<select id="<?php echo esc_attr($famefe_uid); ?>-member" name="member_id" aria-label="<?php echo esc_attr($famefe_label_member); ?>">
									<?php if (!$famefe_is_member) : ?>
										<option value="0" selected><?php echo esc_html($famefe_my_name); ?></option>
									<?php endif; ?>
									<?php foreach (famefe_get_members(['status' => 'active']) as $famefe_member) : ?>
										<option value="<?php echo intval($famefe_member->id); ?>" <?php selected($famefe_me ? intval($famefe_me->id) : 0, intval($famefe_member->id)); ?>><?php echo esc_html(famefe_member_name($famefe_member)); ?></option>
									<?php endforeach; ?>
								</select>
							<?php else : ?>
								<output id="<?php echo esc_attr($famefe_uid); ?>-member" class="famefe-entry-fixed"><?php echo esc_html($famefe_my_name); ?></output>
							<?php endif; ?>
						</td>
						<td data-label="<?php echo esc_attr($famefe_label_date); ?>">
							<?php if ($famefe_date_fixed) : ?>
								<output id="<?php echo esc_attr($famefe_uid); ?>-date" class="famefe-entry-fixed"><?php echo esc_html(famefe_format_date(famefe_today())); ?></output>
								<input type="hidden" name="work_date" value="<?php echo esc_attr(famefe_today()); ?>">
							<?php else : ?>
								<input type="date" id="<?php echo esc_attr($famefe_uid); ?>-date" name="work_date" required aria-label="<?php echo esc_attr($famefe_label_date); ?>"
									value="<?php echo esc_attr(famefe_today()); ?>"
									<?php if (isset($famefe_limits['days_back'])) : ?>
										min="<?php echo esc_attr(famefe_days_ago($famefe_limits['days_back'])); ?>"
									<?php endif; ?>
									max="<?php echo esc_attr(famefe_today()); ?>">
							<?php endif; ?>
						</td>
						<td data-label="<?php echo esc_attr($famefe_label_hours); ?>">
							<select id="<?php echo esc_attr($famefe_uid); ?>-hours" name="hours" required aria-label="<?php echo esc_attr($famefe_label_hours); ?>">
								<?php foreach ($famefe_options as $famefe_value) : ?>
									<option value="<?php echo esc_attr((string) $famefe_value); ?>"><?php echo esc_html(famefe_format_hours($famefe_value)); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
						<td data-label="<?php echo esc_attr($famefe_label_work); ?>" class="famefe-entry-wide">
							<input type="text" id="<?php echo esc_attr($famefe_uid); ?>-description" name="description" maxlength="255" aria-label="<?php echo esc_attr($famefe_label_work); ?>" <?php echo $famefe_limits['require_description'] ? 'required' : ''; ?>>
						</td>
					</tr>
				</tbody>
			</table>
		</div>
		<p class="famefe-entry-submit"><button type="submit" class="wp-element-button famefe-button"><?php echo esc_html($famefe_button); ?></button></p>
	</form>

	<?php if ($famefe_recent) : ?>
		<h4 class="famefe-hours-form__recent-title"><?php esc_html_e('My recent entries', 'fair-member-fees'); ?></h4>
		<div class="famefe-table-wrap">
			<table class="famefe-table">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e('Date', 'fair-member-fees'); ?></th>
						<th scope="col"><?php esc_html_e('Hours', 'fair-member-fees'); ?></th>
						<th scope="col"><?php esc_html_e('Work done', 'fair-member-fees'); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($famefe_recent as $famefe_entry) : ?>
						<tr>
							<td><?php echo esc_html(famefe_format_date($famefe_entry->work_date)); ?></td>
							<td><?php echo esc_html(famefe_format_hours(floatval($famefe_entry->hours))); ?></td>
							<td><?php echo esc_html($famefe_entry->description); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	<?php endif; ?>
</div>
