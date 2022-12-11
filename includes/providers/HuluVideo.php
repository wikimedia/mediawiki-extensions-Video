<?php
/**
 * @file
 * @author William Lee <wlee@wikia-inc.com>
 * @see http://trac.wikia-code.com/changeset/38530
 */

use MediaWiki\MediaWikiServices;

class HuluVideoProvider extends BaseVideoProvider {
	protected $embedTemplate = '<object width="$width" height="$height"><param name="movie" value="$video_id"></param><param name="allowFullScreen" value="true"></param><embed src="$video_id" type="application/x-shockwave-flash"  width="$width" height="$height" allowFullScreen="true"></embed></object>';

	public static function getDomains() {
		return [ 'hulu.com' ];
	}

	protected function getRatio() {
		return 512 / 296;
	}

	protected function extractVideoId( $url ) {
		if ( !preg_match( '#/watch/(?<id>\d+)/#', $url, $matches ) ) {
			return null;
		}

		$videoId = $matches['id'];

		$cache = MediaWikiServices::getInstance()->getMainWANObjectCache();
		$cacheKey = $cache->makeKey( 'video', 'hulu', $videoId );
		$cachedEmbedId = $cache->get( $cacheKey );

		if ( $cachedEmbedId !== false ) {
			return $cachedEmbedId;
		}

		$apiUrl = 'http://www.hulu.com/api/oembed.json?url=' . urlencode( $url );
		$apiResult = MediaWikiServices::getInstance()->getHttpRequestFactory()->get( $apiUrl );

		if ( $apiResult === null ) {
			return null;
		}

		$apiResult = FormatJson::decode( $apiResult, true );
		$embedId = $apiResult['embed_url'];

		$cache->set( $cacheKey, $embedId, 60 * 60 * 24 );

		return $embedId;
	}
}
