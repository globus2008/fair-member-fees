<?php
/**
 * Admin pages: Members, Volunteer hours, Payments, Settings and Help.
 * Forms post to admin-post.php (famefe-admin-handlers.php); lists use WP_List_Table.
 *
 * @package fair-member-fees
 */

if (!defined('ABSPATH')) {
	exit;
}

require_once FAMEFE_PATH . 'includes/famefe-admin-tables.php';

/**
 * Admin menu.
 */
function famefe_admin_menu(): void
{
	add_menu_page(
		__('Fair Member Fees', 'fair-member-fees'),
		__('Member Fees', 'fair-member-fees'),
		FAMEFE_CAPABILITY,
		'famefe-members',
		'famefe_page_members',
		'dashicons-groups',
		71
	);
	add_submenu_page('famefe-members', __('Members', 'fair-member-fees'), __('Members', 'fair-member-fees'), FAMEFE_CAPABILITY, 'famefe-members', 'famefe_page_members');
	add_submenu_page('famefe-members', __('Volunteer hours', 'fair-member-fees'), __('Volunteer hours', 'fair-member-fees'), FAMEFE_CAPABILITY, 'famefe-hours', 'famefe_page_hours');
	add_submenu_page('famefe-members', __('Payments', 'fair-member-fees'), __('Payments', 'fair-member-fees'), FAMEFE_CAPABILITY, 'famefe-payments', 'famefe_page_payments');
	add_submenu_page('famefe-members', __('Settings', 'fair-member-fees'), __('Settings', 'fair-member-fees'), 'manage_options', 'famefe-settings', 'famefe_page_settings');
	add_submenu_page('famefe-members', __('Help', 'fair-member-fees'), __('Help', 'fair-member-fees'), FAMEFE_CAPABILITY, 'famefe-help', 'famefe_page_help');
}
add_action('admin_menu', 'famefe_admin_menu');

/**
 * Styles of the plugin pages.
 */
function famefe_admin_assets(string $hook): void
{
	if (!str_contains($hook, 'famefe-')) {
		return;
	}
	wp_enqueue_style('famefe-admin', FAMEFE_URL . 'assets/admin.css', [], FAMEFE_VERSION);
}
add_action('admin_enqueue_scripts', 'famefe_admin_assets');

/**
 * Notice after a form of the plugin pages.
 */
function famefe_admin_notice(): void
{
	$screen = get_current_screen();
	$notice = famefe_current_notice();
	if (!$notice || !$screen || !str_contains((string) $screen->id, 'famefe-')) {
		return;
	}
	printf(
		'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
		$notice['error'] ? 'error' : 'success',
		esc_html($notice['text'])
	);
}
add_action('admin_notices', 'famefe_admin_notice');

/**
 * URL of a plugin page.
 */
function famefe_admin_url(string $page, array $args = []): string
{
	return add_query_arg(array_merge(['page' => $page], $args), admin_url('admin.php'));
}

/**
 * Hidden fields of an admin form: the admin-post action and its nonce.
 */
function famefe_admin_form_fields(string $action): void
{
	printf('<input type="hidden" name="action" value="%s">', esc_attr($action));
	wp_nonce_field($action);
}

/**
 * Options of a member select (active members first, then former members).
 */
function famefe_member_options(int $selected = 0, bool $with_left = true): void
{
	foreach (['active', 'left'] as $status) {
		if ($status === 'left' && !$with_left) {
			break;
		}
		$members = famefe_get_members(['status' => $status]);
		if (!$members) {
			continue;
		}
		printf('<optgroup label="%s">', esc_attr($status === 'active' ? __('Members', 'fair-member-fees') : __('Former members', 'fair-member-fees')));
		foreach ($members as $member) {
			printf(
				'<option value="%1$d"%2$s>%3$s</option>',
				intval($member->id),
				selected($selected, intval($member->id), false),
				esc_html(famefe_member_name($member) . ($member->member_type === 'honorary' ? ' (' . famefe_member_types()['honorary'] . ')' : ''))
			);
		}
		echo '</optgroup>';
	}
}

/**
 * The "recorded by … on …" text of a log entry, hours or payment.
 */
