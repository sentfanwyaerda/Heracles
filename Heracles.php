<?php 
if(!defined('HERACLES_DB_FILE')){ define('HERACLES_DB_FILE', dirname(__FILE__).'/users.json'); }
if(!defined('HERACLES_SESSION_LENGTH')){ define('HERACLES_SESSION_LENGTH', (12*60*60)); }

if(file_exists(dirname(dirname(__FILE__)).'/Morpheus/Morpheus.php')){ require_once(dirname(dirname(__FILE__)).'/Morpheus/Morpheus.php'); }

class Heracles {
	var $db_file;
	var $length;
	function Heracles(){
		$this->db_file = $this->get_dbfile();
		$this->length = $this->get_session_length();
	}
	
	function get_dbfile(){
		if(defined('HERACLES_DB_FILE')){ $heraclesdb = HERACLES_DB_FILE; }
		elseif(isset($this)){ $heraclesdb = $this->db_file; }
		else{ global $heraclesdb; }
		return $heraclesdb;
	}
	function get_session_length(){
		if(defined('HERACLES_SESSION_LENGTH')){ $slength = HERACLES_SESSION_LENGTH; }
		elseif(isset($this)){ $slength = $this->length; }
		else{ return 15; }
		return $slength;
	}
	function open_db(){
		$db = json_decode(file_get_contents(\Heracles::get_dbfile()), TRUE);
		return $db;
	}
	function list_users($assigned=FALSE){
		$list = array();
		$db = \Heracles::open_db(\Heracles::get_dbfile());
		foreach($db as $i=>$record){
			if(isset($record['username'])){
				if($assigned != FALSE){ $list[$record['username']] = \Heracles::build_fullname($record); }
				else { $list[] = $record['username']; }
			}
		}
		if($assigned != FALSE){ ksort($list); }
		return $list;
	}
	function build_fullname($record=NULL, $pattern=FALSE){
		if($record == NULL){
			$record = \Heracles::load_record(\Heracles::get_user_id());
		}
		return implode(' ', array($record['name']['initials'], (strlen($record['name']['first']) > 1 ? '('.$record['name']['first'].')' : NULL), $record['name']['lastprefix'], $record['name']['last']));
	}
	function load_record($key=NULL){
		$record = array();
		$db = \Heracles::open_db(\Heracles::get_dbfile());
		if(!is_array($key)){ $key = array('username' => $key); }
		foreach($db as $i=>$r){
			if(\Heracles::array_match($key, $r)){ return \Heracles::_lrfix($r); }
		}
		return array();
	}
	function load_record_flags($key=NULL){
		$record = \Heracles::load_record($key);
		return \Heracles::_lrfix($record);
	}
	function save_record($record=array(), $mode=NULL){
		if($mode == NULL && isset($record['select'])){ $mode = $record['select']; }
		$db = \Heracles::open_db(\Heracles::get_dbfile());
		
		$record = \Heracles::_srfix($record);
		
		//*test record if valid commit*/ if(!isset($record['select'])){ return FALSE; }
		
		if($mode == 'new'){
			$db[] = $record;
		} else {
			//*debug*/ print '<!-- '.print_r($record, TRUE).' x '.print_r($db, TRUE).' -->';
			foreach($db as $i=>$r){
				if(\Heracles::array_match(array('username' => $record['username']), $r)){
					//*notify*/ print '<!-- [Heracles] save_record matched by '.$record['username'].' and saved -->';
					$db[$i] = $record;
				}
			}
		}
		
		file_put_contents(\Heracles::get_dbfile(), json_encode($db));
		return TRUE;
	}
	function remove_record($key=NULL){
		$db = \Heracles::open_db(\Heracles::get_dbfile());
		if(!is_array($key)){ $key = array('username' => $key); }
		foreach($db as $i=>$r){
			if(\Heracles::array_match($key, $r)){
				unset($db[$i]);
				file_put_contents(\Heracles::get_dbfile(), json_encode($db));
				return TRUE;
			}
		}
		return FALSE;
	}
	function anonymous(){
		@session_start();
		$_SESSION['hash'] = NULL;
	}
	function try_to_authenticate(){
		if(isset($_POST) && is_array($_POST) && (isset($_POST['username']) || isset($_POST['account'])) && isset($_POST['password'])){ \Heracles::authenticate((isset($_POST['username']) ? $_POST['username'] : $_POST['account']), $_POST['password']); }
	}
	function authenticate($username, $password=FALSE){
		$record = \Heracles::load_record($username);
		if($record['pass-hash'] == md5($username.':'.$password) ){
			session_start();
			$start = round(microtime(TRUE),1);
			$_SESSION['start'] = $start;
			$_SESSION['user'] = $username;
			$_SESSION['hash'] = md5($username.':'.$start.':'.md5($username.':'.$password));
			return TRUE;
		}
		return FALSE;
	}
	function authenticate_by_session($username, $key, $expires=0, $method=NULL, $created=FALSE){
		$record = \Heracles::load_record($username);
		$start = ($created != FALSE ? round($created,1) : ($expires != 0 ? round($expires - \Heracles::get_session_length() , 1) : round(microtime(TRUE),1) ) );
		if($key == md5($username.':'.$start.':'.$record['pass-hash']) ){
			@session_start();
			$_SESSION['start'] = $start;
			$_SESSION['user'] = $username;
			$_SESSION['hash'] = $key;
			return TRUE;
		}
		return FALSE;		
	}
	function is_authenticated(){
		@session_start();
		if( !isset($_SESSION['user']) || !isset($_SESSION['hash']) ){ return FALSE; }
		return ($_SESSION['hash'] == md5($_SESSION['user'].':'.$_SESSION['start'].':'.\Heracles::get_passhash($_SESSION['user'])) && TRUE /*$_SESSION['start'] <~ 1 hour (\Heracles::get_session_length()) */ );
	}
	function get_passhash($key=NULL, $password=FALSE, $confirm=FALSE){
		if($password !== FALSE && ($password == $confirm) ){
			return md5($key.':'.$password);
		}
		else{
			$record = \Heracles::load_record($key);
			return (isset($record['pass-hash']) ? $record['pass-hash'] : NULL);
		}
	}
	function get_user_id(){
		if(\Heracles::is_authenticated()){ return $_SESSION['user']; }
		else { return FALSE; }
	}
	function has_role($role=array(), $operator="AND", $user=FALSE){
		if($user == FALSE){ $user = $_SESSION['user']; }
		$record = \Heracles::load_record($user);
		return \Heracles::array_match($role, $record['role'], FALSE, $operator);
	}
	function has_access_to_application($app=NULL, $user=FALSE){
		if($user == FALSE){ $user = $_SESSION['user']; }
		$record = \Heracles::load_record($user);
		return ( (defined('HERACLES_AUTO_WHITELIST') && constant('HERACLES_AUTO_WHITELIST') == TRUE ? !isset($record['application_whitelist']) : FALSE ) || \Heracles::array_match($app, $record['application_whitelist'], FALSE, "AND") );
	}
	function has_access_to_widget($app=NULL, $user=FALSE){
		if($user == FALSE){ $user = $_SESSION['user']; }
		$record = \Heracles::load_record($user);
		return ( (defined('HERACLES_AUTO_WHITELIST') && constant('HERACLES_AUTO_WHITELIST') == TRUE ? !isset($record['widget_whitelist']) : FALSE ) || \Heracles::array_match($app, $record['widget_whitelist'], FALSE, "AND") );
	}
	function in_group($group=array(), $operator="AND", $user=FALSE){
		if($user == FALSE){ $user = $_SESSION['user']; }
		$record = \Heracles::load_record($user);
		return \Heracles::array_match($group, $record['group'], FALSE, $operator);
	}
	function first_order(){
		$db = \Heracles::open_db();
		return array_keys($db[0]);
	}
	/********************************************************
	 * HTML generating Methods (examples in /admin/)
	 ********************************************************/
	function html_management($flags=array()){
		$skin = dirname(__FILE__).'/admin/';
		if(!is_array($flags)){ $flags = array(); }
		if(!isset($flags['t.domain'])){ $flags['t.domain'] = '@'.(defined('HADES_DOMAIN') ? HADES_DOMAIN : 'domain.ltd'); }

		if( \Heracles::is_authenticated() && \Heracles::has_role('administrator') ){
			if(is_array($_POST) && isset($_POST['select']) && TRUE){
				\Heracles::save_record($_POST);
				//*notify*/ print '<!-- [Heracles] save record of '.print_r($_POST, TRUE).' -->';
			}
			/*fix*/ if(!isset($flags) || !is_array($flags)){ $flags = array(); }
			
			if(isset($_GET['delete'])){
				\Heracles::remove_record($_GET['delete']); $_GET['for'] = 'new';
				//*notify*/ print '<!-- [Heracles] delete '.$_GET['delete'].' -->';
			}
			
			#/*fill $_POST*/ $flags = array_merge($flags, $_POST);
			if(isset($_GET['for']) && $_GET['for'] != 'new'){
				$flags = array_merge($flags, \Heracles::load_record($_GET['for']));
				//*notify*/ print '<!-- [Heracles] load records of '.$_GET['for'].' -->';
			}
			
			if(isset($_GET['for'])){
				$flags['username'] = $_GET['for'];
				if($_GET['for'] == 'new'){ $flags['username'] = NULL; $flags['username.edit'] = TRUE; }
			} else { $flags['username.edit'] = TRUE; }
			$flags = array_merge($flags, array(
					'selector' => Morpheus::basic_parse_str(\Heracles::_lb_select_options(array_merge(array('new'=>'{t.adduser|Add User}'), \Heracles::list_users(TRUE)), (isset($flags['username']) ? $flags['username'] : NULL )) , $flags),
					'sex-select' => Morpheus::basic_parse_str(\Heracles::_lb_select_options(array('m'=>'{t.male|male}','f'=>'{t.female|female}','x'=>'{t.other|other}'), (isset($flags['sex']) ? $flags['sex'] : NULL )) , $flags),
				));
			return Morpheus::basic_parse($skin.'edit-user.html', $flags);
		}
		else{
			# header("Location: ./management.php"); exit;
			# print '<script>window.location.href="./authenticate.php";</script>'; exit;
			return \Heracles::html_authenticate(); //exit;
		}
	}
	function html_authenticate($flags=array()){
		$skin = dirname(__FILE__).'/admin/';
		if(!is_array($flags)){ $flags = array(); }
		return Morpheus::basic_parse($skin.'authenticate.html', $flags);
	}
	/********************************************************
	 * Assisting Methods
	 ********************************************************/
	function _lrfix($r){
		$r['role'] = implode(",", $r['role']);
		$r['group'] = implode(",", $r['group']);
		$r = \Heracles::_lrfix_array($r, 'phone');
		$r = \Heracles::_lrfix_array($r, 'email');
		$r = \Heracles::_lrfix_array($r, 'twitter');
		$r = \Heracles::_lrfix_array($r, 'address');
		$r = \Heracles::_lrfix_array($r, 'name');
		if(!isset($r['name[full]'])){ $r['name[full]'] = \Heracles::build_fullname($r); }
		return $r;
	}
	function _lrfix_array($r, $item){
		if(isset($r[$item]) && is_array($r[$item])){
			foreach($r[$item] as $i=>$p){
				$r[$item.'['.$i.']'] = $p;
				if(is_array($p)){ $r = \Heracles::_lrfix_array($r, $item.'['.$i.']'); }
			}
		}
		return $r;
	}
	function _srfix($r){ 
		//*fix*/ if(!is_array($r) || (!isset($r['username']) && count($r) < 2)){ return $r; }
		unset($r['select']);
		$r['pass-hash'] = \Heracles::get_passhash((isset($r['username']) ? $r['username'] : NULL), (isset($r['password']) && strlen($r['password']) > 1 ? $r['password'] : FALSE), (isset($r['password-confirm']) ? $r['password-confirm'] : NULL));
		unset($r['password']);
		unset($r['password-confirm']);
		$r['role'] = explode(",", (isset($r['role']) ? $r['role'] : NULL));
		$r['group'] = explode(",", (isset($r['group']) ? $r['group'] : NULL));
		$r = \Heracles::array_order($r, \Heracles::first_order());
		return $r;
	}
	function _lb_select_options($options=array(), $value=NULL){
		$str = NULL;
		$m = 0;
		foreach($options as $n=>$o){
			$str .= '<option';
			if(!(is_int($n) && $n==$m++)){ $str .= ' value="'.$n.'"'; }
			if($value == $o || (!is_int($n) && $value == $n)){ $str .= ' selected="true"'; }
			$str .= '>'.$o.'</option>';
		}
		return $str;
	}
	function array_match($needle=array(), $haystack=array(), $with_key=TRUE, $operator="AND", $explosive=','){
		$bool = TRUE;
		if(!is_array($needle)){ $needle = explode($explosive, $needle); /*return FALSE;*/ }
		if(!is_array($haystack)){ $haystack = explode($explosive, $haystack); }
		if($with_key == TRUE){
			foreach($needle as $n=>$v){
				if($needle[$n] == $haystack[$n]){
					//*notify*/ print '<!-- [Heracles] array_match by key of '.$needle[$n].' -->';
					if($operator !== "AND"){ return TRUE; }
					$bool = ($bool ? TRUE : FALSE);
				}
				else { $bool = FALSE; }
			}
		} else {
			foreach($needle as $v){
				if(in_array($v, $haystack)){
					//*notify*/ print '<!-- [Heracles] array_match of '.$v.' -->';
					if($operator !== "AND"){ return TRUE; }
					$bool = ($bool ? TRUE : FALSE);
				}
				else { $bool = FALSE; }
			}
		}
		return $bool;
	}
	function array_order($ar=array(), $order=array()){
		/*fix*/ if(!is_array($ar) || !is_array($order)){ return $ar; }
		ksort($ar);
		$n = array();
		foreach($order as $k){ if(isset($ar[$k])){$n[$k] = $ar[$k]; } }
		foreach($ar as $k=>$v){ if(!in_array($k, $order)){ $n[$k] = $ar[$k]; } }
		return $n;
	}
}

if(!function_exists("is_authenticated")){
	function is_authenticated(){
		return \Heracles::is_authenticated();
		#return (isset($_GET['debug']) && $_GET['debug'] == "auth" ? TRUE : FALSE);
		#return FALSE;
	}
}
if(!function_exists("authenticate")){
	function authenticate($username, $password=FALSE, $method=TRUE){
		return \Heracles::authenticate($username, $password);
	}
}
if(!function_exists("authenticate_by_session")){
	function authenticate_by_session($username, $key, $expires=0, $method=NULL, $created=FALSE){
		return \Heracles::authenticate_by_session($username, $key, $expires, $method, $created);
	}
}
?>