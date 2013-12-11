<?php
/*debug*/ $authenticate_debug = array();
/*fix*/ session_start();
$authenticate_settings = get_authentication_settings(); $authenticate_error = NULL;
function /*bool*/ authenticate($username, $password, $method=TRUE){
	global $authenticate_error;
/*debug*/ global $authenticate_debug;
	//$auth_settings = array('methods' => array());
	global $authenticate_settings; // = get_authentication_settings();

/*debug*/ if(auth_debug()){ $authenticate_debug[] = __FUNCTION__.'() := '.implode(" , ", array($username, $password, $method)); }

	//check if already is authenticated
	if(isset($authenticate_settings['keys']) && is_array($authenticate_settings['keys']) && isset($authenticate_settings['hash']) && $authenticate_settings['hash'] == authenticate_key() && in_array(authenticate_key($username, $password, $method), $authenticate_settings['keys'])){
/*debug*/ if(auth_debug()){ $authenticate_debug[] = __FUNCTION__.'() tells '.$username.' is already authenticated'; }
		return TRUE;
	}

	//authenticate
	if($method === TRUE){ //try all methods until authentication succeeds
/*debug*/ if(auth_debug()){ $authenticate_debug[] = __FUNCTION__.'() starts multiple ('.count($authenticate_settings['methods']).') methods'; }
		$b = TRUE;
		foreach($authenticate_settings['methods'] as $m){
			if(isset($authenticate_settings['explicite']) && $authenticate_settings['explicite'] == TRUE){
				$b = ($b && authenticate($username, $password, $m));
			} else {
				if(authenticate($username, $password, $m)){ return TRUE; }
			}
		}
		if(isset($authenticate_settings['explicite']) && $authenticate_settings['explicite'] == TRUE){ return $b; }
	}
	else{
		if(in_array(strtolower($method), $authenticate_settings['methods'])){ //checks if $method is allowed
/*debug*/ if(auth_debug()){ $authenticate_debug[] = __FUNCTION__.'() processes '.$method.' method'; }
			switch(strtolower($method)){
//*debug*/			case 'anonymous': return TRUE; break;
//				case 'http':
//					$_SERVER['PHP_AUTH_USER'] = $username; $_SERVER['PHP_AUTH_PW'] = $password;
//					//authenticate_key($username, $password, $method, TRUE);
//					break;
				case 'pam':
					if(function_exists("pam_auth")){
						if( pam_auth($username, $password, &$error, FALSE) ){
							return authenticate_key($username, $password, $method, TRUE);
							//return TRUE;
						}
/*debug*/ if(auth_debug()){ $authenticate_debug[] = __FUNCTION__.'.pam_auth() > '.print_r($error, TRUE); }
					}
					else{
/*debug*/ if(auth_debug()){ $authenticate_debug[] = __FUNCTION__.'() tells pam_auth() does not exist on this system. Check your php.ini file and "pecl install pam"'; }
					}
					break;
				default:
					return FALSE;
			}
		}
		else{
/*debug*/ if(auth_debug()){ $authenticate_debug[] = __FUNCTION__.'() ignores '.$method.' method'; }
		}
	}
	return FALSE; //if no return value is given, then return FALSE
}
function authenticate_key($username=TRUE, $password=NULL, $method=TRUE, $add=FALSE, $start_session=TRUE, $add_session=TRUE){
/*debug*/ global $authenticate_debug;
/*debug*/ if(auth_debug()){ $authenticate_debug[] = __FUNCTION__.'() := '.implode(" , ", array($username, $password, $method, $add, $start_session, $add_session)); }
	global $authenticate_settings;
	if(($username === TRUE && $password===NULL && $method === TRUE ) && !is_array($add)){ return md5(implode("\n", $authenticate_settings['keys'])); }
	/*fix*/ if($username == NULL || strlen($username) <= 2){ return FALSE; }
	$str = md5($username.':'.$password.':'.strtolower($method));
/*debug*/ if(auth_debug()){ $authenticate_debug[] = __FUNCTION__.'() generates '.$str; }
	if($add === TRUE && $authenticate_settings['hash'] == authenticate_key() /* && authenticate($username, $password, $method) */){
		if($add_session === TRUE){ $_SESSION['AUTH_USERNAME'] = $username; }
		$authenticate_settings['keys'][] = $str;
		if($start_session === TRUE){
			$now = date('U');
			$expires = ($authenticate_settings['validation_length'] <= 0 ? 0 : ( $now + $authenticate_settings['validation_length'] ) );
			$session = md5($username.':'.$str.':'.$now.'~'.$expires.':'.$method);
			$authenticate_settings['keys'][] = $session;
/*debug*/ if(auth_debug()){ $authenticate_debug[] = __FUNCTION__.'() adds @'.$now.' session '.$session.' to expire @'.$expires; }
			if($add_session === TRUE){
				$_SESSION['AUTH_KEY'][substr(/*md5($username.':'.$str)*/ $str, 0, 4)] = array('key'=>$session,'created'=>$now,'expires'=>$expires,'method'=>$method);
				/*clean-up*/ foreach($_SESSION['AUTH_KEY'] as $akk=>$aka){
					if($aka['expires'] !== 0 && $aka['expires'] < $now){ unset($_SESSION['AUTH_KEY'][$akk]); }
					//elseif(authenticate_by_session($username, $aka['key'], $aka['expires'], $aka['method'], $aka['created']) == FALSE){ unset($_SESSION['AUTH_KEY'][$akk]); }
				}
			}
		}
		$authenticate_settings['hash'] = md5(implode("\n", $authenticate_settings['keys']));
	}
	if(is_array($add)){
		if($method === TRUE){ return md5(implode("\n", $add)); }
		else{ return in_array($str, $add); }
	}
	return ($start_session === TRUE && $add === TRUE ? array('session'=>$session,'expires'=>$expires,'created'=>$now) : $str);
}
function authenticate_by_session($username, $key, $expires=0, $method=NULL, $created=FALSE){ //dummy
/*debug*/ global $authenticate_debug;
	global $authenticate_settings;
	/*fix*/ if(!isset($authenticate_settings['validation_length'])){ $authenticate_settings['validation_length'] = 86400;}
/*debug*/ if(auth_debug()){ $authenticate_debug[] = __FUNCTION__.'() := '.implode(" , ", array($username, $key, $expires, $method, $created)); }
	if(in_array($key, $authenticate_settings['keys'])){
		if($created === FALSE){ $created = ( $expires - $authenticate_settings['validation_length'] ); }
		$now = date('U');
/*debug*/ if(auth_debug()){ $authenticate_debug = __FUNCTION__.'() finds '.$key; }
		if(($now < $expires || $expires == 0) && ($created > 0 && $created < $now)){
/*debug*/ if(auth_debug()){ $authenticate_debug = __FUNCTION__.'() deals with a valid timeframe: ['.$created.' < '.$now.' < '.$expires.']'; }
			foreach($authenticate_settings['keys'] as $src_key){
				if($key === md5($username.':'.$src_key.':'.$created.'~'.$expires.':'.$method)){
					/*debug*/ if(auth_debug()) $authenticate_debug = __FUNCTION__.'() matches '.$key.' with '.$src_key;
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
/*debug*/ global $authenticate_debug;
	$set = array();
	if( $filename === FALSE || !file_exists($filename) ){ $filename = dirname(__FILE__)."/authenticate.json"; }
/*debug*/ if(auth_debug(TRUE)){ $authenticate_debug[] = __FUNCTION__.'.load( '.$filename.' )'; }
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
	/*debug*/ global $authenticate_debug;
	if($settings === FALSE){
		global $authenticate_settings;
		if(!isset($authenticate_settings)){ return FALSE; }
		$settings = $authenticate_settings;
	}
	if(!is_array($settings) || !isset($settings['methods']) || !is_array($settings['methods']) || !isset($settings['keys']) || !is_array($settings['keys']) || !isset($settings['hash']) ){ return FALSE; }
	if( $filename === FALSE || !file_exists($filename) ){ $filename = dirname(__FILE__)."/authenticate.json"; }
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
function auth_debug($force=FALSE){
	global $authenticate_settings;
	if($force === TRUE && !isset($authenticate_settings)){ return TRUE; }
	return (isset($authenticate_settings["debug"]) && $authenticate_settings["debug"] == TRUE);
}

/*### ?sign-out ###*/
if(preg_match("#^sign-out$#i", $_SERVER['QUERY_STRING'])){
	signout();
	$target = preg_replace("#\?".$_SERVER['QUERY_STRING']."$#", "", $_SERVER['REQUEST_URI']);
	header("Location: ".$target);
	print '<a href="'.$target.'">You are now signed out.</a>';
	//exit;
}

/*### HTTP authentication ###*/
if(isset($authenticate_settings["HTTP authentication"]) && $authenticate_settings["HTTP authentication"] === TRUE){
	//*fix*/ if(isset($_SERVER['PHP_AUTH_USER'])){ $_POST['username'] = (isset($_POST['username']) ? $_POST['username'] : $_SERVER['PHP_AUTH_USER']); $_POST['password'] = (isset($_POST['password']) ? $_POST['password'] : $_SERVER['PHP_AUTH_PW']); }
	/*fix*/ if(isset($_POST['username']) && strlen($_POST['username']) > 1){ authenticate($_POST['username'], $_POST['password'], 'http'); }

        /*warning-fix*/ if(!isset($_POST) || !isset($_POST["username"])){ $_POST = array('username'=>NULL,'password'=>NULL); }
	if (!isset($_SERVER['PHP_AUTH_USER']) && !authenticate($_POST['username'], $_POST['password'])) {
		$realm = "Test Authentication System";
		header('HTTP/1.0 401 Unauthorized');
		header('WWW-Authenticate: Basic realm="'.$realm.'"');
		//header('WWW-Authenticate: Digest realm="'.$realm.'",qop="auth",nonce="'.uniqid().'",opaque="'.md5($realm).'"');
		print authentication_form((isset($_SERVER['PHP_AUTH_USER']) ? $_SERVER['PHP_AUTH_USER'] : NULL), (isset($_SERVER['PHP_AUTH_PW']) ? $_SERVER['PHP_AUTH_PW'] : NULL));
		//exit;
	} else {
		echo "<p>Hello <em>{$_SERVER['PHP_AUTH_USER']}<em>.</p>";
		echo "<p>You entered <em>{$_SERVER['PHP_AUTH_PW']}</em> as your password.</p>";
	}
	echo "<hr/>";
}

/*### debug ###*/
//if(isset($authenticate_settings["debug"]) && $authenticate_settings["debug"] == TRUE){
if(auth_debug()){
	/*warning-fix*/ if(!isset($_POST) || !isset($_POST["username"])){ $_POST = array('username'=>NULL,'password'=>NULL); }
	print authentication_form($_POST["username"], $_POST["password"]);

	print '<pre>';
	if(isset($_POST)) print '$_POST = '; print_r($_POST);
	print '$_SERVER = Array'."\n(\n    ...\n"; foreach($_SERVER as $key=>$value){ if(preg_match("#^PHP_#", $key)){ print "    [".$key."] => ".print_r($value, TRUE)."\n"; } } print ")\n";
	print '(default) $auth = '; print_r($authenticate_settings);
	print 'AUTHENTICATE: '; print_r(authenticate($_POST["username"], $_POST["password"], TRUE, TRUE)); print "\n";
//	$sess = authenticate_key($_POST["username"], $_POST["password"], 'anonymous', TRUE);
//	print 'SESSION: '; print_r($sess); print "\n";
	print '(current) $auth = '; print_r($authenticate_settings);
	print '$_SESSION = '; print_r($_SESSION);
	print "\n<hr/>\n".'DEBUG: '; print_r($authenticate_debug);
	print '</pre>';
}
?>