function famefe_recorded_text(int $user_id, string $at): string
{
	if ($user_id <= 0) {
		/* translators: %s: date and time. */
		return sprintf(__('automatically on %s', 'fair-member-fees'), famefe_format_date($at, true));
	}
	/* translators: 1: name of the person, 2: date and time. */
	return sprintf(__('%1$s on %2$s', 'fair-member-fees'), famefe_user_name($user_id), famefe_format_date($at, true));
}

/**
 * Members page: list, or the form of one member with its history.
 */
function famefe_page_members(): void
{
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- navigation only.
	$action = isset($_GET['action']) ? sanitize_key(wp_unslash($_GET['action'])) : '';
	$id = isset($_GET['id']) ? absint($_GET['id']) : 0;
	// phpcs:enable
	if ($action === 'new' || ($action === 'edit' && $id > 0)) {
		famefe_page_member_edit($action === 'edit' ? famefe_get_member_by_id($id) : null);
		return;
	}
	$table = new Famefe_Members_Table();
	$table->prepare_items();
	?>
	<div class="wrap">
		<h1 class="wp-heading-inline"><?php esc_html_e('Members', 'fair-member-fees'); ?></h1>
		<a href="<?php echo esc_url(famefe_admin_url('famefe-members', ['action' => 'new'])); ?>" class="page-title-action"><?php esc_html_e('Add member', 'fair-member-fees'); ?></a>
		<hr class="wp-header-end">
		<?php $table->views(); ?>
		<form method="get">
			<input type="hidden" name="page" value="famefe-members">
			<?php
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$status = isset($_GET['status']) ? sanitize_key(wp_unslash($_GET['status'])) : '';
			if ($status !== '') {
				printf('<input type="hidden" name="status" value="%s">', esc_attr($status));
			}
			$table->search_box(__('Search members', 'fair-member-fees'), 'famefe-member');
			$table->display();
			?>
		</form>
	</div>
	<?php
}

/**
 * Form of one member (new or existing), membership changes and history.
 */
function famefe_page_member_edit(?object $member): void
{
	$is_new = $member === null;
	$types = famefe_member_types();
	?>
	<div class="wrap famefe-admin">
		<h1><?php echo $is_new ? esc_html__('Add member', 'fair-member-fees') : esc_html(famefe_member_name($member)); ?></h1>
		<p><a href="<?php echo esc_url(famefe_admin_url('famefe-members')); ?>">&larr; <?php esc_html_e('Back to the members', 'fair-member-fees'); ?></a></p>

		<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="famefe-card">
			<?php famefe_admin_form_fields('famefe_save_member'); ?>
			<input type="hidden" name="id" value="<?php echo intval($member->id ?? 0); ?>">
			<h2><?php esc_html_e('Details', 'fair-member-fees'); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="famefe-first"><?php esc_html_e('First name', 'fair-member-fees'); ?></label></th>
					<td><input type="text" id="famefe-first" name="first_name" class="regular-text" maxlength="100" value="<?php echo esc_attr($member->first_name ?? ''); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="famefe-last"><?php esc_html_e('Last name', 'fair-member-fees'); ?></label></th>
					<td><input type="text" id="famefe-last" name="last_name" class="regular-text" maxlength="100" value="<?php echo esc_attr($member->last_name ?? ''); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="famefe-email"><?php esc_html_e('Email', 'fair-member-fees'); ?></label></th>
					<td><input type="email" id="famefe-email" name="email" class="regular-text" value="<?php echo esc_attr($member->email ?? ''); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="famefe-user"><?php esc_html_e('User account', 'fair-member-fees'); ?></label></th>
					<td>
						<?php
						wp_dropdown_users([
							'name' => 'user_id',
							'id' => 'famefe-user',
							'selected' => intval($member->user_id ?? 0),
							'show_option_none' => __('— No account —', 'fair-member-fees'),
							'option_none_value' => 0,
							'show' => 'display_name_with_login',
						]);
						?>
						<p class="description"><?php esc_html_e('Members with an account can record their hours and pay online.', 'fair-member-fees'); ?></p>
					</td>
				</tr>
				<?php if ($is_new) : ?>
					<tr>
						<th scope="row"><label for="famefe-type"><?php esc_html_e('Membership type', 'fair-member-fees'); ?></label></th>
						<td>
							<select id="famefe-type" name="member_type">
								<?php foreach ($types as $key => $label) : ?>
									<option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></option>
								<?php endforeach; ?>
							</select>
							<p class="description"><?php esc_html_e('Regular members pay the membership fee, honorary members do not.', 'fair-member-fees'); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="famefe-since"><?php esc_html_e('Member since', 'fair-member-fees'); ?></label></th>
						<td><input type="date" id="famefe-since" name="member_since" required value="<?php echo esc_attr(famefe_today()); ?>"></td>
					</tr>
				<?php endif; ?>
				<tr>
					<th scope="row"><label for="famefe-note"><?php esc_html_e('Note', 'fair-member-fees'); ?></label></th>
					<td><textarea id="famefe-note" name="note" rows="3" class="large-text"><?php echo esc_textarea($member->note ?? ''); ?></textarea></td>
				</tr>
			</table>
			<?php submit_button($is_new ? __('Add member', 'fair-member-fees') : __('Save details', 'fair-member-fees')); ?>
		</form>

		<?php
		if ($is_new) {
			echo '</div>';
			return;
		}
		famefe_member_change_forms($member);
		famefe_member_history($member);
		?>
		<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="famefe-delete">
			<?php famefe_admin_form_fields('famefe_delete_member'); ?>
			<input type="hidden" name="id" value="<?php echo intval($member->id); ?>">
			<button type="submit" class="button-link button-link-delete" onclick="return confirm(this.dataset.confirm)" data-confirm="<?php esc_attr_e('Delete this member? Use this only for a member entered by mistake.', 'fair-member-fees'); ?>"><?php esc_html_e('Delete member entered by mistake', 'fair-member-fees'); ?></button>
		</form>
	</div>
	<?php
}

