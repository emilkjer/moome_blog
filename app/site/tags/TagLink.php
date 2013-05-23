<?php

	class TagLink extends Tag {

		protected $allows_close = true;

		function generate()
		{
			$attr = array();

			if (isset($this->parameters['bind_to_key']))
			{
				$this->parameters['data-bind-to-key'] = $this->parameters['bind_to_key'];
				unset($this->parameters['bind_to_key']);
			}

			if (isset($this->parameters['to']) && strpos($this->parameters['to'], 'archive_') !== false)
			{
				$this->parameters['type'] = str_replace('archive_', '', $this->parameters['to']);
				unset($this->parameters['to']);
			}

			if (isset($this->parameters['url']))
			{
				$this->parameters['url'] = '"' . $this->attr_parse( $this->parameters['url'] ) . '"';
			}
			else if (isset($this->parameters['to']))
			{
				$this->parameters['url'] = $this->parameters['to'] === 'front' ? '"/"' : 'Koken::$location[\'urls\'][\'' . $this->parameters['to'] . '\']';
				foreach($this->parameters as $key => $val)
				{
					if (strpos($key, 'filter:') === false) continue;
					$clean_key = str_replace('filter:', '', $key);
					unset($this->parameters[$key]);
					$this->parameters['url'] = "str_replace(':$clean_key', urlencode('" . $val . "'), " . $this->parameters['url'] . ')';

				}
				unset($this->parameters['to']);
			}
			else if (isset($this->parameters['data']))
			{
				$token = $this->field_to_keys('data');
				if (preg_match("/\.context\.(neighbors|previous|next)/", $this->parameters['data']))
				{
					$bits = explode('.context.', $this->parameters['data']);
					$album = $bits[0] . '.context.album';
					$k = $this->field_to_keys($album);
					$parent_token = "\$__context = isset($k) ? $k : false;";
				}
				unset($this->parameters['data']);
			}
			else
			{
				$token = '$value' . Koken::$tokens[0];
				$check_token = isset(Koken::$tokens[1]) ? Koken::$tokens[1] : Koken::$tokens[0];

				$c = '$value' . $check_token . "['context']['album']";
				$p = '$value' . $check_token . "['album']";
				$parent_token = "\$__context = isset($c) ? $c : ( isset($p) ? $p : false );";
			}

			if (isset($this->parameters['type']))
			{
				$to = "{$token}['__koken__override'] = {$token}['__koken__'] . '_{$this->parameters['type']}';";
				unset($this->parameters['type']);
			}
			else
			{
				$to = '';
			}

			if (isset($this->parameters['echo']))
			{
				$echo = true;
				unset($this->parameters['echo']);
				$this->allows_close = false;
			}
			else
			{
				$echo = false;
			}

			foreach($this->parameters as $key => $val)
			{
				if ($key === 'url') continue;
				$attr[] = $key . '=\"' . $this->attr_parse($val) . '\"';
			}

			if (count($attr) > 0)
			{
				$attr = ' ' . join(' ', $attr);
			}
			else
			{
				$attr = '';
			}

			if (!isset($parent_token))
			{
				$parent_token = '$__context = false;';
			}

			if (isset($this->parameters['lightbox']))
			{
				$lightbox = 'true';
			}
			else
			{
				$lightbox = 'false';
			}

			$out = "<?php \$attr = \"$attr\";";

			if (isset($token))
			{
				$out .= <<<DOC
	$parent_token
	$to
	\$__url = Koken::form_link($token, \$__context, $lightbox);
DOC;
			}
			else
			{
				$out .= '$__url = ' . $this->parameters['url'] . ';';
			}

			$out .= <<<DOC
	if (\$__url === Koken::\$navigation_home_path)
	{
		\$__url = '/';
	}
	if ((\$__url === '/' && Koken::\$location['here'] === '/') || preg_match('/^' . str_replace('/', '\\/', \$__url) . '(\\/.*)?$/', Koken::\$location['here']))
	{
		if (strpos(\$attr, 'class="') === false)
		{
			\$attr .= ' class="k-nav-current"';
		}
		else
		{
			\$attr = preg_replace('/class="([^"]+)"/', 'class="$1 k-nav-current"', \$attr);
		}
	}
	\$__url = strpos(\$__url, '/') === 0 ? Koken::\$location['root'] . \$__url  . ( Koken::\$preview ? '&amp;preview=' . Koken::\$preview : '' ) : \$__url;
DOC;

			if ($echo)
			{
				$out .= "echo ( isset(\$_SERVER['HTTPS']) && \$_SERVER['HTTPS'] === 'on' ? 'https' : 'http' ) . '://' . \$_SERVER['HTTP_HOST'] . \$__url; ?>";
			}
			else
			{
				$out .= <<<DOC
?><a href="<?php echo \$__url; ?>"<?php echo \$attr; ?>>
DOC;
			}

			return $out;
		}

		function close()
		{
			return $this->allows_close ? '</a>' : '';
		}
	}