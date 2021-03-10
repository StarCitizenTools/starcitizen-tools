<?php

class CTHooks {

	/**
	 * Some HTML&JS code for JavaScript test 
	 * @param UploadForm $editPage
	 * @return bool
	 */
    public static function onShowUploadForm( $editPage ) {
        global $wgCTSubmitTimeLabel;

        $editPage->uploadFormTextTop = CTBody::AddJSCode();
        
        $_SESSION[$wgCTSubmitTimeLabel] = time();
        
        return true;
    }
	/**
	 * Some HTML&JS code for JavaScript test 
	 * @param HTMLForm $form
	 * @return bool
	 */
    public static function onShowEditForm( $editPage ) {
        global $wgCTSubmitTimeLabel;

        $editPage->editFormTextBottom = CTBody::AddJSCode();
        
        $_SESSION[$wgCTSubmitTimeLabel] = time();
        
        return true;
    }
	/**
	 * Some HTML&JS code for JavaScript test 
	 * @param HTMLForm $form
	 * @return bool
	 */
    public static function onUserCreateForm( &$template ) {
        global $wgCTSubmitTimeLabel;
        
        $template->set( 'header', CTBody::AddJSCode());

        $_SESSION[$wgCTSubmitTimeLabel] = time();
        
        return true;
    }
	
    /**
	 * Upload spam test 
	 * UploadBase $upload
	 * string $mime
	 * bool|array $error
	 * @return none
	 */
    public static function onUploadFilter ( $upload, $mime, &$error ) {
        global $wgCTAccessKey, $wgCTServerURL, $wgRequest, $wgCTAgent, $wgCTExtName, $wgCTNewEditsOnly, $wgCTMinEditCount, $wgUser;
        
        # Skip spam check if error exists already
        if ($error !== TRUE) {
            return;
        }
        
        $allowUpload = true;

        // Skip antispam test if user is member of special group
        if ( $wgUser->isAllowed('cleantalk-bypass') ) {
            return;
        }

        // Skip antispam test for user with getEditCount() more than setted value
        $edit_count = $wgUser->getEditCount();
        if ( isset($edit_count) && $edit_count > $wgCTMinEditCount ) {
            return;
        }

        // The facility in which to store the query parameters
        $ctRequest = new CleantalkRequest();

        $ctRequest->auth_key = $wgCTAccessKey;
        $ctRequest->sender_email = $wgUser->mEmail;
        $ctRequest->sender_nickname = $wgUser->mName;
        $ctRequest->message = $wgRequest->getVal('wpUploadDescription');
        $ctRequest->agent = $wgCTAgent;
        $ctRequest->sender_ip = $wgRequest->getIP(); 
        $ctRequest->js_on = CTBody::JSTest(); 
        $ctRequest->submit_time = CTBody::SubmitTimeTest(); 
        
        $ctRequest->sender_info=json_encode(
	    Array(
		'page_url' => htmlspecialchars(@$_SERVER['SERVER_NAME'].@$_SERVER['REQUEST_URI']),
    		'REFFERRER' => $_SERVER['HTTP_REFERER'],
    		'USER_AGENT' => $_SERVER['HTTP_USER_AGENT'],
	    )
	);

        $ct = new Cleantalk();
        $ct->server_url = $wgCTServerURL;

        // Check
        $ctResult = $ct->isAllowMessage($ctRequest);

        if ( $ctResult->errno != 0 ) {
        	if(CTBody::JSTest() != 1)
        	{
        		$ctResult->allow = 0;
        		$ctResult->comment = "Forbidden. Please, enable Javascript.";
        		$allowUpload = false;
        	}
        	else
        	{
        		$ctResult->allow = 1;
        		$allowUpload = true;
        	}
        }

        // Disallow edit with CleanTalk comment 
        if (!$allowUpload || $ctResult->allow == 0) {
            // Converting links to wikitext format
            $errorText = preg_replace("(<a\shref=\"([^\s]+)\".+>([a-f0-9]+)</a>)", "[$1 $2]", $ctResult->comment);

            // Fill $error parameter as array with error text
            $error = array($errorText);
        }
       
        if ($ctResult->inactive === 1) {
            CTBody::SendAdminEmail( $wgCTExtName, $ctResult->comment );
        }

        return;
    }
    
