<?php

	class Tag {

		public $tokenize 		= false;
		public $untokenize_on_else = false;
		protected $parameters	= array();
		protected $allows_close	= false;
		protected $attr_parse_level = 0;

		function __construct($parameters = array())
		{
			$this->parameters = $parameters;
		}

		public function attr_replace($matches)
		{
			return '" . ' . $this->field_to_keys($matches[1]) . '. "';
		}

		public function out_cb($matches)
		{
			return '" . Koken::out(\'' . trim(str_replace("'", "\\'", $matches[1])) . '\') . "';
		}

		public function attr_parse($val, $wrap = false)
		{
			$pattern = '/\{\{\s*([^\}]+)\s*\}\}/';
			if (preg_match($pattern, $val))
			{
				$o = preg_replace_callback($pattern, array($this, 'out_cb'), $val);
				if ($wrap)
				{
					return '<?php echo "' . $o. '" ?>';
				}
				else
				{
					return $o;
				}
			}
			else
			{
				$o =  preg_replace_callback('/\{([a-z_.0-9]+)\}/', array($this, 'attr_replace'), $val);
				if ($wrap)
				{
					return '<?php echo "' . $o. '" ?>';
				}
				else
				{
					return $o;
				}
			}
		}

		public function field_to_keys($param, $variable = 'value', $token_index = false)
		{

			if (!$token_index)
			{
				$token_index = $this->attr_parse_level;
			}

			$bits = explode('|', $param);

			$options = array();

			foreach($bits as $param)
			{
				$param = trim($param);
				$prefix = $postfix = '';

				if (isset($this->parameters[$param]))
				{
					$p = $this->parameters[$param];
				}
				else
				{
					$p = $param;
				}

				preg_match('/^(site|location|profile|source|settings|routed_variables|page_variables|pjax|labels)/', $p, $global_matches);

				if (count(Koken::$tokens) === 0 && count($global_matches) === 0)
				{
					return false;
				}

				if (count($global_matches))
				{
					$f = explode('.', $p);
					array_shift($f);
					$prefix = 'Koken::$' . $global_matches[1];
				}
				else if (in_array($p, Koken::$template_variable_keys))
				{
					return "Koken::\$template_variables['$p']";
				}
				else if ($p === 'value')
				{
					return "\$value" . Koken::$tokens[$token_index];
				}
				else
				{
					$pre = '';
					while (strpos($p, '_parent.') !== false)
					{
						$p = substr($p, strlen('_parent.'));
						$token_index++;
					}
					$p = str_replace('.first', '[0]', $p);
					if (strpos($p, '.last') !== false)
					{
						$partial = substr($p, 0, strpos($p, '.last'));
						$p = str_replace('.last', '[count(' . $this->field_to_keys($partial, $variable, $token_index) . ') - 1]', $p);
					}
					if (strpos($p, '.length') !== false)
					{
						$p = substr($p, 0, strpos($p, '.length'));
						$postfix = ')';
						$pre = 'count(';
					}
					$f = explode('.', $p);
					$prefix = $pre . "\$$variable" . Koken::$tokens[$token_index];
				}

				$final = array();
				foreach($f as $v)
				{
					$bits = explode('[', $v);
					$str = "['" . array_shift($bits) . "']";
					if (count($bits))
					{
						$str .= '[' . join('[', $bits);
					}

					$final[] = $str;
				}
				$options[] = "{$prefix}" . join("", $final) . $postfix;
			}
			if (count($options) === 1)
			{
				return $options[0];
			}
			else
			{
				return "empty($options[0]) ? $options[1] : $options[0]";
			}
		}

		public function do_else()
		{
			return '<?php else: ?>';
		}

		function close()
		{
			if ($this->allows_close)
			{
				return <<<DOC
<?php endif; ?>
DOC;
			}
		}

		// $source_options are passed in by non koken:load tags, in order to pass in specific source related api options
		// Right now, this only means koken:pulse
		function load_params($source_options = false)
		{
			$defaults = array(
				'model' 			=> 'content',
				'list'				=> false,
				'filters'			=> array(),
				'id_from_url' 		=> false,
				'paginate_from_url' => false,
				'api'				=> array(),
				'load_content'		=> false,
				'id'				=> false,
				'id_prefix'			=> '',
				'tree' 				=> false,
				'type' 				=> false,
				'source'			=> false,
				'archive'			=> false
			);

			$featured = false;

			if (is_array($source_options))
			{
				$defaults['api'] = $source_options;
			}
			else
			{
				foreach($this->parameters as $key => $val)
				{
					if (!isset($defaults[$key]))
					{
						$defaults['api'][$key] = $val;
					}
				}
			}

			$custom = false;

			if (isset($this->parameters['source']))
			{
				$source = array('type' => $this->parameters['source']);
				$defaults['list'] = substr( strrev($this->parameters['source']), 0, 1 ) === 's';
				$defaults['model'] = rtrim( $this->parameters['source'], 's' ) . 's';
				$defaults['filters'] = $this->parameters['filters'];
				$custom = true;
				if (isset($this->parameters['tree']))
				{
					$defaults['tree'] = true;
				}
			}
			else if (Koken::$source)
			{
				$source = Koken::$source;
				$defaults['list'] = substr( strrev(Koken::$source['type']), 0, 1 ) === 's';
				$defaults['model'] = rtrim( Koken::$source['type'], 's' ) . 's';
				$defaults['filters'] = is_array(Koken::$source['filters']) ? Koken::$source['filters'] : array();
			}

			if (strpos($defaults['model'], 'featured_') === 0)
			{
				$bits = explode('_', $defaults['model']);
				$defaults['model'] = 'features';
				$defaults['id'] = rtrim($bits[1], 's');
				$defaults['list'] = true;
			}

			if ($defaults['model'] === 'contents')
			{
				$defaults['model'] = 'content';
			}
			else if ($defaults['model'] === 'essays' || $defaults['model'] === 'pages')
			{
				if ($defaults['list'])
				{
					$defaults['api']['type'] = trim($defaults['model'], 's');
				}
				$defaults['model'] = 'text';
			}
			else if ($defaults['model'] === 'categorys')
			{
				$defaults['model'] = 'categories';
			}
			else if ($defaults['model'] === 'sets')
			{
				$defaults['model'] = 'albums';
			}

			if (is_array($defaults['filters']))
			{
				foreach($defaults['filters'] as $filter)
				{
					if (strpos($filter, '=') !== false)
					{
						$bits = explode('=', $filter);
						if ($bits[0] === 'id')
						{
							$__id = substr($bits[1], 0, 1) === '"' ? $bits[1] : urlencode($bits[1]);
						}
						else if ($bits[0] === 'members')
						{
							$this->parameters['type'] = $bits[1];
						}
						else
						{
							if (strpos($bits[1], '!') === 0 || strpos($bits[0], '!') !== false)
							{
								$bits[1] = str_replace('!', '', $bits[1]);
								$bits[0] = str_replace('!', '', $bits[0]) . '_not';
							}
							if (strpos($bits[0], 'category') === 0 && (!is_numeric($bits[1]) && strpos($bits[1], '" . Koken') !== 0))
							{
								$bits[1] = '" . Koken::$categories[\'' . strtolower($bits[1]) . '\'] . "';
							}
							$defaults['api'][$bits[0]] = $bits[1];
						}
					}
					else
					{
						if (substr($filter, 0, 1) === '!')
						{
							$filter = str_replace('!', '', $filter);
							$val = 0;
						}
						else
						{
							$val = 1;
						}

						if ($filter === 'featured' && $val === 1)
						{
							$featured = true;
						}
						else
						{
							$defaults['api'][$filter] = $val;
						}

					}
				}
			}

			if ($source['type'] === 'tags' && isset($__id))
			{
				$defaults['id'] = $__id;
			}

			if ($source['type'] === 'archives' && !$custom)
			{
				if (isset(Koken::$routed_variables['month']))
				{
					$defaults['api']['month'] = Koken::$routed_variables['month'];
				}
				else if (isset($defaults['api']['month']))
				{
					Koken::$routed_variables['month'] = $defaults['api']['month'];
				}
				if (isset(Koken::$routed_variables['year']))
				{
					$defaults['api']['year'] = Koken::$routed_variables['year'];
				}
				else if (isset($defaults['api']['year']))
				{
					Koken::$routed_variables['year'] = $defaults['api']['year'];
				}
			}

			if ($source['type'] === 'archive' && !$custom)
			{
				if ($this->parameters['type'] === 'essays')
				{
					$defaults['api']['type'] = 'essay';
					$defaults['model'] = 'text';
				}
				else
				{
					$defaults['model'] = $this->parameters['type'] === 'contents' ? 'content' : $this->parameters['type'];
				}
				$defaults['list'] = true;
				if (isset(Koken::$routed_variables['month']))
				{
					$defaults['api']['month'] = Koken::$routed_variables['month'];
				}
				else if (isset($defaults['api']['month']))
				{
					Koken::$routed_variables['month'] = $defaults['api']['month'];
				}
				if (isset(Koken::$routed_variables['year']))
				{
					$defaults['api']['year'] = Koken::$routed_variables['year'];
				}
				else if (isset($defaults['api']['year']))
				{
					Koken::$routed_variables['year'] = $defaults['api']['year'];
				}

				$defaults['archive'] = 'date';
			}

			if (!$defaults['list'])
			{
				if ($source['type'] === 'tag' && !$custom)
				{
					if ($this->parameters['type'] === 'essays')
					{
						$defaults['api']['type'] = 'essay';
						$defaults['model'] = 'text';
						$defaults['api'] = array_merge(array('order_by' => 'published_on', 'state' => 'published'), $defaults['api'] );
					}
					else
					{
						$defaults['model'] = $this->parameters['type'] === 'contents' ? 'content' : $this->parameters['type'];
					}
					$defaults['list'] = true;
					$defaults['id_prefix'] = 'tags:';
					$defaults['paginate_from_url'] = true;
					$defaults['archive'] = 'tag';
				}

				if (isset($__id))
				{
					$defaults['id'] = $__id;
					if (!$custom)
					{
						Koken::$routed_variables['id'] = $__id;
					}
				}
				else
				{
					$defaults['id_from_url'] = true;
				}

				if ($defaults['model'] === 'albums')
				{
					$defaults['list'] = true;
					$defaults['paginate_from_url'] = true;
				}
			}

			if ($defaults['list'])
			{

				if ($defaults['model'] === 'albums')
				{
					$defaults['api'] = array_merge(array('include_empty' => '0'), $defaults['api'] );
				}
				else if ($defaults['model'] === 'text')
				{
					$defaults['api'] = array_merge(array('order_by' => 'published_on', 'state' => 'published'), $defaults['api'] );
				}

				$defaults['paginate_from_url'] = true;

			}

			$options = $defaults;

			$paginate = $options['paginate_from_url'] && $options['list'] && !$custom;

			$url = '/' . $options['model'] . ( $featured ? '/featured' : '' );

			if ($options['tree'] && $options['model'] === 'albums')
			{
				$url .= '/tree';
			}

			if ($options['id_from_url'] || $options['id'])
			{
				if ($options['id_from_url'])
				{
					if (empty($options['id_prefix']))
					{
						$slug_prefix = 'slug:';
					}
					else
					{
						$slug_prefix = '';
					}
					$url .= "/{$options['id_prefix']}\" . ( isset(Koken::\$routed_variables['id']) ? urlencode(Koken::\$routed_variables['id']) : '$slug_prefix' . urlencode(Koken::\$routed_variables['slug']) ) . \"";
				}
				else if ($options['id'])
				{
					$url .= "/{$options['id_prefix']}\" . urlencode(\"{$options['id']}\") . \"";
				}

				if (!isset($defaults['api']['context']))
				{
					if ($options['model'] === 'content')
					{
						$url .= '" . Koken::context_parameters() . "';
					}
					else if ($options['model'] === 'albums' && $options['list'])
					{
						$url .= '/content" . Koken::context_parameters("albums") . "';
					}
				}
			}

			if (isset($this->parameters['type']) && !$options['list'] && $options['model'] === 'categories')
			{
				$url .= '/' . ($this->parameters['type'] === 'contents' ? 'content' : $this->parameters['type']);
				$options['list'] = true;
				$paginate = !$custom;
				$options['archive'] = 'category';
			}

			foreach($options['api'] as $key => $value)
			{
				if (!is_numeric($value) && $value == 'true')
				{
					$value = 1;
				}
				else if (!is_numeric($value) && $value == 'false')
				{
					$value = 0;
				}
				else
				{
					$value = $this->attr_parse($value);
				}
				$url .= "/$key:\" . urlencode(\"$value\") . \"";
			}

			$collection_name = $options['model'] === 'contents' ? 'content' : $options['model'];

			return array(
				$url, $options, $collection_name, $paginate
			);
		}

	}