<?php
/**
 * Services: every change of the data, shared by the admin pages, the blocks and Stripe.
 * They never read $_POST, print or redirect. They check permissions and return
 * famefe_ok($code, $extra) or a WP_Error whose code is a notice code of famefe-notices.php.
 *
 * @package fair-member-fees
 */

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- only the plugin's table names are interpolated.

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Successful result of a service.
 */
function famefe_ok(string $code, array $extra = []): array
{
	return array_merge(['code' => $code], $extra);
}

/**
 * Failed result of a service.
 */
function famefe_error(string $code): WP_Error
{
	return new WP_Error($code, famefe_notice_text($code));
}

/**
 * Whether the current user manages members, hours of others and payments.
 */
function famefe_can_manage(): bool
{
	return current_user_can(FAMEFE_CAPABILITY);
}

/**
 * Current local time for the recorded_at columns.
 */
function famefe_now(): string
{
	return current_time('mysql');
}

/**
 * Add a history entry of a member.
 */
function famefe_log_change(int $member_id, string $type, string $date, string $old = '', string $new = '', string $note = ''): void
{
	global $wpdb;
	$wpdb->insert(famefe_table('member_log'), [
		'member_id' => $member_id,
		'change_type' => $type,
		'old_value' => $old,
		'new_value' => $new,
		'change_date' => $date,
		'note' => mb_substr($note, 0, 255),
		'recorded_by' => get_current_user_id(),
		'recorded_at' => famefe_now(),
	]);
}

/**
 * Forget the cached member of an account (famefe_get_member()).
 */
function famefe_flush_member_cache(?object $member): void
{
	if ($member && !empty($member->user_id)) {
		wp_cache_delete('famefe_member_user_' . intval($member->user_id), 'famefe');
	}
}

/**
 * Create a member (id 0) or change the details of one. Type and status of an existing member
 * change only through famefe_service_change_member(), so that the history records them.
 *
 * @param array $data first_name, last_name, email, user_id, note; for a new member also member_type and member_since.
 */
function famefe_service_save_member(array $data, int $id = 0): array|WP_Error
{
	global $wpdb;
	if (!famefe_can_manage()) {
		return famefe_error('forbidden');
	}
	$old = $id > 0 ? famefe_get_member_by_id($id) : null;
	if ($id > 0 && !$old) {
		return famefe_error('not_found');
	}
	$row = [
		'first_name' => mb_substr(sanitize_text_field((string) ($data['first_name'] ?? '')), 0, 100),
		'last_name' => mb_substr(sanitize_text_field((string) ($data['last_name'] ?? '')), 0, 100),
		'email' => sanitize_email((string) ($data['email'] ?? '')),
		'note' => sanitize_textarea_field((string) ($data['note'] ?? '')),
	];
	$user_id = intval($data['user_id'] ?? 0);
	if ($user_id > 0) {
		if (!get_userdata($user_id)) {
			return famefe_error('not_found');
		}
		$other = famefe_get_member($user_id);
		if ($other && intval($other->id) !== $id) {
			return famefe_error('user_taken');
		}
		$synced = famefe_sync_member_details_with_user($row, $user_id, !$old || intval($old->user_id) !== $user_id);
		if (is_wp_error($synced)) {
			return $synced;
		}
		$row = $synced;
	}
	if ($row['first_name'] === '' && $row['last_name'] === '') {
		return famefe_error('invalid_name');
	}
	$row['user_id'] = $user_id > 0 ? $user_id : null;

	if ($old) {
		// A wrong "member since" can be corrected; the correction is kept in the history.
		$since = famefe_valid_date($data['member_since'] ?? '');
		$correct_since = $since !== '' && $since !== $old->member_since;
		if ($correct_since && $old->left_on && $since > $old->left_on) {
			return famefe_error('invalid_since');
		}
		if ($correct_since) {
			$row['member_since'] = $since;
		}
		$wpdb->update(famefe_table('members'), $row, ['id' => $id]);
		if ($correct_since) {
			famefe_correct_member_since_log($id, $old->member_since, $since);
		}
		famefe_flush_member_cache($old);
		famefe_flush_member_cache((object) $row);
		return famefe_ok('member_saved', ['id' => $id]);
	}

	$type = (string) ($data['member_type'] ?? 'regular');
	if (!isset(famefe_member_types()[$type])) {
		return famefe_error('invalid_type');
	}
	$since = famefe_valid_date($data['member_since'] ?? '');
	if ($since === '') {
		return famefe_error('invalid_date');
	}
	$row += ['member_type' => $type, 'status' => 'active', 'member_since' => $since, 'created_at' => famefe_now()];
	if (!$wpdb->insert(famefe_table('members'), $row)) {
		return famefe_error('db_error');
	}
	$id = intval($wpdb->insert_id);
	famefe_log_change($id, 'joined', $since, '', $type);
	famefe_flush_member_cache((object) $row);
	return famefe_ok('member_added', ['id' => $id]);
}

