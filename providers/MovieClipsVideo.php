<?php
/**
 * @file
 * @author William Lee <wlee@wikia-inc.com>
 * @see http://trac.wikia-code.com/changeset/39940
 */
class MovieClipsVideo extends FlashVideo {

	var $video,
		$id,
		$url;

	public function __construct( $video ) {
		if( !is_object( $video ) ) {
			throw new MWException( 'MovieClipsVideo constructor given bogus video object.' );
		}

		$this->video =& $video;
		$this->video->ratio = 560 / 304;
		$this->id = $this->extractID( $this->video->getURL() );
		$this->url = $url = 'http://movieclips.com/' . $this->id;
		return $this;
	}

	public function getEmbedCode() {
		$height = $this->video->getHeight();
		$width = $this->video->getWidth();
		$url = 'http://movieclips.com/e/' . $this->id . '/';
		$output = "<embed src=\"{$url}\" width=\"{$width}\" height=\"{$height}\" wmode=\"transparent\" allowScriptAccess=\"always\" allowfullscreen=\"true\" pluginspage=\"http://www.macromedia.com/go/getflashplayer\" type=\"application/x-shockwave-flash\"> </embed>";
		return $output;
	}

	/**
	 * Extract the video ID from its URL.
	 *
	 * @return Mixed: video ID (integer) on success, boolean false on failure
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
		$text = strpos( $fixed_url, 'MOVIECLIPS.COM' );
		if( $text !== false ) {
			$url = trim( $url, '/' );
			$parsed = explode( '/', $url );
			if( is_array( $parsed ) ) {
				$id = array_pop( $parsed );
			}
		}

		return $id;
	}
}