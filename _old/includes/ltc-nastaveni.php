<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) { exit; }

// Funkce pro vytvoření stránky při aktivaci pluginu
function create_ltc_page() {
    // Zkontrolujte, zda stránka již existuje
    $page_id = get_option('ltc_page_id');
    if ($page_id) {
        return; // Stránka již existuje, není třeba vytvářet nový obsah
    }
    // Vytvoření stránky
    $page_title = __('Postup výpočtu výše členského příspěvku', 'ltc-extension');
	$page_content = postup_vypoctu_page();

    // Vytvoření nové stránky
    $page = array(
        'post_title'    => $page_title,
        'post_content'  => $page_content,
        'post_status'   => 'publish',
        'post_type'     => 'page'
    );

    // Vložení stránky do databáze WordPress
    $page_id = wp_insert_post($page);

    // Uložení ID stránky pro budoucí použití
    update_option('ltc_page_id', $page_id);
}

// Funkce pro smazání stránky při odinstalaci pluginu
function delete_ltc_page() {
    // Získání ID stránky uložené v pluginu
    $page_id = get_option('ltc_page_id');

    // Smazání stránky
    if ($page_id) {
        wp_delete_post($page_id, true);
    }

    // Odstranění uloženého ID stránky
    delete_option('ltc_page_id');
}

