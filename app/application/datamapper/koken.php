<?php

class DMZ_Koken {

	private $urls = false;
	private $url_data = false;
	private $segments = false;
	private $base = false;
	private $tz = false;

	private function get_base()
	{
		if (!$this->base)
		{
			$s = new Setting;
			$s->where('name', 'site_url')->get();
			if ($s->value === 'default')
			{
				$CI =& get_instance();
				$koken_url_info = $CI->config->item('koken_url_info');
				$this->base = $koken_url_info->base;
			}
			else
			{
				$this->base = 'http://' . $_SERVER['HTTP_HOST'] . $s->value;
			}
		}
		return rtrim($this->base, '/') . ( KOKEN_REWRITE ? '' : '/index.php?');
	}

	private function get_tz()
	{
		if (!$this->tz)
		{
			$s = new Setting;
			$s->where('name', 'site_timezone')->get();
			$this->tz = $s->value;
		}
		return $this->tz;
	}

	private function form_urls()
	{
		$d = new Draft;
		$context = defined('DRAFT_CONTEXT') ? DRAFT_CONTEXT : false;
		$path = '';

		if (!$context)
		{
			$d->where('current', 1)->get();
			$path = $d->path;
		}
		else if (is_numeric(DRAFT_CONTEXT))
		{
			$d->get_by_id(DRAFT_CONTEXT);
			$path = $d->path;
		}
		else
		{
			$path = DRAFT_CONTEXT;
		}

		list($this->urls,$this->url_data,,$this->segments) = $d->setup_urls(FCPATH . 'storage' . DIRECTORY_SEPARATOR . 'themes' . DIRECTORY_SEPARATOR . $path . DIRECTORY_SEPARATOR);
	}

	private function get_url($model)
	{
		if (!$this->urls)
		{
			$this->form_urls();
		}

		return isset($this->urls[$model]) ? $this->urls[$model] : false;
	}

	function url($object, $options = array())
	{
		$model = $object->model;
		$tail = '';
		$content = false;

		if ($model === 'text')
		{
			$model = $object->page_type > 0 ? 'page' : 'essay';
		}
		if ($model === 'album' && $object->album_type == 2)
		{
			$model = 'set';
		}
		if ($model === 'content' && isset($options['album']) && $options['album'])
		{
			if ($options['album']->album_type == 2)
			{
				$actual_album = new Album;
				$actual_album
					->where_related('content', 'id', $object->id)
					->where('left_id >=', $options['album']->left_id)
					->where('right_id <=', $options['album']->right_id)
					->get();
				$options['album'] = $actual_album;
			}

			$model = 'album';
			$content_template = $this->get_url('content');
			$content_url = $this->url_data['content']['url'];
			$tail = $this->segments['content'] . '/' . ( strpos($content_url, 'slug') === false ? ':content_id' : ':content_slug' ) . '/';
			if (!$content_template)
			{
				$tail .= 'lightbox/';
			}
			$content = $object;
			$object = $options['album'];
			$date = $options['album']->created_on;
		}
		else
		{
			$date = $options['date']['timestamp'];
		}

		$template = $this->get_url($model);

		if (!$template)
		{
			if ($model === 'content')
			{
				$template = '/' . $this->segments['content'] . '/:slug/lightbox/';
				$tail = '';
			}
			else
			{
				return false;
			}
		}

		$template .= $tail;

		$data = array();

		if ( (isset($object->visibility) && (int) $object->visibility === 1) || (isset($object->listed) && $object->listed < 1) )
		{
			$data['id'] = $data['slug'] = $object->internal_id;
		}
		else
		{
			$data = array(
				'id' => $object->id,
				'slug' => $object->slug
			);
		}

		if (isset($options['date']))
		{
			date_default_timezone_set($this->get_tz());
			$data['year'] = date('Y', $date);
			$data['month'] = date('m', $date);
			$data['day'] = date('d', $date);
			date_default_timezone_set('UTC');
		}

		if ($content)
		{
			if ((int) $content->visibility === 1)
			{
				$data['content_id'] = $data['content_slug'] = $content->internal_id;
			}
			else
			{
				$data['content_id'] = $content->id;
				$data['content_slug'] = $content->slug;
			}
		}

		preg_match_all('/:([a-z_]+)/', $template, $matches);

		foreach($matches[1] as $magic)
		{
			$template = str_replace(':' . $magic, urlencode($data[$magic]), $template);
		}

		return array( $template, $this->get_base() . $template );
	}

	function prepare_for_output($object, $options, $exclude = array(), $booleans = array(), $dates = array(), $strings = array())
	{
		if (isset($options['fields']))
		{
			$fields = explode(',', $options['fields']);
		}
		else
		{
			$fields = $object->fields;
		}
		$fields = array_diff($fields, $exclude);
		$public_fields = array_intersect($object->fields, $fields);
		$data = array();
		foreach($public_fields as $name)
		{
			$val = $object->{$name};
			if (in_array($name, $booleans))
			{
				$val = (bool) $val;
			}
			else if (in_array($name, $dates))
			{
				if (is_numeric($val))
				{
					$val = array(
							'datetime' => date('Y/m/d G:i:s', $val),
							'timestamp' => (int) $val
						);
				}
				else
				{
					$val = array('datetime' => null, 'timestamp' => null);
				}
			}
			else if (is_numeric($val) && !in_array($name, $strings))
			{
				$val = (float) $val;
			}
			$data[$name] = $val;
		}
		return array($data, $fields);
	}

	function paginate($object, $options)
	{
		$final = array();
		if ($options['limit'])
		{
			$total = $object->get_clone()->count();
			if (isset($options['cap']) && $options['cap'] < $total)
			{
				$total = $options['cap'];
			}
			$final['page'] = (int) $options['page'];
			$final['pages'] = ceil($total/$options['limit']);
			$final['per_page'] = min((int) $options['limit'], $total);
			$final['total'] = $total;
			if ($options['page'] == 1)
			{
				$start = 0;
			}
			else
			{
				$start = ($options['limit']*($options['page']-1));
			}
			$object->limit($options['limit'], $start);
		}
		else
		{
			$final = array(
				'page' => 1,
				'pages' => 1
			);
		}
		return $final;
	}
}

/* End of file pagination.php */
/* Location: ./application/datamapper/pagination.php */