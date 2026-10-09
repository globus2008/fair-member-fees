<?php
/**
 * One-off import of volunteer hours from the old volunteers-hours plugin ({prefix}brigady_data)
 * into {prefix}famefe_hours. Not part of the plugin release.
 *
 * Run with WP-CLI (the plugin must be active):
 *   wp eval-file wp-content/plugins/fair-member-fees/tools/import-brigady-data.php [dry-run]
 *
 * - An entry of a user linked to a member gets that member_id, others keep only user_id
 *   (they count as hours of non-members). Create and link the members first.
 * - recorded_by = the user (the old form recorded only own hours), recorded_at = the date of the work, noon.
 * - Safe to run again: an entry with the same user, date, hours and description is skipped.
 *
 * @package fair-member-fees
 */

// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

if (!defined('ABSPATH') || !function_exists('famefe_table')) {
	echo "Run with WP-CLI eval-file while Fair Member Fees is active.\n";
	return;
}

global $wpdb;
$famefe_dry = in_array('dry-run', (array) ($args ?? []), true);
$famefe_old = $wpdb->prefix . 'brigady_data';
$famefe_new = famefe_table('hours');

if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $famefe_old)) !== $famefe_old) {
	echo "Table $famefe_old not found.\n";
	return;
}

$famefe_rows = $wpdb->get_results("SELECT * FROM $famefe_old ORDER BY ID");
$famefe_stats = ['imported' => 0, 'skipped' => 0, 'members' => 0, 'non_members' => 0, 'invalid' => 0, 'hours' => 0.0];

foreach ($famefe_rows as $famefe_row) {
	$famefe_user = intval($famefe_row->user_ID);
	$famefe_hours = round(floatval($famefe_row->pocet_hodin), 1);
	$famefe_date = famefe_valid_date($famefe_row->datum);
	$famefe_text = mb_substr(trim((string) $famefe_row->napln_brigady), 0, 255);
	if ($famefe_user <= 0 || $famefe_hours <= 0 || $famefe_date === '') {
		$famefe_stats['invalid']++;
		echo "Invalid entry ID {$famefe_row->ID} skipped.\n";
		continue;
	}
	$famefe_exists = $wpdb->get_var($wpdb->prepare(
		"SELECT id FROM $famefe_new WHERE user_id = %d AND work_date = %s AND hours = %f AND description = %s LIMIT 1",
		$famefe_user,
		$famefe_date,
		$famefe_hours,
		$famefe_text
	));
	if ($famefe_exists) {
		$famefe_stats['skipped']++;
		continue;
	}
	$famefe_member = famefe_get_member($famefe_user);
	$famefe_stats[$famefe_member ? 'members' : 'non_members']++;
	$famefe_stats['imported']++;
	$famefe_stats['hours'] += $famefe_hours;
	if ($famefe_dry) {
		continue;
	}
	$wpdb->insert($famefe_new, [
		'member_id' => $famefe_member ? intval($famefe_member->id) : null,
		'user_id' => $famefe_user,
		'work_date' => $famefe_date,
		'hours' => $famefe_hours,
		'description' => $famefe_text,
		'recorded_by' => $famefe_user,
		'recorded_at' => $famefe_date . ' 12:00:00',
	]);
}

printf(
	"%s: %d entries (%s h) imported – %d of members, %d of non-members; %d already there, %d invalid.\n",
	$famefe_dry ? 'Dry run' : 'Done',
	$famefe_stats['imported'],
	$famefe_stats['hours'],
	$famefe_stats['members'],
	$famefe_stats['non_members'],
	$famefe_stats['skipped'],
	$famefe_stats['invalid']
);
