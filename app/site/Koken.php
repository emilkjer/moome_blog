<?php

class Koken {
	public static $site;
	public static $settings = array();
	public static $profile;
	public static $location = array();
	public static $current_token;
	public static $rss;
	public static $categories;
	public static $page_class = false;
	public static $template_path;
	public static $fallback_path;
	public static $navigation_home_path = false;
	public static $original_url;
	public static $cache_path;
	public static $routed_variables;
	public static $draft;
	public static $preview;
	public static $rewrite;
	public static $pjax;
	public static $source;
	public static $template_variable_keys = array();
	public static $template_variables = array();
	public static $root_path;
	public static $protocol;
	public static $main_load_token = false;

	private static $last;
	private static $level = 0;
	private static $depth;
	public static $tokens = array();
	public static $url_replacements = array();
	public static $last_load_url;


	private static function out_callback($matches)
	{
		return '<?php echo Koken::out(\'' . trim(str_replace("'", "\\'", $matches[1])) . '\'); ?>';
	}

	public static function parse($template)
	{

		$output = preg_replace_callback('/(<|\s)(\/)?koken\:([a-z_\-]+)([\=|\s][^\/].+?")?(\s+\/)?>/', array('Koken', 'callback'), $template);

		foreach(self::$url_replacements as $arr)
		{
			preg_match('/' . str_replace('$', '\$', $arr['url']) . ' = ([^;]+);/', $output, $match);
			list($full, $url) = $match;

			$new = "{$arr['param']}:{$arr['val']}";
			$reg = '/' . $arr['param'] . ':([^\/\']+)/';
			preg_match($reg, $url, $matches);

			if (empty($matches))
			{
				$url .= " . '/$new'";
			}
			else
			{
				if (is_numeric($arr['val']) && $matches[1] < $arr['val'])
				{
					$url = preg_replace('/' . $arr['param'] . ':[^\/\']+/', $new, $url);
				}
			}

			$output = str_replace($full, $arr['url'] . ' = ' . $url . ';', $output);
		}

		$output = preg_replace('/\{\{\s*discussion\s*\}\}/', '<?php Shutter::hook(\'discussion\'); ?>', $output);
		$output = preg_replace_callback('/\{\{\s*([^\}]+)\s*\}\}/', array('Koken', 'out_callback'), $output);

		return $output;
	}

	private static function attr_callback($matches)
	{
		$name = $matches[1];

		$value = preg_replace_callback("/{([a-z._\(\)\,\|\s\'\/\[\]0-9]+)([\s\*\-\+0-9]+)?}/", array('Koken', 'attr_replace'), $matches[2]);
		$value = trim($value, '" . ');
		$value = str_replace('"str_replace(', 'str_replace(', $value);

		if (!preg_match('/^(\((Koken::)?\$|str_replace\(|\(?empty\()/', $value))
		{
			$value = '"' . $value;
		}
		if (substr_count($value, '"') % 2 !== 0)
		{
			$value .= '"';
		}
		$value = str_replace('. "" .', '.', $value);
		if ($name === 'href')
		{
			$value = "<?php echo ( strpos($value, '/') === 0 ? Koken::\$location['root'] . $value : $value ) . ( Koken::\$preview ? '&amp;preview=' . Koken::\$preview : '' ); ?>";
		}
		else
		{
			$value = "<?php echo $value; ?>";
		}
		return "$name=\"$value\"";
	}

	private function attr_replace($matches)
	{
		$t = new Tag();

		if (strpos($matches[1], '.replace(') !== false)
		{
			preg_match('/(.*)\.replace\((.*)\)/', $matches[1], $r_matches);
			$data = $t->field_to_keys($r_matches[1]);
			return 'str_replace(' . $r_matches[2] . ', ' . $data . ')';
		}

		$modifier = isset($matches[2]) ? $matches[2] : '';
		return '" . (' . $t->field_to_keys($matches[1]) . $modifier . ') . "';
	}

