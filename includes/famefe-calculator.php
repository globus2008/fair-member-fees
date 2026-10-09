<?php
/**
 * Calculation of the membership fees. Pure functions without database access.
 *
 * Method (all regular members of the period together pay the expected total):
 * - expected_total = base_fee × regular_count
 * - hours_share    = hours of the member / hours of all regular members
 * - gross_fee      = expected_total × (1 − hours_share)
 * - calculated_fee = gross_fee − (Σ gross_fee − expected_total) / regular_count
 * A fee never goes below 0: such a member pays 0 and the same formula is applied again to the
 * others (their Σ gross_fee and count), so the calculated fees always add up to expected_total.
 * Without any hours every regular member pays the base fee (the formulas give exactly that).
 * Honorary members pay nothing; their hours (and hours of non-members) are only counted in the totals.
 *
 * Optional discount: the regular members with the lowest calculated fees (discount_share % of them,
 * rounded up; ties with the last one included) pay discount_rate % less. The discount only reduces,
 * nothing is moved to the other members.
 *
 * @package fair-member-fees
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Calculate the fees.
 *
 * @param array $rows     List of ['member_id' => int, 'member_type' => 'regular'|'honorary', 'hours' => float].
 * @param float $base_fee Base fee of a regular member.
 * @param array $opts     'discount_share' (0-100 %), 'discount_rate' (0-100 %), 'decimals' (rounding of the results),
 *                        'other_hours' (hours of people who are not members).
 * @return array ['rows' => [member_id => row], 'totals' => [...]]. Every row gets hours, hours_share, gross_fee,
 *               calculated_fee, discounted, discount and final_fee (honorary members: zeros, hours_share null).
 */
function famefe_calculate_fees(array $rows, float $base_fee, array $opts = []): array
{
	$decimals = max(0, intval($opts['decimals'] ?? 0));
	$share = min(100, max(0, floatval($opts['discount_share'] ?? 0)));
	$rate = min(100, max(0, floatval($opts['discount_rate'] ?? 0)));
	$base_fee = max(0, $base_fee);

	$regular = [];
	$hours_total = max(0, floatval($opts['other_hours'] ?? 0));
	$hours_regular = 0.0;
	foreach ($rows as $row) {
		$hours = max(0, floatval($row['hours'] ?? 0));
		$hours_total += $hours;
		if (($row['member_type'] ?? '') === 'regular') {
			$regular[] = intval($row['member_id']);
			$hours_regular += $hours;
		}
	}
	$count = count($regular);
	$expected = $base_fee * $count;

	$result = [];
	$gross_total = 0.0;
	foreach ($rows as $row) {
		$id = intval($row['member_id']);
		$is_regular = in_array($id, $regular, true);
		$hours = max(0, floatval($row['hours'] ?? 0));
		$hours_share = $is_regular ? ($hours_regular > 0 ? $hours / $hours_regular : 0.0) : null;
		$gross = $is_regular ? $expected * (1 - $hours_share) : 0.0;
		$gross_total += $gross;
		$result[$id] = [
			'member_id' => $id,
			'member_type' => $is_regular ? 'regular' : 'honorary',
			'hours' => $hours,
			'hours_share' => $hours_share,
			'gross_fee' => $gross,
			'calculated_fee' => 0.0,
			'discounted' => false,
			'discount' => 0.0,
			'final_fee' => 0.0,
		];
	}

	// What the gross fees collect above the expected total is taken off everybody equally.
	// A member whose fee would go below zero pays 0 and the rest is shared by the others,
	// so the regular members always pay the expected total together.
	$paying = $regular;
	do {
		$gross_paying = 0.0;
		foreach ($paying as $id) {
			$gross_paying += $result[$id]['gross_fee'];
		}
		$surplus_each = $paying ? ($gross_paying - $expected) / count($paying) : 0.0;
		$below_zero = array_values(array_filter($paying, fn($id) => $result[$id]['gross_fee'] - $surplus_each < 0));
		$paying = array_values(array_diff($paying, $below_zero));
	} while ($below_zero && $paying);
	foreach ($paying as $id) {
		$result[$id]['calculated_fee'] = max(0.0, $result[$id]['gross_fee'] - $surplus_each);
	}

	// Discount for the lowest calculated fees (compared as displayed, so equal amounts tie).
	$discounted = [];
	if ($count > 0 && $share > 0 && $rate > 0) {
		$fees = [];
		foreach ($regular as $id) {
			$fees[$id] = round($result[$id]['calculated_fee'], $decimals);
		}
		asort($fees);
		$take = (int) ceil($count * $share / 100);
		$limit = array_values($fees)[$take - 1];
		foreach ($fees as $id => $fee) {
			if ($fee <= $limit) {
				$discounted[] = $id;
			}
		}
	}

	$totals = [
		'regular_count' => $count,
		'honorary_count' => count($rows) - $count,
		'base_fee' => $base_fee,
		'expected_total' => $expected,
		'hours_total' => $hours_total,
		'hours_regular' => $hours_regular,
		'hours_other' => $hours_total - $hours_regular,
		'gross_total' => $gross_total,
		// Amount taken off each unreduced fee (of the members who pay something).
		'reduction_each' => round($surplus_each ?? 0.0, $decimals),
		'calculated_total' => 0.0,
		'discounted_count' => count($discounted),
		'discount_total' => 0.0,
		'final_total' => 0.0,
		'discount_share' => $share,
		'discount_rate' => $rate,
	];
	foreach ($regular as $id) {
		$calculated = round($result[$id]['calculated_fee'], $decimals);
		$final = $calculated;
		if (in_array($id, $discounted, true)) {
			$final = round($result[$id]['calculated_fee'] * (1 - $rate / 100), $decimals);
			$result[$id]['discounted'] = true;
		}
		$result[$id]['calculated_fee'] = $calculated;
		$result[$id]['discount'] = round($calculated - $final, $decimals);
		$result[$id]['final_fee'] = $final;
		$totals['calculated_total'] += $calculated;
		$totals['discount_total'] += $result[$id]['discount'];
		$totals['final_total'] += $final;
	}
	foreach ($result as &$row) {
		$row['gross_fee'] = round($row['gross_fee'], $decimals);
	}
	unset($row);
	$totals['gross_total'] = round($gross_total, $decimals);

	return ['rows' => $result, 'totals' => $totals];
}
