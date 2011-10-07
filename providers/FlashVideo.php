<?php

class FlashVideo {

	var $video,
		$id,
		$url;

	public function __construct( $video ) {
		if( !is_object( $video ) ) {
			throw new MWException( 'FlashVideo constructor given bogus video object.' );
		}

		$this->video =& $video;
		$this->url = $this->video->getURL();
		return $this;
	}

	public function getEmbedCode() {
		$output = "<object width=\"{$this->video->getWidth()}px\" height=\"{$this->video->getHeight()}px\">";
		$output .= "<param name=\"movie\" value=\"{$this->url}\"></param>";
		$output .= '<param name="wmode" value="transparent"></param>';
		$output .= "<embed wmode=\"transparent\" base=\".\" allowScriptAccess=\"always\" src=\"{$this->url}\" type=\"application/x-shockwave-flash\" width=\"{$this->video->getWidth()}px\" height=\"{$this->video->getHeight()}px\">";
		$output .= '</embed>';
		$output .= '</object>';
		return $output;
	}

}