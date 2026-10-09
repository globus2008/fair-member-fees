<?php
/**
 * Reading the plugin tables. Writes are in famefe-services.php.
 *
 * The plugin keeps its own tables, so the queries go straight to $wpdb; values that are not
 * table names always pass through $wpdb->prepare().
 *
 * @package fair-member-fees
 */

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- only the plugin's table names are interpolated.

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Member types and their labels.
 */
function famefe_member_types(): array
{
	return [
		'regular' => __('Regular', 'fair-member-fees'),
		'honorary' => __('Honorary', 'fair-member-fees'),
	];
}

/**
 * Labels of the change types in the member history.
 */
function famefe_change_types(): array
{
	return [
		'joined' => __('Joined', 'fair-member-fees'),
		'type_changed' => __('Membership type changed', 'fair-member-fees'),
		'left' => __('Membership ended', 'fair-member-fees'),
		'rejoined' => __('Rejoined', 'fair-member-fees'),
		'since_corrected' => __('"Member since" corrected', 'fair-member-fees'),
	];
}

/**
 * Labels of the payment methods.
 */
function famefe_payment_methods(): array
{
	return [
		'stripe' => __('Card (Stripe)', 'fair-member-fees'),
		'cash' => __('Cash', 'fair-member-fees'),
		'transfer' => __('Bank transfer', 'fair-member-fees'),
	];
}

/**
 * One member by its ID, or null.
 */
function famefe_get_member_by_id(int $id): ?object
{
	global $wpdb;
	if ($id <= 0) {
		return null;
	}
	$table = famefe_table('members');
	return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id)) ?: null;
}

/**
 * Public API: the member record linked to a WordPress account (also a former member), or null.
 */
function famefe_get_member(int $user_id): ?object
{
	global $wpdb;
	if ($user_id <= 0) {
		return null;
	}
	$key = 'famefe_member_user_' . $user_id;
	$member = wp_cache_get($key, 'famefe');
	if ($member === false) {
		$table = famefe_table('members');
		$member = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE user_id = %d", $user_id)) ?: null;
		wp_cache_set($key, $member, 'famefe');
	}
	return $member;
}

/**
 * Public API: 'regular' or 'honorary' for an active member, '' for everybody else.
 */
function famefe_get_member_type(int $user_id): string
{
	$member = famefe_get_member($user_id);
	return $member && $member->status === 'active' ? (string) $member->member_type : '';
}

/**
 * Members ordered by last and first name.
 *
 * @param array $args 'status' => 'active'|'left'|'all', 'types' => ['regular', ...], 'search' => text.
 */
function famefe_get_members(array $args = []): array
{
	global $wpdb;
	$table = famefe_table('members');
	$where = ['1=1'];
	$values = [];
	$status = $args['status'] ?? 'active';
	if ($status === 'active' || $status === 'left') {
		$where[] = 'status = %s';
		$values[] = $status;
	}
	$types = array_values(array_intersect((array) ($args['types'] ?? []), array_keys(famefe_member_types())));
	if ($types) {
		$where[] = 'member_type IN (' . implode(',', array_fill(0, count($types), '%s')) . ')';
		$values = array_merge($values, $types);
	}
	$search = trim((string) ($args['search'] ?? ''));
	if ($search !== '') {
		$like = '%' . $wpdb->esc_like($search) . '%';
		$where[] = '(first_name LIKE %s OR last_name LIKE %s OR email LIKE %s)';
		array_push($values, $like, $like, $like);
	}
	$sql = "SELECT * FROM $table WHERE " . implode(' AND ', $where) . ' ORDER BY last_name, first_name, id';
	// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- the placeholders are built above.
	return $values ? $wpdb->get_results($wpdb->prepare($sql, $values)) : $wpdb->get_results($sql);
}

/**
 * Number of members per status: ['active' => n, 'left' => n, 'all' => n].
 */
function famefe_count_members(): array
{
	global $wpdb;
	$table = famefe_table('members');
	$counts = ['active' => 0, 'left' => 0, 'all' => 0];
	foreach ($wpdb->get_results("SELECT status, COUNT(*) AS n FROM $table GROUP BY status") as $row) {
		$counts[$row->status] = intval($row->n);
		$counts['all'] += intval($row->n);
	}
	return $counts;
}

