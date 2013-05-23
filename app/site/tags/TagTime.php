<?php

	class TagTime extends Tag {

		function generate()
		{

			$defaults = array('show' => 'both', 'class' => false, 'rss' => 'false');
			$options = array_merge($defaults, $this->parameters);

			$options['rss'] = $options['rss'] != 'false';

			if (isset($this->parameters['data']))
			{
				$token = $this->field_to_keys('data') . "['timestamp']";
			}
			else
			{
				$val = '$value' . (count(Koken::$tokens) ? Koken::$tokens[0] : '');
				$token = "isset({$val}) ? (isset({$val}['album']) ? {$val}['album']['date']['timestamp'] : {$val}['date']['timestamp']) : time()";
			}

			if ($options['class'])
			{
				$class = ' class="' . $options['class'] . '"';
			}
			else
			{
				$class = '';
			}

			$parts = array(
				"<?php echo( date(Koken::\$site['date_format'], $token) ); ?>",
				"<?php echo( date(Koken::\$site['time_format'], $token) ); ?>"
			);

			switch($options['show'])
			{
				case 'both':
					$f = "Koken::\$site['date_format'] . ' ' . Koken::\$site['time_format']";
					break;

				case 'date':
					$f = "Koken::\$site['date_format']";
					break;

				case 'time':
					$f = "Koken::\$site['time_format']";
					break;
			}

			if ($options['rss'])
			{
				return <<<OUT
<?php echo( date('D, d M Y H:i:s T', $token) ); ?>
OUT;
			}
			else
			{
				return <<<OUT
<time$class datetime="<?php echo( date('c', $token) ); ?>">
	<?php echo( date($f, $token) ); ?>
</time>
OUT;
			}

		}

	}