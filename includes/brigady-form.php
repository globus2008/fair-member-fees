<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) { exit; }

// Vytvoření tabulky brigady_data, pokud neexistuje
global $wpdb;
$table_name = $wpdb->prefix . 'brigady_data';
$charset_collate = $wpdb->get_charset_collate();
if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
  $sql = "CREATE TABLE $table_name (
          ID bigint(20) NOT NULL AUTO_INCREMENT,
          user_ID bigint(20) NOT NULL,
          datum date NOT NULL,
          pocet_hodin decimal(3,1) NOT NULL,
		  napln_brigady varchar(150) NOT NULL,
          PRIMARY KEY (ID)
        ) $charset_collate;";
  require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
  dbDelta($sql);
}

// Zpracování odeslaného formuláře
function brigady_form_content_filter($content) {
$brigady_message = '';	
if (isset($_POST['brigady_form_submit'])) {

  $datum = date('Y-m-d');
  $user_id = intval($_POST['user_id']);
  $pocet_hodin = floatval($_POST['pocet_hodin']);
  $napln_brigady = strval($_POST['napln_brigady']);	
  $napln_brigady = (strlen($napln_brigady) > 149) ? substr($napln_brigady,0,149) : $napln_brigady;		

  if ($user_id==0) {
    $brigady_message = '<p><b>' . __( 'Pro odeslání hodnot musíte být přihlášeni ke svému účtu!', 'ltc-extension' ) . '</b></p>';

    return $brigady_message.$content;
  }
  // Zkontrolujeme, zda existuje řádek se shodným datem a ID uživatele
  global $wpdb;
  $table_name = $wpdb->prefix . 'brigady_data';
  $existing_data = $wpdb->get_row("SELECT * FROM $table_name WHERE user_ID=$user_id AND datum='$datum'");

  // Pokud existuje, aktualizujeme hodnotu pocet_hodin
  if ($existing_data) {
    $wpdb->update($table_name, array('pocet_hodin' => $pocet_hodin), array('ID' => $existing_data->ID));
	$wpdb->update($table_name, array('napln_brigady' => $napln_brigady), array('ID' => $existing_data->ID));
	$brigady_message = '<p><b>' . __( 'Hodnoty byly aktualizovány!', 'ltc-extension' ) . '</b></p>';
	  
  } 
  // Jinak vytvoříme nový záznam
  else {
	  if ($pocet_hodin==0) {   			
    		return $brigady_message.$content;
 		 }  
    $wpdb->insert($table_name, array('user_ID' => $user_id, 'datum' => $datum, 'pocet_hodin' => $pocet_hodin, 'napln_brigady' => $napln_brigady));
	$brigady_message = '<p><b>' . esc_html__( 'Hodnoty byly zaznamenány!', 'ltc-extension' ) . '</b></p>';
	
  }
}
	return $brigady_message.$content;
}
add_filter('the_content', 'brigady_form_content_filter');

// Výpis formuláře
function brigady_form_shortcode() {
  ob_start(); 
  $output = '';
  $output .= "<div class='volunteers-table-responsive'>";
  $output .= '<table>';		
  $output .= '<form method="post">';
  $output .= '<input type="hidden" name="user_id" value="' . get_current_user_id() . '">';
  $output .= '<tr>';
  $output .= '<th>';	
  $output .= '<label for="napln_brigady">' . esc_html__( 'Náplň brigády', 'ltc-extension' ) . ':</label>';
  $output .= '</th>';	
  $output .= '<th>';	
  $output .= '<label for="pocet_hodin">' . esc_html__( 'Počet hodin', 'ltc-extension' ) . ':</label>';
  $output .= '</th>';	
  $output .= '</tr>';
  $output .= '<tr>';
  $output .= '<td>';
  $output .= '<textarea id="napln_brigady" name="napln_brigady" required rows="1"></textarea> ';
  $output .= '</td>';
  $output .= '<td>';
  $output .= '<select id="pocet_hodin" name="pocet_hodin">';
  for ($i = 0; $i <= 8; $i += 0.5) {
    $output .= '<option value="' . $i . '">' . $i . '</option>';
  } 
  $output .= '</select>';
  $output .= '</td>';
  $output .= '</tr>';
  $output .= '<tr>';
  $output .= '<td colspan = "2">';
  $output .= '<input type="submit" name="brigady_form_submit" value="' . esc_html__( 'Přidat hodiny', 'ltc-extension' ) . '">';
  $output .= '</td>';
  $output .= '</tr>';	
  $output .= '</form>';
  $output .= '</table>';
  $output .= "</div>";	

  ob_get_clean();
  return $output;
}

add_shortcode('brigady_form', 'brigady_form_shortcode');