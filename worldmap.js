
worldmap = {
	canvas: document.getElementById('foam'),

	height: 320,
	width: 720,
	line_thickness: 1,
	scale: 1,
	grid_step: 6, // How many pixels is used to group line targets.

	max_score: 1,

	line_max_score: 1,
	
	// Viewport target coordinates.
	viewport: {
		n: -90,
		e: -180,
		s: 90,
		w: 180
	},

	map_scales: {
		'1': 'maps/1.jpg',
	},

	friends: {}

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

worldmap.drawLine = function(line, intensity, direction) {
	var ctx = this.canvas.getContext('2d');

	ctx.save();
	
	if(typeof(intensity) == "undefined")
		intensity = 1;

	var from_lat = this.user.location.lat;
	var from_lng = this.user.location.lng;
	var to_lat	 = this.Util.array_sum(line.lat) / line.lat.length;
	var to_lng	 = this.Util.array_sum(line.lng) / line.lng.length;

	/*
	this.updateViewportCoordinates(from_lat, from_lng);
	this.updateViewportCoordinates(from_lng, to_lng);
	*/
	// TODO color by score

	var color_step = 255 / this.line_max_score
	var color = color_step * (line.score - 1);
//	var brightness = (255 - color) * intensity;
//	color += brightness;
	color = Math.round(color);

	var x1 = worldmap.lng2px(from_lng);
	var x2 = worldmap.lng2px(to_lng);
	var y1 = worldmap.lat2px(from_lat);
	var y2 = worldmap.lat2px(to_lat);

	if( x1 == x2 && y1 == y2 )
		return;

	min_y = Math.min(y1, y2);
	max_y = Math.max(y1, y2);

	min_x = Math.min(x1, x2);
	max_x = Math.max(x1, x2);

	cpy = min_y - (max_y - min_y) / 2;
	cpx = max_x - (max_x - min_x) / 2;
	
	var red  = color;
	var green =  color;
	var blue = 127 + Math.max(color-127, 0);
	var line_width = line.count / this.line_thickness * 3 / this.scale;

	ctx.beginPath();
	// ADD Clipping
	var clip_width = (max_x - min_x) * (1 - intensity) * 1.5;
	var clip_height = this.height;

	var clip_y = 0
	if(direction == 1) {
		var clip_x = (x1 < x2) ? x2 - clip_width : x2;
	} else {
		var clip_x = (x1 < x2) ? x1 - line_width : x1 - clip_width - line_width;
	}
	//var clip_x = (x1 < x2) ? min_x + (max_x - min_y) * intensity : x2 + (max_x - (max_x - min_y) * intensity);

	clip_width += line_width * 2
	clip_height += line_width * 2;

	ctx.lineWidth = 0;
	ctx.rect(clip_x, clip_y, clip_width, clip_height);
	ctx.clip();
	ctx.beginPath();
	ctx.lineWidth = line_width;
	ctx.strokeStyle = "rgb("+red+","+green+","+blue+")";
	ctx.strokeStyle = "rgba("+red+","+green+","+blue+", "+intensity+")";

	ctx.lineCap = "round";
	
	ctx.moveTo(x1, y1);
	ctx.quadraticCurveTo(cpx, cpy, x2, y2);
	
	ctx.stroke();

	ctx.restore();

}

worldmap.plotPicture = function(person, lat, lng) {

	var picture = person.picture;

	var width = picture.width / 2
	var height = picture.height / 2
	
	if(typeof(person.score) != "undefined") {
		var score_scale = person.score / this.max_score;
	} else {
		var score_scale = 0.5;
	}

	width = (width / 3) + (width / 3) * score_scale * 2;
	height = (height / 3) + (height / 3) * score_scale * 2;

	// Grow faces more as closer we get
	width = width * Math.pow(1.02, this.scale) / this.scale;
	height = height * Math.pow(1.02, this.scale) / this.scale;

	var left = worldmap.lng2px(lng)-width/2;
	var top  = worldmap.lat2px(lat)-height/2;

	worldmap.canvas.getContext('2d').drawImage(picture, left, top, width, height);

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

	return this.map_scales[map];
}

worldmap.draw = function() {
	worldmap.canvas.getContext('2d').restore();

	steps = [
		'geocode',
		'viewport_get',
		'viewport_update',
		'background',
		'max_score',
		'canvas_clean_state',
		'faces',
		'remove_progressbar',
		'run_effect'
	];
	this.drawSteps(steps);
}

worldmap.drawSteps = function(steps) {
	if(steps.length == 0) {
		window.status = "";
		return;
	}

	var step = steps.shift();

	switch(step) {
		case "geocode":
			window.status = "Detecting friend locations";
			this._geocodeFriends(steps);
			return;

		case "viewport_get":
			window.status = "Calculating viewport dimensions";
			this.viewport = {
				n: -90,
				e: -180,
				s: 90,
				w: 180
			};
			for(id in this.friends) {
				if(typeof(this.friends[id].location) != "object") continue;
				this.updateViewportCoordinates(
					this.friends[id].location.lat,
					this.friends[id].location.lng
				);
			}
			return this.drawSteps(steps);

		case "viewport_update":
			window.status = "Updating viewport";
			this.updateViewport();
			return this.drawSteps(steps);
		case "background":
			window.status = "Drawing background";
			var background = new Image();
			background.onload = function() {
				worldmap.canvas.getContext('2d').drawImage(this, 0, 0, worldmap.width, worldmap.height);
				worldmap.drawSteps(steps);
			}
			background.src = this.getBackround();
			return;

		case "max_score":
			window.status = "Detecting best friend";
			var best_friend = false;
			for(id in this.friends) {
				if(typeof(this.friends[id].location) != "object") continue;
//				this.max_score = Math.max(this.max_score, (this.friends[id].score) ? this.friends[id].score : 1);
				if(this.max_score < this.friends[id].score) {
					this.max_score = this.friends[id].score;
					best_friend = this.friends[id];
				}
			}

			if(best_friend != false) {
				worldmap.Ui.FBPost(best_friend);
			}

			return this.drawSteps(steps);
		case "faces":
			window.status = "Loading faces";

			this.progressbar.set(1);
			this.progressbar.oncomplete = function() {
				worldmap.drawFaces();
				worldmap.drawSteps(steps);
			}

			this.drawFace(this.user);

			for(id in this.friends) {
				if(typeof(this.friends[id].location) != "object") continue;
				this.progressbar.steps++;
				this.drawFace(this.friends[id]);
			}
			// And draw self too
			this.drawFace(this.user);

			return;

		case "remove_progressbar":
			window.status = "Removing progressbar";
			this.progressbar.hide();
			return this.drawSteps(steps);

		case "canvas_clean_state":
			this.canvas_clean_state = this.canvas.getContext("2d").getImageData(0, 0, this.canvas.width, this.canvas.height);
			return this.drawSteps(steps);

		case "run_effect":
			this.runLineEffect();
			return this.drawSteps(steps);
		default:
			console.log("Unknown step:", step);
	}

	return this.drawSteps(steps);
}

worldmap.drawFace = function(person) {
	if(typeof(person.picture) != "object") {
		person.picture = new Image();
		person.picture.onload = person.picture.onerror = function() {
			worldmap.progressbar.increment();
		}
		person.picture.src = 'http://graph.facebook.com/'+person.id+'/picture';
	}
}

worldmap.drawFaces = function() {
	for(id in this.friends) {
		if(typeof(this.friends[id].picture) == "object") {
			this.plotPicture(this.friends[id], this.friends[id].location.lat, this.friends[id].location.lng);
		}
	}
	this.plotPicture(this.user, this.user.location.lat, this.user.location.lng);
}

// Load geocoded friend information.
worldmap._geocodeFriends = function(steps) {
	var list = []

	// Filter those whom location is known.
	for(id in this.friends) {
		if(this.friends[id].location) continue;
		list.push(id);
	}

	this.progressbar.set(list.length);

	this.progressbar.oncomplete = function() {
		worldmap.drawSteps(steps)
	}

	for(var i=0; i< list.length; i++) {
		$.ajax({
			url:'friend.php',
			data:{fid:list[i]},
			dataType:'json',
			cache:true,
			success: function(data) {
				if(data.location)
					jQuery.extend(worldmap.friends[data.id], data);
			},
			complete: function() {
				worldmap.progressbar.increment();
			}
		});
	}
}

// Group lines
worldmap.getConnectingLines = function() {
	
	this.line_max_score = 1;

	lines = {};
	for(id in this.friends) {
		if(!this.friends[id].location) continue;

		var lat = this.alignLine2grid(this.friends[id].location.lat);
		var lng = this.alignLine2grid(this.friends[id].location.lng);

		var grp = lat+":"+lng;

		if(typeof(lines[grp]) != "object") {
			lines[grp] = {
				lat: [],
				lng: [],
				count: 0,
				score: 0,
				score_weight: 0
			};
		}

		lines[grp].count++;
		lines[grp].score += this.friends[id].score;
		lines[grp].score_weight += this.friends[id].score_weight;
		lines[grp].lat.push(this.friends[id].location.lat);
		lines[grp].lng.push(this.friends[id].location.lng);

		this.line_max_score = Math.max(this.line_max_score, lines[grp].score);

		this.max_lines = Math.max(this.max_lines, lines[grp].count);
	}

	// Convert object into array and sort ascending by score
	var sortable = []
	for (var group in lines)
		sortable.push(lines[group])
	  
	sortable.sort(function(a, b) {
		return a.score - b.score;
	});

	return sortable;
}

worldmap.alignLine2grid = function(pos) {
	var pres = 1;
	if(this.scale >= 10)
		pres = 10;

	return Math.round(pos / this.grid_step * pres) * this.grid_step / pres; 
}


worldmap.runLineEffect = function() {
	lines = this.getConnectingLines();
	this.timer_start = new Date().getTime();
	worldmap.redrawLines(lines);
}

worldmap.redrawLines = function(lines, states) {
	if(!states)
		states = [];

	var now = (new Date().getTime() - this.timer_start) / 1000;

	this.canvas.getContext("2d").putImageData(this.canvas_clean_state, 0, 0);

	this.drawFaces();
	
	for(var i=0; i< lines.length; i++) {
		var line = lines[i];

		var effect_time = this.line_max_score / line.score;

		var intensity = 0;
		if(effect_time > 0) {
			intensity = 1 - ((now % effect_time) / effect_time);
		}

		if(typeof(states[i]) != "object") {
			states[i] = {
				direction: 0
			};
		}
		if(states[i].intensity < intensity) {
			// New line. Get direction
			var dir = line.score_weight / line.count;
			states[i].direction = (dir >= Math.random()) ? 0 : 1;
		}

		states[i].intensity = intensity;

		this.drawLine(line, intensity, states[i].direction);
	}
	// 25 fps
	this.timer = setTimeout(function() {
		worldmap.redrawLines(lines, states);
	}, 40);
}

worldmap.progressbar = {
	node: $('#progressbar').progressbar(),
	steps: 1,
	value: 0,

	oncomplete: function() {},

	set: function(steps) {
		this.node.show();
		this.oncomplete = function() {};
		this.steps=steps;
		this.value = 0;
		this.node.attr('value', 0).progressbar({value:0});
	},

	increment: function() {
		this.value++;
		var progress = this.value / this.steps * 100;
		progress = Math.round(progress);

		this.node.attr('value', progress).progressbar({value:progress});
		if(progress == 100)
			this.oncomplete();
	},
	
	hide: function() {
		this.node.hide();
	}
}

worldmap.Ui = {

	FBPost: function(best_friend) {
		var img = 'http://graph.facebook.com/'+worldmap.user.id+'/picture?type=square';
		var app_id = $('head meta[property="fb:app_id"]').attr('content');
		var picture = $('head meta[property="og:image"]').attr('content');
		var link = 'http://apps.facebook.com/worldoffriendlylove/';
		var name = $('head title').text();

		var location = best_friend.location.address;
		var caption = "";
		var message = "My greatest and bestest friend in the whole wide world hangs out at "+location+".\nI lov' you bro!";

		var description = location+" 'rocks, according to city blocks.";
		
		var actions = "[{name:'dat man', link:'http://www.facebook.com/profile.php?id="+best_friend.id+"'}]";
		
		var dialog = '<form method="GET" action="http://www.facebook.com/dialog/feed" class="uiUfiAddComment clearfix ufiItem ufiItem uiListItem  uiListVerticalItemBorder uiUfiAddCommentCollapsed"> \
			<input type="hidden" name="app_id" value="'+app_id+'" /> \
			<input type="hidden" name="link" value="'+link+'" /> \
			<input type="hidden" name="picture" value="'+picture+'" /> \
			<input type="hidden" name="name" value="'+name+'" /> \
			<input type="hidden" name="caption" value="'+caption+'" /> \
			<input type="hidden" name="redirect_uri" value="'+window.location+'" /> \
			<input type="hidden" name="description" value="'+description+'" /> \
			<input type="hidden" name="actions" value="'+actions+'" /> \
			<img alt="" src="'+img+'" class="uiProfilePhoto actorPic UIImageBlock_Image UIImageBlock_ICON_Image uiProfilePhotoMedium img"> \
			<div class="commentArea UIImageBlock_Content UIImageBlock_ICON_Content"> \
			<div class="commentBox"> \
			<textarea name="message" placeholder="Post to profile..." title="Post to profile..." class="uiTextareaNoResize uiTextareaAutogrow textBox textBoxContainer">'+message+'</textarea> \
			</div> \
			<label class="mts commentBtn stat_elem uiButton uiButtonConfirm"> \
			<input type="submit" name="comment" value="Post" /> \
			</label></div></form>';
		$('#post-to-profile').html(dialog);
		$('#post-to-profile textarea, #post-to-profile submit').focus(function(e) {
			$('#post-to-profile, #post-to-profile submit').addClass('child_is_focused');
		});
		$('#post-to-profile textarea, #post-to-profile submit').blur(function() {
			setTimeout(function() {
				$('#post-to-profile').removeClass('child_is_focused');
			}, 250);
		});

		$('#post-to-profile form').submit(function() {
			if(typeof(FB.ui) == "function") {
				FB.ui({
					method: 'feed',
					app_id: $('form :input[name=app_id]').val(),
					picture: $('form :input[name=picture]').val(),
					message: $('form :input[name=message]').val(),
					name: $('form :input[name=name]').val(),
					link: $('form :input[name=link]').val(),
					caption: $('form :input[name=caption]').val(),
					description: $('form :input[name=description]').val(),
					actions: $('form :input[name=actions]').val(),
				});
				return false;
			}
		});

		// Change into citys' picture, if found.
		if(typeof(FB) == "object") {
			var search = 'search?type=page&q='+best_friend.location.address;
			FB.api(search, function(response) {
				var data = response.data;
				for (var i=0; i<data.length; i++) {
					if(data[i].category == "City") {
						var url = window.location.protocol + "//"
							+ window.location.hostname
							+ window.location.pathname
							+ "picture.php?id="+escape(data[i].id);
						$('#post-to-profile :input[name=picture]').val(url);
						break;
					}
				}
			});
		}
	}

}

worldmap.Util = {
	array_sum: function(arr) {
		for(var i=0,sum=0;i<arr.length;sum+=arr[i++]);
		return sum;
	}
}
