<?php

class Text extends DataMapper {

	var $table = 'text';

	var $has_many = array(
		'category' => array(
			'auto_populate' => true
		),
		'album' => array(
			'auto_populate' => true,
			'order_by' => 'title'
		)
	);

	var $validation = array(
		'internal_id' => array(
			'label' => 'Internal id',
			'rules' => array('internalize', 'required')
		),
		'page_type' => array(
			'rules' => array('validate_type')
		),
		'slug' => array(
			'rules' => array('slug', 'required')
		),
		'created_on' => array(
			'rules' => array('validate_created_on')
		),
		'tags' => array(
			'rules' => array('format_tags')
		),
		'title' => array(
			'get_rules' => array('readify')
		),
		'content' => array(
			'rules' => array('format_content')
		),
		'published' => array(
			'rules' => array('re_slug')
		)
 	);

	function _format_content()
	{
		$this->content = rawurldecode($this->content);
		return true;
	}

	function _validate_type()
	{
		$values = array('essay', 'page');
		if (in_array($this->page_type, $values))
		{
			$this->page_type = array_search($this->page_type, $values);
		}
		else
		{
			return false;
		}
	}

	function _validate_created_on()
	{
		$val = $this->created_on;
		if (is_numeric($val) && strlen($val) === 10)
		{
			return true;
		}
		return false;
	}

	/**
	 * Create internal ID if one is not present
	 */
	function _internalize($field)
	{
		$this->{$field} = koken_rand();
	}

	function _re_slug($field)
	{
		if ($this->published > 0)
		{
			$this->_slug('slug');
		}
	}

	function _slug($field)
	{
		$this->load->helper(array('url', 'text', 'string'));
		$slug = reduce_multiples(
					strtolower(
						url_title(
							convert_accented_characters($this->title), 'dash'
						)
					)
				, '-', true);

		if (empty($slug))
		{
			$t = new Text;
			$max = $t->select_max('id')->get();
			$slug = $max->id + 1;
		}

		if (is_numeric($slug))
		{
			$slug = "$slug-1";
		}

		$page_type = is_numeric($this->page_type) ? $this->page_type : 0;
		while($this->where('slug', $slug)->where('id !=', $this->id ? $this->id : 'NULL')->where('page_type', $page_type)->count() > 0)
		{
			$slug = increment_string($slug, '-');
		}

		$this->slug = $slug;
	}

	/**
	 * Constructor: calls parent constructor
	 */
    function __construct($id = NULL)
	{
		parent::__construct($id);
    }

	function _readify()
	{
		if (isset($this->tags) && !empty($this->tags))
		{
			$this->tags = explode(',', trim($this->tags, ','));
		}
		else
		{
			$this->tags = array();
		}
	}

	function _format_tags()
	{
		if (empty($this->tags))
		{
			$this->tags = null;

			if (isset($this->old_tags))
			{
				$t = new Tag();
				$t->manage(array(), $this->old_tags, 'text');
			}
		}
		else
		{
			$tags = $this->tags;
			// Strip unwanted characters
			$tags = preg_replace('/[^\w\p{L}0-9\-\._\s,"]+/u', '', $tags);
			// Collapse multiple spaces or commas
			$tags = preg_replace('/[\s,\,]+/', ' ', $tags);
			// Pull out multiword tags wrapped in quotes
			preg_match_all('/"(.*?)"/', $tags, $matches);
			if (!empty($matches[0]))
			{
				// Remove multiword tags, we'll add them back in a second
				$tags = str_replace($matches[0], '', $tags);
			}
			// Commafy tags for fast storage/searching in DB
			$tags = ',' . str_replace(' ', ',', trim($tags)) . ',';

			if (!empty($matches[1]))
			{
				// Add multiword tags back
				$tags .= join(',', $matches[1]) . ',';
			}
			// One last cleanup
			$this->tags = preg_replace('/\,+/', ',', preg_replace('/[^\w\p{L}0-9\-_\.\s,]+/u', '', $tags));
			if (function_exists('mb_strtolower'))
			{
				// strtolower screws up unicode, so use this if avail.
				$this->tags = mb_strtolower($this->tags);
			}
			// Tag caching
			$new = array_unique( explode(',', trim($this->tags, ',')) );

			if (isset($this->old_tags))
			{
				$old = $this->old_tags;
				$add = array_diff($new, $old);
				$remove = array_diff($old, $new);
			}
			else
			{
				$add = $new;
				$remove = false;
			}

			$t = new Tag();
			$t->manage($add, $remove, 'text');
		}
	}

