<?php

class Plugin extends DataMapper {

	function _get_plugin($plugins)
	{
		foreach ($plugins as $plugin) {
			if ($plugin['path'] === $this->path)
			{
				return $plugin;
				break;
			}
		}
		return false;
	}
	function run_plugin_method($method, $plugins, $arg = false)
	{
		$plugin = $this->_get_plugin($plugins);
		if ($plugin && isset($plugin['php_class']))
		{
			$plug = new $plugin['php_class'];
			if (method_exists($plug, $method))
			{
				return $plug->$method($arg);
			}
		}
		return false;
	}

	function save_data($plugins, $data)
	{
		$plugin = $this->_get_plugin($plugins);

		if ($plugin && isset($plugin['data']))
		{
			$save_data = array();

			foreach($data as $name => $val)
			{
				if (isset($plugin['data'][$name]))
				{
					$info = $plugin['data'][$name];
					$save_data[$name] = $info['type'] === 'boolean' ? $val == 'true' : $val;
				}
			}

			$this->data = serialize($save_data);
		}
	}
}

/* End of file application.php */
/* Location: ./application/models/plugin.php */