<?php
/**
 * Server render of the "Membership fees" block: fee table of the period, totals, payment of the
 * logged-in member and (administrators and editors) the form for cash and bank payments.
 *
 * @var array $attributes Block attributes.
 *
 * @package fair-member-fees
 */

if (!defined('ABSPATH')) {
	exit;
}

$famefe_args = famefe_fees_block_args($attributes);
$famefe_block_id = famefe_block_id($attributes);
$famefe_fees = famefe_period_fees($famefe_args['start'], $famefe_args['end'], $famefe_args['base_fee'], $famefe_args['discount_share'], $famefe_args['discount_rate']);
$famefe_calc = $famefe_fees['calc'];
$famefe_totals = $famefe_calc['totals'];
$famefe_names = famefe_name_mode((string) $attributes['nameDisplay']);
$famefe_discount = $famefe_args['discount_share'] > 0 && $famefe_args['discount_rate'] > 0;
$famefe_types = famefe_member_types();
$famefe_me = famefe_get_member(get_current_user_id());
$famefe_my_id = $famefe_me ? intval($famefe_me->id) : 0;
$famefe_money = fn(float $amount) => famefe_format_money($amount);

// Columns: key => [label, sort type for assets/tables.js].
$famefe_columns = ['name' => [__('Name', 'fair-member-fees'), 'text']];
if ($attributes['showMemberType']) {
	$famefe_columns['type'] = [__('Type', 'fair-member-fees'), 'text'];
}
if ($attributes['showHours']) {
	$famefe_columns['hours'] = [__('Hours', 'fair-member-fees'), 'number'];
}
if ($attributes['showShare']) {
	$famefe_columns['share'] = [__('Share of hours', 'fair-member-fees'), 'number'];
}
if ($attributes['showGross']) {
	$famefe_columns['gross'] = [__('Unreduced fee', 'fair-member-fees'), 'number'];
}
if ($famefe_discount && $attributes['showDiscount']) {
	$famefe_columns['calculated'] = [__('Calculated fee', 'fair-member-fees'), 'number'];
	$famefe_columns['discount'] = [__('Discount', 'fair-member-fees'), 'number'];
}
$famefe_columns['fee'] = [__('Fee to pay', 'fair-member-fees'), 'number'];
if ($attributes['showPaidOn']) {
	$famefe_columns['paid'] = [__('Paid on', 'fair-member-fees'), 'text'];
}

// Rows: every cell is [text, raw value for sorting].
$famefe_rows = [];
$famefe_honorary = ['count' => 0, 'hours' => 0.0];
$famefe_paid_count = 0;
foreach ($famefe_fees['members'] as $famefe_id => $famefe_member) {
	$famefe_row = $famefe_calc['rows'][$famefe_id];
	$famefe_regular = $famefe_row['member_type'] === 'regular';
	$famefe_payment = $famefe_fees['payments'][$famefe_id] ?? null;
	if (!$famefe_regular) {
		$famefe_honorary['count']++;
		$famefe_honorary['hours'] += $famefe_row['hours'];
		if (!$attributes['showHonorary']) {
			continue;
		}
	} elseif ($famefe_payment) {
		$famefe_paid_count++;
	}
	$famefe_rows[] = [
		'current' => $famefe_id === $famefe_my_id && $attributes['highlightCurrent'],
		'name' => array_fill(0, 2, famefe_member_name($famefe_member, $famefe_names)),
		'type' => [$famefe_types[$famefe_row['member_type']] ?? '', $famefe_types[$famefe_row['member_type']] ?? ''],
		'hours' => [famefe_format_hours($famefe_row['hours']), $famefe_row['hours']],
		'share' => $famefe_regular ? [number_format_i18n($famefe_row['hours_share'] * 100, 1) . ' %', $famefe_row['hours_share']] : ['', ''],
		'gross' => $famefe_regular ? [$famefe_money($famefe_row['gross_fee']), $famefe_row['gross_fee']] : ['', ''],
		'calculated' => $famefe_regular ? [$famefe_money($famefe_row['calculated_fee']), $famefe_row['calculated_fee']] : ['', ''],
		'discount' => $famefe_regular && $famefe_row['discounted'] ? [$famefe_money($famefe_row['discount']), $famefe_row['discount']] : ['', 0],
		'fee' => $famefe_regular ? [$famefe_money($famefe_row['final_fee']), $famefe_row['final_fee']] : ['—', ''],
		'paid' => $famefe_payment ? [famefe_format_date($famefe_payment->paid_at), $famefe_payment->paid_at] : ['', ''],
	];
}

