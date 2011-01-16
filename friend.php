<?php

require_once('worldmap-config.php');

if(!$session) {
	die("/* No active session */");
}

ini_set('display_errors', true);

@include_once('FirePHPCore/fb.php');

if(!function_exists('fb')) {
	function fb() {}
}

$me = new FBUser('me');
$data = $me->data();

setlocale(LC_MESSAGES, array(
	$data['locale'].'.utf8',
	$data['locale']
));

define('SCORE_MULTI_FROM', 1);
define('SCORE_MULTI_TO', 0.7);

define('SCORE_EVENT_STATUS', 7);
define('SCORE_EVENT_PHOTO', 10);
define('SCORE_EVENT_LINK', 3);
define('SCORE_EVENT_COMMENT', 4);
define('SCORE_EVENT_LIKED', 1);

define('COMMENT_OPTIMAL_LENGTH', 400);

function score_event($event) {
	$typemap = array(
		'status' => SCORE_EVENT_STATUS,
		'link'	 => SCORE_EVENT_LINK,
		'photo'	 => SCORE_EVENT_PHOTO
	);

	$score = (isset($typemap[$event['type']])) ? $typemap[$event['type']] : SCORE_EVENT_STATUS;
	$score = max(1, $score * score_multiplier($event['message']));
	return $score;
}

function score_multiplier($message) {
	return max(min(
		1 / COMMENT_OPTIMAL_LENGTH * mb_strlen($message),
		1
	), 0.1);
}

/**
 * Check if maximal runtime is closing in.
 */
function are_walls_closing_in() {
	static $exectime = null;
	if($exectime === null) $exectime = ini_get('max_execution_time');

	if($exectime !== null) {
		$rusage = getrusage();
		$runtime = $rusage['ru_utime.tv_sec'] + $rusage['ru_stime.tv_sec'];
		if( $runtime > $exectime * 0.8 ) return true;
	}
	return false;
}

function score_feed($feed, FBUser $me, FBUser $friend) {
	static $seen = array();
	global $facebook;

	$scores = array(
		$me->id => 0,
		$friend->id => 0
	);

	$friends = array($me->id, $friend->id);

	foreach($feed['data'] as $event) {

		if(are_walls_closing_in()) {
			fb("Walls are closing in");
			return $scores;
		}

		if(isset($seen[$event['id']])) continue;
		$seen[$event['id']] = $event['id'];

		//
		// * Search from -> to pairs
		//
		foreach($friends as $sender) {
			$recipient = ($sender == $me->id) ? $friend->id : $me->id;
			if($event['from']['id'] == $sender) {
				if(isset($event['to'])) {
					foreach($event['to']['data'] as $to) {
						if($to['id'] == $recipient) {
							fb($recipient, "Event recipient is");
							$scores[$sender] += score_event($event);
							break 2;
						}
					}
				}
			}
		}

		//
		// * Score by comments
		// 
		// Only interested if from is either one of friends
		if(@$event['comments']['count'] >= 1 && in_array($event['from']['id'], $friends)) {
			if(@$event['comments']['count'] > 2)
				$event = $facebook->api($event['id']);

			foreach($event['comments']['data'] as $comment) {
				$search = ($event['from']['id'] == $me->id) ? $friend->id : $me->id;

				if($comment['from']['id'] == $search) {
					fb($search, "Comment from");
					$score = score_multiplier($comment['message']);
					$scores[$search] += SCORE_EVENT_COMMENT * $score;
				}
			}
		}

		//
		// * Score by likes
		//

		if(!is_array(@$event['likes'])) {
			$event['likes'] = array(
				'data' => array(),
				'count' => (isset($event['likes'])) ? $event['likes'] : 0
			);
		}

		if(@$event['likes']['count'] > 0 && in_array($event['from']['id'], $friends)) {
			if($event['from']['id'] == $me->id) {
				$from = $me->id;
				$search = $friend->id;
			} else {
				$from = $friend->id;
				$search =  $me->id;
			}

			// If extended info is not loaded, load likes -data.
			if(count($event['likes']['data']) < $event['likes']['count']) {
				$limit = min(ceil($event['likes']['count'] / 100) * 100, 1000);
				$likes = $facebook->api("{$event['id']}/likes", array('limit' => $limit));
				$event['likes']['data'] = $likes['data'];
			}
			foreach($event['likes']['data'] as $like) {
				if($like['id'] == $search) {
					fb($search, "Liked by");
					$scores[$search] += SCORE_EVENT_LIKED;
					break;
				}
			}
		}
	}
	return $scores;
}

if(isset($_GET['fid']) && is_numeric($_GET['fid'])) {
	header("Content-Type: application/json; charset=utf-8");

	$friend = new FBUser($_GET['fid']);
	if(fb_user_geocode($friend)) {
		$scores = array();
		$feed = $facebook->api("/{$friend->id}/feed", array('limit' => 30));
		$scores += score_feed($feed, $friend, $me);

		$my_feed = $facebook->api("/{$me->id}/feed", array('limit' => 30));
		$scores += score_feed($my_feed, $me, $friend);

		$total = array_sum($scores);

		$friend->score = $total + 1;
		$friend->score_weight = 0.5;
		if($total > 0)
			$friend->score_weight = 1/$total * $scores[$me->id];

	}

	echo json_encode($friend);
}