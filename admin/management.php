<?php 
if(!defined('HADES_ROOT')){
	define('HADES_ROOT', dirname(__FILE__) );
	require_once(dirname(dirname(HADES_ROOT)).'/Morpheus/Morpheus.php');
	require_once(dirname(HADES_ROOT).'/Heracles.php');
}
else{ //#within an HADES environment:
	require_once(HADES_ROOT.'/tools/loader.php');
	require_once(HADES_ROOT.'/settings.php');
}

$skin = (HADES_ROOT == dirname(__FILE__) ? HADES_ROOT.'/' : HADES_ROOT.'/tools/Heracles/admin/');
$heraclesdb = (HADES_ROOT == dirname(__FILE__) ? dirname(HADES_ROOT).'/users.json' : HADES_ROOT.'/.heracles-db/users.json');

$flags['t.domain'] = '@'.(defined('HADES_DOMAIN') ? HADES_DOMAIN : 'domain.ltd');

function lb_select_options($options=array(), $value=NULL){
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

if( Heracles::is_authenticated() && Heracles::has_role('administrator') ){
	if(is_array($_POST) && TRUE){
		Heracles::save_record($_POST);
	}
	/*fix*/ if(!isset($flags) || !is_array($flags)){ $flags = array(); }
	
	#/*remove user*/
	if(isset($_GET['delete'])){ Heracles::remove_record($_GET['delete']); $_GET['for'] = 'new'; }
	
	#/*fill $_POST*/ $flags = array_merge($flags, $_POST);
	if(isset($_GET['for']) && $_GET['for'] != 'new'){ $flags = array_merge($flags, Heracles::load_record($_GET['for'])); }
	
	if(isset($_GET['for'])){
		$flags['username'] = $_GET['for'];
		if($_GET['for'] == 'new'){ $flags['username'] = NULL; $flags['username.edit'] = TRUE; }
	} else { $flags['username.edit'] = TRUE; }
	$flags = array_merge($flags, array(
			'selector' => Morpheus::basic_parse_str(lb_select_options(array_merge(array('new'=>'{t.adduser|Add User}'), Heracles::list_users(TRUE)), $flags['username']) , $flags),
			'sex-select' => Morpheus::basic_parse_str(lb_select_options(array('m'=>'{t.male|male}','f'=>'{t.female|female}','x'=>'{t.other|other}'), $flags['sex']) , $flags),
		));
	print Morpheus::basic_parse($skin.'edit-user.html', $flags);
}
else{ header("Location: ?authenticate"); exit; }
?>