// Totals in the table footer: regular members, honorary members, others (non-members, former members), all.
$famefe_footer = [];
if ($attributes['showTotals'] && $famefe_rows) {
	$famefe_footer[] = [
		/* translators: %d: number of regular members. */
		'name' => sprintf(__('Regular members (%d)', 'fair-member-fees'), $famefe_totals['regular_count']),
		'hours' => famefe_format_hours($famefe_totals['hours_regular']),
		'share' => $famefe_totals['regular_count'] ? '100 %' : '',
		'gross' => $famefe_money($famefe_totals['gross_total']),
		'calculated' => $famefe_money($famefe_totals['calculated_total']),
		'discount' => $famefe_money($famefe_totals['discount_total']),
		'fee' => $famefe_money($famefe_totals['final_total']),
		/* translators: 1: number of members who paid, 2: number of regular members. */
		'paid' => sprintf(__('%1$d of %2$d', 'fair-member-fees'), $famefe_paid_count, $famefe_totals['regular_count']),
	];
	if ($famefe_honorary['count'] > 0) {
		$famefe_footer[] = [
			/* translators: %d: number of honorary members. */
			'name' => sprintf(__('Honorary members (%d)', 'fair-member-fees'), $famefe_honorary['count']),
			'hours' => famefe_format_hours($famefe_honorary['hours']),
		];
	}
	$famefe_other_hours = $famefe_totals['hours_other'] - $famefe_honorary['hours'];
	if ($famefe_other_hours > 0) {
		$famefe_footer[] = [
			'name' => __('Non-members and former members', 'fair-member-fees'),
			'hours' => famefe_format_hours($famefe_other_hours),
		];
	}
	if (count($famefe_footer) > 1) {
		$famefe_footer[] = [
			'name' => __('Total', 'fair-member-fees'),
			'hours' => famefe_format_hours($famefe_totals['hours_total']),
			'fee' => $famefe_money($famefe_totals['final_total']),
			'class' => 'famefe-total-row',
		];
	}
}

