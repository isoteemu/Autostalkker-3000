<?php

require 'php-sdk/src/facebook.php';

/// TODO: Implement cached api()
class FacebookHack extends Facebook {
	public function getUrl($name, $path='', $params=array()) {
		return parent::getUrl($name, $path, $params);
	}

	public function getPictureUrl($id) {
		//return 'http://graph.facebook.com/isoteemu/picture?type=large';

		$url = $this->getUrl('graph', '/'.$id.'/picture?type=large');

        $curl = curl_init();

		$opts = self::$CURL_OPTS + array(
			CURLOPT_URL => $url,
			CURLOPT_NOBODY => true,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_FOLLOWLOCATION => true
		); 
		$opts[CURLOPT_URL] = $url;

		// disable the 'Expect: 100-continue' behaviour. This causes CURL to wait
		// for 2 seconds if the server does not support this header.
		if (isset($opts[CURLOPT_HTTPHEADER])) {
			$existing_headers = $opts[CURLOPT_HTTPHEADER];
			$existing_headers[] = 'Expect:';
			$opts[CURLOPT_HTTPHEADER] = $existing_headers;
		} else {
			$opts[CURLOPT_HTTPHEADER] = array('Expect:');
		}

		curl_setopt_array($curl, $opts);

        $header = curl_exec($curl);
        $info = curl_getinfo($curl);

		return $info['url'];

	}
}

class FBUser {
	public $id;
	public $name;

	const PATH_PREFIX = '/home/teemu/Documents/Pictures/FB';

	public function __construct($id=null) {
		if($id) {
			global $facebook;
			$data = $facebook->api('/'.$id);
			$this->id = $data['id'];
			$this->name = $data['name'];
		}
	}

	public function path() {
		$path = sprintf('%s/%s', FBUser::PATH_PREFIX, $this->id);
		if(!file_exists($path)) mkdir($path);
		return $path;
	}

	public function friends() {
		global $facebook;
		$friends = $facebook->api('/'.$this->id.'/friends');
		$list = array();
		foreach($friends['data'] as $friend) {
			$user = new FBUser();
			$user->id	= $friend['id'];
			$user->name	= $friend['name'];
			$list[$user->id] = $user;
		}
		return $list;
	}
}

function parse_html_list($file) {

	global $facebook;

	//$html = file_get_contents('jutan-kaverit.txt');
	$html = file_get_contents($file);
	preg_match_all('#<li[^>]*>.*<img [^>]*src="([^"]+)"[^>]*>.*<a[^>]* href="([^"]+)">([^<]+)</a>.*</li>#Usm', $html, $list, PREG_SET_ORDER);

	$ids = array();

	foreach($list as $user) {
		$data = new FBUser();
		if(preg_match('#/profile.php\?id=(\d+)$#', $user[2], $id)) {
			$data->id = $id[1];
		} else {
			preg_match('#.*/(.*)$#', $user[2], $id);
			try {
				echo "> Detecting ID for user {$id[1]} ... ";

				$fb = $facebook->api('/'.$id[1]);
				$data->id = $fb['id'];
				echo "{$fb['id']}\n";

			} catch(FacebookApiException $e) {
				echo "### FAIL: ".$e->getMessage()."\n";
				continue;
			}
		}

		$data->name = trim($user[3]);

		if($data->id && is_numeric($data->id))
			$ids[$data->id] = $data;
	}
	return $ids;
}

function parse_ini_list($file) {
	$users = array();
	$lines = parse_ini_file($file);
	foreach($lines as $id => $name) {
		if(empty($name)) {
			$user = new FBUser($id);
		} else {
			$user = new FBUser();
			$user->id = $id;
			$user->name = $name;
		}
		$users[$user->id] = $user;
	}
	return $users;
}


function fb_picture(FBUser $user, $url=null) {
	if($url == null) {
		global $facebook;
		$url = $facebook->getPictureUrl($user->id);
	}

	$sizes = array('n', 'o'); // 's', 'a'

	$name = trim($user->name);

	// Only jpegs
	if(!preg_match('/.jpg$/',$url)) return false;
	if(preg_match('/(_\w).jpg$/',$url)) {
		$sizes = array('n', 'o'); // 's', 'a'
	} else {
		$sizes = array('');
	}

	$path = $user->path();

	foreach($sizes as $suffix) {
		if(!empty($suffix))
			$url = preg_replace('/(_\w).jpg$/', '_'.$suffix.'.jpg', $url);

		$file = basename($name)." - ".basename($url);
		if(file_exists($path.'/'.$file)) return $file;

		$dest = $path.'/'.$file;

		// Using kde-cp to get metadata into nepomuk
		$cmd = sprintf('kde-cp --noninteractive %s %s', escapeshellarg($url), escapeshellarg($dest));
		echo " * Copying image to $file\n";
		echo exec($cmd);

		if(file_exists($dest)) {
			if(file_exists(NEPOMUK_TAGGER) && is_executable(NEPOMUK_TAGGER)) {
				$tagCmd = sprintf('%s --tag=%s --tag="Facebook Photo" --description=%s %s',
					NEPOMUK_TAGGER,
					escapeshellarg($name),
					escapeshellarg('Source: '.$url),
					escapeshellarg($dest)
				);
				echo exec($tagCmd);
			}
			return $file;
		}
	}
}

function fb_profile_photos(FBUser $user) {
	global $facebook;

	$albums = $facebook->api($user->id.'/albums');
	$profile = false;

	foreach($albums['data'] as $album) {
		if($album['type'] == 'profile') {
			$profile = $album['id'];
			break;
		}
	}

	if($profile) {
		$photos = $facebook->api($profile.'/photos');
		foreach($photos['data'] as $picture) {
			echo " * Fetching user {$user->name} tagged photo\n";
			fb_picture($user, $picture['source']);
		}
	} else {
		return fb_picture($user);
	}
}

function fb_tagged_photos(FBUser $user) {
	global $facebook;
	$photos = $facebook->api('/'.$user->id.'/photos');
	foreach($photos['data'] as $picture) {
		echo " * Found user {$user->name} tagged photo {$picture['id']}\n";
		fb_get_photo($user, $picture['id'], $picture['source']);
	}
}

function fb_scrap_photos(FBUser $user) {
	if(!is_executable(FACEBOOK_SCRAPPER)) return false;
	echo " * Scrapping {$user->id}\n";
	$cmd = sprintf('%s %s',
		FACEBOOK_SCRAPPER,
		escapeshellarg($user->id)
	);
	$json = exec($cmd);
	$photos = json_decode($json);

	foreach($photos as $pid => $thumb) {
		fb_get_photo($user, $pid, $thumb);
	}
}

function fb_get_photo($user, $pid, $url=null) {
	global $facebook;
	echo " * Scrapped photo $pid\n";
	$data = $facebook->api("/$pid");
	if($data) {
		foreach($data['tags']['data'] as $person) {
			$tagged = new FBUser();
			$tagged->id = $person['id'];
			$tagged->name = trim($person['name']);
			echo " * > Found tagged user {$tagged->name}\n";
			fb_picture($tagged, $data['source']);
		}
	} elseif($url != null) {
		fb_picture($user, $url);
	}
}
