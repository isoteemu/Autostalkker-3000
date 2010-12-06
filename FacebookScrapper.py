#!/usr/bin/env python
# -*- coding: utf-8 -*-

import sys, os
import re
import urllib, operator
import cookielib, urllib2

# TODO Check if we are loggend _Really_
# TODO Implement friendlist stalking
class FacebookScrapper:

	cookieFile = 'facebook-cookies-%s.txt'

	headers = {
		'User-Agent': 'Mozilla/5.0 (Linux; U; Android 2.1-update1; en-gb; desire_A8181 Build/ERE27) AppleWebKit/530.17 (KHTML, like Gecko) Version/4.0 Mobile Safari/530.17',
		'Accept-Language': 'en-gb, en-us;q=0.5, en;q=0.5',
		'Accept-Charset': 'ISO-8859-1,utf-8;q=0.7,*;q=0.7',
	}

	urls = {
		'login': 'http://m.facebook.com/',
		'photos': 'http://m.facebook.com/photo_search.php?view=all&',
		'friends': 'http://m.facebook.com/friends.php?a&' # ?a is for "all"
	}

	cookieJar = cookielib.LWPCookieJar()

	def __init__(self, user=None, password=None):

		if user != None and password != None:
			self.mayLogin(user, password)

	def httpRequest(self, url, data=None):
		if operator.isMappingType(data) :
			data = urllib.urlencode(data)

		request = urllib2.Request(url, data, self.headers)
		opener = urllib2.build_opener(urllib2.HTTPCookieProcessor(self.cookieJar))
		result = opener.open(request)
		self.headers['Referer'] = result.geturl()

		return result

	# Login, if necessary
	def mayLogin(self, user, password):
		cookies = self.cookieFile % user
		
		need_login = True;
		if os.path.exists(cookies):
			self.cookieJar.load(cookies)
			
			for cookie in self.cookieJar:
				if cookie.name == "m_user" and cookie.is_expired() == False:
					need_login = False

		if need_login:
			self.login(user, password)
			self.cookieJar.save(cookies)

	def login(self, user, password):
		result = self.httpRequest(self.urls['login']);
		page = result.read()

		target = re.search(r'<form [^>]*action="([^"]+)"[^>]*>', page).group(1)

		login_data = {
			'email': user,
			'pass': password,
			'login': 'Log in'
		}
		inputs = re.findall(r'<input [^>]*\sname="([^"]+)" value="([^"]+)"[^>]*>', page)
		for key, val in inputs: 
			login_data[key] = val

		login_result  = self.httpRequest(target, login_data)

		if not re.match('http://m.facebook.com/home.php', login_result.geturl()):
			raise RuntimeWarning('Could not log into facebook')
			return false

		return self

	def photos(self, fbid):
		page_number = 1
		list = {}

		while True:
			url = self.urls['photos'] + urllib.urlencode([('id', fbid), ('page', page_number)])
			result = self.httpRequest(url)
			page = result.read()
			images = re.findall(r'<a href="/photo.php\?fbid=(\d+)(?=&)[^"]*"[^>]*><img [^>]*\ssrc="([^"]+)[^>]*></a>', page, re.S + re.U)
			for pid, thumb in images:
				list[pid] = thumb

			if len(images) == 10:
				page_number = page_number + 1
			else:
				break
		return list

	def friends(self, fbid):
		offset = 0
		list = {}

		while True:
			url = self.urls['friends'] + urllib.urlencode([('id', fbid), ('f', offset*10)])
			result = self.httpRequest(url)
			page = result.read()
			friends = re.findall(r'<div class="mfsm"><a href="/(profile.php\?id=(\d+)|[a-zA-Z0-9\.]+)(?=[&\?])[^"]*"[^>]*>([^<]+)</a>', page, re.S + re.U)

			for uid, pid, name in friends:
				if pid == "":
					pid = uid
				list[pid] = name
				print pid, name

			if len(friends) == 10:
				offset = offset + 1
			else:
				break

		return list

if __name__ == "__main__":
	import json

	f = file('config.json', 'r')
	config = json.loads(f.read())

	if len(sys.argv[1:]) == 1:
		fbid = unicode(sys.argv[1], 'utf-8')
		fb = FacebookScrapper(config['user']['user'], config['user']['password'])
		print json.dumps(fb.friends(fbid))
