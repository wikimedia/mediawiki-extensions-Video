<?php

class WeGameVideo extends FlashVideo {

	var $video,
		$id,
		$url;

	public function __construct( $video ) {
		if( !is_object( $video ) ) {
			throw new MWException( 'WeGameVideo constructor given bogus video object.' );
		}

		$this->video =& $video;
		$this->video->ratio = 488 / 387; // or the other way around...
		$this->id = $this->extractID( $this->video->getURL() );
		$this->url = 'http://www.wegame.com/watch/' . $this->id;
		return $this;
	}

	public function getEmbedCode() {
		$height = $this->video->getHeight();
		$width = $this->video->getWidth();
		$weid = $this->extractID();
		$output = "<object type=\"application/x-shockwave-flash\" data=\"http://www.wegame.com/static/flash/player2.swf\" width=\"{$width}\" height=\"{$height}\">";
		$output .= "<param name=\"flashvars\" value=\"tag={$weid}\"/></object>";
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

		if ( preg_match( '/^http:\/\/www\.wegame\.com\/watch\/(.+)\/$/', $url, $preg ) ) {
			$id = $preg[1];
		}

		preg_match( '/([0-9A-Za-z_-]+)/', $id, $preg );
		$id = $preg[1];

		return $id;
	}

}