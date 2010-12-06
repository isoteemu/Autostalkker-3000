#!/usr/bin/env php
<?php

$json = json_decode(file_get_contents('config.json'));

$session = (array) $json->session;
$app = (array) $json->app;

define('NEPOMUK_TAGGER', dirname(__FILE__).'/fileTagger.py');
define('FACEBOOK_SCRAPPER', dirname(__FILE__).'/FacebookScrapper.py');

require 'functions.inc.php';

set_include_path(
	dirname(__FILE__).'/ZendGdata-1.11.0/library'
	.PATH_SEPARATOR.get_include_path()
);
#require_once 'Zend/Loader.php';


$facebook = new FacebookHack($app);
$facebook->setSession($session);

$list = array();

// Get self
$me = new FBUser('me');
$list += $friends = $me->friends();

$list += parse_ini_list('lists/stalk.ini');

$list += $friendstalk = parse_ini_list('lists/stalk-friends-of.ini');
foreach($friendstalk as $user) {
	$list += $user->friends();
}

// Loop those whom shall be stalked
foreach($list as $user) {
	echo "Stalking user {$user->name}\n";
	try {
		fb_profile_photos($user);
		if(isset($friends[$user->id]))
			fb_tagged_photos($user);
		else
			fb_scrap_photos($user);
	} catch(Exception $e) {
		echo "\n###ERROR: ";
		echo $e->getMessage();
		echo "\n";
	}
}
