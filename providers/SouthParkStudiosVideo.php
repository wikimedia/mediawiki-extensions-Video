<?php

class SouthParkStudiosVideo extends FlashVideo {

	var $video,
		$id,
		$url;

	public function __construct( $video ) {
		if( !is_object( $video ) ) {
			throw new MWException( 'SouthParkStudiosVideo constructor given bogus video object.' );
		}

		$this->video =& $video;
		$this->video->ratio = 480 / 400;
		$this->id = $this->extractID( $this->video->getURL() );
		$this->url = 'http://www.southparkstudios.com/clips/' . $this->id;
		return $this;
	}

	public function getEmbedCode() {
		$height = $this->video->getHeight();
		$width = $this->video->getWidth();
		$output = '<embed src="http://media.mtvnservices.com/mgid:cms:item:southparkstudios.com:' . $this->id . '" width="' . $width . '" height="' . $height . '" type="application/x-shockwave-flash" wmode="window" flashVars="autoPlay=false&dist=http://www.southparkstudios.com&orig=" allowFullScreen="true" allowScriptAccess="always" allownetworking="all" bgcolor="#000000"></embed>';
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
		$text = strpos( $fixed_url, 'SOUTHPARKSTUDIOS.COM' );
		if( $text !== false ) {
			$parsed = explode( '/', $url );
			if( is_array( $parsed ) ) {
				$mdata = array_pop( $parsed );
				if ( ( $mdata != '' ) && ( strpos( $mdata, '?' ) === false ) ) {
					$id = $mdata;
				} else {
					$id = array_pop( $parsed );
				}
			}
		}

		return $id;
	}

}