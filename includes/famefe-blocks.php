<?php
/**
 * Blocks: registration and helpers shared by the render.php files and the block form handlers.
 * All blocks are dynamic; render.php prints plain HTML and forms (no JavaScript on the front end).
 *
 * @package fair-member-fees
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Register every block built from src/blocks into build/blocks (npm run build).
 */
function famefe_register_blocks(): void
{
	// One stylesheet for all blocks ("style": "famefe-blocks" in block.json).
	wp_register_style('famefe-blocks', FAMEFE_URL . 'assets/blocks.css', [], FAMEFE_VERSION);
	// Sorting and searching of the tables ("viewScript": "famefe-tables"); the tables work without it.
	wp_register_script('famefe-tables', FAMEFE_URL . 'assets/tables.js', [], FAMEFE_VERSION, ['strategy' => 'defer', 'in_footer' => true]);
	foreach ((array) glob(FAMEFE_PATH . 'build/blocks/*/block.json') as $metadata) {
		$block = register_block_type(dirname($metadata));
		if ($block) {
			foreach ($block->editor_script_handles as $handle) {
				wp_set_script_translations($handle, 'fair-member-fees', FAMEFE_PATH . 'languages');
			}
		}
	}
}
add_action('init', 'famefe_register_blocks');

/**
 * Settings the block editor needs (currency, default name display, whether the user manages members).
 */
function famefe_block_editor_settings(): void
{
	$data = [
		'currency' => famefe_settings('currency'),
		'decimals' => intval(famefe_settings('decimals')),
		'nameDisplay' => famefe_settings('name_display'),
		'settingsUrl' => admin_url('admin.php?page=famefe-settings'),
	];
	wp_add_inline_script('wp-blocks', 'window.famefeEditor = ' . wp_json_encode($data) . ';', 'before');
}
add_action('enqueue_block_editor_assets', 'famefe_block_editor_settings');

/**
 * Attributes of a block with the given blockId in a post (also inside groups, columns and synced patterns).
 * Form handlers use them, so nothing that decides an amount or a limit comes from the browser.
 */
function famefe_find_block(int $post_id, string $name, string $block_id): ?array
{
	$post = $post_id > 0 ? get_post($post_id) : null;
	if (!$post || $block_id === '' || !is_post_publicly_viewable($post) && !current_user_can('read_post', $post_id)) {
		return null;
	}
	return famefe_find_block_in(parse_blocks($post->post_content), $name, $block_id, 0);
}

/**
 * Recursive part of famefe_find_block().
 */
function famefe_find_block_in(array $blocks, string $name, string $block_id, int $depth): ?array
{
	if ($depth > 10) {
		return null;
	}
	foreach ($blocks as $block) {
		if (($block['blockName'] ?? '') === $name && ($block['attrs']['blockId'] ?? '') === $block_id) {
			return $block['attrs'];
		}
		if (!empty($block['innerBlocks'])) {
			$found = famefe_find_block_in($block['innerBlocks'], $name, $block_id, $depth + 1);
			if ($found !== null) {
				return $found;
			}
		}
		if (($block['blockName'] ?? '') === 'core/block' && !empty($block['attrs']['ref'])) {
			$pattern = get_post(intval($block['attrs']['ref']));
			if ($pattern && $pattern->post_type === 'wp_block' && $pattern->post_status === 'publish') {
				$found = famefe_find_block_in(parse_blocks($pattern->post_content), $name, $block_id, $depth + 1);
				if ($found !== null) {
					return $found;
				}
			}
		}
	}
	return null;
}

/**
 * Period and fee parameters of a Membership fees block, with the defaults of block.json
 * (period = the current calendar year when not set).
 *
 * @return array start, end, base_fee, discount_share, discount_rate.
 */
function famefe_fees_block_args(array $attrs): array
{
	$year = current_time('Y');
	$start = famefe_valid_date($attrs['periodStart'] ?? '') ?: $year . '-01-01';
	$end = famefe_valid_date($attrs['periodEnd'] ?? '') ?: $year . '-12-31';
	if ($end < $start) {
		$end = $start;
	}
	$discount = !empty($attrs['discountEnabled']);
	return [
		'start' => $start,
		'end' => $end,
		'base_fee' => max(0, floatval($attrs['baseFee'] ?? 1000)),
		'discount_share' => $discount ? min(100, max(0, floatval($attrs['discountShare'] ?? 60))) : 0,
		'discount_rate' => $discount ? min(100, max(0, floatval($attrs['discountRate'] ?? 20))) : 0,
	];
}

/**
 * Limits of an Hours form block for famefe_service_add_hours(). Members record hours only for today
 * unless the block allows another date (then at most daysBack days back); administrators and editors
 * may choose any past date.
 */
function famefe_hours_block_limits(array $attrs): array
{
	$limits = [
		'max_hours' => min(24, max(0.5, floatval($attrs['maxHours'] ?? 8))),
		'require_description' => $attrs['requireDescription'] ?? true,
	];
	if (!famefe_can_manage()) {
		$limits['days_back'] = empty($attrs['allowDateChange']) ? 0 : max(0, intval($attrs['daysBack'] ?? 30));
	}
	return $limits;
}

/**
 * Deletion limits of a Volunteer hours list block for famefe_service_delete_hours_entry() (days after recording).
 */
function famefe_hours_list_delete_limits(array $attrs): array
{
	return [
		'member_days' => min(366, max(0, intval($attrs['memberDeleteDays'] ?? 7))),
		'manager_days' => min(3660, max(0, intval($attrs['managerDeleteDays'] ?? 7))),
	];
}

/**
 * Safe blockId from the editor (letters and digits).
 */
function famefe_block_id(array $attrs): string
{
	return substr(preg_replace('/[^a-z0-9]/', '', strtolower((string) ($attrs['blockId'] ?? ''))), 0, 32);
}

/**
 * Hidden fields that tell a block form handler which block sent it.
 */
function famefe_block_form_fields(string $action, string $block_id): string
{
	$post_id = intval(get_the_ID());
	return sprintf(
		'<input type="hidden" name="action" value="%1$s"><input type="hidden" name="post_id" value="%2$d"><input type="hidden" name="block_id" value="%3$s">%4$s',
		esc_attr($action),
		$post_id,
		esc_attr($block_id),
		wp_nonce_field($action . '_' . $post_id, '_wpnonce', true, false)
	);
}

/**
 * The message box of a block when the block has no blockId yet (inserted before saving) or the
 * page cannot be found by the form handler.
 */
function famefe_block_message(string $text, string $class = ''): string
{
	return sprintf(
		'<div %1$s><p class="famefe-notice">%2$s</p></div>',
		get_block_wrapper_attributes(['class' => trim('famefe-block ' . $class)]),
		esc_html($text)
	);
}

/**
 * Whether a column/option attribute of a block is on (block.json defaults apply when missing).
 */
function famefe_attr_on(array $attrs, string $key, bool $default = true): bool
{
	return array_key_exists($key, $attrs) ? (bool) $attrs[$key] : $default;
}
