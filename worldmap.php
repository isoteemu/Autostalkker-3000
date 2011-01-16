<?php
class worldmap {

	const EOL = "\n";

	public $viewport = array(
		'n' => -90,
		'e' => -180,
		's' => 90,
		'w' => 180
	);

	public $map_scales = array(
		'1' => 'maps/1.jpg'
	);

	public $friends = array();

	public $max_lines = 1;

	protected $user;

	/**
	 * Group lines which spans # of degrees
	 */
	public $grid = 3;

	protected $errors = array();

	public function __construct(FBUser &$user) {
		$this->user =& $user;
		foreach($user->friends() as $friend) {
			$this->addFriend($friend);
		}

		$this->map_scales = $this->getMapsScales();
	}

	public function addFriend(FBUser &$user) {
		$this->friends[$user->id] =& $user;
	}

	public function drawLineTo(FBUser $to) {
		$to_x = $this->align2grid($to->location->lat);
		$to_y = $this->align2grid($to->location->lng);

		$id = "$to_x:$to_y";

		if(!isset($this->lines[$id])) {
			$this->lines[$id] = array(
				'lat' => array(),
				'lng' => array(),
				'count' => 0
			);
		}

		$this->updateViewportCoordinates($to->location->lat, $to->location->lng);

		$this->lines[$id]['lat'][] = $to->location->lat;
		$this->lines[$id]['lng'][] = $to->location->lng;
		$this->lines[$id]['count']++;
		$this->max_lines = max($this->lines[$id]['count'], $this->max_lines);
	}

	public function updateViewportCoordinates($lat, $lng) {
		$this->viewport['n'] = max($this->viewport['n'], $lat);
		$this->viewport['e'] = max($this->viewport['e'], $lng);
		$this->viewport['s'] = min($this->viewport['s'], $lat);
		$this->viewport['w'] = min($this->viewport['w'], $lng);
	}

	public function error($message) {
		$this->errors[] = $message;
	}

	public function errorDialog() {
		if(count($this->errors)) {

			$title  = json_encode(_("Error"));
			$close  = json_encode(_("Close"));
			$errors = '<ul class="ui-widget">';
			foreach($this->errors as $err) {
				$errors .= sprintf('<li class="ui-state-error">%s</li>', $err);
			}
			$errors .= '</ul>';
			$errors = json_encode($errors);

			$dialog = <<<EOL
				$($errors).dialog({
					modal: true,
					title: $title,
					buttons: {
						$close: function() {
							$( this ).dialog( "close" );
						}
					}
				});
EOL;

			return $dialog;
		}
	}

	public static function progressbar($value) {
		$o = sprintf('$("#progressbar").attr({value: %1$d}).progressbar({value:%1$d});', $value);
		$o .= str_repeat(" ", 256);
		if(round($value) == 100) {
			//$o .= '$("#progressbar").hide("normal");';
		}
		return $o;
	}

	/**
	 * Fit item into grid
	 */
	protected function align2grid($gis) {
		return round($gis / $this->grid) * $this->grid; 
	}

	protected function getMapsScales() {
		$maps = glob('maps/*.jpg');
		$scales = array();
		foreach($maps as $map) {
			if(preg_match('%maps/([0-9]+|[0-9]+\.[0-9]+)\.jpg%', $map, $match)) {
				$scales[$match[1]] = $map;
			}
		}
		return $scales;
	}

	public function __toString() {
		if(!fb_user_geocode($this->user)) {
			$this->error(
				_("You are missing your location information. Please fill your location or hometown.")
			);
		}

		$js   = array();

		if(count($this->errors)) {
			$js[] = $this->errorDialog();
			return implode(self::EOL, $js);
		}

		$properties = array_keys(get_object_vars($this));
		foreach($properties as $property) {
			$js[] = sprintf('worldmap[%s] = %s;',
				json_encode($property),
				json_encode($this->{$property})
			);
		}

		$js[] = 'worldmap.canvas.getContext("2d").save();';
		$js[] = 'worldmap.draw();';

		return implode(self::EOL, $js);
	}
}
