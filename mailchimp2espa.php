<?php
include 'conn-local.php';
//include 'conn-rir.php';

require("vendor/autoload.php"); // Load MailChimp class

$MailChimp = new \Drewm\MailChimp('fea09b006ecf826b510b9e59feebb890-us7');

//Request the last x sign-ups
$results   = $MailChimp->call("lists/members", array(
  "id"=>  "153b0a2ef4", //Dave Test List list id
   // "id" => "93aa2f28e1", // required, the list id to connect to. Get by calling lists/list()
    "status" => "subscribed", // optional, the status to get members for - one of(subscribed, unsubscribed, cleaned), defaults to subscribed
    "opts" => array( // optional, various options for controlling returned data
        "start" => 0, // optional, for large data sets, the page number to start at - defaults to 1st page of data (page 0)
        "limit" => 20, // optional, for large data sets, the number of results to return - defaults to 25, upper limit set at 100
        "sort_field" => "optin_time", // optional, the data field to sort by - mergeX (1-30), your custom merge tags, "email", "rating","last_update_time", or "optin_time" - invalid fields will be ignored
        "sort_dir" => "DESC" // optional, the direction - ASC or DESC. defaults to ASC (case insensitive)
        
    )
));
//create db connx
$dbh       = new PDO("sqlsrv:Server=$server;Database=Results", $username, $password);

//delete backup table
$dbh->exec("DROP TABLE MC2PeopleBackup");

//create new backup and populate with current contents of People table
$dbh->exec("SELECT * into MC2PeopleBackup from People");

// Get the last ID from the People table and put in $PreviousID variable
$stmt = $dbh->prepare("select top 1 PeopleID from People order by PeopleID DESC");
$stmt->execute();
$last = $stmt->fetch();
$previousID = $last['PeopleID'];

// Loop through rersults
foreach ($results['data'] as $data) {
    
    $email   = $data['email'];
    $first   = $data['merges']['FNAME'];
    $last    = $data['merges']['LNAME'];
    $org     = $data['merges']['ORGANISATI'];
    $country = $data['merges']['MMERGE4'];
    
    //print $email . '<br>';
    //print $first . '<br>';
    //print $last . '<br>';
    //print $org . '<br>';
    //print $country . '<br>';
    
	// insert each record into DB if email address doesn't exist
    $dbh->exec("INSERT into Results.dbo.People(Surname,Firstname, Email, Organisation, Country, Projectstaff, UseFirstName, DataSource, MailChimpActive, MailChimpHasBeenActive, MailChimpAddTo, BulkEmailSelect, MailChimpInvite, [Select], Uploaded, BadEmail, CorrectedEmail, SendToDelegate, CopyToDelegate) select NULLIF('$last',''), NULLIF('$first',''), '$email', '$org', '$country', 'FALSE', 'FALSE', 'MailChimp', 'TRUE', 'TRUE', 'FALSE', 'FALSE', 'FALSE', 'FALSE', 'FALSE', 'FALSE', 'FALSE', 'FALSE', 'FALSE'  where not exists(select 1 from Results.dbo.People 
 where Email = '$email')");
    

}

//print results to file
$date = date('Y-m-d H:i:s');

$msghead = '*********************************************************************************' . PHP_EOL . '            MailChimp to ESPA DB Sync Report - ' . $date . PHP_EOL . '*********************************************************************************' . PHP_EOL . PHP_EOL;

$my_log_file = 'mailchimp2espa.log';

$loghandle = fopen($my_log_file, 'a') or die('Cannot open file:  ' . $my_log_file); //implicitly creates file
//if ( 0 == filesize( $my_log_file ) ) {
fwrite($loghandle, $msghead);
//}

$msg = '';
$msg .= 'New MailChimp Subscribers' . PHP_EOL;
$msg .= '-------------------------' . PHP_EOL;


// Select all new records and write to $msg variable
$new = "SELECT  Firstname, Surname, Email, Organisation, Country from People where PeopleID > " . $previousID . "";
$cnt = 1;
foreach ($dbh->query($new) as $row) {
    $msg .= $cnt . '. ' . $row['Firstname'] . ' ' . $row['Surname'] . ' <' . $row['Email'] . '>, ' . $row['Organisation'] . ', ' . $row['Country'] . PHP_EOL;
    print $cnt . '. ' . $row['Email'] . '<br>';
    $cnt++;
}

if ($cnt <> 1) {
    $msg .= PHP_EOL . ($cnt - 1) . ' new Mailchimp subscribers have been added to the ESPA database' . PHP_EOL . PHP_EOL;
} else {
    $msg .= PHP_EOL . 'No new Mailchimp subscribers were added to the ESPA database' . PHP_EOL . PHP_EOL;
}
$msg .= PHP_EOL;

//write to file
fwrite($loghandle, $msg);
// print("<pre>" . print_r($results, true) . "</pre>");


//send summary email
$to      = "david.elsmore@ed.ac.uk";
$subject = "MailChimp to ESPA DB Sync Report";
$txt     = $msg;
$headers = "From: david.elsmore@ed.ac.uk" . "\r\n";
mail($to, $subject, $txt, $headers);