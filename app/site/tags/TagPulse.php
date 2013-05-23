<?php

	class TagPulse extends Tag {

		function _clean_val($val)
		{
			if (strpos($val, '$') === 0)
			{
				return $val;
			}
			else if (strpos($val, '{$') === false)
			{
				if ($val != 'true' && $val != 'false' && !is_numeric($val))
				{
					$val = "\"$val\"";
				}
			}
			else
			{
				$val = "trim('" . preg_replace('/\{(\$[^}]+)\}/', "' . $1 . '", $val) . "')";
				$val = "is_numeric($val) ? (int) $val : ( $val == 'false' || $val == 'true' ? $val === 'true' : $val)";
			}
			return $val;
		}

		function generate()
		{

			$options = array( 'group' => 'default' );
			$disabled = array();

			$group_wrap = '<?php echo "default"; ?>';
			foreach($this->parameters as $key => $val)
			{
				if ($key === 'group')
				{
					$group_wrap = $this->attr_parse($val, true);
				}
				$val = $this->attr_parse($val);
				if (strpos($key, ':') !== false)
				{
					$bits = explode(':', $key);
					if (in_array($bits[0], $disabled))
					{
						continue;
					}
					if ($bits[1] === 'enabled' && $val == 'false')
					{
						$disabled[] = $bits[0];
						unset($options[$bits[0]]);
					}
					else
					{
						if (!isset($options[$bits[0]]))
						{
							$options[$bits[0]] = array();
						}
						$options[$bits[0]][$bits[1]] = $val;
					}
				}
				else
				{
					$options[$key] = $val;
				}

			}

			if (isset($options['jsvar']))
			{
				$js = 'var ' . $this->attr_parse($options['jsvar'], true) . ' = ';
			}
			else
			{
				$js = '';
			}
			if (isset($options['data_from_url']))
			{
				$url = $this->attr_parse($options['data_from_url']);
				unset($options['data_from_url']);
			}
			else if (isset($options['data']))
			{
				$data = $this->field_to_keys('data');
				if (strpos($data, 'covers') !== false)
				{
					$base = str_replace("['covers']", '', $data);
					$options['data'] = "array( 'content' => $data, 'album_id' => {$base}['id'], 'album_type' => {$base}['album_type'] )";
				}
				else
				{
					$options['data'] = "array( 'content' => $data )";
				}
			}
			else
			{
				list($url,) = $this->load_params( isset($options['source']) ? $options['source'] : array() );
			}

			unset($options['source']);

			if (isset($url))
			{
				$url = Koken::$location['real_root_folder'] . '/api.php?' . $url;
				$native = array("'dataUrl' => \"$url\"");
			}
			else
			{
				$native = array();
			}

			foreach($options as $key => $val)
			{
				if ($key === 'data')
				{
					$native[] = "'$key' => $val";
				}
				else if ($key !== 'group')
				{
					$native[] = "'$key' => " . $this->_clean_val($val);
				}
			}

			$native = '<?php echo json_encode( array_merge( array(' . join(', ', $native) . '), isset(Koken::$site[\'pulse_groups\']["' . $options['group']  . '"]) ? Koken::$site[\'pulse_groups\']["' . $options['group']  . '"] : array() ) ); ?>';

			return <<<OUT
<?php \$__id = 'pulse_' . md5(uniqid()); ?>
<div id="<?php echo \$__id; ?>" style="clear:left;" data-pulse-group="$group_wrap"></div>
<script>
	{$js}\$K.pulse.register({ id: '<?php echo \$__id; ?>', options: $native })
</script>
OUT;

		}

	}