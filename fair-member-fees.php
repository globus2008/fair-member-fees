<?php
/**
 * Plugin Name:       Fair Member Fees
 * Description:       Membership register with a change history, volunteer hours and a fair membership fee: members who volunteer more pay less. Optional discount and payment through Stripe. Built with blocks.
 * Version:           1.0.1
 * Requires at least: 6.6
 * Requires PHP:      8.0
 * Author:            globus2008
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       fair-member-fees
 * Domain Path:       /languages
 *
 * @package fair-member-fees
 */

if (!defined('ABSPATH')) {
	exit;
}

define('FAMEFE_VERSION', '1.0.1');
// Bump whenever a CREATE TABLE statement in famefe_install() changes.
define('FAMEFE_DB_VERSION', '1.0.1');
define('FAMEFE_FILE', __FILE__);
define('FAMEFE_PATH', plugin_dir_path(__FILE__));
define('FAMEFE_URL', plugin_dir_url(__FILE__));

require_once FAMEFE_PATH . 'includes/famefe-install.php';
require_once FAMEFE_PATH . 'includes/famefe-settings.php';
require_once FAMEFE_PATH . 'includes/famefe-calculator.php';
require_once FAMEFE_PATH . 'includes/famefe-data.php';
require_once FAMEFE_PATH . 'includes/famefe-services.php';
require_once FAMEFE_PATH . 'includes/famefe-notices.php';
require_once FAMEFE_PATH . 'includes/famefe-users.php';
require_once FAMEFE_PATH . 'includes/famefe-stripe.php';
require_once FAMEFE_PATH . 'includes/famefe-blocks.php';
require_once FAMEFE_PATH . 'includes/famefe-block-forms.php';

if (is_admin()) {
	require_once FAMEFE_PATH . 'includes/famefe-admin.php';
	require_once FAMEFE_PATH . 'includes/famefe-admin-handlers.php';
}

/**
 * Translations shipped with the plugin (languages/); language packs from translate.wordpress.org
 * in wp-content/languages take precedence.
 */
function famefe_load_textdomain(): void
{
	load_plugin_textdomain('fair-member-fees', false, dirname(plugin_basename(__FILE__)) . '/languages');
}
add_action('init', 'famefe_load_textdomain');

register_activation_hook(__FILE__, 'famefe_install');
add_action('plugins_loaded', 'famefe_maybe_upgrade');