/**
 * Forms of the membership changes: type, end of membership, rejoining.
 */
function famefe_member_change_forms(object $member): void
{
	$types = famefe_member_types();
	$status_text = $member->status === 'active'
		/* translators: 1: membership type, 2: date. */
		? sprintf(__('%1$s member since %2$s', 'fair-member-fees'), $types[$member->member_type] ?? $member->member_type, famefe_format_date($member->member_since))
		/* translators: %s: date. */
		: sprintf(__('Membership ended on %s', 'fair-member-fees'), famefe_format_date($member->left_on));
	?>
	<div class="famefe-card">
		<h2><?php esc_html_e('Membership', 'fair-member-fees'); ?></h2>
		<p><strong><?php echo esc_html($status_text); ?></strong></p>
		<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="famefe-inline-form">
			<?php famefe_admin_form_fields('famefe_change_member'); ?>
			<input type="hidden" name="id" value="<?php echo intval($member->id); ?>">
			<?php if ($member->status === 'active') : ?>
				<label><?php esc_html_e('Change', 'fair-member-fees'); ?>
					<select name="change">
						<?php foreach ($types as $key => $label) : ?>
							<?php if ($key !== $member->member_type) : ?>
								<option value="type:<?php echo esc_attr($key); ?>">
									<?php
									/* translators: %s: membership type (Regular, Honorary). */
									echo esc_html(sprintf(__('Change type to: %s', 'fair-member-fees'), $label));
									?>
								</option>
							<?php endif; ?>
						<?php endforeach; ?>
						<option value="left"><?php esc_html_e('End the membership', 'fair-member-fees'); ?></option>
					</select>
				</label>
			<?php else : ?>
				<input type="hidden" name="change" value="rejoined">
				<span><?php esc_html_e('Rejoin', 'fair-member-fees'); ?></span>
			<?php endif; ?>
			<label><?php esc_html_e('Valid from', 'fair-member-fees'); ?> <input type="date" name="change_date" required value="<?php echo esc_attr(famefe_today()); ?>"></label>
			<label><?php esc_html_e('Note', 'fair-member-fees'); ?> <input type="text" name="note" maxlength="255"></label>
			<?php submit_button(__('Record the change', 'fair-member-fees'), 'secondary', 'submit', false); ?>
		</form>
	</div>
	<?php
}

/**
 * History table of a member: every change with who recorded it and when.
 */