$famefe_period_text = sprintf(
	/* translators: 1: first day of the period, 2: last day of the period. */
	__('%1$s – %2$s', 'fair-member-fees'),
	famefe_format_date($famefe_args['start']),
	famefe_format_date($famefe_args['end'])
);
?>
<div <?php echo get_block_wrapper_attributes(['class' => 'famefe-block famefe-fees']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<?php famefe_block_notice($famefe_block_id); ?>

	<?php if ($attributes['summaryAbove']) : ?>
		<p class="famefe-fees__period">
			<?php
			echo esc_html(sprintf(
				/* translators: 1: period (from – to), 2: base fee with currency. */
				__('Membership fees for %1$s. Base fee: %2$s.', 'fair-member-fees'),
				$famefe_period_text,
				$famefe_money($famefe_args['base_fee'])
			));
			?>
		</p>
	<?php endif; ?>

	<?php if (!$famefe_rows) : ?>
		<p><?php esc_html_e('There are no members in this period.', 'fair-member-fees'); ?></p>
	<?php else : ?>
		<div class="famefe-table-wrap">
			<table class="famefe-table">
				<thead>
					<tr>
						<?php foreach ($famefe_columns as $famefe_key => [$famefe_label, $famefe_sort]) : ?>
							<th scope="col" class="famefe-col-<?php echo esc_attr($famefe_key); ?>" data-sort="<?php echo esc_attr($famefe_sort); ?>"><?php echo esc_html($famefe_label); ?></th>
						<?php endforeach; ?>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($famefe_rows as $famefe_row) : ?>
						<tr<?php echo $famefe_row['current'] ? ' class="famefe-current"' : ''; ?>>
							<?php foreach (array_keys($famefe_columns) as $famefe_key) : ?>
								<td class="famefe-col-<?php echo esc_attr($famefe_key); ?>" data-value="<?php echo esc_attr((string) $famefe_row[$famefe_key][1]); ?>"><?php echo esc_html($famefe_row[$famefe_key][0]); ?></td>
							<?php endforeach; ?>
						</tr>
					<?php endforeach; ?>
				</tbody>
				<?php if ($famefe_footer) : ?>
					<tfoot>
						<?php foreach ($famefe_footer as $famefe_total) : ?>
							<tr<?php echo isset($famefe_total['class']) ? ' class="' . esc_attr($famefe_total['class']) . '"' : ''; ?>>
								<?php foreach (array_keys($famefe_columns) as $famefe_key) : ?>
									<?php if ($famefe_key === 'name') : ?>
										<th scope="row"><?php echo esc_html($famefe_total['name']); ?></th>
									<?php else : ?>
										<td class="famefe-col-<?php echo esc_attr($famefe_key); ?>"><?php echo esc_html($famefe_total[$famefe_key] ?? ''); ?></td>
									<?php endif; ?>
								<?php endforeach; ?>
							</tr>
						<?php endforeach; ?>
					</tfoot>
				<?php endif; ?>
			</table>
		</div>
	<?php endif; ?>

	<?php
	// Payment of the logged-in member.
	$famefe_my_row = $famefe_my_id ? ($famefe_calc['rows'][$famefe_my_id] ?? null) : null;
	if ($famefe_my_row && $famefe_my_row['member_type'] === 'regular') :
		$famefe_my_payment = $famefe_fees['payments'][$famefe_my_id] ?? null;
		?>
		<div class="famefe-pay">
			<?php if ($famefe_my_payment) : ?>
				<p>
					<?php
					echo esc_html(sprintf(
						/* translators: 1: amount, 2: date. */
						__('You paid %1$s on %2$s. Thank you!', 'fair-member-fees'),
						famefe_format_money(floatval($famefe_my_payment->amount), $famefe_my_payment->currency),
						famefe_format_date($famefe_my_payment->paid_at)
					));
					?>
				</p>
			<?php elseif ($famefe_my_row['final_fee'] <= 0) : ?>
				<p><?php esc_html_e('You do not need to pay a membership fee for this period. Thank you for your work!', 'fair-member-fees'); ?></p>
			<?php else : ?>
				<p>
					<?php
					echo esc_html(sprintf(
						/* translators: %s: amount. */
						__('Your membership fee: %s', 'fair-member-fees'),
						$famefe_money($famefe_my_row['final_fee'])
					));
					?>
				</p>
				<?php if ($attributes['showPayButton'] && famefe_stripe_ready() && $famefe_block_id !== '') : ?>
					<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
						<?php famefe_block_form_fields('famefe_block_checkout', $famefe_block_id); ?>
						<button type="submit" class="wp-element-button famefe-button">
							<?php
							/* translators: %s: amount. */
							echo esc_html(sprintf(__('Pay %s online', 'fair-member-fees'), $famefe_money($famefe_my_row['final_fee'])));
							?>
						</button>
					</form>
				<?php endif; ?>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<?php
	// Why administrators and editors may not see the payment button.
	if ($attributes['showPayButton'] && famefe_can_manage()) :
		if (!famefe_stripe_ready()) {
			$famefe_hint = __('The online payment button is hidden: Stripe is not set up yet (Member Fees → Settings → Stripe secret key).', 'fair-member-fees');
		} elseif (!$famefe_my_row || $famefe_my_row['member_type'] !== 'regular') {
			$famefe_hint = __('The online payment button is shown only to logged-in regular members who have a fee to pay.', 'fair-member-fees');
		} else {
			$famefe_hint = '';
		}
		if ($famefe_hint !== '') :
			?>
			<p class="famefe-hint"><?php echo esc_html($famefe_hint); ?> <?php esc_html_e('(Visible only to administrators and editors.)', 'fair-member-fees'); ?></p>
		<?php endif; ?>
	<?php endif; ?>

	<?php
	// Cash and bank payments recorded by administrators and editors.
	if (famefe_can_manage() && $famefe_block_id !== '') :
		$famefe_unpaid = [];
		foreach ($famefe_fees['members'] as $famefe_id => $famefe_member) {
			$famefe_row = $famefe_calc['rows'][$famefe_id];
			if ($famefe_row['member_type'] === 'regular' && !isset($famefe_fees['payments'][$famefe_id])) {
				$famefe_unpaid[$famefe_id] = famefe_member_name($famefe_member) . ' – ' . $famefe_money($famefe_row['final_fee']);
			}
		}
		if ($famefe_unpaid) :
			?>
			<details class="famefe-manage">
				<summary><?php esc_html_e('Record a cash or bank payment (administrators and editors)', 'fair-member-fees'); ?></summary>
				<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="famefe-form">
					<?php famefe_block_form_fields('famefe_block_payment', $famefe_block_id); ?>
					<p>
						<label for="famefe-pay-member-<?php echo esc_attr($famefe_block_id); ?>"><?php esc_html_e('Member and fee', 'fair-member-fees'); ?></label>
						<select id="famefe-pay-member-<?php echo esc_attr($famefe_block_id); ?>" name="member_id" required>
							<?php foreach ($famefe_unpaid as $famefe_id => $famefe_label) : ?>
								<option value="<?php echo intval($famefe_id); ?>"><?php echo esc_html($famefe_label); ?></option>
							<?php endforeach; ?>
						</select>
					</p>
					<p>
						<label for="famefe-pay-method-<?php echo esc_attr($famefe_block_id); ?>"><?php esc_html_e('Method', 'fair-member-fees'); ?></label>
						<select id="famefe-pay-method-<?php echo esc_attr($famefe_block_id); ?>" name="method">
							<option value="transfer"><?php echo esc_html(famefe_payment_methods()['transfer']); ?></option>
							<option value="cash"><?php echo esc_html(famefe_payment_methods()['cash']); ?></option>
						</select>
					</p>
					<p>
						<label for="famefe-pay-date-<?php echo esc_attr($famefe_block_id); ?>"><?php esc_html_e('Paid on', 'fair-member-fees'); ?></label>
						<input type="date" id="famefe-pay-date-<?php echo esc_attr($famefe_block_id); ?>" name="paid_on" required value="<?php echo esc_attr(famefe_today()); ?>">
					</p>
					<p><button type="submit" class="wp-element-button famefe-button"><?php esc_html_e('Record payment', 'fair-member-fees'); ?></button></p>
					<p class="famefe-hint"><?php esc_html_e('Only administrators and editors see this form. The amount is the calculated fee of the member.', 'fair-member-fees'); ?></p>
				</form>
			</details>
		<?php endif; ?>
	<?php endif; ?>
</div>