/**
 * Members who were members at some time of the period (joined before its end, not left before its start).
 * Their current type decides whether they pay.
 */
function famefe_members_in_period(string $start, string $end): array
{
	global $wpdb;
	$table = famefe_table('members');
	return $wpdb->get_results($wpdb->prepare(
		"SELECT * FROM $table WHERE member_since <= %s AND (left_on IS NULL OR left_on >= %s) ORDER BY last_name, first_name, id",
		$end,
		$start
	));
}

/**
 * Name shortened for data protection as in the old volunteers-hours plugin: names longer than
 * 8 characters keep only the first 4 and the last 4 characters ("Jana Nováková" → "Janaková").
 */
function famefe_mask_name(string $name): string
{
	$name = trim($name);
	if ($name === '') {
		return __('Unknown name', 'fair-member-fees');
	}
	return mb_strlen($name) > 8 ? mb_substr($name, 0, 4) . mb_substr($name, -4) : $name;
}

/**
 * Name of a person for display.
 *
 * @param string $mode full (Jane Smith), short (Jane S.), initials (J. S.), masked (see famefe_mask_name());
 *                     display = full here (people without an account have no display name).
 */
function famefe_person_name(string $first, string $last, string $mode = 'full'): string
{
	$first = trim($first);
	$last = trim($last);
	$initial = fn(string $name) => $name === '' ? '' : mb_strtoupper(mb_substr($name, 0, 1)) . '.';
	switch ($mode) {
		case 'masked':
			return famefe_mask_name($first . ' ' . $last);
		case 'short':
			$parts = [$first, $initial($last)];
			break;
		case 'initials':
			$parts = [$initial($first), $initial($last)];
			break;
		default:
			$parts = [$first, $last];
	}
	return trim(implode(' ', array_filter($parts, 'strlen')));
}

/**
 * Name of a member for display (see famefe_person_name()).
 */
function famefe_member_name(object $member, string $mode = 'full'): string
{
	// Display name and masked name come from the account, as in the old plugin.
	if (in_array($mode, ['display', 'masked'], true) && !empty($member->user_id) && get_userdata(intval($member->user_id))) {
		return famefe_user_name(intval($member->user_id), $mode);
	}
	return famefe_person_name((string) $member->first_name, (string) $member->last_name, $mode);
}

/**
 * Name of a WordPress user for display (first and last name of the profile, else the display name).
 */
function famefe_user_name(int $user_id, string $mode = 'full'): string
{
	$user = $user_id > 0 ? get_userdata($user_id) : false;
	if (!$user) {
		return $user_id > 0 ? __('Deleted user', 'fair-member-fees') : '';
	}
	if ($mode === 'display') {
		return (string) $user->display_name;
	}
	if ($mode === 'masked') {
		return famefe_mask_name((string) $user->display_name);
	}
	if ($user->first_name !== '' || $user->last_name !== '') {
		return famefe_person_name($user->first_name, $user->last_name, $mode);
	}
	$words = preg_split('/\s+/', trim($user->display_name));
	return famefe_person_name((string) array_shift($words), implode(' ', $words), $mode);
}

/**
 * First name, last name and e-mail of a user account ('' for missing values). Without first and last name
 * in the profile, the display name is split into them.
 */
function famefe_user_details(int $user_id): array
{
	$user = get_userdata($user_id);
	if (!$user) {
		return ['first_name' => '', 'last_name' => '', 'email' => ''];
	}
	$first = trim((string) $user->first_name);
	$last = trim((string) $user->last_name);
	if ($first === '' && $last === '') {
		$words = preg_split('/\s+/', trim((string) $user->display_name));
		$first = (string) array_shift($words);
		$last = implode(' ', $words);
	}
	return ['first_name' => $first, 'last_name' => $last, 'email' => (string) $user->user_email];
}

/**
 * History of a member, newest first, with the name of the person who recorded each change.
 */
function famefe_member_log(int $member_id): array
{
	global $wpdb;
	$table = famefe_table('member_log');
	return $wpdb->get_results($wpdb->prepare(
		"SELECT * FROM $table WHERE member_id = %d ORDER BY change_date DESC, id DESC",
		$member_id
	));
}