/**
 * Name and e-mail of a member linked to a user account: the account is the source of truth.
 *
 * - A newly linked account wins: its non-empty details replace the form (the form only fills what the profile lacks).
 * - For an account that was already linked, the form changes the profile too – but only when the current
 *   user may edit that account (an editor must never change an administrator's e-mail and take the account over).
 *   Otherwise the details stay as in the profile.
 *
 * @param array $row       first_name, last_name, email from the form.
 * @param bool  $new_link  The account is linked now (new member or another account).
 * @return array|WP_Error The row with the synchronised details.
 */
function famefe_sync_member_details_with_user(array $row, int $user_id, bool $new_link): array|WP_Error
{
	$user = famefe_user_details($user_id);
	$can_edit = current_user_can('edit_user', $user_id);
	if ($new_link || !$can_edit) {
		foreach (['first_name', 'last_name', 'email'] as $key) {
			if ($user[$key] !== '' || !$can_edit) {
				$row[$key] = $user[$key] !== '' ? $user[$key] : $row[$key];
			}
		}
		if (!$can_edit) {
			return $row;
		}
	}
	$update = ['ID' => $user_id];
	foreach (['first_name', 'last_name'] as $key) {
		if ($row[$key] !== $user[$key]) {
			$update[$key] = $row[$key];
		}
	}
	if ($row['email'] !== '' && strcasecmp($row['email'], $user['email']) !== 0) {
		if (email_exists($row['email'])) {
			return famefe_error('email_taken');
		}
		$update['user_email'] = $row['email'];
	}
	if ($row['email'] === '') {
		// An account always keeps its e-mail.
		$row['email'] = $user['email'];
	}
	if (count($update) > 1) {
		$result = wp_update_user($update);
		if (is_wp_error($result)) {
			return famefe_error($result->get_error_code() === 'existing_user_email' ? 'email_taken' : 'db_error');
		}
	}
	return $row;
}

/**
 * Create a WordPress account (default role of the site) and the member linked to it.
 * Needs the capability create_users in addition to famefe_manage.
 *
 * @param array $data      first_name, last_name, email (required), member_type, member_since, note.
 * @param bool  $send_link Send the new user the e-mail with the link for setting the password.
 */
function famefe_service_add_member_with_account(array $data, bool $send_link): array|WP_Error
{
	if (!famefe_can_manage() || !current_user_can('create_users')) {
		return famefe_error('forbidden');
	}
	$email = sanitize_email((string) ($data['email'] ?? ''));
	if (!is_email($email)) {
		return famefe_error('invalid_email');
	}
	if (email_exists($email)) {
		return famefe_error('email_taken');
	}
	$first = sanitize_text_field((string) ($data['first_name'] ?? ''));
	$last = sanitize_text_field((string) ($data['last_name'] ?? ''));
	if ($first === '' && $last === '') {
		return famefe_error('invalid_name');
	}
	if (!isset(famefe_member_types()[(string) ($data['member_type'] ?? '')]) || famefe_valid_date($data['member_since'] ?? '') === '') {
		return famefe_error(famefe_valid_date($data['member_since'] ?? '') === '' ? 'invalid_date' : 'invalid_type');
	}
	// Login name from the e-mail address, made unique.
	$base = sanitize_user(strstr($email, '@', true), true) ?: 'member';
	$login = $base;
	for ($i = 2; username_exists($login); $i++) {
		$login = $base . $i;
	}
	$user_id = wp_insert_user([
		'user_login' => $login,
		'user_email' => $email,
		'user_pass' => wp_generate_password(24),
		'first_name' => $first,
		'last_name' => $last,
		'display_name' => trim($first . ' ' . $last),
		'role' => get_option('default_role', 'subscriber'),
	]);
	if (is_wp_error($user_id)) {
		return famefe_error('db_error');
	}
	$result = famefe_service_save_member(array_merge($data, ['user_id' => $user_id]), 0);
	if (is_wp_error($result)) {
		return $result;
	}
	if ($send_link) {
		wp_send_new_user_notifications($user_id, 'user');
	}
	$result['code'] = $send_link ? 'member_account_sent' : 'member_account_added';
	return $result;
}

/**
 * After a correction of "member since": move the history entry that started the membership
 * (the latest joined/rejoined entry) to the new date and record the correction itself.
 */
