<?php

require_once('worldmap-config.php');

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

$fblocale = 'en_US';

if($login) {

	$me = new FBUser('/me');

	$data = $me->data();
	setlocale(LC_MESSAGES, array(
		$data['locale'].'.utf8',
		$data['locale']
	));

	$fblocale = $data['locale'];

}

header('Content-Type: text/html; charset=utf-8');

?>
<!doctype html>
<html xmlns:fb="http://www.facebook.com/2008/fbml">
	<title>World of Friendly Love</title>
	<meta http-equiv="Content-Type" content="text/html;charset=utf-8" />
	<meta property="og:image" content="http://fundi.es/wofl/wofl.png" />
	<meta property="fb:app_id" content="<?php echo $facebook->getAppId(); ?>" />

	<script src="http://ajax.googleapis.com/ajax/libs/jquery/1.4/jquery.min.js"></script>
	<script src="http://ajax.googleapis.com/ajax/libs/jqueryui/1.8/jquery-ui.min.js"></script>

	<style>
		@import url("http://ajax.googleapis.com/ajax/libs/jqueryui/1.8/themes/redmond/jquery-ui.css");
/* Facebook styles */

.uiProfilePhotoMedium {
    height: 32px;
    width: 32px;
}
img {
    border: 0 none;
}

.hidden_add_comment .uiUfiAddComment, .uiUfiAddCommentCollapsed .actorPic, .uiUfiAddComment .commentBtn {
    display: none;
}
.child_is_focused .hidden_add_comment .uiUfiAddComment, .uiUfiAddCommentCollapsed .actorPic, .child_is_focused .uiUfiAddComment .commentBtn {
    display: block;
}

.uiUfiAddComment .actorPic {
    float: left;
    margin-right: 6px;
}
.uiUfiAddComment .commentArea {
    padding: 0 !important;
}
.uiUfiAddComment .commentBox {
    padding: 0 8px 0 0;
}
.uiUfiAddComment .commentBtn {
    float: right;
}
.uiUfiAddComment .textBox {
    display: block;
    margin: 0;
    width: 100%;
}
.child_is_focused .uiUfiAddCommentCollapsed .textBox, .uiUfiAddComment .textBox {
    height: 29px;
}
.uiUfiAddCommentCollapsed .textBox {
    height: 14px;
}
.hidden_add_comment .uiUfiAddComment, .uiUfiAddCommentCollapsed .actorPic, .uiUfiAddComment .commentBtn {
    display: none;
}
.child_is_focused .uiUfiAddCommentCollapsed .actorPic, .child_is_focused .uiUfiAddComment .commentBtn {
    display: block;
}

textarea, .inputtext, .inputpassword {
    border: 1px solid #BDC7D8;
    font-family: "lucida grande",tahoma,verdana,arial,sans-serif;
    font-size: 11px;
    padding: 3px;
	color:#777777;
}

/** Own addition **/
.child_is_focused textarea, textarea:focus {
	color:#333333;
}

.ufiItem {
    background-color: #EDEFF4;
    border-bottom: 1px solid #E5EAF1;
    margin-top: 2px;
    padding: 5px 5px 4px;
}

.uiUfiAddComment .commentArea {
    padding: 0 !important;
}
.UIImageBlock_ICON_Content {
    padding-top: 1px;
}
.UIImageBlock_Content {
    display: table-cell;
    vertical-align: top;
    width: 10000px;
}

/* Button */
.uiButton, .uiButtonSuppressed:active, .uiButtonSuppressed:focus, .uiButtonSuppressed:hover {
    -moz-box-shadow: 0 1px 0 rgba(0, 0, 0, 0.1);
    background: url("https://s-static.ak.facebook.com/rsrc.php/zD/r/B4K_BWwP7P5.png") repeat scroll 0 0 #EEEEEE;
    border-color: #999999 #999999 #888888;
    border-style: solid;
    border-width: 1px;
    cursor: pointer;
    display: inline-block;
    font-size: 11px;
    font-weight: bold;
    line-height: normal !important;
    padding: 2px 6px;
    text-align: center;
    text-decoration: none;
    vertical-align: top;
    white-space: nowrap;
}
.uiButton + .uiButton {
    margin-left: 4px;
}
.uiButton:hover {
    text-decoration: none;
}
.uiButton:active, .uiButtonDepressed {
    -moz-box-shadow: 0 1px 0 rgba(0, 0, 0, 0.05);
    background: none repeat scroll 0 0 #DDDDDD;
    border-bottom-color: #999999;
}
.uiButton .img {
    margin-top: 2px;
    vertical-align: top;
}
.uiButtonLarge .img {
    margin-top: 4px;
}
.uiButton .customimg {
    margin-top: 0;
}
.uiButton .uiButtonText, .uiButton input {
    background: none repeat scroll 0 0 transparent;
    border: 0 none;
    color: #333333;
    cursor: pointer;
    display: inline-block;
    font-family: 'Lucida Grande',Tahoma,Verdana,Arial,sans-serif;
    font-size: 11px;
    font-weight: bold;
    margin: 0;
    outline: medium none;
    padding: 1px 0 2px;
    white-space: nowrap;
}

.uiButtonConfirm {
    background-color: #5B74A8;
    background-position: 0 -48px;
    border-color: #29447E #29447E #1A356E;
}
.uiButtonConfirm:active {
    background: none repeat scroll 0 0 #4F6AA3;
    border-bottom-color: #29447E;
}


	</style>
</head>
<?php
	ob_end_flush();
	flush();
?>
<body>
	<?php if(!$login): ?>
		To use this app, facebook access must be allowed:
		<fb:login-button show-faces perms="user_hometown,friends_hometown,user_location,friends_location,read_stream"></fb:login-button>

		<div style="float:right;">
			<fb:like-box href="http://www.facebook.com/apps/application.php?id=173982279304513" width="292" show_faces="true" stream="false" header="false"></fb:like-box>
		</div>
	<?php else: ?>
		<div>
			<canvas style="background: no-repeat url(http://static.ak.fbcdn.net/rsrc.php/z9/r/jKEcVPZFk-2.gif) center center;" id="foam" width="720" height="320">
				<img id="earth" src="maps/1.jpg" width="720" height="320" />
			</canvas>
		</div>
		<div>
			<meter style="width:720px; display:block; height:1em;" id="progressbar" class="ui-progressbar ui-widget ui-widget-content ui-corner-all" min="0" max="100" value="0"></meter>
			<div id="post-to-profile" style="width:720px; display:block;"></div>

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
		e.src = document.location.protocol + '//connect.facebook.net/<?php echo $fblocale ?>/all.js';
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