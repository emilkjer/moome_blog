<?php

	class TagPagination extends Tag {

		protected $allows_close = true;
		public $tokenize = true;

		function generate()
		{
			$token = Koken::$tokens[0];
			$page = '$page' . Koken::$tokens[1];

			return <<<DOC
<?php

	if(isset($page) && {$page}['pages'] > 1):

		\$value{$token} = {$page};
		\$value{$token}['previous_page'] = array(
			'number' => {$page}['page'] - 1,
			'link' => rtrim(Koken::\$location['here'], '/') . '/page/' . ({$page}['page'] - 1) . '/'
		);
		\$value{$token}['next_page'] = array(
			'number' => {$page}['page'] + 1,
			'link' => rtrim(Koken::\$location['here'], '/') . '/page/' . ({$page}['page'] + 1) . '/'
		);
		\$value{$token}['__loop__'] = array();
		foreach(range(1, {$page}['pages']) as \$num):
			\$value{$token}['__loop__'][] = array(
				'number' => \$num,
				'is_current' => \$num === {$page}['page'] ? 'k-pagination-current' : '',
				'link' => rtrim(Koken::\$location['here'], '/') . '/page/' . \$num . '/'
			);
		endforeach;

		if ({$page}['page'] > 1):
			echo '<!-- KOKEN HEAD BEGIN --><link rel="prev" href="' . \$value{$token}['previous_page']['link'] . '" /><!-- KOKEN HEAD END -->';
		endif;

		if ({$page}['page'] < {$page}['pages']):
			echo '<!-- KOKEN HEAD BEGIN --><link rel="next" href="' . \$value{$token}['next_page']['link'] . '" /><!-- KOKEN HEAD END -->';
		endif;
?>
DOC;
		}

	}