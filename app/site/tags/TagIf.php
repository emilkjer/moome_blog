<?php

	class TagIf extends Tag {

		protected $allows_close = true;
		protected $js_condition;
		protected $can_live_update;
		protected $data_attrs;
		protected $condition;

		function generate()
		{
			switch(true)
			{

				case isset($this->parameters['empty']):
					$token = $this->field_to_keys('empty');
					$condition = "empty($token)";
					$js_condition = "$token.length";
					break;

				case isset($this->parameters['equals']):
					$token = $this->field_to_keys('data');
					$value = $this->parameters['equals'];
					$condition = $js_condition = "$token == '$value'";
					break;

				case isset($this->parameters['true']):
					$token = $this->field_to_keys('true');
					$condition = "isset($token) && (bool) $token";
					$js_condition = "$token";
					break;

				case isset($this->parameters['exists']):
					$token = $this->field_to_keys('exists');
					$condition = "isset($token)";
					$js_condition = "$token";
					break;

				case isset($this->parameters['false']):
					$token = $this->field_to_keys('false');
					$condition = "!isset($token) || !(bool) $token";
					$js_condition = "!$token";
					break;

				case isset($this->parameters['condition']):
					$condition = $js_condition = preg_replace_callback('/\{{1,2}\s*([a-z_.]+)\s*\}{1,2}/', array($this, 'condition_parse'), $this->parameters['condition']);
					break;

			}

			if (strpos($condition, 'settings') !== false && (!isset($this->parameters['live']) || $this->parameters['live'] != 'false'))
			{
				$this->can_live_update = true;
				$js_settings = preg_match_all('/\Koken::\$settings\[\'([^\']+)\'\]/', $js_condition, $matches);
				$this->data_attrs = ' data-' . join('="true" data-', $matches[1]) . '="true"';
				$this->js_condition = str_replace("'", "\'", preg_replace('/Koken::\$settings\[\'([^\']+)\'\]/', '$1', $js_condition));
			}
			else
			{
				$this->data_attrs = '';
				$this->can_live_update = false;
				$this->js_condition = '';
			}
			if (isset($this->parameters['_not']))
			{
				if ($this->js_condition && !empty($this->js_condition))
				{
					$this->js_condition = "!$js_condition";
				}
				$condition = "!($condition)";
			}

			if ($condition)
			{
				$this->condition = $condition;
				// TODO: Remove @ from here for production
				if (Koken::$draft && !Koken::$preview && $this->can_live_update)
				{
					return <<<DOC
<?php
	echo '<i class="k-control-structure"$this->data_attrs data-control-condition="$this->js_condition" style="display:' . (  @$condition ? 'inline': 'none' ) . '">';
?>
DOC;
				}
				else
				{
					return <<<DOC
<?php

	if (@$condition):

?>
DOC;
				}
			}

		}

		function do_else()
		{
			if (Koken::$draft &&!Koken::$preview && $this->can_live_update)
			{
				return <<<DOC
<?php
		echo '</i><i class="k-control-structure"$this->data_attrs data-control-condition="!($this->js_condition)" style="display:' . ( $this->condition ? 'none': 'inline' ) . '">';
?>
DOC;
			}
			else
			{
				return '<?php else: ?>';
			}
		}

		function close()
		{
			if (Koken::$draft &&!Koken::$preview && $this->can_live_update)
			{
				return '</i>';
			}
			else
			{
				return '<?php endif; ?>';
			}

		}

		private function condition_parse($matches)
		{
			return $this->field_to_keys($matches[1]);
		}

	}