<?php
/**
 * Server render of the "Fee calculation explained" block: the method on an example
 * calculated by the same function as the real fees (famefe_calculate_fees()).
 *
 * @var array $attributes Block attributes.
 *
 * @package fair-member-fees
 */

if (!defined('ABSPATH')) {
	exit;
}

$famefe_parse = function (string $list): array {
	$out = [];
	// Commas, semicolons and spaces separate the numbers; decimals use a dot (2.5).
	foreach (preg_split('/[\s,;]+/', $list) as $famefe_part) {
		if ($famefe_part !== '' && is_numeric($famefe_part)) {
			$out[] = max(0, min(9999, floatval($famefe_part)));
		}
	}
	return array_slice($out, 0, 30);
};
$famefe_regular = $famefe_parse((string) $attributes['regularHours']) ?: [0, 10, 15, 20, 25];
$famefe_honorary = $famefe_parse((string) $attributes['honoraryHours']);
$famefe_base = max(0, floatval($attributes['baseFee']));
$famefe_discount = !empty($attributes['discountEnabled']);

$famefe_rows = [];
$famefe_labels = [];
foreach ($famefe_regular as $famefe_i => $famefe_hours) {
	$famefe_rows[] = ['member_id' => $famefe_i + 1, 'member_type' => 'regular', 'hours' => $famefe_hours];
}
foreach ($famefe_honorary as $famefe_hours) {
	$famefe_rows[] = ['member_id' => count($famefe_rows) + 1, 'member_type' => 'honorary', 'hours' => $famefe_hours];
}
$famefe_calc = famefe_calculate_fees($famefe_rows, $famefe_base, [
	'decimals' => intval(famefe_settings('decimals')),
	'discount_share' => $famefe_discount ? floatval($attributes['discountShare']) : 0,
	'discount_rate' => $famefe_discount ? floatval($attributes['discountRate']) : 0,
]);
$famefe_t = $famefe_calc['totals'];
$famefe_money = fn(float $amount) => famefe_format_money($amount);
$famefe_types = famefe_member_types();
$famefe_has_discount = $famefe_t['discount_share'] > 0 && $famefe_t['discount_rate'] > 0;
/* translators: %d: number of the member in the example. */
$famefe_member_label = fn(int $n) => sprintf(__('Member %d', 'fair-member-fees'), $n);
?>
<div <?php echo get_block_wrapper_attributes(['class' => 'famefe-block famefe-explanation']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<p><?php esc_html_e('The membership fee depends on how much each member volunteered for the club, measured in the recorded hours. Members only need to record their hours; the fees are calculated from them. The following example shows how.', 'fair-member-fees'); ?></p>

	<h4><?php esc_html_e('1. Members and their hours', 'fair-member-fees'); ?></h4>
	<div class="famefe-table-wrap">
		<table class="famefe-table">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e('Member', 'fair-member-fees'); ?></th>
					<th scope="col"><?php esc_html_e('Type', 'fair-member-fees'); ?></th>
					<th scope="col" class="famefe-col-hours"><?php esc_html_e('Hours', 'fair-member-fees'); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($famefe_calc['rows'] as $famefe_id => $famefe_row) : ?>
					<tr>
						<td><?php echo esc_html($famefe_member_label($famefe_id)); ?></td>
						<td><?php echo esc_html($famefe_types[$famefe_row['member_type']]); ?></td>
						<td class="famefe-col-hours"><?php echo esc_html(famefe_format_hours($famefe_row['hours'])); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
			<tfoot>
				<tr>
					<th scope="row" colspan="2"><?php esc_html_e('Total', 'fair-member-fees'); ?></th>
					<td class="famefe-col-hours"><?php echo esc_html(famefe_format_hours($famefe_t['hours_total'])); ?></td>
				</tr>
			</tfoot>
		</table>
	</div>
	<p>
		<?php
		echo esc_html(sprintf(
			/* translators: 1: number of regular members, 2: number of honorary members. */
			__('There are %1$d regular members and %2$d honorary members. Honorary members do not pay a membership fee, and their hours do not count in the calculation.', 'fair-member-fees'),
			$famefe_t['regular_count'],
			$famefe_t['honorary_count']
		));
		?>
	</p>

	<h4><?php esc_html_e('2. Expected total', 'fair-member-fees'); ?></h4>
	<p>
		<?php
		echo esc_html(sprintf(
			/* translators: 1: base fee, 2: number of regular members, 3: base fee again, 4: expected total. */
			__('The base fee is %1$s. Together, the %2$d regular members are expected to pay %3$s × %2$d = %4$s.', 'fair-member-fees'),
			$famefe_money($famefe_base),
			$famefe_t['regular_count'],
			$famefe_money($famefe_base),
			$famefe_money($famefe_t['expected_total'])
		));
		?>
	</p>

	<h4><?php esc_html_e('3. Fee of each member', 'fair-member-fees'); ?></h4>
	<p>
		<?php
		echo esc_html(sprintf(
			/* translators: %s: hours of all regular members. */
			__('Each regular member’s share of the hours of all regular members (%s hours) lowers the unreduced fee: unreduced fee = expected total × (1 − share of hours).', 'fair-member-fees'),
			famefe_format_hours($famefe_t['hours_regular'])
		));
		?>
		<?php
		echo esc_html(sprintf(
			/* translators: 1: sum of the unreduced fees, 2: expected total, 3: amount taken off each fee. */
			__('The unreduced fees add up to %1$s, more than the expected total of %2$s. The difference is shared equally: each fee is lowered by %3$s, so all fees together give the expected total. No fee goes below zero; if one would, that member pays nothing and the rest is shared by the others.', 'fair-member-fees'),
			$famefe_money($famefe_t['gross_total']),
			$famefe_money($famefe_t['expected_total']),
			$famefe_money($famefe_t['reduction_each'])
		));
		?>
	</p>
	<div class="famefe-table-wrap">
		<table class="famefe-table">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e('Member', 'fair-member-fees'); ?></th>
					<th scope="col" class="famefe-col-hours"><?php esc_html_e('Hours', 'fair-member-fees'); ?></th>
					<th scope="col"><?php esc_html_e('Share of hours', 'fair-member-fees'); ?></th>
					<th scope="col"><?php esc_html_e('Unreduced fee', 'fair-member-fees'); ?></th>
					<th scope="col"><?php esc_html_e('Calculated fee', 'fair-member-fees'); ?></th>
					<?php if ($famefe_has_discount) : ?>
						<th scope="col"><?php esc_html_e('Discount', 'fair-member-fees'); ?></th>
						<th scope="col"><?php esc_html_e('Fee to pay', 'fair-member-fees'); ?></th>
					<?php endif; ?>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($famefe_calc['rows'] as $famefe_id => $famefe_row) : ?>
					<?php
					if ($famefe_row['member_type'] !== 'regular') {
						continue;
					}
					?>
					<tr>
						<td><?php echo esc_html($famefe_member_label($famefe_id)); ?></td>
						<td class="famefe-col-hours"><?php echo esc_html(famefe_format_hours($famefe_row['hours'])); ?></td>
						<td><?php echo esc_html(number_format_i18n($famefe_row['hours_share'] * 100, 1) . ' %'); ?></td>
						<td><?php echo esc_html($famefe_money($famefe_row['gross_fee'])); ?></td>
						<td><?php echo esc_html($famefe_money($famefe_row['calculated_fee'])); ?></td>
						<?php if ($famefe_has_discount) : ?>
							<td><?php echo esc_html($famefe_row['discounted'] ? $famefe_money($famefe_row['discount']) : ''); ?></td>
							<td><?php echo esc_html($famefe_money($famefe_row['final_fee'])); ?></td>
						<?php endif; ?>
					</tr>
				<?php endforeach; ?>
			</tbody>
			<tfoot>
				<tr>
					<th scope="row"><?php esc_html_e('Total', 'fair-member-fees'); ?></th>
					<td class="famefe-col-hours"><?php echo esc_html(famefe_format_hours($famefe_t['hours_regular'])); ?></td>
					<td>100 %</td>
					<td><?php echo esc_html($famefe_money($famefe_t['gross_total'])); ?></td>
					<td><?php echo esc_html($famefe_money($famefe_t['calculated_total'])); ?></td>
					<?php if ($famefe_has_discount) : ?>
						<td><?php echo esc_html($famefe_money($famefe_t['discount_total'])); ?></td>
						<td><?php echo esc_html($famefe_money($famefe_t['final_total'])); ?></td>
					<?php endif; ?>
				</tr>
			</tfoot>
		</table>
	</div>

	<?php if ($famefe_has_discount) : ?>
		<h4><?php esc_html_e('4. Discount', 'fair-member-fees'); ?></h4>
		<p>
			<?php
			echo esc_html(sprintf(
				/* translators: 1: share of members in percent, 2: discount in percent, 3: number of members with the discount. */
				__('The %1$s %% of regular members with the lowest calculated fees pay %2$s %% less (here %3$d members; members with the same fee as the last of them get it too). The discount is not added to the fees of the others, so the club collects less than the expected total.', 'fair-member-fees'),
				number_format_i18n($famefe_t['discount_share']),
				number_format_i18n($famefe_t['discount_rate']),
				$famefe_t['discounted_count']
			));
			?>
		</p>
	<?php endif; ?>

	<p><?php esc_html_e('Whoever does not volunteer pays more than the base fee: paying instead of working should never be the cheaper choice.', 'fair-member-fees'); ?></p>
</div>
