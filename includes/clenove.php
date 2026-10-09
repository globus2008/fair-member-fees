<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) { exit; }

function clenove_plugin_activate() {
    // Vytvoření pole s výchozími hodnotami pro nová metadata
    $default_meta = array(
        'clen' => '',
        'pocet_hodin' => '',
        'VCP' => '',
        'POH' => '',
		'NCP' => '',
    );

    // Získání všech uživatelů
    $users = get_users();
    foreach ( $users as $user ) {
        // Získání metadat pro uživatele
        $user_meta = get_user_meta( $user->ID );

        // Přidání chybějících metadat
        foreach ( $default_meta as $key => $value ) {
            if ( !isset( $user_meta[$key] ) ) {
                add_user_meta( $user->ID, $key, $value );
            }
        }
    }
}

// Funkce pro vykreslení administrační stránky
function clenove_plugin_main_page() {
	if (isset($_POST['calculate_user_hours']) && $_POST['calculate_user_hours'] == 1) {
    	calculate_user_hours();
		calculate_total_hours();
		clenove_plugin_page();	
	}
	clenove_plugin_page();	
};
	
function clenove_plugin_page() {
    global $wpdb;

    // Zpracování formuláře pro přidání člena
    if ( isset( $_POST['user_id'] ) && isset( $_POST['clen'] ) ) {
        $user_id = intval( $_POST['user_id'] );
        $clen = sanitize_text_field( $_POST['clen'] );
        update_user_meta( $user_id, 'clen', $clen );
    }

    // Zpracování formuláře pro přidání člena z nabídky
    if ( isset( $_POST['add_member_id'] ) && isset( $_POST['add_member_clen'] ) ) {
        $user_id = intval( $_POST['add_member_id'] );
        $clen = sanitize_text_field( $_POST['add_member_clen'] );
        update_user_meta( $user_id, 'clen', $clen );
    }

    // Získání počtu uživatelů s hodnotou "R" v poli "clen"
    $pocet_radnych_clenu = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key = 'clen' AND meta_value = 'R'" );

    // Získání počtu uživatelů s hodnotou "C" v poli "clen"
    $pocet_cestnych_clenu = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key = 'clen' AND meta_value = 'C'" );

    // Uložení počtu uživatelů s hodnotou "R" a "C" do wp_options
    update_option( 'pocet_radnych_clenu', $pocet_radnych_clenu );
    update_option( 'pocet_cestnych_clenu', $pocet_cestnych_clenu );

    // Zobrazení tabulky s uživateli
    $users = get_users( array( 'fields' => array( 'ID', 'display_name' ) ) );
	usort( $users, function( $a, $b ) {
    return strcmp( $a->display_name, $b->display_name );
	} );
	
    ?>

	<h1><?php echo __('Členská základna', 'ltc-extension'); ?></h1>
	<h2><?php echo __('Uživatelé', 'ltc-extension'); ?></h2>

    <table class="widefat">
        <thead>
            <tr style="background-color: #ffcc99">
                <th><?php echo __('ID', 'ltc-extension'); ?></th>
                <th><?php echo __('Jméno', 'ltc-extension'); ?></th>
                <th><?php echo __('Člen', 'ltc-extension'); ?></th>
                <th><?php echo __('Počet hodin', 'ltc-extension'); ?></th>
                <th><?php echo __('POH', 'ltc-extension'); ?></th>
                <th><?php echo __('NCP', 'ltc-extension'); ?></th>				
                <th><?php echo __('VCP', 'ltc-extension'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ( $users as $user ) : ?>
                <?php
                $clen = get_user_meta( $user->ID, 'clen', true );
                $pocet_hodin = get_user_meta( $user->ID, 'pocet_hodin', true );
                $vcp = get_user_meta( $user->ID, 'VCP', true );
                $poh = get_user_meta( $user->ID, 'POH', true );
				$ncp = get_user_meta( $user->ID, 'NCP', true );
	            //$poh = floatval(number_format($poh, 2));
                ?>
                <?php if ( $clen == 'R' || $clen == 'C' ) : ?>
                    <tr>
                        <td><?php echo $user->ID; ?></td>
                        <td><?php echo $user->display_name; ?></td>
                        <td>
                            <form method="post">
                                <input type="hidden" name="user_id" value="<?php echo $user->ID; ?>">
                                <select name="clen" onchange="this.form.submit();">
                                    <option value="-" <?php selected( $clen, '-' ); ?>>-</option>
                                    <option value="R" <?php selected( $clen, 'R' ); ?>>R</option>
                                    <option value="C" <?php selected( $clen, 'C' ); ?>>C</option>
                                </select>
                            </form>
                        </td>
                        <td><?php echo $pocet_hodin; ?></td>
						<td><?php echo $poh; ?></td>
                        <td><?php echo $ncp; ?></td>
						<td><?php echo $vcp; ?></td>
                    </tr>
				<?php if ( isset( $_POST['clen'] ) && $_POST['clen'] === 'C' ) {
                    update_user_meta( $user->ID, 'POH', 0 );
                    update_user_meta( $user->ID, 'NCP', 0 );
                    update_user_meta( $user->ID, 'VCP', 0 );
                	} ?>
                <?php endif; ?>
            <?php endforeach; ?>
        </tbody>
    </table>

    <?php
	
    // Zobrazení počtu uživatelů s hodnotou "R" a "C"
    echo '<p>' . __('Počet řádných členů', 'ltc-extension') . ': ' . $pocet_radnych_clenu . '</p>';
    echo '<p>' . __('Počet čestných členů', 'ltc-extension') . ': ' . $pocet_cestnych_clenu . '</p>';

    echo '<p>' . __('Počet brigádnických hodin celkem', 'ltc-extension') . ': ' . get_option('pocet_brigadnickych_hodin') . '</p>';

	echo '<p>' . __('Počet brigádnických hodin řádných členů', 'ltc-extension') . ': ' . get_option('pocet_brigadnickych_hodin_radny_clen') . '</p>';

	echo '<p>' . __('Součet POH-procento z celkového počtu odpracovaných hodin řádných členů', 'ltc-extension') . ': ' . get_option('ltc_poh') . '</p>';

    echo '<p>' . __('Součet NCP-neredukovaný členský příspěvek', 'ltc-extension') . ': ' . get_option('ltc_ncp') . ' Kč</p>';

	echo '<p>' . __('Součet VCP-vypočtený členský příspěvek', 'ltc-extension') . ': ' . get_option('ltc_vcp') . ' Kč</p>';




	
// Vytvoření nabídky pro přidání člena
    $users_select = get_users();
    ?>
    <h2><?php echo __('Přidat člena', 'ltc-extension'); ?></h2>
    <form method="post">
        <select name="add_member_id">
            <?php foreach ( $users_select as $user ) : ?>
                <option value="<?php echo $user->ID; ?>"><?php echo $user->display_name; ?></option>
            <?php endforeach; ?>
        </select>
        <select name="add_member_clen">
            <option value="-">-</option>
            <option value="R">R</option>
            <option value="C">C</option>
        </select>
        <input type="submit" value="<?php echo esc_attr(__('Přidat', 'ltc-extension')); ?>">
    </form>
    <?php
}

//začátek vložení počítání brigádnických hodin	
function calculate_user_hours() {
    global $wpdb;

    // získání všech uživatelů s jejich ID a jménem
    $users = get_users( array( 'fields' => array( 'ID', 'display_name' ) ) );

    foreach ( $users as $user ) {
        $user_id = $user->ID;

        // získání počtu brigádnických hodin pro uživatele mezi zadanými daty
        $start_date = get_option( 'ltc_start' );
        $end_date = get_option( 'ltc_end' );
        $query = $wpdb->prepare(
            "SELECT SUM(pocet_hodin) FROM {$wpdb->prefix}brigady_data WHERE user_id = %d AND datum >= %s AND datum <= %s",
            $user_id,
            $start_date,
            $end_date
        );
        $pocet_hodin = $wpdb->get_var( $query );
		
        // uložení hodnoty počtu brigádnických hodin do pole "pocet_hodin" v tabulce "usermeta"
        update_user_meta( $user_id, 'pocet_hodin', $pocet_hodin );
    }
	
}

//konec vložení počítání brigádnických hodin	

//kalkulace součtu brigdnických hodin u všech členů
function calculate_total_hours() {

global $wpdb;
// získání součtu hodnot "pocet_hodin" u všech uživatelů 
$total_hours = $wpdb->get_var( "SELECT SUM(meta_value) FROM {$wpdb->usermeta} WHERE meta_key = 'pocet_hodin' AND (meta_value IS NOT NULL AND meta_value != '')" );

update_option( 'pocet_brigadnickych_hodin', $total_hours );

// získání součtu hodnot "pocet_hodin" jen uživatelů s hodnotou "C" v poli "clen"

$total_hours_radny = $wpdb->get_var("
    SELECT SUM(um1.meta_value)
    FROM {$wpdb->usermeta} um1
    JOIN {$wpdb->usermeta} um2 ON um1.user_id = um2.user_id
    WHERE um1.meta_key = 'pocet_hodin'
      AND (um1.meta_value IS NOT NULL AND um1.meta_value != '')
      AND um2.meta_key = 'clen' AND um2.meta_value = 'R'
");

update_option( 'pocet_brigadnickych_hodin_radny_clen', $total_hours_radny );
	
	

	
// Načtení hodnoty pocet_brigadnickych_hodin_radny_clen z tabulky options
$pocet_brigadnickych_hodin_radny_clen = floatval(get_option('pocet_brigadnickych_hodin_radny_clen'));

// Dotaz pro získání dat z tabulky usermeta
$users_query = "SELECT u.user_id, u.meta_value, m.meta_value as clen FROM {$wpdb->usermeta} u JOIN {$wpdb->usermeta} m ON u.user_id = m.user_id WHERE u.meta_key = 'pocet_hodin' AND m.meta_key = 'clen' AND m.meta_value = 'R'";	
$users = $wpdb->get_results($users_query);	

// Cyklus pro výpočet hodnoty POH a uložení do tabulky usermeta
foreach ($users as $user) {
    $user_id = $user->user_id;
	$pocet_brigadnickych_hodin = floatval($user->meta_value === NULL ? 0 : $user->meta_value);

    
    // Pokud je pocet_brigadnickych_hodin_radny_clen nulový nebo neexistuje v options, nastavíme hodnotu na 1, aby nedocházelo k dělení nulou
    if (!$pocet_brigadnickych_hodin_radny_clen) {
        $pocet_brigadnickych_hodin_radny_clen = 1;
    }
    
    // Výpočet hodnoty POH a uložení do tabulky usermeta
    $poh = $pocet_brigadnickych_hodin / $pocet_brigadnickych_hodin_radny_clen;
    update_user_meta($user_id, 'POH', number_format($poh, 4));
}

//NCP výpočet
//NČP=SOPx(1-POH).
//SOP – součet očekávaných plateb = ZČP x PŘČ = 1000 x 5=5000 Kč
//POH – procento z celkového počtu odpracovaných hodin řádných členů
// Načtení hodnot z tabulky options
$ltc_zcp = floatval(get_option('ltc_zcp'));
$pocet_radnych_clenu = intval(get_option('pocet_radnych_clenu'));

// Dotaz pro získání dat z tabulky usermeta
$users_query = "SELECT user_id, meta_value FROM {$wpdb->usermeta} WHERE meta_key = 'pocet_hodin'";	
$users = $wpdb->get_results($users_query);

// Cyklus pro výpočet hodnoty NCP a uložení do tabulky usermeta
foreach ($users as $user) {
    $user_id = $user->user_id;
    $clen = get_user_meta($user_id, 'clen', true);
    
    // Pokud uživatel není "R", přeskočíme výpočet
    if ($clen !== 'R') {
        continue;
    }
    
    $poh = floatval(get_user_meta($user_id, 'POH', true));
    
    // Výpočet hodnoty NCP a uložení do tabulky usermeta
    $ncp = $ltc_zcp * $pocet_radnych_clenu * (1 - $poh);
    update_user_meta($user_id, 'NCP', number_format($ncp,0));
}

//konec NCP výpočet	
//kalkulace NCP a POH pro všechny dohromady

// Načtení hodnot z tabulky options
$ltc_zcp = get_option('ltc_zcp');
$pocet_radnych_clenu = get_option('pocet_radnych_clenu');

// Výpočet hodnot "POH" a "NCP" pro každého uživatele s hodnotou "R" v poli "clen" z tabulky "usermeta"
$users_query = "SELECT user_id, meta_value FROM {$wpdb->usermeta} WHERE meta_key = 'pocet_hodin'";	
$users = $wpdb->get_results($users_query);

$ltc_poh = 0;
$ltc_ncp = 0;

foreach ($users as $user) {
    $user_id = $user->user_id;
    $clen = get_user_meta($user_id, 'clen', true);
    $poh = get_user_meta($user_id, 'POH', true);
    
    // Výpočet hodnoty "NCP" pro uživatele s hodnotou "R" v poli "clen"
    if ($clen === 'R') {
        $ncp = $ltc_zcp * $pocet_radnych_clenu * (1 - $poh);
		//"NCP"="ltc_zcp"x"pocet_radnych_clenu"x(1-"POH")
        update_user_meta($user_id, 'NCP', $ncp);
        
        $ltc_poh += $poh;
        $ltc_ncp += $ncp;
    }
}

// Uložení hodnot do tabulky options
update_option('ltc_poh', $ltc_poh);
update_option('ltc_ncp', $ltc_ncp);
//konec kalkulace NCP a POH pro všechny dohromady	

//počítá VCP
// Načtení hodnot z tabulky options
$ltc_ncp = intval(get_option('ltc_ncp'));
$ltc_zcp = intval(get_option('ltc_zcp'));
$pocet_radnych_clenu = intval(get_option('pocet_radnych_clenu'));

// Dotaz pro získání dat z tabulky usermeta
$users_query = "SELECT user_id, meta_value FROM {$wpdb->usermeta} WHERE meta_key = 'pocet_hodin'";	
$users = $wpdb->get_results($users_query);

// Cyklus pro výpočet hodnoty VCP a uložení do tabulky usermeta
$ltc_vcp = 0;
foreach ($users as $user) {
    $user_id = $user->user_id;
    $pocet_brigadnickych_hodin = intval($user->meta_value);
    $clen = get_user_meta($user_id, 'clen', true);
	    
    // Pokud uživatel není "R", přeskočíme ho
    if ($clen === 'R') {    
    
    // Získání hodnoty NCP a POH
    $poh = floatval(get_user_meta($user_id, 'POH', true));
    $ncp = floatval(get_user_meta($user_id, 'NCP', true));
    
    // Výpočet hodnoty VCP a uložení do tabulky usermeta
    $vcp = $ncp - ($ltc_ncp - $ltc_zcp * $pocet_radnych_clenu) / $pocet_radnych_clenu;
	if ($vcp < 0) {
  		$vcp = 0;
	}
	
    update_user_meta($user_id, 'VCP', intval($vcp));

}
	else {$vcp =0;}
	$ltc_vcp += $vcp;
}	
	update_option('ltc_vcp', $ltc_vcp);
//konec výpočtu VCP
}