<?php
/**
 * Messages after a form was sent. Form handlers redirect back with ?famefe_notice=<code>;
 * the admin pages and the blocks print the text of that code. No state is stored.
 *
 * @package fair-member-fees
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Text of a notice code ('' for unknown codes).
 */
function famefe_notice_text(string $code): string
{
	$texts = [
		// Success.
		'member_added' => __('The member was added.', 'fair-member-fees'),
		'member_saved' => __('The member was saved.', 'fair-member-fees'),
		'member_changed' => __('The change of the membership was recorded.', 'fair-member-fees'),
		'member_deleted' => __('The member was deleted.', 'fair-member-fees'),
		'hours_added' => __('Thank you, your hours were recorded.', 'fair-member-fees'),
		'hours_saved' => __('The hours were saved.', 'fair-member-fees'),
		'hours_deleted' => __('The hours were deleted.', 'fair-member-fees'),
		'payment_recorded' => __('The payment was recorded.', 'fair-member-fees'),
		'payment_deleted' => __('The payment was deleted.', 'fair-member-fees'),
		'paid' => __('Thank you, your membership fee was paid.', 'fair-member-fees'),
		'payment_cancelled' => __('The payment was cancelled. You can try again at any time.', 'fair-member-fees'),
		'settings_saved' => __('The settings were saved.', 'fair-member-fees'),
		// Errors.
		'forbidden' => __('You are not allowed to do this.', 'fair-member-fees'),
		'login_required' => __('Please log in first.', 'fair-member-fees'),
		'expired' => __('The form has expired. Please reload the page and try again.', 'fair-member-fees'),
		'not_found' => __('The record was not found.', 'fair-member-fees'),
		'invalid_name' => __('Enter the first or the last name.', 'fair-member-fees'),
		'invalid_date' => __('Enter a valid date.', 'fair-member-fees'),
		'invalid_type' => __('Choose a valid membership type.', 'fair-member-fees'),
		'invalid_change' => __('This change is not possible for the member (check the status and the date).', 'fair-member-fees'),
		'user_taken' => __('This user account already belongs to another member.', 'fair-member-fees'),
		'has_records' => __('The member has hours or payments and cannot be deleted. End the membership instead.', 'fair-member-fees'),
		'invalid_hours' => __('Enter a valid number of hours.', 'fair-member-fees'),
		'date_out_of_range' => __('Hours cannot be recorded for this date any more.', 'fair-member-fees'),
		'description_required' => __('Describe the work, please.', 'fair-member-fees'),
		'not_member' => __('Only members can record volunteer hours.', 'fair-member-fees'),
		'invalid_amount' => __('Enter a valid amount.', 'fair-member-fees'),
		'invalid_method' => __('Choose a valid payment method.', 'fair-member-fees'),
		'already_paid' => __('The membership fee for this period has already been paid.', 'fair-member-fees'),
		'nothing_to_pay' => __('There is nothing to pay.', 'fair-member-fees'),
		'block_not_found' => __('The block of this form was not found on the page. Please reload the page.', 'fair-member-fees'),
		'stripe_not_configured' => __('Online payment is not set up.', 'fair-member-fees'),
		'stripe_error' => __('The payment service could not be reached. Please try again later.', 'fair-member-fees'),
		'payment_not_confirmed' => __('The payment has not been confirmed yet. If you paid, it will appear shortly.', 'fair-member-fees'),
		'db_error' => __('The data could not be saved. Please try again.', 'fair-member-fees'),
	];
	return $texts[$code] ?? '';
}

/**
 * Notice of the current request: ['code', 'text', 'error'] or null.
 */
function famefe_current_notice(): ?array
{
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only selects a fixed message.
	$code = isset($_GET['famefe_notice']) ? sanitize_key(wp_unslash($_GET['famefe_notice'])) : '';
	$text = $code !== '' ? famefe_notice_text($code) : '';
	if ($text === '') {
		return null;
	}
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$error = isset($_GET['famefe_error']);
	return ['code' => $code, 'text' => $text, 'error' => $error];
}

/**
 * Redirect to $url with the notice of a service result and stop.
 */
function famefe_redirect_with_result(string $url, array|WP_Error $result, array $args = []): void
{
	$url = remove_query_arg(['famefe_notice', 'famefe_error', 'famefe_session'], $url);
	$args['famefe_notice'] = is_wp_error($result) ? $result->get_error_code() : $result['code'];
	if (is_wp_error($result)) {
		$args['famefe_error'] = 1;
	}
	wp_safe_redirect(add_query_arg($args, $url));
	exit;
}

/**
 * Notice box of a block. Block forms redirect with famefe_block=<blockId>, so only the block
 * that sent the form shows the message.
 */
function famefe_block_notice(string $block_id): string
{
	$notice = famefe_current_notice();
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only selects which block shows the message.
	$target = isset($_GET['famefe_block']) ? sanitize_key(wp_unslash($_GET['famefe_block'])) : '';
	if (!$notice || $block_id === '' || $target !== $block_id) {
		return '';
	}
	return sprintf(
		'<p class="famefe-notice%s" role="%s">%s</p>',
		$notice['error'] ? ' famefe-notice--error' : '',
		$notice['error'] ? 'alert' : 'status',
		esc_html($notice['text'])
	);
}
