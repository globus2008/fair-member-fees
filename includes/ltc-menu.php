<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) { exit; }

function volunteers_hours_menu() {

    // Vytvoření rodičovského menu
    add_menu_page(
        __( 'Brigádnické hodiny', 'volunteers-hours' ),
        __( 'Brigádnické hodiny', 'volunteers-hours' ),
        'manage_options',
        'volunteers-hours',
        'volunteers_hours_submenu_page_callback',
		'dashicons-clock'
    );

    // Další položky menu

    add_submenu_page(
        'volunteers-hours',
        __( 'Členové klubu', 'volunteers-hours' ),
        __( 'Členové klubu', 'volunteers-hours' ),
        'manage_options',
        'club-members',
        'clenove_plugin_main_page'
    );

    // Další submenu položky
/*    global $wpdb;
    if ($wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}bookacti_bookings'") == "{$wpdb->prefix}bookacti_bookings") {
        add_submenu_page(
            'volunteers-hours',
            __( 'Základní nastavení', 'volunteers-hours' ),
            __( 'Základní nastavení', 'volunteers-hours' ),
            'manage_options',
            'my-stripe-settings',
            'my_stripe_settings_page'
        );
    }

    add_submenu_page(
        'volunteers-hours',
        __( 'Activity nastavení', 'volunteers-hours' ),
        __( 'Activity nastavení', 'volunteers-hours' ),
        'manage_options',
        'activity-nastaveni',
        'activity_nastaveni_submenu_page_callback'
    ); */
}

add_action( 'admin_menu', 'volunteers_hours_menu' );

function volunteers_hours_submenu_page_callback() { 
    echo '<div class="wrap">';
    echo volunteers_hours_submenu_page_content();
    echo '</div>';
}

//$page_title: Název stránky, který bude zobrazen v záhlaví stránky.
//$menu_title: Název stránky, který bude zobrazen v menu.
//$capability: Oprávnění, které uživatel potřebuje k zobrazení stránky (v tomto případě "manage_options" znamená, že uživatel //musí mít oprávnění pro správu vlastností webu).
//$menu_slug: Unikátní identifikátor stránky v URL adresovém řádku.
//$function: Funkce, která se spustí, když uživatel navštíví tuto stránku.
//

function avolunteers_hours2_register_settings() {
    register_setting( 'avolunteers_hours2_settings', 'avolunteers_hours_settings' );
}
add_action( 'admin_init', 'avolunteers_hours2_register_settings' );

