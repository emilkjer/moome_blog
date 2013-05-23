<?php

class System extends Koken_Controller {

	function __construct()
    {
         parent::__construct();
    }

	function index()
	{
		// TODO: require auth for this controller
		// director-core will need some reworking
		// if (!$this->auth)
		// {
		// 	$this->error('401', 'The action requires an authentication token.');
		// }

		list($params,) = $this->parse_params(func_get_args());

		include(FCPATH . 'app' . DIRECTORY_SEPARATOR . 'koken' . DIRECTORY_SEPARATOR . 'darkroom.php');
		include(FCPATH . 'app' . DIRECTORY_SEPARATOR . 'koken' . DIRECTORY_SEPARATOR . 'ffmpeg.php');

		$ffmpeg = new FFmpeg;

		list($version, $lib) = Darkroom::processing_library_version();
		if (!$version)
		{
			$processing_string = false;
		}
		else
		{
			if ($lib)
			{
				$processing_string = $lib . ' ' . $version;
			}
			else
			{
				$processing_string = 'ImageMagick ' . $version;
			}
		}

		function max_upload_to_bytes($val)
		{
			$val = strtolower($val);
			if (preg_match('/(k|m|g)/', $val, $match))
			{
				$val = (int) str_replace($match[1], '', $val);
				switch($match[1])
				{
					case 'k':
						$val *= 1024;
						break;

					case 'm':
						$val *= 1048576;
						break;

					case 'g':
						$val *= 1073741824;
						break;
				}
				return $val;
			}
			else
			{
				return (int) $val;
			}
		}

		$max_upload = max_upload_to_bytes( ini_get('upload_max_filesize') );
		$post_max = max_upload_to_bytes( ini_get('post_max_size') );

		$max = min($max_upload, $post_max);

		if ($max >= 1024)
		{
			$max_clean = ($max / 1024) . 'KB';
		}

		if ($max >= 1048576)
		{
			$max_clean = ($max / 1048576) . 'MB';
		}

		if ($max >= 1073741824)
		{
			$max_clean = ($max / 1073741824) . 'GB';
		}

		$c = new Content();
		$c->select_max('modified_on')->get();
		$a = new Album();
		$a->select_max('modified_on')->get();
		$t = new Text();
		$t->select_max('modified_on')->get();

		// TODO: Some of this info should be limited to authenticated sessions
		$data = array(
			'version' => KOKEN_VERSION,
			'operating_system' => PHP_OS,
			'memory_limit' => ini_get('memory_limit'),
			'auto_updates' => AUTO_UPDATE,
			'beta_builds' => ALLOW_BETA_BUILDS,
			'php_version' => PHP_VERSION,
			'exif_support' => is_really_callable('exif_read_data'),
			'iptc_support' => is_really_callable('iptcparse'),
			'image_processing_support' => $processing_string,
			'ffmpeg_support' => is_really_callable('exec') ? $ffmpeg->version() : false,
			'upload_limit' => $max,
			'upload_limit_clean' => $max_clean,
			'timestamp' => (int) max($c->modified_on, $a->modified_on, $t->modified_on),
			'rewrite_enabled' => $this->check_for_rewrite(),
			'mysql_version' => $this->db->call_function('get_server_info', $this->db->conn_id)
		);

		$this->set_response_data($data);
	}
}

/* End of file system.php */
/* Location: ./system/application/controllers/system.php */