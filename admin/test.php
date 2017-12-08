<?php
session_start();
if(isset($_GET['action']) && $_GET['action']=='clear'){ unset($_SESSION); }

require_once(dirname(dirname(__FILE__)).'/Heracles.php');

if(!isset($_GET['user'])){ $_GET['user'] = NULL; }
if(!isset($_GET['pass'])){ $_GET['pass'] = NULL; }

print '<pre>_GET = ';
print_r($_GET);
print md5($_GET['user'].':'.$_GET['pass'])."\n<hr/>\n"; 

print 'SESSION = ';
print_r($_SESSION);

print "\n\n<hr/><h3>Heracles STATICS</h3>\n";
foreach( array('get_dbfile', 'get_session_length', 'open_db', 'list_users', 'is_authenticated', 'get_user_id') as $i=>$s ){
	print "\n \Heracles::".$s."()\t= ".print_r(\Heracles::$s(), TRUE);
}

print '</pre>';
?>