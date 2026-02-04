<?php

use MediaWiki\Linker\LinksMigration;
use MediaWiki\Title\Title;

/**
 * Special:UnusedVideos - a special page for unused videos
 * Reuses various bits and pieces from SpecialUnusedimages.php and ImageQueryPage.php
 *
 * @file
 * @ingroup Extensions
 */
class SpecialUnusedVideos extends MediaWiki\SpecialPage\QueryPage {
	public function __construct(
		private readonly LinksMigration $linksMigration,
	) {
		parent::__construct( 'UnusedVideos' );
	}

	/**
	 * @inheritDoc
	 */
	protected function outputResults( $out, $skin, $dbr, $res, $num, $offset ) {
		if ( $num > 0 ) {
			$gallery = new VideoGallery();

			# $res might contain the whole 1,000 rows, so we read up to
			# $num [should update this to use a Pager]
			$i = 0;
			foreach ( $res as $row ) {
				$i++;
				$title = Title::makeTitle( NS_VIDEO, $row->title );
				$video = new Video( $title, $this->getContext() );

				$gallery->add( $video );
				if ( $i === $num ) {
					break;
				}
			}

			$out->addHTML( $gallery->toHTML() );
		}
	}

	/** @inheritDoc */
	public function getQueryInfo() {
		[ , $titleField ] = $this->linksMigration->getTitleFields( 'pagelinks' );
		$queryInfo = $this->linksMigration->getQueryInfo( 'pagelinks', 'pagelinks' );
		return [
			'tables' => array_merge( $queryInfo['tables'], [ 'video' ] ),
			'fields' => [
				'namespace' => NS_VIDEO,
				'title' => 'video_name'
			],
			'conds' => [ $titleField . ' IS NULL' ],
			'join_conds' => array_merge(
				$queryInfo['joins'],
				[ 'video' => [ 'RIGHT JOIN', $titleField . ' = video_name' ] ]
			)
		];
	}

	/**
	 * Gotta override this since it's abstract
	 *
	 * @param MediaWiki\Skin\Skin $skin
	 * @param stdClass $result
	 * @return string
	 */
	public function formatResult( $skin, $result ) {
		return '';
	}

	/** @inheritDoc */
	public function isExpensive() {
		return true;
	}

	/** @inheritDoc */
	public function getOrderFields() {
		return [ 'title' ];
	}

	/** @inheritDoc */
	protected function getGroupName() {
		return 'maintenance';
	}
}