function famefe_member_history(object $member): void
{
	$types = famefe_member_types();
	$changes = famefe_change_types();
	$value = fn(string $v) => $types[$v] ?? ($v === 'active' ? __('Active', 'fair-member-fees') : ($v === 'left' ? __('Former member', 'fair-member-fees') : $v));
	?>
	<div class="famefe-card">
		<h2><?php esc_html_e('History', 'fair-member-fees'); ?></h2>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e('Change date', 'fair-member-fees'); ?></th>
					<th><?php esc_html_e('Change', 'fair-member-fees'); ?></th>
					<th><?php esc_html_e('From', 'fair-member-fees'); ?></th>
					<th><?php esc_html_e('To', 'fair-member-fees'); ?></th>
					<th><?php esc_html_e('Note', 'fair-member-fees'); ?></th>
					<th><?php esc_html_e('Recorded by', 'fair-member-fees'); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach (famefe_member_log(intval($member->id)) as $entry) : ?>
					<tr>
						<td><?php echo esc_html(famefe_format_date($entry->change_date)); ?></td>
						<td><?php echo esc_html($changes[$entry->change_type] ?? $entry->change_type); ?></td>
						<td><?php echo esc_html($entry->old_value !== '' ? $value($entry->old_value) : ''); ?></td>
						<td><?php echo esc_html($entry->new_value !== '' ? $value($entry->new_value) : ''); ?></td>
						<td><?php echo esc_html($entry->note); ?></td>
						<td><?php echo esc_html(famefe_recorded_text(intval($entry->recorded_by), $entry->recorded_at)); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<?php
}

/**
 * Volunteer hours page: form to record hours, filters and the list; or the form of one entry.
 */
function famefe_page_hours(): void
{
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- navigation only.
	$edit = isset($_GET['action'], $_GET['id']) && $_GET['action'] === 'edit' ? famefe_get_hours_entry(absint($_GET['id'])) : null;
	// phpcs:enable
	$table = new Famefe_Hours_Table();
	$table->prepare_items();
	$filters = $table->filters;
	?>
	<div class="wrap famefe-admin">
		<h1><?php esc_html_e('Volunteer hours', 'fair-member-fees'); ?></h1>
		<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="famefe-card famefe-inline-form">
			<?php famefe_admin_form_fields('famefe_save_hours'); ?>
			<input type="hidden" name="id" value="<?php echo intval($edit->id ?? 0); ?>">
			<h2><?php echo $edit ? esc_html__('Edit hours', 'fair-member-fees') : esc_html__('Record hours for a member', 'fair-member-fees'); ?></h2>
			<label><?php esc_html_e('Member', 'fair-member-fees'); ?>
				<select name="member_id" required>
					<?php if ($edit && empty($edit->member_id)) : ?>
						<option value="0"><?php echo esc_html(famefe_user_name(intval($edit->user_id))); ?></option>
					<?php endif; ?>
					<?php famefe_member_options(intval($edit->member_id ?? 0)); ?>
				</select>
			</label>
			<label><?php esc_html_e('Date', 'fair-member-fees'); ?> <input type="date" name="work_date" required max="<?php echo esc_attr(famefe_today()); ?>" value="<?php echo esc_attr($edit->work_date ?? famefe_today()); ?>"></label>
			<label><?php esc_html_e('Hours', 'fair-member-fees'); ?> <input type="number" name="hours" required min="0.1" max="24" step="0.1" class="small-text" value="<?php echo esc_attr($edit->hours ?? ''); ?>"></label>
			<label class="famefe-grow"><?php esc_html_e('Work done', 'fair-member-fees'); ?> <input type="text" name="description" maxlength="255" value="<?php echo esc_attr($edit->description ?? ''); ?>"></label>
			<?php submit_button($edit ? __('Save', 'fair-member-fees') : __('Add hours', 'fair-member-fees'), 'primary', 'submit', false); ?>
			<?php if ($edit) : ?>
				<a href="<?php echo esc_url(famefe_admin_url('famefe-hours')); ?>"><?php esc_html_e('Cancel', 'fair-member-fees'); ?></a>
			<?php endif; ?>
		</form>

		<form method="get" class="famefe-filters">
			<input type="hidden" name="page" value="famefe-hours">
			<label><?php esc_html_e('From', 'fair-member-fees'); ?> <input type="date" name="from" value="<?php echo esc_attr($filters['start']); ?>"></label>
			<label><?php esc_html_e('To', 'fair-member-fees'); ?> <input type="date" name="to" value="<?php echo esc_attr($filters['end']); ?>"></label>
			<label><?php esc_html_e('Member', 'fair-member-fees'); ?>
				<select name="member">
					<option value="0"><?php esc_html_e('All', 'fair-member-fees'); ?></option>
					<?php famefe_member_options(intval($filters['member_id'])); ?>
				</select>
			</label>
			<?php submit_button(__('Filter', 'fair-member-fees'), 'secondary', '', false); ?>
			<span class="famefe-total">
				<?php
				/* translators: %s: number of hours. */
				echo esc_html(sprintf(__('Total: %s hours', 'fair-member-fees'), famefe_format_hours($table->total_hours)));
				?>
			</span>
		</form>
		<?php $table->display(); ?>
	</div>
	<?php
}