	function listing($params)
	{
		$options = array(
			'page' => 1,
			'order_by' => 'modified_on',
			'order_direction' => 'DESC',
			'tags' => false,
			'tags_not' => false,
			'match_all_tags' => false,
			'limit' => 100,
			'published' => 1,
			'category' => false,
			'category_not' => false,
			'type' => false,
			'state' => false,
			'year' => false,
			'year_not' => false,
			'letter' => false,
			'month' => false,
			'month_not' => false,
			'render' => true,
			'tags' => false
		);
		$options = array_merge($options, $params);

		if (is_numeric($options['limit']) && $options['limit'] > 0)
		{
			$options['limit'] = min($options['limit'], 100);
		}
		else
		{
			$options['limit'] = 100;
		}
		if ($options['type'])
		{
			if ($options['type'] === 'essay')
			{
				$this->where('page_type', 0);
			}
			else if ($options['type'] === 'page')
			{
				$this->where('page_type', 1);
			}
		}
		if ($options['state'])
		{
			if ($options['state'] === 'published')
			{
				$this->where('published', 1);
			}
			else if ($options['state'] === 'draft' && $options['order_by'] !== 'published_on' )
			{
				$this->where('published', 0);
			}
		}

		if ($options['order_by'] === 'published_on')
		{
			$this->where('published', 1);
		}

		if ($options['tags'] || $options['tags_not'])
		{
			$this->group_start();
			if ($options['match_all_tags'])
			{
				$method = 'like';
			}
			else
			{
				$method = 'or_like';
			}

			if ($options['tags_not'])
			{
				$method = str_replace('like', 'not_like', $method);
				$options['tags'] = $options['tags_not'];
			}
			$tags = explode(',', urldecode($options['tags']));
			foreach($tags as $t)
			{
				$this->{$method}('tags', ',' . $t . ',', 'both');
			}
			$this->group_end();
		}

		if ($options['category'])
		{
			$this->where_related('category', 'id', $options['category']);
		}
		else if ($options['category_not'])
		{
			$cat = new Text;
			$cat->select('id')->where_related('category', 'id', $options['category_not'])->get_iterated();
			$cids = array();
			foreach($cat as $c)
			{
				$cids[] = $c->id;
			}
			$this->where_not_in('id', $cids);
		}

		if ($options['order_by'] === 'created_on' || $options['order_by'] === 'published_on' || $options['order_by'] === 'modified_on')
		{
			$bounds_order = $options['order_by'];
		}
		else
		{
			$bounds_order = 'published_on';
		}

		// Do this before date filters are applied, and only if sorted by created_on
		$bounds = $this->get_clone()
					->select('COUNT(*) as count, MONTH(FROM_UNIXTIME(' . $bounds_order . ')) as month, YEAR(FROM_UNIXTIME(' . $bounds_order . ')) as year')
					->group_by('month,year')
					->order_by('year')
					->get_iterated();

		$dates = array();
		foreach($bounds as $b)
		{
			if (!isset($dates[$b->year])) {
				$dates[$b->year] = array();
			}

			$dates[$b->year][$b->month] = (int) $b->count;
		}

		if (in_array($options['order_by'], array('created_on', 'published_on', 'modified_on')))
		{
			$date_col = $options['order_by'];
		}
		else
		{
			$date_col = 'published_on';
		}

		if ($options['year'] || $options['year_not'])
		{
			if ($options['year_not'])
			{
				$options['year'] = $options['year_not'];
				$compare = ' !=';
			}
			else
			{
				$compare = '';
			}
			$this->where('YEAR(FROM_UNIXTIME(' . $date_col . '))' . $compare, $options['year']);
		}
		if ($options['month'] || $options['month_not'])
		{
			if ($options['month_not'])
			{
				$options['month'] = $options['month_not'];
				$compare = ' !=';
			}
			else
			{
				$compare = '';
			}
			$this->where('MONTH(FROM_UNIXTIME(' . $date_col . '))' . $compare, $options['month']);
		}

		if ($options['letter'])
		{
			if ($options['letter'] === 'num')
			{
				$this->where('title <', 'a');
			} else {
				$this->like('title', $options['letter'], 'after');
			}

		}

		$final = $this->paginate($options);

		$final['dates'] = $dates;

		$data = $this->order_by($options['order_by'] . ' ' . $options['order_direction'])->get_iterated();

		if (!$options['limit'])
		{
			$final['per_page'] = $data->result_count();
			$final['total'] = $data->result_count();
		}

		$final['counts'] = array( 'total' => $final['total'] );

		$final['text'] = array();
		foreach($data as $page)
		{
			$final['text'][] = $page->to_array($options);
		}
		return $final;
	}

