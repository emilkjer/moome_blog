<?php

class BD_Shortcodes extends KokenPlugin {

	function __construct()
	{
		$this->register_shortcode('koken_photo', 'koken_media');
		$this->register_shortcode('koken_video', 'koken_media');
		$this->register_shortcode('koken_oembed', 'koken_oembed');
		$this->register_shortcode('koken_slideshow', 'koken_slideshow');
		$this->register_shortcode('koken_upload', 'koken_upload');
		$this->register_shortcode('koken_code', 'koken_code');
	}

	function koken_oembed($attr)
	{
		if (!isset($attr['url']) || !isset($attr['endpoint'])) { return ''; }

		$endpoint = $attr['endpoint'];
		if (strpos($endpoint, '?') !== false)
		{
			$endpoint .= '&';
		}
		else
		{
			$endpoint .= '?';
		}

		$info = Shutter::get_oembed($endpoint . 'url=' . $attr['url']);

		if (isset($info['html']))
		{
			$html = $info['html'];
		}
		else if (isset($info['url'])) {
			$html = '<img width="100%" src="' . $info['url'] . '" />';
		}
		else
		{
			return '';
		}
		return '<div class="k-content-embed"><div class="k-content">' . $html . '</div></div>';
	}

	function koken_media($attr)
	{
		if (!isset($attr['id'])) { return ''; }

		if ($attr['media_type'] === 'image')
		{
			$tag = 'img';
		}
		else
		{
			$tag = 'video';
		}

		$text = '';
		if (isset($attr['caption']) && $attr['caption'] !== 'none')
		{
			$text .= '<div class="k-content-text">';
			if ($attr['caption'] !== 'caption')
			{
				$text .= '<span class="k-content-title">{{ content.title | content.filename }}</span>';
			}
			if ($attr['caption'] !== 'title')
			{
				$text .= '<span class="k-content-caption">{{ content.caption }}</span>';
			}
			$text .= '</div>';
		}

		$link_pre = $link_post = $context_param = '';

		if (isset($attr['link']) && $attr['link'] !== 'none')
		{
			if ($attr['link'] === 'detail' || $attr['link'] === 'lightbox')
			{
				$link_pre = '<koken:link' . ( $attr['link'] === 'lightbox' ? ' lightbox="true"': '' ) . '>';
				$link_post = '</koken:link>';
			}
			else if ($attr['link'] === 'album')
			{
				$context_param = " filter:context=\"{$attr['album']}\"";
				$link_pre = '<koken:link data="context.album">';
				$link_post = '</koken:link>';
			}
			else
			{
				$link_pre = '<a href="' . $attr['custom_url'] . '">';
				$link_post = '</a>';
			}
		}

		return <<<HTML
<div class="k-content-embed">
	<koken:load source="content" filter:id="{$attr['id']}"$context_param>
		<div class="k-content">
			$link_pre
			<koken:$tag />
			$link_post
		</div>
		$text
	</koken:load>
</div>
HTML;

	}

	function koken_upload($attr)
	{
		$text = '';
		$src = $attr['filename'];
		if (strpos($src, 'http') !== 0)
		{
			$src = $attr['_relative_root'] . 'storage/custom/' . $src;
		}

		$img = '<img class="k-media-img" style="max-width:100%" src="' . $src . '" />';

		if (isset($attr['link']) && !empty($attr['link']))
		{
			$img = '<a href="' . $attr['link'] . '"' . ( isset($attr['target']) && $attr['target'] !== 'none' ? ' target="_blank"' : '' ) . '>' . $img . '</a>';
		}

		if (isset($attr['title']) && !empty($attr['title']))
		{
			$text .= '<span class="k-content-title">' . $attr['title'] . '</span>';
		}

		if (isset($attr['caption']) && !empty($attr['caption']))
		{
			$text .= '<span class="k-content-caption">' . $attr['caption'] . '</span>';
		}

		if (!empty($text))
		{
			$text = "<div class=\"k-content-text\">$text</div>";
		}

		return <<<DOC
		<div class="k-content-embed">
			<div class="k-content">$img</div>
			$text
		</div>
DOC;

	}

	function koken_code($attr)
	{
		if (isset($attr['code']))
		{
			return '<div class="k-content-embed">' . $attr['code'] . '</div>';
		}
		else
		{
			return '';
		}
	}

	function koken_slideshow($attr)
	{

		if (isset($attr['content']))
		{

			$path = '/content/' . $attr['content'];
		}
		else if (isset($attr['album']))
		{
			$path = '/albums/' . $attr['album'] . '/content';
		}

		return <<<HTML
<div class="k-content-embed">
	<div class="k-content">
		<koken:pulse data_from_url="$path" size="auto" link_to="advance" group="essays" />
	</div>
</div>
HTML;

	}
}