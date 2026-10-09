<?php
/**
 * Link between members and WordPress user accounts.
 *
 * The register stays in the plugin's tables (members without an account, history that survives a deleted
 * account), but for a linked member the account is the source of the name and e-mail:
 * - a change of the profile is copied to the member (famefe_sync_member_from_user),
 * - a change in the member form is written to the profile (famefe_sync_member_details_with_user),
 * - the user profile shows the membership, the Users list has a Member column,
 * - deleting an account warns that it belongs to a member (the membership stays, only the link goes).
 *
 * @package fair-member-fees
 */

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Copy the name and e-mail of a changed profile to the linked member.
 */
function famefe_sync_member_from_user(int $user_id): void
{
	global $wpdb;
	$member = famefe_get_member($user_id);
	if (!$member) {
		return;
	}
	$details = famefe_user_details($user_id);
	$row = ['email' => $details['email']];
	if ($details['first_name'] !== '' || $details['last_name'] !== '') {
		$row['first_name'] = mb_substr($details['first_name'], 0, 100);
		$row['last_name'] = mb_substr($details['last_name'], 0, 100);
	}
	$wpdb->update(famefe_table('members'), $row, ['id' => intval($member->id)]);
	famefe_flush_member_cache($member);
}
add_action('profile_update', 'famefe_sync_member_from_user');

/**
 * Membership section of the user profile (Users → Edit user, Profile).
 */
function famefe_user_profile_section(WP_User $user): void
{
	$member = famefe_get_member($user->ID);
	$manage = famefe_can_manage();
	if (!$member && !$manage) {
		return;
	}
	?>
	<h2><?php esc_html_e('Membership', 'fair-member-fees'); ?></h2>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><?php esc_html_e('Member', 'fair-member-fees'); ?></th>
			<td>
				<?php if (!$member) : ?>
					<?php esc_html_e('Not a member.', 'fair-member-fees'); ?>
					<a href="<?php echo esc_url(famefe_admin_url('famefe-members', ['action' => 'new', 'user_id' => $user->ID])); ?>"><?php esc_html_e('Add as member', 'fair-member-fees'); ?></a>
				<?php else : ?>
					<?php echo esc_html(famefe_member_status_text($member)); ?>
					<?php if ($manage) : ?>
						– <a href="<?php echo esc_url(famefe_admin_url('famefe-members', ['action' => 'edit', 'id' => $member->id])); ?>"><?php esc_html_e('Membership and history', 'fair-member-fees'); ?></a>
					<?php endif; ?>
					<p class="description"><?php esc_html_e('The first name, last name and e-mail of this profile are also the details of the member.', 'fair-member-fees'); ?></p>
				<?php endif; ?>
			</td>
		</tr>
	</table>
	<?php
}
add_action('show_user_profile', 'famefe_user_profile_section');
add_action('edit_user_profile', 'famefe_user_profile_section');

/**
 * "Regular member since 28 September 2022" or "Membership ended on …".
 */
function famefe_member_status_text(object $member): string
{
	if ($member->status !== 'active') {
		/* translators: %s: date. */
		return sprintf(__('Membership ended on %s', 'fair-member-fees'), famefe_format_date($member->left_on));
	}
	/* translators: 1: membership type, 2: date. */
	return sprintf(__('%1$s member since %2$s', 'fair-member-fees'), famefe_member_types()[$member->member_type] ?? $member->member_type, famefe_format_date($member->member_since));
}

/**
 * Members of all linked accounts, loaded once for the Users list: [user_id => member].
 */
function famefe_members_by_user(): array
{
	static $map = null;
	if ($map === null) {
		$map = [];
		foreach (famefe_get_members(['status' => 'all']) as $member) {
			if ($member->user_id) {
				$map[intval($member->user_id)] = $member;
			}
		}
	}
	return $map;
}

/**
 * Member column in the Users list.
 */
function famefe_users_columns(array $columns): array
{
	if (famefe_can_manage()) {
		$columns['famefe_member'] = __('Member', 'fair-member-fees');
	}
	return $columns;
}
add_filter('manage_users_columns', 'famefe_users_columns');

/**
 * Content of the Member column.
 */
function famefe_users_column_content(string $output, string $column, int $user_id): string
{
	if ($column !== 'famefe_member') {
		return $output;
	}
	$member = famefe_members_by_user()[$user_id] ?? null;
	if (!$member) {
		return '—';
	}
	$label = $member->status === 'active'
		? (famefe_member_types()[$member->member_type] ?? $member->member_type)
		: __('Former member', 'fair-member-fees');
	return sprintf(
		'<a href="%1$s">%2$s</a>',
		esc_url(famefe_admin_url('famefe-members', ['action' => 'edit', 'id' => $member->id])),
		esc_html($label)
	);
}
add_filter('manage_users_custom_column', 'famefe_users_column_content', 10, 3);

/**
 * Warning on the "Delete users" screen when an account belongs to a member.
 *
 * @param WP_User $current_user The user deleting.
 * @param int[]   $user_ids     Accounts to delete.
 */
function famefe_delete_user_warning($current_user, $user_ids): void
{
	$names = [];
	foreach ((array) $user_ids as $user_id) {
		$member = famefe_get_member(intval($user_id));
		if ($member) {
			$names[] = famefe_member_name($member);
		}
	}
	if (!$names) {
		return;
	}
	printf(
		'<div class="notice notice-warning inline"><p>%s</p></div>',
		esc_html(sprintf(
			/* translators: %s: list of member names. */
			__('These accounts belong to members: %s. The members, their history, hours and payments stay in the register; only the link to the account is removed. To end a membership, use Member Fees → Members.', 'fair-member-fees'),
			implode(', ', $names)
		))
	);
}
add_action('delete_user_form', 'famefe_delete_user_warning', 10, 2);
