<?php

class Update extends Koken_Controller {

	var $migrate_path;

	function __construct()
    {
    	$this->strict_cookie_auth = false;
    	$this->migrate_path = FCPATH . 'app' . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'migrations' . DIRECTORY_SEPARATOR;
        parent::__construct();
    }

    function _download($f, $to)
    {
    	if (extension_loaded('curl')) {
    		$cp = curl_init($f);
    		$fp = fopen($to, "w+");
    		if (!$fp) {
    			curl_close($cp);
    			return false;
    		} else {
    			curl_setopt($cp, CURLOPT_FILE, $fp);
    			curl_exec($cp);
    			curl_close($cp);
    			fclose($fp);
    		}
    	} elseif (ini_get('allow_url_fopen')) {
    		if (!copy($f, $to)) {
    			return false;
    		}
    	}
    	return true;
    }

    function theme()
    {
    	if ($this->method !== 'post')
		{
			$this->error('403', 'Forbidden');
		}

    	$local = $this->input->post('local_path');
    	$guid = $this->input->post('guid');

    	$themes = FCPATH . 'storage' .
    					DIRECTORY_SEPARATOR . 'themes';

    	$tmp_path = FCPATH . 'storage' .
    					DIRECTORY_SEPARATOR . 'tmp';

    	$remote = 'http://store.koken.me/themes/download/' . $guid;
    	$local_zip = $tmp_path . DIRECTORY_SEPARATOR . 'update.zip';

    	$updated_theme = $tmp_path . DIRECTORY_SEPARATOR . $guid;
    	$current_path = $themes . DIRECTORY_SEPARATOR . $local;

    	if (is_dir("$current_path.off"))
    	{
			delete_files("$current_path.off", true, 1);
    	}

    	if (is_dir("$current_path/.git"))
    	{
			delete_files("$current_path/.git", true, 1);
    	}

    	make_child_dir($tmp_path);

    	$this->load->helper('file');

    	$success = false;
    	if ($this->_download($remote, $local_zip))
    	{
    		$this->load->library('unzip');
    		$this->unzip->extract($local_zip);

    		if (file_exists($updated_theme . DIRECTORY_SEPARATOR . 'info.json'))
    		{
	    		if (rename($current_path, "$current_path.off"))
	    		{
	    			if (rename($updated_theme, $current_path))
	    			{
	    				delete_files("$current_path.off", true, 1);
	    				$success = true;
	    			}
	    			else
	    			{
	    				// ERROR
	    			}
	    		}
	    		else
	    		{
	    			// ERROR
	    		}
    		}
    		else
    		{
    			// ERROR
    		}
    	}

    	unlink($local_zip);
    	delete_files($updated_theme, true, 1);
    	sleep(2);
	    die( json_encode( array('done' => $success ) ) );

    }

