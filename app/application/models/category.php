<?php

class Category extends DataMapper {

	var $has_many = array(
		'content',
		'album',
		'text'
	);

	var $validation = array(
		'title' => array(
			'label' => 'Title',
			'rules' => array('required')
		),
		'slug' => array(
			'rules' => array('slug', 'required')
		)
	);

	/**
	 * Constructor: calls parent constructor
	 */
    function __construct($id = NULL)
	{
		parent::__construct($id);
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
			$t = new Category;
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

    function to_array($options = array())
	{
		$exclude = array('content_count', 'album_count', 'text_count');
		list($data, $public_fields) = $this->prepare_for_output($options, $exclude);
		$data['counts'] = array(
			'content' => (int) $this->content_count,
			'albums' => (int) $this->album_count,
			'essays' => (int) $this->text_count,
		);
		$data['__koken__'] = 'category';
		return $data;
	}

	function manage_counts($obj, $type, $post)
	{
		$obj->categories->get_iterated();
		$current_category_ids = array();
		$refresh_ids = array();

		foreach($obj->categories as $cat)
		{
			$current_category_ids[] = $cat->id;
		}

		if (empty($post))
		{
			foreach($obj->categories as $cat)
			{
				$cat->delete($obj);
				$refresh_ids[] = $cat->id;
			}
		}
		else
		{
			$new_categories = explode(',', $post);
			$add = array_diff($new_categories, $current_category_ids);
			$remove = array_diff($current_category_ids, $new_categories);

			if (!empty($add))
			{
				$c_new = new Category;
				$new = $c_new->where_in('id', $add)->get_iterated();
				foreach($new as $n)
				{
					$refresh_ids[] = $n->id;
					$n->save($obj);
				}
			}

			if (!empty($remove))
			{
				$c_rm = new Category;
				$remove = $c_rm->where_in('id', $remove)->get_iterated();
				foreach($remove as $r)
				{
					if (!in_array($r->id, $refresh_ids)) {
						$refresh_ids[] = $r->id;
					}
					$r->delete($obj);
				}
			}
		}

		if (!empty($refresh_ids))
		{
			$c_re = new Category;
			$refresh = $c_re->where_in('id', $refresh_ids)->get_iterated();

			foreach($refresh as $r)
			{
				$count = "{$type}_count";
				$r->{$count} = $r->{$type}->count();
				$r->save();
			}
		}
	}

	function load_preview()
	{
		$c = $this->contents->
				limit(10)->
				where('visibility', 0)->
				order_by('captured_on DESC')->
				get_iterated();

		$a = $this->albums->
				limit(10)->
				where('listed', 1)->
				order_by('created_on DESC')->
				get_iterated();

		$t = $this->texts->
				limit(10)->
				where('published', 1)->
				order_by('published_on DESC')->
				get_iterated();

		$content = $albums = $essays = array();
		foreach($c as $_c)
		{
			$content[] = $_c->to_array();
		}

		foreach($a as $_a)
		{
			$albums[] = $_a->to_array();
		}

		foreach($t as $_t)
		{
			$essays[] = $_t->to_array();
		}

		return array( $content, $albums, $essays );
	}

}

/* End of file category.php */
/* Location: ./application/models/category.php */