    /**
	 * Edit spam test 
	 * @return bool
	 */
	public static function onEditFilter (  $editor, $text, $section, &$error, $summary ) {
        global $wgCTAccessKey, $wgCTServerURL, $wgRequest, $wgCTAgent, $wgCTExtName, $wgCTNewEditsOnly, $wgCTMinEditCount;
        
        $allowEdit = true;

        // Skip antispam test if editor member of special group
        if ( $editor->getArticle()->getContext()->getUser()->isAllowed('cleantalk-bypass') ) {
            return $allowEdit;
        }
        
        // Skip antispam test of not new edit if flag is set
        if ( $wgCTNewEditsOnly && !$editor->isNew) {
            return $allowEdit;
        }

        // Skip antispam test for user with getEditCount() more than setted value
        $edit_count = $editor->getArticle()->getContext()->getUser()->getEditCount();
        if ( isset($edit_count) && $edit_count > $wgCTMinEditCount ) {
            return $allowEdit;
        }

        // The facility in which to store the query parameters
        $ctRequest = new CleantalkRequest();

        $ctRequest->auth_key = $wgCTAccessKey;
        $ctRequest->sender_email = $editor->getArticle()->getContext()->getUser()->mEmail; 
        $ctRequest->sender_nickname = $editor->getArticle()->getContext()->getUser()->mName;
        $ctRequest->message = $editor->getTitle()->getText() . "\n \n" . $summary . "\n \n" . $text;
        $ctRequest->agent = $wgCTAgent;
        $ctRequest->sender_ip = $wgRequest->getIP(); 
        $ctRequest->js_on = CTBody::JSTest(); 
        $ctRequest->submit_time = CTBody::SubmitTimeTest(); 
        $ctRequest->sender_info=json_encode(
	    Array(
		'page_url' => htmlspecialchars(@$_SERVER['SERVER_NAME'].@$_SERVER['REQUEST_URI']),
    		'REFFERRER' => $_SERVER['HTTP_REFERER'],
    		'USER_AGENT' => $_SERVER['HTTP_USER_AGENT'],
	    )
	);

        $ct = new Cleantalk();
        $ct->server_url = $wgCTServerURL;

        // Check
        $ctResult = $ct->isAllowMessage($ctRequest);

        // Allow edit if we have any API errors
        /*if ( $ctResult->errno != 0 ) {
            return $allowEdit;
        }*/
        if ( $ctResult->errno != 0 ) 
        {
        	if(CTBody::JSTest()!=1)
        	{
        		$ctResult->allow=0;
        		$ctResult->comment = "Forbidden. Please, enable Javascript.";
        		$allowEdit = false;
        	}
        	else
        	{
        		$ctResult->allow=1;
        		$allowEdit = true;
        	}
        }

        // Disallow edit with CleanTalk comment 
        if (!$allowEdit || $ctResult->allow == 0) {
            $error = $ctResult->comment;
            
            // Converting links to wikitext format
            $error = preg_replace("(<a\shref=\"([^\s]+)\".+>([a-f0-9]+)</a>)", "[$1 $2]", $error);
            
            $error = Html::openElement( 'div', array( 'class' => 'errorbox' ) ) .
               $error . 
               Html::closeElement( 'div' ) . "\n" .
               Html::element( 'br', array( 'clear' => 'all' ) ) . "\n";
        }
       
        if ($ctResult->inactive === 1) {
            CTBody::SendAdminEmail( $wgCTExtName, $ctResult->comment );
        }

        return $allowEdit;
	}
    
    /**
	 * Account spam test 
	 * @return bool
	 */
	public static function onAbortNewAccount ( $user, &$message ) {
        global $wgCTAccessKey, $wgCTServerURL, $wgRequest, $wgCTAgent, $wgCTExtName;
        
        $allowAccount = true;
        
        // The facility in which to store the query parameters
        $ctRequest = new CleantalkRequest();

        $ctRequest->auth_key = $wgCTAccessKey;
        $ctRequest->sender_email = $user->mEmail; 
        $ctRequest->sender_nickname = $user->mName; 
        $ctRequest->agent = $wgCTAgent;
        $ctRequest->sender_ip = $wgRequest->getIP(); 
        $ctRequest->js_on = CTBody::JSTest(); 
        $ctRequest->submit_time = CTBody::SubmitTimeTest(); 
        $ctRequest->sender_info=json_encode(
	    Array(
		'page_url' => htmlspecialchars(@$_SERVER['SERVER_NAME'].@$_SERVER['REQUEST_URI']),
    		'REFFERRER' => $_SERVER['HTTP_REFERER'],
    		'USER_AGENT' => $_SERVER['HTTP_USER_AGENT'],
	    )
	);

        $ct = new Cleantalk();
        $ct->server_url = $wgCTServerURL;

        // Check
        $ctResult = $ct->isAllowUser($ctRequest);

        // Allow account if we have any API errors
        if ( $ctResult->errno != 0 ) 
        {
        	if(CTBody::JSTest()!=1)
        	{
        		$ctResult->allow=0;
        		$ctResult->comment = "Forbidden. Please, enable Javascript.";
        	}
        	else
        	{
        		$ctResult->allow=1;
        	}
        }

        // Disallow account with CleanTalk comment 
        if ($ctResult->allow == 0) {
            $allowAccount = false;
            $message = $ctResult->comment;
        }
        
        if ($ctResult->inactive === 1) {
            CTBody::SendAdminEmail( $wgCTExtName, $ctResult->comment );
        }

        return $allowAccount;
	}
	