/**
 * The latest history entry of each member: [member_id => row].
 */
function famefe_last_changes(): array
{
	global $wpdb;
	$table = famefe_table('member_log');
	$rows = $wpdb->get_results(
		"SELECT l.* FROM $table l JOIN (SELECT member_id, MAX(id) AS id FROM $table GROUP BY member_id) x ON x.id = l.id"
	);
	$out = [];
	foreach ($rows as $row) {
		$out[intval($row->member_id)] = $row;
	}
	return $out;
}

/**
 * WHERE clause and values for the hours queries.
 *
 * @param array $args 'start', 'end' (Y-m-d), 'member_id', 'user_id' (entries of a member or of an account).
 */
function famefe_hours_where(array $args): array
{
	// Always one placeholder, so every query of the hours goes through $wpdb->prepare().
	$where = ['1 = %d'];
	$values = [1];
	if (!empty($args['start'])) {
		$where[] = 'h.work_date >= %s';
		$values[] = $args['start'];
	}
	if (!empty($args['end'])) {
		$where[] = 'h.work_date <= %s';
		$values[] = $args['end'];
	}
	if (!empty($args['member_id'])) {
		$where[] = 'h.member_id = %d';
		$values[] = intval($args['member_id']);
	}
	if (!empty($args['user_id'])) {
		// Own entries: recorded for the member linked to the account, or for the account itself.
		$member = famefe_get_member(intval($args['user_id']));
		$where[] = $member ? '(h.member_id = %d OR h.user_id = %d)' : 'h.user_id = %d';
		if ($member) {
			$values[] = intval($member->id);
		}
		$values[] = intval($args['user_id']);
	}
	return [implode(' AND ', $where), $values];
}

/**
 * Hours entries, newest first, with first_name/last_name of the member (empty for non-members).
 *
 * @param array $args See famefe_hours_where(), plus 'limit' and 'offset'.
 */
function famefe_get_hours(array $args = []): array
{
	global $wpdb;
	[$where, $values] = famefe_hours_where($args);
	$hours = famefe_table('hours');
	$members = famefe_table('members');
	$values[] = max(1, intval($args['limit'] ?? 100));
	$values[] = max(0, intval($args['offset'] ?? 0));
	// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- $where holds placeholders only; values are in one array.
	return $wpdb->get_results($wpdb->prepare(
		"SELECT h.*, m.first_name, m.last_name FROM $hours h LEFT JOIN $members m ON m.id = h.member_id
		WHERE $where ORDER BY h.work_date DESC, h.id DESC LIMIT %d OFFSET %d",
		$values
	));
	// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
}

/**
 * Number of entries and sum of hours: ['count' => n, 'hours' => float].
 */
function famefe_hours_summary(array $args = []): array
{
	global $wpdb;
	[$where, $values] = famefe_hours_where($args);
	$hours = famefe_table('hours');
	// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- $where holds the placeholders (famefe_hours_where()).
	$row = $wpdb->get_row($wpdb->prepare("SELECT COUNT(*) AS n, COALESCE(SUM(h.hours), 0) AS total FROM $hours h WHERE $where", $values));
	return ['count' => intval($row->n ?? 0), 'hours' => floatval($row->total ?? 0)];
}

/**
 * One hours entry, or null.
 */
function famefe_get_hours_entry(int $id): ?object
{
	global $wpdb;
	$table = famefe_table('hours');
	return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id)) ?: null;
}

/**
 * Display name of an hours entry: the member, else the account that recorded own hours.
 */
function famefe_hours_name(object $entry, string $mode): string
{
	if (in_array($mode, ['display', 'masked'], true) && !empty($entry->user_id) && get_userdata(intval($entry->user_id))) {
		return famefe_user_name(intval($entry->user_id), $mode);
	}
	if (!empty($entry->member_id) && ($entry->first_name ?? '') . ($entry->last_name ?? '') !== '') {
		return famefe_person_name((string) $entry->first_name, (string) $entry->last_name, $mode);
	}
	return famefe_user_name(intval($entry->user_id), $mode);
}

/**
 * Sums of hours in a period: ['members' => [member_id => hours], 'others' => hours of non-members].
 */
