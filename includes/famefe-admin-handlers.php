<?php
/**
 * Handlers of the admin forms (admin-post.php): nonce, call the service, redirect with a notice.
 *
 * @package fair-member-fees
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Verify the nonce of an admin form; stops with the "expired" notice when it is not valid.
 */
function famefe_admin_verify(string $action, string $back): void
{
	$nonce = isset($_REQUEST['_wpnonce']) ? sanitize_text_field(wp_unslash($_REQUEST['_wpnonce'])) : '';
	if (!wp_verify_nonce($nonce, $action)) {
		famefe_redirect_with_result($back, famefe_error('expired'));
	}
}

/**
 * A text field of the posted form (call only after famefe_admin_verify()).
 */
function famefe_post_text(string $key): string
{
	// phpcs:ignore WordPress.Security.NonceVerification -- verified by famefe_admin_verify().
	return isset($_REQUEST[$key]) ? sanitize_text_field(wp_unslash($_REQUEST[$key])) : '';
}

/**
 * Save the details of a member or add a new one.
 */
function famefe_handle_save_member(): void
{
	$id = absint(famefe_post_text('id'));
	$back = famefe_admin_url('famefe-members', $id ? ['action' => 'edit', 'id' => $id] : ['action' => 'new']);
	famefe_admin_verify('famefe_save_member', $back);
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified above.
	$note = isset($_POST['note']) ? sanitize_textarea_field(wp_unslash($_POST['note'])) : '';
	$result = famefe_service_save_member([
		'first_name' => famefe_post_text('first_name'),
		'last_name' => famefe_post_text('last_name'),
		'email' => famefe_post_text('email'),
		'user_id' => absint(famefe_post_text('user_id')),
		'note' => $note,
		'member_type' => famefe_post_text('member_type'),
		'member_since' => famefe_post_text('member_since'),
	], $id);
	if (!is_wp_error($result)) {
		$back = famefe_admin_url('famefe-members', ['action' => 'edit', 'id' => $result['id']]);
	}
	famefe_redirect_with_result($back, $result);
}
add_action('admin_post_famefe_save_member', 'famefe_handle_save_member');

/**
 * Record a membership change (type, end, rejoin).
 */
function famefe_handle_change_member(): void
{
	$id = absint(famefe_post_text('id'));
	$back = famefe_admin_url('famefe-members', ['action' => 'edit', 'id' => $id]);
	famefe_admin_verify('famefe_change_member', $back);
	$change = famefe_post_text('change');
	$type = '';
	if (str_starts_with($change, 'type:')) {
		$type = substr($change, 5);
		$change = 'type_changed';
	}
	famefe_redirect_with_result($back, famefe_service_change_member($id, $change, famefe_post_text('change_date'), $type, famefe_post_text('note')));
}
add_action('admin_post_famefe_change_member', 'famefe_handle_change_member');

/**
 * Delete a member entered by mistake.
 */
function famefe_handle_delete_member(): void
{
	$id = absint(famefe_post_text('id'));
	$back = famefe_admin_url('famefe-members', ['action' => 'edit', 'id' => $id]);
	famefe_admin_verify('famefe_delete_member', $back);
	$result = famefe_service_delete_member($id);
	famefe_redirect_with_result(is_wp_error($result) ? $back : famefe_admin_url('famefe-members'), $result);
}
add_action('admin_post_famefe_delete_member', 'famefe_handle_delete_member');

/**
 * Add hours for a member, or save an edited entry.
 */
function famefe_handle_save_hours(): void
{
	$id = absint(famefe_post_text('id'));
	$back = famefe_admin_url('famefe-hours', $id ? ['action' => 'edit', 'id' => $id] : []);
	famefe_admin_verify('famefe_save_hours', $back);
	$data = [
		'member_id' => absint(famefe_post_text('member_id')),
		'work_date' => famefe_post_text('work_date'),
		'hours' => famefe_post_text('hours'),
		'description' => famefe_post_text('description'),
	];
	if ($id) {
		$result = famefe_service_update_hours($id, $data);
	} elseif (!famefe_can_manage()) {
		$result = famefe_error('forbidden');
	} elseif ($data['member_id'] <= 0) {
		$result = famefe_error('not_found');
	} else {
		$result = famefe_service_add_hours($data);
		if (!is_wp_error($result)) {
			$result['code'] = 'hours_saved';
		}
	}
	famefe_redirect_with_result(is_wp_error($result) ? $back : famefe_admin_url('famefe-hours'), $result);
}
add_action('admin_post_famefe_save_hours', 'famefe_handle_save_hours');

/**
 * Delete an hours entry (row action link).
 */
function famefe_handle_delete_hours(): void
{
	$back = wp_get_referer() ?: famefe_admin_url('famefe-hours');
	famefe_admin_verify('famefe_delete_hours', $back);
	famefe_redirect_with_result($back, famefe_service_delete_hours(absint(famefe_post_text('id'))));
}
add_action('admin_post_famefe_delete_hours', 'famefe_handle_delete_hours');

/**
 * Record a cash or bank payment.
 */
function famefe_handle_save_payment(): void
{
	$back = famefe_admin_url('famefe-payments');
	famefe_admin_verify('famefe_save_payment', $back);
	famefe_redirect_with_result($back, famefe_service_record_payment([
		'member_id' => absint(famefe_post_text('member_id')),
		'period_start' => famefe_post_text('period_start'),
		'period_end' => famefe_post_text('period_end'),
		'amount' => famefe_post_text('amount'),
		'method' => famefe_post_text('method'),
		'paid_on' => famefe_post_text('paid_on'),
		'note' => famefe_post_text('note'),
	]));
}
add_action('admin_post_famefe_save_payment', 'famefe_handle_save_payment');

/**
 * Delete a payment (row action link).
 */
function famefe_handle_delete_payment(): void
{
	$back = wp_get_referer() ?: famefe_admin_url('famefe-payments');
	famefe_admin_verify('famefe_delete_payment', $back);
	famefe_redirect_with_result($back, famefe_service_delete_payment(absint(famefe_post_text('id'))));
}
add_action('admin_post_famefe_delete_payment', 'famefe_handle_delete_payment');
