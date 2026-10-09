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
	<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="famefe-form">
		<?php echo famefe_block_form_fields('famefe_block_hours', $famefe_block_id); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the function. ?>
		<?php if ($famefe_manage) : ?>
			<p>
				<label for="<?php echo esc_attr($famefe_uid); ?>-member"><?php esc_html_e('Member', 'fair-member-fees'); ?></label>
				<select id="<?php echo esc_attr($famefe_uid); ?>-member" name="member_id">
					<?php if (!$famefe_is_member) : ?>
						<option value="0" selected><?php echo esc_html($famefe_my_name); ?></option>
					<?php endif; ?>
					<?php foreach (famefe_get_members(['status' => 'active']) as $famefe_member) : ?>
						<option value="<?php echo intval($famefe_member->id); ?>" <?php selected($famefe_me ? intval($famefe_me->id) : 0, intval($famefe_member->id)); ?>><?php echo esc_html(famefe_member_name($famefe_member)); ?></option>
					<?php endforeach; ?>
				</select>
			</p>
		<?php else : ?>
			<p class="famefe-form__fixed">
				<span class="famefe-form__label"><?php esc_html_e('Member', 'fair-member-fees'); ?></span>
				<strong><?php echo esc_html($famefe_my_name); ?></strong>
			</p>
		<?php endif; ?>
		<?php if ($famefe_date_fixed) : ?>
			<p class="famefe-form__fixed">
				<span class="famefe-form__label"><?php esc_html_e('Date', 'fair-member-fees'); ?></span>
				<strong><?php echo esc_html(famefe_format_date(famefe_today())); ?></strong>
				<input type="hidden" name="work_date" value="<?php echo esc_attr(famefe_today()); ?>">
			</p>
		<?php else : ?>
			<p>
				<label for="<?php echo esc_attr($famefe_uid); ?>-date"><?php esc_html_e('Date', 'fair-member-fees'); ?></label>
				<input type="date" id="<?php echo esc_attr($famefe_uid); ?>-date" name="work_date" required
					value="<?php echo esc_attr(famefe_today()); ?>"
					<?php if (isset($famefe_limits['days_back'])) : ?>
						min="<?php echo esc_attr(famefe_days_ago($famefe_limits['days_back'])); ?>"
					<?php endif; ?>
					max="<?php echo esc_attr(famefe_today()); ?>">
			</p>
		<?php endif; ?>
		<p>
			<label for="<?php echo esc_attr($famefe_uid); ?>-hours"><?php esc_html_e('Hours', 'fair-member-fees'); ?></label>
			<select id="<?php echo esc_attr($famefe_uid); ?>-hours" name="hours" required>
				<?php foreach ($famefe_options as $famefe_value) : ?>
					<option value="<?php echo esc_attr((string) $famefe_value); ?>"><?php echo esc_html(famefe_format_hours($famefe_value)); ?></option>
				<?php endforeach; ?>
			</select>
		</p>
		<p class="famefe-form__wide">
			<label for="<?php echo esc_attr($famefe_uid); ?>-description"><?php esc_html_e('Work done', 'fair-member-fees'); ?></label>
			<input type="text" id="<?php echo esc_attr($famefe_uid); ?>-description" name="description" maxlength="255" <?php echo $famefe_limits['require_description'] ? 'required' : ''; ?>>
		</p>
		<p><button type="submit" class="wp-element-button famefe-button"><?php echo esc_html($famefe_button); ?></button></p>
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
