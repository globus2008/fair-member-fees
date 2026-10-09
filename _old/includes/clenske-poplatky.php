<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) { exit; }

//[clenske_poplatky ltc_ids="1,2,3,5" ltc_zcp="1000" ltc_vcp_round="0" start_date="2023-05-01" end_date="2023-05-31" hide_table="0" hide_calculation="0" hide_payment_day="0" hide_description_above="0" hide_description_below="0" hide_date_button="0" hide_payment_button="1"]
add_shortcode('clenske_poplatky', 'clenske_poplatky_function');

//clenske_poplatky_tabulka($clenove,$atts['start_date'],$atts['hide_table'],$atts['hide_calculation'],$atts['hide_payment_day']);
function clenske_poplatky_function($atts) {
 global $wpdb;	
 //$ltc_ids = isset($atts['ltc_ids']) ? array_map('intval', explode(',', $atts['ltc_ids'])) : get_users(array('fields' => 'ID'));
    // Získání uživatelů s hodnotou "R" nebo "C" v poli "clen" v tabulce "usermeta"
$ltc_ids = isset($atts['ltc_ids']) ? array_map('intval', explode(',', $atts['ltc_ids'])) : get_users(array(
    'fields' => 'ID',
    'meta_query' => array(
        'relation' => 'AND',
        array(
            'key' => 'clen',
            'value' => 'R',
            'compare' => '=',
        ),
    ),
));

	$ltc_zcp = isset($atts['ltc_zcp']) ? intval($atts['ltc_zcp']) :get_option( 'ltc_zcp' );
	
$atts = shortcode_atts( array(
		'ltc_ids' => $ltc_ids, 
		'ltc_zcp' => $ltc_zcp, 
		'ltc_vcp_round' => 0,
   		'start_date' => date('Y-01-01'), // Výchozí hodnota: 1.ledna aktuálního roku
   		'end_date' => date('Y-12-31'), // Výchozí hodnota: 31.prosince aktuálního roku
		'hide_id' => 1,
		'hide_table' => 0,
		'hide_calculation' => 0,
		'hide_payment_day' => 0,
		'hide_description_above' => 0,
		'hide_description_below' => 0,
		'hide_date_button' => 0,
		'hide_payment_button' => 1,
	), $atts, 'clenske_poplatky' );
	
	$clenove = [];
	$ltc_total = [];
	$ltc_total[] = array("pocet_radnych_clenu" => count($ltc_ids));
	$ltc_total[] = array("ltc_zcp" => $atts['ltc_zcp']);
	$ltc_total[] = array("start_day" => $atts['start_date']);
	$ltc_total[] = array("end_day" => $atts['end_date']);
	
	if (!($wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}bookacti_bookings'") == "{$wpdb->prefix}bookacti_bookings")) {
    	$atts['hide_payment_button'] = 1;
	}

	pocitej_user_hours($clenove,$atts['start_date'], $atts['end_date']);
	//print('clenove:');
	//print_r($clenove);
	pocitej_total_hours($clenove,$ltc_total,$atts['ltc_ids'],$atts['ltc_zcp'],$atts['ltc_vcp_round'],$atts['start_date'], $atts['end_date']);
	$output='<div class="wrap">';
	$output.= zobraz_legendu_nad ($ltc_total,$atts['hide_description_above']);
	$output.= clenske_poplatky_tabulka($ltc_ids, $clenove, $atts['start_date'], $atts['end_date'], $atts['hide_id'], $atts['hide_table'], $atts['hide_calculation'], $atts['hide_payment_day']);
	$output.= zobraz_legendu_pod($ltc_total,$atts['hide_description_below']);
	$output.=zobraz_pole_datum_platby($clenove,$atts['hide_date_button']);

	// kód pro vypsání tlačítka na stránce
   $output.= zobraz_tlacitko_platby($clenove,$atts['end_date'],$atts['hide_payment_button']);
	$output .= '</div>';
	return $output; 
}

