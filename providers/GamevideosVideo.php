<?php

class GamevideosVideo extends FlashVideo {

	var $video,
		$id,
		$url;

	public function __construct( $video ) {
		if( !is_object( $video ) ) {
			throw new MWException( 'GamevideosVideo constructor given bogus video object.' );
		}

		$this->video =& $video;
		$this->video->ratio = 500 / 319;
		$this->id = $this->extractID( $this->video->getURL() );
		$this->url = 'http://gamevideos.1up.com/video/id/' . $this->id;
		return $this;
	}

	public function getEmbedCode() {
		$height = $this->video->getHeight();
		$width = $this->video->getWidth();
		$output = '<embed wmode="transparent" type="application/x-shockwave-flash" width="' . $width . '" height="' . $height . '" src="http://gamevideos.1up.com/swf/gamevideos12.swf?embedded=1&amp;fullscreen=1&amp;autoplay=0&amp;src=http://gamevideos.1up.com/do/videoListXML%3Fid%3D' . $this->id . '%26adPlay%3Dtrue" align="middle"></embed>';
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
		$text = strpos( $fixed_url, 'GAMEVIDEOS.1UP.COM' );
		if( $text !== false ) {
			$parsed = explode( '/', $url );
			if( is_array( $parsed ) ) {
				$id = array_pop( $parsed );
			}
		}

		return $id;
	}

}