/**
 * Payments page: manual payment form, filter and the list.
 */
function famefe_page_payments(): void
{
	$table = new Famefe_Payments_Table();
	$table->prepare_items();
	$year = current_time('Y');
	?>
	<div class="wrap famefe-admin">
		<h1><?php esc_html_e('Payments', 'fair-member-fees'); ?></h1>
		<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="famefe-card famefe-inline-form">
			<?php famefe_admin_form_fields('famefe_save_payment'); ?>
			<h2><?php esc_html_e('Record a payment', 'fair-member-fees'); ?></h2>
			<p class="description famefe-full"><?php esc_html_e('Cash and bank payments. The period must be the same as in the Membership fees block, otherwise the block does not show the member as paid. Card payments are recorded automatically.', 'fair-member-fees'); ?></p>
			<label><?php esc_html_e('Member', 'fair-member-fees'); ?>
				<select name="member_id" required><?php famefe_member_options(0, false); ?></select>
			</label>
			<label><?php esc_html_e('Period from', 'fair-member-fees'); ?> <input type="date" name="period_start" required value="<?php echo esc_attr($year . '-01-01'); ?>"></label>
			<label><?php esc_html_e('to', 'fair-member-fees'); ?> <input type="date" name="period_end" required value="<?php echo esc_attr($year . '-12-31'); ?>"></label>
			<label>
				<?php
				/* translators: %s: currency code. */
				echo esc_html(sprintf(__('Amount (%s)', 'fair-member-fees'), famefe_settings('currency')));
				?>
				<input type="number" name="amount" required min="0" step="any" class="small-text">
			</label>
			<label><?php esc_html_e('Method', 'fair-member-fees'); ?>
				<select name="method">
					<option value="transfer"><?php echo esc_html(famefe_payment_methods()['transfer']); ?></option>
					<option value="cash"><?php echo esc_html(famefe_payment_methods()['cash']); ?></option>
				</select>
			</label>
			<label><?php esc_html_e('Paid on', 'fair-member-fees'); ?> <input type="date" name="paid_on" required value="<?php echo esc_attr(famefe_today()); ?>"></label>
			<label class="famefe-grow"><?php esc_html_e('Note', 'fair-member-fees'); ?> <input type="text" name="note" maxlength="255"></label>
			<?php submit_button(__('Record payment', 'fair-member-fees'), 'primary', 'submit', false); ?>
		</form>
		<form method="get" class="famefe-filters">
			<input type="hidden" name="page" value="famefe-payments">
			<label><?php esc_html_e('Member', 'fair-member-fees'); ?>
				<select name="member">
					<option value="0"><?php esc_html_e('All', 'fair-member-fees'); ?></option>
					<?php famefe_member_options(intval($table->filters['member_id'])); ?>
				</select>
			</label>
			<?php submit_button(__('Filter', 'fair-member-fees'), 'secondary', '', false); ?>
		</form>
		<?php $table->display(); ?>
	</div>
	<?php
}

/**
 * Settings registered with the Settings API.
 */
function famefe_register_settings(): void
{
	register_setting('famefe_settings', 'famefe_settings', [
		'type' => 'array',
		'sanitize_callback' => 'famefe_sanitize_settings',
		'default' => famefe_default_settings(),
	]);
}
add_action('admin_init', 'famefe_register_settings');

/**
 * Settings page.
 */
