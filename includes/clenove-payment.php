<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) { exit; }

function zobraz_pole_datum_platby($clenove,$hide_date_button) {
	if($hide_date_button==1) return "";
	ob_start();
    $user = wp_get_current_user();
    if (in_array('administrator', $user->roles) || in_array('editor', $user->roles) || in_array('redaktor', $user->roles)) {
        ?>
        <div class="ltc-uceleny_text">
			<h4><?php echo __( 'Přidat datum platby', 'ltc-extension' ); ?></h4>
			<p><?php echo __( 'Toto tlačítko se zobrazuje pouze administrátorům webu.', 'ltc-extension' ); ?></p>
            <form class="add-payment-form" method="post">
               <p> <label for="user_id"><?php echo __( 'Vyberte uživatele:', 'ltc-extension' ); ?></label>
                <select name="user_id">
                    <?php foreach ($clenove as $clen): ?>
                        <?php
                            $user_id = intval($clen['user_id']);
                            $user = get_user_by('ID', $user_id);
                            $display_name = $user->display_name;
                        ?>
                        <option value="<?php echo $user_id ?>"><?php echo $display_name ?></option>
                    <?php endforeach; ?>
				   </select></p>
				<p>
                <label for="payment_date"><?php echo __( 'Datum platby:', 'ltc-extension' ); ?></label>
                <input type="date" name="payment_date">
				</p>
                <input type="submit" name="submit_payment_date" value="<?php echo __( 'Přidat datum platby', 'ltc-extension' ); ?>">
            </form>
        </div>
        <?php
    }
	//zpracování a odeslání do tabulky usermeta
	if (isset($_POST['payment_date'])) {
    $user_id = intval($_POST['user_id']);
    $payment_date = sanitize_text_field($_POST['payment_date']);
    update_user_meta($user_id, 'payment_date', $payment_date);
	}
	return ob_get_clean(); // Přidejte tento řádek na konci
}
//stripe_payment_button_shortcode(array('platba_castka' => 250, 'platba_typ' => 0));
function zobraz_tlacitko_platby($clenove,$end_date,$hide_payment_button) {
	if($hide_payment_button==1) return '';	
	//ob_start();
	$current_user_id = get_current_user_id();
	if ($current_user_id==0) return ""; //nepřihlášený uživatel
	foreach ($clenove as $clen) {
  	if ($clen['user_id'] == $current_user_id) {
   	 $VCP = $clen['VCP'];
    break;
  		}
	}
	$payment_date = get_user_meta($current_user_id, 'payment_date', true);
	$end_date = date('Y-m-d', strtotime('last day of previous month'));

	if ($payment_date && $payment_date > $end_date) {
    	$output = '<p class="ltc-text-kolem-tabulky">' . __( 'Vaše platba již byla provedena.', 'ltc-extension' ) . '</p>';

		return $output;	
	}
	
	if($VCP > 0) {
		$button_shortcode = stripe_payment_button_shortcode(array('platba_castka' => $VCP, 'platba_typ' => 1));	
	} else {
		$output = '<p class="ltc-text-kolem-tabulky">' . __( 'Platit členský příspěvek nemusíte.', 'ltc-extension' ) . '</p>';
		return $output;	
	}	
	
	if ($button_shortcode !== null) $button_with_format = preg_replace('/(\d{4})-(\d{2})-(\d{2})/', '$3/$2/$1', $button_shortcode);
	else $button_with_format = $button_shortcode; //zde je potřeba zkontrolovat smysluplnost vložené podmínky
	
	return $button_with_format;	
}

function zobraz_legendu_pod ($ltc_total,$hide_description_below){
	if($hide_description_below==1) return "";
	$output = '<p class="ltc-text-kolem-tabulky">' . __( 'Základní členský příspěvek ve výši: ', 'ltc-extension' ) . '<b>' . najdi_total_parametr($ltc_total, 'ltc_zcp'). ' ' . __( 'Kč', 'ltc-extension' ) . '</b>';
	$output = '<p class="ltc-text-kolem-tabulky">' . __( 'Základní členský příspěvek ve výši: ', 'ltc-extension' ) . '<b>' . najdi_total_parametr($ltc_total, 'ltc_zcp'). ' ' . __( 'Kč', 'ltc-extension' ) . '</b>';
	$output .= '<br/>' . __('Počet brigádnických hodin celkem: ', 'ltc-extension') . '<b>' . najdi_total_parametr($ltc_total, 'total_pocet_hodin') . ' ' . __('hod.', 'ltc-extension') . '</b>';
	$output .= '<br/>' . __('Počet brigádnických hodin řádných členů: ', 'ltc-extension') . '<b>' . najdi_total_parametr($ltc_total, 'total_hours_radny') . ' ' . __('hod.', 'ltc-extension') . '</b>';

	$output .= '<br/>' . __('Počet brigádnických hodin čestných členů a ostatních: ', 'ltc-extension') . '<b>' . (najdi_total_parametr($ltc_total, 'total_pocet_hodin') - najdi_total_parametr($ltc_total, 'total_hours_radny')) . ' ' . __('hod.', 'ltc-extension') . '</b>';

	return $output;	
}

function zobraz_legendu_nad ($ltc_total,$hide_description_above){
	if($hide_description_above==1) return "";
$output = '<div><h4>' . __( 'Hodnoty pro období od ', 'ltc-extension' ) . najdi_total_parametr($ltc_total, 'start_day') . __( ' do ', 'ltc-extension' ) . najdi_total_parametr($ltc_total, 'end_day') . ':</h4></div>';
	return $output;
}

function najdi_total_parametr($ltc_total, $parametr) {
  foreach ($ltc_total as $pole) {
    if (isset($pole[$parametr])) {
      return $pole[$parametr];
    }
  }
  // pokud se parametr nenašel v žádném poli, vrátíme false nebo hodnotu podle potřeby
  return false;
}


