<?php

class Plugins extends Koken_Controller {

	function __construct()
    {
		$this->caching = false;
		parent::__construct();
    }

	function call()
	{
		list($params, $id) = $this->parse_params(func_get_args());

		$p = new Plugin;
		$p->where('path', $params['plugin'])->get();

		if ($p->exists())
		{
			$this->set_response_data( $p->run_plugin_method($params['method'], $this->parse_plugins(), $params) );
		}
	}

	function index()
	{

		list($params, $id) = $this->parse_params(func_get_args());

		switch($this->method)
		{
			case 'delete':
				$p = new Plugin;
				$p->where('id', $id)->get();

				if ($p->exists())
				{
					$p->run_plugin_method('after_uninstall', $this->parse_plugins());
					$p->delete();
				}
				exit;
				break;

			case 'post':
				$p = new Plugin;
				$p->path = $_POST['path'];
				$p->setup = $p->run_plugin_method('require_setup', $this->parse_plugins()) === false;

				if ($p->save())
				{
					$p->run_plugin_method('after_install', $this->parse_plugins());
				}

				$this->redirect('/plugins');
				break;

			case 'put':
				unset($_POST['token']);
				unset($_POST['_method']);
				$data = serialize($_POST);
				$p = new Plugin;
				$p->where('id', $id)->get();
				$p->save_data($this->parse_plugins(), $_POST);

				$validate = $p->run_plugin_method('confirm_setup', $this->parse_plugins(), $data);

				if ($validate === true)
				{
					$p->setup = 1;
					$p->save();
					exit;
				}
				else
				{
					$this->set_response_data(array('error' => $validate));
				}
				break;

			default:
				$data = array( 'plugins' => $this->parse_plugins() );
				foreach($data['plugins'] as &$plugin)
				{
					if (isset($plugin['php_class']))
					{
						unset($plugin['php_class']);
					}
				}
				$this->set_response_data($data);
				break;
		}

	}
}

/* End of file system.php */
/* Location: ./system/application/controllers/plugins.php */