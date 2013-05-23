<?php

class Tags extends Koken_Controller {

	function __construct()
    {
         parent::__construct();
    }

	function index()
	{
		$defaults = array('summary' => false, 'page' => 1, 'limit' => 10);

		list($params,$id) = $this->parse_params(func_get_args());

		$params = array_merge($defaults, $params);

		$t = new Tag();
		list($count, $listing) = $t->listing($params);

		$count_obj = array_pop( $count->result() );
		$final = array(
					'page' => (int) $params['page'],
					'pages' => ceil($count_obj->cnt/$params['limit']),
					'per_page' => min((int) $params['limit'], $count_obj->cnt),
					'total' => (int) $count_obj->cnt,
					'tags' => array()
				);

		foreach($listing->result() as $l) {

			$arr = array(
				'title' => $l->id,
				'__koken__' => 'tag',
				'counts' => array(
					'total' => (int) $l->count,
					'content' => (int) $l->content_count,
					'albums' => (int) $l->album_count,
					'essays' => (int) $l->text_count
				)
			);

			if (!$params['summary'])
			{
				$c = new Content;
				$a = new Album;
				$t = new Text;

				$content = $albums = $essays = array();

				$c->limit(10)
					->like('tags', ',' . $l->id . ',')
					->where('visibility', 0)
					->order_by('captured_on DESC')
	 				->get_iterated();

	 			foreach($c as $_c)
				{
					$content[] = $_c->to_array( array('auth' => $this->auth) );
				}

				$a->limit(10)
					->like('tags', ',' . $l->id . ',')
					->where('listed', 1)
					->order_by('created_on DESC')
					->get_iterated();

				foreach($a as $_a)
				{
					$albums[] = $_a->to_array();
				}

				$t->limit(10)
					->like('tags', ',' . $l->id . ',')
					->where('page_type', 0)
					->where('published', 1)
					->order_by('published_on DESC')
					->get_iterated();

				foreach($t as $_t)
				{
					$essays[] = $_t->to_array();
				}

				$arr['preview'] = array(
					'content' => $content,
					'albums' => $albums,
					'essays' => $essays
				);

			}

			$final['tags'][] = $arr;

		}

		$this->set_response_data($final);
	}

}