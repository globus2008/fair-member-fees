<?php
/**
 * Payment of the membership fee through Stripe Checkout (Stripe API over WordPress HTTP, no library).
 *
 * Flow: the block form (famefe_handle_block_checkout) creates a Checkout Session with the fee the server
 * calculated and sends the member to Stripe. Stripe returns to the page with ?famefe_session=<id>; the
 * session is checked over the API and the payment stored once (unique stripe_session_id). The webhook
 * POST famefe/v1/stripe-webhook stores it too, in case the member never returns to the page.
 *
 * @package fair-member-fees
 */

if (!defined('ABSPATH')) {
	exit;
}

const FAMEFE_STRIPE_API = 'https://api.stripe.com/v1/';

/**
 * Secret key of the active mode (test or live), '' when not set.
 */
function famefe_stripe_secret(): string
{
	return (string) famefe_settings('stripe_' . famefe_settings('stripe_mode') . '_secret');
}

/**
 * Webhook signing secret of the active mode, '' when not set.
 */
function famefe_stripe_webhook_secret(): string
{
	return (string) famefe_settings('stripe_' . famefe_settings('stripe_mode') . '_webhook_secret');
}

/**
 * Whether members can pay online.
 */
function famefe_stripe_ready(): bool
{
	return famefe_stripe_secret() !== '';
}

/**
 * Currencies Stripe charges in whole units (no cents).
 */
function famefe_stripe_zero_decimal(string $currency): bool
{
	return in_array(strtoupper($currency), ['BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW', 'MGA', 'PYG', 'RWF', 'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF'], true);
}

/**
 * Amount in the smallest unit of the currency, as Stripe wants it (100.50 EUR = 10050).
 */
function famefe_stripe_to_minor(float $amount, string $currency): int
{
	return (int) round(famefe_stripe_zero_decimal($currency) ? $amount : $amount * 100);
}

/**
 * Amount from the smallest unit of the currency.
 */
function famefe_stripe_from_minor(int $amount, string $currency): float
{
	return famefe_stripe_zero_decimal($currency) ? (float) $amount : $amount / 100;
}

/**
 * Mark of this site in the session metadata, so that sessions of another site using the same
 * Stripe account are never stored here.
 */
function famefe_stripe_site_mark(): string
{
	return substr(md5(home_url('/')), 0, 12);
}

/**
 * Call the Stripe API. Returns the decoded answer or a WP_Error with the code stripe_error.
 */
function famefe_stripe_request(string $method, string $path, array $body = []): array|WP_Error
{
	$secret = famefe_stripe_secret();
	if ($secret === '') {
		return famefe_error('stripe_not_configured');
	}
	$response = wp_remote_request(FAMEFE_STRIPE_API . $path, [
		'method' => $method,
		'timeout' => 20,
		'headers' => ['Authorization' => 'Bearer ' . $secret],
		'body' => $method === 'GET' ? null : $body,
	]);
	if (is_wp_error($response)) {
		return famefe_error('stripe_error');
	}
	$data = json_decode(wp_remote_retrieve_body($response), true);
	if (wp_remote_retrieve_response_code($response) !== 200 || !is_array($data)) {
		return famefe_error('stripe_error');
	}
	return $data;
}

/**
 * Create a Checkout Session for the fee of a member and return its payment page address.
 */
function famefe_stripe_create_checkout(object $member, float $amount, string $start, string $end, int $post_id, string $block_id): string|WP_Error
{
	if (!famefe_stripe_ready()) {
		return famefe_error('stripe_not_configured');
	}
	$currency = (string) famefe_settings('currency');
	$page = (string) get_permalink($post_id);
	$email = $member->email ?: (string) wp_get_current_user()->user_email;
	$body = [
		'mode' => 'payment',
		'line_items' => [[
			'quantity' => 1,
			'price_data' => [
				'currency' => strtolower($currency),
				'unit_amount' => famefe_stripe_to_minor($amount, $currency),
				'product_data' => [
					'name' => sprintf(
						/* translators: 1: first day of the period, 2: last day of the period. */
						__('Membership fee %1$s – %2$s', 'fair-member-fees'),
						famefe_format_date($start),
						famefe_format_date($end)
					),
				],
			],
		]],
		'client_reference_id' => (string) $member->id,
		'metadata' => [
			'famefe_site' => famefe_stripe_site_mark(),
			'member_id' => (string) $member->id,
			'period_start' => $start,
			'period_end' => $end,
			'block_id' => $block_id,
		],
		// Stripe replaces {CHECKOUT_SESSION_ID}; add_query_arg() keeps the braces as they are.
		'success_url' => add_query_arg(['famefe_session' => '{CHECKOUT_SESSION_ID}', 'famefe_block' => $block_id], $page),
		'cancel_url' => add_query_arg(['famefe_notice' => 'payment_cancelled', 'famefe_block' => $block_id], $page),
	];
	if (is_email($email)) {
		$body['customer_email'] = $email;
	}
	$session = famefe_stripe_request('POST', 'checkout/sessions', $body);
	if (is_wp_error($session)) {
		return $session;
	}
	return (string) ($session['url'] ?? '') ?: famefe_error('stripe_error');
}

