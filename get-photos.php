#!/usr/bin/env php
<?php

$json = json_decode(file_get_contents('config.json'));

$session = (array) $json->session;
$app = (array) $json->app;

define('NEPOMUK_TAGGER', dirname(__FILE__).'/fileTagger.py');
define('FACEBOOK_SCRAPPER', dirname(__FILE__).'/FacebookScrapper.py');

require 'functions.inc.php';

$facebook = new FacebookHack($app);
$facebook->setSession($session);

$user = new FBUser($argv[1]);

fb_profile_photos($user);
fb_scrap_photos($user);
