<?php

class ViddlerVideo extends FlashVideo {

	var $video,
		$id,
		$url;

	/**
	 * @var String: Viddler API key -- this is Wikia's :)
	 */
	const API_KEY = 'hacouneo6n6o3nysn0em';

	public function __construct( $video ) {
		if( !is_object( $video ) ) {
			throw new MWException( 'ViddlerVideo constructor given bogus video object.' );
		}

		$this->video =& $video;
		$this->video->ratio = 437 / 288;
		$this->id = $this->extractID( $this->video->getURL() );
		// This needs to take from their API, since they're doing some conversion on their side
		// URL ID -> embedding ID
		// The above is what Bartek once wrote on the WikiaVideo extension.
		// I'm not sure if my code is correct, but if it's not, feel free to
		// correct me...
		//$this->url = $this->getURLToEmbed();
		$this->url = 'http://www.viddler.com/explore/' . $this->id;
		return $this;
	}

	/**
	 * This function is a combination of WikiaVideo's getUrlToEmbed() and
	 * getViddlerTrueID() functions.
	 * I removed memcached support -- if it ever worked that must've
	 * been good luck, because the code was just...well, wtf: it used
	 * uninitialized $url variable in the memcached key.
	 *
	 * @return Mixed: false in case of failure or video URL in case of success
	 */
	private function getURLToEmbed() {
		//global $wgMemc;

		//$cacheKey = wfMemcKey( 'wvi', 'viddlerid', $this->id, $url );
		//$obj = $wgMemc->get( $cacheKey );

		//if ( isset( $obj ) ) {
		//	return $obj;
		//}

		$url = 'http://api.viddler.com/rest/v1/?method=viddler.videos.getDetailsByUrl&api_key=' .
				self::API_KEY . '&url=http://www.viddler.com/explore/' . $this->id;
		wfSuppressWarnings();
		$file = Http::get( $url );
		$doc = new DOMDocument( '1.0', 'UTF-8' );
		$doc->loadXML( $file );
		wfRestoreWarnings();
		$trueID = trim( $doc->getElementsByTagName( 'id' )->item( 0 )->textContent );
		if ( empty( $trueID ) ) {
			return false;
		}

		//$wgMemc->set( $cacheKey, $trueID, 60 * 60 * 24 );

		return 'http://www.viddler.com/player/' . $trueID . '/';
	}

	public function getEmbedCode() {
		$height = $this->video->getHeight();
		$width = $this->video->getWidth();
		$url = $this->getURLToEmbed();
		$output = "<embed src=\"{$url}\" width=\"{$width}\" height=\"{$height}\" wmode=\"transparent\" allowScriptAccess=\"always\" allowfullscreen=\"true\" pluginspage=\"http://www.macromedia.com/go/getflashplayer\" type=\"application/x-shockwave-flash\"> </embed>";
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
		$text = strpos( $fixed_url, 'VIDDLER.COM' );
		if( $text !== false ) {
			$parsed = explode( '/explore/', strtolower( $url ) );
			if( is_array( $parsed ) ) {
				$mdata = array_pop( $parsed );
				if ( ( $mdata != '' ) && ( strpos( $mdata, '?' ) === false ) ) {
					$id = $mdata;
				} else {
					$id = array_pop( $parsed );
				}
				if ( substr( $id, -1, 1 ) != '/' ) {
					$id .= '/';
				}
			}
		}

		return $id;
	}
