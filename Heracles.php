<?php 
#if(!defined('HERACLES_DB_FILE')){ define('HERACLES_DB_FILE', dirname(__FILE__).'/users.json'); }
if(!defined('HERACLES_SESSION_LENGTH')){ define('HERACLES_SESSION_LENGTH', (12*60*60)); }

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
		$db = json_decode(file_get_contents(Heracles::get_dbfile()), TRUE);
		return $db;
	}
	function list_users($assigned=FALSE){
		$list = array();
		$db = Heracles::open_db(Heracles::get_dbfile());
		foreach($db as $i=>$record){
			if(isset($record['username'])){
				if($assigned != FALSE){ $list[$record['username']] = Heracles::build_fullname($record); }
				else { $list[] = $record['username']; }
			}
		}
		if($assigned != FALSE){ ksort($list); }
		return $list;
	}
	function build_fullname($record=NULL){
		if($record == NULL){
			$record = Heracles::load_record(Heracles::get_user_id());
		}
		return implode(' ', array($record['name']['initials'], (strlen($record['name']['first']) > 1 ? '('.$record['name']['first'].')' : NULL), $record['name']['lastprefix'], $record['name']['last']));
	}
	function load_record($key=NULL){
		$record = array();
		$db = Heracles::open_db(Heracles::get_dbfile());
		if(!is_array($key)){ $key = array('username' => $key); }
		foreach($db as $i=>$r){
			if(Heracles::array_match($key, $r)){ return Heracles::_lrfix($r); }
		}
		return array();
	}
	function save_record($record=array(), $mode=NULL){
		if($mode == NULL && isset($record['select'])){ $mode = $record['select']; }
		$db = Heracles::open_db(Heracles::get_dbfile());
		
		$record = Heracles::_srfix($record);
		
		if($mode == 'new'){
			$db[] = $record;
		} else {
			foreach($db as $i=>$r){
				if(Heracles::array_match(array('username' => $record['username']), $r)){
					$db[$i] = $record;
				}
			}
		}
		
		file_put_contents(Heracles::get_dbfile(), json_encode($db));
		return TRUE;
	}
	function remove_record($key=NULL){
		$db = Heracles::open_db(Heracles::get_dbfile());		
		if(!is_array($key)){ $key = array('username' => $key); }
		foreach($db as $i=>$r){
			if(Heracles::array_match($key, $r)){
				unset($db[$i]);
				file_put_contents(Heracles::get_dbfile(), json_encode($db));
				return TRUE;
			}
		}
		return FALSE;
	}
	function anonymous(){
		session_start();
		$_SESSION['hash'] = NULL;
	}
	function authenticate($username, $password=FALSE){
		$record = Heracles::load_record($username);
		if($record['pass-hash'] == md5($username.':'.$password) ){
			session_start();
			$start = number_format(microtime(TRUE),1);
			$_SESSION['start'] = $start;
			$_SESSION['user'] = $username;
			$_SESSION['hash'] = md5($username.':'.$start.':'.md5($username.':'.$password));
			return TRUE;
		}
		return FALSE;
	}
	function authenticate_by_session($username, $key, $expires=0, $method=NULL, $created=FALSE){
		$record = Heracles::load_record($username);
		$start = ($created != FALSE ? number_format($created,1) : ($expires != 0 ? number_format($expires - Heracles::get_session_length() , 1) : number_format(microtime(TRUE),1) ) );
		if($key == md5($username.':'.$start.':'.$record['pass-hash']) ){
			session_start();
			$_SESSION['start'] = $start;
			$_SESSION['user'] = $username;
			$_SESSION['hash'] = $key;
			return TRUE;
		}
		return FALSE;		
	}
	function is_authenticated(){
		session_start();
		if( !isset($_SESSION['user']) || !isset($_SESSION['hash']) ){ return FALSE; }
		return ($_SESSION['hash'] == md5($_SESSION['user'].':'.$_SESSION['start'].':'.Heracles::get_passhash($_SESSION['user'])) && TRUE /*$_SESSION['start'] <~ 1 hour (Heracles::get_session_length()) */ );
	}
	function get_passhash($key=NULL, $password=FALSE, $confirm=FALSE){
		if($password !== FALSE && ($password == $confirm) ){
			return md5($key.':'.$password);
		}
		else{
			$record = Heracles::load_record($key);
			return $record['pass-hash'];
		}
	}
	function get_user_id(){
		return $_SESSION['user'];
	}
	function has_role($role=array(), $operator="AND", $user=FALSE){
		if($user == FALSE){ $user = $_SESSION['user']; }
		$record = Heracles::load_record($user);
		return Heracles::array_match($role, $record['role'], FALSE, $operator);
	}
	function in_group($group=array(), $operator="AND", $user=FALSE){
		if($user == FALSE){ $user = $_SESSION['user']; }
		$record = Heracles::load_record($user);
		return Heracles::array_match($group, $record['group'], FALSE, $operator);
	}
	function first_order(){
		$db = Heracles::open_db();
	return array_keys($db[0]);
	}
	function _lrfix($r){
		$r['role'] = implode(",", $r['role']);
		$r['group'] = implode(",", $r['group']);
		$r = Heracles::_lrfix_array($r, 'phone');
		$r = Heracles::_lrfix_array($r, 'email');
		$r = Heracles::_lrfix_array($r, 'twitter');
		$r = Heracles::_lrfix_array($r, 'address');
		$r = Heracles::_lrfix_array($r, 'name');
		return $r;
	}
	function _lrfix_array($r, $item){
		if(isset($r[$item]) && is_array($r[$item])){
			foreach($r[$item] as $i=>$p){
				$r[$item.'['.$i.']'] = $p;
				if(is_array($p)){ $r = Heracles::_lrfix_array($r, $item.'['.$i.']'); }
			}
		}
		return $r;
	}
	function _srfix($r){ 
		unset($r['select']);
		$r['pass-hash'] = Heracles::get_passhash($r['username'], (strlen($r['password']) > 1 ? $r['password'] : FALSE), $r['password-confirm']);
		unset($r['password']);
		unset($r['password-confirm']);
		$r['role'] = explode(",", $r['role']);
		$r['group'] = explode(",", $r['group']);
		$r = Heracles::array_order($r, Heracles::first_order());
		return $r;
	}
	function array_match($needle=array(), $haystack=array(), $with_key=TRUE, $operator="AND"){
		$bool = TRUE;
		if($with_key == TRUE){
			foreach($needle as $n=>$v){
				if($needle[$n] == $haystack[$n]){
					if($operator !== "AND"){ return TRUE; }
					$bool = ($bool ? TRUE : FALSE);
				}
				else { $bool = FALSE; }
			}
		} else {
			foreach($needle as $v){
				if(in_array($v, $haystack)){
					if($operator !== "AND"){ return TRUE; }
					$bool = ($bool ? TRUE : FALSE);
				}
				else { $bool = FALSE; }
			}
		}
		return $bool;
	}
	function array_order($ar=array(), $order=array()){
		ksort($ar);
		$n = array();
		foreach($order as $k){ $n[$k] = $ar[$k]; }
		foreach($ar as $k=>$v){ if(!in_array($k, $order)){ $n[$k] = $ar[$k]; } }
		return $n;
	}
}

if(!function_exists("is_authenticated")){
	function is_authenticated(){
		return Heracles::is_authenticated();
		#return (isset($_GET['debug']) && $_GET['debug'] == "auth" ? TRUE : FALSE);
		#return FALSE;
	}
}
if(!function_exists("authenticate")){
	function authenticate($username, $password=FALSE, $method=TRUE){
		return Heracles::authenticate($username, $password);
	}
}
if(!function_exists("authenticate_by_session")){
	function authenticate_by_session($username, $key, $expires=0, $method=NULL, $created=FALSE){
		return Heracles::authenticate_by_session($username, $key, $expires, $method, $created);
	}
}
?>