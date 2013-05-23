<?php

class Contents extends Koken_Controller {

	function __construct()
    {
         parent::__construct();
    }

    function cache()
    {
    	if ($this->method === 'delete')
    	{
			$this->load->helper('file');
			delete_files( FCPATH . 'storage' . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'images', true, 1 );
    	}
    	exit;
    }

	function index()
	{
		list($params, $id, $slug) = $this->parse_params(func_get_args());

		// Create or update
		if ($this->method != 'get')
		{
			$c = new Content();
			switch($this->method)
			{
				case 'post':
				case 'put':
					if ($this->method == 'put')
					{
						// Update
						$c->get_by_id($id);
						if (!$c->exists())
						{
							$this->error('404', "Content with ID: $id not found.");
						}
						$c->old_tags = $c->tags;
						if (isset($_POST['tags']) && empty($_POST['tags']))
						{
							$_POST['tags'] = 0;
						}

						if (isset($_POST['categories']))
						{
							$cat = new Category;
							$cat->manage_counts($c, 'content', $_POST['categories']);
						}
					}
					if (isset($_REQUEST['name']))
					{
						$file_name = $c->clean_filename($_REQUEST['name']);

						$chunk = isset($_REQUEST["chunk"]) ? $_REQUEST["chunk"] : 0;
						$chunks = isset($_REQUEST["chunks"]) ? $_REQUEST["chunks"] : 0;

						$tmp_dir = FCPATH . 'storage' . DIRECTORY_SEPARATOR . 'tmp';
						$tmp_path = $tmp_dir . DIRECTORY_SEPARATOR . $file_name;

						make_child_dir($tmp_dir);

						if ($chunks == 0 || $chunk == ($chunks - 1))
						{
							if (isset($_REQUEST['text']))
							{
								$path = FCPATH . 'storage' .
										DIRECTORY_SEPARATOR . 'custom' .
										DIRECTORY_SEPARATOR;
								$internal_id = false;
							}
							else
							{
								list($internal_id, $path) = $c->generate_internal_id();
							}
							if ($path)
							{
								$path .= $file_name;
								if ($chunks == 0)
								{
									$tmp_path = $path;
								}
							}
							else
							{
								$this->error('500', 'Unable to create directory for upload.');
							}
						}

						// Look for the content type header
						if (isset($_SERVER["HTTP_CONTENT_TYPE"]))
						{
							$contentType = $_SERVER["HTTP_CONTENT_TYPE"];
						}
						else if (isset($_SERVER["CONTENT_TYPE"]))
						{
							$contentType = $_SERVER["CONTENT_TYPE"];
						}
						else
						{
							$contentType = '';
						}

						if (strpos($contentType, "multipart") !== false) {
							if (isset($_FILES['file']['tmp_name']) && is_uploaded_file($_FILES['file']['tmp_name']))
							{
								$out = fopen($tmp_path, $chunk == 0 ? "wb" : "ab");
								if ($out)
								{
									// Read binary input stream and append it to temp file
									$in = fopen($_FILES['file']['tmp_name'], "rb");

									if ($in)
									{
										while ($buff = fread($in, 4096))
										{
											fwrite($out, $buff);
										}
									}
									else
									{
										$this->error('500', 'Unable to read input stream.');
									}

									fclose($out);
									unlink($_FILES['file']['tmp_name']);
								}
								else
								{
									$this->error('500', 'Unable to write to output file.');
								}
							}
							else
							{
								$this->error('500', 'Unable to move uploaded file.');
							}
						}
						else
						{
							$out = fopen($tmp_path, $chunk == 0 ? "wb" : "ab");
							if ($out)
							{
								// Read binary input stream and append it to temp file
								$in = fopen("php://input", "rb");

								if ($in)
								{
									while ($buff = fread($in, 4096))
									{
										fwrite($out, $buff);
									}
								}
								else
								{
									$this->error('500', 'Unable to read uploaded file.');
								}
								fclose($out);
							}
							else
							{
								$this->error('500', 'Unable to open output stream.');
							}
						}

						if ($chunk < ($chunks - 1))
						{
							// Don't continue until all chunks are uploaded
							exit;
						}
						else if ($chunks > 0)
						{
							// Done, move to permanent location and save to DB
							rename($tmp_path, $path);
						}

						if (!$internal_id) {
							// Custom text uploads can stop here
							die( json_encode( array( 'filename' => $file_name ) ) );
						}

						$from = array();
						$from['filename'] = $file_name;
						$from['internal_id'] = $internal_id;
						$from['file_modified_on'] = time();
					}
					else if (isset($_POST['localfile']))
					{
						$filename = basename($_REQUEST['localfile']);
						list($internal_id, $path) = $c->generate_internal_id();
						if (!file_exists($_REQUEST['localfile']))
						{
							$this->error('500', '"localfile" does not exist.');
						}
						if ($path)
						{
							$path .= $filename;
						}
						else
						{
							$this->error('500', 'Unable to create directory for upload.');
						}
						copy($_REQUEST['localfile'], $path);
						$from = array();
						$from['filename'] = $filename;
						$from['internal_id'] = $internal_id;
						$from['file_modified_on'] = time();
					}
					else if (is_null($id))
					{
						$this->error('403', 'New content records must be accompanied by an upload.');
					}
					if ($this->method == 'put')
					{
						$c->clear_cache();
					}

					if (isset($from))
					{
						$from = array_merge($_POST, $from);
					}
					else
					{
						$from = $_POST;
					}

					if (isset($_REQUEST['rotate']) &&
						is_numeric($_REQUEST['rotate']) &&
						$c->exists())
					{
						$r = $_REQUEST['rotate'];
						if (abs($r) != 90)
						{
							$this->error('403', 'Rotation can only be done in multiples of 90.');
						}
						$path = $c->path_to_original();

						if (MAGICK_PATH === 'gd')
						{

							$memory_limit = (int) ini_get('memory_limit');

							if (is_numeric($memory_limit) && $memory_limit < 256)
							{
								@ini_set('memory_limit', '256M');
							}

							list(,,$source_image_type) = getimagesize($path);

							switch($source_image_type) {
								case IMAGETYPE_JPEG:
									$src_img = imagecreatefromjpeg($path);
									break;

								case IMAGETYPE_PNG:
									$src_img = imagecreatefrompng($path);
									break;

								case IMAGETYPE_GIF:
									$src_img = imagecreatefromgif($path);
									break;
							}

							$rotated = imagerotate($src_img, -$r, 0);

							imagedestroy($src_img);

							switch($source_image_type) {
								case IMAGETYPE_JPEG:
									imagejpeg($rotated, $path, 99);
									break;

								case IMAGETYPE_PNG:
									imagepng($rotated, $path);
									break;

								case IMAGETYPE_GIF:
									imagegif($rotated, $path);
									break;
							}
						}
						else
						{
							if (in_array('imagick', get_loaded_extensions()))
							{
								$image = new Imagick($path);
								$image->rotateImage( new ImagickPixel(), $r );
								$image->writeImage($path);
								$image->clear();
								$image->destroy();
							}
							else
							{
								$cmd = MAGICK_PATH_FINAL . " \"$path\" -rotate {$r} \"$path\"";
								exec($cmd);
							}
						}

						$c->clear_cache();
						$from['width'] = $c->height;
						$from['height'] = $c->width;
						$from['aspect_ratio'] = $from['width'] / $from['height'];
						$from['file_modified_on'] = time();
					}

					if (isset($_REQUEST['reset_internal_id']) &&
						$_REQUEST['reset_internal_id'] &&
						$c->exists())
					{
						list($from['internal_id'],) = $c->generate_internal_id(true);
					}

					$hook = 'content.' . ( $id ? 'update' : 'create' );

					if (isset($from['filename']) && $id)
					{
						$hook .= '_with_upload';
						$c->_before();
					}

					$from = Shutter::filter("api.$hook", array_merge($from, array('file' => isset($path) ? $path : $c->path_to_original() )));

					unset($from['file']);

					$c->from_array($from, array(), true);

					Shutter::hook($hook, $c->to_array(array('auth' => true)));

					$this->redirect("/content/{$c->id}");
					break;
				case 'delete':
					if (is_null($id))
					{
						$this->error('403', 'Required parameter "id" not present.');
					}
					else
					{
						$t = new Tag();
						if (is_numeric($id))
						{
							$content = $c->get_by_id($id);
							if ($c->exists())
							{
								if (!empty($c->tags))
								{
									$t->manage(false, $c->tags);
								}
								$trash = new Trash();
								$this->db->query("DELETE from {$trash->table} WHERE id = 'content-{$c->id}'");
								$c->do_delete();
							}
							else
							{
								$this->error('404', "Content with ID: $id not found.");
							}
						}
						else
						{
							if ($id === 'trash')
							{
								$id = array();
								$trash = new Trash();
								$trash->like('id', 'content-')->get_iterated();
								foreach($trash as $item)
								{
									$content = unserialize($item->data);
									$id[] = $content['id'];
								}
							}
							else
							{
								$id = explode(',', $id);
							}

							/*
								Multiple delete
							 	/content/n1/n2/n3
							*/
							// Keep track of tags to --
							$tags = array();

							$c->where_in('id', $id);
							$contents = $c->get_iterated();
							$trash = new Trash();
							foreach($contents as $c)
							{
								if ($c->exists())
								{
									$tags = array_merge($tags, $c->tags);
									$this->db->query("DELETE from {$trash->table} WHERE id = 'content-{$c->id}'");
									$c->do_delete();
								}
							}
							$t->manage(false, $tags);
						}
					}
					exit;
					break;
			}
		}
		$c = new Content();
		if ($slug || (isset($id) && strpos($id, ',') === false))
		{
			$options = array(
				'context' => false,
				'neighbors' => false
			);
			$options = array_merge($options, $params);

			if ($options['context'] && $options['context'] !== 'stream')
			{
				if (is_numeric($options['context']))
				{
					$context_field = 'id';
				}
				else
				{
					$context_field = 'slug';
					$options['context'] = str_replace('slug-', '', $options['context']);
				}

				$a = new Album();
				$a->group_start()
						->where($context_field, $options['context'])
						->or_where('internal_id', $options['context'])
					->group_end()
					->get();

				$c->include_join_fields()->where_related_album('id', $a->id);
			}

			$with_token = false;

			if (is_numeric($id))
			{
				$content = $c->where('deleted', 0)->get_by_id($id);
			}
			else
			{
				if ($slug)
				{
					$content = $c->where('deleted', 0)
									->group_start()
										->where('internal_id', $slug)
										->or_where('slug', $slug)
										->or_where('old_slug', $slug)
									->group_end()
									->get();
				}
				else
				{
					$content = $c->where('deleted', 0)
									->where('internal_id', $id)
									->get();
				}

				if ($content->exists() && $content->internal_id === ( is_null($id) ? $slug : $id))
				{
					$with_token = true;
				}
			}

			if ($content->exists())
			{
				if (($c->visibility == 1 && !$this->auth && !$with_token) || (!$this->auth && !is_numeric($id) && $c->visibility == 2))
				{
					$this->error('403', 'Private content.');
				}

				$options['auth'] = $this->auth;

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
					$single_neighbors = false;
				}
				else
				{
					$options['neighbors'] = 1;
					$single_neighbors = true;
				}

				if ($options['context'] && $options['context'] !== 'stream')
				{
					$options['in_album'] = $a;
				}

				$final = $content->to_array($options);

				if ($options['context'])
				{
					// TODO: Performance check
					$next = new Content;
					$prev = new Content;
					$in_a = new Album;

					$next->where('deleted', 0);
					$prev->where('deleted', 0);

					if ($options['context'] !== 'stream')
					{
						if (!isset($options['context_order']))
						{
							$options['context_order'] = 'manual';
							$options['context_order_direction'] = 'ASC';
						}

						$final['context']['album'] = $a->to_array();

						$in_a->where("$context_field !=", $options['context']);

						$next->where_related_album('id', $a->id);
						$prev->where_related_album('id', $a->id);

						if ($options['context_order'] === 'manual')
						{
							$next->order_by_join_field('album', 'order', 'ASC')
							     ->where_join_field('album', 'order >', $content->join_order);

							$prev->order_by_join_field('album', 'order', 'DESC')
							     ->where_join_field('album', 'order <', $content->join_order);
						}
						else
						{
							$next_operator = strtolower($options['context_order_direction']) === 'desc' ? '<' : '>';
							$prev_operator = $next_operator === '<' ? '>' : '<';

							$next
								->group_start()
									->where($options['context_order'] . " $next_operator", $content->{$options['context_order']})
									->or_group_start()
										->where($options['context_order'], $content->{$options['context_order']})
										->where("id $next_operator", $content->id)
									->group_end()
								->group_end();

							$prev
								->group_start()
									->where($options['context_order'] . " $prev_operator", $content->{$options['context_order']})
									->or_group_start()
										->where($options['context_order'], $content->{$options['context_order']})
										->where("id $prev_operator", $content->id)
									->group_end()
								->group_end();
						}

						if (!$this->auth)
						{
							$next->where('visibility <', 2);
							$prev->where('visibility <', 2);
						}

						$in_album = $a;
					}
					else
					{
						if (!isset($options['context_order']))
						{
							$options['context_order'] = 'captured_on';
							$options['context_order_direction'] = 'DESC';
						}

						$next_operator = strtolower($options['context_order_direction']) === 'desc' ? '<' : '>';
						$prev_operator = $next_operator === '<' ? '>' : '<';

						$next
							->group_start()
								->where($options['context_order'] . " $next_operator", $content->{$options['context_order']})
								->or_group_start()
									->where($options['context_order'], $content->{$options['context_order']})
									->where("id $next_operator", $content->id)
								->group_end()
							->group_end();

						$prev
							->group_start()
								->where($options['context_order'] . " $prev_operator", $content->{$options['context_order']})
								->or_group_start()
									->where($options['context_order'], $content->{$options['context_order']})
									->where("id $prev_operator", $content->id)
								->group_end()
							->group_end();

						if (!$this->auth)
						{
							$next->where('visibility', 0);
							$prev->where('visibility', 0);
						}

						$in_album = false;
					}

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
						if ($options['context_order'] !== 'manual')
						{
							$next->order_by($options['context_order'] . ' ' . $options['context_order_direction'] . ', id ' . $options['context_order_direction']);
						}

						$next->limit($next_limit)->get_iterated();

						foreach($next as $c)
						{
							$final['context']['next'][] = $c->to_array( array('auth' => $this->auth, 'in_album' => $in_album) );
						}
					}

					if ($pre_limit > 0)
					{
						if ($options['context_order'] !== 'manual')
						{
							$dir = strtolower($options['context_order_direction']) === 'desc' ? 'asc' : 'desc';
							$prev->order_by($options['context_order'] . ' ' . $dir . ', id ' . $dir);
						}

						$prev->limit($pre_limit)->get_iterated();

						foreach($prev as $c)
						{
							$final['context']['previous'][] = $c->to_array( array('auth' => $this->auth, 'in_album' => $in_album) );
						}
						$final['context']['previous'] = array_reverse($final['context']['previous']);
					}

					$albums = $in_a->where_related('content', 'id', $content->id);

					if (!$this->auth)
					{
						$in_a->where('listed', 1);
					}

					$in_a->get_iterated();

					$final['albums'] = array();
					foreach($albums as $a)
					{
						$final['albums'][] = $a->to_array();
					}

				}
			}
			else
			{
				$this->error('404', "Content with ID: $id not found.");
			}
		}
		else
		{
			$c->where('deleted', 0);
			$params['auth'] = $this->auth;
			$final = $c->listing($params, $id);
		}

		$this->set_response_data($final);
	}
}

/* End of file contents.php */
/* Location: ./system/application/controllers/contents.php */