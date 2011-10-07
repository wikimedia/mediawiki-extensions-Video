<?php

class MyVideoVideo extends FlashVideo {

	var $video,
		$id,
		$url;

	public function __construct( $video ) {
		if( !is_object( $video ) ) {
			throw new MWException( 'MyVideoVideo constructor given bogus video object.' );
		}

		$this->video =& $video;
		$this->video->ratio = 470 / 406;
		$this->id = $this->extractID( $this->video->getURL() );
		$this->url = 'http://www.myvideo.de/watch/' . $this->id;
		return $this;
	}

	public function getEmbedCode() {
		$height = $this->video->getHeight();
		$width = $this->video->getWidth();
		$output = "<object style=\"width:{$width}px;height:{$height}px;\" type=\"application/x-shockwave-flash\" data=\"http://www.myvideo.de/movie/{$this->id}\">";
		$output .= '<param name="wmode" value="transparent">';
		$output .= '<param name="movie" value="http://www.myvideo.de/movie/' . $this->id . '" />';
		$output .= '<param name="AllowFullscreen?" value="true" /> </object>';
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
		$text = strpos( $fixed_url, 'MYVIDEO.DE' );
		if( $text !== false ) {
			$parsed = explode( '/', $url );
			if( is_array( $parsed ) ) {
				$id = array_pop( $parsed );
			}
		}

		return $id;
	}

}