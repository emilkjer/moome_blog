<?php

include(dirname(dirname(dirname(dirname(__FILE__)))) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR. 'configuration' . DIRECTORY_SEPARATOR . 'database.php');

class Content extends DataMapper {

	var $table = 'content';
	var $created_field = 'uploaded_on';

	var $validation = array(
		'internal_id' => array(
			'label' => 'Internal id',
			'rules' => array('required')
		),
		'uploaded_on' => array(
			'rules' => array('validate_uploaded_on')
		),
		'filename' => array(
			'rules' => array('before'),
			'get_rules' => array('readify')
		),
		'title' => array(
			'rules' => array('title_to_slug')
		),
		'tags' => array(
			'rules' => array('format_tags')
		),
		'license' => array(
			'rules' => array('validate_license')
		),
		'visibility' => array(
			'rules' => array('validate_visibility')
		),
		'max_download' => array(
			'rules' => array('validate_max_download')
		),
		'slug' => array(
			'rules' => array('slug', 'required')
		)
	);

	function clean_filename($file)
	{
		$this->load->helper(array('url', 'text', 'string'));
		$info = pathinfo($file);
		return reduce_multiples(
			str_replace('_', '-',
				url_title(
					convert_accented_characters($info['filename']), 'dash'
				)
			)
		, '-', true) . '.' . $info['extension'];
	}

	function _title_to_slug($field)
	{
		if (empty($this->old_slug))
		{
			$this->_slug('slug');
		}
	}

