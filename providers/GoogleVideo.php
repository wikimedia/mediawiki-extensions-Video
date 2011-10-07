<?php

class GoogleVideo extends FlashVideo {

	var $video,
		$id,
		$url;

	public function __construct( $video ) {
		if( !is_object( $video ) ) {
			throw new MWException( 'GoogleVideo constructor given bogus video object.' );
		}
		$this->video =& $video;
		$this->video->ratio = 425/355;
		$this->id = $this->extractID( $this->video->getURL() );
		$this->url = "http://video.google.com/googleplayer.swf?docId={$this->id}";
		return $this;
	}

	private function extractID() {
		// Standard Google browser URL
		$url = $this->video->getURL();
		$standard_inurl = strpos( strtoupper( $url ), 'VIDEOPLAY?DOCID=' );

		if( $standard_inurl !== false ) {
			$id = substr( $url, $standard_inurl + strlen( 'VIDEOPLAY?DOCID=' ), strlen( $url ) );
		}
		if( !$id ) {
			$id_test = preg_replace( "%http\:\/\/video\.google\.com\/googleplayer\.swf\?docId=%i", '', $url );
			if( $id_test != $url ) {
				$id = $id_test;
			}
		}
		return $id;
	}
}