	public static function onSkinAfterBottomScripts( $skin, &$text )
	{
		global $wgCTShowLink, $wgCTSFW, $wgCTDataStoreFile, $wgCTAccessKey;
		
		/* SFW starts */
		
		if($wgCTSFW && file_exists($wgCTDataStoreFile))
		{
			$settings = file_get_contents($wgCTDataStoreFile);
			
			include_once("cleantalk-sfw.class.php");
			
			if ($settings)
			{
				$settings = json_decode($settings, true);
				if(!isset($settings['lastSFWUpdate']))
				{
					$settings['lastSFWUpdate'] = 0;
				}
				if(time()-$settings['lastSFWUpdate'] > 30)
				{
					$dbr = wfGetDB(DB_MASTER);
					$dbr->query("DROP TABLE IF EXISTS `cleantalk_sfw`;");
					$dbr->commit();
					$dbr->query("CREATE TABLE IF NOT EXISTS `cleantalk_sfw` (
		`network` int(11) unsigned NOT NULL,
		`mask` int(11) unsigned NOT NULL,
		INDEX (  `network` ,  `mask` )
		) ENGINE = MYISAM ;");
					$dbr->commit();
					$data = Array(	'auth_key' => $wgCTAccessKey,
						'method_name' => '2s_blacklists_db'
		 			);
		 			$result=sendRawRequest('https://api.cleantalk.org/2.1',$data,false);
					$result=json_decode($result, true);
					if(isset($result['data']))
					{
						$result=$result['data'];
						$query="INSERT INTO `cleantalk_sfw` VALUES ";
						for($i=0;$i<sizeof($result);$i++)
						{
							if($i==sizeof($result)-1)
							{
								$query.="(".$result[$i][0].",".$result[$i][1].");";
							}
							else
							{
								$query.="(".$result[$i][0].",".$result[$i][1]."), ";
							}
						}
						$result = $dbr->query($query);
						$dbr->commit();
						$settings['lastSFWUpdate'] = time();
						$fp = fopen( $wgCTDataStoreFile, 'w' ) or error_log( 'Could not open file:' . $wgCTDataStoreFile );
						fwrite( $fp, json_encode($settings) );
						fclose( $fp ); 
					}
				}
				
				/* Check IP here */
				
				$is_sfw_check=true;
			   	$ip=CleantalkGetIP();
			   	$ip=array_unique($ip);
			   	$key=$wgCTAccessKey;
			   	for($i=0;$i<sizeof($ip);$i++)
				{
			    	if(isset($_COOKIE['ct_sfw_pass_key']) && isset($ip[$i]) && $_COOKIE['ct_sfw_pass_key']==md5($ip[$i].$key))
			    	{
			    		$is_sfw_check=false;
			    		if(isset($_COOKIE['ct_sfw_passed']))
			    		{
			    			@setcookie ('ct_sfw_passed', '0', 1, "/");
			    		}
			    	}
			    }
				if($is_sfw_check)
				{
					$sfw = new CleanTalkSFW();
					$sfw->cleantalk_get_real_ip();
					$sfw->check_ip();
					if($sfw->result)
					{
						$sfw->sfw_die();
					}
				}
				
				/* Finish checking IP */
			}
		}
		
		/* SFW ends */
		
		if($wgCTShowLink)
		{
			$text.="<div style='width:100%;text-align:center;'><a href='https://cleantalk.org'>MediaWiki spam</a> blocked by CleanTalk.</div>";
		}
		return true;
	}
}

function CleantalkGetIP()
{
	$result=Array();
	if ( function_exists( 'apache_request_headers' ) )
	{
		$headers = apache_request_headers();
	}
	else
	{
		$headers = $_SERVER;
	}
	if ( array_key_exists( 'X-Forwarded-For', $headers ) )
	{
		$the_ip=explode(",", trim($headers['X-Forwarded-For']));
		$result[] = trim($the_ip[0]);
	}
	if ( array_key_exists( 'HTTP_X_FORWARDED_FOR', $headers ))
	{
		$the_ip=explode(",", trim($headers['HTTP_X_FORWARDED_FOR']));
		$result[] = trim($the_ip[0]);
	}
	$result[] = filter_var( $_SERVER['REMOTE_ADDR'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 );

	if(isset($_GET['sfw_test_ip']))
	{
		$result[]=$_GET['sfw_test_ip'];
	}
	return $result;
}