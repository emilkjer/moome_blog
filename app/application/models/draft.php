<?php

class Draft extends DataMapper {

	/**
	 * Constructor: calls parent constructor
	 */
	function __construct($id = NULL)
	{
		parent::__construct($id);
	}

	function _push($item, &$array, $type, $level)
	{
		if (!in_array($item, $array))
		{
			if ($level === 1)
			{
				$array[] = $item;
			}
			else
			{
				$array[] = array(
					'path' => $item['path'],
					'template' => "redirect:$type"
				);
			}
		}

	}

	function setup_urls($template_path)
	{
		$this->load->helper(array('url', 'text', 'inflector'));
		$url = new Url;
		$url_list = $url->order_by('id DESC')->get_iterated();
		$urls = $data = $routes = array();
		$level = 0;
		$top_segments = array();

		foreach($url_list as $config)
		{
			$level++;

			$config = unserialize($config->data);
			$segments = $_data = array();
			$content_regex = false;

			foreach($config as $u) {
				$d = $u['data'];
				if (is_array($d) && !isset($data[$u['type']]))
				{
					$data[$u['type']] = $d;
				}

				$_data[$u['type']] = $d;

				if (!isset($segments[$u['type']]) && isset($d['plural']))
				{
					$segment = strtolower(
									url_title(
										convert_accented_characters($d['plural']), 'dash'
									)
								);

					if (preg_match('/[^a-z\-]+/', $segment) || empty($segment))
					{
						$segment = $u['type'] === 'content' ? 'content' : plural($u['type']);
					}

					$segments[$u['type']] = $segment;
				}

				if ($u['type'] === 'content' && !$content_regex && isset($d['url']))
				{
					$content_regex = strpos($d['url'], 'slug') === false ? ':content_id' : ':content_slug';
				}
			}

			if ($level === 1)
			{
				$top_segments = $segments;
			}

			$supported = array();
			$supported_raw = array();
			if (file_exists($template_path . 'archive.contents.lens'))
			{
				$supported[] = $segments['content'];
				$supported_raw[] = 'contents';
			}
			if (file_exists($template_path . 'archive.essays.lens'))
			{
				$supported[] = $segments['essay'];
				$supported_raw[] = 'essays';
			}
			if (file_exists($template_path . 'archive.albums.lens'))
			{
				$supported[] = $segments['album'];
				$supported_raw[] = 'albums';
			}

			foreach($config as $u)
			{
				$has_detail = file_exists($template_path . $u['type'] . '.lens');

				$type = $u['type'];
				$type_plural = "{$type}s";
				$source = false;
				$template = '/' . $segments[$type];
				$type_data = $_data[$type === 'set' ? 'album' : $type];

				if ($has_detail && isset($type_data['url']))
				{
					$url = $type_data['url'];
					if (strpos($url, 'date') !== false)
					{
						$template .= '/:year/:month';
					}
					if (strpos($url, 'slug') !== false)
					{
						$template .= '/:slug';
					}
					else
					{
						$template .= '/:id';
					}
				}

				if ($has_detail && !isset($urls[$u['type']]))
				{
					$urls[$u['type']] = rtrim($template, '/') . '/';
				}

				if ($has_detail)
				{
					if (!isset($urls[$u['type']]))
					{
						$urls[$u['type']] = $template;
					}

					if ($u['type'] === 'content' || $u['type'] === 'album')
					{
						$template .= '(?P<lightbox>/lightbox)?/';
					}
					else
					{
						$template .= '/';
					}

					$arr = array(
						'path' => $template,
						'template' => $type,
						'source' => $source,
						'vars' => false,
						'section' => true
					);

					if ($u['type'] === 'album')
					{
						$parts = explode(' ', $data['content']['album_order']);
						$arr['filters'] = array(
							"order_by={$parts[0]}",
							"order_direction={$parts[1]}"
						);

						if (file_exists($template_path . 'content.lens'))
						{
							$album_content_template = str_replace('(?P<lightbox>/lightbox)?', '', $template) . $segments['content'] . '/' . $content_regex . '(?P<lightbox>/lightbox)?/';
						}
						else
						{
							$album_content_template = str_replace('(?P<lightbox>/lightbox)?', '', $template) . $segments['content'] . '/' . $content_regex . '/(?P<lightbox>lightbox)/';
						}

						$album_content_template = str_replace(':id', ':album_id', $album_content_template);
						$album_content_template = str_replace(':slug', ':album_slug', $album_content_template);
						$album_content_template = str_replace(':content_id', ':id', $album_content_template);
						$album_content_template = str_replace(':content_slug', ':slug', $album_content_template);
						$album_content_route = $arr;
						$album_content_route['path'] = $album_content_template;
						$album_content_route['template'] = 'content';
						$album_content_route['source'] = 'content';
						$album_content_route['filters'] = array();

						$this->_push($album_content_route, $routes, $u['type'], $level);
					}
					else if ($u['type'] === 'set')
					{
						$parts = explode(' ', $data['album']['order']);
						$arr['filters'] = array(
							"order_by={$parts[0]}",
							"order_direction={$parts[1]}"
						);
					}
					else
					{
						$arr['filters'] = false;
					}

					$this->_push($arr, $routes, $u['type'], $level);
				}
				else if ($u['type'] === 'content')
				{
					$arr = array(
						'path' => '/' . $segments['content'] . '/:slug/(?P<lightbox>lightbox)/',
						'template' => 'content',
						'source' => 'content',
						'vars' => false,
						'section' => true
					);

					$this->_push($arr, $routes, $u['type'], $level);
				}

				if (in_array($u['type'], array('content', 'album', 'essay')) && in_array($type_plural, $supported_raw))
				{
					$archive_template = '/' . $segments[$type] . '/:year(?:/:month)?/';

					if ($level === 1)
					{
						$urls['archive_' . $type_plural] = $archive_template;
					}

					$this->_push(array(
						'path' => $archive_template,
						'template' => 'archive.' . $type_plural,
						'source' => 'archive',
						'filters' => array( "members=$type_plural"),
						'vars' => false,
						'section' => true
					), $routes, 'archive_' . $type_plural, $level);

					$tag_template = '/' . $segments[$type] . '/' . $segments['tag'] . '/:slug/';

					if ($level === 1)
					{
						$urls['tag_' . $type_plural] = $tag_template;
					}

					$this->_push(array(
						'path' => $tag_template,
						'template' => 'archive.' . $type_plural,
						'source' => 'tag',
						'filters' => array( "members=$type_plural"),
						'vars' => false,
						'section' => true
					), $routes, 'tag_' . $type_plural, $level);

					$category_template = '/' . $segments[$type] . '/' . $segments['category'] . '/:slug/';

					if ($level === 1)
					{
						$urls['category_' . $type_plural] = $category_template;
					}

					$this->_push(array(
						'path' => $category_template,
						'template' => 'archive.' . $type_plural,
						'source' => 'category',
						'filters' => array("members=$type_plural"),
						'vars' => false,
						'section' => true
					), $routes, 'category_' . $type_plural, $level);
				}

				if (in_array($u['type'], array('category', 'favorite', 'tag', 'content', 'album', 'essay', 'archive')))
				{
					$type = $u['type'] === 'category' ? 'categories' : $type_plural;

					$has_index = file_exists($template_path . $type . '.lens');

					if ($has_index)
					{
						$index_path = '/' . $segments[$u['type']] . '/';

						if ($type === 'archives')
						{
							$index_path = rtrim($index_path, '/') . '(?:/:year(?:/:month)?)?/';
						}

						$arr = array(
							'path' => $index_path,
							'template' => $type,
							'source' => in_array($type, array('archives', 'tags', 'categories')) ? $type : false,
							'vars' => false,
							'section' => true
						);

						if (isset($data[$u['type']]['order']))
						{
							$parts = explode(' ', strtolower($data[$u['type']]['order']));
							$arr['filters'] = array(
								"order_by={$parts[0]}",
								"order_direction={$parts[1]}"
							);
						}
						else
						{
							$arr['filters'] = false;
						}

						$this->_push($arr, $routes, $type, $level);

						if (!isset($urls[$type]))
						{
							$urls[$type] = $index_path;
						}
					}
				}
			}
		}

		$routes[] = array(
			'path' => '/content/:id/in_album/:album_id(?P<lightbox>/lightbox)?/',
			'template' => 'redirect:content'
		);

		$routes[] = array(
			'path' => '/' . $top_segments['set'] . '/',
			'template' => 'redirect:soft:albums'
		);

		return array($urls, $data, $routes, $top_segments);
	}