function famefe_correct_member_since_log(int $member_id, string $old_since, string $new_since): void
{
	global $wpdb;
	$log = famefe_table('member_log');
	$entry = $wpdb->get_var($wpdb->prepare(
		"SELECT id FROM $log WHERE member_id = %d AND change_type IN ('joined', 'rejoined') ORDER BY change_date DESC, id DESC LIMIT 1",
		$member_id
	));
	if ($entry) {
		$wpdb->update($log, ['change_date' => $new_since], ['id' => intval($entry)]);
	}
	famefe_log_change($member_id, 'since_corrected', famefe_today(), $old_since, $new_since);
}

/**
 * Change the membership: 'type_changed' (needs $new_type), 'left' or 'rejoined', valid from $date.
 * Every change is written to the history with the person who recorded it and when.
 */
function famefe_service_change_member(int $id, string $change, string $date, string $new_type = '', string $note = ''): array|WP_Error
{
	global $wpdb;
	if (!famefe_can_manage()) {
		return famefe_error('forbidden');
	}
	$member = famefe_get_member_by_id($id);
	if (!$member) {
		return famefe_error('not_found');
	}
	$date = famefe_valid_date($date);
	if ($date === '') {
		return famefe_error('invalid_date');
	}
	$note = sanitize_text_field($note);
	$table = famefe_table('members');

	switch ($change) {
		case 'type_changed':
			if (!isset(famefe_member_types()[$new_type]) || $new_type === $member->member_type || $member->status !== 'active') {
				return famefe_error('invalid_change');
			}
			$wpdb->update($table, ['member_type' => $new_type], ['id' => $id]);
			famefe_log_change($id, 'type_changed', $date, $member->member_type, $new_type, $note);
			break;
		case 'left':
			if ($member->status !== 'active' || $date < $member->member_since) {
				return famefe_error('invalid_change');
			}
			$wpdb->update($table, ['status' => 'left', 'left_on' => $date], ['id' => $id]);
			famefe_log_change($id, 'left', $date, 'active', 'left', $note);
			break;
		case 'rejoined':
			if ($member->status !== 'left' || $date < (string) $member->left_on) {
				return famefe_error('invalid_change');
			}
			// A new membership starts; the earlier one stays in the history.
			$wpdb->update($table, ['status' => 'active', 'left_on' => null, 'member_since' => $date], ['id' => $id]);
			famefe_log_change($id, 'rejoined', $date, 'left', 'active', $note);
			break;
		default:
			return famefe_error('invalid_change');
	}
	famefe_flush_member_cache($member);
	return famefe_ok('member_changed', ['id' => $id]);
}

/**
 * Delete a member entered by mistake. Members with hours or payments cannot be deleted
 * (end the membership instead, the history must stay).
 */
