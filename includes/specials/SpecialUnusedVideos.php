<?php

use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\IResultWrapper;

/**
 * Special:UnusedVideos - a special page for unused videos
 * Reuses various bits and pieces from SpecialUnusedimages.php and ImageQueryPage.php
 *
 * @file
 * @ingroup Extensions
 */
class SpecialUnusedVideos extends QueryPage {
	public function __construct() {
		parent::__construct( 'UnusedVideos' );
	}

	/**
	 * Format and output report results using the given information plus
	 * OutputPage
	 *
	 * @param OutputPage $out OutputPage to print to
	 * @param Skin $skin User skin to use [unused]
	 * @param IDatabase $dbr (read) connection to use
	 * @param IResultWrapper $res Result pointer
	 * @param int $num Number of available result rows
	 * @param int $offset Paging offset
	 * @suppress PhanParamSignatureMismatch This is just a documentation/MW version mismatch thing,
	 * not a real issue and the suppression can be removed once we support&require a 1.4x series MW
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
		$linksMigration = MediaWikiServices::getInstance()->getLinksMigration();
		[ , $titleField ] = $linksMigration->getTitleFields( 'pagelinks' );
		$queryInfo = $linksMigration->getQueryInfo( 'pagelinks', 'pagelinks' );
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
	 * @param Skin $skin
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
