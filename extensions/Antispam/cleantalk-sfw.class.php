<?php

/**
Cleantalk Spam FireWall class
**/

class CleanTalkSFW
{
	public $ip = 0;
	public $ip_str = '';
	public $ip_array = Array();
	public $ip_str_array = Array();
	public $blocked_ip = '';
	public $result = false;
	
	public function cleantalk_get_real_ip()
	{
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
			$the_ip = trim($the_ip[0]);
			$this->ip_str_array[]=$the_ip;
			$this->ip_array[]=sprintf("%u", ip2long($the_ip));
		}
		if ( array_key_exists( 'HTTP_X_FORWARDED_FOR', $headers ))
		{
			$the_ip=explode(",", trim($headers['HTTP_X_FORWARDED_FOR']));
			$the_ip = trim($the_ip[0]);
			$this->ip_str_array[]=$the_ip;
			$this->ip_array[]=sprintf("%u", ip2long($the_ip));
		}
		$the_ip = filter_var( $_SERVER['REMOTE_ADDR'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 );
		$this->ip_str_array[]=$the_ip;
		$this->ip_array[]=sprintf("%u", ip2long($the_ip));

		if(isset($_GET['sfw_test_ip']))
		{
			$the_ip=$_GET['sfw_test_ip'];
			$this->ip_str_array[]=$the_ip;
			$this->ip_array[]=sprintf("%u", ip2long($the_ip));
		}
	}
	
	public function check_ip()
	{
		$passed_ip='';
		global $wgCTAccessKey;
		for($i=0;$i<sizeof($this->ip_array);$i++)
		{
			$dbr = wfGetDB(DB_SLAVE);
			
			$sql="select count(network) as cnt from `cleantalk_sfw` where network = ".$this->ip_array[$i]." & mask";
			$result = $dbr->query($sql);
			$row = $dbr->fetchRow ( $result );
    		$cnt = intval($row['cnt']);
			

			if($cnt>0)
			{
				$this->result=true;
				$this->blocked_ip=$this->ip_str_array[$i];
			}
			else
			{
				$passed_ip = $this->ip_str_array[$i];
			}
		}
		if($passed_ip!='')
		{
			$key=$wgCTAccessKey;
			@setcookie ('ct_sfw_pass_key', md5($passed_ip.$key), 0, "/");
		}
	}
	
	public function sfw_die()
	{
		global $wgCTAccessKey;
		$key=$wgCTAccessKey;
		$sfw_die_page=file_get_contents(dirname(__FILE__)."/sfw_die_page.html");
		$sfw_die_page=str_replace("{REMOTE_ADDRESS}",$this->blocked_ip,$sfw_die_page);
		$sfw_die_page=str_replace("{REQUEST_URI}",$_SERVER['REQUEST_URI'],$sfw_die_page);
		$sfw_die_page=str_replace("{SFW_COOKIE}",md5($this->blocked_ip.$key),$sfw_die_page);
		@header('HTTP/1.0 403 Forbidden');
		print $sfw_die_page;
		die();
	}
}

?>