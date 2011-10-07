<?php

class MetaCafeVideo extends FlashVideo {

	public function __construct( &$video ) {
		parent::__construct( $video );
		$this->video->ratio = 400/345;
		$this->url = $this->video->getURL();
		$this->extractID( $this->video->getURL() );
	}

	private function extractID() {
		// Standard Metacafe browser URL
		$url = $this->video->getURL();
		$standard_inurl = strpos( strtoupper( $url ), 'HTTP://WWW.METACAFE.COM/WATCH/' );

		if( $standard_inurl !== false ) {
			$id = substr( $url, $standard_inurl + strlen( 'HTTP://WWW.METACAFE.COM/WATCH/' ), strlen( $url ) );
			$last_char = substr( $id, -1 ,1 );

			if( $last_char == '/' ) {
				$id = substr( $id, 0, strlen( $id )-1 );
			}
			$this->url = "http://www.metacafe.com/fplayer/{$id}.swf";
		}
	}
}