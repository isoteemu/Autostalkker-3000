<?php

$url = 'http://graph.facebook.com/'.$_GET['id'].'/picture?type=large';

header("Location: $url");

