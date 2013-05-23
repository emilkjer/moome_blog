<?php

	class TagCategories extends Tag {

		protected $allows_close = true;
		public $tokenize = true;

		function generate()
		{

			$token = '$value' . Koken::$tokens[1];
			$ref = '$value' . Koken::$tokens[0];

			return <<<OUT
<?php

	if (!isset({$token}['categories']) && isset({$token}['album']))
	{
		\$__base = {$token}['album'];
	}
	else
	{
		\$__base = $token;
	}

	if (isset(\$__base['categories']) && !empty(\$__base['categories'])):

		$ref = array();
		{$ref}['__loop__'] =& \$__base['categories'];
?>
OUT;
		}
	}