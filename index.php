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
		To use this app:
		<fb:login-button show-faces perms="user_hometown,friends_hometown,user_location,friends_location"></fb:login-button>

		<div style="float:right;">
			<fb:like-box href="http://www.facebook.com/apps/application.php?id=173982279304513" width="292" show_faces="true" stream="false" header="false"></fb:like-box>
		</div>
	<?php else: ?>
		<div>
			<canvas style="background: no-repeat url(http://static.ak.fbcdn.net/rsrc.php/z9/r/jKEcVPZFk-2.gif) center center;" id="foam" width="720" height="320">
				<img id="earth" src="maps/2.83.jpg" width="720" height="320" />
			</canvas>
		</div>
		<div>
			<meter style="width:720px; display:block; height:1em;" id="progressbar" class="ui-progressbar ui-widget ui-widget-content ui-corner-all" min="0" max="100" value="0"></meter>
<!--			<fb:like href="http://www.facebook.com/apps/application.php?id=173982279304513" show_faces="false" width="450" action="recommend"></fb:like> -->
		</div>
	<?php endif; ?>
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
</body>
<?php flush(); ?>
<script src="worldmap.js"></script>
<script>
	<?php
		include_once 'worldmap.php';
		$worldmap = new worldmap($me);
		echo $worldmap;
	?>
</script>

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