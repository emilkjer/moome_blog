<?php

class Texts extends Koken_Controller {

	function __construct()
	{
		 parent::__construct();
	}

	function oembed_preview() {
		if (isset($_POST['url']))
		{
			$data = Shutter::get_oembed(urldecode($_POST['url']));
			$this->set_response_data($data);
		}
	}

	function index()
	{
		list($params, $id, $slug) = $this->parse_params(func_get_args());
		$params['auth'] = $this->auth;

		// Create or update
		if ($this->method != 'get')
		{
			$t = new Text();
			switch($this->method)
			{
				case 'post':
				case 'put':
					if ($id)
					{
						$t->get_by_id($id);

						$t->old_tags = $t->tags;
						if (isset($_POST['tags']) && empty($_POST['tags']))
						{
							$_POST['tags'] = 0;
						}

						if (isset($_POST['unpublish']))
						{
							$_POST['published'] = 0;
							$_POST['published_on'] = null;
						}

						if (isset($_POST['categories']) && $t->page_type == 0)
						{
							$cat = new Category;
							$cat->manage_counts($t, 'text', $_POST['categories']);
						}
					}
					else
					{
						if (isset($_POST['page_type']) && $_POST['page_type'] === 'page')
						{
							$_POST['published'] = 1;
						}
					}
					if (!$t->from_array($_POST, array(), true))
					{
						// TODO: More info
						$this->error('500', 'Save failed.');
					}

					if ($id)
					{
						Shutter::hook('text.update', $t->to_array(array('auth' => true)));
					}
					else
					{
						Shutter::hook('text.create', $t->to_array(array('auth' => true)));
					}

					$this->redirect("/text/{$t->id}" . ( isset($params['render']) ? '/render:' . $params['render'] : '' ));
					break;
				case 'delete':
					if (is_null($id))
					{
						$this->error('403', 'Required parameter "id" not present.');
					}
					else
					{
						$text = $t->get_by_id($id);
						if ($text->exists())
						{
							Shutter::hook('text.delete', $text->to_array(array('auth' => true)));
							if (!$text->delete())
							{
								// TODO: More info
								$this->error('500', 'Delete failed.');
							}
						}
						else
						{
							$this->error('404', "Album with ID: $id not found.");
						}
					}
					exit;
					break;
			}
		}
		$p = new Text();
		// No id, so we want a list
		if (is_null($id) && !$slug)
		{
			$final = $p->listing($params);
		}
		// Get entry by id
		else
		{

			if (!is_null($id))
			{
				$page = $p->get_by_id($id);
			}
			else if ($slug)
			{
				$page = $p->where('slug', $slug)->get();
			}

			if ($page->exists())
			{

				$final = $page->to_array($params);
			}
			else
			{
				$this->error('404', "Text with ID: $id not found.");
			}

			if ($final['page_type'] === 'essay' && $page->published)
			{

				$options = array(
					'neighbors' => false
				);
				$options = array_merge($options, $params);

				if ($options['neighbors'])
				{
					// Make sure $neighbors is at least 2
					$options['neighbors'] = max($options['neighbors'], 2);

					// Make sure neighbors is even
					if ($options['neighbors'] & 1 != 0)
					{
						$options['neighbors']++;
					}

					$options['neighbors'] = $options['neighbors']/2;
				}
				else
				{
					$options['neighbors'] = 1;
				}

				if ($options['neighbors'])
				{
					// TODO: Performance check
					$next = new Text;
					$prev = new Text;

					$next
						->group_start()
							->where('page_type', 0)
							->where('published', 1)
							->where('published_on <', $page->published_on)
							->or_group_start()
								->where('published_on =', $page->published_on)
								->where('id <', $page->id)
							->group_end()
						->group_end();

					$prev
						->group_start()
							->where('page_type', 0)
							->where('published', 1)
							->where('published_on >', $page->published_on)
							->or_group_start()
								->where('published_on =', $page->published_on)
								->where('id >', $page->id)
							->group_end()
						->group_end();

					$max = $next->get_clone()->count();
					$min = $prev->get_clone()->count();

					$final['context']['total'] = $max + $min + 1;
					$final['context']['position'] = $min + 1;
					$pre_limit = $next_limit = $options['neighbors'];

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

					$final['context']['previous'] = array();
					$final['context']['next'] = array();

					if ($next_limit > 0)
					{
						$next->order_by('published_on DESC, id DESC')
							->limit($next_limit);

						$next->get_iterated();

						foreach($next as $c)
						{
							$final['context']['next'][] = $c->to_array( array('auth' => $this->auth) );
						}
					}

					if ($pre_limit > 0)
					{
						$prev->order_by('published_on ASC, id ASC')
							->limit($pre_limit);

						$prev->get_iterated();

						foreach($prev as $c)
						{
							$final['context']['previous'][] = $c->to_array( array('auth' => $this->auth) );
						}
						$final['context']['previous'] = array_reverse($final['context']['previous']);
					}

				}
			}

		}
		$this->set_response_data($final);
	}

	function topics() {

		list(, $id) = $this->parse_params(func_get_args());
		list($text_id, $album_id) = $id;

		if ($this->method === 'get')
		{
			// This is onlt for POST/DELETE operations, redirect them back to main /text GET
			$this->redirect("/text/{$text_id}");
		}
		else
		{

			$text = new Text();
			$t = $text->get_by_id($text_id);

			if (is_numeric($album_id))
			{
				$album_id = array( $album_id );
			}
			else
			{
				$album_id = explode(',', $album_id);
			}

			$album = new Album();
			$albums = $album->where_in('id', $album_id)->get_iterated();

			foreach($albums as $a)
			{
				if ($this->method === 'post')
				{
					$a->save($t);
				}
				else
				{
					$a->delete($t);
				}
			}

			$this->redirect("/text/{$text_id}");
			exit;
		}
	}

}

/* End of file albums.php */
/* Location: ./system/application/controllers/pages.php */