/**
 * Let wp_safe_redirect() send members to the Stripe payment page.
 */
function famefe_stripe_redirect_hosts(array $hosts): array
{
	$hosts[] = 'checkout.stripe.com';
	return $hosts;
}
add_filter('allowed_redirect_hosts', 'famefe_stripe_redirect_hosts');

/**
 * Store the payment of a paid Checkout Session of this site (repeated calls store nothing new).
 */
function famefe_stripe_record_session(array $session): array|WP_Error
{
	$meta = (array) ($session['metadata'] ?? []);
	if (($meta['famefe_site'] ?? '') !== famefe_stripe_site_mark() || empty($meta['member_id'])) {
		return famefe_error('not_found');
	}
	if (($session['payment_status'] ?? '') !== 'paid') {
		return famefe_error('payment_not_confirmed');
	}
	$start = famefe_valid_date($meta['period_start'] ?? '');
	$end = famefe_valid_date($meta['period_end'] ?? '');
	$member = famefe_get_member_by_id(intval($meta['member_id']));
	if (!$member || $start === '' || $end === '') {
		return famefe_error('not_found');
	}
	$currency = strtoupper((string) ($session['currency'] ?? famefe_settings('currency')));
	return famefe_store_payment([
		'member_id' => intval($member->id),
		'period_start' => $start,
		'period_end' => $end,
		'amount' => famefe_stripe_from_minor(intval($session['amount_total'] ?? 0), $currency),
		'currency' => $currency,
		'method' => 'stripe',
		'stripe_session_id' => (string) $session['id'],
	]);
}

/**
 * Return from Stripe: check the session, store the payment and show the result in the block.
 */
function famefe_stripe_handle_return(): void
{
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- the session is verified with Stripe.
	$session_id = isset($_GET['famefe_session']) ? sanitize_text_field(wp_unslash($_GET['famefe_session'])) : '';
	if ($session_id === '' || !preg_match('/^cs_[A-Za-z0-9_]+$/', $session_id)) {
		return;
	}
	$session = famefe_stripe_request('GET', 'checkout/sessions/' . rawurlencode($session_id));
	$result = is_wp_error($session) ? $session : famefe_stripe_record_session($session);
	if (!is_wp_error($result)) {
		$result['code'] = 'paid';
	}
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$block = isset($_GET['famefe_block']) ? sanitize_key(wp_unslash($_GET['famefe_block'])) : '';
	$page = is_singular() ? (string) get_permalink() : home_url('/');
	famefe_redirect_with_result($page, $result, ['famefe_block' => $block]);
}
add_action('template_redirect', 'famefe_stripe_handle_return');

/**
 * REST route of the Stripe webhook.
 */
function famefe_stripe_register_routes(): void
{
	register_rest_route('famefe/v1', '/stripe-webhook', [
		'methods' => 'POST',
		'callback' => 'famefe_stripe_webhook',
		// Stripe cannot log in; the request is authenticated by its signature in the callback.
		'permission_callback' => '__return_true',
	]);
}
add_action('rest_api_init', 'famefe_stripe_register_routes');

/**
 * Check the Stripe-Signature header (HMAC-SHA256 of "timestamp.body", at most 5 minutes old).
 */
function famefe_stripe_signature_valid(string $payload, string $header, string $secret, int $tolerance = 300): bool
{
	if ($secret === '' || $header === '') {
		return false;
	}
	$timestamp = 0;
	$signatures = [];
	foreach (explode(',', $header) as $part) {
		[$key, $value] = array_pad(explode('=', trim($part), 2), 2, '');
		if ($key === 't') {
			$timestamp = intval($value);
		} elseif ($key === 'v1') {
			$signatures[] = $value;
		}
	}
	if ($timestamp <= 0 || abs(time() - $timestamp) > $tolerance) {
		return false;
	}
	$expected = hash_hmac('sha256', $timestamp . '.' . $payload, $secret);
	foreach ($signatures as $signature) {
		if (hash_equals($expected, $signature)) {
			return true;
		}
	}
	return false;
}

/**
 * Webhook: store the payment of checkout.session.completed / async_payment_succeeded.
 */
function famefe_stripe_webhook(WP_REST_Request $request): WP_REST_Response
{
	$payload = $request->get_body();
	if (!famefe_stripe_signature_valid($payload, (string) $request->get_header('stripe_signature'), famefe_stripe_webhook_secret())) {
		return new WP_REST_Response(['error' => 'invalid_signature'], 400);
	}
	$event = json_decode($payload, true);
	$type = (string) ($event['type'] ?? '');
	if (in_array($type, ['checkout.session.completed', 'checkout.session.async_payment_succeeded'], true)) {
		$result = famefe_stripe_record_session((array) ($event['data']['object'] ?? []));
		if (is_wp_error($result) && $result->get_error_code() === 'db_error') {
			// Stripe retries the event later.
			return new WP_REST_Response(['error' => 'db_error'], 500);
		}
	}
	return new WP_REST_Response(['received' => true], 200);
}
