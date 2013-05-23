<?php

class Album extends DataMapper {

	var $validation = array(
		'internal_id' => array(
			'label' => 'Internal id',
			'rules' => array('internalize', 'required')
		),
		'created_on' => array(
			'rules' => array('validate_created_on')
		),
		'left_id' => array(
			'rules' => array('into_tree', 'required')
		),
		'listed' => array(
			'rules' => array('tree')
		),
		'tags' => array(
			'rules' => array('format_tags')
		),
		'title' => array(
			'rules' => array('required'),
			'get_rules' => array('readify')
		),
		'slug' => array(
			'rules' => array('slug', 'required')
		)
 	);

	var $db_join_prefix;

	function _into_tree()
	{
		if (is_null($this->left_id))
		{
			if (!is_numeric($this->listed))
			{
				$listed = 0;
			}
			else
			{
				$listed = $this->listed;
			}

			$check = new Album();
			$r = $check->where('listed', $listed)->select_max('right_id')->get()->right_id;

			if (!is_numeric($r))
			{
				$r = 0;
			}
			$this->left_id = $r + 1;
			$this->right_id = $r + 2;
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
			$t = new Album;
			$max = $t->select_max('id')->get();
			$slug = $max->id + 1;
		}

		if (is_numeric($slug))
		{
			$slug = "$slug-1";
		}

		while($this->where('slug', $slug)->count() > 0)
		{
			$slug = increment_string($slug, '-');
		}

		$this->slug = $slug;
	}

	function _validate_created_on()
	{
		$val = $this->created_on;
		if (is_numeric($val))
		{
			return strlen($val) === 10;
		}
		return false;
	}

	function update_set_counts()
	{
		$a = new Album();
		$a->where('album_type', 2)
			->update(array(
				'total_count' => "(right_id - left_id - 1)/2"
			), false);
	}

	function update_counts($save = true)
	{
		$c = new Content();
		$c->where_related('album', $this->id)->where('deleted', 0);

		$this->total_count = $this->content->where('deleted', 0)->count();
		$this->video_count = $this->content->where('deleted', 0)->where('file_type', 1)->count();

		if ($save)
		{
			$this->save();
		}
	}

	function tree_trash_restore()
	{
		$check = new Album();
		$max_right = $check->where('listed', $this->listed)->where('deleted', 0)->select_max('right_id')->get()->right_id;

		if (is_numeric($max_right))
		{
			$max_right++;
		}
		else
		{
			$max_right = 1;
		}

		$diff = $this->left_id - $max_right;
		$level_diff = $this->level - 1;

		if ($diff === 0)
		{
			$this->where('listed', $this->listed)->where('deleted', 1)->where('left_id >=', $this->left_id)->where('right_id <=', $this->right_id)->update(array(
					'level' => "level - $level_diff",
					'deleted' => 0
				), false);
		}
		else
		{
			if ($diff < 0)
			{
				$op = '+';
			}
			else
			{
				$op = '-';
			}
			$diff = abs($diff);
			$this->where('listed', $this->listed)->where('deleted', 1)->where('left_id >=', $this->left_id)->where('right_id <=', $this->right_id)->update(array(
					'right_id' => "right_id $op $diff",
					'left_id' => "left_id $op $diff",
					'level' => "level - $level_diff",
					'deleted' => 0
				), false);
		}

	}

