<?php
/**
 * Uninstall: removes the tables, options and the capability only when the setting
 * "Delete data" is on. Otherwise all data stay for a later reinstall.
 *
 * @package fair-member-fees
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
	exit;
}

$famefe_settings = (array) get_option('famefe_settings', []);
if (empty($famefe_settings['delete_data'])) {
	return;
}

global $wpdb;
foreach (['members', 'member_log', 'hours', 'payments'] as $famefe_table) {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- tables of the plugin.
	$wpdb->query($wpdb->prepare('DROP TABLE IF EXISTS %i', $wpdb->prefix . 'famefe_' . $famefe_table));
}
delete_option('famefe_settings');
delete_option('famefe_db_version');
foreach (wp_roles()->role_objects as $famefe_role) {
	$famefe_role->remove_cap('famefe_manage');
}
