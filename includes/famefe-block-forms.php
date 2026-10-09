<?php
/**
 * Handlers of the forms printed by the blocks (admin-post.php). Each one checks the nonce,
 * reads the block attributes from the saved page (famefe_find_block()), calls a service
 * and redirects back to the page with a notice for the block.
 *
 * @package fair-member-fees
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Common start of a block form handler: nonce, login and the block attributes.
 *
 * @return array [post_id, block_id, attrs, back URL]; redirects with a notice on failure.
 */
function famefe_block_form_start(string $action, string $block_name): array
{
	// phpcs:disable WordPress.Security.NonceVerification.Missing -- the nonce is verified right below.
	$post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
	$block_id = isset($_POST['block_id']) ? famefe_block_id(['blockId' => sanitize_text_field(wp_unslash($_POST['block_id']))]) : '';
	// phpcs:enable
	$back = $post_id > 0 ? (string) get_permalink($post_id) : '';
	if ($back === '') {
		$back = wp_get_referer() ?: home_url('/');
	}
	$fail = fn(string $code) => famefe_redirect_with_result($back, famefe_error($code), ['famefe_block' => $block_id]);

	if (!is_user_logged_in()) {
		wp_safe_redirect(wp_login_url($back));
		exit;
	}
	$nonce = isset($_POST['_wpnonce']) ? sanitize_text_field(wp_unslash($_POST['_wpnonce'])) : '';
	if (!wp_verify_nonce($nonce, $action . '_' . $post_id)) {
		$fail('expired');
	}
	$attrs = famefe_find_block($post_id, $block_name, $block_id);
	if ($attrs === null) {
		$fail('block_not_found');
	}
	return [$post_id, $block_id, $attrs, $back];
}

/**
 * Hours form block: record own hours (managers may choose another member).
 */
function famefe_handle_block_hours(): void
{
	[, $block_id, $attrs, $back] = famefe_block_form_start('famefe_block_hours', 'famefe/hours-form');
	// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified in famefe_block_form_start().
	$result = famefe_service_add_hours([
		'member_id' => isset($_POST['member_id']) ? absint($_POST['member_id']) : 0,
		'work_date' => isset($_POST['work_date']) ? sanitize_text_field(wp_unslash($_POST['work_date'])) : '',
		'hours' => isset($_POST['hours']) ? sanitize_text_field(wp_unslash($_POST['hours'])) : '',
		'description' => isset($_POST['description']) ? sanitize_text_field(wp_unslash($_POST['description'])) : '',
	], famefe_hours_block_limits($attrs));
	// phpcs:enable
	famefe_redirect_with_result($back, $result, ['famefe_block' => $block_id]);
}
add_action('admin_post_famefe_block_hours', 'famefe_handle_block_hours');
add_action('admin_post_nopriv_famefe_block_hours', 'famefe_handle_block_hours');

/**
 * Membership fees block: a manager records a cash or bank payment. The amount is the fee
 * the block calculates for the member, never a number from the browser.
 */
function famefe_handle_block_payment(): void
{
	[, $block_id, $attrs, $back] = famefe_block_form_start('famefe_block_payment', 'famefe/membership-fees');
	$args = famefe_fees_block_args($attrs);
	// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified in famefe_block_form_start().
	$member_id = isset($_POST['member_id']) ? absint($_POST['member_id']) : 0;
	$method = isset($_POST['method']) ? sanitize_key(wp_unslash($_POST['method'])) : '';
	$paid_on = isset($_POST['paid_on']) ? sanitize_text_field(wp_unslash($_POST['paid_on'])) : '';
	// phpcs:enable
	$fees = famefe_period_fees($args['start'], $args['end'], $args['base_fee'], $args['discount_share'], $args['discount_rate']);
	$row = $fees['calc']['rows'][$member_id] ?? null;
	if (!famefe_can_manage()) {
		$result = famefe_error('forbidden');
	} elseif (!$row || $row['member_type'] !== 'regular') {
		$result = famefe_error('not_found');
	} else {
		$result = famefe_service_record_payment([
			'member_id' => $member_id,
			'period_start' => $args['start'],
			'period_end' => $args['end'],
			'amount' => $row['final_fee'],
			'method' => $method,
			'paid_on' => $paid_on,
		]);
	}
	famefe_redirect_with_result($back, $result, ['famefe_block' => $block_id]);
}
add_action('admin_post_famefe_block_payment', 'famefe_handle_block_payment');
add_action('admin_post_nopriv_famefe_block_payment', 'famefe_handle_block_payment');

/**
 * Membership fees block: the logged-in regular member pays the own fee through Stripe Checkout.
 */
function famefe_handle_block_checkout(): void
{
	[$post_id, $block_id, $attrs, $back] = famefe_block_form_start('famefe_block_checkout', 'famefe/membership-fees');
	$args = famefe_fees_block_args($attrs);
	$fail = fn(string $code) => famefe_redirect_with_result($back, famefe_error($code), ['famefe_block' => $block_id]);

	$member = famefe_get_member(get_current_user_id());
	if (!$member || empty($attrs['showPayButton'] ?? true)) {
		$fail('forbidden');
	}
	$fees = famefe_period_fees($args['start'], $args['end'], $args['base_fee'], $args['discount_share'], $args['discount_rate']);
	$row = $fees['calc']['rows'][intval($member->id)] ?? null;
	if (!$row || $row['member_type'] !== 'regular') {
		$fail('forbidden');
	}
	if (isset($fees['payments'][intval($member->id)])) {
		$fail('already_paid');
	}
	if ($row['final_fee'] <= 0) {
		$fail('nothing_to_pay');
	}
	$url = famefe_stripe_create_checkout($member, $row['final_fee'], $args['start'], $args['end'], $post_id, $block_id);
	if (is_wp_error($url)) {
		$fail($url->get_error_code());
	}
	wp_safe_redirect($url);
	exit;
}
add_action('admin_post_famefe_block_checkout', 'famefe_handle_block_checkout');
add_action('admin_post_nopriv_famefe_block_checkout', 'famefe_handle_block_checkout');