function volunteers_hours_submenu_page_content() {
    $output = "<h1>" . __( 'Všeobecné informace k pluginu LTC extension', 'volunteers-hours' ) . "</h1>";
    $output .= "<h3>" . __( 'Přispějte na trvale udržitelný vývoj tohoto pluginu pomocí', 'volunteers-hours' ) . " <a href='https://www.paypal.com/donate/?business=S295WXEHMKLF6&no_recurring=0&item_name=Contribution+to+the+development+of+Wordpress+plugin+LTC+extension.&currency_code=EUR' target='_blank'>Paypal</a> " . __( 'brány.', 'volunteers-hours' ) . "</h3>";
    $output .= "<p><h2>" . __( 'Tento plugin slouží k následujícím základním účelům:', 'volunteers-hours' ) . "</h2></p>";
    $output .= "<ol><h4>";
    $output .= "<li>" . __( 'Evidence brigádnických hodin.', 'volunteers-hours' ) . "</li>";
    $output .= "<li>" . __( 'Kalkulace členského poplatku na základě množství brigádnických hodin.', 'volunteers-hours' ) . "</li>";
    $output .= "<li>" . __( 'Platba členského poplatku pomocí platební brány Stripe a evidence datumu zaplacení.', 'volunteers-hours' ) . ": " . __( 'vyžaduje plugin', 'volunteers-hours' ) . " <a href='https://wordpress.org/plugins/booking-activities/' target='_blank'>Booking Activities</a>.</li>";
    $output .= "<li>" . __( 'Platba rezervací spravovaných pluginem Booking Activities pomocí brány Stripe.', 'volunteers-hours' ) . ": " . __( 'vyžaduje plugin', 'volunteers-hours' ) . " <a href='https://wordpress.org/plugins/booking-activities/' target='_blank'>Booking Activities</a>.</li>";
    $output .= "<li>" . __( 'Přehled uskutečněných rezervací.', 'volunteers-hours' ) . ": " . __( 'vyžaduje plugin', 'volunteers-hours' ) . " <a href='https://wordpress.org/plugins/booking-activities/' target='_blank'>Booking Activities</a>.</li>";
    $output .= "<li>" . __( 'Soukromé zprávy pro členy maji jiný popisek a dovětek o viditelnosti na konci stránky.', 'volunteers-hours' ) . "</li>";
    $output .= "</h4></ol>";
    $output .= "<p>" . __( 'Tento plugin je uzpůsobený pro rezervační systém tenisových kurtů, kde se dají rezervace zaplatit se zvolenou slevou podle toho, zda hraje člen klubu, hráč mimo klub nebo dítě do určitého věku. Zároveň jsou k dispozici různé statistiky rezervací. Pro členy klubu je k dispozici formulář evidující brigádnické hodiny, z kterých se potom kalkuluje výše členského příspěvku.', 'volunteers-hours' ) . "</p>";
    $output .= '<table class="volunteers-hours-table">';
    $output .= "<tr>";
    $output .= '<th style="width: 50px;">' . __( 'Ad Nr.', 'volunteers-hours' ) . '</th>';
    $output .= '<th style="width: 400px;">' . __( 'Shortcode pro vložení na stránku', 'volunteers-hours' ) . '</th>';
    $output .= "<th>" . __( 'Popis funkce', 'volunteers-hours' ) . "</th>";
    $output .= "</tr>";
    $output .= "<tr>";
    $output .= "<td>1.1</td>";
    $output .= "<td>[brigady_form]</td>";
    $output .= "<td>" . __( 'Na stránku vloží jednoduché pole, kde se zvolí počet odpracovaných hodin a popis činnosti.', 'volunteers-hours' ) . "</td>";
    $output .= "</tr>";
    $output .= "<tr>";
    $output .= "<td>1.2</td>";
    $output .= "<td>[volunteer_hours_table]</td>";
    $output .= "<td>" . __( 'Vytvoří seznam záznamů brigádnických hodin. Datum od a datum do vezmu ze základního nastavení v admin menu. Na stránku vypíše 100 posledních záznamů.', 'volunteers-hours' ) . "</td>";
    $output .= "</tr>";
    $output .= "<tr>";
    $output .= "<td>1.3</td>";
    $output .= "<td>[volunteer_hours_table date_from='2023-05-01' date_to='2024-04-30' count='100']</td>";
    $output .= "<td>" . __( 'Vytvoří seznam záznamů brigádnických hodin. Datum od, datum do a počet záznamů je udán pomocí parametrů. Pokud se některý z parametrů neuvede, vezme se výchozí z výše uvedeného popisu.', 'volunteers-hours' ) . "</td>";
    $output .= "</tr>";
    $output .= "<tr>";
    $output .= "<td>2.1</td>";
    $output .= "<td>[clenske_poplatky]</td>";
    $output .= "<td>" . __( 'Na obrazovku vypíše kalkulaci členských poplatků na základě odpracovaných brigádnických hodin. Jako výchozí použije tyto hodnoty:', 'volunteers-hours' ) . "<ol>";
    $output .= "<li>" . __( 'Seznam členů vezme z administračního rozhraní podle vložených členů. Do kalkulace zahrne pouze řádné členy, protože členové čestní jsou zproštěni členského poplatku.', 'volunteers-hours' ) . "</li>";
    $output .= "<li>" . __( 'Výsledná částka z platbě se zaokrouhlí na celá čísla.', 'volunteers-hours' ) . "</li>";
    $output .= "<li>" . __( 'Datum od a datum do se vezme ze Základního nastavení v admin menu.', 'volunteers-hours' ) . "</li>";
    $output .= "<li>" . __( 'Tlačítko pro platbu členského příspěvku není zobrazeno.', 'volunteers-hours' ) . "</li>";
    $output .= "<li>" . __( 'Tlačítko pro vložení datumu platby členského příspěvku se zobrazuje.', 'volunteers-hours' ) . "</li>";
    $output .= "<li>" . __( 'Rozkrytá kalkulace členského příspěvku.', 'volunteers-hours' ) . "</li>";
    $output .= "<li>" . __( 'Popisek nad se zobrazuje.', 'volunteers-hours' ) . "</li>";
    $output .= "<li>" . __( 'Přehled pod tabulkou se zobrazuje.', 'volunteers-hours' ) . "</li>";
    $output .= "</ol></td>";
    $output .= "</tr>";
    $output .= "<tr>";
    $output .= "<td>2.2</td>";
    $output .= "<td>[clenske_poplatky ltc_ids='1,6,7,8,9,11,12,17' ltc_vcp_round='0' start_date='2023-05-01' end_date='2024-04-30' hide_payment_day='1' hide_description_above='0' hide_description_below='0' hide_payment_button='0' hide_calculation='0' hide_date_button='1']</td>";
  $output .= "<td>" . __( 'Výše uvedené výchozí hodnoty lze změnit pomocí těchto atributů. Mohou být uvedeny i jen některé. ltc_ids="1,6,7,8,9,11,12,17" je seznam user_id členů, u kterých bude počítán členský příspěvek.', 'volunteers-hours' ) . "</td>";
    $output .= "</tr>";
    $output .= "<tr>";
    $output .= "<td>3.1</td>";
    $output .= "<td>[stripe_payment_button platba_typ='1']</td>";
    $output .= "<td>" . __( 'Částka k platbě se vezme z tabulky členských příspěvků.', 'volunteers-hours' ) . "</td>";
    $output .= "</tr>";
	$output .= "<tr>";
    $output .= "<td>3.2</td>";
    $output .= "<td>[insert_payment_date]</td>";
    $output .= "<td>" . __( 'Tento shortcode se vloží na stránku odkazující na úspěšně provedenou platbu pomocí Stripe. Zajistí tak aktualizaci dat v databázi.', 'volunteers-hours' ) . "</td>";
    $output .= "</tr>";
	$output .= "<tr>";
	$output .= "<td>3.3</td>";
    $output .= "<td>[redirect_po_platbe]</td>";
    $output .= "<td>" . __( 'Tento shortcode se vloží na stránku odkazující na úspěšně provedenou platbu pomocí Stripe, hned za shortcode [insert_payment_date]. Zajistí tak přesměrování stránky po 4 vteřinách na domovskou stránku.', 'volunteers-hours' ) . "</td>";
    $output .= "</tr>";
    $output .= "<tr>";
    $output .= "<td>4.1</td>";
    $output .= "<td>[rezervace_list]</td>";
    $output .= "<td>" . __( 'Vytvoří seznam rezervací provedených přes plugin Booking Activities a umožní zaškrtnout ty, které se budou nyní platit a zároveň specifikovat velikost slevy ze základní ceny.', 'volunteers-hours' ) . "</td>";
    $output .= "</tr>";
    $output .= "<tr>";
    $output .= "<td>4.2</td>";
    $output .= "<td>[stripe_payment_button]</td>";
    $output .= "<td>" . __( 'Zobrazí tlačítko pro platbu. Ve výchozím stavu bez uvedení atributů bude částka="250" platba rezervace.', 'volunteers-hours' ) . "</td>";
    $output .= "</tr>";
    $output .= "<tr>";
    $output .= "<td>4.3</td>";
    $output .= "<td>[stripe_payment_button platba_castka='250' platba_typ='0']</td>";
    $output .= "<td>" . __( 'Zobrazí tlačítko pro platbu. Ve výchozím stavu se částka vezme z tabulky rezervací. Parametry viz výše. platba_typ: 0\\=rezervace, 1\\=prispevek', 'volunteers-hours' ) . "</td>";
    $output .= "</tr>";
	$output .= "<tr>";
    $output .= "<td>4.4</td>";
    $output .= "<td>[aktualizace_bookings_data]</td>";
    $output .= "<td>" . __( 'Tento shortcode se vloží na stránku odkazující na úspěšně provedenou platbu pomocí Stripe. Zajistí tak aktualizaci dat v databázi.', 'volunteers-hours' ) . "</td>";
    $output .= "</tr>";
	$output .= "<tr>";
	$output .= "<td>4.5</td>";
    $output .= "<td>[redirect_po_platbe]</td>";
    $output .= "<td>" . __( 'Tento shortcode se vloží na stránku odkazující na úspěšně provedenou platbu pomocí Stripe, hned za shortcode [aktualizace_bookings_data]. Zajistí tak přesměrování stránky po 4 vteřinách na domovskou stránku.', 'volunteers-hours' ) . "</td>";
    $output .= "</tr>";
    $output .= "<tr>";
    $output .= "<td>5.1</td>";
    $output .= "<td>[bookings_summary]</td>";
    $output .= "<td>" . __( 'Zobrazí tabulku se všeobecným přehledem všech rezervací, evidencí neproplacených a proplacených rezervací, rozdělených mezi členy a nečleny klubu.', 'volunteers-hours' ) . "</td>";
    $output .= "</tr>";
    $output .= "<tr>";
    $output .= "<td>5.2</td>";
    $output .= "<td>[platby_table display_row='5' count_min='0']</td>";
    $output .= "<td>" . __( 'Zobrazí provedené platby přes platební bránu. Parametr count_min="0" v zásadě definuje, zda budou zahrnuty do tabulky i slevy 100 % s nulovou platbou.', 'volunteers-hours' ) . "</td>";
    $output .= "</tr>";
    $output .= "</table>";

    return $output;
}
function volunteers_hours_submenu_page_styles() {
    echo '<style>
        .volunteers-hours-table {
            width: 100%;
            border-collapse: collapse;
        }

        .volunteers-hours-table th,
        .volunteers-hours-table td {
            border: 1px solid #ddd;
            padding: 8px;
        }

        .volunteers-hours-table th {
            background-color: #f2f2f2;
            font-weight: bold;
            text-align: left;
        }

        .volunteers-hours-table tr:nth-child(even) {
            background-color: #f9f9f9;
        }
    </style>';
}

add_action('admin_menu', 'volunteers_hours_menu');
add_action('admin_print_styles', 'volunteers_hours_submenu_page_styles');



