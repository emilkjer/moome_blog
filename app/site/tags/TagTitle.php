<?php

	class TagTitle extends Tag {

		function generate()
		{

			if (isset($this->parameters['separator']))
			{
				$sep = $this->parameters['separator'];
			}
			else
			{
				$sep = '-';
			}

			$sep = " $sep ";

			if (Koken::$source && Koken::$source['type'])
			{
				$list = substr( strrev(Koken::$source['type']), 0, 1 ) === 's';

				if ($list)
				{
					$pre = '{{ labels.' . rtrim(Koken::$source['type'], 's') . '.plural case="title" }}';
				}
			}

			if (isset($pre))
			{
				$pre = "$pre{$sep}";
			}
			else
			{
				$pre = '';
			}
			return <<<DOC
<?php \$the_title_separator = '$sep'; ?>
<koken_title>
	<?php if (Koken::\$location['here'] !== '/'): ?>
		$pre
	<?php endif; ?>

	<?php if (isset(Koken::\$routed_variables['code'])): ?>
		<?php echo Koken::\$routed_variables['code']; ?> -
	<?php endif; ?>

	<?php echo Koken::\$site['title']; ?>
</koken_title>
DOC;
		}

	}