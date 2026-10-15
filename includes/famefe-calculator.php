<?php
/**
 * Calculation of the membership fees. Pure functions without database access.
 *
 * Method:
 * - expected_total = base_fee × regular_count
 * - hours_share    = hours of the member / hours of all regular members
 * - gross_fee      = expected_total × (1 − hours_share)
 * - A regular member without any hours in the period always pays 2 × base_fee (owner 2026-10-10).
 * - The members with hours share the rest: calculated_fee = gross_fee − k, where k makes their fees add up
 *   to expected_total − 2 × base_fee × (members without hours). Without any fee below zero this is exactly
 *   the original formula gross_fee − (Σ gross_fee − expected_total) / regular_count, which itself gives
 *   2 × base_fee for a member without hours.
 * - A fee never goes below 0: such a member pays 0 and k is computed again for the others.
 *   The regular members then pay expected_total together, except when the members without hours
 *   alone pay more than that (their double fee is never reduced).
 * Honorary members pay nothing; their hours (and hours of non-members) are only counted in the totals.
 *
 * Optional discount: the regular members with the lowest calculated fees (discount_share % of all regular
 * members, rounded up; ties with the last one included) pay discount_rate % less. Members without hours
 * never get it. The discount only reduces, nothing is moved to the other members.
 * Instead of the rate, a discount_target can be given: the total that the regular members should pay together.
 * The rate is then calculated for the same members, and the rounded fees are adjusted by the smallest unit
 * so that they add up to the target exactly (members with equal fees always stay equal, so a tie can leave
 * a small difference). A target the calculated fees do not exceed means no discount; a target that even
 * a 100 % discount cannot reach leaves the discounted members at zero.
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
 * @param array $opts     'discount_share' (0-100 %), 'discount_rate' (0-100 %), 'discount_target' (total to collect,
 *                        replaces discount_rate; null = not used), 'decimals' (rounding of the results),
 *                        'other_hours' (hours of people who are not members).
 * @return array ['rows' => [member_id => row], 'totals' => [...]]. Every row gets hours, hours_share, gross_fee,
 *               calculated_fee, discounted, discount and final_fee (honorary members: zeros, hours_share null).
 */
function famefe_calculate_fees(array $rows, float $base_fee, array $opts = []): array
{
	$decimals = max(0, intval($opts['decimals'] ?? 0));
	$share = min(100, max(0, floatval($opts['discount_share'] ?? 0)));
	$rate = min(100, max(0, floatval($opts['discount_rate'] ?? 0)));
	$target_total = isset($opts['discount_target']) ? max(0, floatval($opts['discount_target'])) : null;
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

	// Members without hours pay twice the base fee, always.
	$without_hours = array_values(array_filter($regular, fn($id) => $result[$id]['hours'] <= 0));
	foreach ($without_hours as $id) {
		$result[$id]['calculated_fee'] = 2 * $base_fee;
	}
	// The members with hours pay the rest of the expected total: what their gross fees collect above it
	// is taken off them equally. A member whose fee would go below zero pays 0 and the rest is shared by the others.
	$target = $expected - 2 * $base_fee * count($without_hours);
	$paying = array_values(array_diff($regular, $without_hours));
	$surplus_each = 0.0;
	do {
		$gross_paying = 0.0;
		foreach ($paying as $id) {
			$gross_paying += $result[$id]['gross_fee'];
		}
		$surplus_each = $paying ? ($gross_paying - $target) / count($paying) : 0.0;
		$below_zero = array_values(array_filter($paying, fn($id) => $result[$id]['gross_fee'] - $surplus_each < 0));
		$paying = array_values(array_diff($paying, $below_zero));
	} while ($below_zero && $paying);
	foreach ($paying as $id) {
		$result[$id]['calculated_fee'] = max(0.0, $result[$id]['gross_fee'] - $surplus_each);
	}

	// Discount for the lowest calculated fees (compared as displayed, so equal amounts tie);
	// the share counts all regular members, members without hours never get it.
	$discounted = [];
	$candidates = array_values(array_diff($regular, $without_hours));
	if ($candidates && $share > 0 && ($rate > 0 || $target_total !== null)) {
		$fees = [];
		foreach ($candidates as $id) {
			$fees[$id] = round($result[$id]['calculated_fee'], $decimals);
		}
		asort($fees);
		$take = min(count($fees), (int) ceil($count * $share / 100));
		$limit = array_values($fees)[max(1, $take) - 1];
		foreach ($fees as $id => $fee) {
			if ($fee <= $limit) {
				$discounted[] = $id;
			}
		}
	}
	// Discount given by the total to collect: the rate that brings the fees to the target.
	$final_fees = [];
	if ($discounted && $target_total !== null) {
		$others_total = 0.0;
		$discounted_total = 0.0;
		foreach ($regular as $id) {
			if (in_array($id, $discounted, true)) {
				$discounted_total += $result[$id]['calculated_fee'];
			} else {
				$others_total += round($result[$id]['calculated_fee'], $decimals);
			}
		}
		$rate = $discounted_total > 0 ? min(100, max(0, 100 * (1 - ($target_total - $others_total) / $discounted_total))) : 0.0;
		if ($rate <= 0) {
			$discounted = [];
		} elseif ($rate < 100) {
			$final_fees = famefe_fees_to_total($discounted, $result, $rate, $target_total - $others_total, $decimals);
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
		'reduction_each' => round($surplus_each, $decimals),
		'without_hours_count' => count($without_hours),
		'calculated_total' => 0.0,
		'discounted_count' => count($discounted),
		'discount_total' => 0.0,
		'final_total' => 0.0,
		'discount_share' => $share,
		'discount_rate' => $rate,
		'discount_target' => $target_total,
	];
	foreach ($regular as $id) {
		$calculated = round($result[$id]['calculated_fee'], $decimals);
		$final = $calculated;
		if (in_array($id, $discounted, true)) {
			$final = $final_fees[$id] ?? round($result[$id]['calculated_fee'] * (1 - $rate / 100), $decimals);
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

/**
 * Discounted fees rounded so that they add up to $total: rounded as usual, then the fees whose rounding
 * went most the other way move by the smallest unit (largest remainder method). Members with the same
 * fee move together or not at all.
 *
 * @return array [member_id => final fee].
 */
function famefe_fees_to_total(array $ids, array $result, float $rate, float $total, int $decimals): array
{
	$unit = 10 ** -$decimals;
	$fees = [];
	$groups = [];
	foreach ($ids as $id) {
		$exact = $result[$id]['calculated_fee'] * (1 - $rate / 100);
		$fees[$id] = round($exact, $decimals);
		$key = (string) round($exact, $decimals + 4);
		$groups[$key]['ids'][] = $id;
		// How far the exact fee is above the rounded one, in units (-0.5 to 0.5).
		$groups[$key]['remainder'] = ($exact - $fees[$id]) / $unit;
	}
	$missing = (int) round(($total - array_sum($fees)) / $unit);
	$step = $missing > 0 ? 1 : -1;
	// Rounding up first the fees that were rounded down the most, or the other way round.
	uasort($groups, fn($a, $b) => $step * ($b['remainder'] <=> $a['remainder']));
	foreach ($groups as $group) {
		$size = count($group['ids']);
		if ($missing === 0 || $size > abs($missing)) {
			continue;
		}
		foreach ($group['ids'] as $id) {
			$fees[$id] = max(0.0, round($fees[$id] + $step * $unit, $decimals));
		}
		$missing -= $step * $size;
	}
	return $fees;
}
