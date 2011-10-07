<?php

class YouTubeVideo extends FlashVideo {

	var $video,
		$id,
		$url;

	public function __construct( $video ) {
		if( !is_object( $video ) ) {
			throw new MWException( 'YouTube constructor given bogus video object.' );
		}
		$this->video =& $video;
		$this->video->ratio = 425/355;
		$this->id = $this->extractYouTubeID( $this->video->getURL() );
		$this->url = "http://www.youtube.com/v/{$this->id}";
		return $this;
	}

	private function extractYouTubeID() {
		// Standard YouTube URL
		$url = $this->video->getURL();
		$standard_youtube_inurl = strpos( strtoupper( $url ), 'WATCH?V=' );

		$id = '';
		if( $standard_youtube_inurl !== false ) {
			$id = substr( $url, $standard_youtube_inurl + 8, strlen( $url ) );
		}
		if( empty( $id ) ) {
			$id_test = str_replace( 'http://www.youtube.com/v/', '', $url );
			if( $id_test != $url ) {
				$id = $id_test;
			}
		}
		return $id;
	}
}