	function to_array($options = array())
	{
		$options = array_merge( array('auth' => false, 'render' => true), $options );

		$koken_url_info = $this->config->item('koken_url_info');

		$exclude = array('deleted', 'total_count', 'video_count', 'audio_count');
		$dates = array('created_on', 'modified_on', 'published_on');

		$bools = array('published');

		if (!$this->published)
		{
			$this->published_on = time();
		}
		list($data, $public_fields) = $this->prepare_for_output($options, $exclude, $bools, $dates);

		if (!$options['auth'])
		{
			unset($data['internal_id']);
		}

		if (array_key_exists('page_type', $data))
		{
			switch($data['page_type'])
			{
				case 1:
					$data['page_type'] = 'page';
					break;
				default:
					$data['page_type'] = 'essay';
			}
		}

		$data['__koken__'] = $data['page_type'];

		$data['categories'] = array();
		foreach($this->categories as $category)
		{
			if ($data['page_type'] === 'essay')
			{
				$data['categories'][] = array_merge($category->to_array(), array('__koken__' => 'category_essays'));
			}
			else
			{
				$data['categories'][] = $category->to_array();
			}
		}

		$data['topics'] = array();
		foreach($this->albums as $a)
		{
			$data['topics'][] = $a->to_array(array('with_topics' => false));
		}

		$rendered = Shutter::shortcodes( $data['content'], array( $this, $options ) );
		if (empty($options) || ( isset($options['render']) && $options['render'] ))
		{
			$data['content'] = $rendered;
		}
		if (empty($data['excerpt']))
		{
			$clean_parts = explode( ' ', preg_replace('/(\.|\?|\!)([^\s])/', '$1 $2', trim( strip_tags( preg_replace('/\[koken_[^\]]+]/', '', $rendered) ) ) ) );
			$excerpt = '';
			while(count($clean_parts) && ($next = array_shift($clean_parts)) && strlen(trim($excerpt) . ' ' . $next) <= 254)
			{
				$excerpt .= ' ' . $next;
			}
			$data['excerpt'] = trim($excerpt);
			if (count($clean_parts))
			{
				$data['excerpt'] = preg_replace('/[^a-z0-9]$/', '', $data['excerpt']) . '…';
			}
		}
		if (isset($options['order_by']) && in_array($options['order_by'], array( 'created_on', 'modified_on', 'published_on' )))
		{
			$data['date'] =& $data[ $options['order_by'] ];
		}
		else if ($data['page_type'] === 'essay')
		{
			$data['date'] =& $data['published_on'];
		}

		$data['url'] = $this->url(array(
				'date' => $data['published_on']
			)
		);

		if ($data['url'])
		{
			list($data['__koken_url'], $data['url']) = $data['url'];
		}

		return Shutter::filter('api.text', array( $data, $this, $options ));

	}
}

/* End of file page.php */
/* Location: ./application/models/page.php */