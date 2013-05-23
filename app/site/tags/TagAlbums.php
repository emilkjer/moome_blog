<?php

	class TagAlbums extends Tag {

		protected $allows_close = true;
		public $tokenize = true;

		function generate()
		{

			$token = '$value' . Koken::$tokens[1];
			$ref = '$value' . Koken::$tokens[0];

			return <<<OUT
<?php

	if (isset({$token}['albums']) && !empty({$token}['albums'])):

		$ref = array();
		{$ref}['__loop__'] =& {$token}['albums'];
?>
OUT;
		}
	}