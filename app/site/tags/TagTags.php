<?php

	class TagTags extends Tag {

		protected $allows_close = true;
		public $tokenize = true;

		function generate()
		{

			$token = '$value' . Koken::$tokens[1];
			$ref = '$value' . Koken::$tokens[0];

			return <<<OUT
<?php

	if (!isset({$token}['tags']) && isset({$token}['album']))
	{
		\$__base = {$token}['album'];
	}
	else
	{
		\$__base = $token;
	}

	if (isset(\$__base['tags']) && !empty(\$__base['tags'])):

		$ref = array();

		{$ref}['__loop__'] = \$__base['tags'];

		if (!is_array(\$__base['tags'][0]))
		{
			foreach({$ref}['__loop__'] as &\$t)
			{
				\$t = array(
					'title' => \$t,
					'__koken__' => 'tag_' . \$__base['__koken__'] . 's'
				);
			}
		}
?>
OUT;
		}
	}