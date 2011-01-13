
worldmap = {
	canvas: document.getElementById('foam'),

	height: 320,
	width: 720,
	line_thickness: 1,
	scale: 1,
	grid_step: 6, // How many pixels is used to group line targets.

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

	return this.map_scales[map];
}

worldmap.draw = function() {
	worldmap.canvas.getContext('2d').restore();

	steps = [
		'geocode',
		'viewport_get',
		'viewport_update',
		'background',
		'faces',
		'lines'
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

		case "faces":
			window.status = "Loading faces";
			for(id in this.friends) {
				if(typeof(this.friends[id].location) != "object") continue;
				this.drawFace(this.friends[id]);
			}
			return this.drawSteps(steps);

		case "lines":
			window.status = "Drawing connection lines";
			var lines = this.getConnectingLines();
			for(key in lines) {
				var line = lines[key];

				this.drawLine(
					this.user.location.lat,
					this.user.location.lng,
					this.Util.array_sum(line.lat) / line.lat.length,
					this.Util.array_sum(line.lng) / line.lng.length,
					line.count
				);

			}
			return this.drawSteps(steps);

		default:
			console.log("Unknown step:", step);
	}

	return this.drawSteps(steps);
}

worldmap.drawFace = function(person) {
	this.plotPicture(person.id, person.location.lat, person.location.lng);
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
					worldmap.friends[data.id].location = data.location;
			},
			complete: function() {
				worldmap.progressbar.increment();
			}
		});
	}
}

// Group lines
worldmap.getConnectingLines = function() {
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
				count: 0
			};
		}

		lines[grp].count++;
		lines[grp].lat.push(this.friends[id].location.lat);
		lines[grp].lng.push(this.friends[id].location.lng);

		this.max_lines = Math.max(this.max_lines, lines[grp].count);
	}

	return lines;
}

worldmap.alignLine2grid = function(pos) {
	var pres = 1;
	if(this.scale >= 10)
		pres = 10;

	return Math.round(pos / this.grid_step * pres) * this.grid_step / pres; 
}

worldmap.progressbar = {
	node: $('#progressbar').progressbar(),
	steps: 1,
	value: 0,

	oncomplete: function() {},

	set: function(steps) {
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
}

worldmap.Util = {
	array_sum: function(arr) {
		for(var i=0,sum=0;i<arr.length;sum+=arr[i++]);
		return sum;
	}
}
