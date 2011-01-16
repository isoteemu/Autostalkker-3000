<?php

@include_once 'Cache/Lite.php';

if(class_exists('Cache_Lite')) {
	function _cache_lite_default_options() {
		return array(
				'cacheDir' => sys_get_temp_dir(),
				'lifeTime' => null,
				'pearErrorMode' => CACHE_LITE_ERROR_DIE
		);
	}

	if(!function_exists('apc_store')) {
		function apc_store($key, $var, $ttl=0) {
			$options = array(
				'lifeTime' => $ttl
			) + _cache_lite_default_options();

			$cache = new Cache_Lite($options);

			return $cache->save(serialize($var), $key);
		}
	}

	if(!function_exists('apc_fetch')) {
		function apc_fetch($key, &$success=false) {
			$options = _cache_lite_default_options();
			$cache = new Cache_Lite($options);
			$data = $cache->get($key);

			if($data === false) {
				$success = false;
				return null;
			}

			$data = @unserialize($data);
			$success = true;
			return $data;
		}
	}
}