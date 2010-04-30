<?php
/*
 * Created on 23/08/2006 by jose.canciani (at) 4tm.com.ar
 * This script should be run every N minutes. It will
 * connect to GMail, search the spam folder and insert
 * in a local table the information on the spam emails. 
 *
 * Tables needed to run the program:
 *  
 * CREATE TABLE spam_occurance (
 *   message_id VARCHAR(30) NOT NULL ,
 *   ts INT(11) NOT NULL ,
 *   recv_email VARCHAR(60) NOT NULL ,
 *   subj VARCHAR(200) NOT NULL ,
 *   from_email VARCHAR(60) NOT NULL ,
 *   PRIMARY KEY (message_id),
 *   INDEX (recv_email,ts),
 *   INDEX (ts,recv_email)
 * );
 * 
 * TODO: use email timestamp intead of script timestamp
 *       not sure if needed becouse the script runs several
 *       times a day, and reports will be dayly, weekly and monthly.
 * 
 */
 
 include('config.inc.php');
 
 //debug? this will print to screen a resume of what is being collected
 $debug = true;
 
 
 // script starts up here
 
 // quick command link detection (not sure if it works)
 if (isset($_SERVER['argv'][0])) {
 	$nl = chr(10);
 } else {
 	$nl = '<br/>';
 }
 
 
 function debug($txt) {
 	global $debug;
 	if ($debug) {
 		echo $txt;
 	}
 }
 
 // email regular expression to strip email from strings
 $regex = '([_a-z0-9-]+[_a-z0-9\-\.]*@[a-z0-9\-]+[\.a-z0-9\-\.]*[\.]+[a-z]{2,4})';
 
 require("libgmailer.php");
 require("adodb_lite/adodb.inc.php");
 
   $db = NewADOConnection($db_uri);
   
   if (!$db) {
      debug('Could not connect to DB'.$nl);
      exit;   	
   }
      
   $gm = new GMailer();
   $gm->setLoginInfo($myemail, $pwd, $tz);  // only required for connecting the first time, 
                                         // e.g. in your login page
                                         // in other pages you can simply connect()
   // connect to gmail
   if ($gm->connect()) {
   	  // get the conversations on the Spam folder
      $gm->fetchBox(GM_STANDARD, "spam", 0);  // name of constants can be found in libgmailer.php
      $snapshot = $gm->getSnapshot(GM_STANDARD);
      if ($snapshot) {
      	 debug('Total # of conversations in Spam folder = ' . $snapshot->box_total.$nl.$nl);
         foreach ($snapshot->box as $conv) {
         	// we will only inspect unread messages for better performance
         	if ($conv['is_read']==1) {
         		
         		debug('Conversation "'.strip_tags($conv['subj']).'" (id: '.$conv['id'].')'.$nl);
         	    
	         	// get the messages in the conversation
	         	$q = "search=spam&view=cv&th=".$conv['id'];
		        $gm->fetch($q);
	    	    $snapshot2 = $gm->getSnapshot(GM_STANDARD|GM_LABEL|GM_QUERY|GM_CONVERSATION);
				foreach ($snapshot2->conv as $msg) {
					debug(' From: '.$msg['sender_email'].' ID: '.$msg['id'].' was send to ');
					foreach ($msg['recv_email'] as $recv_email){
						$email = '';
						eregi($regex, $recv_email, $email);
						debug($email[0].' ');
						$sql = 'select 1 from spam_occurance where message_id = ?';
						$rs = $db->execute($sql,array($msg['id']));
						if ($rs->EOF) {
							$sql = 'insert into spam_occurance (message_id,ts,recv_email,subj,from_email) values (?,?,?,?,?)';
							$db->execute($sql,array($msg['id'],time(),$email[0],strip_tags($conv['subj']),$msg['sender_email']));
							debug('INSERTED');
						} else {
							debug('SKIPPED');
						}
					}				
					debug($nl);
				}    	             	
         	} else {
         		debug('Skipping (already read) conversation "'.strip_tags($conv['subj']).'" (id: '.$conv['id'].')'.$nl);
         	}
         }
         debug($nl);
                  
      } else {
      	 debug('Failed to getSnapshot()'.$nl);
      }
      // $gm->disconnect() only when you really want to logout
   } else {
	  debug("Failed to connect()".$nl);   	   	
   }   
 
?>