	private static function callback($matches)
	{
		$out = '';
		list($full, $start, $closing, $action) = $matches;
		$closing = $closing === '/';
		$attr = $start !== '<';

		if (isset($matches[4]))
		{
			preg_match_all('/([:a-z_\-]+)="([^"]+?)?"/', $matches[4], $param_matches);
			$parameters = array();
			$parameters['api'] = array();

			foreach($param_matches[1] as $index => $key)
			{
				if (strpos($key, 'api:') === 0)
				{
					$key = str_replace('api:', '', $key);
					$parameters['api'][$key] = $param_matches[2][$index];
				}
				else
				{
					$parameters[$key] = $param_matches[2][$index];
				}
			}

			if (empty($parameters['api']))
			{
				unset($parameters['api']);
			}
		}
		else
		{
			$parameters = array();
		}

		if (isset($matches[5]))
		{
			$self_closing = trim($matches[5]) === '/';
		}
		else
		{
			$self_closing = false;
		}

		if ($attr)
		{
			$out = preg_replace_callback('/koken:([a-z\-]+)="([^"]+?)"/', array('Koken', 'attr_callback'), $full);
		}
		else if ($action === 'else')
		{
			$else_tag = self::$last[self::$level-1];
			$out .= $else_tag->do_else();
			if ($else_tag->untokenize_on_else)
			{
				array_shift(self::$tokens);
				$else_tag->tokenize = false;
			}
		}
		else
		{
			$action = preg_replace_callback(
				'/_([a-zA-Z])/',
				create_function(
					'$matches',
					'return strtoupper($matches[1]);'
				),
				$action
			);

			if ($action === 'not')
			{
				$action = 'if';
				$parameters['_not'] = true;
			}
			else if ($action === 'pop')
			{
				$action = 'shift';
				$parameters['_pop'] = true;
			}
			else if ($action === 'permalink')
			{
				$action = 'link';
				$parameters['echo'] = true;
			} else if ($action === 'previous')
			{
				$action = 'next';
				$parameters['_prev'] = true;
			}

			$klass = 'Tag' . ucwords($action);
			$t = new $klass($parameters);

			if (!$closing)
			{
				if (!$self_closing)
				{
					self::$last[self::$level] = $t;
				}

				if ($t->tokenize)
				{
					$token = md5(uniqid());
					array_unshift(self::$tokens, $token);
				}

				$out .= trim($t->generate());

				if ($t->tokenize)
				{
					$out .= "<?php Koken::\$current_token = \$value$token; ?>";
				}
			}

			if ($self_closing || $closing)
			{
				if (!$self_closing && isset(self::$last[self::$level-1]) && method_exists(self::$last[self::$level-1], 'close'))
				{
					$close_tag = self::$last[self::$level-1];
					$out .= $close_tag->close();

					if ($close_tag->tokenize)
					{
						array_shift(self::$tokens);
						if (count(self::$tokens))
						{
							$out .= '<?php Koken::$current_token = $value' . self::$tokens[0] . '; ?>';
						}
					}
				}
				else if (method_exists($t, 'close'))
				{
					$out .= $t->close();

					if ($t->tokenize && !$t->untokenize_on_else)
					{
						array_shift(self::$tokens);
						if (count(self::$tokens))
						{
							$out .= '<?php Koken::$current_token = $value' . self::$tokens[0] . '; ?>';
						}
					}
				}

				if ($closing)
				{
					self::$level--;
				}
			}
			else
			{
				self::$level++;
			}
		}

		return $out;
	}

