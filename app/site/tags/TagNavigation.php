<?php

	class TagNavigation extends Tag {

		function generate()
		{
			if (isset($this->parameters['group']))
			{
				$group = 'groups.' . $this->parameters['group'] . '.items';
			}
			else
			{
				$group = 'items';
			}

			if (isset($this->parameters['list']))
			{
				$list = $this->parameters['list'] == 'true';
			}
			else
			{
				$list = true;
			}

			if ($list)
			{
				if (isset($this->parameters['nested']))
				{
					$group .= '_nested';
				}
			}

			if (!isset($this->parameters['class']))
			{
				$this->parameters['class'] = '';
			}

			if (isset($this->parameters['fallbacktext']))
			{
				$fallback = '<span class=\"k-note\">' . $this->parameters['fallbacktext'] . '</span>';
			}
			else
			{
				$fallback = '';
			}
			$list = $list ? 'true' : 'false';

			$token = $this->field_to_keys('site.navigation.' . $group);

			return <<<OUT
<?php echo empty($token) ? (Koken::\$draft ? "$fallback" : '') : Koken::render_nav($token, $list, true, '{$this->parameters['class']}'); ?>
OUT;

		}

	}