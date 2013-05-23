<?php  if (!defined('BASEPATH')) exit('No direct script access allowed');

class Koken_Controller extends CI_Controller {

	var $method = 'get';
	var $auto_authenticate = true;
	var $strict_cookie_auth = true;
	var $callback = false;
	var $auth = false;
	var $auth_user_id;
	var $auth_token;
	var $auto_role;
	var $caching = true;
	var $cache_path;
	var $cache_path_to_file;

	// If not format is specified, JSON it is
	var $format = 'json';

	// We can't use CI's set_output, as it causes type coersion, so we'll use our own var for that
	var $response_data = array();

	function __construct()
    {
		parent::__construct();

		if (!$this->db->conn_id)
		{
			$this->error(500, 'Database connection failed. Make sure the database server is running and the information in storage / configuration / database.php is still correct.');
		}

		$path_info = $this->uri->uri_string();
    	$this->cache_path = FCPATH . 'storage' . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'api';
    	$stamp = $this->cache_path . DIRECTORY_SEPARATOR . 'stamp';
    	make_child_dir($this->cache_path);
    	$this->check_for_rewrite();

    	if (!file_exists($stamp))
    	{
    		touch($stamp);
    	}

    	$cache_stamp = filemtime($stamp);

    	$this->cache_path_to_file = $this->cache_path . DIRECTORY_SEPARATOR . md5( $path_info ) . '.cache';

		if ($this->input->is_cli_request())
		{
			$this->method = 'get';
		}
		else
		{
			$this->method = strtolower($_SERVER['REQUEST_METHOD']);
		}

		if ($this->auto_authenticate && is_array($this->auto_authenticate))
		{
			if (array_key_exists('exclude', $this->auto_authenticate))
			{
				$action = array_shift($this->uri->ruri_to_assoc(1));
				if (in_array($action, $this->auto_authenticate['exclude']))
				{
					$this->auto_authenticate = false;
				}
			}
		}

		if ($this->auto_authenticate)
		{
			$auth = $this->authenticate();
			if ($auth)
			{
				$this->auth = true;
				list($this->auth_user_id, $this->auth_token, $this->auth_role) = $auth;
	    		$this->cache_path_to_file = preg_replace('/.cache$/', '.auth.cache', $this->cache_path_to_file);
			}
		}

		if ($this->method === 'get')
		{
			if ($this->caching && file_exists($this->cache_path_to_file) && ENVIRONMENT === 'production')
			{
				$mtime = filemtime($this->cache_path_to_file);

				if ($mtime > $cache_stamp)
				{
					if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) && strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']) >= $mtime) {
						header("HTTP/1.1 304 Not Modified");
				    	exit;
					}
					$contents = file_get_contents($this->cache_path_to_file);
					if (!empty($contents) && json_decode($contents))
					{
						header('Content-type: application/json');
						header('Cache-control: must-revalidate');
						header('Last-Modified: ' . gmdate('D, d M Y H:i:s', filemtime($this->cache_path_to_file)) . ' GMT');
						die($contents);
					}
				}
			}
		}
		else
		{
			if ($this->auto_authenticate)
			{
				if (!$this->auth || $this->auth_role == 'read')
				{
					$this->error('401', 'Not authorized to perform this action.');
				}
			}

			if ($this->caching && ENVIRONMENT === 'production')
			{
				touch($stamp);
				$this->load->helper('file');
				delete_files(FCPATH . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'site', true, 1);
			}

			if (isset($_POST) && isset($_POST['_method']))
			{
				$this->method = strtolower($_POST['_method']);
				if (isset($_POST['model']))
				{
					$_POST = json_decode($_POST['model']);
				}
			}
		}

		$reflector = new ReflectionClass(get_class($this));
		if (basename($reflector->getFileName()) !== 'update.php')
		{
			$plugins = $this->parse_plugins();

			Shutter::finalize_plugins($plugins);
		}
    }

	function redirect($url)
	{
		if ($this->auth && $this->auth_role !== 'god')
		{
			$url .= '/token:' . $this->auth_token;
		}
		$info = $this->config->item('koken_url_info');

		header("Location: {$info->base}api.php?$url");
		exit;
	}

	function set_response_data($data)
	{
		$this->response_data = $data;
	}

	function add_to_history($message)
	{
		$h = new History();
		$h->message = $message;
		$h->save($this->auth_user_id);
	}

	function authenticate($require_king = false)
	{
		$token = false;
		$cookie = false;
		if (isset($_COOKIE['koken_session']) && isset($_SERVER['HTTP_X_KOKEN_AUTH']) && $_SERVER['HTTP_X_KOKEN_AUTH'] === 'cookie')
		{
			$cookie = unserialize($_COOKIE['koken_session']);
			$token = $cookie['token'];
		}
		else if ($this->method == 'get' && preg_match("/token:([a-zA-Z0-9]{32})/", $this->uri->uri_string(), $matches))
		{
			$token = $matches[1];
		}
		else if (isset($_REQUEST['token']))
		{
			$token = $_REQUEST['token'];
		}
		if ($token)
		{
			$a = new Application();
			$a->where('token', $token)->limit(1)->get();

			if ($a->exists())
			{
				if ($a->role === 'god' && $this->strict_cookie_auth)
				{
					if (!$cookie)
					{
						return false;
					}
				}
				else
				{
					if ($a->single_use)
					{
						$a->delete();
					}
				}
				return array($a->user_id, $token, $a->role);
			}
		}
		return false;
	}

	// Ignore $data, it will always be empty. Use our response_data var instead
	function _output($data, $code = 200)
	{
		switch($this->format)
		{
			// TODO: Other formats (XML, ATOM, Media RSS)?
			case 'php':
				$content_type = 'text/plain';
				$data = serialize($this->response_data);
				break;
			default:
				$data = json_encode($this->response_data);
				$content_type = 'json';
				if ($this->callback)
				{
					$content_type = 'javascript';
					$data = "{$this->callback}($data)";
				}
				$content_type = "application/$content_type";
				break;
		}
		set_status_header($code);
		header("Content-type: $content_type");

		if ($this->caching && isset($this->cache_path_to_file) && $this->cache_path_to_file && ENVIRONMENT === 'production' && $this->method === 'get' && (int) $code === 200)
		{
			file_put_contents($this->cache_path_to_file, $data);
			header('Cache-control: must-revalidate');
			header('Last-Modified: ' . gmdate('D, d M Y H:i:s', time()) . ' GMT');
		}

		die($data);
	}

	function parse_plugins()
	{
		$plugins = Shutter::plugins();

		$activated = new Plugin;
		$activated->get_iterated();
		$map = $final = array();

		foreach($activated as $active)
		{
			$map[$active->path] = array('id' => $active->id, 'setup' => $active->setup == 1, 'data' => unserialize($active->data));
		}

		foreach($plugins as $plugin)
		{
			if (isset($map[$plugin['path']]) || $plugin['internal'])
			{
				$plugin['activated'] = true;

				if ($plugin['internal'])
				{

				}
				else
				{
					$plugin['id'] = $map[$plugin['path']]['id'];
					$plugin['setup'] = $map[$plugin['path']]['setup'];
					$saved = $map[$plugin['path']]['data'];
				}

				if (isset($plugin['data']))
				{
					foreach($plugin['data'] as $key => &$d)
					{
						if (isset($saved[$key]))
						{
							$d['value'] = $saved[$key];
						}
					}
					if ($d['type'] === 'boolean' && isset($d['value']))
					{
						$d['value'] = $d['value'] == 'true';
					}
				}
			}
			else
			{
				$plugin['activated'] = false;
				$plugin['setup'] = false;
			}

			if (isset($plugin['php_class']))
			{
				$plug = new $plugin['php_class'];
				$plugin['compatible'] = $plug->is_compatible();
				if ($plugin['compatible'] !== true)
				{
					$plugin['compatible_error'] = $plugin['compatible'];
					$plugin['compatible'] = false;
				}
			}
			else
			{
				$plugin['compatible'] = true;
			}

			$final[] = $plugin;
		}

		return $final;
	}

	function error($code, $message = 'Error message not available.')
	{
		$this->response_data = array(
			'request' => $_SERVER['REQUEST_URI'],
			'error' => $message,
			'http' => $code
		);
		$this->_output('', $code);
	}

	function check_for_rewrite()
	{

		if (defined('KOKEN_REWRITE'))
		{
			return KOKEN_REWRITE;
		}

		if (!file_exists(FCPATH . '.htaccess') && strpos($_SERVER['SERVER_SOFTWARE'], 'Apache') === 0)
		{
			define('KOKEN_REWRITE', false);
			return false;
		}

		$cache = $this->cache_path . DIRECTORY_SEPARATOR . 'rewrite_check';

		if (file_exists($cache))
		{
			define('KOKEN_REWRITE', trim(file_get_contents($cache)) === 'on');
			return KOKEN_REWRITE;
		}

		$koken_url_info = $this->config->item('koken_url_info');

		$curl = curl_init();
		curl_setopt($curl, CURLOPT_URL, $koken_url_info->base . '__rewrite_test/');
		curl_setopt($curl, CURLOPT_HEADER, 0);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
		$return = trim(curl_exec($curl));
		$code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
		curl_close($curl);

		$rewrite_check = $code === 200 && $return === 'koken:rewrite';

		file_put_contents($cache, $rewrite_check ? 'on' : 'off');

		define('KOKEN_REWRITE', $rewrite_check);

		return $rewrite_check;
	}

	function parse_params($args)
	{
		$params = $id = array();
		$allowed_string_ids = array('trash');

		if (count($args))
		{
			foreach($args as $index => $arg)
			{
				if (strpos($arg, ':') !== false)
				{
					$bits = explode(':', $arg);
					if (strpos($bits[1], '&') !== false)
					{
						// Upload URLs have extra query string, remove it here
						$bits[1] = substr($bits[1], 0, strpos($bits[1], '&'));
					}

					$bits[1] = urldecode($bits[1]);

					switch($bits[0])
					{
						case 'size':
							$params['size'][] = $bits[1];
							break;
						case 'preview':
							$params['preview'] = $bits[1];
							break;
						case 'callback':
							$this->callback = $bits[1];
							break;
						case 'format':
							$this->format = $bits[1];
							break;
						default:
							$params[$bits[0]] = $bits[1];
							break;
					}
				}
				else if (is_numeric($arg) || strpos($arg, ',') !== FALSE || strlen($arg) == 32 || in_array($arg, $allowed_string_ids))
				{
					$id[] = $arg;
				}
			}
		}
		if (count($id) == 0)
		{
			$id = null;
		}
		else if (count($id) == 1)
		{
			$id = $id[0];
		}

		// Security
		unset($params['auth']);

		if (is_null($id) && isset($params['slug']))
		{
			$slug = $params['slug'];
			unset($params['slug']);
		}
		else
		{
			$slug = false;
		}

		if (isset($params['draft']))
		{
			define('DRAFT_CONTEXT', $params['draft']);
		}
		return array($params, $id, $slug);
	}

}
