<?php

require_once('worldmap-config.php');

ini_set('zlib.output_compression', 0);

$login = null;
// Session based API call.
if ($session) {
  try {
    $uid = $facebook->getUser();
    $login = $facebook->api('/me');
  } catch (FacebookApiException $e) {
    error_log($e);
  }
}
if($login) {

	$me = new FBUser('/me');

	$data = $me->data();
	setlocale(LC_MESSAGES, array(
		$data['locale'].'.utf8',
		$data['locale']
	));

}

?>
<!doctype html>
<html xmlns:fb="http://www.facebook.com/2008/fbml">
	<title>World of Friendly Love</title>
	<meta property="og:image" content="wofl.png" />
	<meta property="fb:app_id" content="173982279304513" />

	<script src="http://ajax.googleapis.com/ajax/libs/jquery/1.4/jquery.min.js"></script>
	<script src="http://ajax.googleapis.com/ajax/libs/jqueryui/1.8/jquery-ui.min.js"></script>

	<style>
		@import url("http://ajax.googleapis.com/ajax/libs/jqueryui/1.8/themes/redmond/jquery-ui.css");
	</style>
</head>
<?php
	ob_end_flush();
	flush();
?>
<body>
	<?php if(!$login): ?>
		<div id="fb-root"></div>
		<script>
		window.fbAsyncInit = function() {
			FB.init({
				appId : '<?php echo $facebook->getAppId(); ?>',
				session : <?php echo json_encode($session); ?>, // don't refetch the session when PHP already has it
				status : true, // check login status
				cookie : true, // enable cookies to allow the server to access the session
				xfbml : true // parse XFBML
			});

			FB.Event.subscribe('auth.login', function() {
				window.location.reload();
			});
		};

		(function() {
			var e = document.createElement('script');
			e.src = document.location.protocol + '//connect.facebook.net/en_US/all.js';
			e.async = true;
			document.getElementById('fb-root').appendChild(e);
		}());
		</script>

		To use this app:
		<fb:login-button show-faces perms="user_hometown,friends_hometown,user_location,friends_location"></fb:login-button>

		<div style="float:right;">
			<fb:like-box href="http://www.facebook.com/apps/application.php?id=173982279304513" width="292" show_faces="true" stream="false" header="false"></fb:like-box>
		</div>
	<?php else: ?>
		<div>
			<canvas style="background: no-repeat url(http://static.ak.fbcdn.net/rsrc.php/z9/r/jKEcVPZFk-2.gif) center center;" id="foam" width="720" height="320">
				<img id="earth" src="maps/2.84.jpg" width="720" height="320" />
			</canvas>
		</div>
		<div>
			<meter style="width:720px; display:block; height:1em;" id="progressbar" class="ui-progressbar ui-widget ui-widget-content ui-corner-all" min="0" max="100" value="0"></meter>
<!--			<fb:like href="http://www.facebook.com/apps/application.php?id=173982279304513" show_faces="false" width="450" action="recommend"></fb:like> -->
		</div>
	<?php endif; ?>
</body>
<?php flush(); ?>
<script>

worldmap = {
	height: 320,
	width: 720,
	line_thickness: 1,
	scale: 1,

	// Viewport target coordinates.
	viewport: {
		n: -90,
		e: -180,
		s: 90,
		w: 180
	},

	faces: {}

};

worldmap.lat2px = function(lat) {
	var top =  (lat - 90) * -1;
	var multi = top / 180;
	return this.height * multi;
}

worldmap.lng2px = function(lng) {
	var left = 180 + lng;
	var multi = left / 360;
	return this.width * multi;
}

