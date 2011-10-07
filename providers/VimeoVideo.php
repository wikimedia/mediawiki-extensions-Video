<?php

class VimeoVideo extends FlashVideo {

	var $video,
		$id,
		$url;

	public function __construct( $video ) {
		if( !is_object( $video ) ) {
			throw new MWException( 'VimeoVideo constructor given bogus video object.' );
		}

		$this->video =& $video;
		$this->video->ratio = 400 / 225;
		$this->id = $this->extractID( $this->video->getURL() );
		$this->url = 'http://www.vimeo.com/' . $this->id;
		return $this;
	}

	public function getEmbedCode() {
		$height = $this->video->getHeight();
		$width = $this->video->getWidth();
		$auto = '';#$autoplay ? '&amp;autoplay=1' : '';
		$output = '<object width="' . $width . '" height="' . $height . '">';
		$output .= '<param name="allowfullscreen" value="true" />';
		$output .= '<param name="wmode" value="transparent">';
		$output .= '<param name="allowscriptaccess" value="always" />';
		$output .= '<param name="movie" value="http://vimeo.com/moogaloop.swf?clip_id=' . $this->id .
			'&amp;server=vimeo.com&amp;show_title=1&amp;show_byline=1&amp;show_portrait=0&amp;color=&amp;fullscreen=1' . $auto . '" />';
		$output .= '<embed src="http://vimeo.com/moogaloop.swf?clip_id=' . $this->id . '&amp;server=vimeo.com&amp;show_title=1&amp;show_byline=1&amp;show_portrait=0&amp;color=&amp;fullscreen=1' . $auto . '" type="application/x-shockwave-flash" allowfullscreen="true" allowscriptaccess="always" width="' . $width . '" height="' . $height . '">';
		$output .= '</embed></object>';
		return $output;
	}

	/**
	 * Extract the video ID from its URL.
	 * Copypasted from WikiaVideo's VideoPage.php's parseUrl().
	 *
	 * @return Integer: video ID
	 */
	private function extractID() {
		$url = $this->video->getURL();
		$url = trim( $url );

		$fixed_url = strtoupper( $url );
		$test = strpos( $fixed_url, 'HTTP://' );
		if( !false === $test ) {
			return false;
		}

		$fixed_url = str_replace( 'HTTP://', '', $fixed_url );
		$fixed_parts = explode( '/', $fixed_url );
		$fixed_url = $fixed_parts[0];

		$id = '';
		$text = strpos( $fixed_url, 'VIMEO.COM' );
		if( $text !=== false ) {
			$parsed = explode( '/', $url );
			if( is_array( $parsed ) ) {
				$id = array_pop( $parsed );
			}
		}

		return $id;
	}

}