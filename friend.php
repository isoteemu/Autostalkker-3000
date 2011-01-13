<?php

require_once('worldmap-config.php');

if(!$session) {
	die("/* No active session */");
}

$me = new FBUser('me');
$data = $me->data();

setlocale(LC_MESSAGES, array(
	$data['locale'].'.utf8',
	$data['locale']
));

if($_GET['fid']) {
	header("Content-Type: application/json");

	$friend = new FBUser($_GET['fid']);
	fb_user_geocode($friend);
	echo json_encode($friend);

}