	function init_draft_nav($refresh = true)
	{
		if ($refresh === 'nav')
		{
			$this->data = json_decode($this->data, true);
		}
		else
		{
			$this->data = array();
		}

		$ds = DIRECTORY_SEPARATOR;
		$template_path = FCPATH . 'storage' . $ds . 'themes' . $ds;
		$theme_root = $template_path . $this->path . $ds;
		$template_info = json_decode( file_get_contents( $theme_root . 'info.json'), true );

		list($urls, $data, $routes) = $this->setup_urls($theme_root);

		$this->data['navigation'] = array('items' => array());
		$this->data['navigation']['groups'] = $groups = array();

		$defaults = array('albums', 'contents', 'essays');

		$used_autos = array();

		$user = new User;
		$user->get();

		$front = array(
			'label' => 'Home', 'front' => true
		);

		if (isset($template_info['default_front_page']) && in_array($template_info['default_front_page'], array('albums', 'contents', 'essays', 'archives')) && isset($urls[$template_info['default_front_page']]))
		{
			$defaults = array_diff($defaults, array($template_info['default_front_page']));
			$front['path'] = $urls[$template_info['default_front_page']];
			$front['auto'] = $template_info['default_front_page'];
			$used_autos[] = $template_info['default_front_page'];
		}
		else
		{
			$front['path'] = '/home/';
		}

		$this->data['navigation']['items'][] = $front;

		foreach($defaults as $default)
		{
			if (file_exists($theme_root . $default . '.lens'))
			{
				$used_autos[] = $default;
				$this->data['navigation']['items'][] = array(
					'auto' => $default
				);
			}
		}

		if (isset($template_info['navigation_groups']))
		{
			foreach($template_info['navigation_groups'] as $key => $info)
			{
				$items = array();
				if (isset($info['defaults']))
				{
					foreach($info['defaults'] as $def)
					{

						if (is_array($def))
						{
							$def['path'] = $def['url'];
							unset($def['url']);
							$items[] = $def;
						}
						else
						{
							if ($def === 'front')
							{
								$items[] = $front;
							}
							else
							{
								if (in_array($def, array('twitter', 'facebook', 'gplus')))
								{
									$def = $def === 'gplus' ? 'google' : $def;
									if (!empty($user->{$def}))
									{
										$items[] = array(
											'auto' => 'profile',
											'id' => $def
										);
									}
								}
								else if (file_exists($theme_root . $def . '.lens'))
								{
									$items[] = array(
										'auto' => $def
									);
								}
							}
						}
					}
				}

				$this->data['navigation']['groups'][] = array(
					'key' => $key,
					'label' => $info['label'],
					'items' => $items
				);
			}
		}

		if ($refresh === 'nav')
		{
			$this->data['routes'] = $routes;
		}
		else
		{
			$p = new Draft;
			$p->where('current', 1)->get();
			$pub_data = json_decode( $p->live_data, true);

			foreach($pub_data['navigation']['items'] as $item)
			{
				if (
						(!isset($item['front']) || !$item['front'])
						&& !in_array($item, $this->data['navigation']['items'])
						&& (isset($item['custom']) || (isset($item['auto']) && isset($urls[$item['auto']]) && !in_array($item['auto'], $used_autos)))
					)
				{
					$this->data['navigation']['items'][] = $item;
				}
			}
		}

		$this->data = json_encode( $this->data );
	}

}

/* End of file category.php */
/* Location: ./application/models/draft.php */