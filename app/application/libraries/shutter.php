<?php

class Shutter {

	private static $filters = array();
	private static $hooks = array();
	private static $shortcodes = array();
	private static $plugin_info = array();
	private static $active_plugins = array();
	public static $active_pulse_plugins = array();
	private static $class_map = array();

	private static function plugin_is_active($callback)
	{
		return in_array( get_class( $callback[0] ), self::$active_plugins);
	}

	public static function get_oembed($url)
	{
		if (!defined('FCPATH')) return false; // Shouldn't be called outside of API context

		$parts = explode('url=', $url);
		$url = $parts[0] . 'url=' . urlencode($parts[1]);

		$hash = md5($url) . '.oembed.cache';
		$cache = FCPATH . 'storage' . DIRECTORY_SEPARATOR .
					'cache' . DIRECTORY_SEPARATOR .
					'api' . DIRECTORY_SEPARATOR . $hash;

		if (file_exists($cache) && (time() - filemtime($cache)) < 3600)
		{
			$info = file_get_contents($cache);
		}
		else
		{
			$curl = curl_init();
			curl_setopt($curl, CURLOPT_URL, $url);
			curl_setopt($curl, CURLOPT_HEADER, 0);
			curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
			$info = curl_exec($curl);
			curl_close($curl);

			$json = json_decode($info);

			if ($json && !isset($json->error))
			{
				file_put_contents($cache, $info);
			}
		}

		return json_decode($info, true);
	}

	public static function init()
	{
		$root = dirname(dirname(dirname(dirname(__FILE__))));

		// Loads Koken internal plugins
		$iterator = new DirectoryIterator("$root/app/plugins");
		foreach($iterator as $fileinfo)
		{
			$dir = $fileinfo->getPath() . '/' . $fileinfo->getFilename();
			$plugin = $dir. '/plugin.php';
			$info = $dir. '/plugin.json';
			if ($fileinfo->isDir() && !$fileinfo->isDot() && file_exists($plugin)) {
				include($plugin);
				$data = json_decode(file_get_contents($info), true);
				$klasses = get_declared_classes();
				$last = array_pop( $klasses );
				$data['php_class'] = new $last;
				$data['php_class_name'] = get_class($data['php_class']);
				self::$active_plugins[] = $data['php_class_name'];
				$data['path'] = $fileinfo->getFilename();
				$data['internal'] = true;
				$data['pulse'] = false;
				self::$plugin_info[] = $data;
			}
		}

		// Loads userland plugins installed in storage/plugins
		$iterator = new DirectoryIterator("$root/storage/plugins");
		foreach($iterator as $fileinfo)
		{
			$dir = $fileinfo->getPath() . '/' . $fileinfo->getFilename();
			$plugin = $dir. '/plugin.php';
			$pulse = $dir. '/pulse.json';
			$info = $dir. '/plugin.json';
			if ($fileinfo->isDir() && !$fileinfo->isDot()) {
				if (file_exists($info))
				{
					$data = json_decode(file_get_contents($info), true);
					if ($data)
					{
						if (!file_exists($plugin) && !isset($data['oembeds']))
						{
							continue;
						}
						$data['path'] = $fileinfo->getFilename();
						if (file_exists($plugin))
						{
							$raw_plugin = file_get_contents($plugin);
							preg_match('/class\s([^\s]+)\sextends\sKokenPlugin/m', $raw_plugin, $matches);

							if ($matches && !class_exists($matches[1]))
							{
								include($plugin);
								$klasses = get_declared_classes();
								$last = array_pop( $klasses );
								$data['php_class'] = new $last;
								$data['php_class_name'] = get_class($data['php_class']);
								self::$class_map[ $data['php_class_name'] ] = $data['php_class'];
							}
						}
						$data['pulse'] = $data['internal'] = false;
						self::$plugin_info[] = $data;
					}
				}
				else if (file_exists($pulse))
				{
					$data = json_decode(file_get_contents($pulse), true);
					$data['path'] = $fileinfo->getFilename();
					$data['plugin'] = '/storage/plugins/' . $data['path'] . '/' . $data['plugin'];
					$data['pulse'] = true;
					$data['internal'] = false;
					$data['ident'] = $data['id'];
					self::$plugin_info[] = $data;
				}

			}
		}
	}

	public static function plugins()
	{
		return self::$plugin_info;
	}

	public static function finalize_plugins($plugins)
	{
		foreach($plugins as $plugin)
		{
			if ($plugin['pulse'] && $plugin['activated'])
			{
				self::$active_pulse_plugins[] = array(
					'key' => $plugin['ident'],
					'path' => $plugin['plugin']
				);
			}
			else if (isset($plugin['php_class_name']) && $plugin['activated'] && ($plugin['internal'] || $plugin['setup']))
			{
				self::$active_plugins[] = $plugin['php_class_name'];
			}

			if (!empty($plugin['data']) && isset(self::$class_map[ $plugin['php_class_name'] ]))
			{
				$d = new stdClass;
				foreach($plugin['data'] as $key => $data)
				{
					if (!isset($data['value']))
					{
						$data['value'] = '';
					}
					$d->$key = $data['value'];
				}
				self::$class_map[ $plugin['php_class_name'] ]->set_data( $d );
			}
		}
	}