function famefe_hours_by_member(string $start, string $end): array
{
	global $wpdb;
	$table = famefe_table('hours');
	$rows = $wpdb->get_results($wpdb->prepare(
		"SELECT COALESCE(member_id, 0) AS member_id, SUM(hours) AS total FROM $table
		WHERE work_date >= %s AND work_date <= %s GROUP BY COALESCE(member_id, 0)",
		$start,
		$end
	));
	$out = ['members' => [], 'others' => 0.0];
	foreach ($rows as $row) {
		if (intval($row->member_id) === 0) {
			$out['others'] += floatval($row->total);
		} else {
			$out['members'][intval($row->member_id)] = floatval($row->total);
		}
	}
	return $out;
}

/**
 * Payment of a member for exactly this period, or null.
 */
function famefe_get_period_payment(int $member_id, string $start, string $end): ?object
{
	global $wpdb;
	$table = famefe_table('payments');
	return $wpdb->get_row($wpdb->prepare(
		"SELECT * FROM $table WHERE member_id = %d AND period_start = %s AND period_end = %s ORDER BY paid_at DESC LIMIT 1",
		$member_id,
		$start,
		$end
	)) ?: null;
}

/**
 * Payments, newest first, with the member's names.
 *
 * @param array $args 'member_id', 'start', 'end' (paid between), 'limit', 'offset'.
 */
function famefe_get_payments(array $args = [], bool $count_only = false): array|int
{
	global $wpdb;
	$payments = famefe_table('payments');
	$members = famefe_table('members');
	$where = ['1=1'];
	$values = [];
	if (!empty($args['member_id'])) {
		$where[] = 'p.member_id = %d';
		$values[] = intval($args['member_id']);
	}
	if (!empty($args['start'])) {
		$where[] = 'p.paid_at >= %s';
		$values[] = $args['start'] . ' 00:00:00';
	}
	if (!empty($args['end'])) {
		$where[] = 'p.paid_at <= %s';
		$values[] = $args['end'] . ' 23:59:59';
	}
	$where = implode(' AND ', $where);
	// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- $where holds placeholders only; values are in one array.
	if ($count_only) {
		$sql = "SELECT COUNT(*) FROM $payments p WHERE $where";
		return intval($values ? $wpdb->get_var($wpdb->prepare($sql, $values)) : $wpdb->get_var($sql));
	}
	$values[] = max(1, intval($args['limit'] ?? 50));
	$values[] = max(0, intval($args['offset'] ?? 0));
	return $wpdb->get_results($wpdb->prepare(
		"SELECT p.*, m.first_name, m.last_name FROM $payments p LEFT JOIN $members m ON m.id = p.member_id
		WHERE $where ORDER BY p.paid_at DESC, p.id DESC LIMIT %d OFFSET %d",
		$values
	));
	// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
}

/**
 * Everything the Membership fees block and the payment need for one period.
 *
 * @return array 'members' => [id => member], 'calc' => famefe_calculate_fees() result,
 *               'payments' => [member_id => payment], 'start', 'end'.
 */
function famefe_period_fees(string $start, string $end, float $base_fee, float $discount_share = 0, float $discount_rate = 0): array
{
	$members = [];
	$rows = [];
	$hours = famefe_hours_by_member($start, $end);
	foreach (famefe_members_in_period($start, $end) as $member) {
		$id = intval($member->id);
		$members[$id] = $member;
		$rows[] = ['member_id' => $id, 'member_type' => $member->member_type, 'hours' => $hours['members'][$id] ?? 0];
	}
	// Hours of former members recorded in the period count like hours of non-members.
	$others = $hours['others'];
	foreach ($hours['members'] as $id => $sum) {
		if (!isset($members[$id])) {
			$others += $sum;
		}
	}
	$calc = famefe_calculate_fees($rows, $base_fee, [
		'discount_share' => $discount_share,
		'discount_rate' => $discount_rate,
		'decimals' => intval(famefe_settings('decimals')),
		'other_hours' => $others,
	]);
	$payments = [];
	foreach (array_keys($members) as $id) {
		$payment = famefe_get_period_payment($id, $start, $end);
		if ($payment) {
			$payments[$id] = $payment;
		}
	}
	return ['members' => $members, 'calc' => $calc, 'payments' => $payments, 'start' => $start, 'end' => $end];
}
