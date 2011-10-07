<?php

class ArchiveOrgVideo extends FlashVideo {

	var $video,
		$id,
		$url;

	public function __construct( $video ) {
		if( !is_object( $video ) ) {
			throw new MWException( 'ArchiveOrgVideo constructor given bogus video object.' );
		}

		$this->video =& $video;
		$this->video->ratio = 320 / 263; // or the other way around :) --Jack
		$this->id = $this->extractID( $this->video->getURL() );
		$this->url = "http://www.archive.org/download/{$this->id}.flv";
		return $this;
	}

	public function getEmbedCode() {
		$height = $this->video->getHeight();
		$width = $this->video->getWidth();
		$aovid = $this->extractID();
		$url = "http://www.archive.org/download/{$aovid}.flv";
		$output = "<object type=\"application/x-shockwave-flash\" data=\"http://www.archive.org/flv/FlowPlayerWhite.swf\" width=\"{$width}\" height=\"{$height}\">
			<param name=\"movie\" value=\"http://www.archive.org/flv/FlowPlayerWhite.swf\"/><param name=\"flashvars\" value=\"config={loop: false, videoFile: '{$url}', autoPlay: false}\"/>
		</object>";
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

		if ( preg_match( '/http:\/\/www\.archive\.org\/download\/(.+)\.flv$/', $url, $preg ) ) {
			$id = $preg[1];
		}

		preg_match( '/([0-9A-Za-z_\/.]+)/', $id, $preg );
		$id = $preg[1];

		return $id;
	}

}