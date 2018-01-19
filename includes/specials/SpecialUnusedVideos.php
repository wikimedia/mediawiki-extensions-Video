<?php

use Wikimedia\Rdbms\ResultWrapper;
use Wikimedia\Rdbms\IDatabase;

/**
 * Special:UnusedVideos - a special page for unused videos
 * Reuses various bits and pieces from SpecialUnusedimages.php and ImageQueryPage.php
 *
 * @file
 * @ingroup Extensions
 */
class SpecialUnusedVideos extends QueryPage {
	/**
	 * Constructor
	 */
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
	 * @param ResultWrapper $res Result pointer
	 * @param int $num Number of available result rows
	 * @param int $offset Paging offset
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

	public function getQueryInfo() {
		return [
			'tables' => [ 'video', 'pagelinks' ],
			'fields' => [
				'namespace' => NS_VIDEO,
				'title' => 'video_name'
			],
			'conds' => [ 'pl_title IS NULL' ],
			'join_conds' => [ 'pagelinks' => [ 'LEFT JOIN', 'pl_title = video_name' ] ]
		];
	}

	// Gotta override this since it's abstract
	public function formatResult( $skin, $result ) {
	}

	public function isExpensive() {
		return true;
	}

	public function getOrderFields() {
		return [ 'title' ];
	}

	protected function getGroupName() {
		return 'maintenance';
	}
}
