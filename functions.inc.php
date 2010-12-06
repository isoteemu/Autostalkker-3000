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

	// NOT a good place to be...
	const PATH_PREFIX = '/home/teemu/Documents/Pictures/FB';

	/**
	 * Usernames to ID's cache
	 */
	protected static $usenameCache = array();

	protected static $attributeCache = array();

	public function __construct($id=null, $name=null) {
		$username = null;
		if($id !== null && !is_numeric($id)) {
			// Try mapping username to ID
			$username = strtolower(trim($id));
			if(isset(self::$usenameCache[$username])) {
				$id = self::$usenameCache[$username];
			}
		}

		if($id !== null) {

			if(is_numeric($id) && $name !== null) {
				$this->id = $id;
				$this->name = trim($name);
			} else {
				$data = $this->getAttibutes($id);
				$this->id = $data['id'];
				$this->name = $data['name'];
			}

			if($this->id) {
				if($username !== null)
					self::$usenameCache[$username] = $this->id;
				$this->cacheAttributes($this);
			}
		}
	}

	protected function getAttibutes($id) {
		if(!isset(self::$attributeCache[$id])) {
			global $facebook;
			self::$attributeCache[$id] = $facebook->api('/'.$id);
		}
		return self::$attributeCache[$id];
	}

	protected function cacheAttributes(FBUser $user) {
		$id = $user->id;
		if(!isset(self::$attributeCache[$id]))
			self::$attributeCache[$id] = array();

		self::$attributeCache[$id] += array(
			'id' => $user->id,
			'name' => $user->name
		);
	}

	public function path() {
		$path = sprintf('%s/%s', FBUser::PATH_PREFIX, $this->id);
		if(!file_exists($path)) mkdir($path);
		return $path;
	}

	public function friends() {
		global $facebook;
		$session = $facebook->getSession();

		$list = array();

		if( $this->id == $session['uid'] ) {
			$friends = $facebook->api('/'.$this->id.'/friends');
			foreach($friends['data'] as $friend) {
				$user = new FBUser($friend['id'], $friends['name']);
				$list[$user->id] = $user;
			}
		} else {
			$friends = FacebookScrapper::friends($this);
			foreach($friends as $fbid => $name) {
				$user = new FBUser($fbid, $name);
				$list[$user->id] = $user;
			}
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
		$user = new FBUser($id, $name);
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
	$urls = array();

	// Only jpegs
	if(!preg_match('/.jpg$/',$url)) return false;
	if(preg_match('/\/[0-9_]+(_\w).jpg$/',$url)) {
		foreach($sizes as $suffix) {
			$urls[] = preg_replace('/(_\w).jpg$/', '_'.$suffix.'.jpg', $url);
		}
	} elseif(preg_match('/\/(\w)[0-9_]+.jpg$/',$url)) {
		foreach($sizes as $suffix) {
			$urls[] = preg_replace('/\/(\w)([0-9_]+).jpg$/', '/'.$suffix.'\2.jpg', $url);
		}
	}

	$urls[] = $url;

	$path = $user->path();

	foreach($urls as $_url) {

		$file = basename($name)." - ".basename($_url);
		$dest = $path.'/'.$file;

		if(file_exists($dest)) return $dest;

		// Using kde-cp to get metadata into nepomuk
		$cmd = sprintf('kde-cp --noninteractive %s %s', escapeshellarg($_url), escapeshellarg($dest));
		echo " * Copying $_url image to $file\n";
		exec($cmd);

		if(file_exists($dest)) {
			NepomukUtil::tagFBUser($user, $dest);
			if(file_exists($dest)) return $dest;
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
	echo " * Scrapping {$user->id}\n";
	$photos = FacebookScrapper::photos($user);
	foreach($photos as $pid => $thumb) {
		fb_get_photo($user, $pid, $thumb);
	}
}

function fb_get_photo($user, $pid, $url=null) {
	global $facebook;
	echo " * Scrapped photo $pid\n";
	$data = $facebook->api("/$pid");
	if($data) {
		$photo = fb_picture($user, $data['source']);
		if(!$photo) return false;

		$faces = new FaceDetect($photo);

		foreach($data['tags']['data'] as $person) {
			if(!$person['id']) continue;
			$tagged = new FBUser($person['id'], $person['name']);
			echo " * > Found tagged user {$tagged->name}\n";

			if($faces->fbSearch($person)) {
				echo " * > Found face for {$tag['name']}\n";
				NepomukUtil::tagFBUser($tagged, $photo);
			}
		}
	} elseif($url != null) {
		fb_picture($user, $url);
	}
}

class FacebookScrapper {
	public static $scrapper = FACEBOOK_SCRAPPER;

	public static function photos(FBUser $user) {
		$scrapper = self::scrapper();
		if(! $scrapper) return array();

		$cmd = sprintf('%s --photos %s',
			FACEBOOK_SCRAPPER,
			escapeshellarg($user->id)
		);
		$json = exec($cmd);
		$photos = json_decode($json);

		return (array) $photos;
	}

	public static function friends(FBUser $user) {
		$scrapper = self::scrapper();
		if(!$scrapper) return array();

		$cmd = sprintf('%s --friends %s',
			FACEBOOK_SCRAPPER,
			escapeshellarg($user->id)
		);
		$json = exec($cmd);
		$friends = json_decode($json);
		return (array) $friends;
	}

	public static function scrapper() {
		if(!is_executable(self::$scrapper)) return false;
		return self::$scrapper;
	}


}

class NepomukUtil {

	/**
	* Tag image with user
	*/
	public static function tagFBUser(FBUser $user, $picture) {
		if(file_exists(NEPOMUK_TAGGER) && is_executable(NEPOMUK_TAGGER)) {
			if($picture[0] != '/')
				$picture = $user->path().'/'.$picture;

			$tagCmd = sprintf('%s --tag=%s %s',
				NEPOMUK_TAGGER,
				escapeshellarg($user->name),
				escapeshellarg($picture)
			);
			exec($tagCmd);
			return true;
		} else {
			throw new RuntimeException('Nepomuktagger script was not found');
		}
		return false;
	}
}

class FaceDetect {

	const DETECTOR = './detect/detect';

	public $photo;
	public $faces = array();

	public function __construct($photo) {
		$this->photo = realpath($photo);
		if(file_exists($photo))
			$this->faces = $this->detect();
	}

	public function detect() {
		exec(sprintf('%s %s',
			self::DETECTOR,
			escapeshellarg($this->photo)
		), $lines);

		$ret = array();

		foreach($lines as $line) {
			if(preg_match('/^(\d+),(\d+) (\d+)x(\d+)/', $line, $matches)) {
				$ret[] = array(
					'x' => $matches[1],
					'y' => $matches[2],
					'w' => $matches[3],
					'h' => $matches[4]
				);
			}
		}
		return $ret;
	}

	/**
	 * Facebook returns faces positions as relative (%),
	 * so convert to pixels
	 */
	public function fbSearch(array $tag) {
		$img = getimagesize($this->photo);
		$x = $img[0] / 100 * $tag['x'];
		$y = $img[1] / 100 * $tag['y'];

		return $this->search($x, $y);
	}

	/// TODO: If multiple found, select nearest
	public function search($x, $y) {
		foreach($this->faces as $face) {
			if($x >= $face['x'] && $x <= $face['x']+$face['w'] &&
				$y >= $face['y'] && $y <= $face['y']+$face['h']) {
				return true;
			}
		}
		return false;
	}

}