worldmap.drawLine = function(lat1, lng1, lat2, lng2, thickness) {
	var ctx = this.canvas.getContext('2d');

	this.updateViewportCoordinates(lat1, lng1);
	this.updateViewportCoordinates(lat2, lng2);

	var color_step = 255 / this.line_thickness
	var color = color_step * (thickness-1);
	color += Math.round(Math.random()*color_step);

	var x1 = worldmap.lng2px(lng1);
	var x2 = worldmap.lng2px(lng2);
	var y1 = worldmap.lat2px(lat1);
	var y2 = worldmap.lat2px(lat2);

	min_y = Math.min(y1, y2);
	max_y = Math.max(y1, y2);

	min_x = Math.min(x1, x2);
	max_x = Math.max(x1, x2);

	cpy = min_y - (max_y - min_y) / 2;
	cpx = max_x - (max_x - min_x) / 2;
	ctx.beginPath();
	ctx.strokeStyle = "rgb("+color+","+color+","+(127+Math.max(color-128, 0))+")";
	ctx.strokeStyle = "rgba("+color+","+color+","+(127+Math.max(color-128, 0))+",0.7)";
	ctx.lineWidth = 2 / this.scale;
	ctx.lineCap = "round";
	ctx.moveTo(x1, y1);
	ctx.quadraticCurveTo(cpx, cpy, x2, y2);

	ctx.stroke();

}

worldmap.plotPicture = function(id, lat, lng) {
	var picture = new Image();
	picture.onload = function() {
		var width = this.width / 3
		var height = this.height / 3

		// Grow faces as more closer we get
		width = width * Math.pow(1.02, worldmap.scale) / worldmap.scale;
		height = height * Math.pow(1.02, worldmap.scale) / worldmap.scale;

		var left = worldmap.lng2px(lng)-width/2;
		var top  = worldmap.lat2px(lat)-height/2;

		worldmap.canvas.getContext('2d').drawImage(this, left, top, width, height);
	}

	picture.src = 'http://graph.facebook.com/'+id+'/picture';
}

worldmap.updateViewportCoordinates = function(lat, lng) {
	this.viewport.n = Math.max(this.viewport.n, lat);
	this.viewport.e = Math.max(this.viewport.e, lng);
	this.viewport.s = Math.min(this.viewport.s, lat);
	this.viewport.w = Math.min(this.viewport.w, lng);
}

worldmap.updateViewport = function() {
	var n = this.lat2px(this.viewport.n);
	var e = this.lng2px(this.viewport.e);
	var s = this.lat2px(this.viewport.s);
	var w = this.lng2px(this.viewport.w);

	var lat_scale = (s - n) / 5;
	var lng_scale = (e - w) / 5;

	var top = Math.max(0, n-lat_scale);
	var bot = Math.min(this.height, s+lat_scale);
	var lef = Math.max(0, w-lng_scale);
	var rig = Math.min(this.width, e+lng_scale);

	this.scale = Math.min(this.height / (bot - top), this.width / (rig - lef));

	var ctx = this.canvas.getContext('2d');

	ctx.scale(this.scale, this.scale);
	ctx.translate(0-lef, 0-top);

}

worldmap.getBackround = function() {
	var map = 1;
	for (scale in this.map_scales) {
		if(scale <= this.scale)
			map = Math.max(map, scale);
	}

	var img = new Image();
	img.onload = function() {
		worldmap.canvas.getContext('2d').drawImage(this, 0, 0, worldmap.width, worldmap.height);
	}

	img.src = this.map_scales[map];

}

worldmap.draw = function() {
	steps = [
		'viewport',
		'background',
		'lines',
		'faces'
	];
	this.drawSteps(steps);
}

worldmap.drawSteps = function(steps) {
	if(steps.length == 0)
		return;

	step = steps.shift();
	switch(step) {
		case "viewport":
			this.updateViewport();
			return this.drawSteps(steps);
		case "background":
			var background = new Image();
			background.onload = function() {
				worldmap.drawSteps(steps);
			}
			background.src = this.getBackround();
			return;
		case "faces":
			for(id in this.faces) {
				this.plotPicture(id, this.faces[id].lat, this.faces[id].lng);
			}
			return this.drawSteps(steps);
		default:
			console.log("Unknown drawing step:", step);
			return this.drawSteps(steps);
	}

}

</script>
<?php if($login):
	include_once 'worldmap_js.php';
	
	// <script src="worldmap_js.php"></script>
endif;?>
<script type="text/javascript">
  var _gaq = _gaq || [];
  _gaq.push(['_setAccount', 'UA-19455747-1']);
  _gaq.push(['_trackPageview']);

  (function() {
    var ga = document.createElement('script'); ga.type = 'text/javascript'; ga.async = true;
    ga.src = ('https:' == document.location.protocol ? 'https://ssl' : 'http://www') + '.google-analytics.com/ga.js';
    var s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(ga, s);
  })();
</script>
</html>