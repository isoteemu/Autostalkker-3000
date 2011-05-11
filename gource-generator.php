#!/usr/bin/env php
<?php

$json = json_decode(file_get_contents('config.json'));

$session = (array) $json->session;
$app = (array) $json->app;

require 'functions.inc.php';

$facebook = new FacebookHack($app);
$facebook->setSession($session);


function gource_title($message) {
	$title = explode("\n", $message);

	list($title) = preg_split('(\.|\?|!|$)', $title[0]);

	$title = str_replace(array('|', '/'), '', $title);

	return trim($title);
}

function gource_event_color($type) {
	$map = array(
		'comment' => '6FFF5C',
		'status' => 'F8FCFF',
		'photo' => 'F1DCFF',
		'like' => '1C00D2',
		'link' => 'D8E6FF',
		'to' => ''
	);

	return (isset($map[$type])) ? $map[$type] : $map['status'];
}


function gource_build_likes($id, $event, $created_time=null) {
	global $facebook;
	$like_data = $facebook->api("$id/likes", array('date_format' => 'U', 'limit' => 999));
	$likes = array();

	$offset_time = ($created_time) ? $created_time : $event['created_time'];

	if(!$created_time && $event['updated_time'] != $event['created_time']) {
		$like_time_range = $event['updated_time'] - $event['created_time'];
	} else {
		$now = (int)gmdate('U');
		$like_time_range = min($now - $offset_time, 3600 * 8);
	}

	// TODO laske easing likeille.
	$like_time_step = $like_time_range / (count($like_data) + 1);

	for($i = 0; $i < count($like_data); $i++) {
		$like_time = $offset_time + $like_time_step + $like_time_step * $i;

		$likes[] = array(
			'time' => $like_time, 
			'name' => $like_data['data'][$i]['name']
		);

	}

	return $likes;

}

function gource_avatar($user) {
	global $facebook;
	$file = "avatars/{$user['name']}.jpg";
	if(!file_exists($file)) {
		$url = FacebookHack::$DOMAIN_MAP['graph'].'/'.$user['id'].'/picture';
		file_put_contents($file, file_get_contents($url));
		if(!@getimagesize($file)) {
			unlink($file);
		}
	}
}


$gource_events = array();

function gource_event($time, $type, $user, $path) {

	global $gource_events;

	$action_map = array(
		'like' => 'A',
		'status' => 'A',
		'comment' => 'A',
		'to' => 'M'
	);

	$time = round($time);

	$time = (defined('TIMEZONE_OFFSET')) ? $time - TIMEZONE_OFFSET : $time;

	if(!isset($gource_events[$time]))
		$gource_events[$time] = array();

	$color = gource_event_color($type);
	$action = (isset($action_map[$type])) ? $action_map[$type] : 'A';

	if (is_array($user)) {
		$name = $user['name'];
		gource_avatar($user);
	} else {
		$name = $user;
	}

	if($type == 'like') {
		$path = dirname($path)."/$name Tykää";
	}

	$line = sprintf('%d|%s|%s|%s|%s',
		$time,
		$name,
		$action,
		$path,
		$color
	);

	$gource_events[$time][] = $line;

}

function gource_process_feed($news, $max_time = 0) {
	global $facebook;

	static $processed = array();

	foreach($news['data'] as $event) {

		if($event['created_time'] <= $max_time) continue;

		if(isset($processed[$event['id']])) continue;
		$processed[$event['id']] = true;

		switch($event['type']) {
			case 'link' :
			case 'video' :
				if(!isset($event['message'])) {
					$event['message'] = $event['name'];
				}
				break;
			case 'photo' :
				if(!isset($event['description']) && isset($event['name'])) {
					$event['message'] = $event['name'];
				} elseif(!isset($event['name']) && isset($event['description'])) {
					$event['message'] = $event['description'];
				} else {
					$event['message'] = $event['name'].': '.$event['description'];
				}
				break;
			case 'status' :
			default :
				break;
		}

		if(empty($event['message'])) {
			file_put_contents("php://stderr", print_r($event,1));
			continue;
		}
		$base_title = gource_title($event['message']);
		$title = $base_title;

		gource_event($event['created_time'], $event['type'], $event['from'], $title.'/'.$title);

		if(isset($event['to'])) {
			foreach($event['to']['data'] as $to) {
				gource_event($event['created_time'], 'to', $to, $title.'/'.$title);
			}
		}

		// Likes are modifications
		if(isset($event['likes'])) {
			if($event['likes']['count'] > 1) {

				foreach(gource_build_likes($event['id'], $event) as $like) {
					gource_event($like['time'], 'like', $like['name'], $title.'/'.$title);
				}

			}
		}

		if(isset($event['comments'])) {
			if($event['comments']['count'] >= 2) {
				$comments_data = $facebook->api("{$event['id']}/comments", array('date_format' => 'U', 'limit' => 999));
				$event['comments']['data'] = $comments_data['data'];
			}

			foreach($event['comments']['data'] as $comment) {

				$comment_title = $title.'/'.$comment['id'].'/'.gource_title($comment['message']);

				gource_event($comment['created_time'], 'comment', $comment['from'], $comment_title);

				if(isset($comment['likes'])) {
					foreach(gource_build_likes($comment['id'], $event, $comment['created_time']) as $like) {
						gource_event($like['time'], 'like', $like['name'], $comment_title);
					}
				}

			}
		}
	}
}

$max_time = gmdate('U') - 604800;

$me = $facebook->api('/me');
define('TIMEZONE_OFFSET', ($me['timezone']) ? $me['timezone'] * 3600 : 7200);

$friends = $facebook->api('/me/friends');
array_unshift($friends['data'], $me);

foreach($friends['data'] as $user) {

	$args = array(
		'limit' => 25,
		'since' => $max_time
	);

	file_put_contents("php://stderr","Processing contact {$user['name']}\n");

	// Handle paging
	while(true) {

		$params = $args + array('date_format' => 'U');

		$news = $facebook->api($user['id'].'/feed', $params);
		if(!count($news['data'])) break;

		gource_process_feed($news, $max_time);

		if(isset($news['paging']['next'])) {
			$query = parse_url($news['paging']['next'], PHP_URL_QUERY);
			parse_str($query, $args);
			if($max_time >= $args['until']) break;
		} else {
			break;
		}

	}
}

ksort($gource_events);
foreach($gource_events as $group) {
	foreach($group as $line) {
		echo "$line\n";
	}
}