	public static function api($url)
	{
		if (!is_null(self::$site['draft_id']))
		{
			$url .= '/draft:' . self::$site['draft_id'];
		}
		else if (self::$preview)
		{
			$url .= '/draft:' . self::$preview;
		}
		$api_cache_dir = self::$root_path .
							DIRECTORY_SEPARATOR . 'storage' .
							DIRECTORY_SEPARATOR . 'cache' .
							DIRECTORY_SEPARATOR . 'api';
		$stamp = $api_cache_dir . DIRECTORY_SEPARATOR . 'stamp';
		$cache_file = $api_cache_dir . DIRECTORY_SEPARATOR . md5($url) . '.cache';

		if (file_exists($cache_file) && filemtime($cache_file) >= filemtime($stamp))
		{
			$data = file_get_contents($cache_file);
		}
		else
		{
			$curl = curl_init();
			curl_setopt($curl, CURLOPT_URL, self::$protocol . '://' . $_SERVER['HTTP_HOST'] . self::$location['real_root_folder'] . '/api.php?' . $url);
			curl_setopt($curl, CURLOPT_HEADER, 0);
			curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);

			$data = curl_exec($curl);
			curl_close($curl);
		}

		return json_decode($data, true);
	}

	public static function get_path($relative_path)
	{
		$primary = self::$template_path . DIRECTORY_SEPARATOR . $relative_path;
		$backup = self::$fallback_path . DIRECTORY_SEPARATOR . $relative_path;

		if (file_exists($primary))
		{
			return $primary;
		}
		else if (file_exists($backup))
		{
			return $backup;
		}
		return false;
	}

	public static function render($raw)
	{
		ob_start();
		eval('?>' . $raw);
		$contents = ob_get_contents();
		ob_end_clean();
		return $contents;
	}

	public static function cache($contents)
	{

		$buster = self::$root_path . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'no-site-cache';

		if (isset(self::$cache_path) && error_reporting() == 0 && !file_exists($buster))
		{
			function make_child_dir($path)
			{
				// No need to continue if the directory already exists
				if (is_dir($path)) return true;

				// Make sure parent exists
				$parent = dirname($path);
				if (!is_dir($parent))
				{
					make_child_dir($parent);
				}

				$created = false;
				$old = umask(0);

				// Try to create new directory with parent directory's permissions
				$permissions = substr(sprintf('%o', fileperms($parent)), -4);
				if (mkdir($path, octdec($permissions), true))
				{
					$created = true;
				}
				// If above doesn't work, chmod to 777 and try again
				else if (	$permissions == '0755' &&
							chmod($parent, 0777) &&
							mkdir($path, 0777, true)
						)
				{
					$created = true;
				}
				umask($old);
				return $created;
			}

			if (make_child_dir( dirname(self::$cache_path) ))
			{
				file_put_contents(self::$cache_path, $contents);
			}
		}
	}

	public static function out($key)
	{
		$parameters = array();
		preg_match_all('/([a-z_]+)=["\']([^"\']+)["|\']/', $key, $matches);
		foreach($matches[1] as $i => $name)
		{
			$key = str_replace($matches[0][$i], '', $key);
			$parameters[$name] = $matches[2][$i];
		}

		$key = str_replace(' ', '', $key);
		$count = $plural = $singular = $math = false;
		if (strpos($key, '|') === false)
		{
			$globals = array(
				'site', 'location', 'profile', 'source', 'settings', 'routed_variables', 'page_variables', 'pjax', 'labels'
			);

			if (strpos($key, '.length') !== false)
			{
				$key = str_replace('.length', '', $key);
				$count = true;
			}

			if (preg_match('/_on$/', $key))
			{
				$key .= '.timestamp';
				if (!isset($parameters['date_format']))
				{
					if (isset($parameters['date_only']))
					{
						$parameters['date_format'] = self::$site['date_format'];
					}
					else if (isset($parameters['time_only'])) {
						$parameters['date_format'] = self::$site['time_format'];
					}
					else
					{
						$parameters['date_format'] = self::$site['date_format'] . ' ' . self::$site['time_format'];
					}
				}
			}

			if (preg_match('~\s*([+\-/\*])\s*([0-9]+)\s*?~', $key, $maths))
			{
				$math = array('operator' => $maths[1], 'num' => $maths[2]);
				$key = str_replace($maths[0], '', $key);
			}

			$keys = explode('.', $key);

			if (in_array($keys[0], $globals))
			{
				$global_key = array_shift($keys);
				if ($global_key === 'labels')
				{
					$return = self::$site['url_data'];
				}
				else
				{
					$return = self::$$global_key;
				}
			}
			else if (in_array($key, self::$template_variable_keys))
			{
				return self::$template_variables[$key];
			}
			else
			{
				$return = self::$current_token;
			}



			while(count($keys))
			{
				$index = array_shift($keys);
				if ($index === 'index')
				{
					$index = '__loop_index';
				}
				else if ($index === 'first')
				{
					$index = 0;
				}

				if (!isset($return[$index]) && $index === 'type' && isset($return['__koken__']))
				{
					$return = self::$site['url_data'][$return['__koken__']]['plural'];
					$parameters['case'] = 'title';
					$plural = $singular = false;
				}
				else
				{
					if ($index === 'plural' && !isset($return['plural']))
					{
						$plural = true;
					}
					else if ($index === 'singular' && !isset($return['singular']))
					{
						$singular = true;
					}
					else
					{
						$return = isset($return[$index]) ? $return[$index] : '';
					}
				}
			}
		}
		else
		{
			$candidates = explode('|', $key);
			$return = '';
			while(empty($return) && count($candidates))
			{
				$return = self::out(array_shift($candidates));
			}
		}

		if ($count)
		{
			return count($return);
		}
		else
		{

			if (isset($parameters['truncate']))
			{
				$return = self::truncate(strip_tags($return), $parameters['truncate'], isset($parameters['after_truncate']) ? $parameters['after_truncate'] : '…');
			}

			if (isset($parameters['case']))
			{
				switch ($parameters['case']) {
					case 'lower':
						$return = function_exists('mb_strtolower') ? mb_strtolower($return) : strtolower($return);
						break;

					case 'upper':
						$return = function_exists('mb_strtoupper') ? mb_strtoupper($return) : strtoupper($return);
						break;

					case 'title':
						$return = function_exists('mb_convert_case') ? mb_convert_case($return, MB_CASE_TITLE) : ucwords($return);
						break;

					case 'sentence':
						return function_exists('mb_substr') ? mb_strtoupper(mb_substr($return, 0, 1)) . mb_strtolower(mb_substr($return, 1)) : ucfirst($return);
						break;
				}
			}

			if (isset($parameters['paragraphs']))
			{
				$return = self::format_paragraphs($return);
			}

			if (isset($parameters['date_format']))
			{
				$return = date($parameters['date_format'], $return);
			}

			if (isset($parameters['strip_html']))
			{
				$return = str_replace('"', '&quot;', strip_tags($return));
			}

			if ($plural)
			{
				$return = self::plural($return);
			}

			if ($singular)
			{
				$return = self::singular($return);
			}

			if ($math)
			{
				switch($math['operator'])
				{
					case '+':
						$return += $math['num'];
						break;

					case '-':
						$return -= $math['num'];
						break;

					case '/':
						$return /= $math['num'];
						break;

					case '*':
						$return *= $math['num'];
						break;
				}
			}

			if (is_array($return))
			{
				if (isset($return['clean']))
				{
					$return = $return['clean'];
				}
				else if (isset($return['raw']))
				{
					$return = $return['raw'];
				}
			}
			return isset($parameters['debug']) ? var_dump($return) : $return;
		}
	}

	private static function singular($str)
	{
		$result = strval($str);

		$singular_rules = array(
			'/(matr)ices$/'         => '\1ix',
			'/(vert|ind)ices$/'     => '\1ex',
			'/^(ox)en/'             => '\1',
			'/(alias)es$/'          => '\1',
			'/([octop|vir])i$/'     => '\1us',
			'/(cris|ax|test)es$/'   => '\1is',
			'/(shoe)s$/'            => '\1',
			'/(o)es$/'              => '\1',
			'/(bus|campus)es$/'     => '\1',
			'/([m|l])ice$/'         => '\1ouse',
			'/(x|ch|ss|sh)es$/'     => '\1',
			'/(m)ovies$/'           => '\1\2ovie',
			'/(s)eries$/'           => '\1\2eries',
			'/([^aeiouy]|qu)ies$/'  => '\1y',
			'/([lr])ves$/'          => '\1f',
			'/(tive)s$/'            => '\1',
			'/(hive)s$/'            => '\1',
			'/([^f])ves$/'          => '\1fe',
			'/(^analy)ses$/'        => '\1sis',
			'/((a)naly|(b)a|(d)iagno|(p)arenthe|(p)rogno|(s)ynop|(t)he)ses$/' => '\1\2sis',
			'/([ti])a$/'            => '\1um',
			'/(p)eople$/'           => '\1\2erson',
			'/(m)en$/'              => '\1an',
			'/(s)tatuses$/'         => '\1\2tatus',
			'/(c)hildren$/'         => '\1\2hild',
			'/(n)ews$/'             => '\1\2ews',
			'/([^u])s$/'            => '\1',
		);

		foreach ($singular_rules as $rule => $replacement)
		{
			if (preg_match($rule, $result))
			{
				$result = preg_replace($rule, $replacement, $result);
				break;
			}
		}

		return $result;
	}

	private static function plural($str, $force = FALSE)
	{
		$result = strval($str);

		$plural_rules = array(
			'/^(ox)$/'                 => '\1\2en',     // ox
			'/([m|l])ouse$/'           => '\1ice',      // mouse, louse
			'/(matr|vert|ind)ix|ex$/'  => '\1ices',     // matrix, vertex, index
			'/(x|ch|ss|sh)$/'          => '\1es',       // search, switch, fix, box, process, address
			'/([^aeiouy]|qu)y$/'       => '\1ies',      // query, ability, agency
			'/(hive)$/'                => '\1s',        // archive, hive
			'/(?:([^f])fe|([lr])f)$/'  => '\1\2ves',    // half, safe, wife
			'/sis$/'                   => 'ses',        // basis, diagnosis
			'/([ti])um$/'              => '\1a',        // datum, medium
			'/(p)erson$/'              => '\1eople',    // person, salesperson
			'/(m)an$/'                 => '\1en',       // man, woman, spokesman
			'/(c)hild$/'               => '\1hildren',  // child
			'/(buffal|tomat)o$/'       => '\1\2oes',    // buffalo, tomato
			'/(bu|campu)s$/'           => '\1\2ses',    // bus, campus
			'/(alias|status|virus)/'   => '\1es',       // alias
			'/(octop)us$/'             => '\1i',        // octopus
			'/(ax|cris|test)is$/'      => '\1es',       // axis, crisis
			'/s$/'                     => 's',          // no change (compatibility)
			'/$/'                      => 's',
		);

		foreach ($plural_rules as $rule => $replacement)
		{
			if (preg_match($rule, $result))
			{
				$result = preg_replace($rule, $replacement, $result);
				break;
			}
		}

		return $result;
	}

	public static function get_default_link($name)
	{
		$name = rtrim($name, 's');
		$template = self::$location['urls'][$name];
		preg_match('~^/([^/]+)~', $template, $matches);
		return $matches[0];
	}

	public static function form_link($obj, $ctx, $lightbox)
	{
		if (isset($obj['link']))
		{
			return $obj['link'];
		}

		if (isset($obj['__koken_url']))
		{
			$url = $obj['__koken_url'];
		}
		else
		{
			$defaults = self::$location['urls'];
			if (isset($obj['__koken__override']))
			{
				$type = $obj['__koken__override'];
			}
			else
			{
				if (isset($obj['album']))
				{
					$obj = $obj['album'];
				}
				$type = $obj['__koken__'];
			}

			$url = '';

			if ($type === 'album' && $obj['album_type'] === 'set')
			{
				$type = 'set';
			}

			if (isset($defaults[$type]))
			{
				if (!$defaults[$type] && $type === 'set')
				{
					$type = 'album';
				}

				if ($defaults[$type])
				{
					if (strpos($type, 'tag') === 0)
					{
						$obj['id'] = $obj['title'];
					}

					if (isset($obj['internal_id']))
					{
						$obj['id'] = $obj['slug'] = $obj['internal_id'];
					}

					$url = $defaults[$type];

					if ($obj['__koken__'] === 'content' && isset($ctx['id']) && is_numeric($ctx['id']))
					{
						$regex = strpos($url, ':slug') === false ? ':content_id' : ':content_slug';
						$obj['content_slug'] = $obj['slug'];
						$obj['content_id'] = $obj['id'];
						if (isset($ctx['internal_id']))
						{
							$obj['id'] = $obj['slug'] = $ctx['internal_id'];
						}
						else
						{
							$obj['slug'] = $ctx['slug'];
							$obj['id'] = $ctx['id'];
						}
						$url = $defaults['album'] . '/' . $regex;
					}

					if (!isset($obj['slug']) && isset($obj['tag']))
					{
						$obj['slug'] = $obj['tag']['title'];
					}

					if (isset($obj['date']))
					{
						$obj['year'] = date('Y', $obj['date']['timestamp']);
						$obj['month'] = date('m', $obj['date']['timestamp']);
						$obj['day'] = date('d', $obj['date']['timestamp']);
					}

					preg_match_all('/:([a-z_]+)/', $url, $matches);

					foreach($matches[1] as $magic)
					{
						$url = str_replace(':' . $magic, urlencode($obj[$magic]), $url);
					}

					$url = str_replace('(?:', '', $url);
					$url = str_replace(')?', '', $url);
				}
			}
		}

		$url = rtrim($url, '/');

		if ($lightbox)
		{
			$url = preg_replace('~/?lightbox$~', '', $url) . '/lightbox';
		}

		return $url . '/';
	}

	public static function context_parameters($type = 'content')
	{
		if ($type === 'content')
		{
			$params = '/context:';
			if (isset(Koken::$routed_variables['album_id']) || isset(Koken::$routed_variables['album_slug']))
			{
				$params .= isset(Koken::$routed_variables['album_id']) ? Koken::$routed_variables['album_id'] : 'slug-' . Koken::$routed_variables['album_slug'];
				$order = explode(' ', self::$site['url_data']['content']['album_order']);
				$params .= '/context_order:' . $order[0] . '/context_order_direction:' . strtolower($order[1]);
			}
			else
			{
				$order = explode(' ', self::$site['url_data']['content']['order']);
				$params .= 'stream/context_order:' . $order[0] . '/context_order_direction:' . strtolower($order[1]);
			}
		}
		else
		{
			$order = explode(' ', self::$site['url_data']['album']['order']);
			$params = '/context_order:' . $order[0] . '/context_order_direction:' . strtolower($order[1]);
		}
		return $params;
	}

	public static function render_nav($data, $list, $root = false, $klass = '')
	{
		$pre = $wrap_pre = $wrap_post = '';
		$post = '&nbsp;';
		if ($list)
		{
			$wrap_pre = '<ul class="k-nav-list' . ($root ? ' k-nav-root' : '') . ' ' . $klass . '">';
			$wrap_post = '</ul>';
			$pre = '<li>';
			$post = '</li>';
		}

		$current_match_len = 0;
		$o = $wrap_pre;
		foreach($data as $key => $value)
		{
			if ($value['path'][0] === '/')
			{
				$value['path'] = rtrim($value['path'], '/') . '/';
			}
			if ($value['path'] === self::$navigation_home_path)
			{
				$value['path'] = '/';
			}
			if ($value['path'] == '/')
			{
				$current = self::$location['here'] === '/';
			}
			else
			{
				$current = preg_match('~^' . $value['path'] . '(.*)?$~', self::$location['here']) && strlen($value['path']) > $current_match_len;

				if ($current)
				{
					$current_match_len = strlen($value['path']);
					$o = str_replace('class="k-nav-current"', '', $o);
					$o = str_replace('class="k-nav-current ', 'class="', $o);
				}
			}
			$o .= $pre . '<a';
			if (isset($value['target']))
			{
				$o .= ' target="' . $value['target'] . '"';
			}
			$classes = array();
			if ($current){
				$classes[] = 'k-nav-current';
			}
			if (isset($value['set']))
			{
				$classes[] = 'k-nav-set';
			}
			if (count($classes))
			{
				$o .= ' class="' . join(' ', $classes) . '"';
			}

			$root = $value['path'] === '/' ? preg_replace( '/\/index.php$/', '', self::$location['root'] ) : self::$location['root'];
			$o .= ' href="' . ( strpos($value['path'], 'http') === false && strpos($value['path'], 'mailto:') !== 0 ? $root : '' ) . $value['path'] . ( self::$preview ? '&amp;preview=' . self::$preview : '') . '">' . $value['label'] . '</a>';

			if (isset($value['items']) && !empty($value['items']))
			{
				$o .= self::render_nav($value['items'], $list);
			}
			$o .= $post;
		}
		return $o . $wrap_post;
	}

	static public function output_img($content, $options = array(), $params = '')
	{
		$defaults = array(
			'width' => 0,
			'height' => 0,
			'crop' => false,
			'hidpi' => self::$site['hidpi']
		);

		$options = array_merge($defaults, $options);

		$attr = array(
			$options['width'],
			$options['height']
		);

		$w = $options['width'];
		$h = $options['height'];

		if ($w ==0 && $h == 0)
		{
			// Responsive
			// return;
			// $w = '100%';
		}

		if (!isset($content['url']) || !isset($content['cropped']))
		{
			if (!$options['crop'])
			{
				if ($options['width'] == 0)
				{
					$w = round(($h*$content['width'])/$content['height']);
				}
				else if ($options['height'] == 0)
				{
					$h = round(($w*$content['height'])/$content['width']);
				}
				else
				{
					$original_aspect = $content['aspect_ratio'];
					$target_aspect = $w/$h;
					if ($original_aspect >= $target_aspect)
					{
						if ($w > $content['width'])
						{
							$w = $content['width'];
							$h = $content['height'];
						}
						else
						{
							$h = round(($w*$content['height'])/$content['width']);
						}
					}
					else
					{
						if ($h > $content['height'])
						{
							$w = $content['width'];
							$h = $content['height'];
						}
						else
						{
							$w = round(($h*$content['width'])/$content['height']);
						}
					}
				}
			}

			$longest = max($w, $h);

			$breakpoints = array(
				'tiny' => 60,
				'small' => 100,
				'medium' => 480,
				'medium_large' => 800,
				'large' => 1024,
				'xlarge' => 1600,
				'huge' => 2048
			);

			$preset_base = '';
			$last_len = false;
			foreach($breakpoints as $name => $len)
			{
				if ($longest <= $len)
				{
					$diff = $len - $longest;
					if (!$last_len || ($longest - $last_len > $diff))
					{
						$preset_base = $name;
					}
					break;
				}
				$preset_base = $name;
				$last_len = $len;
			}

			$attr[] = self::$site["image_{$preset_base}_quality"];
			$attr[] = self::$site["image_{$preset_base}_sharpening"]*100;

			$url = $content['cache_path']['prefix'] . ',' . join('.', $attr);
			if ($options['crop'])
			{
				$url .= '.crop';
			}
			if ($options['hidpi'])
			{
				$url .= '.2x';
			}
			$url .= '.' . $content['cache_path']['extension'];
		}
		else
		{
			if ($options['crop'])
			{
				$content = $content['cropped'];
			}
			else if ($w > 0 && $h > 0)
			{
				$original_aspect = $content['width'] / $content['height'];
				$target_aspect = $w/$h;
				if ($original_aspect >= $target_aspect)
				{
					$h = round(($w*$content['height'])/$content['width']);
				}
				else
				{
					$w = round(($h*$content['width'])/$content['height']);
				}
			}
			$url = $content['url'];
		}

		if ((isset($params['class']) && strpos($params['class'], 'k-lazy-load') !== false) || $options['hidpi'])
		{
			$params['data-src'] = $url;
			$noscript = true;
			$params['src'] = 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';
		}
		else
		{
			$noscript = false;
			$params['src'] = $url;
		}

		if ($w > 0)
		{
			$params['width'] = $w;
		}
		if ($h > 0)
		{
			$params['height'] = $h;
		}

		if ($noscript)
		{
			$noscript_params = $params;
			$noscript_params['src'] = $noscript_params['data-src'];
			unset($noscript_params['data-src']);
			$noscript = '<noscript><img ' . self::params_to_str($noscript_params) . ' /></noscript>';
		}
		else
		{
			$noscript = '';
		}

		return "$noscript<img " . self::params_to_str($params) . " />";
	}

	static private function params_to_str($params)
	{
		$arr = array();
		foreach($params as $key => $val)
		{
			$arr[] = "$key=\"$val\"";
		}
		return join(' ', $arr);
	}

	static public function truncate($str, $limit, $after = '…')
	{
		if (strlen($str) > $limit) {
			$str = trim(substr($str, 0, $limit)) . $after;
		}

		return $str;
	}

	static public function format_paragraphs($pee, $br = 1)
	{
		if ( trim($pee) === '' )
		return '';
		$pee = $pee . "\n"; // just to make things a little easier, pad the end
		$pee = preg_replace('|<br />\s*<br />|', "\n\n", $pee);
		// Space things out a little
		$allblocks = '(?:table|thead|tfoot|caption|col|colgroup|tbody|tr|td|th|div|dl|dd|dt|ul|ol|li|pre|select|option|form|map|area|blockquote|address|math|style|input|p|h[1-6]|hr|fieldset|legend|section|article|aside|hgroup|header|footer|nav|figure|figcaption|details|menu|summary)';
		$pee = preg_replace('!(<' . $allblocks . '[^>]*>)!', "\n$1", $pee);
		$pee = preg_replace('!(</' . $allblocks . '>)!', "$1\n\n", $pee);
		$pee = str_replace(array("\r\n", "\r"), "\n", $pee); // cross-platform newlines
		if ( strpos($pee, '<object') !== false ) {
			$pee = preg_replace('|\s*<param([^>]*)>\s*|', "<param$1>", $pee); // no pee inside object/embed
			$pee = preg_replace('|\s*</embed>\s*|', '</embed>', $pee);
		}
		$pee = preg_replace("/\n\n+/", "\n\n", $pee); // take care of duplicates
		// make paragraphs, including one at the end
		$pees = preg_split('/\n\s*\n/', $pee, -1, PREG_SPLIT_NO_EMPTY);
		$pee = '';
		foreach ( $pees as $tinkle )
			$pee .= '<p>' . trim($tinkle, "\n") . "</p>\n";
		$pee = preg_replace('|<p>\s*</p>|', '', $pee); // under certain strange conditions it could create a P of entirely whitespace

		return $pee;
	}

	static public function covers($data, $min, $max)
	{
		if (!isset($data['album']['covers']))
		{
			return array();
		}

		$covers = $data['album']['covers'];

		if ($min && count($covers) < $min)
		{
			if (isset($data['content']))
			{
				$pool = $data['content'];
			}
			else
			{
				$content = self::api('/albums/' . $data['album']['id'] . '/content');
				$pool = $content['content'];
			}

			if (count($pool) > count($covers))
			{
				foreach($pool as $content)
				{
					if (!in_array($content, $covers))
					{
						$covers[] = $content;
					}

					if (count($covers) >= $min)
					{
						break;
					}
				}
			}
		}

		if ($max)
		{
			$covers = array_slice($covers, 0, $max);
		}

		return $covers;
	}
}