function famefe_page_settings(): void
{
	if (!current_user_can('manage_options')) {
		return;
	}
	$s = famefe_settings();
	$field = fn(string $key) => 'famefe_settings[' . $key . ']';
	?>
	<div class="wrap famefe-admin">
		<h1><?php esc_html_e('Fair Member Fees settings', 'fair-member-fees'); ?></h1>
		<?php settings_errors(); ?>
		<p><?php esc_html_e('The season (period), the base fee and the discount are set in the Membership fees block on your page.', 'fair-member-fees'); ?></p>
		<form method="post" action="options.php">
			<?php settings_fields('famefe_settings'); ?>
			<h2><?php esc_html_e('Money', 'fair-member-fees'); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="famefe-currency"><?php esc_html_e('Currency', 'fair-member-fees'); ?></label></th>
					<td>
						<input type="text" id="famefe-currency" name="<?php echo esc_attr($field('currency')); ?>" value="<?php echo esc_attr($s['currency']); ?>" maxlength="3" class="small-text" required pattern="[A-Za-z]{3}">
						<p class="description"><?php esc_html_e('Three-letter ISO code, e.g. CZK, EUR, USD.', 'fair-member-fees'); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="famefe-decimals"><?php esc_html_e('Decimal places', 'fair-member-fees'); ?></label></th>
					<td>
						<input type="number" id="famefe-decimals" name="<?php echo esc_attr($field('decimals')); ?>" value="<?php echo esc_attr((string) $s['decimals']); ?>" min="0" max="4" class="small-text">
						<p class="description"><?php esc_html_e('Fees are rounded to this number of decimals. Leave empty to use the usual number of the currency (CZK 0, EUR and USD 2).', 'fair-member-fees'); ?></p>
					</td>
				</tr>
			</table>

			<h2><?php esc_html_e('Stripe', 'fair-member-fees'); ?></h2>
			<p><?php esc_html_e('Members pay on the Stripe payment page. Fill in the secret key of the mode you use; without it the Membership fees block shows no payment button.', 'fair-member-fees'); ?></p>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e('Mode', 'fair-member-fees'); ?></th>
					<td>
						<label><input type="radio" name="<?php echo esc_attr($field('stripe_mode')); ?>" value="test" <?php checked($s['stripe_mode'], 'test'); ?>> <?php esc_html_e('Test (no real money)', 'fair-member-fees'); ?></label><br>
						<label><input type="radio" name="<?php echo esc_attr($field('stripe_mode')); ?>" value="live" <?php checked($s['stripe_mode'], 'live'); ?>> <?php esc_html_e('Live', 'fair-member-fees'); ?></label>
					</td>
				</tr>
				<?php
				$keys = [
					'stripe_test_secret' => __('Test secret key', 'fair-member-fees'),
					'stripe_test_webhook_secret' => __('Test webhook signing secret', 'fair-member-fees'),
					'stripe_live_secret' => __('Live secret key', 'fair-member-fees'),
					'stripe_live_webhook_secret' => __('Live webhook signing secret', 'fair-member-fees'),
				];
				foreach ($keys as $key => $label) :
					?>
					<tr>
						<th scope="row"><label for="famefe-<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></label></th>
						<td><input type="password" autocomplete="off" id="famefe-<?php echo esc_attr($key); ?>" name="<?php echo esc_attr($field($key)); ?>" value="<?php echo esc_attr($s[$key]); ?>" class="regular-text"></td>
					</tr>
				<?php endforeach; ?>
				<tr>
					<th scope="row"><?php esc_html_e('Webhook endpoint', 'fair-member-fees'); ?></th>
					<td>
						<code><?php echo esc_html(rest_url('famefe/v1/stripe-webhook')); ?></code>
						<p class="description"><?php esc_html_e('Add this address in Stripe (Developers → Webhooks) with the events checkout.session.completed and checkout.session.async_payment_succeeded, then copy its signing secret here. Payments are recorded even when a member closes the browser before returning to your site.', 'fair-member-fees'); ?></p>
					</td>
				</tr>
			</table>

			<h2><?php esc_html_e('Volunteer hours and names', 'fair-member-fees'); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e('Hours of non-members', 'fair-member-fees'); ?></th>
					<td><label><input type="checkbox" name="<?php echo esc_attr($field('hours_non_members')); ?>" value="1" <?php checked($s['hours_non_members'], 1); ?>> <?php esc_html_e('Logged-in users who are not members may record their hours too (they count only in the totals).', 'fair-member-fees'); ?></label></td>
				</tr>
				<tr>
					<th scope="row"><label for="famefe-names"><?php esc_html_e('Names in blocks', 'fair-member-fees'); ?></label></th>
					<td>
						<select id="famefe-names" name="<?php echo esc_attr($field('name_display')); ?>">
							<?php foreach (famefe_name_modes() as $mode => $label) : ?>
								<option value="<?php echo esc_attr($mode); ?>" <?php selected($s['name_display'], $mode); ?>><?php echo esc_html($label); ?></option>
							<?php endforeach; ?>
						</select>
						<p class="description"><?php esc_html_e('Default for all blocks; each block can override it.', 'fair-member-fees'); ?></p>
					</td>
				</tr>
			</table>

			<h2><?php esc_html_e('Uninstall', 'fair-member-fees'); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e('Delete data', 'fair-member-fees'); ?></th>
					<td><label><input type="checkbox" name="<?php echo esc_attr($field('delete_data')); ?>" value="1" <?php checked($s['delete_data'], 1); ?>> <?php esc_html_e('Delete all members, hours, payments and settings when the plugin is deleted.', 'fair-member-fees'); ?></label></td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>
	</div>
	<?php
}