	function tree_trash()
	{
		if ($this->deleted < 1)
		{
			$size = ($this->right_id - $this->left_id) + 1;

			$check = new Album();
			$max_right = $check->where('deleted', 1)->select_max('right_id')->get()->right_id;

			if (is_numeric($max_right))
			{
				$max_right++;
			}
			else
			{
				$max_right = 1;
			}

			$diff = $this->left_id - $max_right;
			$level_diff = $this->level - 1;

			$update = array('deleted' => 1, 'level' => "level - $level_diff");

			if ($diff !== 0)
			{
				if ($diff < 0)
				{
					$op = '+';
				}
				else
				{
					$op = '-';
				}
				$diff = abs($diff);
				$update['right_id'] = "right_id $op $diff";
				$update['left_id'] = "left_id $op $diff";
			}

			$this->where('listed', $this->listed)
					->where('deleted', 0)
					->where('left_id >=', $this->left_id)
					->where('right_id <=', $this->right_id)
					->update($update, false);


			$this->where('listed', $this->listed)->where('deleted', 0)->where('right_id >', $this->right_id)->update(array(
					'right_id' => "right_id - $size",
				), false);

			$this->where('listed', $this->listed)->where('deleted', 0)->where('left_id >', $this->right_id)->update(array(
					'left_id' => "left_id - $size",
				), false);

		}
	}

	function make_listed()
	{
		$old_right = $this->right_id;
		$size = ($this->right_id - $this->left_id) + 1;

		$old_listed = abs($this->listed - 1);

		$check = new Album();
		$max_right = $check->where('listed', $this->listed)->select_max('right_id')->get()->right_id;

		if (is_numeric($max_right))
		{
			$max_right++;
		}
		else
		{
			$max_right = 1;
		}

		$diff = $this->left_id - $max_right;
		$level_diff = $this->level - 1;

		if ($diff === 0)
		{
			$this->where('listed', $old_listed)->where('left_id >=', $this->left_id)->where('right_id <=', $this->right_id)->update(array(
					'listed' => $this->listed,
					'level' => "level - $level_diff"
				), false);
		}
		else
		{
			if ($diff < 0)
			{
				$op = '+';
			}
			else
			{
				$op = '-';
			}
			$diff = abs($diff);
			$this->where('listed', $old_listed)->where('left_id >=', $this->left_id)->where('right_id <=', $this->right_id)->update(array(
					'right_id' => "right_id $op $diff",
					'left_id' => "left_id $op $diff",
					'listed' => $this->listed,
					'level' => "level - $level_diff"
				), false);
		}

		$this->where('listed', $old_listed)->where('right_id >', $old_right)->update(array(
				'right_id' => "right_id - $size",
			), false);

		$this->where('listed', $old_listed)->where('left_id >', $old_right)->update(array(
				'left_id' => "left_id - $size",
			), false);

		$this->update_set_counts();
	}

	function _tree($field)
	{
		$this->make_listed();
	}

	/**
	 * Create internal ID if one is not present
	 */
	function _internalize($field)
	{
		$this->{$field} = koken_rand();
	}

