<?php

	class TagBackground extends Tag {

		public $tag = 'div';

		function generate()
		{

			$options = array_merge(
				array(
					'tag' => 'div',
					'data' => false
				),
				$this->parameters
			);

			if (isset($this->parameters['data']))
			{
				$token = $this->field_to_keys($this->parameters['data']);
				unset($this->parameters['data']);
			}
			else
			{
				$token = '$value' . Koken::$tokens[0];
			}

			$this->tag = $options['tag'];

			unset($this->parameters['tag']);

			$params = array();

			foreach($this->parameters as $key => $val)
			{
				if (!isset($defaults[$key]))
				{
					$val = $this->attr_parse($val, true);
					$params[] = "$key=\"$val\"";
				}
			}

			$params = join(' ', $params);

			return <<<DOC
<?php
		\$__presets = array();
		\$__item = isset({$token}['presets']) ? $token : ( isset({$token}['covers']) ? {$token}['covers'][0] : false );
		if (\$__item)
		{
			foreach(\$__item['presets'] as \$name => \$obj)
			{

				\$__presets[] = "\$name,{\$obj['width']},{\$obj['height']}";
			}
		}

		\$__presets = join(' ', \$__presets);
?>
<{$this->tag} $params data-bg-presets="<?php echo \$__presets; ?>" data-base="<?php echo \$__item['cache_path']['prefix']; ?>" data-extension="<?php echo \$__item['cache_path']['extension']; ?>">
DOC;
		}

		function close()
		{
			return '</' . $this->tag . '>';
		}

	}