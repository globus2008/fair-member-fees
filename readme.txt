=== Fair Member Fees ===
Contributors: globus2008
Tags: membership, volunteers, membership fee, club, stripe
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 8.0
Stable tag: 1.0.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Membership register, volunteer hours and a fair membership fee: members who volunteer more pay less. Optional discount and Stripe payments.

== Description ==

Clubs live from the work of their members. Fair Member Fees rewards it: every member records the hours they volunteered, and the membership fee of each member is calculated from them. Whoever works for the club pays less; whoever does not, pays more than the base fee – paying instead of working is never the cheaper choice.

**Features**

* Membership register with regular and honorary members (honorary members pay no fee).
* Full history of every member: joined, membership type changed, membership ended, rejoined – each with the date of the change, who recorded it and when.
* Volunteer hours recorded by the members themselves, or by administrators and editors for anybody.
* Fair calculation of the fees for any period (season), see below.
* Optional discount for the members with the lowest fees.
* Online payment of the fee through Stripe Checkout; cash and bank payments recorded by administrators and editors.
* Five blocks, all set up in the block settings – no shortcodes:
  * **Membership fees** – the fee table of a period with the payment button,
  * **Volunteer hours form**,
  * **Volunteer hours list**,
  * **Members list**,
  * **Fee calculation explained** – the method on an example with your own numbers.
* Ready for translation.

**How the fee is calculated**

1. Expected total = base fee × number of regular members.
2. Share of hours = hours of the member / hours of all regular members.
3. Unreduced fee = expected total × (1 − share of hours).
4. The unreduced fees add up to more than the expected total; the difference is taken off every fee equally. No fee goes below zero (the rest is shared by the others), so all regular members together always pay the expected total.

Without any recorded hours everybody pays the base fee. Hours of honorary members and non-members are shown in the totals but do not change the fees.

**Discount (optional)**

Set a share of members (e.g. 60 %) and a discount (e.g. 20 %) in the Membership fees block: the given share of regular members with the lowest fees pays the given percentage less. Members with the same fee as the last of them get it too. The discount is not added to the fees of the others.

== External services ==

This plugin connects to **Stripe** (https://stripe.com) to let members pay their membership fee by card. It is used only when you enter a Stripe secret key in the plugin settings.

* When a member clicks the payment button, the plugin sends to Stripe: the amount and currency, the name of the payment ("Membership fee" and the period), the member's e-mail address, the member ID and the period, and the addresses of your page to return to. The member then enters the card details directly on the Stripe payment page.
* When the member returns, the plugin asks Stripe whether the payment was completed. Stripe also sends the result to the webhook address of the plugin.

Stripe terms of service: https://stripe.com/legal/ssa – Stripe privacy policy: https://stripe.com/privacy

== Installation ==

1. Install and activate the plugin.
2. Go to **Member Fees → Members** and add your members; link them to their user accounts so that they can record hours and pay online.
3. In **Member Fees → Settings** set the currency and, for online payments, your Stripe keys and the webhook.
4. Add the blocks to your pages (search for "Fair Member Fees" in the block inserter). Set the period, the base fee and the discount in the Membership fees block.

== Frequently Asked Questions ==

= Who can record hours? =

Every logged-in member records own hours. Administrators and editors can record hours for any member. In the settings you can allow logged-in non-members to record hours too; their hours count only in the totals.

= Who sees the form for cash payments? =

Only administrators and editors (the capability `famefe_manage`).

= Can the amount to pay be changed in the browser? =

No. The server always calculates the fee from the saved block settings and the recorded hours.

= What happens with the data when I delete the plugin? =

Nothing, unless you turn on "Delete data" in the settings.

== Changelog ==

= 1.0.0 =
* First release.
