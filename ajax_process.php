<?php

function ajax_form_message( $data, $class = 'OK', $then = '' )
{
    //send headers as JSON
    header('Content-Type: application/json');
    //write data and end processing
    $resource = [
        'type' => $class,
        'content' => $data
    ];
    //if we need to reload the page or reset form, push it
    if ( $then && is_string($then) ) {
        
        $then = str_replace([ ' ' ], '', $then);
        foreach ( explode(',', $then) as $action )
            $resource[$then] = 1;
        
    }
    wp_die(json_encode($resource));
}

/**
 * @return int
 * @throws Exception
 */
function ajax_process_SAM_app()
{
    //if can't verify the nonce string, don't accept the data
    if ( !$n = wp_verify_nonce($_POST['wp_nonce'], 'Amen') )
        ajax_form_message([ 'nonce', $_POST['wp_nonce'], $n ], 'error', 'reload');
    
    $formData = [];
    
    //important fields
    $fields = [ 'related_post', 'jmeno', 'prijmeni', 'email', 'bdate', 'ulice', 'cp', 'psc', 'mesto', 'note', 'support' ];
    foreach ( $fields as $key ) {
        //if any of required fields missing, send error
        if ( !isset($_POST[$key]) )
            ajax_form_message($key, 'missing');
        $formData[$key] = $_POST[$key];
    }
    
    if ($formData['bdate'] <= 1900 || $formData['bdate'] > date("Y"))
        ajax_form_message('Zadejte prosím svůj rok narození.', 'error');
    
    $id = intval($formData['related_post']);
    if ( $id <= 0 )
        ajax_form_message('related_post', 'error', 'reload');
    
    if ( strlen($_POST['note']) > 255 )
        ajax_form_message("Poznámka je příliš dlouhá. Povolená délka je do 255 znaků.", 'error');
    
    //get prices for recalculations
    $prices = json_decode(get_post_meta($id, 'sam_prices', true), 'reload');
    
    
    //start array of 3 zeros
    $sum = array_fill(0, 3, 0);
    
    //array of all ordered items
    $details = [];
    
    //every day
    for ( $i = 0; $i < count($prices); $i++ ) {
        //program
        if ( $_POST['input-ch-program'][$i] == 'on' ) {
            $details[] = $i;
            $sum[0] += intval($prices[$i]['price']);
        }
        //meal
        for ( $j = 0; $j < count($prices[$i]['options']); $j++ )
            if ( $_POST['input-ch-strava'][$i . '.' . $j] == 'on' ) {
                $details[] = $i . '.' . $j;
                $sum[1] += intval($prices[$i]['options'][$j]['price']);
            }
    }
    
    //if support, append the amount to details
    if ( isset($_POST['support']) && ($supp = intval($_POST['support'])) > 0 )
        $details[] = $sum[2] = $supp;
    
    
    $databaseData = [
        'token' => wp_generate_password(32, false),
        'related_post' => $id,
        'status' => 'new',
        'name' => $formData['jmeno'],
        'sname' => $formData['prijmeni'],
        'byear' => intval($formData['bdate']),
        'email' => $formData['email'],
        'location' => $formData['ulice'] . ' ' . $formData['cp'] . ', ' . $formData['psc'] . ' ' . $formData['mesto'],
        'accomodation' => (int)($_POST['input-ch-acco'] == 'on'),
        'vegetarian' => (int)($_POST['input-ch-vege'] == 'on'),
        'appdetail' => implode(',', $details),
        'note' => isset($_POST['note']) && !empty($_POST['note']) ? $_POST['note'] : "",
        'price' => array_sum($sum)
    ];
    
    global $wpdb;
    $tableName = $wpdb->prefix . 'apps';
    
    //get count of existing apps
    $count = intval($wpdb->get_var($wpdb->prepare("SELECT count(ID) FROM `{$tableName}` WHERE `token` = %s OR (`name` = %s AND `sname` = %s AND YEAR(`appdate`) = %s)", $databaseData['token'], $databaseData['name'], $databaseData['sname'], (new DateTime())->format('Y'))));
    //if registration exists
    if ( $count > 0 )
        ajax_form_message('Přihláška již existuje. Pokud si přejete přihlášku pozměnit, prosím kontaktujte organizační tým.', 'error');
    
    //insert app to database
    $q = $wpdb->insert($tableName, $databaseData);
    if ( !$q )
        ajax_form_message("Něco se přihodilo po cestě do databáze. Obnovte stránku a zkuste to, prosím, ještě jednou.", 'error', 'reload');
    
    $paymentData = [
        '@name' => $datebaseData['name'],
        '@sname' => $databaseData['sname'],
        '@acc' => '19-3568510277/0100',
        '@iban' => 'CZ8301000000193568510277',
        '@cena' => number_format($databaseData['price'], 2),
        '@splatnost' => "14. 8. 2019",
        '@vs' => (new DateTime())->format('Y') . '.' . $wpdb->insert_id,
        '@msg' => implode(',', [ 'SAM', $databaseData['sname'], $databaseData['name'] ])
    ];

//  $QRstring = str_replace(array_keys($paymentData), array_values($paymentData), 'SPD*1.0*ACC:@iban*AM:@price*CC:CZK*MSG:@msg*X-VS:@vs');
//  $QRstring = QRcode::text($QRstring);
//  ajax_form_message($QRstring);
//
//  //now get QR code image and email it to the user
//  //start caching data
//  ob_start();
//  QRCode::png($QRstring, NULL);
//
//  $QRstring = ob_get_contents();
//
//  //clear cached data
//  ob_end_clean();
//    add_action('phpmailer_init', function ( &$phpMailer ) use ( $qrCodeString ) {
//        $phpMailer->Encoding = "base64";
//        $phpMailer->isHTML(true);
//        $phpMailer->addStringEmbeddedImage($qrCodeString, 'qrcode', 'Platba pomocí QR kódu', 'base64', 'image/png');
//    });
    
    $pricesTable = '<table>';
    $pricesTable.= "<tr><td>Účastnický příspěvek</td><td>{$sum[0]}</td></tr>";
    $pricesTable.= "<tr><td>Cena strávného</td><td>{$sum[1]}</td></tr>";
    if (isset($sum[2]) && $sum[2] > 0)
        $pricesTable .= "<tr><td>Dar (děkujeme)</td><td>{$sum[2]}</td></tr>";
    $pricesTable.= "<tr><td>Cena celkem</td><td>{$paymentData['@cena']}</td></tr>";
    $pricesTable .= '</table>';
    
    
    $message = '<p>Dobrý den,<br/>
právě jste se jménem @name @sname přihlásili na SAM. Jsme rádi, že nezůstáváte samorostem a chystáte se přijít :)</p>
<p style="font-weight:bold;">K dokončení registrace zbývá jen zaplatit účastnický poplatek ve výši @cena,- Kč, což můžete učinit dvěma způsoby:</p>
<li>bankovním převodem na účet <b>@acc</b>, s variabilním symbolem <b>@vs</b> a jako zprávu pro příjemce uveďte \'SAM\' a Vaše jméno</li>
<li>v hotovosti v sídle organizátora na adrese:
<span style="font-size:120%;font-weight:bold;">Benjamín Orlová, z.s.<br/>
Petra Cingra 482<br/>
735 11, Orlová 1</span></li></ul>
<p style="font-size:110%;">Vemte prosím na vědomí, že registrace přes internet je platná pouze v případě <b>připsání částky</b> na účet (nebo zaplacení v hotovosti) <b>do @splatnost</b>. Níže najdete informaci o rozpadu ceny:</p>'.$pricesTable;
    
    //replace @var with values
    $message = str_replace(array_keys($paymentData), array_values($paymentData), $message);
    
    $headers = [
        'Content-Type: text/html; charset=UTF-8',
        'From: SAM Přihlášky <prihlasky@samorlova.cz>',
        'Reply-To: SAM Orlová <sam@benjaminorlova.cz>'
    ];
    
    //watch mail sending, if anything, say it loud
    if ( !wp_mail($databaseData['email'], "Přihláška na akci SAMorost 2019", $message, $headers) )
        ajax_form_message("Něco se nepovedlo při odesílání e-mailu. Prosím, kontaktujte organizační tým", "error", 'reload');
    
    //say everythings OK
    ajax_form_message("Přihláška byla úspěšně zpracována. Na vámi zadanou e-mailovou adresu jsme zaslali informace o přihlášce a platbě. <br/><b>Nezapomeňte ji včas zaplatit, jinak můžete přijít o levnější cenu</b>.", "success", 'reset');
    
}

add_action("wp_ajax_nopriv_SAMapp", "ajax_process_SAM_app");
add_action("wp_ajax_getSAMapp", "ajax_process_SAM_app");
