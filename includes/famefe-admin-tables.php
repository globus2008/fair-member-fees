<?php
/**
 * List tables of the admin pages.
 *
 * @package fair-member-fees
 */

if (!defined('ABSPATH')) {
	exit;
}

if (!class_exists('WP_List_Table')) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Members: filter by status (views), search, last change with who recorded it.
 */
class Famefe_Members_Table extends WP_List_Table
{
	private string $status = 'active';
	private array $last = [];

	public function __construct()
	{
		parent::__construct(['singular' => 'member', 'plural' => 'members', 'ajax' => false]);
	}

	public function get_columns(): array
	{
		return [
			'name' => __('Name', 'fair-member-fees'),
			'member_type' => __('Type', 'fair-member-fees'),
			'member_since' => __('Member since', 'fair-member-fees'),
			'last_change' => __('Last change', 'fair-member-fees'),
			'change_date' => __('Change date', 'fair-member-fees'),
			'recorded' => __('Recorded by', 'fair-member-fees'),
			'account' => __('User account', 'fair-member-fees'),
			'email' => __('Email', 'fair-member-fees'),
		];
	}

	public function prepare_items(): void
	{
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- list filters only.
		$status = isset($_GET['status']) ? sanitize_key(wp_unslash($_GET['status'])) : 'active';
		$search = isset($_REQUEST['s']) ? sanitize_text_field(wp_unslash($_REQUEST['s'])) : '';
		// phpcs:enable
		$this->status = in_array($status, ['active', 'left', 'all'], true) ? $status : 'active';
		$this->items = famefe_get_members(['status' => $this->status, 'search' => $search]);
		$this->last = famefe_last_changes();
		$this->_column_headers = [$this->get_columns(), [], []];
	}

	protected function get_views(): array
	{
		$counts = famefe_count_members();
		$labels = [
			'active' => __('Members', 'fair-member-fees'),
			'left' => __('Former members', 'fair-member-fees'),
			'all' => __('All', 'fair-member-fees'),
		];
		$views = [];
		foreach ($labels as $status => $label) {
			$views[$status] = sprintf(
				'<a href="%1$s"%2$s>%3$s <span class="count">(%4$d)</span></a>',
				esc_url(famefe_admin_url('famefe-members', ['status' => $status])),
				$this->status === $status ? ' class="current" aria-current="page"' : '',
				esc_html($label),
				$counts[$status]
			);
		}
		return $views;
	}

	public function no_items(): void
	{
		esc_html_e('No members found.', 'fair-member-fees');
	}

	protected function column_name($item): string
	{
		$url = famefe_admin_url('famefe-members', ['action' => 'edit', 'id' => $item->id]);
		$name = famefe_member_name($item) ?: __('(no name)', 'fair-member-fees');
		return sprintf('<strong><a href="%1$s">%2$s</a></strong>', esc_url($url), esc_html($name))
			. $this->row_actions(['edit' => sprintf('<a href="%s">%s</a>', esc_url($url), esc_html__('Edit and history', 'fair-member-fees'))]);
	}

	protected function column_member_type($item): string
	{
		if ($item->status === 'left') {
			return esc_html__('Former member', 'fair-member-fees');
		}
		return esc_html(famefe_member_types()[$item->member_type] ?? $item->member_type);
	}

	protected function column_member_since($item): string
	{
		return esc_html(famefe_format_date($item->member_since));
	}

	protected function column_last_change($item): string
	{
		$entry = $this->last[intval($item->id)] ?? null;
		return $entry ? esc_html(famefe_change_types()[$entry->change_type] ?? $entry->change_type) : '';
	}

	protected function column_change_date($item): string
	{
		$entry = $this->last[intval($item->id)] ?? null;
		return $entry ? esc_html(famefe_format_date($entry->change_date)) : '';
	}

	protected function column_recorded($item): string
	{
		$entry = $this->last[intval($item->id)] ?? null;
		return $entry ? esc_html(famefe_recorded_text(intval($entry->recorded_by), $entry->recorded_at)) : '';
	}

	protected function column_account($item): string
	{
		$user = $item->user_id ? get_userdata(intval($item->user_id)) : false;
		return $user ? sprintf('<a href="%1$s">%2$s</a>', esc_url(get_edit_user_link($user->ID)), esc_html($user->user_login)) : '—';
	}

	protected function column_email($item): string
	{
		return $item->email ? sprintf('<a href="mailto:%1$s">%2$s</a>', esc_attr($item->email), esc_html($item->email)) : '';
	}
}

/**
 * Volunteer hours with filters (period, member) and paging.
 */
class Famefe_Hours_Table extends WP_List_Table
{
	public array $filters = ['start' => '', 'end' => '', 'member_id' => 0];
	public float $total_hours = 0;

	public function __construct()
	{
		parent::__construct(['singular' => 'hours', 'plural' => 'hours', 'ajax' => false]);
	}

	public function get_columns(): array
	{
		return [
			'work_date' => __('Date', 'fair-member-fees'),
			'name' => __('Name', 'fair-member-fees'),
			'hours' => __('Hours', 'fair-member-fees'),
			'description' => __('Work done', 'fair-member-fees'),
			'recorded' => __('Recorded by', 'fair-member-fees'),
		];
	}

