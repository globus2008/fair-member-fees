<?php
/**
 * Database tables and the capability of the plugin.
 *
 * @package fair-member-fees
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Capability for managing members, hours of other people and manual payments.
 * Given to administrators and editors on install.
 */
const FAMEFE_CAPABILITY = 'famefe_manage';

/**
 * Full name of a plugin table, e.g. famefe_table('members') = wp_famefe_members.
 */
function famefe_table(string $name): string
{
	global $wpdb;
	return $wpdb->prefix . 'famefe_' . $name;
}

/**
 * Create or update the tables (dbDelta) and give the capability to administrators and editors.
 * Runs on activation and whenever FAMEFE_DB_VERSION changes.
 */
function famefe_install(): void
{
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$charset = $wpdb->get_charset_collate();

	// dbDelta needs two spaces after PRIMARY KEY and one field per line.
	$members = famefe_table('members');
	$log = famefe_table('member_log');
	$hours = famefe_table('hours');
	$payments = famefe_table('payments');

	dbDelta("CREATE TABLE $members (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		user_id bigint(20) unsigned DEFAULT NULL,
		first_name varchar(100) NOT NULL DEFAULT '',
		last_name varchar(100) NOT NULL DEFAULT '',
		email varchar(190) NOT NULL DEFAULT '',
		member_type varchar(20) NOT NULL DEFAULT 'regular',
		status varchar(20) NOT NULL DEFAULT 'active',
		member_since date NOT NULL,
		left_on date DEFAULT NULL,
		note text NOT NULL,
		created_at datetime NOT NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY user_id (user_id),
		KEY status (status)
	) $charset;");

	dbDelta("CREATE TABLE $log (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		member_id bigint(20) unsigned NOT NULL,
		change_type varchar(20) NOT NULL,
		old_value varchar(50) NOT NULL DEFAULT '',
		new_value varchar(50) NOT NULL DEFAULT '',
		change_date date NOT NULL,
		note varchar(255) NOT NULL DEFAULT '',
		recorded_by bigint(20) unsigned NOT NULL DEFAULT 0,
		recorded_at datetime NOT NULL,
		PRIMARY KEY  (id),
		KEY member_id (member_id)
	) $charset;");

	dbDelta("CREATE TABLE $hours (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		member_id bigint(20) unsigned DEFAULT NULL,
		user_id bigint(20) unsigned DEFAULT NULL,
		work_date date NOT NULL,
		hours decimal(4,1) NOT NULL,
		description varchar(255) NOT NULL DEFAULT '',
		recorded_by bigint(20) unsigned NOT NULL DEFAULT 0,
		recorded_at datetime NOT NULL,
		PRIMARY KEY  (id),
		KEY member_id (member_id),
		KEY user_id (user_id),
		KEY work_date (work_date)
	) $charset;");

	dbDelta("CREATE TABLE $payments (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		member_id bigint(20) unsigned NOT NULL,
		period_start date NOT NULL,
		period_end date NOT NULL,
		amount decimal(12,2) NOT NULL,
		currency char(3) NOT NULL,
		method varchar(20) NOT NULL,
		paid_at datetime NOT NULL,
		stripe_session_id varchar(255) DEFAULT NULL,
		note varchar(255) NOT NULL DEFAULT '',
		recorded_by bigint(20) unsigned NOT NULL DEFAULT 0,
		recorded_at datetime NOT NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY stripe_session_id (stripe_session_id(191)),
		KEY member_period (member_id,period_start,period_end)
	) $charset;");

	foreach (['administrator', 'editor'] as $role_name) {
		$role = get_role($role_name);
		if ($role && !$role->has_cap(FAMEFE_CAPABILITY)) {
			$role->add_cap(FAMEFE_CAPABILITY);
		}
	}
	update_option('famefe_db_version', FAMEFE_DB_VERSION, false);
}

/**
 * Run the installer after an update that changed the tables (activation does not run on updates).
 */
function famefe_maybe_upgrade(): void
{
	if (get_option('famefe_db_version') !== FAMEFE_DB_VERSION) {
		famefe_install();
	}
}

/**
 * A deleted WordPress account keeps its member record and hours; only the link to the account goes.
 */
function famefe_forget_deleted_user(int $user_id): void
{
	global $wpdb;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->update(famefe_table('members'), ['user_id' => null], ['user_id' => $user_id]);
	wp_cache_delete('famefe_member_user_' . $user_id, 'famefe');
}
add_action('deleted_user', 'famefe_forget_deleted_user');