/**
 * Labels of the name display modes.
 */
function famefe_name_modes(): array
{
	return [
		'full' => __('Full name (Jane Smith)', 'fair-member-fees'),
		'short' => __('First name and initial (Jane S.)', 'fair-member-fees'),
		'initials' => __('Initials (J. S.)', 'fair-member-fees'),
	];
}

/**
 * Help page: the blocks and how the fee is calculated.
 */
function famefe_page_help(): void
{
	$blocks = [
		[__('Membership fees', 'fair-member-fees'), __('The table of fees for a period. Set the period, the base fee and the optional discount in the block settings. Members pay their fee with the button; administrators and editors can record cash and bank payments right there.', 'fair-member-fees')],
		[__('Volunteer hours form', 'fair-member-fees'), __('Members record their own hours. Set the maximum hours per entry, how many days back a date may be, and whether a description is required.', 'fair-member-fees')],
		[__('Volunteer hours list', 'fair-member-fees'), __('Recorded hours for a period, all or only the visitor’s own.', 'fair-member-fees')],
		[__('Members list', 'fair-member-fees'), __('The membership register with the date of the last change and who recorded it; visible to logged-in users by default.', 'fair-member-fees')],
		[__('Fee calculation explained', 'fair-member-fees'), __('Explains the calculation on an example with your numbers.', 'fair-member-fees')],
	];
	?>
	<div class="wrap famefe-admin">
		<h1><?php esc_html_e('Fair Member Fees help', 'fair-member-fees'); ?></h1>
		<div class="famefe-card">
			<h2><?php esc_html_e('Getting started', 'fair-member-fees'); ?></h2>
			<ol>
				<li><?php esc_html_e('Add your members (Members → Add member) and link them to their user accounts.', 'fair-member-fees'); ?></li>
				<li><?php esc_html_e('Set the currency, and Stripe if you want online payments (Settings).', 'fair-member-fees'); ?></li>
				<li><?php esc_html_e('Add the blocks to your pages: search for "Fair Member Fees" in the block inserter.', 'fair-member-fees'); ?></li>
			</ol>
		</div>
		<div class="famefe-card">
			<h2><?php esc_html_e('Blocks', 'fair-member-fees'); ?></h2>
			<dl>
				<?php foreach ($blocks as [$name, $text]) : ?>
					<dt><strong><?php echo esc_html($name); ?></strong></dt>
					<dd><?php echo esc_html($text); ?></dd>
				<?php endforeach; ?>
			</dl>
		</div>
		<div class="famefe-card">
			<h2><?php esc_html_e('How the fee is calculated', 'fair-member-fees'); ?></h2>
			<p><?php esc_html_e('All regular members of the period together pay the base fee times their number (the expected total). Each member’s share of the volunteer hours of all regular members lowers the fee; what is collected above the expected total is taken off everybody equally, and no fee goes below zero. Honorary members pay nothing. With the optional discount, the given share of members with the lowest fees pays the given percentage less; nothing is moved to the others.', 'fair-member-fees'); ?></p>
		</div>
	</div>
	<?php
}
