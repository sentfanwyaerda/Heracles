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
$heraclesdb = (HADES_ROOT == dirname(__FILE__) ? dirname(HADES_ROOT).'/users.json' : HADES_ROOT.'/.heracles-db/users.json');

if(Heracles::is_authenticated()){ 
	Heracles::anonymous();
}
print '<script>window.location.href="./authenticate.php";</script>'; exit;
?>