	public function prepare_items(): void
	{
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- list filters only.
		$this->filters = [
			'start' => famefe_valid_date(isset($_GET['from']) ? sanitize_text_field(wp_unslash($_GET['from'])) : ''),
			'end' => famefe_valid_date(isset($_GET['to']) ? sanitize_text_field(wp_unslash($_GET['to'])) : ''),
			'member_id' => isset($_GET['member']) ? absint($_GET['member']) : 0,
		];
		// phpcs:enable
		$per_page = 50;
		$summary = famefe_hours_summary($this->filters);
		$this->total_hours = $summary['hours'];
		$this->items = famefe_get_hours($this->filters + ['limit' => $per_page, 'offset' => ($this->get_pagenum() - 1) * $per_page]);
		$this->set_pagination_args(['total_items' => $summary['count'], 'per_page' => $per_page]);
		$this->_column_headers = [$this->get_columns(), [], []];
	}

	public function no_items(): void
	{
		esc_html_e('No hours recorded.', 'fair-member-fees');
	}

	protected function column_work_date($item): string
	{
		$edit = famefe_admin_url('famefe-hours', ['action' => 'edit', 'id' => $item->id]);
		$delete = wp_nonce_url(add_query_arg(['action' => 'famefe_delete_hours', 'id' => $item->id], admin_url('admin-post.php')), 'famefe_delete_hours');
		return esc_html(famefe_format_date($item->work_date)) . $this->row_actions([
			'edit' => sprintf('<a href="%s">%s</a>', esc_url($edit), esc_html__('Edit', 'fair-member-fees')),
			'delete' => sprintf(
				'<a href="%1$s" class="submitdelete" onclick="return confirm(this.dataset.confirm)" data-confirm="%2$s">%3$s</a>',
				esc_url($delete),
				esc_attr__('Delete these hours?', 'fair-member-fees'),
				esc_html__('Delete', 'fair-member-fees')
			),
		]);
	}

	protected function column_name($item): string
	{
		$name = famefe_hours_name($item, 'full');
		return esc_html(empty($item->member_id) ? sprintf(
			/* translators: %s: name of a user who is not a member. */
			__('%s (not a member)', 'fair-member-fees'),
			$name
		) : $name);
	}

	protected function column_hours($item): string
	{
		return esc_html(famefe_format_hours(floatval($item->hours)));
	}

	protected function column_description($item): string
	{
		return esc_html($item->description);
	}

	protected function column_recorded($item): string
	{
		return esc_html(famefe_recorded_text(intval($item->recorded_by), $item->recorded_at));
	}
}

/**
 * Payments with a member filter and paging.
 */
class Famefe_Payments_Table extends WP_List_Table
{
	public array $filters = ['member_id' => 0];

	public function __construct()
	{
		parent::__construct(['singular' => 'payment', 'plural' => 'payments', 'ajax' => false]);
	}

	public function get_columns(): array
	{
		return [
			'paid_at' => __('Paid on', 'fair-member-fees'),
			'name' => __('Member', 'fair-member-fees'),
			'period' => __('Period', 'fair-member-fees'),
			'amount' => __('Amount', 'fair-member-fees'),
			'method' => __('Method', 'fair-member-fees'),
			'note' => __('Note', 'fair-member-fees'),
			'recorded' => __('Recorded by', 'fair-member-fees'),
		];
	}

	public function prepare_items(): void
	{
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- list filter only.
		$this->filters['member_id'] = isset($_GET['member']) ? absint($_GET['member']) : 0;
		$per_page = 50;
		$this->items = famefe_get_payments($this->filters + ['limit' => $per_page, 'offset' => ($this->get_pagenum() - 1) * $per_page]);
		$this->set_pagination_args(['total_items' => famefe_get_payments($this->filters, true), 'per_page' => $per_page]);
		$this->_column_headers = [$this->get_columns(), [], []];
	}

	public function no_items(): void
	{
		esc_html_e('No payments recorded.', 'fair-member-fees');
	}

	protected function column_paid_at($item): string
	{
		$delete = wp_nonce_url(add_query_arg(['action' => 'famefe_delete_payment', 'id' => $item->id], admin_url('admin-post.php')), 'famefe_delete_payment');
		return esc_html(famefe_format_date($item->paid_at)) . $this->row_actions([
			'delete' => sprintf(
				'<a href="%1$s" class="submitdelete" onclick="return confirm(this.dataset.confirm)" data-confirm="%2$s">%3$s</a>',
				esc_url($delete),
				esc_attr__('Delete this payment? The member will be shown as unpaid.', 'fair-member-fees'),
				esc_html__('Delete', 'fair-member-fees')
			),
		]);
	}

	protected function column_name($item): string
	{
		return esc_html(famefe_person_name((string) $item->first_name, (string) $item->last_name));
	}

	protected function column_period($item): string
	{
		return esc_html(famefe_format_date($item->period_start) . ' – ' . famefe_format_date($item->period_end));
	}

	protected function column_amount($item): string
	{
		return esc_html(famefe_format_money(floatval($item->amount), $item->currency));
	}

	protected function column_method($item): string
	{
		$label = famefe_payment_methods()[$item->method] ?? $item->method;
		if ($item->method === 'stripe' && $item->stripe_session_id) {
			return sprintf('%1$s<br><small><code>%2$s</code></small>', esc_html($label), esc_html(substr($item->stripe_session_id, 0, 24) . '…'));
		}
		return esc_html($label);
	}

	protected function column_note($item): string
	{
		return esc_html($item->note);
	}

	protected function column_recorded($item): string
	{
		return esc_html(famefe_recorded_text(intval($item->recorded_by), $item->recorded_at));
	}
}