//začátek vložení počítání brigádnických hodin	
function pocitej_user_hours(&$clenove,$start_date,$end_date) {
    global $wpdb;

    // získání všech uživatelů s jejich ID a jménem
    $users = get_users( array( 'fields' => array( 'ID', 'display_name' ) ) );

    foreach ( $users as $user ) {
        $user_id = $user->ID;

        // získání počtu brigádnických hodin pro uživatele mezi zadanými daty
        $query = $wpdb->prepare(
            "SELECT SUM(pocet_hodin) FROM {$wpdb->prefix}brigady_data WHERE user_id = %d AND datum >= %s AND datum <= %s",
            $user_id,
            $start_date,
            $end_date
        );
        $pocet_hodin = $wpdb->get_var( $query );
		
        // uložení hodnoty počtu brigádnických hodin do pole "pocet_hodin" v tabulce "usermeta"
		$clenove[] = array(
  			     "user_id" => $user_id,
      			"pocet_hodin" => $pocet_hodin,
       			 "POH" => 0,
       			 "NCP" => 0,
      			 "VCP" => 0
 					);
    }
}
//konec vložení počítání brigádnických hodin
//vyhledá hodiny člena	
function search_hodiny($clenove, $user_id) {
    foreach ($clenove as $clen) {
        if ($clen['user_id'] == $user_id) {
			return floatval($clen['pocet_hodin']);
        }
    }
    return null; // řádek s uživatelem nebyl nalezen
}
//vyhledá POH člena	
function search_poh($clenove, $user_id) {
    foreach ($clenove as $clen) {
        if ($clen['user_id'] == $user_id) {
			return floatval($clen['POH']);
        }
    }
    return null; // řádek s uživatelem nebyl nalezen
}
//vyhledá NCP člena	
function search_ncp($clenove, $user_id) {
    foreach ($clenove as $clen) {
        if ($clen['user_id'] == $user_id) {
			return floatval($clen['NCP']);
        }
    }
    return null; // řádek s uživatelem nebyl nalezen
}
//zapíše hodnotu POH
// projdeme pole $clenove a najdeme řádek s daným user_id
function save_POH(&$clenove, $user_id, $poh) {
foreach ($clenove as &$clen) {
    if ($clen['user_id'] == $user_id) {
        // našli jsme řádek s daným user_id, tak upravíme hodnotu POH
        $clen['POH'] = $poh;
        break; // ukončíme cyklus, protože jsme našli hledaný řádek
    }
}
}	
//zapíše hodnotu NCP
// projdeme pole $clenove a najdeme řádek s daným user_id
function save_NCP(&$clenove, $user_id, $ncp) {
foreach ($clenove as &$clen) {
    if ($clen['user_id'] == $user_id) {
        // našli jsme řádek s daným user_id, tak upravíme hodnotu NCP
        $clen['NCP'] = $ncp;
        break; // ukončíme cyklus, protože jsme našli hledaný řádek
    }
}
}	
//zapíše hodnotu vCP
// projdeme pole $clenove a najdeme řádek s daným user_id
function save_VCP(&$clenove, $user_id, $vcp) {
foreach ($clenove as &$clen) {
    if ($clen['user_id'] == $user_id) {
        // našli jsme řádek s daným user_id, tak upravíme hodnotu NCP
        $clen['VCP'] = $vcp;
        break; // ukončíme cyklus, protože jsme našli hledaný řádek
    }
}
}	
//kalkulace součtu brigádnických hodin u všech členů
function pocitej_total_hours(&$clenove,&$ltc_total,$ltc_ids,$ltc_zcp,$ltc_vcp_round,$start_date,$end_date) {
	if (is_string($ltc_ids)) {
    	// Kód, který se provede, pokud $ltc_ids je string
    	$ltc_ids = explode(',', $ltc_ids);
	}


		$ltc_total[] = array("pocet_radnych_clenu" => count($ltc_ids));

$pocet_radnych_clenu = intval(najdi_total_parametr($ltc_total, 'pocet_radnych_clenu'));

global $wpdb;
// získání součtu hodnot "pocet_hodin" u všech uživatelů 
	
$total_pocet_hodin = 0;
$total_hours_radny = 0;
foreach ($clenove as $clen) {
    $total_pocet_hodin += $clen['pocet_hodin'];
	$temp_id=intval($clen['user_id']);

	if ( in_array( $temp_id, $ltc_ids ) ) {
	$total_hours_radny +=$clen['pocet_hodin'];	
	}
}	
		$ltc_total[] = array("total_pocet_hodin" => floatval($total_pocet_hodin));
		$total_hours_radny=floatval($total_hours_radny);
		$ltc_total[] = array("total_hours_radny" => $total_hours_radny);	
	$pocet_brigadnickych_hodin_radny_clen=$total_hours_radny;
	
// Načtení hodnoty pocet_brigadnickych_hodin_radny_clen z tabulky options
//$pocet_brigadnickych_hodin_radny_clen = floatval(get_option('pocet_brigadnickych_hodin_radny_clen'));

// Dotaz pro získání dat z tabulky usermeta
$users_query = "SELECT u.user_id, u.meta_value, m.meta_value as clen FROM {$wpdb->usermeta} u JOIN {$wpdb->usermeta} m ON u.user_id = m.user_id WHERE u.meta_key = 'pocet_hodin' AND m.meta_key = 'clen' AND m.meta_value = 'R'";	
$users = $wpdb->get_results($users_query);	

$users_ids = [];	
foreach ($users as $user) {
    $user_id = $user->user_id;
	$users_ids[] = $user_id;
}
if(empty($ltc_ids))	$merged_ids = $users_ids;
else $merged_ids = $ltc_ids;
//$merged_ids = array_merge($users_ids, $ltc_ids);	
	//print_r($merged_ids);
	//print('$merged_ids výše');
	
// Cyklus pro výpočet hodnoty POH a uložení do tabulky usermeta
//foreach ($users as $user) {
   // $user_id = $user->user_id;

foreach ($merged_ids as $user_id) {
    //$user_id = $user->user_id;
	$pocet_brigadnickych_hodin = search_hodiny($clenove, $user_id); 
    
    // Pokud je pocet_brigadnickych_hodin_radny_clen nulový nebo neexistuje v options, nastavíme hodnotu na 1, aby nedocházelo k dělení nulou
    if (!$pocet_brigadnickych_hodin_radny_clen) {
        $pocet_brigadnickych_hodin_radny_clen = 1;
    }
    
    // Výpočet hodnoty POH a uložení do tabulky usermeta
	$poh = round(($pocet_brigadnickych_hodin / $pocet_brigadnickych_hodin_radny_clen),4);
	save_POH($clenove,$user_id,$poh);
}

	
//NCP výpočet
//NČP=SOPx(1-POH).
//SOP – součet očekávaných plateb = ZČP x PŘČ = 1000 x 5=5000 Kč
//POH – procento z celkového počtu odpracovaných hodin řádných členů
// Načtení hodnot z tabulky options
$ltc_zcp = floatval($ltc_zcp);
$pocet_radnych_clenu = intval(najdi_total_parametr($ltc_total, 'pocet_radnych_clenu'));
// Dotaz pro získání dat z tabulky usermeta
$users_query = "SELECT user_id, meta_value FROM {$wpdb->usermeta} WHERE meta_key = 'pocet_hodin'";	
$users = $wpdb->get_results($users_query);

// Cyklus pro výpočet hodnoty NCP a uložení do tabulky usermeta
foreach ($merged_ids as $user_id) {
//foreach ($users as $user) {
    //$user_id = $user->user_id;
    $clen = get_user_meta($user_id, 'clen', true);
	
	//if($user_id == 1) print('veronika!');
    
    // Pokud uživatel není "R", přeskočíme výpočet
    if (($clen !== 'R' && empty($ltc_ids)) || (!in_array($user_id,$ltc_ids) && !empty($ltc_ids)) ) {
        continue;
    }


	$poh =search_poh($clenove,$user_id);
	
    // Výpočet hodnoty NCP a uložení do tabulky usermeta
    $ncp = round(($ltc_zcp * $pocet_radnych_clenu * (1 - $poh)),2);
	save_NCP($clenove, $user_id, $ncp);  
}

//konec NCP výpočet	
//kalkulace NCP a POH pro všechny dohromady
//součet u všech členů:
$ltc_poh = array_reduce($clenove, function($carry, $item) {
    return $carry + $item['POH'];
}, 0);

$ltc_ncp = array_reduce($clenove, function($carry, $item) {
    return $carry + $item['NCP'];
}, 0);	
	$ltc_total[] = array("ltc_poh" => $ltc_poh);	
	$ltc_total[] = array("ltc_ncp" => $ltc_ncp);	
// Dotaz pro získání dat z tabulky usermeta
$users_query = "SELECT user_id, meta_value FROM {$wpdb->usermeta} WHERE meta_key = 'pocet_hodin'";	
$users = $wpdb->get_results($users_query);

	//print_r($clenove);
// Cyklus pro výpočet hodnoty VCP a uložení do tabulky usermeta
$ltc_vcp = 0;
foreach ($merged_ids as $user_id) {
//foreach ($users as $user) {
    //$user_id = $user->user_id;
    //$pocet_brigadnickych_hodin = intval($user->meta_value);
	$pocet_brigadnickych_hodin = search_hodiny($clenove, $user_id); 
    $clen = get_user_meta($user_id, 'clen', true);
	    
    // Pokud uživatel není "R", přeskočíme ho
   // if ($clen === 'R') {    
    if (($clen == 'R' && empty($ltc_ids)) || (in_array($user_id,$ltc_ids) && !empty($ltc_ids)) ) {
    
    // Získání hodnoty NCP a POH
    $poh =search_poh($clenove,$user_id);
	$ncp =search_ncp($clenove,$user_id);	
    
    // Výpočet hodnoty VCP a uložení do tabulky usermeta
    $vcp = round(($ncp - ($ltc_ncp - $ltc_zcp * $pocet_radnych_clenu) / $pocet_radnych_clenu),intval($ltc_vcp_round));
	if ($vcp < 0) {
  		$vcp = 0;
	}
	save_VCP($clenove, $user_id, $vcp);
}
	else {$vcp =0;}
	$ltc_vcp += $vcp;
}	
	$ltc_total[] = array("ltc_vcp" => $ltc_vcp);
//konec výpočtu VCP
}
function clenske_poplatky_tabulka($ltc_ids, $clenove,$start_date,$end_date,$hide_id=0, $hide_table=0,$hide_calculation=0, $hide_payment_date = 0) {
    //vykreslení tabulky
    ob_start(); 
	if(!$hide_table) {		

	$output = '<div class="volunteers-table-responsive">';
	$output .= "<table>";
    $output .= "<tr>";
			if (!$hide_id) { 
            $output .= "<th>ID</th>";
			}
            $output .= "<th>". __( 'Jméno', 'ltc-extension' ) ."</th>";
            $output .= "<th>" . __( 'Člen', 'ltc-extension' )."</th>";
			$pocet_hodin_translation = __( 'Počet hodin', 'ltc-extension' );
			$output .= "<th>" . $pocet_hodin_translation . "</th>";

		 	/*$output .= "<th>" . __( 'Počet hodin', 'ltc-extension' ) . "</th>";*/
        


			if (!$hide_calculation) { 
            	$output .= "<th>". __( 'POH', 'ltc-extension' ) ."</th>";
            	$output .= "<th>". __( 'NCP', 'ltc-extension' ) ."</th>";
			} 
            $output .= "<th>". __( 'VCP', 'ltc-extension' ) ."</th>";
			if (!$hide_payment_date) { 
            	$output .= "<th>".  __( 'Datum platby', 'ltc-extension' ) ."</th>";
				} 			
            $output .= "</tr>";
			usort($clenove, function($a, $b) {
   				 $userA = get_user_by('ID', intval($a['user_id']));
    			 $userB = get_user_by('ID', intval($b['user_id']));
   			 return strcmp($userA->display_name, $userB->display_name);
			});					  
            foreach ($clenove as $clen) {
                $user_id = intval($clen['user_id']);
                $user = get_user_by('ID', $user_id);
				$clen_typ = get_user_meta( $user->ID, 'clen', true );
                $pocet_hodin = $clen['pocet_hodin'];
                $poh = $clen['POH'];
                $ncp = $clen['NCP'];
                $vcp = $clen['VCP'];
				$payment_date = get_user_meta($user->ID, 'payment_date', true);
 				if (!$payment_date || $start_date > $payment_date || date('Y-01-01') > $payment_date) {
 					   $payment_date = "";
				}
                if ( ($clen_typ == 'C' && $pocet_hodin > 0) || in_array($clen['user_id'],$ltc_ids) ) { //$clen_typ == 'R' || 
                     $output .= "<tr>";
					    if (!$hide_id) {
                        $output .= "<td>".$user_id."</td>";
						}
                        $output .= "<td>".$user->display_name."</td>";
                        $output .= "<td>".$clen_typ. "</td>";
 					    $output .= "<td>" . $pocet_hodin . "</td>";
						 if (!$hide_calculation) { 
                       	 	$output .= "<td>".  $poh ."</td>";
                        	$output .= "<td>". $ncp ."</td>";
						} 
                        $output .= "<td>" .$vcp ."</td>";
						 if (!$hide_payment_date) { 
                        	$output .= "<td>". $payment_date."</td>";
						}  						 
                    $output .= "</tr>";
		}		
	}
    $output .= "</table>";
	$output .= "</div>";	
	return $output;				  
	} //konec if hide
	return ob_get_clean(); // Přidejte tento řádek na konci
	} //konec funkce
	