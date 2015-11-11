<?php
// declare db connection variables

//$server   = 'localhost';
//$username = 'espadev';
//$password = 'espa1210';
//$database = 'EDINAImports';

$server = 'espa1.rir.ed.ac.uk';
$username = 'delsemore';
$password = 'Edina1210';
//$database = 'EDINAImports';

require("vendor/autoload.php"); // Load MailChimp class

try {
    
    $dbh = new PDO("sqlsrv:Server=$server;Database=Results", $username, $password);
	
	$dbh->exec("DROP TABLE People2MCBackup");

    $dbh->exec("SELECT * into People2MCBackup from People");
    
    $sql = "Select top 5  Email, Firstname, Surname, Organisation, Country from People  WHERE (MailChimpAddTo = 'True') AND (NOT (Email IS NULL) OR NOT (Email <> '')) ORDER BY Email";
    
    $index = 0; // array index 
    
    $newsubs          = array(); //array to hold batch subscribe details
   $newsubs['id']    = '153b0a2ef4'; //Dave Test list id
    // $newsubs['id']    = '93aa2f28e1'; //ESPA list id
	 $newsubs['batch'] = array(); //array to holder each new subscriber details
    foreach ($dbh->query($sql) as $row) {
        $email   = $row['Email'];
        $first   = $row['Firstname'];
        $last    = $row['Surname'];
        $org     = $row['Organisation'];
		if($org =='') {
			$org='Not Specified';
		}
        $country = $row['Country'];
        
        //construct the array in the appropriate form
        // see: https://benmarshall.me/mailchimp-php-api-class/#batch-subscribe
        
        $newsubs['batch'][$index]                             = array();
        $newsubs['batch'][$index]['email']                    = array();
        $newsubs['batch'][$index]['email']['email']           = $email;
        $newsubs['batch'][$index]['merge_vars']               = array();
        $newsubs['batch'][$index]['merge_vars']['FNAME']      = $first;
        $newsubs['batch'][$index]['merge_vars']['LNAME']      = $last;
        $newsubs['batch'][$index]['merge_vars']['ORGANISATI'] = $org;
        $newsubs['batch'][$index]['merge_vars']['MMERGE5']    = 'Other';
        $newsubs['batch'][$index]['merge_vars']['MMERGE4']    = $country;
        $newsubs['batch'][$index]['merge_vars']['SOURCE']     = 'Other';
        $index++;
    }
    $newsubs['double_optin'] = "false";
   // print_r($newsubs);
    //do subscribe
    $MailChimp = new \Drewm\MailChimp('fea09b006ecf826b510b9e59feebb890-us7');
    $result    = $MailChimp->call('lists/batch-subscribe', $newsubs);
    
    //print results to screen
    print("<pre>" . print_r($result, true) . "</pre>");
    
    //print results to file
    $date = date('Y-m-d H:i:s');
    
    $msghead = '*********************************************************************************' . PHP_EOL . '                          MailChimp Sync Log - ' . $date . PHP_EOL . '*********************************************************************************' . PHP_EOL . PHP_EOL;
    
    $my_log_file = 'espa2mailchimp.log';
    
    $loghandle = fopen($my_log_file, 'a') or die('Cannot open file:  ' . $my_log_file); //implicitly creates file
    //if ( 0 == filesize( $my_log_file ) ) {
    fwrite($loghandle, $msghead);
    //}
    
    $msg = '';
    $msg .= 'Summary' . PHP_EOL;
    $msg .= '---------------' . PHP_EOL;
    $msg .= 'New Subscribers = ' . $result['add_count'] . PHP_EOL;
    $msg .= 'Errors = ' . $result['error_count'] . PHP_EOL . PHP_EOL;
    
 if ($result['add_count'] > 0) {
        $msg .= 'New Subscribers' . PHP_EOL;
        $msg .= '---------------' . PHP_EOL;
		$si  = 1;
        $ns = '';
        foreach ($result['adds'] as $ns) {
           
            $msg .= $ns['email'] . PHP_EOL;
            $si++;
        }
 }
    $msg .= PHP_EOL;
	
    if ($result['error_count'] > 0) {
        $msg .= 'Errors' . PHP_EOL;
        $msg .= '-------' . PHP_EOL;
        $ei  = 1;
        $err = '';
        foreach ($result['errors'] as $error) {
            $err   = $error['error'];
            $email = $error['email']['email'];
            $err   = str_replace('MMERGE4', 'Country', $err);
            if (strlen($err) > 125)
                $err = substr($err, 0, 125) . "...";
            $msg .= $ei . '. [' . $email . '] - ' . $err . PHP_EOL;
            $ei++;
        }
    }
    $msg .= PHP_EOL;
    
    fwrite($loghandle, $msg);
    
    //get email addresses from results and convert to comma-separated string
    $subs = '';
    foreach ($result['adds'] as $sub) {
        $subs .= "'" . $sub["email"] . "',";
    }
    $subs = rtrim($subs, ',');
    print $subs;
    // Update db where sub was successful
    $dbh->exec("update People set MailChimpActive ='True', MailChimpAddTo = 'False' where People.Email in (" . $subs . ")");
    
    //send summary email
    $to      = "david.elsmore@gmail.com";
    $subject = "Sync Test";
    $txt     = $msg;
    $headers = "From: david.elsmore@ed.ac.uk" . "\r\n";
    mail($to,$subject,$txt,$headers);
    
    
}
catch (PDOException $e) {
    echo 'Failed to connect to database: ' . $e->getMessage() . "\n";
    exit;
}
