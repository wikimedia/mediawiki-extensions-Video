<?php
/**
 * @file
 */
class BlipTVVideo extends FlashVideo {

	var $video,
		$id,
		$url;

	public function __construct( $video ) {
		if( !is_object( $video ) ) {
			throw new MWException( 'BlipTVVideo constructor given bogus video object.' );
		}

		$this->video =& $video;
		$this->video->ratio = 480 / 350;
		$this->id = $this->extractID( $this->video->getURL() );
		$this->url = 'http://blip.tv/file/' . $this->id;
		return $this;
	}

	public function getEmbedCode() {
		$height = $this->video->getHeight();
		$width = $this->video->getWidth();
		$result = $this->getData();
		$url = 'http://blip.tv/play/' . $result['mTrueID'];
		$output = "<embed src=\"{$url}\" width=\"{$width}\" height=\"{$height}\" wmode=\"transparent\" allowScriptAccess=\"always\" allowfullscreen=\"true\" pluginspage=\"http://www.macromedia.com/go/getflashplayer\" type=\"application/x-shockwave-flash\"> </embed>";
		return $output;
	}

	/**
	 * Extract the video ID from its URL.
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
		$text = strpos( $fixed_url, 'BLIP.TV' );
		if( $text !== false ) {
			$blip = '';
			$parsed = explode( '/', $url );
			if( is_array( $parsed ) ) {
				$mdata = array_pop( $parsed );
				if ( $mdata != '' ) {
					$blip = $mdata;
				} else {
					$blip = array_pop( $parsed );
				}
				$last = explode( '?', $blip );
				$id = $last[0];
			}
		}

		return $id;
	}

	/**
	 * Get BlipTV data (true ID and thumbnail URL) via their API and hold in
	 * memcached.
	 * Thumbnail URL isn't used anywhere at the moment
	 *
	 * @return Mixed: array containing the true ID number and thumbnail URL on
	 *                sucess, boolean false on failure
	 */
	private function getData() {
		global $wgMemc;

		// Try memcached first
		$cacheKey = wfMemcKey( 'video', 'bliptv', $this->id, $url );
		$obj = $wgMemc->get( $cacheKey );

		// Got something? Super! We can return here and give the cached data to
		// the user instead of having to make a HTTP request to Blip.tv API.
		if ( isset( $obj ) ) {
			return $obj;
		}

		$url = 'http://blip.tv/file/' . $this->id . '?skin=rss&version=3';

		wfSuppressWarnings();
		$file = Http::get( $url );
		wfRestoreWarnings();
	 	if ( empty( $file ) ) {
	 		return false;
	 	}
		$doc = new DOMDocument( '1.0', 'UTF-8' );
		wfSuppressWarnings();
		$doc->loadXML( $file );
		wfRestoreWarnings();

		$mTrueID = trim( $doc->getElementsByTagNameNS( 'http://blip.tv/dtd/blip/1.0', 'embedLookup' )->item( 0 )->textContent );
		$thumbnailUrl = trim( $doc->getElementsByTagNameNS( 'http://search.yahoo.com/mrss/', 'thumbnail' )->item( 0 )->getAttribute( 'url' ) );
		$mType = trim( $doc->getElementsByTagNameNS( 'http://blip.tv/dtd/blip/1.0', 'embedUrl' )->item( 0 )->getAttribute( 'type' ) );

		if (
			( $mType !== 'application/x-shockwave-flash' ) ||
			( empty( $mTrueID ) ) || ( empty( $thumbnailUrl ) )
		)
		{
			return false;
		}

		$obj = array(
			'mTrueID' => $mTrueID,
			'thumbnailUrl' => $thumbnailUrl
		);

		$wgMemc->set( $cacheKey, $obj, 60 * 60 * 24 );

		return $obj;
	}
}