# Fair Member Fees – WordPress plugin (notes for Claude and contributors)

- Slug and text domain: `fair-member-fees`. Code prefix **`famefe_`** everywhere: functions, tables
  (`{prefix}famefe_*`), options, capability `famefe_manage`, REST `famefe/v1`, blocks `famefe/*`.
  (A wp.org reviewer rejected the short `drt_` prefix of another plugin.)
- Repo: https://github.com/globus2008/fair-member-fees (public; ltc-extension: globus2008/ltc-extension, private).
- Meant for wordpress.org. Rebuilt in 2026-10 from the owner's old plugin `volunteers-hours`
  (its code is in the git history, commit a433394). No backward compatibility, not even data:
  the owner converts the live data (`brigady_data`, user meta `clen`, `payment_date`) separately.
- Everything in the code is **English** (strings, comments, DB values). The owner chats in Czech.
- `c:\scr\doubles-rotation-tournament` is a local WP in Docker (http://localhost:8000, container
  `doubles-rotation-tournament-wordpress-1`, WP-CLI: `php /var/www/html/wp-cli.phar --allow-root`, needs
  `MSYS_NO_PATHCONV=1` in Git Bash). PHP is not on PATH on the dev machine; lint with `php -l` in the container.
- Requires PHP 8.0 (union types, `mixed`) and WP 6.6.
- Sister plugin `ltc-extension` (club-specific, not published) uses only the public API below.

## Files
| File | Purpose |
|---|---|
| `fair-member-fees.php` | Bootstrap, constants, textdomain, activation |
| `includes/famefe-install.php` | `famefe_table()`, tables via dbDelta (only when `FAMEFE_DB_VERSION` changes), capability |
| `includes/famefe-settings.php` | Option `famefe_settings` (currency, decimals, Stripe keys, name display, ...), formatting helpers |
| `includes/famefe-calculator.php` | `famefe_calculate_fees()` – pure function, no DB |
| `includes/famefe-data.php` | Read queries, public API `famefe_get_member()`, `famefe_get_member_type()`, `famefe_period_fees()` |
| `includes/famefe-services.php` | Every write (`famefe_service_*`): permissions, validation; never read `$_POST`, print or redirect |
| `includes/famefe-notices.php` | Notice codes and texts; redirects with `?famefe_notice=<code>[&famefe_error=1][&famefe_block=<id>]` |
| `includes/famefe-stripe.php` | Stripe Checkout over `wp_remote_*` (no library), return handler, webhook `famefe/v1/stripe-webhook` |
| `includes/famefe-blocks.php` | Block registration, `famefe_find_block()`, block helpers |
| `includes/famefe-block-forms.php` | admin-post handlers of the block forms (hours, manual payment, checkout) |
| `includes/famefe-admin*.php` | Admin pages (Members, Volunteer hours, Payments, Settings, Help), list tables, handlers |
| `src/blocks/*` -> `build/blocks/*` | 5 dynamic blocks; `npm run build`. Shared editor code `src/blocks/shared.js`, CSS `assets/blocks.css` |
| `languages/` | POT + cs_CZ (.po/.mo/.json). Regenerate: `wp i18n make-pot . languages/fair-member-fees.pot --exclude=node_modules,src` |

## Data
- `famefe_members` (user_id optional + unique, member_type regular|honorary, status active|left, member_since, left_on).
- Members and WP users (owner 2026-10-09, `includes/famefe-users.php`): the register stays in the plugin's table
  (members without an account; history survives a deleted account), but a linked account is the source of the
  name and e-mail. Profile changes are copied to the member (`profile_update`); the member form writes to the
  profile only when the current user may `edit_user` that account (an editor must not change an admin's e-mail
  = account takeover). "Add member": existing account / new account (`create_users`, default role, optional
  password link) / no account. Profile section "Membership", Users list column, warning on the delete screen.
  Choosing an account in the member form fills first name, last name and e-mail at once (`assets/admin.js`,
  REST GET `famefe/v1/user-details/<id>`, managers only, one account per request) and warns when the account
  already belongs to another member.
- The whole back end of the plugin is only for administrators and editors (`famefe_manage`; Settings only
  `manage_options`); members do not even see the Membership section in their own profile (owner 2026-10-09).
- `famefe_member_log`: every membership change (joined, type_changed, left, rejoined) with change_date,
  recorded_by and recorded_at – required by the club statutes. Type/status change only through
  `famefe_service_change_member()`. Rejoining sets a new member_since.
- `famefe_hours`: member_id (NULL for non-members) + user_id. Max 24 h per entry.
- `famefe_payments`: per member and period (exact period_start/period_end of the block); unique `stripe_session_id`.
- A member counts in a period when member_since <= end and (left_on IS NULL or left_on >= start); the current type decides.

## Fee calculation (owner's method, see ltcchrast.cz "Postup výpočtu výše členského příspěvku")
expected_total = base_fee × regular_count; hours_share = hours / hours of regular members;
gross_fee = expected_total × (1 − share); calculated_fee = gross_fee − (Σgross − expected_total)/count.
A fee that would go below 0 becomes 0 and the formula is repeated for the others, so the sum is always expected_total
(added 2026-10-09; the old plugin only clipped and collected more). Optional discount: the lowest ⌈N × share⌉ fees
(ties included) × (1 − rate); it only reduces, nothing is redistributed (owner: not volunteering must never be cheap).
Rounding to the currency decimals only at the end. The website example (1000; 0/10/15/20/25 h) gives
2000/1286/929/571/214 (the old web page rounds to 50).

## Blocks
- Dynamic, `render.php`, plain HTML forms posting to admin-post.php (no front-end JS, no Interactivity API).
- Forms carry `post_id` + `block_id`; the handler reads the block attributes from the saved post
  (`famefe_find_block()`, also inside synced patterns). **No amount or limit is ever taken from the browser.**
  `blockId` is set in the editor (`useBlockId`, regenerated for copies).
- Season (period), base fee and discount are attributes of the Membership fees block, not settings (owner decision).
- Manual payments (cash/transfer) only for `famefe_manage` (administrator, editor).
- Membership fees table: sortable columns and totals rows in `<tfoot>` (regular / honorary / non-members and former
  members / total); no sentences with numbers below the table (owner 2026-10-09). Managers see a hint when the
  payment button cannot show (Stripe not set up, or they are not regular members).
- Hours form: members record hours only for today unless `allowDateChange` is on (default off, owner 2026-10-09:
  no invented hours in the past, no change of a closed period); enforced in `famefe_hours_block_limits()`.
  Managers always choose the date and the member (their own name preselected, also when they are not members).
- `assets/tables.js` (handle `famefe-tables`, plain script, `viewScript` of the fees and members blocks): sort buttons
  for `th[data-sort]` using `td[data-value]`, and the name search `input[data-famefe-search]` of the members list.
- Tests: Playwright from the Rotation Tournaments node_modules with `executablePath` of the installed Chromium;
  accounts `claude@claude.cz`/`claude` (admin) and the local test subscriber `zz_famefe_member`/`famefe-test`.
