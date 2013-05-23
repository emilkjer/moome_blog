<?php

class Tag extends DataMapper {

	function manage($add, $remove, $type = 'content')
	{
		$count = "{$type}_count";

		if ($add)
		{
			$q = array();
			foreach($add as $tag)
			{
				$q[] = "('$tag', 1, 1)";
			}

			if (!empty($q))
			{
				$q = join(',', $q);
				$this->db->query("INSERT INTO {$this->table}(id, count, $count) VALUES $q ON DUPLICATE KEY UPDATE $count = $count + 1, count = count + 1");
			}
		}

		if ($remove)
		{
			if (!empty($remove))
			{
				$in = "'" . join("','", $remove) . "'";
				$this->db->query("UPDATE {$this->table} SET $count = $count - 1, count = count - 1 WHERE id IN ($in)");
			}
		}
	}

	function listing($params)
	{
		$defaults = array(
			'order_by' => 'count',
			'order_direction' => 'DESC',
			'floor' => 1,
			'tags' => false,
			'limit_to' => false
		);

		$params = array_merge($defaults, $params);

		if ($params['order_by'] === 'essay_count')
		{
			$params['order_by'] = 'text_count';
		}

		if (strpos($params['order_by'], 'count') === false)
		{
			$where = 'count';
		}
		else
		{
			$where = $params['order_by'];
		}

		if ($params['limit_to'])
		{
			$where = str_replace('essay', 'text', rtrim($params['limit_to'], 's')) . '_count';
		}

		$start = '';
		if ($params['page'] > 1)
		{
			$start = ',' . ($params['limit']*($params['page']-1));
		}
		if ($params['tags'])
		{
			$filter = ' AND id="' . $params['tags'] . '"';
		}
		else
		{
			$filter = '';
		}
		$count = $this->db->query("SELECT count(*) as cnt FROM {$this->table} WHERE $where >= {$params['floor']}$filter ORDER BY {$params['order_by']} {$params['order_direction']}");
		$data = $this->db->query("SELECT * FROM {$this->table} WHERE $where >= {$params['floor']}$filter ORDER BY {$params['order_by']} {$params['order_direction']} LIMIT {$params['limit']}$start");
		return array($count, $data);
	}
}

/* End of file trash.php */
/* Location: ./application/models/trash.php */