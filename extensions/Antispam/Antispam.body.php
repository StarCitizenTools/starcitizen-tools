<?php

class CTBody {
    /**
	 * Builds MD5 secret hash for JavaScript test (self::JSTest) 
	 * @return string 
	 */
    public static function getJSChallenge() {
        global $wgCTAccessKey, $wgEmergencyContact;

        return md5( $wgCTAccessKey . '+' . $wgEmergencyContact ); 
    } 
    
    /**
	 * Tests hidden field falue for secret hash 
	 * @return 0|1|null 
	 */
    public static function JSTest() {
        global $wgRequest, $wgCTHiddenFieldName;
        
        $result = null;
         
        $jsPostValue = $wgRequest->getVal( $wgCTHiddenFieldName );
        if ( $jsPostValue ) {
            $challenge = self::getJSChallenge();

            if ( preg_match( "/$/", $jsPostValue ) ) {
                $result = 1;
            } else {
                $result = 0;
            } 
        }
            
        return $result; 
    } 
    
    /**
	 * Calculate form fill time 
	 * @return 0|1|null 
	 */
    public static function SubmitTimeTest() {
        global $wgCTSubmitTimeLabel;

        $submit_time = null;
        if (isset($_SESSION[$wgCTSubmitTimeLabel])) {
            $submit_time = time() - (int) $_SESSION[$wgCTSubmitTimeLabel];
        }

        return $submit_time;
    } 
    
    /**
	 * Adds hidden field to form for JavaScript test 
	 * @return string 
	 */
    public static function AddJSCode() {
        global $wgCTHiddenFieldName, $wgCTHiddenFieldDefault, $wgCTExtName;
        
        $ct_checkjs_key = CTBody::getJSChallenge(); 
        
        $field_id = $wgCTHiddenFieldName . '_' . md5( rand( 0, 1000 ) );
        $html = '
<input type="hidden" id="%s" name="%s" value="%s" />
<script type="text/javascript">
// <![CDATA[
var ct_input_name = \'%s\';
var ct_input_value = document.getElementById(ct_input_name).value;
var ct_input_challenge = \'%s\'; 

document.getElementById(ct_input_name).value = document.getElementById(ct_input_name).value.replace(ct_input_value, ct_input_challenge);

if (document.getElementById(ct_input_name).value == ct_input_value) {
    document.getElementById(ct_input_name).value = ct_set_challenge(ct_input_challenge); 
}

function ct_set_challenge(val) {
    return val; 
}; 

// ]]>
</script>
';
        $html = sprintf( $html, $field_id, $wgCTHiddenFieldName, $wgCTHiddenFieldDefault, $field_id, $ct_checkjs_key );
        
        $html .= '<noscript><p><b>Please enable JavaScript to pass anti-spam protection!</b><br />Here are the instructions how to enable JavaScript in your web browser <a href="http://www.enable-javascript.com" rel="nofollow" target="_blank">http://www.enable-javascript.com</a>.<br />' . $wgCTExtName . '.</p></noscript>';

        return $html;
    }

    /**
	 * Sends email notificatioins to admins 
	 * @return bool 
	 */
    public static function SendAdminEmail( $title, $body ) {
        global $wgCTExtName, $wgCTAdminAccountId, $wgCTDataStoreFile, $wgCTAdminNotificaionInteval;
        
        if ( file_exists($wgCTDataStoreFile) ) {
            $settings = file_get_contents ( $wgCTDataStoreFile );
            if ( $settings ) {
                $settings = json_decode($settings, true);
            }
        }
        
        // Skip notification if permitted interval doesn't exhaust
        if ( isset( $settings['lastAdminNotificaionSent'] ) && time() - $settings['lastAdminNotificaionSent'] < $wgCTAdminNotificaionInteval ) {
            return false; 
        }
        
        $u = User::newFromId( $wgCTAdminAccountId );
         
        $status = $u->sendMail( $title , $body );

        if ( $status->ok ) {
            $fp = fopen( $wgCTDataStoreFile, 'w' ) or error_log( 'Could not open file:' . $wgCTDataStoreFile );
            $settings['lastAdminNotificaionSent'] = time();
            fwrite( $fp, json_encode($settings) );
            fclose( $fp );   
        }

        return $status->ok;
    }
}

?>