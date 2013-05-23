<?php

class Archives extends Koken_Controller {

	function __construct()
    {
         parent::__construct();
    }

	function _aggregate($arr, $dates, $label = 'content')
	{
		foreach($arr as $b)
		{
			if (!isset($dates[$b->year]))
			{
				$dates[$b->year] = array();
			}

			if (!isset($dates[$b->year][$b->month]))
			{
				$dates[$b->year][$b->month] = array();
			}

			$dates[$b->year][$b->month][$label] = (int) $b->count;
		}

		return $dates;
	}

	function index()
	{
		$defaults = array('summary' => false, 'limit_to' => false, 'limit' => null, 'page' => 1, 'year' => false, 'month' => false);

		list($params,) = $this->parse_params(func_get_args());

		$params = array_merge($defaults, $params);

		if (!$params['summary']) {
			if (is_null($params['limit']))
			{
				$params['limit'] = 20;
			}
			else
			{
				$params['limit'] = min($params['limit'], 20);
			}
		}
		$final = array( 'archives' => array() );

		$dates = array();

		$a = new Album;
		$c = new Content;
		$t = new Text;

		$a->select('COUNT(*) as count, MONTH(FROM_UNIXTIME(created_on)) as month, YEAR(FROM_UNIXTIME(created_on)) as year')
			->where('listed', 1)
			->where('deleted', 0)
			->where('total_count >', 0);

		if ($params['month'])
		{
			$a->where("MONTH(FROM_UNIXTIME(created_on)) = '{$params['month']}'");
		}

		if ($params['year'])
		{
			$a->where("YEAR(FROM_UNIXTIME(created_on)) = {$params['year']}");
		}

		$a->group_by('month,year')->order_by('year')->get_iterated();

		$dates = $this->_aggregate($a, $dates, 'albums');

		$c->select('COUNT(*) as count, MONTH(FROM_UNIXTIME(captured_on)) as month, YEAR(FROM_UNIXTIME(captured_on)) as year')
			->where('visibility', 0)
			->where('deleted', 0);

		if ($params['month'])
		{
			$c->where("MONTH(FROM_UNIXTIME(captured_on)) = '{$params['month']}'");
		}

		if ($params['year'])
		{
			$c->where("YEAR(FROM_UNIXTIME(captured_on)) = {$params['year']}");
		}

		$c->group_by('month,year')->order_by('year')->get_iterated();

		$dates = $this->_aggregate($c, $dates);

		$t->select('COUNT(*) as count, MONTH(FROM_UNIXTIME(published_on)) as month, YEAR(FROM_UNIXTIME(published_on)) as year')
			->where('page_type', 0)
			->where('published', 1);

		if ($params['month'])
		{
			$t->where("MONTH(FROM_UNIXTIME(published_on)) = '{$params['month']}'");
		}

		if ($params['year'])
		{
			$t->where("YEAR(FROM_UNIXTIME(published_on)) = {$params['year']}");
		}

		$t->group_by('month,year')->order_by('year')->get_iterated();

		$dates = $this->_aggregate($t, $dates, 'essays');

		foreach($dates as $year => &$months)
		{
			krsort($months);
		}

		krsort($dates);

		$cnt = $skip = 0;

		if ($params['page'] > 1)
		{
			$skip = $params['limit']*($params['page']-1);
		}

		foreach($dates as $year => &$months)
		{
			foreach($months as $m => $arr)
			{

				if ($params['limit_to'] && (!isset( $arr[ $params['limit_to'] ] ) || (int) $arr[ $params['limit_to'] ] === 0)) continue;
				if ($skip > 0)
				{
					$skip--;
					continue;
				}

				$cnt++;

				if (is_null($params['limit']) || $cnt <= $params['limit'])
				{

					$base = array(
						'__koken__' => 'archive',
						'month' => $m < 10 ? "0$m" : "$m",
						'year' => $year,
						'title' => date('F Y', strtotime("$year-$m-01")),
						'counts' => array(
							'total' => @(int) $arr['content'] + @(int) $arr['albums'] + @(int) $arr['essays'],
							'albums' => @(int) $arr['albums'],
							'content' => @(int) $arr['content'],
							'essays' => @(int) $arr['essays']
						)
					);

					if (!$params['summary'])
					{

						$c = new Content;
						$a = new Album;
						$t = new Text;

						$content = $albums = $essays = array();

						if ($base['counts']['content'] > 0)
						{
							$c->limit(10)
								->where('visibility', 0)
								->where("MONTH(FROM_UNIXTIME(captured_on)) = '$m' AND YEAR(FROM_UNIXTIME(captured_on)) = $year")
								->order_by('captured_on DESC')
				 				->get_iterated();

				 			foreach($c as $_c)
							{
								$base['preview']['content'][] = $_c->to_array( array('auth' => $this->auth) );
							}
						}

						if ($base['counts']['albums'] > 0)
						{
							$a->limit(10)
								->where('listed', 1)
								->where('total_count >', 0)
								->where("MONTH(FROM_UNIXTIME(created_on)) = '$m' AND YEAR(FROM_UNIXTIME(created_on)) = $year")
								->order_by('created_on DESC')
				 				->get_iterated();

				 			foreach($a as $_a)
							{
								$base['preview']['albums'][] = $_a->to_array();
							}
						}

						if ($base['counts']['essays'] > 0)
						{
							$t->limit(10)
								->where('published', 1)
								->where('page_type', 0)
								->where("MONTH(FROM_UNIXTIME(published_on)) = '$m' AND YEAR(FROM_UNIXTIME(published_on)) = $year")
								->order_by('published_on DESC')
				 				->get_iterated();

				 			foreach($t as $_t)
							{
								$base['preview']['essays'][] = $_t->to_array();
							}
						}

					}

					$final['archives'][] = $base;
				}

			}

		}

		$final['total'] = $cnt;
		if (is_numeric($params['limit']))
		{
			$final['page'] = (int) $params['page'];
			$final['pages'] = ceil($cnt/$params['limit']);
			$final['per_page'] = min((int) $params['limit'], $cnt);
		}
		else
		{
			$final['page'] = 1;
			$final['pages'] = 1;
			$final['per_page'] = $cnt;
		}


		$this->set_response_data($final);
	}

}