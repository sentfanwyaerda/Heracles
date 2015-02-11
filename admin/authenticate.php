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


/*authenticate*/ if(isset($_POST['username'])){ Heracles::authenticate($_POST['username'], $_POST['password']); }
if(Heracles::is_authenticated()){ 
	# header("Location: ./management.php"); exit;
	if(Heracles::has_role('administrator')){
		print '<script>window.location.href="./management.php";</script>'; exit;
	} else {
		print 'Authenticated, but has no administrator-rights.<br/>';
		//*debug*/ print_r(Heracles::load_record(Heracles::get_user_id())); print_r($_SESSION);
	}
}
print Morpheus::basic_parse($skin.'authenticate.html');
?>