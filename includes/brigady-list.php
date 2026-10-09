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

// Zpracování filtrů pro výběr dat
// vzor pro zadání [volunteer_hours_table date_from='2023-05-01' date_to='2024-04-30' count='100' whole_names="0"]
function volunteer_hours_table_shortcode($atts) {
  global $wpdb;
  $table_name = $wpdb->prefix . 'brigady_data';
  $date_from = isset($atts['date_from']) ? $atts['date_from'] : '0000-01-01';
  $date_to = isset($atts['date_to']) ? $atts['date_to'] : '9999-12-31';
  $count = isset($atts['count']) ? intval($atts['count']) : 100;
  $whole_names = isset($atts['whole_names']) ? intval($atts['whole_names']) : 0;	

  $sql = $wpdb->prepare(
    "SELECT bd.ID, bd.user_ID, u.display_name, bd.datum, bd.pocet_hodin, bd.napln_brigady
     FROM $table_name bd
     LEFT JOIN $wpdb->users u ON bd.user_ID = u.ID
     WHERE bd.datum BETWEEN %s AND %s
     ORDER BY bd.ID DESC
     LIMIT %d",
    $date_from, $date_to, $count
);
$results = $wpdb->get_results($sql);

  ob_start(); 
	$output	="";
	$output .= '<div class="volunteers-table-responsive">';
  	$output .='<table>';
      $output .='<tr>';
        $output .='<th>ID</th>';
		$output .= '<th>' . esc_html__( 'Jméno', 'ltc-extension' ) . '</th>';
        //$output .='<th>Datum</th>';
		$output .= '<th>' . esc_html__( 'Datum', 'ltc-extension' ) . '</th>';
        $output .= '<th>' . __( 'Počet hodin', 'ltc-extension' ) . '</th>';
        $output .= '<th>' . __( 'Náplň brigády', 'ltc-extension' ) . '</th>';
      $output .='</tr>';

      foreach ($results as $result) { 
        $output .='<tr>';
          $output .= '<td>' . esc_html( $result->ID ) . '</td>';
		  if ($whole_names == 0) {
   				 $output .= '<td>' . esc_html( display_name_shortcut($result->display_name )) . '</td>';
			} else {
    			 $output .= '<td>' . esc_html( $result->display_name ) . '</td>';
			}
          
          $output .= '<td>' . esc_html( $result->datum ) . '</td>';
          $output .= '<td>' . esc_html( $result->pocet_hodin ) . '</td>';
          $output .= '<td>' . esc_html( $result->napln_brigady ) . '</td>';
        $output .='</tr>';
       }

  $output .='</table>';
	$output .= "</div'>";
    ob_end_clean();

    return $output;
}
add_shortcode('volunteer_hours_table', 'volunteer_hours_table_shortcode');

//zařídí zkrácení jména
function display_name_shortcut($display_name){
	if($display_name == NULL) {
		$display_name = sanitize_text_field(__( 'Unknown name', 'ltc-extension' ));
	}
	if(mb_strlen($display_name, 'UTF-8') > 8) {
  					  $first_part = mb_substr($display_name, 0, 4, 'UTF-8');
   					 $last_part = mb_substr($display_name, -4, null, 'UTF-8');
   					 $display_name = $first_part . $last_part;
				}
	return $display_name;
}