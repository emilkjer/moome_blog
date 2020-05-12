<?php

	$c = new Category;
	$c->where('slug', NULL)
		->or_where('slug', '')
		->limit(100)->get_iterated();

	foreach($c as $content)
	{
		$content->slug = 'generate';
		$content->save();
	}

	$c = new Category;

	if ($c->where('slug', NULL)->or_where('slug', '')->count() === 0)
	{
		$done = true;
	}