//obsah stránky Postup výpočtu
function postup_vypoctu_page()	{
	$output = ""; // Inicializace proměnné $output
	$output .= "<p>" . esc_html__( 'Výše členského příspěvku se odvijí od velikosti brigádnického nasazení, která má svoji měřitelnou podobu v počtu odpracovaných hodin, které jsou zaznamenané v tabulce Brigád. Výši členské příspěvku vypočítá výkonný výbor. Od členů se požaduje pouze provádění zápisu odpracovaných hodin do tabulky Brigád.', 'ltc-extension' ) . "</p>";
	$output .= "<p>" . esc_html__( 'Pro pochopení principu výpočtu výše členského příspěvku sledujte prosím následující příklad.', 'ltc-extension' ) . "</p>";
	$output .= "<p>" . esc_html__( 'Vycházejme z následující tabulky č.1, která udává počet členů, typ jejich členství a počet odpracovaných hodin.', 'ltc-extension' ) . "</p>";
	$output .= esc_html__( 'Tabulka č.1', 'ltc-extension' );
    $output .= '<table>';

    // Vytvoření hlavičky tabulky
    $output .= '<tr>';
    $output .= "<th>". esc_html__( 'Jméno', 'ltc-extension' )."</th>";
    $output .= '<th>' . esc_html__( 'Člen', 'ltc-extension' ) . '</th>';
    $output .= '<th>' . esc_html__( 'Počet brigádnických hodin', 'ltc-extension' ) . '</th>';
    $output .= '</tr>';

    $output .= '<tr>';
    $output .= '<td>' . esc_html__( 'Člen', 'ltc-extension' ) . ' 1</td>';
    $output .= '<td>' . esc_html__( 'Ř', 'ltc-extension' ) . '</td>';
    $output .= '<td>0</td>';
    $output .= '</tr>';

    $output .= '<tr>';
    $output .= '<td>' . esc_html__( 'Člen', 'ltc-extension' ) . ' 2</td>';
    $output .= '<td>' . esc_html__( 'Ř', 'ltc-extension' ) . '</td>';
    $output .= '<td>10</td>';
    $output .= '</tr>';
	
    $output .= '<tr>';
    $output .= '<td>' . esc_html__( 'Člen', 'ltc-extension' ) . ' 3</td>';
    $output .= '<td>' . esc_html__( 'Ř', 'ltc-extension' ) . '</td>';
    $output .= '<td>15</td>';
    $output .= '</tr>';
	
    $output .= '<tr>';
    $output .= '<td>' . esc_html__( 'Člen', 'ltc-extension' ) . ' 4</td>';
    $output .= '<td>' . esc_html__( 'Ř', 'ltc-extension' ) . '</td>';
    $output .= '<td>20</td>';
    $output .= '</tr>';
	
    $output .= '<tr>';
    $output .= '<td>' . esc_html__( 'Člen', 'ltc-extension' ) . ' 5</td>';
    $output .= '<td>' . esc_html__( 'Ř', 'ltc-extension' ) . '</td>';
    $output .= '<td>25</td>';
    $output .= '</tr>';
	
    $output .= '<tr>';
    $output .= '<td>' . esc_html__( 'Člen', 'ltc-extension' ) . ' 6</td>';
    $output .= '<td>' . esc_html__( 'Č', 'ltc-extension' ) . '</td>';
    $output .= '<td>20</td>';
    $output .= '</tr>';
		
    $output .= '<tr>';
    $output .= '<td>' . esc_html__( 'Celkem', 'ltc-extension' ) . '</td>';
    $output .= '<td>' . esc_html__( '5xŘ +1xČ', 'ltc-extension' ) . '</td>';
    $output .= '<td>' . esc_html__( 'Součet 90 hodin', 'ltc-extension' ) . '</td>';
    $output .= '</tr>';

    $output .= '</table>';

	$output .= "<p>" . esc_html__( 'Z tabulky č.1 se dozvídáme, že členská základna má 5 řádných členů a 1 čestného člena, který je v souladu se stanovami od platby členského příspěvku osvobozen.', 'ltc-extension' ) . "</p>";
	$output .= "<p>" . esc_html__( 'V daném roce vyhlásil výkonný výbor základní členský příspěvek ve výši 1000 Kč. Od 5 řádných členů tedy bude očekávat dohromady platbu 5000 Kč. Zároveň bylo uvedeno, že se příspěvek bude měnit podle odpracovaných hodin pro LTC. Ve výpočtu se tedy budou objevovat tyto proměnné:', 'ltc-extension' ) . "</p>";
	$output .= '<ol>';
    $output .= '<li>' . esc_html__( 'ZČP – základní členský příspěvek = 1000 Kč', 'ltc-extension' ) . '</li>';
    $output .= '<li>' . esc_html__( 'PŘČ – počet řádných členů = 5', 'ltc-extension' ) . '</li>';
    $output .= '<li>' . esc_html__( 'SOP – součet očekávaných plateb = ZČP x PŘČ = 1000 x 5=5000 Kč', 'ltc-extension' ) . '</li>';
    $output .= '<li>' . esc_html__( 'POH – procento z celkového počtu odpracovaných hodin řádných členů (mění se u každého člena dle Tabulky č.2', 'ltc-extension' ) . '</li>';
    $output .= '<li>' . esc_html__( 'NČP – neredukovaný členský příspěvek dle Tabulky č.3', 'ltc-extension' ) . '</li>';
	$output .= '<li>' . esc_html__( 'VČP – vypočtený členský příspěvek', 'ltc-extension' ) . '</li>';
    $output .= '</ol>';
	$output .= "<p>" . esc_html__( 'Nyní se provede nový součet celkových hodin, kde se odečtou hodiny čestných členů. Pak se vypočítá parametr POH, který udává procentuální podíl na celkovém počtu odpracovaných hodin.', 'ltc-extension' ) . "</p>";
	$output .= esc_html__( 'Tabulka č.2', 'ltc-extension' );
    $output .= '<table>';

    // Vytvoření hlavičky tabulky
    $output .= '<tr>';
    $output .= "<th>". esc_html__( 'Jméno', 'ltc-extension' )."</th>";
    $output .= '<th>' . esc_html__( 'Člen', 'ltc-extension' ) . '</th>';
    $output .= '<th>' . esc_html__( 'Počet brigádnických hodin', 'ltc-extension' ) . '</th>';
    $output .= '<th>' . esc_html__( 'Zlomek z celkového počtu', 'ltc-extension' ) . '</th>';
	$output .= '<th>' . esc_html__( 'POH', 'ltc-extension' ) . '</th>';
    $output .= '</tr>';

    $output .= '<tr>';
    $output .= '<td>' . esc_html__( 'Člen', 'ltc-extension' ) . ' 1</td>';
    $output .= '<td>' . esc_html__( 'Ř', 'ltc-extension' ) . '</td>';
    $output .= '<td>0</td>';
    $output .= '<td>0</td>';
    $output .= '<td>0 %</td>';
    $output .= '</tr>';

    $output .= '<tr>';
    $output .= '<td>' . esc_html__( 'Člen', 'ltc-extension' ) . ' 2</td>';
    $output .= '<td>' . esc_html__( 'Ř', 'ltc-extension' ) . '</td>';
    $output .= '<td>10</td>';
	$output .= '<td>10/70</td>';
	$output .= '<td>14 %</td>';
    $output .= '</tr>';
	
    $output .= '<tr>';
    $output .= '<td>' . esc_html__( 'Člen', 'ltc-extension' ) . ' 3</td>';
    $output .= '<td>' . esc_html__( 'Ř', 'ltc-extension' ) . '</td>';
    $output .= '<td>15</td>';
	$output .= '<td>15/70</td>';
	$output .= '<td>21 %</td>';
    $output .= '</tr>';
	
    $output .= '<tr>';
    $output .= '<td>' . esc_html__( 'Člen', 'ltc-extension' ) . ' 4</td>';
    $output .= '<td>' . esc_html__( 'Ř', 'ltc-extension' ) . '</td>';
    $output .= '<td>20</td>';
	$output .= '<td>20/70</td>';
	$output .= '<td>29 %</td>';
    $output .= '</tr>';
	
    $output .= '<tr>';
    $output .= '<td>' . esc_html__( 'Člen', 'ltc-extension' ) . ' 5</td>';
    $output .= '<td>' . esc_html__( 'Ř', 'ltc-extension' ) . '</td>';
    $output .= '<td>25</td>';
	$output .= '<td>25/70</td>';
	$output .= '<td>36 %</td>';
    $output .= '</tr>';
	
    $output .= '<tr>';
    $output .= '<td>' . esc_html__( 'Člen', 'ltc-extension' ) . ' 6</td>';
    $output .= '<td>' . esc_html__( 'Č', 'ltc-extension' ) . '</td>';
    $output .= '<td>' . esc_html__( 'nezapočítává se', 'ltc-extension' ) . '</td>';
	$output .= '<td></td>';
	$output .= '<td></td>';
    $output .= '</tr>';
		
    $output .= '<tr>';
    $output .= '<td>' . esc_html__( 'Celkem', 'ltc-extension' ) . '</td>';
    $output .= '<td>' . esc_html__( '5xŘ +1xČ', 'ltc-extension' ) . '</td>';
    $output .= '<td>' . esc_html__( 'započítaných 70 hodin celkem', 'ltc-extension' ) . '</td>';
	$output .= '<td></td>';
	$output .= '<td>' . esc_html__( 'celkem 100 %', 'ltc-extension' ) . '</td>';
    $output .= '</tr>';

    $output .= '</table>';
	$output .= "<p>" . esc_html__( 'Nyní provedeme v Tabulce č.3 výpočet Neredukovaného členského příspěvku, kdy u každého člena propočítáme číslo dané vzorcem NČP=SOPx(1-POH).', 'ltc-extension' ) . "</p>";
	$output .= esc_html__( 'Tabulka č.3', 'ltc-extension' );
    $output .= '<table>';

    // Vytvoření hlavičky tabulky
    $output .= '<tr>';
    $output .= "<th>". esc_html__( 'Jméno', 'ltc-extension' )."</th>";
    $output .= '<th>' . esc_html__( 'Člen', 'ltc-extension' ) . '</th>';
	$output .= '<th>' . esc_html__( 'POH', 'ltc-extension' ) . '</th>';
	$output .= '<th>' . esc_html__( 'NČP=SOPx(1-POH)', 'ltc-extension' ) . '</th>';
    $output .= '</tr>';

    $output .= '<tr>';
    $output .= '<td>' . esc_html__( 'Člen', 'ltc-extension' ) . ' 1</td>';
    $output .= '<td>' . esc_html__( 'Ř', 'ltc-extension' ) . '</td>';
    $output .= '<td>0 %</td>';
	$output .= '<td>5000x(1-0)=5000</td>';
    $output .= '</tr>';

    $output .= '<tr>';
    $output .= '<td>' . esc_html__( 'Člen', 'ltc-extension' ) . ' 2</td>';
    $output .= '<td>' . esc_html__( 'Ř', 'ltc-extension' ) . '</td>';
	$output .= '<td>14 %</td>';
	$output .= '<td>5000x(1-0,14)=4300</td>';
    $output .= '</tr>';
	
    $output .= '<tr>';
    $output .= '<td>' . esc_html__( 'Člen', 'ltc-extension' ) . ' 3</td>';
    $output .= '<td>' . esc_html__( 'Ř', 'ltc-extension' ) . '</td>';
	$output .= '<td>21 %</td>';
	$output .= '<td>5000x(1-0,21)=3950</td>';
    $output .= '</tr>';
	
    $output .= '<tr>';
    $output .= '<td>' . esc_html__( 'Člen', 'ltc-extension' ) . ' 4</td>';
    $output .= '<td>' . esc_html__( 'Ř', 'ltc-extension' ) . '</td>';
	$output .= '<td>29 %</td>';
	$output .= '<td>5000x(1-0,29)=3550</td>';
    $output .= '</tr>';
	
    $output .= '<tr>';
    $output .= '<td>' . esc_html__( 'Člen', 'ltc-extension' ) . ' 5</td>';
    $output .= '<td>' . esc_html__( 'Ř', 'ltc-extension' ) . '</td>';
	$output .= '<td>36 %</td>';
	$output .= '<td>5000x(1-0,36)=3200</td>';
    $output .= '</tr>';
		
    $output .= '<tr>';
    $output .= '<td>' . esc_html__( 'Celkem', 'ltc-extension' ) . '</td>';
    $output .= '<td>' . esc_html__( '5xŘ +1xČ', 'ltc-extension' ) . '</td>';
	$output .= '<td>' . esc_html__( 'celkem 100 %', 'ltc-extension' ) . '</td>';
	$output .= '<td>' . esc_html__( 'Celkem 20000', 'ltc-extension' ) . '</td>';
    $output .= '</tr>';

    $output .= '</table>';	
	$output .= "<p>" . esc_html__( 'A nyní se provede v Tabulce č.4 konečný krok, kdy se NČP změní na vypočtený členský příspěvek (VČP).', 'ltc-extension' ) . "</p>";
		$output .= esc_html__( 'Tabulka č.4', 'ltc-extension' );
    $output .= '<table>';

    // Vytvoření hlavičky tabulky
    $output .= '<tr>';
    $output .= "<th>". esc_html__( 'Jméno', 'ltc-extension' )."</th>";
    $output .= '<th>' . esc_html__( 'Člen', 'ltc-extension' ) . '</th>';
	$output .= '<th>' . esc_html__( 'NČP', 'ltc-extension' ) . '</th>';
	$output .= '<th>' . esc_html__( 'VČP=NČP-(celkem NČP-POH)/PŘČ', 'ltc-extension' ) . '</th>';
    $output .= '</tr>';

    $output .= '<tr>';
    $output .= '<td>' . esc_html__( 'Člen', 'ltc-extension' ) . ' 1</td>';
    $output .= '<td>' . esc_html__( 'Ř', 'ltc-extension' ) . '</td>';
	$output .= '<td>5000x(1-0)=5000</td>';
	$output .= '<td>5000-(20000-5000)/5=2000 Kč</td>';
    $output .= '</tr>';

    $output .= '<tr>';
    $output .= '<td>' . esc_html__( 'Člen', 'ltc-extension' ) . ' 2</td>';
    $output .= '<td>' . esc_html__( 'Ř', 'ltc-extension' ) . '</td>';
	$output .= '<td>5000x(1-0,14)=4300</td>';
	$output .= '<td>4300-(20000-5000)/5=1300 Kč</td>';
    $output .= '</tr>';
	
    $output .= '<tr>';
    $output .= '<td>' . esc_html__( 'Člen', 'ltc-extension' ) . ' 3</td>';
    $output .= '<td>' . esc_html__( 'Ř', 'ltc-extension' ) . '</td>';
	$output .= '<td>5000x(1-0,21)=3950</td>';
	$output .= '<td>3950-(20000-5000)/5=950 Kč</td>';
    $output .= '</tr>';
	
    $output .= '<tr>';
    $output .= '<td>' . esc_html__( 'Člen', 'ltc-extension' ) . ' 4</td>';
    $output .= '<td>' . esc_html__( 'Ř', 'ltc-extension' ) . '</td>';
	$output .= '<td>5000x(1-0,29)=3550</td>';
	$output .= '<td>3550-(20000-5000)/5=550 Kč</td>';
    $output .= '</tr>';
	
    $output .= '<tr>';
    $output .= '<td>' . esc_html__( 'Člen', 'ltc-extension' ) . ' 5</td>';
    $output .= '<td>' . esc_html__( 'Ř', 'ltc-extension' ) . '</td>';
	$output .= '<td>5000x(1-0,36)=3200</td>';
	$output .= '<td>3200-(20000-5000)/5=200 Kč</td>';
    $output .= '</tr>';
		
    $output .= '<tr>';
    $output .= '<td>' . esc_html__( 'Celkem', 'ltc-extension' ) . '</td>';
    $output .= '<td>' . esc_html__( '5xŘ +1xČ', 'ltc-extension' ) . '</td>';
	$output .= '<td>' . esc_html__( 'Celkem 20000', 'ltc-extension' ) . '</td>';
	$output .= '<td>' . esc_html__( 'Celkem 5000 Kč', 'ltc-extension' ) . '</td>';
    $output .= '</tr>';

    $output .= '</table>';	
	$output .= "<p>" . esc_html__( 'Nejvíce zaplatí člen č.1 a to 2000 Kč při žádné odpracované hodině. Nejméně zaplatí člen č.5, který do LTC kasičky pošle jen 200 Kč.' , 'ltc-extension' ) . "</p>";
	$output .= "<p>" . esc_html__( 'Tento příspěvek se bude kalkulovat po jarní brigádě. Po provedené kalkulaci se uveřejní nová čistá tabulka na stránce Brigády. Tam se budou zapisovat i podzimní brigády a proto do celkového součtu hodin, které mají vliv na výpočet členského příspěvku, bude brán zřetel i na odpracované hodiny ze závěru loňské sezóny.' , 'ltc-extension' ) . "</p>";
	
	return $output;
}
function update_ltc_page_content_on_activation() {
    $ltc_page_id = get_option('ltc_page_id'); // Získání ID stránky uloženého v pluginu
    
    // Kontrola, zda je ID stránky platné
    if ($ltc_page_id) {
        $page_content = postup_vypoctu_page(); // Nový obsah stránky
        
        // Aktualizace obsahu stránky
        $page_data = array(
            'ID'           => $ltc_page_id,
            'post_content' => $page_content
        );
        wp_update_post($page_data);
    }
}