	public static function hook($name, $obj = null)
	{
		if (!isset(self::$hooks[$name])) return;

		$to_call = self::$hooks[$name];
		if (!empty($to_call))
		{
			foreach($to_call as $callback)
			{
				if (self::plugin_is_active($callback))
				{
					call_user_func($callback, $obj);
				}
			}
		}
	}

	public static function shortcodes($content, $args)
	{
		$scripts = array();

		preg_match_all('/\[([a-z_]+)(\s(.*?))?\]/', $content, $matches);

		foreach($matches[0] as $index => $match) {
			$tag = $match;
			$code = $matches[1][$index];
			$attr = $matches[3][$index];
			if (isset(self::$shortcodes[$code]) && self::plugin_is_active(self::$shortcodes[$code]))
			{
				if (!empty($attr))
				{
					preg_match_all('/([a-z_]+)="([^"]+)?"/', $attr, $attrs);
					$attr = array_combine($attrs[1], $attrs[2]);
				}
				$attr['_relative_root'] = array_shift(explode('api.php', $_SERVER['PHP_SELF']));

				foreach($attr as $key => &$val) {
					$val = str_replace(array('__quot__', '__lt__', '__gt__', '__n__', '__lb__', '__rb__'), array('"', '<', '>', "\n", '[', ']'), $val);
				}

				$filtered = call_user_func(self::$shortcodes[$code], $attr);
				if (is_array($filtered))
				{
					$replacement = $filtered[0];
					if (empty($filtered[1]))
					{
						$filtered[1] = array();
					}
					else if (!is_array($filtered[1]))
					{
						$filtered[1] = array($filtered[1]);
					}
					foreach($filtered[1] as $script)
					{
						if (!in_array($script, $scripts))
						{
							$scripts[] = $script;
						}
					}

				}
				else
				{
					$replacement = $filtered;
				}
				$content = str_replace($tag, $replacement, $content);
			}
		}

		if (!empty($scripts))
		{
			$base = array_shift(explode('/api.php', $_SERVER['REQUEST_URI']));
			foreach($scripts as &$script)
			{
				$script = '<script src="' . $base . $script . '"></script>';
			}
			$content = join('', $scripts) . $content;
		}
		return $content;
	}

	public static function filter($name, $args)
	{
		$data = is_array($args) && isset($args[0]) ? array_shift($args) : $args;

		if (!isset(self::$filters[$name]))
		{
			return $data;
		}

		$to_call = self::$filters[$name];

		if (!empty($to_call))
		{
			foreach($to_call as $callback)
			{
				if (self::plugin_is_active($callback))
				{
					if (is_array($args))
					{
						$data = call_user_func_array($callback, array_merge($data, $args));
					}
					else
					{
						$data = call_user_func($callback, $data);
					}
				}
			}
		}

		return $data;
	}

	public static function register_hook($name, $arr)
	{
		if (!isset(self::$hooks[$name]))
		{
			self::$hooks[$name] = array();
		}

		if (in_array($arr, self::$hooks[$name])) return;

		self::$hooks[$name][] = $arr;
	}

	public static function register_filter($name, $arr)
	{
		if (!isset(self::$filters[$name]))
		{
			self::$filters[$name] = array();
		}

		if (in_array($arr, self::$filters[$name])) return;

		self::$filters[$name][] = $arr;
	}

	public static function register_shortcode($name, $arr)
	{
		if (!isset(self::$shortcodes[$name]))
		{
			self::$shortcodes[$name] = $arr;
		}
	}

	public static function hook_exists($name)
	{
		if (!isset(self::$hooks[$name]) || empty(self::$hooks[$name]))
		{
			return false;
		}

		foreach(self::$hooks[$name] as $callback)
		{
			if (self::plugin_is_active($callback))
			{
				return true;
			}
		}

		return false;
	}
}

class KokenPlugin {

	protected $data;
	protected $require_setup = false;

	function after_setup()
	{
		return true;
	}

	function is_compatible()
	{
		return true;
	}

	function require_setup()
	{
		return $this->require_setup;
	}

	function confirm_setup()
	{
		return true;
	}

	function set_data($data)
	{
		$this->data = $data;
	}

	/* Following functions are "final" and cannot be overriden in plugin classes */

	final protected function get_path()
	{
		$reflector = new ReflectionClass(get_class($this));
		$dir = basename(dirname($reflector->getFileName()));
		return Koken::$location['real_root_folder'] . '/storage/plugins/' . $dir;
	}

	final protected function request_token()
	{
		if (class_exists('Application') && isset($_POST))
		{
			$a = new Application;
			$a->single_use = 1;
			$a->role = 'read-write';
			$a->token = koken_rand();
			$a->save();
			return $a->token;
		}
		else
		{
			return false;
		}
	}

	final protected function register_hook($hook, $method)
	{
		Shutter::register_hook($hook, array($this, $method));
	}

	final protected function register_filter($filter, $method)
	{
		Shutter::register_filter($filter, array($this, $method));
	}

	final protected function register_shortcode($shortcode, $method)
	{
		Shutter::register_shortcode($shortcode, array($this, $method));
	}
}

Shutter::init();