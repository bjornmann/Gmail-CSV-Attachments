<?php
//
//
//Gmail code modified from: http://davidwalsh.name/gmail-php-imap & http://www.codediesel.com/php/downloading-gmail-attachments-using-php/
//
//

//Set to your timezone
date_default_timezone_set('America/New_York');

//Gmail host, you shouldn't have to change this.
$hostname = '{imap.gmail.com:993/imap/ssl}INBOX';

//Your gmail account
$username = 'yourname@gmail.com';

//Your gmail password
$password = 'Password123!';

//Create your variable to hold the email headers;
$headers;

//The email address your report will come from
$emailAdd = 'youraddress@yourdomain.com';

//What is the filename of the csv going to be?
$expectedName = 'myfilename';

//Set a bool flag
$hit = false;

//Get today's date
$today = date("Y-m-d");

// try to connect
$inbox = imap_open($hostname,$username,$password) or die(imap_last_error());

// grab emails
$emails = imap_search($inbox,'ALL');

// if emails are returned, cycle through each...
if($emails) {
	//Sort by newest first
	rsort($emails);
	//start looping through
	foreach($emails as $email_number) {

		//grab the email header info
		$header = imap_headerinfo($inbox,$email_number);

		//Get the sender info from the header
		$fromaddr = $header->from[0]->mailbox . "@" . $header->from[0]->host;

		//Get the send date from the header, format it to match our today and yesterday variables
		$date = date_create($header->MailDate);
		$date = date_format($date, 'Y-m-d');

		//Get structure data of this email
        $structure = imap_fetchstructure($inbox, $email_number);

        //Empty array to hold attachment data
		$attachments = array();

		//If this email is from your set address, and it was sent today, continue
		if($fromaddr === $emailAdd && $date === $today){
			//If there are parts of the email set, continue
			if(isset($structure->parts) && count($structure->parts)){
				//Flag flipped!
				$hit = true;

				//How many parts are there?
				$partCount = count($structure->parts);

			    for($i = 0; $i < $partCount; $i++){
			    	//Someday soon this will hold your attachment data. ahhhhh. nice.
			        $attachments[$i] = array(
			            'is_attachment' => false,
			            'filename' => '',
			            'name' => '',
			            'attachment' => ''
			        );
			        //2 ways to grab your data
			        //first method
					if($structure->parts[$i]->ifdparameters){
						foreach($structure->parts[$i]->dparameters as $object){
						    if(strtolower($object->attribute) == 'filename'){
						        $attachments[$i]['is_attachment'] = true;
						        $attachments[$i]['filename'] = $object->value;
						    }
						}
					}
					//second method
					if($structure->parts[$i]->ifparameters){
					    foreach($structure->parts[$i]->parameters as $object){
					        if(strtolower($object->attribute) == 'name'){
					            $attachments[$i]['is_attachment'] = true;
					            $attachments[$i]['name'] = $object->value;
					        }
					    }
					}
					//if one of those methods worked, keep it moving
					if($attachments[$i]['is_attachment']){
						//make sure the filename is what you were expecting
						if($attachments[$i]['name'] == $expectedName.".csv" || $attachments[$i]['filename'] == $expectedName.".csv"){
							//get your data!
							$attachments[$i]['attachment'] = imap_fetchbody($inbox, $email_number, $i+1);
							if($structure->parts[$i]->encoding == 3){
							    $attachments[$i]['attachment'] = base64_decode($attachments[$i]['attachment']);
							}
						    /* 3 = BASE64 encoding */
							/* 4 = QUOTED-PRINTABLE encoding */
						    elseif($structure->parts[$i]->encoding == 4){
						        $attachments[$i]['attachment'] = quoted_printable_decode($attachments[$i]['attachment']);
						    }
						}
			        }
			    }
			
		        /* iterate through each attachment and save it */
		        foreach($attachments as $attachment){
		            if($attachment['is_attachment'] == 1){
		                $filename = $attachment['name'];
		                if(empty($filename)) $filename = $attachment['filename'];
		                if(empty($filename)) $filename = time() . ".dat";
		                /* prefix the email number to the filename in case two emails
		                 * have the attachment with the same file name.
		                 */
		                $fp = fopen('csv/'.$filename, "w+");
		                fwrite($fp, $attachment['attachment']);
		                fclose($fp);
		            }
		        }
			}
		}
		//If the email is from someone other than your set sender, or is from a date other than today, do this stuff instead
		else{
			//Get yesterday
			$yesterday = date("Y-m-d", strtotime( '-1 days' ) );

			//If the email is from yesterday, and from your sender address, delete it. This assumes you have a cron job set up to run this script each day.
			if($fromaddr === $emailAdd && $date === $yesterday){
				imap_delete($inbox,$email_number);
			}
			//In every other instance, delete the email and expunge the inbox.
			else{
				imap_delete($inbox,$email_number);
				imap_expunge($inbox);
			}
		}
	}
}
//If there aren't any emails returned, fail.
else{
	die('No emails :-(');
}
// close the Gmail connection
imap_close($inbox);

if($hit && $hit == true){
	/*Start File Get */
	$url = "csv/".$expectedName.".csv";
	if (($handle = fopen($url, "r")) !== FALSE) {
		fgetcsv($handle);
	    while (($data = fgetcsv($handle, 0, ",")) !== FALSE) {
	    		print_r($data);
	    	}
	    }
	  fclose($handle);
	}
}
?>