	/**
	 * Constructor: calls parent constructor
	 */
    function __construct($id = NULL)
	{
		include(dirname(dirname(dirname(dirname(__FILE__)))) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR. 'configuration' . DIRECTORY_SEPARATOR . 'database.php');

		$this->db_join_prefix = $KOKEN_DATABASE['prefix'] . 'join_';

		$this->has_many = array(
			'content',
			'category' => array(
				'auto_populate' => true
			),
			'text' => array(
				'auto_populate' => true
			),
			'cover' => array(
				'class' => 'content',
				'join_table' => $this->db_join_prefix . 'albums_covers',
				'other_field' => 'covers',
				'join_other_as' => 'cover',
				'join_self_as' => 'album',
				'auto_populate' => true
			)
		);

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
				$t->manage(array(), $this->old_tags, 'album');
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
			$t->manage($add, $remove, 'album');
		}
	}

	function context($params, $auth)
	{
		if (!$params['neighbors'])
		{
			$single_neighbors = true;
			$n = 1;
		}
		else
		{
			$single_neighbors = false;
			$n = $params['neighbors']/2;
		}

		if (!isset($params['context_order']))
		{
			$params['context_order'] = 'left_id';
			$params['context_order_direction'] = 'ASC';
		}

		if ($params['context_order'] === 'manual')
		{
			$params['context_order'] = 'left_id';
		}

		$next_operator = strtolower($params['context_order_direction']) === 'asc' ? '>' : '<';
		$prev_operator = $next_operator === '>' ? '<' : '>';

		$arr = array();

		$next = new Album;
		$prev = new Album;

		$prev->where('deleted', 0)
			->where("{$params['context_order']} $prev_operator", $this->{$params['context_order']})
			->where('level', $this->level)
			->order_by("{$params['context_order']} " . ($prev_operator === '<' ? 'DESC' : 'ASC'));

		$next
			->where('deleted', 0)
			->where("{$params['context_order']} $next_operator", $this->{$params['context_order']})
			->where('level', $this->level)
			->order_by("{$params['context_order']} {$params['context_order_direction']}");

		if (!$auth)
		{
			$next->where('listed', 1);
			$prev->where('listed', 1);
		}

		if (!$params['include_empty_neighbors'])
		{
			$next->where('total_count >', 0);
			$prev->where('total_count >', 0);
		}

		$max = $next->get_clone()->count();
		$min = $prev->get_clone()->count();

		$arr['total'] = $max + $min + 1;
		$arr['position'] = $min + 1;
		$pre_limit = $next_limit = $n;

		if ($min < $pre_limit)
		{
			$next_limit += ($pre_limit - $min);
			$pre_limit = $min;
		}
		if ($max < $next_limit)
		{
			$pre_limit = min($min, $pre_limit + ($next_limit - $max));
			$next_limit = $max;
		}

		$arr['previous'] = array();
		$arr['next'] = array();

		if ($next_limit > 0)
		{
			$next->get_iterated();

			foreach($next as $a)
			{
				$arr['next'][] = $a->to_array();
			}
		}

		if ($pre_limit > 0)
		{
			$prev->get_iterated();

			foreach($prev as $c)
			{
				$arr['previous'][] = $c->to_array( array('auth' => $auth) );
			}
			$arr['previous'] = array_reverse($arr['previous']);
		}

		return $arr;
	}
	function listing($params)
	{
		$options = array(
			'trash' => false,
			'page' => 1,
			'order_by' => 'title',
			'order_direction' => 'ASC',
			'search' => false,
			'search_filter' => false,
			'tags' => false,
			'tags_not' => false,
			'match_all_tags' => false,
			'limit' => false,
			'listed' => 1,
			'include_empty' => true,
			'types' => false,
			'featured' => false,
			'category' => false,
			'category_not' => false,
			'year' => false,
			'year_not' => false,
			'month' => false,
			'month_not' => false
		);
		$options = array_merge($options, $params);

		if ($options['listed'] && !isset($params['order_by']))
		{
			$options['order_by'] = 'manual';
		}

		if ($options['order_by'] === 'manual')
		{
			$options['order_by'] = 'left_id';
			$options['order_direction'] = 'asc';
		}

		if ($options['featured'] == 1 && !isset($params['order_by']))
		{
			$options['order_by'] = 'featured_on';
		}

		if (!is_numeric($options['limit']))
		{
			$options['limit'] = false;
		}
		if ($options['types'])
		{
			$types = explode(',', str_replace(' ', '', $options['types']));

			$this->group_start();
			foreach($types as $t)
			{
				switch($t)
				{
					case 'set':
						$this->or_where('album_type', 2);
						break;

					case 'smart':
						$this->or_where('album_type', 1);
						break;

					case 'standard':
						$this->or_where('album_type', 0);
						break;
				}
			}
			$this->group_end();
		}
		if ($options['search'])
		{

			if ($options['search_filter'])
			{
				if ($options['search_filter'] === 'category')
				{
					$cat = new Category;
					$cat->where('title', urldecode($options['search']))->get();
					if ($cat->exists())
					{
						$this->where_related('category', 'id', $cat->id);
					}
					else
					{
						$this->where_related('category', 'id', 0);
					}

				}
				else
				{
					$this->group_start();
					$this->like($options['search_filter'], urldecode($options['search']), 'both');
					$this->group_end();
				}

			}
			else
			{
				$this->group_start();
				$this->like('title', urldecode($options['search']), 'both');
				$this->or_like('description', urldecode($options['search']), 'both');
				$this->or_like('tags', ',' . urldecode($options['search']) . ',', 'both');
				$this->group_end();
			}

		}
		else if ($options['tags'] || $options['tags_not'])
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

		$sub_list = false;

		if ($this->exists())
		{
			$sub_list = true;
			$this->where('left_id >', $this->left_id)
					->where('right_id <', $this->right_id)
					->where('level', $this->level + 1)
					->where('listed', $this->listed);
		}
		// TODO: Add auth so only priveledged accounts can get unlisted album lists
		else if ($options['listed'] !== false)
		{
			$this->where('listed', $options['listed']);
		}

		if (!$options['include_empty'])
		{
			$this->where('total_count >', 0);
		}
		if ($options['featured'] || $options['category'] || $options['category_not'])
		{
			if ($options['featured'])
			{
				$this->where('featured', 1);
			}
			if ($options['category'])
			{
				$this->where_related('category', 'id', $options['category']);
			}
			else if ($options['category_not'])
			{
				$cat = new Album;
				$cat->select('id')->where_related('category', 'id', $options['category_not'])->get_iterated();
				$cids = array();
				foreach($cat as $c)
				{
					$cids[] = $c->id;
				}
				$this->where_not_in('id', $cids);
			}
		}
		else if ($options['featured'] !== false && (int) $options['featured'] === 0)
		{
			$this->where('featured', 0);
		}
		else if (!$sub_list && !$options['tags'] && !$options['year'])
		{
			$this->where('level', 1);
		}

		if (in_array($options['order_by'], array('created_on', 'modified_on')))
		{
			$date_col = $options['order_by'];
		}
		else
		{
			$date_col = 'created_on';
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

		$this->where('deleted', (int) $options['trash']);
		$set_count = $this->get_clone()->where('album_type', 2)->count();
		$final = $this->paginate($options);

		$data = $this->order_by($options['order_by'] . ' ' . $options['order_direction'])->get_iterated();
		if (!$options['limit'])
		{
			$final['per_page'] = $data->result_count();
			$final['total'] = $data->result_count();
		}

		$final['counts'] = array(
			'albums' => $final['total'] - $set_count,
			'sets' => $set_count,
			'total' => $final['total']
		);


		$final['albums'] = array();
		foreach($data as $album)
		{
			$params['include_parent'] = !$sub_list;
			$final['albums'][] = $album->to_array($params);
		}
		return $final;
	}

	function reset_covers($whitelist = null, $blacklist = null)
	{
		if ($this->album_type == 0)
		{
			$count = $this->covers->result_count();
			if ($count > 2) { return; }

			$existing_ids = array();
			foreach($this->covers as $f)
			{
				$existing_ids[] = $f->id;
			}

			if (!is_null($blacklist))
			{
				$existing_ids[] = $blacklist;
			}

			$next = $this->contents->select('id')
						->where('deleted', 0);

			if (is_null($whitelist))
			{
				$next->where('visibility', 0);
			}
			else
			{
				$next->group_start()
					->where('id', $whitelist)
					->or_where('visibility', 0)
				->group_end();
			}

			$next->group_start()
				->where('file_type', 0)
				->or_where('lg_preview !=', 'NULL')
			->group_end();

			if (!empty($existing_ids))
			{
				$next->where_not_in('id', $existing_ids);
			}

			$next->limit(3 - $count)->get_iterated();

			foreach($next as $n)
			{
				$this->save_cover($n);
			}
		}
	}

	function manage_content($content_id, $method = 'post')
	{
		if (strpos($content_id, ',') !== FALSE)
		{
			$ids = explode(',', $content_id);
		}
		else
		{
			$ids = array($content_id);
		}

		$h = new History();

		if ($this->album_type == 0)
		{
			$c = new Content();
			$members = $this->contents->select('id,lg_preview')->get_iterated();
			$member_ids = array();
			foreach($members as $member)
			{
				$member_ids[] = $member->id;
			}
			$contents = $c->where_in('id', $ids)->order_by('id ASC')->get_iterated();

			foreach($contents as $content)
			{
				if (!$content->exists())
				{
					return false;
				}
				$covers_count = $this->covers->count();
				switch($method)
				{
					case 'post':
						if ($this->save($content))
						{
							if (!in_array($content->id, $member_ids))
							{
								if ($covers_count < 3 && ($content->file_type == 0 || $content->lg_preview))
								{
									$this->save_cover($content);
								}

								$this->update_counts(false);
								$this->save();
								$this->set_join_field($content, 'order', $this->total_count);
							}
						}
						break;
					case 'delete':
						if (in_array($content->id, $member_ids))
						{
							$this->delete($content);
							$this->delete_cover($content);
							$this->save();
							$this->reset_covers();
						}
						break;
				}
			}
			if (count($ids) == 1)
			{
				$message = 'content:move';
				$c = $content->filename;
			}
			else
			{
				$message = 'content:move:multiple';
				$c = count($ids);
			}
		}
		else
		{
			$a = new Album();
			switch($method)
			{
				case 'post':
				case 'delete':

					$this->db->trans_begin();

					foreach($ids as $move_id)
					{
						$d = new Album();
						$dest_copy = $d->select('level,left_id,right_id')->get_by_id($this->id);
						$move_copy = $a->select('listed,level,left_id,right_id')->get_by_id($move_id);

						if ($method == 'post')
						{
							$destination_left = $dest_copy->right_id;
							$delta = ($dest_copy->level - $move_copy->level) + 1;
							$delta = $delta >= 0 ? '+ ' . $delta : '- ' . abs($delta);
							$level_delta = 'level ' . $delta;
						}
						else
						{
							// For removals, we simply move the object back to the root
							$max = new Album();
							$max->select_max('right_id')->get();
							$destination_left = $max->right_id;
							$level_delta = 'level - ' . abs(1 - $move_copy->level);
							$destination_left++;
						}

						$left = $move_copy->left_id;
						$right = $move_copy->right_id;
						$size = $right - $left + 1;

						$a->shift_tree_values($destination_left, $size, $this->listed);

						if ($move_copy->left_id >= $destination_left && $move_copy->listed == $this->listed)
						{
							$left += $size;
							$right += $size;
						}

						$delta = $destination_left - $left;
						$delta = $delta >= 0 ? '+ ' . $delta : '- '. abs($delta);

						$a->where('left_id >=', $left)
								->where('right_id <=', $right)
								->where('listed', $move_copy->listed)
								->update(array(
									'left_id' => "left_id $delta",
									'right_id' => "right_id $delta",
									'listed' => $this->listed,
									'level' => $level_delta
								), false);

						$a->shift_tree_values($right + 1, -$size, $move_copy->listed);
					}

					$this->update_set_counts();

					$this->db->trans_complete();
					break;
			}
			$message = "album:move";
			if (count($ids) > 1)
			{
				$message .= ':multiple';
			}
			$c = count($ids);
		}
		if ($method == 'delete')
		{
			$message = str_replace('move', 'remove', $message);
		}
		$h->message = array($message, $c, $this->title);
		$h->save();
	}

	function shift_tree_values($first, $delta, $listed)
	{
		$delta = $delta >= 0 ? '+ ' . $delta : '- '. abs($delta);

		$this->where('left_id >=', $first)
				->where('listed', $listed)
				->update(array(
					'left_id' => "left_id $delta"
				), false);

		$this->where('right_id >=', $first)
				->where('listed', $listed)
				->update(array(
					'right_id' => "right_id $delta"
				), false);
	}

	function to_array($options = array())
	{
		$options = array_merge( array('with_topics' => true, 'auth' => false), $options );

		$koken_url_info = $this->config->item('koken_url_info');

		$exclude = array('deleted', 'total_count', 'video_count');
		$dates = array('created_on', 'modified_on', 'featured_on');
		$strings = array('title', 'summary', 'description');

		$bools = array('listed', 'featured');

		list($data, $public_fields) = $this->prepare_for_output($options, $exclude, $bools, $dates, $strings);

		if (!$options['auth'] && $data['listed'] == 1) {
			unset($data['internal_id']);
		}

		if (!$data['featured'])
		{
			unset($data['featured_on']);
		}

		$data['__koken__'] = 'album';

		if (array_key_exists('album_type', $data))
		{
			switch($data['album_type'])
			{
				case 2:
					$data['album_type'] = 'set';
					break;
				case 1:
					$data['album_type'] = 'smart';
					break;
				default:
					$data['album_type'] = 'standard';
			}
		}

		$data['counts'] = array(
			'total' => (int) $this->total_count,
			'videos' => (int) $this->video_count,
			'images' => $this->total_count - $this->video_count
		);

		$data['categories'] = array();
		foreach($this->categories as $category)
		{
			$data['categories'][] = array_merge($category->to_array(), array('__koken__' => 'category_albums'));
		}

		if ($options['with_topics']) {
			$data['topics'] = array();
			foreach($this->texts as $text)
			{
				$data['topics'][] = $text->to_array();
			}
		}

		$data['covers'] = $existing = array();

		foreach($this->covers->order_by("covers_{$this->db_join_prefix}albums_covers.id ASC")->get_iterated() as $f)
		{
			if ($f->exists())
			{
				$data['covers'][] = $f->to_array(array('in_album' => $this));
				$existing[] = $f->id;
			}
		}

		if ($this->album_type == 2 && $this->covers->result_count() == 0)
		{
			$a = new Album();
			$ids = $a->select('id')
						->where('right_id <', $this->right_id)
						->where('left_id >', $this->left_id)
						->where('listed', $this->listed)
						->get_iterated();

			$id_arr = array();

			foreach($ids as $id)
			{
				$id_arr[] = $id->id;
			}

			if (!empty($id_arr))
			{
				$c = new Content();
				$q = "SELECT DISTINCT cover_id FROM {$this->db_join_prefix}albums_covers WHERE album_id IN (" . join(',', $id_arr) . ")";
				if (!empty($existing))
				{
					$q .= ' AND cover_id NOT IN(' . join(',', $existing) . ')';
				}
				$covers = $c->query($q . "GROUP BY album_id LIMIT " . (3 - $this->covers->result_count()));

				$f_ids = array();
				foreach($covers as $f)
				{
					$f_ids[] = $f->cover_id;
				}

				if (!empty($f_ids))
				{
					$c->where_in('id', $f_ids)->get_iterated();
					foreach($c as $content)
					{
						// TODO: auth needs to be passed in here
						array_unshift($data['covers'], $content->to_array(array('in_album' => $this)));
					}
				}
			}
		}

		// Latest covers first
		$data['covers'] = array_reverse($data['covers']);

		if (isset($options['order_by']) && in_array($options['order_by'], array( 'created_on', 'modified_on' )))
		{
			$data['date'] =& $data[ $options['order_by'] ];
		}
		else
		{
			$data['date'] =& $data['created_on'];
		}

		if ($data['level'] > 1 && (!array_key_exists('include_parent', $options) || $options['include_parent']))
		{
			$parent = new Album();
			$parent->where('left_id <', $data['left_id'])
					->where('level <', $data['level'])
					->order_by('left_id DESC')
					->limit(1)
					->get();

			$data['parent'] = $parent->to_array();
		}
		else if ($data['level'] == 1)
		{
			$data['parent'] = false;
		}

		$data['url'] = $this->url(
			array(
				'date' => $data['created_on']
			)
		);

		if ($data['url'])
		{
			list($data['__koken_url'], $data['url']) = $data['url'];
		}

		if (!$options['auth'] && $data['listed'] < 1) {
			unset($data['url']);
		}

		return Shutter::filter('api.album', array( $data, $this, $options ));
	}

	function apply_smart_conditions($smart_rules, $options = array(), $limit_for_preview = false)
	{
		$content = new Content;
		$array = unserialize($smart_rules);
		$conditions = $array['conditions'];
		if (!empty($conditions))
		{
			if ($array['any_all'])
			{
				$content->group_start();
			}
			else
			{
				$content->or_group_start();
			}
			foreach($conditions as $c)
			{
				if (isset($c['bool']) && !$c['bool'])
				{
					$bool = ' NOT ';
				}
				else
				{
					$c['bool'] = true;
					$bool = '';
				}
				switch($c['type'])
				{
					case 'album':
						if (!empty($c['filter']) && is_numeric($c['filter']))
						{
							$content->where_related_album('id' . ($c['bool'] ? '' : '!='), $c['filter']);
						}
						break;
					case 'tag':
						if (!empty($c['input']))
						{
							$content->group_start();
							if ($c['bool'])
							{
								$method = 'like';
							}
							else
							{
								$method = 'not_like';
								$content->or_group_start();
							}
							$content->{$method}('tags', "{$c['input']},");
							if (!$c['bool'])
							{
								$content->where('tags IS NULL');
								$content->group_end();
							}
							if (is_numeric($c['filter']))
							{
								$content->where_related_album('id', $c['filter']);
							}
							$content->group_end();
						}
						break;
					case 'date':
						switch($c['modifier'])
						{
							// TODO: Time zone offsets
							case 'on':
								$start = strtotime($c['start'] . ' 00:00:00');
								$end = strtotime($c['start'] . ' 23:59:59');
								$content->where($c['column'] . "{$bool}BETWEEN $start AND $end");
								break;
							case 'before':
								$start = strtotime($c['start'] . ' 00:00:00');
								$content->group_start();
								$content->where($c['column'] . ' ' . ($c['bool'] ? '<' : '>'), $start)
										->where($c['column'] . ' IS NOT NULL')
										->where($c['column'] . ' <> 0');
								$content->group_end();
								break;
							case 'after':
								$start = strtotime($c['start'] . ' 23:59:59');
								$content->where($c['column'] . ' ' . ($c['bool'] ? '>' : '<'), $start);
								break;
							case 'between':
								$start = strtotime($c['start'] . ' 00:00:00');
								$end = strtotime($c['end'] . ' 23:59:59');
								$content->where($c['column'] . "{$bool}BETWEEN $start AND $end");
								break;
							case 'within':
								$end_str = date('Y-m-d') . ' 23:59:59';
								$end = strtotime($end_str);
								$start = strtotime($end_str . ' -' . $c['within'] . ' ' . $c['within_modifier'] . 's');
								$content->where($c['column'] . ' ' . ($c['bool'] ? '>' : '<'), $start);
								break;
						}
						break;
				}
			}
			$content->group_end();
			if (isset($array['limit_to']) && is_numeric($array['limit_to']))
			{
				$content->where('file_type', $array['limit_to']);
			}
			switch($array['order'])
			{
				case 'file':
					// TODO: Is this enough, or do we need to use natcasesort?
					$column = 'filename';
					break;
				default:
					if ($array['order'] == 'date')
					{
						$column = 'created_on';
					}
					else
					{
						$column = 'captured_on';
					}
					break;
			}
			$content->order_by($column . ' ' . $array['order_direction']);
			if (isset($options['limit']) && is_numeric($array['limit']))
			{
				if (!$options['limit'] || $array['limit'] < $options['limit'])
				{
					$options['limit'] = $array['limit'];
				}
				$options['cap'] = $array['limit'];
			}
		}
		if (empty($options))
		{
			$final = array();
		}
		else
		{
			$final = $content->paginate($options);
		}

		return array($content, $final);
	}
}

/* End of file album.php */
/* Location: ./application/models/album.php */