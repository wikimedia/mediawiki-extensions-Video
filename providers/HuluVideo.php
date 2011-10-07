<?php
/**
 * @file
 * @author William Lee <wlee@wikia-inc.com>
 * @see http://trac.wikia-code.com/changeset/38530
 */
class HuluVideo extends FlashVideo {

	var $video,
		$id,
		$url;

	public function __construct( $video ) {
		if( !is_object( $video ) ) {
			throw new MWException( 'HuluVideo constructor given bogus video object.' );
		}

		$this->video =& $video;
		$this->video->ratio = 512 / 296;
		$this->id = $this->extractID( $this->video->getURL() );
		$this->url = 'http://www.hulu.com/watch/' . $this->id;
		return $this;
	}

	public function getEmbedCode() {
		$height = $this->video->getHeight();
		$width = $this->video->getWidth();
		$data = $this->getData();
		$url = 'http://www.hulu.com/embed/' . $data['embedId'];
		$output = "<embed src=\"{$url}\" width=\"{$width}\" height=\"{$height}\" wmode=\"transparent\" allowScriptAccess=\"always\" allowfullscreen=\"true\" pluginspage=\"http://www.macromedia.com/go/getflashplayer\" type=\"application/x-shockwave-flash\"> </embed>";
		return $output;
	}

	/**
	 * Extract the video ID from its URL.
	 *
	 * @return Mixed: video ID (integer) on success, boolean false on failure
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
		$text = strpos( $fixed_url, 'HULU.COM' );
		if( $text !== false ) {
			// Hulu goes like
			// http://www.hulu.com/watch/252775/[seo terms]
			$url = trim( $url, '/' );
			$parsed = explode( '/', $url );
			if( is_array( $parsed ) ) {
				// $id is a number, and it is either last or second to last element of $parsed
				$last = explode( '?', array_pop( $parsed ) );
				$last = $last[0];
				if ( is_numeric( $last ) ) {
					$id = $last;
				} else {
					$id = array_pop( $parsed );
					//$seo = $last;
				}
				/*
				$this->mData = null; // getData() works only if mData is null
				$huluData = $this->getData();
				$this->mData = array();
				if ( is_array( $huluData ) ) {
					foreach ( $huluData as $key => $value ) {
						$this->mData[] = $value;
					}
				}
				if ( !empty( $seo ) ) {
					$this->mData[] = $seo;
				}
				*/
			}
		}

		return $id;
	}

	private function getData() {
		$data = array();

		/*
		if ( !empty( $this->mData ) ) {
			// metadata could be a one-element array, expressed in serialized form.
			// If so, deserialize
			if ( sizeof( $this->mData ) == 1 ) {
				$this->mData = explode( ',', $this->mData[0] );
			}

			$data['embedId'] = $this->mData[0];
			$data['thumbnailUrl'] = $this->mData[1];
			$data['videoName'] = $this->mData[2];
			if ( sizeof( $this->mData ) > 3 ) {
				$data['seo'] = $this->mData[3];
			}
		} else {*/
			wfSuppressWarnings();
			$file = Http::get(
				'http://www.hulu.com/api/oembed.xml?url=' .
				urlencode( 'http://www.hulu.com/watch/' . $this->id ), false );
			wfRestoreWarnings();

			if ( $file ) {
				$doc = new DOMDocument( '1.0', 'UTF-8' );
				wfSuppressWarnings();
				$doc->loadXML( $file );
				wfRestoreWarnings();
				$embedUrl = trim( $doc->getElementsByTagName( 'embed_url' )->item( 0 )->textContent );
				$embedUrlParts = explode( '/', $embedUrl );
				$data['embedId'] = array_pop( $embedUrlParts );
				$data['thumbnailUrl'] = trim( $doc->getElementsByTagName( 'thumbnail_url' )->item( 0 )->textContent );
				$data['videoName'] = trim( $doc->getElementsByTagName( 'title' )->item( 0 )->textContent );
			}
		/*}*/
		return $huluData;
	}
}