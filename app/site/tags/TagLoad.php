<?php

	class TagLoad extends Tag {

		public $tokenize = true;
		protected $allows_close = true;
		public $source = false;

		function generate()
		{
			if (isset($this->parameters['source']))
			{
				$this->attr_parse_level = 1;
				$this->parameters['filters'] = array();
				foreach($this->parameters as $key => $val)
				{
					if ($key === 'tree') continue;
					if (strpos($key, 'filter:') === 0)
					{
						$left = str_replace('filter:', '', $key);
						if (strpos($left, ':not') === false)
						{
							$right = '';
						}
						else
						{
							$right = '!';
							$left = str_replace(':not', '', $left);
						}
						$this->parameters['filters'][] = $left . '=' . $right . $this->attr_parse($val);
						unset($this->parameters[$key]);
					}
				}
			}

			if (!Koken::$main_load_token && !isset($this->parameters['source']) && isset($this->parameters['infinite']))
			{
				$infinite = $this->attr_parse($this->parameters['infinite']);;
				Koken::$main_load_token = Koken::$tokens[0];
				if (isset($this->parameters['infinite_toggle']))
				{
					$infinite_selector = $this->attr_parse($this->parameters['infinite_toggle']);
					unset($this->parameters['infinite_toggle']);
				}
				else
				{
					$infinite_selector = '';
				}
				unset($this->parameters['infinite']);
			}
			else
			{
				$infinite = false;
				$infinite_selector = '';
			}

			list($url, $options, $collection_name, $paginate) = $this->load_params();

			$main = '$value' . Koken::$tokens[0];
			$curl = '$curl' . Koken::$tokens[0];
			$page = '$page' . Koken::$tokens[0];
			$load_url = Koken::$last_load_url = '$url' . Koken::$tokens[0];

			$out = <<<DOC
<?php
	$load_url = "$url";
DOC;
			if ($infinite)
			{
				$top_token = Koken::$tokens[0];
				$out .= <<<DOC
	if ($infinite === true)
	{
		Koken::\$location['__infinite_token'] = '$top_token';
	}
DOC;
			}

			if ($paginate) {
				$out .= <<<DOC
	if (isset(Koken::\$location['parameters']['page']))
	{
		$load_url .= '/page:' . Koken::\$location['parameters']['page'];
	}
DOC;
			}

			$archive = '';
			if ($options['archive'])
			{
				switch($options['archive'])
				{
					case 'tag':
						$archive = "{$main}['archive'] = array('__koken__' => 'tag', 'title' => str_replace(',', ', ', urldecode(isset(Koken::\$routed_variables['id']) ? Koken::\$routed_variables['id'] : Koken::\$routed_variables['slug'])));";
						break;

					case 'category':
						$archive = "{$main}['archive'] = array('__koken__' => 'category', 'title' => {$main}['category']['title']);";
						break;

					case 'date':
						$archive = "{$main}['archive'] = array('__koken__' => 'archive', 'month' => isset(Koken::\$routed_variables['month']) ? Koken::\$routed_variables['month'] : false, 'year' => Koken::\$routed_variables['year'], 'title' => !isset(Koken::\$routed_variables['month']) ? Koken::\$routed_variables['year'] : date('F Y', strtotime(Koken::\$routed_variables['month'] . '/1/' . Koken::\$routed_variables['year'])));";
						break;
				}
			}

			if ($options['list'])
			{
				$out .= <<<DOC
	if (isset(Koken::\$routed_variables['tags']))
	{
		$load_url .= '/tags:' . Koken::\$routed_variables['tags'];
	}
DOC;
			}

			$out .= <<<DOC
	$main = Koken::api($load_url);
DOC;

			if (!isset($this->parameters['source']))
			{
				$out .= <<<DOC
	if (isset({$main}['error']))
	{
		header("Location: " . Koken::\$location['root_folder'] . "/error/{{$main}['http']}/");
	}
DOC;
			}

			if ($options['list'])
			{
				$out .= <<<DOC
	if (isset({$main}['page']))
	{
		$page = array(
			'page' => {$main}['page'],
			'pages' => {$main}['pages'],
			'per_page' => {$main}['per_page'],
			'total' => {$main}['total'],
		);
DOC;

		if ($infinite)
		{
			$out .= <<<DOC
?>
	<script>
		\$K.infinity.totalPages = <?php echo {$page}['pages']; ?>;
		\$K.infinity.selector = '$infinite_selector';
	</script>
<?php
DOC;
		}

		$out .= <<<DOC
	}

	if (isset({$main}['content']))
	{
		{$main}['__loop__'] = {$main}['content'];
	}
	else if (isset({$main}['albums']))
	{
		{$main}['__loop__'] = {$main}['albums'];
	}
	else if (isset({$main}['text']))
	{
		{$main}['__loop__'] = {$main}['text'];
	}
	else if (isset({$main}['$collection_name']))
	{
		{$main}['__loop__'] = {$main}['$collection_name'];
		{$main}['$collection_name'] =& {$main}['__loop__'];
	}
	else
	{
		{$main}['__loop__'] = {$main};
	}

	if (array_key_exists('counts', $main))
	{
		{$main}['$collection_name']['counts'] =& {$main}['counts'];
	}

	if (!empty({$main}['__loop__'])):
		$archive
DOC;
			}
			else
			{

				$out .= <<<DOC
	if ($main && !isset({$main}['error'])):
		if (!isset({$main}[{$main}['__koken__']])):
			{$main}[{$main}['__koken__']] =& $main;
		endif;
DOC;
			}

			if (!isset($this->parameters['source']))
			{
				$out .= <<<DOC
	if (!empty({$main}['title']))
	{
		\$the_title = {$main}['title'];
	}
	else if (isset({$main}['filename']))
	{
		\$the_title = {$main}['filename'];
	}
	else if (isset({$main}['album']['title']))
	{
		\$the_title = {$main}['album']['title'];
	}
	else if (isset({$main}['archive']['title']))
	{
		\$the_title = {$main}['archive']['title'];
	}

	if (isset({$main}['canonical_url']))
	{
		echo '<!-- KOKEN HEAD BEGIN --><link rel="canonical" href="' . {$main}['canonical_url'] . '"><!-- KOKEN HEAD END -->';
	}

	if (isset(\$the_title) && isset(\$the_title_separator))
	{
		echo '<!-- KOKEN HEAD BEGIN --><koken_title>' . \$the_title . \$the_title_separator . Koken::\$site['title'] . '</koken_title><!-- KOKEN HEAD END -->';
	}

	if (isset({$main}['essay']) && !isset(\$_COOKIE['koken_session']) && !{$main}['essay']['published'])
	{
		header('Location: ' . Koken::\$location['root'] . '/error/403/');
		exit;
	}

	if (isset({$main}['album']) || isset({$main}['context']['album']))
	{
		\$__album = isset({$main}['album']) ? {$main}['album'] : {$main}['context']['album'];
		echo '<!-- KOKEN HEAD BEGIN --><link rel="alternate" type="application/atom+xml" title="' . Koken::\$site['title'] . ': Uploads from ' . \$__album['title'] . '" href="' . Koken::\$location['root'] . '/feed/albums/' . \$__album['id'] . '/recent.rss" /><!-- KOKEN HEAD END -->';
	}
DOC;
			}

			return $out . '?>';

		}

	}