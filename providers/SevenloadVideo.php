<?php
/**
 * Bartek Łapiński's notes from WikiaVideo extension:
 * needs an API key - to be done last
 * 1. create a token
 * http://api.sevenload.com/rest/1.0/tokens/create with user and password
 *
 * 2. load the data using the token
 * http://api.sevenload.com/rest/1.0/items/A2C4E6G \
 *  ?username=XYZ&token-id=8b8453ca4b79f500e94aac1fc7025b0704f3f2c7
 */

class SevenloadVideo extends FlashVideo {

	var $video,
		$id,
		$url;

	public function __construct( $video ) {
		if( !is_object( $video ) ) {
			throw new MWException( 'SevenloadVideo constructor given bogus video object.' );
		}

		$this->video =& $video;
		$this->video->ratio = 500 / 408;
		$this->id = $this->extractID( $this->video->getURL() );
		$this->url = 'http://www.sevenload.com/videos/' . $this->id;
		return $this;
	}

	public function getEmbedCode() {
		$output = '<object style="visibility: visible;" id="sevenloadPlayer_' . $this->id .
			'" data="http://static.sevenload.com/swf/player/player.swf" type="application/x-shockwave-flash" height="' .
			$this->video->getHeight() .
			'" width="' . $this->video->getWidth() . '">';
		$output .= '<param name="wmode" value="transparent">';
		$output .= '<param value="always" name="allowScriptAccess">';
		$output .= '<param value="true" name="allowFullscreen">';
		$output .= '<param value="configPath=http%3A%2F%2Fflash.sevenload.com%2Fplayer%3FportalId%3Den%26autoplay%3D0%26itemId%3D' . $this->id .
			'&amp;locale=en_US&amp;autoplay=0&amp;environment=" name="flashvars">';
		$output .= '</object>';
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
		$text = strpos( $fixed_url, 'SEVENLOAD.COM' );
		if( $text !== false ) {
			$parsed = explode( '/', $url );
			$id = array_pop( $parsed );
			$parsed_id = explode( '-', $id );
			if( is_array( $parsed_id ) ) {
				$id = $parsed_id[0];
			}
		}
		return $id;
	}

}