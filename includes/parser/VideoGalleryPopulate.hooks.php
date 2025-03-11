<?php

/**
 * <videogallerypopulate> parser hook extension -- display a gallery of all
 * videos in a specific category
 *
 * @file
 * @ingroup Extensions
 */

use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;

class VideoGalleryPopulateHooks {

	/**
	 * @param Parser $parser
	 */
	public static function onParserFirstCallInit( $parser ) {
		$parser->setHook( 'videogallerypopulate', [ __CLASS__, 'renderVideoGalleryPopulate' ] );
	}

	/**
	 * @param string $input
	 * @param string[] $args
	 * @param Parser $parser
	 * @return string
	 */
	public static function renderVideoGalleryPopulate( $input, $args, $parser ) {
		$parser->getOutput()->updateCacheExpiry( 0 );

		// @phan-suppress-next-line PhanPluginDuplicateConditionalNullCoalescing
		$category = ( isset( $args['category'] ) ) ? $args['category'] : '';
		$limit = ( isset( $args['limit'] ) ) ? intval( $args['limit'] ) : 10;

		if ( !$category ) {
			return '';
		}

		$category = $parser->recursivePreprocess( $category );
		$category_title = Title::newFromText( $category );
		if ( !( $category_title instanceof Title ) ) {
			return '';
		}

		// @todo FIXME: not overly i18n-friendly here...
		$category_title_secondary = Title::newFromText( $category . ' Videos' );
		if ( !( $category_title_secondary instanceof Title ) ) {
			return '';
		}

		$params = [];
		$params['ORDER BY'] = 'page_id';
		if ( $limit ) {
			$params['LIMIT'] = $limit;
		}

		$dbr = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_REPLICA );
		$res = $dbr->select(
			[ 'page', 'categorylinks' ],
			'page_title',
			[
				'cl_to' => [
					$category_title->getDBkey(),
					$category_title_secondary->getDBkey()
				],
				'page_namespace' => NS_VIDEO
			],
			__METHOD__,
			$params,
			[ 'categorylinks' => [ 'INNER JOIN', 'cl_from = page_id' ] ]
		);

		$gallery = new VideoGallery();
		$gallery->setShowFilename( true );

		foreach ( $res as $row ) {
			$video = Video::newFromName( $row->page_title, RequestContext::getMain() );
			$gallery->add( $video );
		}

		return $gallery->toHTML();
	}
}
