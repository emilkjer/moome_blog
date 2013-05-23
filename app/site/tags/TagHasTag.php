<?php

	class TagHasTag extends Tag {

		protected $allows_close = true;

		function generate()
		{

			$token = '$value' . Koken::$tokens[0];
			$tag = $this->attr_parse($this->parameters['title']);

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

	\$__tags = explode(',', '$tag');
	if (isset(\$__base['tags']) && !empty(\$__base['tags']) && array_intersect(\$__tags, \$__base['tags']) === \$__tags):

?>
OUT;
		}
	}