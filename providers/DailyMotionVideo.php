<?php

class DailyMotionVideo extends FlashVideo {

	var $video,
		$id,
		$url;

	public function __construct( $video ) {
		if( !is_object( $video ) ) {
			throw new MWException( 'DailyMotionVideo constructor given bogus video object.' );
		}
		$this->video =& $video;
		$this->video->ratio = 425/335;
		$this->id = $this->extractID( $this->video->getURL() );
		$this->url = "http://www.dailymotion.com/swf/{$this->id}";
		return $this;
	}

	private function extractID() {
		$url = $this->video->getURL();
		$id = preg_replace( '%http\:\/\/www\.dailymotion\.com\/(swf|video)\/%i', '', $url );
		return $id;
	}

}