<?php

use MediaWiki\MediaWikiServices;

class ViddlerVideoProvider extends BaseVideoProvider {
	private const ID_REGEX = '#src="http://www\.viddler\.com/player/(?<id>[a-zA-Z0-9]*?)/"#';
	// phpcs:disable Generic.Files.LineLength
	protected $embedTemplate = '<object width="$width" height="$height" id="viddlerplayer-$video_id"><param name="movie" value="http://www.viddler.com/player/$video_id/" /><param name="allowScriptAccess" value="always" /><param name="wmode" value="transparent" /><param name="allowFullScreen" value="true" /><embed src="http://www.viddler.com/player/$video_id/" width="$width" height="$height" type="application/x-shockwave-flash" wmode="transparent" allowScriptAccess="always" allowFullScreen="true" name="viddlerplayer-$video_id" ></embed></object>';

	public static function getDomains() {
		return [ 'viddler.com' ];
	}

	protected function getRatio() {
		return 437 / 288;
	}

	protected function extractVideoId( $url ) {
		$cache = MediaWikiServices::getInstance()->getMainWANObjectCache();
		$cacheKey = $cache->makeKey( 'video', 'viddler', sha1( $url ) );
		$cachedEmbedId = $cache->get( $cacheKey );

		if ( $cachedEmbedId !== false ) {
			return $cachedEmbedId;
		}

		$apiUrl = 'http://lab.viddler.com/services/oembed/?format=json&url=' . urlencode( $url );
		$apiResult = Http::get( $apiUrl );

		if ( $apiResult === false ) {
			return null;
		}

		$apiResult = FormatJson::decode( $apiResult, true );

		// Extract the player source from the HTML
		if ( !preg_match( self::ID_REGEX, $apiResult['html'], $matches ) ) {
			return null;
		}

		$embedId = $matches['id'];

		$cache->set( $cacheKey, $embedId, 60 * 60 * 24 );

		return $embedId;
	}
}
