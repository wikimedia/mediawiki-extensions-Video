<?php

class GametrailersVideo extends FlashVideo {

	var $video,
		$id,
		$url;

	public function __construct( $video ) {
		if( !is_object( $video ) ) {
			throw new MWException( 'GametrailersVideo constructor given bogus video object.' );
		}

		$this->video =& $video;
		$this->video->ratio = 480 / 392;
		$this->id = $this->extractID( $this->video->getURL() );
		$this->url = 'http://www.gametrailers.com/video/play/' . $this->id;
		return $this;
	}

	public function getEmbedCode() {
		$height = $this->video->getHeight();
		$width = $this->video->getWidth();
		$output = '<object classid="clsid:d27cdb6e-ae6d-11cf-96b8-444553540000" codebase="http://download.macromedia.com/pub/shockwave/cabs/flash/swflash.cab#version=8,0,0,0" id="gtembed" width="' . $width . '" height="' . $height . '">
			<param name="allowScriptAccess" value="sameDomain" />
			<param name="allowFullScreen" value="true" />
			<param name="movie" value="http://www.gametrailers.com/remote_wrap.php?mid=' . $this->id . '"/>
			<param name="quality" value="high" />
			<embed src="http://www.gametrailers.com/remote_wrap.php?mid=' . $this->id . '" swLiveConnect="true" name="gtembed" align="middle" allowScriptAccess="sameDomain" allowFullScreen="true" quality="high" pluginspage="http://www.macromedia.com/go/getflashplayer" type="application/x-shockwave-flash" width="' . $width . '" height="' . $height . '"></embed>
		</object>';
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
		$text = strpos( $fixed_url, 'GAMETRAILERS' );
		if( $text !== false ) {
			$parsed = explode( '/', $url );
			if( is_array( $parsed ) ) {
				$id = explode( '?', array_pop( $parsed ) );
				$id = $id[0];
			}
		}

		return $id;
	}

}