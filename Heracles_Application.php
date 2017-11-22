<?php
namespace Hades;
require_once(dirname(__FILE__).'/Heracles.php');
if(defined('HADES_BASE_DIRECTORY')){ require_once(HADES_BASE_DIRECTORY.'tools/Hades/Hades_Application.php'); }
class Heracles_Application extends \Hades\Application {
	function Heracles_Application($path=NULL){
		/*__construct fix*/ $this->_set_path($path, FALSE); $this->__initiate();
		return (!$this->is_authenticated() ? $this->sign_in() : $this->welcome());
	}
	function welcome(){
		return 'This is a welcome message. It states you are signed '.($this->is_authenticated() ? 'in' : 'out').'.';
	}
	function sign_in(){
		$flags = array(
			'action'=>(!in_array($_GET['action'], array('sign-in','sign_in','login','authenticate')) ? $_GET['action'] : NULL),
			'account'=>NULL,
			'password'=>NULL,
			'filepath'=>$this->path,
			'pagetitle'=>'Authenticate'
		);
		$body = new \Morpheus('sign-in.html', $flags, array('theme/current', 'theme/default'));
		return $body;
		//return '{{sign-out-form|}}';
	}
	function sign_out(){
		\Hades::Message('you have signed out');
		if(class_exists('\Heracles')){ \Heracles::anonymous(); }
		$body = new \Morpheus('sign-out.md', array('filepath'=>$this->path), array('theme/current','theme/default'));
		return $body;
		//return '{{sign-out-form|}}';
	}
	function register(){
		return '{{register-form|}}';
	}
	function is_authenticated(){
		if(class_exists('Heracles')){
			return \Heracles::is_authenticated();
		} else { return FALSE; }
	}
	/***************************************
	 * Path processing functions
	 ***************************************/
	function claim_path($path=NULL){
		if($path === NULL){ $path = self::_get('path', TRUE); }
		return in_array(strtolower($path), array('sign_in','sign_out','register'));
	}
	/***************************************
	 * Form processing functions
	 ***************************************/
	function claim_form($path=NULL, $action=NULL, $get=FALSE, $post=FALSE){
		if($path === NULL){ $path = self::_get('path', TRUE); }
		if($action === NULL){ $action = self::_get('action', NULL, 'view'); }// else { self::_set('action', $action); }
		if($get === FALSE){ $get = $_GET; }
		if($post === FALSE){ $post = $_POST; }
		/* $post['action'] see self::form_processing for accepted forms */
		if(in_array($post['action'], array('sign-in','sign_in','login','authenticate','sign-out','sign_out','logoff')) ){ return TRUE; }
		return FALSE;
	}
	function form_processing($path=NULL, $action=NULL, $get=FALSE, $post=FALSE, $altaction=NULL){
		if($path === NULL){ $path = self::_get('path', TRUE); }
		if($action === NULL){ $action = self::_get('action', NULL, 'view'); }// else { self::_set('action', $action); }
		if($get === FALSE){ $get = $_GET; }
		if($post === FALSE){ $post = $_POST; }
		if($altaction === NULL){ $altaction = ($post['action'] ? $post['action'] : $action); }
		$bool = TRUE;
		switch(strtolower($altaction)){
			case 'sign-in': case 'sign_in': case 'login': case 'authenticate':
				if(class_exists('\Heracles')){ \Heracles::try_to_authenticate(); }
				\Hades::Message('you have signed in');
				break;
			case 'sign-out': case 'sign_out': case 'logoff':
				if(class_exists('\Heracles')){ \Heracles::anonymous(); }
				$bool = TRUE;
				break;
			default:
				/*IGNORE ANY FORM*/ $bool = FALSE;
		}
		return $bool;
	}
}
?>
