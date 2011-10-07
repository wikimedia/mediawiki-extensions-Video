<?php

class GoGreenTubeVideo extends FlashVideo {

	var $video,
		$id,
		$url;

	public function __construct( $video ) {
		if( !is_object( $video ) ) {
			throw new MWException( 'GoGreenTubeVideo constructor given bogus video object.' );
		}

		$this->video =& $video;
		$this->video->ratio = 432 / 394; // or the other way around...
		$this->id = $this->extractID( $this->video->getURL() );
		$this->url = 'http://www.gogreentube.com/embed/' . $this->id;
		return $this;
	}

	public function getEmbedCode() {
		$height = $this->video->getHeight();
		$width = $this->video->getWidth();
		$ggid = $this->extractID();
		$url = "http://www.gogreentube.com/embed/{$ggid}";
		$output = "<script type=\"text/javascript\" src=\"{$url}\"></script>";
		return $output;
	}

	/**
	 * Extract the video ID from its URL.
	 *
	 * @return Integer: video ID
	 */
	private function extractID() {
		$url = $this->video->getURL();

		$id = $url;

		if( preg_match( '/^http:\/\/www\.gogreentube\.com\/watch\.php\?v=(.+)$/', $url, $preg ) ) {
			$id = $preg[1];
		} elseif( preg_match( '/^http:\/\/www\.gogreentube\.com\/embed\/(.+)$/', $url, $preg ) ) {
			$id = $preg[1];
		}

		preg_match( '/([0-9A-Za-z]+)/', $id, $preg );
		$id = $preg[1];

		return $id;
	}

}