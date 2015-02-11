<?php
function /*bool*/ authenticate($username, $password, $method=TRUE){
	global $heracles;
	if(!isset($heracles) || !is_object($heracles) ){ $heracles = new Heracles(); }
	return $heracles->authenticate($username, $password, $method);
}
function /*bool*/ authenticate_by_session($username, $key, $expires=0, $method=NULL, $created=FALSE){
	global $heracles;
	if(!isset($heracles) || !is_object($heracles) ){ $heracles = new Heracles(); }
        return $heracles->authenticate_by_session($username, $key, $expires=0, $method=NULL, $created=FALSE);
}
function /*bool*/ try_to_authenticate(/*no arguments!*/){}
function /*bool*/ is_authenticated($method=TRUE){}
function /*bool*/ is_granted_to($priviledge, $object=FALSE, $method=TRUE){}
class Heracles {
	private $_configfile = NULL;
	var $settings = array();
	/*debug*/ private $_debug = array();
	function Heracles(){
		/*fix*/ session_start();
		$this->settings = self::get_authentication_settings();

		global $heracles; $heracles = $this;
	}
	var $error = array(); var $warning = array();
	function /*bool*/ authenticate($username, $password, $method=TRUE){
		//$auth_settings = array('methods' => array());
	
	/*debug*/ if(self::_auth_debug()){ $this->_debug[] = __FUNCTION__.'() := '.implode(" , ", array($username, $password, $method)); }
	
		//check if already is authenticated
		if(isset($this->settings['keys']) && is_array($this->settings['keys']) && isset($this->settings['hash']) && $this->settings['hash'] == self::authenticate_key() && in_array(self::authenticate_key($username, $password, $method), $this->settings['keys'])){
	/*debug*/ if(self::_auth_debug()){ $this->_debug[] = __FUNCTION__.'() tells '.$username.' is already authenticated'; }
			return TRUE;
		}
	
		//authenticate
		if($method === TRUE){ //try all methods until authentication succeeds
	/*debug*/ if(self::_auth_debug()){ $this->_debug[] = __FUNCTION__.'() starts multiple ('.count($this->settings['methods']).') methods'; }
			$b = TRUE;
			foreach($this->settings['methods'] as $m){
				if(isset($this->settings['explicite']) && $this->settings['explicite'] == TRUE){
					$b = ($b && self::authenticate($username, $password, $m));
				} else {
					if(self::authenticate($username, $password, $m)){ return TRUE; }
				}
			}
			if(isset($this->settings['explicite']) && $this->settings['explicite'] == TRUE){ return $b; }
		}
		else{
			if(in_array(strtolower($method), $this->settings['methods'])){ //checks if $method is allowed
	/*debug*/ if(self::_auth_debug()){ $this->_debug[] = __FUNCTION__.'() processes '.$method.' method'; }
				switch(strtolower($method)){
	//*debug*/			case 'anonymous': return TRUE; break;
	//				case 'http':
	//					$_SERVER['PHP_AUTH_USER'] = $username; $_SERVER['PHP_AUTH_PW'] = $password;
	//					//authenticate_key($username, $password, $method, TRUE);
	//					break;
					case 'pam':
						if(function_exists("pam_auth")){
							if( pam_auth($username, $password, &$error, FALSE) ){
								return self::authenticate_key($username, $password, $method, TRUE);
								//return TRUE;
							}
							$this->warning[] = $error; //on Authentication failure, only deserves a warning: authenticate() will still return FALSE, but process won't halt
	/*debug*/ if(self::_auth_debug()){ $this->_debug[] = __FUNCTION__.'.pam_auth() > '.print_r($error, TRUE); }
						}
						else{
							$this->warning[] = 'PECL PAM not available';
	/*debug*/ if(self::_auth_debug()){ $this->_debug[] = __FUNCTION__.'() tells pam_auth() does not exist on this system. Check your php.ini file and "pecl install pam"'; }
						}
						break;
					default:
						return FALSE;
				}
			}
			else{
	/*debug*/ if(self::_auth_debug()){ $this->_debug[] = __FUNCTION__.'() ignores '.$method.' method'; }
			}
		}
		return FALSE; //if no return value is given, then return FALSE
	}
	private function authenticate_key($username=TRUE, $password=NULL, $method=TRUE, $add=FALSE, $start_session=TRUE, $add_session=TRUE){
	/*debug*/ if(self::_auth_debug()){ $this->_debug[] = __FUNCTION__.'() := '.implode(" , ", array($username, $password, $method, $add, $start_session, $add_session)); }
		if(($username === TRUE && $password===NULL && $method === TRUE ) && !is_array($add)){ return md5(implode("\n", $this->settings['keys'])); }
		/*fix*/ if($username == NULL || strlen($username) <= 2){ return FALSE; }
		$str = md5($username.':'.$password.':'.strtolower($method));
	/*debug*/ if(self::_auth_debug()){ $this->_debug[] = __FUNCTION__.'() generates '.$str; }
		if($add === TRUE && $this->settings['hash'] == self::authenticate_key() /* && self::authenticate($username, $password, $method) */){
			if($add_session === TRUE){ $_SESSION['AUTH_USERNAME'] = $username; }
			$this->settings['keys'][] = $str;
			if($start_session === TRUE){
				$now = date('U');
				$expires = ($this->settings['validation_length'] <= 0 ? 0 : ( $now + $this->settings['validation_length'] ) );
				$session = md5($username.':'.$str.':'.$now.'~'.$expires.':'.$method);
				$this->settings['keys'][] = $session;
	/*debug*/ if(self::_auth_debug()){ $this->_debug[] = __FUNCTION__.'() adds @'.$now.' session '.$session.' to expire @'.$expires; }
				if($add_session === TRUE){
					$_SESSION['AUTH_KEY'][substr(/*md5($username.':'.$str)*/ $str, 0, 4)] = array('key'=>$session,'created'=>$now,'expires'=>$expires,'method'=>$method);
					/*clean-up*/ foreach($_SESSION['AUTH_KEY'] as $akk=>$aka){
						if($aka['expires'] !== 0 && $aka['expires'] < $now){ unset($_SESSION['AUTH_KEY'][$akk]); }
						//elseif(self::authenticate_by_session($username, $aka['key'], $aka['expires'], $aka['method'], $aka['created']) == FALSE){ unset($_SESSION['AUTH_KEY'][$akk]); }
					}
				}
			}
			$this->settings['hash'] = md5(implode("\n", $this->settings['keys']));
		}
		if(is_array($add)){
			if($method === TRUE){ return md5(implode("\n", $add)); }
			else{ return in_array($str, $add); }
		}
		return ($start_session === TRUE && $add === TRUE ? array('session'=>$session,'expires'=>$expires,'created'=>$now) : $str);
	}
	function authenticate_by_session($username, $key, $expires=0, $method=NULL, $created=FALSE){ //dummy
		if(is_array($key) && isset($key['key']) && isset($key['method']) && (isset($key['created']) || isset($key[$expires]))){
			foreach(array('created','expires','method','key') as $label){ //do key as last because it will destroy the array
				if(isset($key[$label])){ $$label = $key[$label]; }
			}
		}
		/*fix*/ if(is_array($key)){ return FALSE; }

		/*fix*/ if(!isset($this->settings['validation_length'])){ $this->settings['validation_length'] = 86400;}
	/*debug*/ if(self::_auth_debug()){ $this->_debug[] = __FUNCTION__.'() := '.implode(" , ", array($username, $key, $expires, $method, $created)); }
		if(in_array($key, $this->settings['keys'])){
			if($created === FALSE){ $created = ( $expires - $this->settings['validation_length'] ); }
			$now = date('U');
	/*debug*/ if(self::_auth_debug()){ $this->_debug = __FUNCTION__.'() finds '.$key; }
			if(($now < $expires || $expires == 0) && ($created > 0 && $created < $now)){
	/*debug*/ if(self::_auth_debug()){ $this->_debug = __FUNCTION__.'() deals with a valid timeframe: ['.$created.' < '.$now.' < '.$expires.']'; }
				foreach($this->settings['keys'] as $src_key){
					if($key === md5($username.':'.$src_key.':'.$created.'~'.$expires.':'.$method)){
	/*debug*/ if(self::_auth_debug()) $this->_debug = __FUNCTION__.'() matches '.$key.' with '.$src_key;
						return TRUE;
					}
				}
			}
		}
		return FALSE;
	}
	function change_password($username, $password, $newpassword, $method=TRUE){ //dummy
		//PAM: pam_chpass($username, $password, $newpassword, &$error)
		return FALSE;
	}
	function signout(){ //dummy
		//experimental
		unset($_SERVER['PHP_AUTH_USER']);
		unset($_SERVER['PHP_AUTH_PW']);
	
		unset($_SESSION['AUTH_KEY']);
		unset($_SESSION['AUTH_USERNAME']);
		return FALSE;
	}
	function get_authentication_settings($filename=FALSE){
		$set = array();
		if( $filename === FALSE || !file_exists($filename) ){ $filename = (isset($this->_configfile) && strlen($this->_configfile) > 3 ? $this->_configfile : dirname(__FILE__)."/authenticate.json" ); }
	/*debug*/ if(self::_auth_debug(TRUE)){ $this->_debug[] = __FUNCTION__.'.load( '.$filename.' )'; }
		if(!file_exists($filename)){ return FALSE; }
		$this->_configfile = $filename;
		$set = json_decode(file_get_contents($filename), TRUE);
		if(!isset($set['methods']) || !is_array($set['methods'])){ $set['methods'] = array(); } //http,pam,unix,mysql,mysql-table
		if(!isset($set['keys']) || !is_array($set['keys']) || !isset($set['hash'])){
			$set['keys'] = array();
			$set['hash'] = md5(implode("\n", $set['keys']));
		}
		/*fix*/ if(!isset($set['validation_length'])){ $set['validation_length'] = 60*60;}
		//*debug*/ $set['HTTP authentication'] = TRUE;
		//*debug*/ $set['debug'] = TRUE;
		return $set;
	}
	function save_authentication_settings($filename=FALSE, $settings=FALSE){
		if($settings === FALSE){
			if(!isset($this->settings)){ return FALSE; }
			$settings = $this->settings;
		}
		if(!is_array($settings) || !isset($settings['methods']) || !is_array($settings['methods']) || !isset($settings['keys']) || !is_array($settings['keys']) || !isset($settings['hash']) ){ return FALSE; }
		if( $filename === FALSE || !file_exists($filename) ){ $filename = (isset($this->_configfile) && strlen($this->_configfile) >3 ? $this->_configfile : dirname(__FILE__)."/authenticate.json" ); }
		if(!file_exists($filename)){ return FALSE; }
		return file_put_contents($filename, json_encode($settings));
	}
	function authentication_form($username, $password=NULL, $action=NULL){
		$str = NULL;
		$str .= '<table class="authentication-form"><form method="POST"'.($action!=NULL ? ' action="'.$action.'"' : NULL).'>'."\n\t";
		$str .= '<tr><td>Username:</td><td><input type="text" class="username" name="username" value="'.$username.'" /></td></tr>'."\n\t";
		$str .= '<tr><td>Password:</td><td><input type="password" class="password" name="password" value="'.$password.'" /></td></tr>'."\n\t";
		$str .= '<tr><td rowspan="2" class="submit right"><input type="submit" class="button" value="Authenticate" /><a href="'.$action.'?sign-out">Sign Out<a/></td></tr>'."\n";
		$str .= '</form></table>'."\n";
		return $str;
	}
	private function _auth_debug($force=FALSE){
		if($force === TRUE || !isset($this->settings) || !is_array($this->settings)){ return TRUE; }
		return (isset($this->settings["debug"]) && $this->settings["debug"] == TRUE);
	}
	