	function _slug($field)
	{
		$this->load->helper(array('url', 'text', 'string'));

		if (empty($this->title))
		{
			$info = pathinfo($this->filename);
			$base = $info['filename'];
		}
		else
		{
			$base = $this->title;
		}

		$slug = reduce_multiples(
					strtolower(
						url_title(
							convert_accented_characters($base), 'dash'
						)
					)
				, '-', true);


		if (empty($slug))
		{
			$t = new Content;
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

		if (empty($this->old_slug))
		{
			if (!empty($this->slug) && $this->slug !== '__generate__')
			{
				$this->old_slug = $this->slug;
			}
			else if (!empty($this->title))
			{
				$this->old_slug = $slug;
			}
		}

		$this->slug = $slug;
	}

	// Multibyte safe unserialization for cray cray EXIF/IPTC junk
	function _unserialize($string) {
		$original = $string;
		$string = preg_replace('!s:(\d+):"(.*?)";!se', "'s:'.strlen('$2').':\"$2\";'", $string);
		$mb = @unserialize($string);
		if ($mb)
		{
			return $mb;
		}
		else
		{
			return @unserialize($original);
		}
	}

	function _validate_uploaded_on()
	{
		$val = $this->uploaded_on;
		if (is_numeric($val))
		{
			return strlen($val) === 10;
		}
		return false;
	}

	function _validate_max_download()
	{
		$values = array('none', 'original', 'huge', 'xlarge', 'large', 'medium_large', 'medium');
		if (in_array($this->max_download, $values))
		{
			$this->max_download = array_search($this->max_download, $values);
		}
		else
		{
			return false;
		}
	}

	function _validate_visibility()
	{
		$values = array('public', 'unlisted', 'private');
		if (in_array($this->visibility, $values))
		{
			$a = new Album;
			if ($this->visibility === 'public')
			{
				$albums = $a->where_related('content', 'id', $this->id)->get_iterated();
				foreach($albums as $album)
				{
					$album->reset_covers($this->id);
				}
			}
			else
			{
				$covers = $a->where_related('cover', 'id', $this->id)->get_iterated();
				foreach($covers as $album)
				{
					$album->delete_cover($this);
					$album->reset_covers(null, $this->id);
				}
			}
			$this->visibility = array_search($this->visibility, $values);
		}
		else
		{
			return false;
		}
	}

	function _validate_license()
	{
		if (!empty($this->license))
		{
			if ($this->license == 'all')
			{
				return true;
			}
			if (!preg_match('/^(y|n)\,(y|s|n)$/', $this->license))
			{
				return false;
			}
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
				$t->manage(array(), $this->old_tags);
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
			$t->manage($add, $remove);
		}
	}

	function generate_internal_id($reset = false)
	{
		$base = FCPATH .
					DIRECTORY_SEPARATOR . 'storage' .
					DIRECTORY_SEPARATOR . 'originals' .
					DIRECTORY_SEPARATOR;

		if ($this->exists())
		{
			if ($reset) {
				$internal_id = substr($this->internal_id, 0, 4) . substr(koken_rand(), 4);
			} else {
				$internal_id = $this->internal_id;
			}
			$path = $base . $this->path;
		}
		else
		{
			$internal_id = koken_rand();
			$hash = substr($internal_id, 0, 2) . DIRECTORY_SEPARATOR . substr($internal_id, 2, 2);
			$path = $base . $hash;
			if (!make_child_dir($path))
			{
				$path = false;
			}
		}
		return array($internal_id, $path . DIRECTORY_SEPARATOR);
	}

	function _before()
	{
		$this->file_type = (int) $this->set_type();
		$path = $this->path_to_original();
		if ($this->file_type > 0)
		{
			include_once(FCPATH . 'app' . DIRECTORY_SEPARATOR . 'koken' . DIRECTORY_SEPARATOR . 'ffmpeg.php');
			$ffmpeg = new FFmpeg($path);
			if ($ffmpeg->version())
			{
				$this->duration = $ffmpeg->duration();
				list($this->width, $this->height) = $ffmpeg->dimensions();
				$this->lg_preview = $ffmpeg->create_thumbs();
			}
		}
		else
		{
			list($this->width, $this->height) = getimagesize($path);
			// Have to do this twice for some reason, as some metadata errors cause getimagesize
			// to return false, even if it could tell us the dimensions
			getimagesize($path, $info);
			if (!empty($info['APP13']))
			{
				$iptc = iptcparse($info['APP13']);
				if (!empty($iptc))
				{
					$this->iptc = utf8_encode(serialize($iptc));
				}
			} else {
				$iptc = array();
			}

			$pathinfo = pathinfo($path);
			if (in_array(strtolower($pathinfo['extension']), array('jpg', 'jpeg')) && is_callable('exif_read_data'))
			{
				@$exif = exif_read_data($path, 0, true);
				// Maker notes are evil and eat up space
				unset($exif['EXIF']['MakerNote']);
				if (!empty($exif))
				{
					$this->exif = utf8_encode(serialize($exif));
				}
			}
			else
			{
				$exif = array();
			}

			if (isset($iptc['2#005']))
			{
				if (is_array($iptc['2#005']))
				{
					$iptc['2#005'] = $iptc['2#005'][0];
				}
				$this->title = $iptc['2#005'];
			}

			if (isset($iptc['2#120']))
			{
				if (is_array($iptc['2#120']))
				{
					$iptc['2#120'] = $iptc['2#120'][0];
				}
				$this->caption = $iptc['2#120'];
			}

			if (isset($iptc['2#025']) && is_array($iptc['2#025']))
			{
				$words = array();
				if (count($iptc['2#025']) == 1)
				{
					$words = explode(' ', $iptc['2#025'][0]);
				}
				else
				{
					foreach($iptc['2#025'] as $key)
					{
						$words[] = '"' . $key . '"';
					}
				}

				$this->tags .= ' ' . join(',', $words);
			}

			$captured_on = $this->parse_captured($iptc, $exif);

			if (!is_null($captured_on) && $captured_on > 0)
			{
				$this->captured_on = $captured_on;
			}

			// TODO: EXIF (F, shutter speed, subject distance?)
			if (isset($exif['IFD0']['Make']))
			{
				$this->exif_make = trim($exif['IFD0']['Make']);
			}
			if (isset($exif['IFD0']['Model']))
			{
				$this->exif_model = trim($exif['IFD0']['Model']);
			}
			if (isset($exif['EXIF']['ISOSpeedRatings']))
			{
				$this->exif_iso = trim($exif['EXIF']['ISOSpeedRatings']);
			}
			// Best Lens info is in this tag
			if (isset($exif['EXIF']['UndefinedTag:0xA434']))
			{
			 	$this->exif_camera_lens = trim($exif['EXIF']['UndefinedTag:0xA434']);
			}
			// If the above doesn't work, this is a fallback
			else if (isset($exif['EXIF']['UndefinedTag:0xA432']))
			{
				$val = $exif['EXIF']['UndefinedTag:0xA432'];
				$short = array_shift(explode('/', $val[0]));
				$long = array_shift(explode('/', $val[1]));
				$lens = $short;
				if ($short != $long)
				{
					$lens .= '-' . $long;
				}
				$lens .= ' mm';
				$this->exif_camera_lens = $lens;
			}

			$longest = max($this->width, $this->height);
			$midsize = preg_replace('/\.' . $pathinfo['extension'] . '$/', '.1600.' . $pathinfo['extension'], $path);

			if (file_exists($midsize))
			{
				unlink($midsize);
			}

			if ($longest > 1600 && ENVIRONMENT === 'production')
			{
				include_once(FCPATH . 'app' . DIRECTORY_SEPARATOR . 'koken' . DIRECTORY_SEPARATOR . 'darkroom.php');

				Darkroom::develop( array(
						'source' => $path,
						'destination' => preg_replace('/\.' . $pathinfo['extension'] . '$/', '.1600.' . $pathinfo['extension'], $path),
						'width' => 1600,
						'height' => 1600,
						'quality' => 99,
						'square' => false,
						'sharpening' => 0,
						'focal_x' => 50,
						'focal_y' => 50,
						'hires' => false,
						'source_width' => $this->width,
						'source_height' => $this->height,
						'strip' => false
					)
				);
			}
		}
		$this->filesize = filesize($path);

		if (is_numeric($this->width))
		{
			$this->aspect_ratio = $this->width / $this->height;
		}
	}

	function _set_paths()
	{
		$this->path = substr($this->internal_id, 0, 2) . DIRECTORY_SEPARATOR . substr($this->internal_id, 2, 2);

		$padded_id = str_pad($this->id, 6, '0', STR_PAD_LEFT);
		$this->cache_path = substr($padded_id, 0, 3) . DIRECTORY_SEPARATOR . substr($padded_id, 3);
	}

	// General prep. We use the get_rule on filename to ensure this is always run, as filename is always present
	function _readify()
	{
		$this->_set_paths();
		if (isset($this->tags) && !empty($this->tags))
		{
			$this->tags = array_unique( explode(',', trim($this->tags, ',')) );
		}
		else
		{
			$this->tags = array();
		}
		$f = unserialize($this->focal_point);
		if (empty($f))
		{
			$f = array('x' => 50, 'y' => 50);
		}
		$this->focal_point = $f;
	}

	/**
	 * Constructor: calls parent constructor
	 */
    function __construct($id = NULL)
	{
		include(dirname(dirname(dirname(dirname(__FILE__)))) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR. 'configuration' . DIRECTORY_SEPARATOR . 'database.php');

		$this->has_many = array(
			'album',
			'category' => array(
				'auto_populate' => true
			),
			'covers' => array(
				'class' => 'album',
				'join_table' => $KOKEN_DATABASE['prefix'] . 'join_albums_covers',
				'other_field' => 'cover',
				'join_self_as' => 'cover',
				'join_other_as' => 'album'
			)
		);
		parent::__construct($id);
		$this->load->helper('file');
		$this->load->helper('directory');
    }

	function clear_cache()
	{
		delete_files(FCPATH . 'storage' . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . $this->cache_path, true, 1);
	}

	function do_delete()
	{
		$a = new Album();
		$previews = $a->where_related('cover', 'id', $this->id)->get_iterated();
		foreach($previews as $a)
		{
			$a->reset_covers();
		}

		$albums = $a->where_related('content', 'id', $this->id)->get_iterated();
		foreach($albums as $a)
		{
			$a->update_counts();
		}

		$this->clear_cache();
		$original = $this->path_to_original();
		unlink($original);
		if ($this->file_type > 0 && is_dir($original . '_previews'))
		{
			delete_files($original . '_previews', true, 1);
		}
		Shutter::hook('content.delete', $this->to_array(array('auth' => true)));
		$this->delete();
	}

	function set_type()
	{
		$image_types = array('jpg', 'jpeg', 'png', 'gif');
		$audio_types = array('mp3');
		$info = pathinfo($this->filename);
		$ext = strtolower($info['extension']);

		switch(true)
		{
			case in_array($ext, $image_types):
				return 0;
				break;

			case in_array($ext, $audio_types):
				return 2;
				break;

			default:
				return 1;
				break;
		}
	}

	function path_to_original()
	{
		if (!in_array('path', $this->fields))
		{
			$this->_set_paths();
		}
		return FCPATH . 'storage' .
				DIRECTORY_SEPARATOR . 'originals' .
				DIRECTORY_SEPARATOR . $this->path .
				DIRECTORY_SEPARATOR . $this->filename;
	}

	function parse_captured($iptc, $exif) {
		$captured_on = null;

		if (isset($exif['EXIF']['DateTimeDigitized']))
		{
			$dig = $exif['EXIF']['DateTimeDigitized'];
		}
		else if (isset($exif['EXIF']['DateTimeOriginal']))
		{
			$dig = $exif['EXIF']['DateTimeOriginal'];
		}

		if (isset($dig) && preg_match('/\d{4}:\d{2}:\d{2} \d{2}:\d{2}:\d{2}$/', $dig))
		{
			$bits = explode(' ', $dig);
			$captured_on = strtotime(str_replace(':', '-', $bits[0]) . ' ' . $bits[1]);
		}
		else if (!empty($iptc['2#055'][0]) && !empty($iptc['2#060'][0]))
		{
			$captured_on = strtotime($iptc['2#055'][0] . ' ' . $iptc['2#060'][0]);
		}
		return $captured_on;
	}

	function compute_cache_size($w, $h, $square)
	{
		if ($this->file_type > 0)
		{
			if (empty($this->lg_preview))
			{
				return false;
			}
			else
			{
				$preview_file = array_shift(explode(':', $this->lg_preview));
				if ($preview_file == 'waveform.svg')
				{
					return array($w, $h);
				}
				$preview_path = $this->path_to_original() . '_previews' . DIRECTORY_SEPARATOR . $preview_file;
				list($original_width, $original_height) = getimagesize($preview_path);
				$original_aspect = $original_width/$original_height;
			}
		}
		else
		{
			$original_width = $this->width;
			$original_height = $this->height;
			$original_aspect = $this->aspect_ratio;
		}

		if ($square)
		{
			return array($w, $h);
		}
		else
		{
			$target_aspect = $w/$h;
			if ($original_aspect >= $target_aspect)
			{
				if ($w > $original_width)
				{
					return array($original_width, $original_height);
				}
				else
				{
					return array($w, round(($w*$original_height)/$original_width));
				}
			}
			else
			{
				if ($h > $original_height)
				{
					return array($original_width, $original_height);
				}
				else
				{
					return array(round(($h*$original_width)/$original_height), $h);
				}
			}
		}
	}

	function to_array($options = array())
	{
		$options['auth'] = isset($options['auth']) ? $options['auth'] : false;
		$options['in_album'] = isset($options['in_album']) ? $options['in_album'] : false;

		// TODO: Show albums content is a member of? Perhaps /content/n/albums?
		$exclude = array('deleted', 'exif_make', 'exif_model', 'exif_iso', 'exif_camera_serial', 'exif_camera_lens', 'featured_order', 'favorite_order', 'old_slug');
		$bools = array('featured', 'favorite');
		$dates = array('uploaded_on', 'modified_on', 'captured_on', 'featured_on', 'file_modified_on');
		list($data, $fields) = $this->prepare_for_output($options, $exclude, $bools, $dates);

		if (!$data['featured'])
		{
			unset($data['featured_on']);
		}

		if (!$data['favorite'])
		{
			unset($data['favorited_on']);
		}

		$koken_url_info = $this->config->item('koken_url_info');

		if ($options['auth'] || $data['file_type'] != 0 || (int) $data['max_download'] === 1)
		{
			$path = 'storage/originals/' . str_replace(DIRECTORY_SEPARATOR, '/', $this->path) . '/' . $this->filename;
			$url = $koken_url_info->base . $path;
			$url_info = parse_url($url);
			$data['original'] = array(
				'url' => $url,
				'relative_url' => $url_info['path'],
				'width' => (int) $this->width,
				'height' => (int) $this->height
			);
		}

		if (!$options['auth'] && $data['visibility'] == 0) {
			unset($data['internal_id']);
		}

		$info = pathinfo($this->filename);

		$mimes = array(
			'jpg' => 'image/jpeg',
			'jpeg' => 'image/jpeg',
			'gif' => 'image/gif',
			'png' => 'image/png',
			'flv' => 'video/x-flv',
			'f4v' => 'video/f4v',
			'swf' => 'application/x-shockwave-flash',
			'mov' => 'video/mp4',
			'mp4' => 'video/mp4',
			'm4v' => 'video/x-m4v',
			'3gp' => 'video/3gpp',
			'3g2' => 'video/3gpp2',
			'mp3' => 'audio/mpeg'
		);

		$data['__koken__'] = 'content';

		if (array_key_exists(strtolower($info['extension']), $mimes)) {
			$data['mime_type'] = $mimes[strtolower($info['extension'])];
		} else if (function_exists('mime_content_type')) {
			$data['mime_type'] = mime_content_type($this->path_to_original());
		} else {
			$data['mime_type'] = '';
		}

		if (!isset($options['include_presets']) || $options['include_presets'])
		{
			include_once(FCPATH . 'app' . DIRECTORY_SEPARATOR . 'koken' . DIRECTORY_SEPARATOR . 'darkroom.php');

			if ($this->file_type > 0 && empty($this->lg_preview))
			{
				$prefix = $koken_url_info->base . 'admin/images/no-thumb';
				$data['cache_path'] = array(
					'prefix' => $prefix,
					'extension' => 'png'
				);

				$data['presets'] = array();
				foreach(Darkroom::$presets as $name => $opts)
				{
					$h = ($opts['width'] * 2) / 3;
					$data['presets'][$name] = array(
						'url' => $koken_url_info->base . "admin/images/no-thumb,$name.png",
						'width' => $opts['width'],
						'height' => round($h)
					);

					$data['presets'][$name]['cropped'] = array(
						'url' => $koken_url_info->base . "admin/images/no-thumb,$name.crop.png",
						'width' => $opts['width'],
						'height' => $opts['width']
					);
				}
			}
			else
			{
				$prefix = preg_replace("/\.{$info['extension']}$/", '', $this->filename);
				$cache_base = $koken_url_info->base . (KOKEN_REWRITE ? 'storage/cache/images' : 'i.php?') . '/' . str_replace('\\', '/', $this->cache_path) . '/' . $prefix;

				$data['cache_path'] = array(
					'prefix' => $cache_base
				);

				if ($this->file_type != 0)
				{
					$preview_file = array_shift(explode(':', $this->lg_preview));
					$info = pathinfo($preview_file);
				}

				$data['cache_path']['extension'] = $info['extension'] . '?' . $this->file_modified_on;
				$data['presets'] = array();
				foreach(Darkroom::$presets as $name => $opts)
				{
					$dims = $this->compute_cache_size($opts['width'], $opts['height'], 0);
					if ($dims)
					{
						list($w, $h) = $dims;
						$data['presets'][$name] = array(
							'url' => $cache_base . ",$name.{$info['extension']}?{$this->file_modified_on}",
							'hidpi_url' => $cache_base . ",$name.2x.{$info['extension']}?{$this->file_modified_on}",
							'width' => (int) $w,
							'height' => (int) $h
						);

						$data['presets'][$name]['cropped'] = array(
							'url' => $cache_base . ",$name.crop.{$info['extension']}?{$this->file_modified_on}",
							'hidpi_url' => $cache_base . ",$name.crop.2x.{$info['extension']}?{$this->file_modified_on}",
							'width' => (int) $opts['width'],
							'height' => (int) $opts['height']
						);
					}
				}
			}
		}

		if ($data['file_type'] == 0)
		{
			unset($data['duration']);
		}

		if (array_key_exists('duration', $data))
		{
			$r = $data['duration'];
			$data['duration'] = array();
			$data['duration']['raw'] = $r;

			$m = floor($r/60);
			$s = str_pad(floor($r%60), 2, '0', STR_PAD_LEFT);

			if ($m > 60)
			{
				$h = floor($m/60);
				$m = str_pad(floor($m%60), 2, '0', STR_PAD_LEFT);
				$m = "$h:$m";
			}

			$data['duration']['clean'] = "$m:$s";
		}

		if (array_key_exists('iptc', $data))
		{
			list($data['iptc'], $data['iptc_fields']) = $this->iptc_to_human($data['iptc']);
		}

		if (array_key_exists('exif', $data))
		{
			if (!isset($options['exif']))
			{
				$options['exif'] = 'all';
			}
			list($data['exif'], $data['exif_fields']) = $this->exif_to_human($data['exif'], $options['exif']);
			if (!empty($this->exif_camera_lens) && ($options['exif'] === 'all' || $options['exif'] === 'core' || strpos($options['exif'], ',lens') !== false))
			{
				$data['exif'][] = array('key' => 'lens', 'label' => 'Lens', 'raw' => $this->exif_camera_lens);
				$data['exif_fields'][] = 'lens';
			}
		}

		if (array_key_exists('file_type', $data))
		{
			switch($data['file_type'])
			{
				// TODO: Make this array and include mime type? Ehhh?
				case 0:
					$data['file_type'] = 'image';
					break;
				case 1:
					$data['file_type'] = 'video';
					break;
				case 2:
					$data['file_type'] = 'audio';
					break;
			}
		}

		if (array_key_exists('visibility', $data))
		{
			switch($data['visibility'])
			{
				case 1:
					$raw = 'unlisted';
					break;
				case 2:
					$raw = 'private';
					break;
				default:
					$raw = 'public';
					break;
			}

			$data['visibility'] = array(
				'raw' => $raw,
				'clean' => ucwords($raw)
			);
		}

		if (array_key_exists('max_download', $data))
		{
			switch($data['max_download'])
			{
				case 0:
					$data['max_download'] = 'none';
					$clean = 'None';
					break;
				case 1:
					$data['max_download'] = 'original';
					$clean = 'Original';
					break;
				case 2:
					$data['max_download'] = 'huge';
					$clean = 'Huge (2048)';
					break;
				case 3:
					$data['max_download'] = 'xlarge';
					$clean = 'X-Large (1600)';
					break;
				case 4:
					$data['max_download'] = 'large';
					$clean = 'Large (1024)';
					break;
				case 5:
					$data['max_download'] = 'medium_large';
					$clean = 'Medium-Large (800)';
					break;
				case 6:
					$data['max_download'] = 'medium';
					$clean = 'Medium (480)';
					break;
			}
			$data['max_download'] = array(
				'raw' => $data['max_download'],
				'clean' => $clean
			);
		}

		if (array_key_exists('license', $data))
		{
			if ($data['license'] == 'all')
			{
				$clean = '© All rights reserved';
			}
			else
			{
				// Data is stored as commercial,modifications ... (y|n),(y,s,n)
				// Example: NonCommercial ShareAlike == n,s
				list($commercial, $mods) = explode(',', $data['license']);

				$license_url = 'http://creativecommons.org/licenses/by';

				if ($commercial == 'y')
				{
					$clean = 'Commercial';
				}
				else
				{
					$license_url .= '-nc';
					$clean = 'NonCommercial';
				}

				switch($mods)
				{
					case 'y':
						// Nothing to do here, standard license
						break;
					case 's':
						$clean .= '-ShareAlike';
						$license_url .= '-sa';
						break;
					case 'n':
						$clean .= '-NoDerivs';
						$license_url .= '-nd';
						break;
				}

				$license_url .= '/3.0/deed.en_US';
			}
			$data['license'] = array(
				'raw' => $data['license'],
				'clean' => $clean
			);

			if (isset($license_url))
			{
				$data['license']['url'] = $license_url;
			}

			@$exif = $this->_unserialize($this->exif);

			$data['geolocation'] = false;

			if (isset($exif['GPS']['GPSLatitude']))
			{
				include_once(FCPATH . 'app' . DIRECTORY_SEPARATOR . 'koken' . DIRECTORY_SEPARATOR . 'gps.php');
				$gps = new GPS($exif['GPS']);
				$data['geolocation'] = array(
					'latitude' => $gps->latitude(),
					'longitude' => $gps->longitude()
				);
			}
		}

		// TODO: include_related
		$this->categories->get_iterated();
		$data['categories'] = array();
		foreach($this->categories as $category)
		{
			$data['categories'][] = array_merge($category->to_array(), array('__koken__' => 'category_contents'));
		}

		if (isset($options['order_by']) && in_array($options['order_by'], array( 'uploaded_on', 'modified_on', 'captured_on' )))
		{
			$data['date'] =& $data[ $options['order_by'] ];
		}
		else
		{
			$data['date'] =& $data['captured_on'];
		}

		if ($data['visibility'] === 'private')
		{
			$data['url'] = false;
		}
		else
		{
			$data['url'] = $this->url(array(
				'date' => $data['captured_on'],
				'album' => $options['in_album']
			));

			if ($data['url'])
			{
				list($data['__koken_url'], $data['url']) = $data['url'];
				list(,$data['canonical_url']) = $this->url(array(
					'date' => $data['captured_on']
				));
			}
		}

		if (!$options['auth'] && $data['visibility'] === 'unlisted') {
			unset($data['url']);
		}

		return Shutter::filter('api.content', array( $data, $this, $options ));
	}

	function greatest_common_denominator($a, $b)
	{
	    if ($b === 0) return $a;
    	return $this->greatest_common_denominator($b, ($a % $b));
	}

	function simplify_fraction($a, $b) {
	    while(($gcd = $this->greatest_common_denominator($a, $b)) > 1) {
	        $a /= $gcd;
	        $b /= $gcd;
	    }
	    return "$a/$b";
	}

	function iptc_to_human($iptc)
	{
		$mappings = array(
			'byline' 		=> array(
				'label' => 'Byline',
				'index' => '080'
			),
			'byline_title' 	=> array(
				'label' => 'Byline title',
				'index' => '085'
			),
			'caption' 		=> array(
				'label' => 'Caption',
				'index' => '120'
			),
			'category' 		=> array(
				'label' => 'Category',
				'index' => '050'
			),
			'city' 			=> array(
				'label' => 'City',
				'index' => '090'
			),
			'country' 		=> array(
				'label' => 'Country',
				'index' => '101'
			),
			'copyright' 	=> array(
				'label' => 'Copyright',
				'index' => '116'
			),
			'contact' 		=> array(
				'label' => 'Contact',
				'index' => '118'
			),
			'credit' 		=> array(
				'label' => 'Credit',
				'index' => '110'
			),
			'headline' 		=> array(
				'label' => 'Headline',
				'index' => '105'
			),
			'keywords' 		=> array(
				'label' => 'Keywords',
				'index' => '025'
			),
			'source' 		=> array(
				'label' => 'Source',
				'index' => '115'
			),
			'state' 		=> array(
				'label' => 'State',
				'index' => '095'
			),
			'title' 		=> array(
				'label' => 'Title',
				'index' => '005'
			)
		);

		@$iptc = $this->_unserialize($iptc);

		if (!$iptc || empty($iptc))
		{
			return array(array(), array());
		}
		else
		{
			$final = $keys = array();

			foreach($mappings as $name => $options)
			{
				$index = "2#{$options['index']}";
				if (isset($iptc[$index]))
				{
					$value = $iptc[$index];
					if (is_array($value))
					{
						$value = $value[0];
					}
					$keys[] = $name;
					$final[] = array(
						'label' => $options['label'],
						'value' =>  $value,
						'key' => $name
					);
				}
			}
			return array($final, $keys);
		}
	}

	function exif_to_human($exif, $include = 'all')
	{
		$mappings = array(
			'make' => array(
				'label' => 'Camera make',
				'field' => "IFD0.Make"
			),
			'model' => array(
				'label' => 'Camera',
				'field' => "IFD0.Model",
				'core' => true
			),
			'image_description' => array(
				'label' => 'Description',
				'field' => "IFD0.ImageDescription"
			),
			'aperture' => array(
				'label' => 'Aperture',
				'field' => "EXIF.FNumber",
				'divide' => true,
				'pre' => 'f/',
				'core' => true
			),
			'exposure' => array(
				'label' => 'Exposure',
				'field' => "EXIF.ExposureTime",
				'divide' => true,
				'post' => ' sec',
				'core' => true
			),
			'exposure_bias' => array(
				'label' => 'Exposure bias',
				'field' => "EXIF.ExposureBiasValue",
				'divide' => true,
				'post' => ' EV'
			),
			'exposure_mode' => array(
				'label' => 'Exposure mode',
				'field' => "EXIF.ExposureMode",
				'values' => array(
					0 => 'Easy shooting',
					1 => 'Program',
					2 => 'Shutter priority',
					3 => 'Aperture priority',
					4 => 'Manual',
					5 => 'A-DEP'
				)
			),
			'flash' => array(
				'label' => 'Flash',
				'field' => 'EXIF.Flash',
				'boolean' => array(
					'test' => array(0,16,24,32),
					'result' => false
				),
				'values' => array(
					0 => 'No Flash',
					1 => 'Flash',
					5 => 'Flash, strobe return light not detected',
					7 => 'Flash, strob return light detected',
					9 => 'Compulsory Flash',
					13 => 'Compulsory Flash, Return light not detected',
					16 => 'No Flash',
					24 => 'No Flash',
					25 => 'Flash, Auto-Mode',
					29 => 'Flash, Auto-Mode, Return light not detected',
					31 => 'Flash, Auto-Mode, Return light detected',
					32 => 'No Flash',
					65 => 'Red Eye',
					69 => 'Red Eye, Return light not detected',
					71 => 'Red Eye, Return light detected',
					73 => 'Red Eye, Compulsory Flash',
					77 => 'Red Eye, Compulsory Flash, Return light not detected',
					79 => 'Red Eye, Compulsory Flash, Return light detected',
					89 => 'Red Eye, Auto-Mode',
					93 => 'Red Eye, Auto-Mode, Return light not detected',
					95 => 'Red Eye, Auto-Mode, Return light detected'
				)
			),
			'focal_length' => array(
				'label' => 'Focal length',
				'field' => "EXIF.FocalLength",
				'divide' => true,
				'post' => 'mm',
				'core' => true
			),
			'iso_speed_ratings' => array(
				'label' => 'ISO',
				'field' => 'EXIF.ISOSpeedRatings',
				'core' => true,
				'pre' => 'ISO '
			),
			'metering_mode' => array(
				'label' => 'Metering mode',
				'field' => 'EXIF.MeteringMode',
				'values' => array(
					0 => 'Unknown',
					1 => 'Average',
					2 => 'Center Weighted Average',
					3 => 'Spot',
					4 => 'Multi-Spot',
					5 => 'Multi-Segment',
					6 => 'Partial',
					255 => 'Other'
				)
			),
			'white_balance' => array(
				'label' => 'White balance',
				'field' => 'EXIF.WhiteBalance',
				'values' => array(
					0 => 'Auto',
					1 => 'Sunny',
					2 => 'Cloudy',
					3 => 'Tungsten',
					4 => 'Fluorescent',
					5 => 'Flash',
					6 => 'Custom',
					129 => 'Manual'
				)
			)
		);

		$exif = $this->_unserialize($exif);

		if (!$exif || empty($exif)) {
			return array(array(), array());
		}
		else
		{
			if (strpos($include, ','))
			{
				$include = explode(',', $include);
			}
			$final = $keys = array();
			$defaults = array(
				'divide' => false,
				'pre' => '',
				'post' => '',
				'values' => false,
				'boolean' => false,
				'core' => false
			);
			foreach($mappings as $property => $options)
 			{
 				$options = array_merge($defaults, $options);
 				if (is_array($include) && !in_array($property, $include))
 				{
 					continue;
 				}
 				else if ($include == 'core' && !$options['core'])
 				{
 					continue;
 				}

				$bits = explode('.', $options['field']);
				if (isset($exif[$bits[0]][$bits[1]]))
				{
					$value = $exif[$bits[0]][$bits[1]];
					if (is_array($value))
					{
						$value = $value[0];
					}

					if ($options['divide'])
					{
						list($n, $d) = explode('/', $value);
						if ($d < 1)
						{
							$result = $value = 0;
						}
						else
						{
							$result = round($n / $d, 6);
							$value = $this->simplify_fraction($n, $d);
						}

						if ($property !== 'exposure' || $result >= 1)
						{
							$clean = $result;
						}
						else
						{
							$clean = $value;
						}
					}
					else if ($options['values'] && isset($options['values'][(int) $value]))
					{
						$clean = $options['values'][(int) $value];
					} else {
						$value = trim($value);
						if (!empty($options['pre']) || !empty($options['post']))
						{
							$clean = $value;
						}
					}
					if ($options['boolean'])
					{
						$result = isset($options['boolean']['true']) ? true : false;
						$test = in_array($value, $options['boolean']['test']);
						if ($test)
						{
							$bool = $options['boolean']['result'];
						}
						else
						{
							$bool = !$options['boolean']['result'];
						}
					}
					$arr = array();
					$arr['key'] = $property;
					$arr['label'] = $options['label'];
					$arr['raw'] = $value;
					if (isset($clean))
					{
						$arr['computed'] = $clean;
						$arr['clean'] = $options['pre'] . $clean . $options['post'];
						unset($clean);
					}
					if (isset($bool))
					{
						$arr['bool'] = $bool;
						unset($bool);
					}

					$final[] = $arr;
					$keys[] = $property;
				}
			}
			return array($final, $keys);
		}
	}

	function listing($params, $id = false)
	{
		$options = array(
			'order_by' => 'captured_on',
			'order_direction' => 'DESC',
			'search' => false,
			'search_filter' => false,
			'tags' => false,
			'tags_not' => false,
			'page' => 1,
			'match_all_tags' => false,
			'limit' => 100,
			'include_presets' => true,
			'featured' => null,
			'types' => false,
			'auth' => false,
			'favorites' => null,
			'after' => false,
			'category' => false,
			'category_not' => false,
			'year' => false,
			'year_not' => false,
			'month' => false,
			'month_not' => false,
			'in_album' => false
		);
		$options = array_merge($options, $params);

		if ($options['featured'] == 1 && !isset($params['order_by']))
		{
			$options['order_by'] = 'featured_on';
		}
		else if ($options['favorites'] == 1 && !isset($params['order_by']))
		{
			$options['order_by'] = 'favorited_on';
		}
		if ($options['auth'])
		{
			if (isset($options['visibility']))
			{
				$values = array('public', 'unlisted', 'private');
				if (in_array($options['visibility'], $values))
				{
					$options['visibility'] = array_search($options['visibility'], $values);
				}
				else
				{
					$options['visibility'] = false;
				}
			}
			else
			{
				$options['visibility'] = false;
			}
		}
		else if ($options['in_album'])
		{
			$options['visibility'] = 'album';
		}
		else
		{
			$options['visibility'] = 0;
		}
		if ($options['order_by'] == 'dimension')
		{
			$options['order_by'] = 'width * height';
		}
		if (is_numeric($options['limit']) && $options['limit'] > 0)
		{
			$options['limit'] = min($options['limit'], 100);
		}
		else
		{
			$options['limit'] = 100;
		}
		if ($options['types'])
		{
			$types = explode(',', str_replace(' ', '', $options['types']));
			$this->group_start();
			foreach($types as $t)
			{
				switch($t)
				{
					case 'photo':
						$this->or_where('file_type', 0);
						break;

					case 'video':
						$this->or_where('file_type', 1);
						break;

					case 'audio':
						$this->or_where('file_type', 2);
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
				$this->or_like('caption', urldecode($options['search']), 'both');
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
		if (!is_null($options['featured']))
		{
			$this->where('featured', $options['featured']);
		}
		if (!is_null($options['favorites']))
		{
			$this->where('favorite', $options['favorites']);
		}
		if ($options['category'])
		{
			$this->where_related('category', 'id', $options['category']);
		}
		else if ($options['category_not'])
		{
			$cat = new Content;
			$cat->select('id')->where_related('category', 'id', $options['category_not'])->get_iterated();
			$cids = array();
			foreach($cat as $c)
			{
				$cids[] = $c->id;
			}
			$this->where_not_in('id', $cids);
		}
		if ($options['after'])
		{
			$this->where($options['order_by'] . ' >=', $options['after']);
		}
		if ($options['visibility'] === 'album')
		{
			$this->where('visibility <', 2);
		}
		else if ($options['visibility'] !== false)
		{
			$this->where('visibility', $options['visibility']);
		}
		if ($id)
		{
			$sql_order = "ORDER BY FIELD(id,$id)";
			$id = explode(',', $id);
			$this->where_in('id', $id);
		}

		if ($options['order_by'] === 'captured_on' || $options['order_by'] === 'uploaded_on' || $options['order_by'] === 'modified_on')
		{
			$bounds_order = $options['order_by'];
		}
		else
		{
			$bounds_order = 'captured_on';
		}

		// Do this before date filters are applied
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

		if (in_array($options['order_by'], array('captured_on', 'uploaded_on', 'modified_on')))
		{
			$date_col = $options['order_by'];
		}
		else
		{
			$date_col = 'captured_on';
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

		$vid_count = $this->get_clone()->where('file_type', 1)->count();
		$aud_count = $this->get_clone()->where('file_type', 2)->count();

		$final = $this->paginate($options);
		$final['dates'] = $dates;

		if ($id && !isset($params['order_by']))
		{
			$q = explode('LIMIT', $this->get_sql());
			$query = $q[0] . $sql_order . ' LIMIT ' . $q[1];
			$data = $this->query( $query );
		}
		else
		{
			$data = $this->order_by($options['order_by'] . ' ' . $options['order_direction'])
					->order_by('id ' . $options['order_direction'])->get_iterated();
		}

		if (!$options['limit'])
		{
			$final['per_page'] = $data->result_count();
			$final['total'] = $data->result_count();
		}

		$final['counts'] = array(
			'videos' => $vid_count,
			'audio' => $aud_count,
			'images' => $final['total'] - $vid_count - $aud_count,
			'total' => $final['total']
		);

		$final['content'] = array();
		foreach($data as $content)
		{
			$final['content'][] = $content->to_array($options);
		}
		return $final;
	}
}

/* End of file content.php */
/* Location: ./application/models/content.php */