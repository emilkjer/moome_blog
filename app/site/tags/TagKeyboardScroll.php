<?php

	class TagKeyboardScroll extends Tag {
		
		function generate()
		{
			
			$defaults = array('offset' => '0');
			$options = array_merge($defaults, $this->parameters);

			return <<<OUT
<script>\$K.keyboardScroll('{$options['element']}', {$options['offset']});</script>
OUT;

		}

	}