	function within_page_html_hook(){
		/*### ?sign-out ###*/
		if(preg_match("#^sign-out$#i", $_SERVER['QUERY_STRING'])){
			self::signout();
			$target = preg_replace("#\?".$_SERVER['QUERY_STRING']."$#", "", $_SERVER['REQUEST_URI']);
			header("Location: ".$target);
			print '<a href="'.$target.'">You are now signed out.</a>';
			//exit;
		}
		
		/*### HTTP authentication ###*/
		if(isset($this->settings["HTTP authentication"]) && $this->settings["HTTP authentication"] === TRUE){
			//*fix*/ if(isset($_SERVER['PHP_AUTH_USER'])){ $_POST['username'] = (isset($_POST['username']) ? $_POST['username'] : $_SERVER['PHP_AUTH_USER']); $_POST['password'] = (isset($_POST['password']) ? $_POST['password'] : $_SERVER['PHP_AUTH_PW']); }
			/*fix*/ if(isset($_POST['username']) && strlen($_POST['username']) > 1){ self::authenticate($_POST['username'], $_POST['password'], 'http'); }
		
		        /*warning-fix*/ if(!isset($_POST) || !isset($_POST["username"])){ $_POST = array('username'=>NULL,'password'=>NULL); }
			if (!isset($_SERVER['PHP_AUTH_USER']) && !self::authenticate($_POST['username'], $_POST['password'])) {
				$realm = "Test Authentication System";
				header('HTTP/1.0 401 Unauthorized');
				header('WWW-Authenticate: Basic realm="'.$realm.'"');
				//header('WWW-Authenticate: Digest realm="'.$realm.'",qop="auth",nonce="'.uniqid().'",opaque="'.md5($realm).'"');
				print self::authentication_form((isset($_SERVER['PHP_AUTH_USER']) ? $_SERVER['PHP_AUTH_USER'] : NULL), (isset($_SERVER['PHP_AUTH_PW']) ? $_SERVER['PHP_AUTH_PW'] : NULL));
				//exit;
			} else {
				echo "<p>Hello <em>{$_SERVER['PHP_AUTH_USER']}<em>.</p>";
				echo "<p>You entered <em>{$_SERVER['PHP_AUTH_PW']}</em> as your password.</p>";
			}
			echo "<hr/>";
		}
		
		/*### debug ###*/
		//if(isset($this->settings["debug"]) && $this->settings["debug"] == TRUE){
		if(self::_auth_debug()){
			/*warning-fix*/ if(!isset($_POST) || !isset($_POST["username"])){ $_POST = array('username'=>NULL,'password'=>NULL); }
			print self::authentication_form($_POST["username"], $_POST["password"]);
		
			print '<pre>';
			if(isset($_POST)) print '$_POST = '; print_r($_POST);
			print '$_SERVER = Array'."\n(\n    ...\n"; foreach($_SERVER as $key=>$value){ if(preg_match("#^PHP_#", $key)){ print "    [".$key."] => ".print_r($value, TRUE)."\n"; } } print ")\n";
			print '(default) $auth = '; print_r($this->settings);
			print 'AUTHENTICATE: '; print_r(self::authenticate($_POST["username"], $_POST["password"], TRUE, TRUE)); print "\n";
		//	$sess = self::authenticate_key($_POST["username"], $_POST["password"], 'anonymous', TRUE);
		//	print 'SESSION: '; print_r($sess); print "\n";
			print '(current) $auth = '; print_r($this->settings);
			print '$_SESSION = '; print_r($_SESSION);
			print "\n<hr/>\n".'DEBUG: '; print_r($this->_debug);
			print '</pre>';
		}
	}
}

$heracles = new Heracles();
$heracles->within_page_html_hook();

/*debug*/ print '<pre>'; print_r($heracles); print '</pre>';
?>