    function migrate($n = false)
    {
    	if ($this->method !== 'post')
		{
			$this->error('403', 'Forbidden');
		}

    	require(FCPATH . 'storage' .
    						DIRECTORY_SEPARATOR . 'configuration' .
    						DIRECTORY_SEPARATOR . 'database.php');

    	$CI =& get_instance();
    	$this->db =& $CI->db;

    	$this->load->dbforge();

    	if ($n === 'schema')
    	{
    		require(FCPATH . 'app' .
    							DIRECTORY_SEPARATOR . 'koken' .
    							DIRECTORY_SEPARATOR . 'schema.php');

    		foreach($koken_tables as $table_name => $info)
    		{
    			$table = $KOKEN_DATABASE['prefix'] . "$table_name";
    			if ($this->db->table_exists($table))
    			{
    				$existing_fields = array();
    				foreach($this->db->field_data($table) as $field)
    				{
    					$existing_fields[$field->name] = $field;
    				}

    				foreach($info['fields'] as $field_name => $field_info)
    				{
    					if (array_key_exists($field_name, $existing_fields))
    					{
    						$field_info['type'] = strtolower($field_info['type']);
    						$compare = (array) $existing_fields[$field_name];
    						$diff = array_diff( $field_info, $compare );
    						if (isset($diff['null']) && $diff['null'] === true && is_null($compare['default']))
    						{
    							unset($diff['null']);
    						}
    						if (!empty( $diff ))
    						{
    							$this->dbforge->modify_column($table, array($field_name => $field_info));
    						}
    					}
    					else
    					{
    						$this->dbforge->add_column($table, array( $field_name => $field_info ));
    					}
    				}

    				if (isset($info['keys']))
    				{
	    				foreach($info['keys'] as $key)
	    				{
	    					if (is_array($key))
	    					{
	    						$key_name = $this->db->_protect_identifiers(implode('_', $key));
	    						$key = $this->db->_protect_identifiers($key);
	    					}
	    					else
	    					{
	    						$key_name = $this->db->_protect_identifiers($key);
	    						$key = array($key_name);
	    					}

	    					$sql = "ALTER TABLE $table ADD KEY {$key_name} (" . implode(', ', $key) . ")";
	    					$this->db->query($sql);
	    				}
    				}
    			}
    			else
    			{
	    			if (!isset($info['no_id']))
	    			{
	    				$this->dbforge->add_field('id');
	    			}
	    			$this->dbforge->add_field($info['fields']);
	    			if (isset($info['keys']))
	    			{
		    			foreach($info['keys'] as $key)
		    			{
		    				$primary = false;
		    				if ($key == 'id')
		    				{
		    					$primary = true;
		    				}
		    				$this->dbforge->add_key($key, $primary);
		    			}
	    			}
	    			$this->dbforge->create_table($KOKEN_DATABASE['prefix'] . "$table_name");
    			}
    		}

    		$s = new Setting;
    		$s->where('name', 'uuid')->get();

    		if (!$s->exists())
    		{
    			$s = new Setting;
    			$s->name = 'uuid';
    			$s->value = md5($_SERVER['HTTP_HOST'] . uniqid('', true));
    			$s->save();
    		}

			$base_folder = trim(preg_replace('/\/api\.php(.*)?$/', '', $_SERVER['SCRIPT_NAME']), '/');

			include(FCPATH . 'app' . DIRECTORY_SEPARATOR . 'koken' . DIRECTORY_SEPARATOR . 'darkroom.php');

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

			$curl = curl_init();
			curl_setopt($curl, CURLOPT_URL, KOKEN_STORE_URL . '/register?domain=' . $_SERVER['HTTP_HOST'] . '&path=/' . $base_folder . '&uuid=' . $s->value . '&php=' . PHP_VERSION . '&version=' . KOKEN_VERSION . '&ip=' . $_SERVER['SERVER_ADDR'] . '&image_processing=' . urlencode($processing_string));
			curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 10);
			curl_setopt($curl, CURLOPT_HEADER, 0);
			curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
			$r = curl_exec($curl);
			curl_close($curl);

			$ht_path = FCPATH . '.htaccess';
			if (file_exists($ht_path))
			{
				$contents = file_get_contents($ht_path);
				$htaccess = create_htaccess();
				preg_match('/#MARK#.*/s', $htaccess, $match);
				$contents = preg_replace('/#MARK#.*/s', str_replace('$', '\\$', $match[0]), $contents);
				file_put_contents($ht_path, $contents);
			}

			$themes = array(
				'axis' => '86d2f683-9f90-ca3f-d93f-a2e0a9d0a089',
				'blueprint' => '1a355994-6217-c7ce-b67a-4241be3feae8',
				'boulevard' => 'b30686d9-3490-9abb-1049-fe419a211502',
				'chastain' => 'd174e766-5a5f-19eb-d735-5b46ae673a6d',
				'elementary' => 'be1cb2d9-ed05-2d81-85b4-23282832eb84',
				'madison' => '618e0b9f-fba0-37eb-810a-6d615d0f0e08',
				'observatory' => '605ea246-fa37-11f0-f078-d54c8a7cbd3c',
				'regale' => 'efde04b6-657d-33b6-767d-67af8ef15e7b',
				'repertoire' => 'fa8a5d39-01a5-dfd6-92ff-65a22af5d5ac'
			);

			$themes_dir = FCPATH .
							'storage' . DIRECTORY_SEPARATOR .
							'themes' . DIRECTORY_SEPARATOR;

			foreach($themes as $name => $guid)
			{
				$dir = $themes_dir . $name;
				$guid_path = $dir . DIRECTORY_SEPARATOR . 'koken.guid';
				$old_guid_path = $dir . DIRECTORY_SEPARATOR . '.guid';
				if (file_exists($old_guid_path))
				{
					rename($old_guid_path, $guid_path);
				}
				else if (is_dir($dir) && !file_exists($guid_path))
				{
					file_put_contents($guid_path, $guid);
				}
			}

			if (!isset($_COOKIE['koken_session']))
			{
				// Catch upgrades with old auth setup and try to keep them logged in.
				$u = new User;
				$u->get_by_id($this->auth_user_id);

				if ($u->exists())
				{
					setcookie(	'koken_session',
								serialize(
									array(
										'token' => $this->auth_token,
										'user' => $u->to_array()
									)
								),
								0,
								'/',
								null,
								false,
								true
							);
				}
			}

    		die( json_encode( array('done' => true) ) );
    	}
    	else if ($n)
    	{
    		$path = $this->migrate_path . "$n.php";
    		if (is_file($path))
    		{
    			include($path);
    			die( json_encode( array('done' => isset($done)) ) );
    			exit;
    		}
    	}
    }

	function index($v = false)
	{

		if ($this->method !== 'post')
		{
			$this->error('403', 'Forbidden');
		}

		function rollback($back)
		{
			foreach($back as $b)
			{
				$f = FCPATH . $b;
				@rename($f, str_replace('.off', '', $f));
			}
		}

		function fail($msg = 'Koken does not have the necessary permissions to perform the update automatically. Try setting the permissions on the entire Koken folder to 777, then try again.')
		{
			die( json_encode( array('error' => $msg) ) );
		}

		if ($v) {

			if (ENVIRONMENT === 'development')
			{
				//hack
				sleep(2);

				// fail();

				die(
					json_encode(
						array('migrations' => array('0001.php', '0001.php', '0001.php')
						)
					)
				);
			}

			$old_mask = umask(0);

			// Anything with - means it is beta, rc, etc, so get edge release
			if (strpos($v, '-') !== false)
			{
				$fn = 'upgrade_edge';
			}
			else
			{
				$fn = 'upgrade';
			}

			$get_core = "http://install.koken.me/releases/$fn.zip";
			$get_lib = "http://install.koken.me/releases/pclzip.lib.txt";

			$core = FCPATH . 'core.zip';

			if ($this->_download($get_core, $core)) {

				$migrations_before = scandir($this->migrate_path);

				// Move these to off position in case we need to rollback
				$movers = array('admin', 'app', 'api.php', 'i.php', 'index.php', 'preview.php', 'dl.php');
				$moved = array();

				$this->load->helper('file');
				$this->load->library('unzip');

				foreach($movers as $m) {
					$path = FCPATH . $m;
					$to = $path . '.off';
					if (file_exists($to))
					{
						delete_files($to, true, 1);
					}
					if (rename($path, $to))
					{
						$moved[] = basename($to);
					}
					else
					{
						rollback($moved);
						umask($old_mask);
						fail();
					}
				}

				$this->unzip->extract($core);

				foreach($moved as $m)
				{
					$path = FCPATH . $m;
					if (is_dir($path))
					{
						delete_files($path, true, 1);
					}
					else if (file_exists($to))
					{
						unlink($path);
					}
				}

				@unlink($core);

				die(
					json_encode(
						array('migrations' => array_values(
							array_diff(
								scandir($this->migrate_path), $migrations_before)
							)
						)
					)
				);
			} else {
				umask($old_mask);
				@unlink($core);
				fail();
			}
		}
	}
}

/* End of file trashes.php */
/* Location: ./system/application/controllers/trashes.php */