function famefe_service_delete_member(int $id): array|WP_Error
{
	global $wpdb;
	if (!famefe_can_manage()) {
		return famefe_error('forbidden');
	}
	$member = famefe_get_member_by_id($id);
	if (!$member) {
		return famefe_error('not_found');
	}
	$hours = famefe_table('hours');
	$payments = famefe_table('payments');
	$used = intval($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $hours WHERE member_id = %d", $id)))
		+ intval($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $payments WHERE member_id = %d", $id)));
	if ($used > 0) {
		return famefe_error('has_records');
	}
	$wpdb->delete(famefe_table('member_log'), ['member_id' => $id]);
	$wpdb->delete(famefe_table('members'), ['id' => $id]);
	famefe_flush_member_cache($member);
	return famefe_ok('member_deleted');
}

/**
 * Check and clean the fields of an hours entry.
 *
 * @param array $limits 'max_hours', 'days_back' (oldest allowed date; null = any), 'require_description'.
 */
function famefe_clean_hours(array $data, array $limits): array|WP_Error
{
	$hours = round(floatval(str_replace(',', '.', (string) ($data['hours'] ?? 0))), 1);
	$max = floatval($limits['max_hours'] ?? 24);
	if ($hours <= 0 || $hours > $max || $hours > 99.9) {
		return famefe_error('invalid_hours');
	}
	$date = famefe_valid_date($data['work_date'] ?? '');
	if ($date === '' || $date > famefe_today()) {
		return famefe_error('invalid_date');
	}
	if (isset($limits['days_back'])) {
		if ($date < famefe_days_ago(intval($limits['days_back']))) {
			return famefe_error('date_out_of_range');
		}
	}
	$description = mb_substr(sanitize_text_field((string) ($data['description'] ?? '')), 0, 255);
	if ($description === '' && !empty($limits['require_description'])) {
		return famefe_error('description_required');
	}
	return ['work_date' => $date, 'hours' => $hours, 'description' => $description];
}

/**
 * Record volunteer hours.
 * Managers may record them for any member ($data['member_id']). Everybody else records only own
 * hours: the logged-in member, or a logged-in non-member when the settings allow it.
 *
 * @param array $data   member_id (managers only), work_date, hours, description.
 * @param array $limits See famefe_clean_hours().
 */
function famefe_service_add_hours(array $data, array $limits = []): array|WP_Error
{
	global $wpdb;
	$user_id = get_current_user_id();
	if ($user_id <= 0) {
		return famefe_error('login_required');
	}
	$member_id = intval($data['member_id'] ?? 0);
	$own = famefe_get_member($user_id);
	$row = ['member_id' => null, 'user_id' => null];
	if ($member_id > 0 && (!$own || intval($own->id) !== $member_id)) {
		// Hours of somebody else.
		if (!famefe_can_manage()) {
			return famefe_error('forbidden');
		}
		$member = famefe_get_member_by_id($member_id);
		if (!$member) {
			return famefe_error('not_found');
		}
		$row['member_id'] = $member_id;
		$row['user_id'] = $member->user_id ? intval($member->user_id) : null;
	} elseif ($own && $own->status === 'active') {
		$row['member_id'] = intval($own->id);
		$row['user_id'] = $user_id;
	} elseif (famefe_settings('hours_non_members') || famefe_can_manage()) {
		// Own hours of a non-member (allowed by the settings, or an administrator/editor); they count only in the totals.
		$row['user_id'] = $user_id;
	} else {
		return famefe_error('not_member');
	}
	$clean = famefe_clean_hours($data, $limits);
	if (is_wp_error($clean)) {
		return $clean;
	}
	$row += $clean + ['recorded_by' => $user_id, 'recorded_at' => famefe_now()];
	if (!$wpdb->insert(famefe_table('hours'), $row)) {
		return famefe_error('db_error');
	}
	return famefe_ok('hours_added', ['id' => intval($wpdb->insert_id)]);
}

/**
 * Change an hours entry (managers).
 */
function famefe_service_update_hours(int $id, array $data): array|WP_Error
{
	global $wpdb;
	if (!famefe_can_manage()) {
		return famefe_error('forbidden');
	}
	$entry = famefe_get_hours_entry($id);
	if (!$entry) {
		return famefe_error('not_found');
	}
	$clean = famefe_clean_hours($data, []);
	if (is_wp_error($clean)) {
		return $clean;
	}
	$member_id = intval($data['member_id'] ?? $entry->member_id);
	if ($member_id > 0 && $member_id !== intval($entry->member_id)) {
		$member = famefe_get_member_by_id($member_id);
		if (!$member) {
			return famefe_error('not_found');
		}
		$clean['member_id'] = $member_id;
		$clean['user_id'] = $member->user_id ? intval($member->user_id) : null;
	}
	$wpdb->update(famefe_table('hours'), $clean, ['id' => $id]);
	return famefe_ok('hours_saved', ['id' => $id]);
}

/**
 * Delete an hours entry (managers).
 */
function famefe_service_delete_hours(int $id): array|WP_Error
{
	global $wpdb;
	if (!famefe_can_manage()) {
		return famefe_error('forbidden');
	}
	if (!famefe_get_hours_entry($id)) {
		return famefe_error('not_found');
	}
	$wpdb->delete(famefe_table('hours'), ['id' => $id]);
	return famefe_ok('hours_deleted');
}

/**
 * Whether the current user may delete an hours entry in the Volunteer hours list block.
 * - Members: only entries they recorded themselves for themselves, at most member_days after recording.
 * - Administrators and editors: any entry, at most manager_days after recording.
 * The time limit counts from the day the entry was recorded (0 = only on that day).
 *
 * @param array $limits 'member_days', 'manager_days'.
 * @return string '' when allowed, otherwise the notice code why not.
 */
function famefe_hours_delete_denied(object $entry, array $limits): string
{
	$user_id = get_current_user_id();
	if ($user_id <= 0) {
		return 'login_required';
	}
	if (famefe_can_manage()) {
		$days = intval($limits['manager_days'] ?? 7);
	} else {
		$own = famefe_get_member($user_id);
		$for_me = intval($entry->user_id) === $user_id || ($own && intval($entry->member_id) === intval($own->id));
		if (!$for_me || intval($entry->recorded_by) !== $user_id) {
			return 'forbidden';
		}
		$days = intval($limits['member_days'] ?? 7);
	}
	return substr((string) $entry->recorded_at, 0, 10) < famefe_days_ago(max(0, $days)) ? 'delete_too_late' : '';
}

/**
 * Delete an hours entry from the Volunteer hours list block (see famefe_hours_delete_denied()).
 */
function famefe_service_delete_hours_entry(int $id, array $limits): array|WP_Error
{
	global $wpdb;
	$entry = famefe_get_hours_entry($id);
	if (!$entry) {
		return famefe_error('not_found');
	}
	$denied = famefe_hours_delete_denied($entry, $limits);
	if ($denied !== '') {
		return famefe_error($denied);
	}
	$wpdb->delete(famefe_table('hours'), ['id' => $id]);
	return famefe_ok('hours_deleted');
}

/**
 * Write a payment without permission checks (callers check them). A Stripe session is stored
 * only once, so a repeated return from Stripe or a repeated webhook changes nothing.
 *
 * @param array $data member_id, period_start, period_end, amount, currency, method, paid_at, stripe_session_id, note.
 */
function famefe_store_payment(array $data): array|WP_Error
{
	global $wpdb;
	$table = famefe_table('payments');
	$session = (string) ($data['stripe_session_id'] ?? '');
	if ($session !== '') {
		$existing = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE stripe_session_id = %s", $session));
		if ($existing) {
			return famefe_ok('payment_recorded', ['id' => intval($existing), 'existing' => true]);
		}
	}
	$row = [
		'member_id' => intval($data['member_id']),
		'period_start' => $data['period_start'],
		'period_end' => $data['period_end'],
		'amount' => round(floatval($data['amount']), 2),
		'currency' => strtoupper((string) $data['currency']),
		'method' => $data['method'],
		'paid_at' => $data['paid_at'] ?? famefe_now(),
		'stripe_session_id' => $session !== '' ? $session : null,
		'note' => mb_substr((string) ($data['note'] ?? ''), 0, 255),
		'recorded_by' => get_current_user_id(),
		'recorded_at' => famefe_now(),
	];
	if (!$wpdb->insert($table, $row)) {
		// Two requests with the same session at once: the unique key let only one in.
		$existing = $session !== '' ? $wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE stripe_session_id = %s", $session)) : 0;
		return $existing ? famefe_ok('payment_recorded', ['id' => intval($existing), 'existing' => true]) : famefe_error('db_error');
	}
	$id = intval($wpdb->insert_id);
	/**
	 * A membership fee payment was recorded.
	 *
	 * @param int   $id  Payment ID.
	 * @param array $row The stored payment.
	 */
	do_action('famefe_payment_recorded', $id, $row);
	return famefe_ok('payment_recorded', ['id' => $id]);
}

/**
 * Record a payment by hand (cash or bank transfer); administrators and editors only.
 *
 * @param array $data member_id, period_start, period_end, amount, method, paid_on (Y-m-d), note.
 */
function famefe_service_record_payment(array $data): array|WP_Error
{
	if (!famefe_can_manage()) {
		return famefe_error('forbidden');
	}
	$member = famefe_get_member_by_id(intval($data['member_id'] ?? 0));
	if (!$member) {
		return famefe_error('not_found');
	}
	$start = famefe_valid_date($data['period_start'] ?? '');
	$end = famefe_valid_date($data['period_end'] ?? '');
	$paid_on = famefe_valid_date($data['paid_on'] ?? '');
	if ($start === '' || $end === '' || $paid_on === '' || $start > $end) {
		return famefe_error('invalid_date');
	}
	$amount = floatval(str_replace(',', '.', (string) ($data['amount'] ?? '')));
	if ($amount < 0) {
		return famefe_error('invalid_amount');
	}
	$method = (string) ($data['method'] ?? '');
	if (!in_array($method, ['cash', 'transfer'], true)) {
		return famefe_error('invalid_method');
	}
	if (famefe_get_period_payment(intval($member->id), $start, $end)) {
		return famefe_error('already_paid');
	}
	return famefe_store_payment([
		'member_id' => intval($member->id),
		'period_start' => $start,
		'period_end' => $end,
		'amount' => $amount,
		'currency' => famefe_settings('currency'),
		'method' => $method,
		'paid_at' => $paid_on . ' 12:00:00',
		'note' => sanitize_text_field((string) ($data['note'] ?? '')),
	]);
}

/**
 * Delete a payment (managers).
 */
function famefe_service_delete_payment(int $id): array|WP_Error
{
	global $wpdb;
	if (!famefe_can_manage()) {
		return famefe_error('forbidden');
	}
	if (!$wpdb->delete(famefe_table('payments'), ['id' => $id])) {
		return famefe_error('not_found');
	}
	return famefe_ok('payment_deleted');
}
