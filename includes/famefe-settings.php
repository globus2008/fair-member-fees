<?php
/**
 * Plugin settings (option famefe_settings) and formatting helpers that depend on them.
 * The season and the base fee are not settings: they are attributes of the Membership fees block.
 *
 * @package fair-member-fees
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Default values of all settings.
 */
function famefe_default_settings(): array
{
	return [
		'currency' => 'CZK',
		'decimals' => 0,
		'stripe_mode' => 'test',
		'stripe_test_secret' => '',
		'stripe_test_webhook_secret' => '',
		'stripe_live_secret' => '',
		'stripe_live_webhook_secret' => '',
		'hours_non_members' => 0,
		'name_display' => 'full',
		'delete_data' => 0,
	];
}

/**
 * All settings merged with the defaults, or one value when $key is given.
 */
function famefe_settings(?string $key = null): mixed
{
	$settings = array_merge(famefe_default_settings(), (array) get_option('famefe_settings', []));
	return $key === null ? $settings : ($settings[$key] ?? null);
}

/**
 * Sanitize callback of the settings form (Settings API).
 */
function famefe_sanitize_settings($input): array
{
	$input = (array) $input;
	$old = famefe_settings();
	$out = famefe_default_settings();

	$currency = strtoupper(preg_replace('/[^A-Za-z]/', '', (string) ($input['currency'] ?? '')));
	$out['currency'] = strlen($currency) === 3 ? $currency : $old['currency'];
	$decimals = $input['decimals'] ?? '';
	// Empty field = the usual number of decimals of the currency.
	$out['decimals'] = $decimals === '' ? famefe_currency_decimals($out['currency']) : max(0, min(4, intval($decimals)));
	$out['stripe_mode'] = ($input['stripe_mode'] ?? '') === 'live' ? 'live' : 'test';
	foreach (['stripe_test_secret', 'stripe_test_webhook_secret', 'stripe_live_secret', 'stripe_live_webhook_secret'] as $key) {
		$out[$key] = trim(sanitize_text_field((string) ($input[$key] ?? '')));
	}
	$out['hours_non_members'] = empty($input['hours_non_members']) ? 0 : 1;
	$out['name_display'] = famefe_name_mode((string) ($input['name_display'] ?? ''), 'full');
	$out['delete_data'] = empty($input['delete_data']) ? 0 : 1;
	return $out;
}

/**
 * Usual number of decimals of a currency (ISO 4217); used when the setting is left empty.
 */
function famefe_currency_decimals(string $currency): int
{
	$whole = ['CZK', 'HUF', 'JPY', 'KRW', 'ISK', 'CLP', 'VND', 'PYG', 'UGX', 'XAF', 'XOF'];
	return in_array(strtoupper($currency), $whole, true) ? 0 : 2;
}

/**
 * A valid name display mode; $fallback for anything else (e.g. 'default' of a block).
 */
function famefe_name_mode(string $mode, string $fallback = ''): string
{
	if (in_array($mode, ['full', 'short', 'initials', 'display', 'masked'], true)) {
		return $mode;
	}
	return $fallback !== '' ? $fallback : (string) famefe_settings('name_display');
}

/**
 * Amount rounded and formatted with the currency of the settings, e.g. "1 300 Kč" or "12.50 USD".
 */
function famefe_format_money(float $amount, ?string $currency = null): string
{
	$currency = $currency ?: (string) famefe_settings('currency');
	$decimals = $currency === famefe_settings('currency') ? intval(famefe_settings('decimals')) : famefe_currency_decimals($currency);
	$symbols = ['CZK' => 'Kč', 'EUR' => '€', 'USD' => '$', 'GBP' => '£', 'PLN' => 'zł', 'HUF' => 'Ft', 'CHF' => 'CHF'];
	return sprintf(
		/* translators: 1: amount, 2: currency symbol or code (Kč, €, USD). */
		__('%1$s %2$s', 'fair-member-fees'),
		number_format_i18n($amount, $decimals),
		$symbols[$currency] ?? $currency
	);
}

/**
 * Number of hours for display (one decimal only when needed: 10, 2.5).
 */
function famefe_format_hours(float $hours): string
{
	return number_format_i18n($hours, floor($hours) == $hours ? 0 : 1);
}

/**
 * Date (Y-m-d or Y-m-d H:i:s) in the site's date format; '' for empty values.
 */
function famefe_format_date(?string $date, bool $with_time = false): string
{
	if (!$date || str_starts_with($date, '0000')) {
		return '';
	}
	$format = get_option('date_format') . ($with_time ? ' ' . get_option('time_format') : '');
	// Stored dates are local (site) times.
	$time = date_create_immutable($date, wp_timezone());
	return $time ? wp_date($format, $time->getTimestamp()) : '';
}

/**
 * Today in the site's time zone (Y-m-d).
 */
function famefe_today(): string
{
	return current_time('Y-m-d');
}

/**
 * The date $days days before today in the site's time zone (Y-m-d).
 */
function famefe_days_ago(int $days): string
{
	return (new DateTimeImmutable(famefe_today(), wp_timezone()))->modify('-' . max(0, $days) . ' days')->format('Y-m-d');
}

/**
 * A valid Y-m-d date or ''.
 */
function famefe_valid_date($value): string
{
	$value = trim((string) $value);
	$date = DateTime::createFromFormat('!Y-m-d', $value);
	return $date && $date->format('Y-m-d') === $value ? $value : '';
}
