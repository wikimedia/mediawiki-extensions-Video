<?php

use MediaWiki\Category\Category;
use MediaWiki\Category\CategoryViewer;
use MediaWiki\MainConfigNames;
use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;

/**
 * This is like a normal CategoryViewer, except that it supports videos.
 * This is initialized for every category page by the VideoCategoryPage class,
 * which in turn is fired up by VideoHooks::videoFromTitle function in Video.hooks.php.
 */
class CategoryWithVideoViewer extends CategoryViewer {

	private VideoGallery $videogallery;

	/** @inheritDoc */
	protected function clearCategoryState() {
		$this->articles = [];
		$this->articles_start_char = [];
		$this->children = [];
		$this->children_start_char = [];
		if ( $this->showGallery ) {
			$this->gallery = ImageGalleryBase::factory();
		}
		$this->videogallery = new VideoGallery();
	}

	/** @inheritDoc */
	public function getHTML() {
		$this->showGallery = $this->getConfig()->get( MainConfigNames::CategoryMagicGallery )
			&& !$this->getOutput()->getNoGallery();

		$this->clearCategoryState();
		$this->doCategoryQuery();
		$this->finaliseCategoryState();

		return $this->getCategoryTop() .
			$this->getSubcategorySection() .
			$this->getPagesSection() .
			$this->getImageSection() .
			$this->getVideoSection() .
			$this->getCategoryBottom();
	}

	/**
	 * If there are videos on the category, display a message indicating how
	 * many videos are in the category and render the gallery of videos.
	 *
	 * @return string HTML when there are videos on the category
	 */
	private function getVideoSection(): string {
		if ( !$this->videogallery->isEmpty() ) {
			return "<div id=\"mw-category-media\">\n" . '<h2>' .
				$this->msg(
					'category-video-header',
					$this->getTitle()->getText()
				)->escaped() . "</h2>\n" .
				$this->msg(
					'category-video-count',
					$this->videogallery->count()
				)->parse() . $this->videogallery->toHTML() . "\n</div>";
		} else {
			return '';
		}
	}

	/**
	 * Add a page in the video namespace
	 */
	private function addVideo( Title $title ): void {
		$video = new Video( $title, $this->getContext() );
		if ( $this->flip ) {
			$this->videogallery->insert( $video );
		} else {
			$this->videogallery->add( $video );
		}
	}

	/** @inheritDoc */
	protected function doCategoryQuery() {
		$dbr = MediaWikiServices::getInstance()->getConnectionProvider()->getReplicaDatabase( false, 'category' );

		$this->nextPage = [
			'page' => null,
			'subcat' => null,
			'file' => null,
		];
		$this->flip = [
			'page' => false,
			'subcat' => false,
			'file' => false
		];

		foreach ( [ 'page', 'subcat', 'file' ] as $type ) {
			# Get the sortkeys for start/end, if applicable.  Note that if
			# the collation in the database differs from the one
			# set in $wgCategoryCollation, pagination might go totally haywire.
			$extraConds = [ 'cl_type' => $type ];
			if ( isset( $this->from[$type] ) && $this->from[$type] !== null ) {
				$extraConds[] = 'cl_sortkey >= '
					. $dbr->addQuotes( $this->collation->getSortKey( $this->from[$type] ) );
			} elseif ( isset( $this->until[$type] ) && $this->until[$type] !== null ) {
				$extraConds[] = 'cl_sortkey < '
					. $dbr->addQuotes( $this->collation->getSortKey( $this->until[$type] ) );
				$this->flip[$type] = true;
			}

			$res = $dbr->select(
				[ 'page', 'categorylinks', 'category' ],
				[ 'page_id', 'page_title', 'page_namespace', 'page_len',
					'page_is_redirect', 'cl_sortkey', 'cat_id', 'cat_title',
					'cat_subcats', 'cat_pages', 'cat_files',
					'cl_sortkey_prefix', 'cl_collation' ],
				array_merge( [ 'cl_to' => $this->getTitle()->getDBkey() ], $extraConds ),
				__METHOD__,
				[
					'USE INDEX' => [ 'categorylinks' => 'cl_sortkey' ],
					'LIMIT' => $this->limit + 1,
					'ORDER BY' => $this->flip[$type] ? 'cl_sortkey DESC' : 'cl_sortkey',
				],
				[
					'categorylinks' => [ 'INNER JOIN', 'cl_from = page_id' ],
					'category' => [ 'LEFT JOIN', [
						'cat_title = page_title',
						'page_namespace' => NS_CATEGORY
					] ]
				]
			);

			$count = 0;
			foreach ( $res as $row ) {
				$title = Title::newFromRow( $row );
				if ( $row->cl_collation === '' ) {
					// Hack to make sure that while updating from 1.16 schema
					// and db is inconsistent, that the sky doesn't fall.
					// See r83544. Could perhaps be removed in a couple decades...
					$humanSortkey = $row->cl_sortkey;
				} else {
					$humanSortkey = $title->getCategorySortkey( $row->cl_sortkey_prefix );
				}

				if ( ++$count > $this->limit ) {
					# We've reached the one extra which shows that there
					# are additional pages to be had. Stop here...
					$this->nextPage[$type] = $humanSortkey;
					break;
				}

				if ( $title->getNamespace() == NS_CATEGORY ) {
					$cat = Category::newFromRow( $row, $title );
					$this->addSubcategoryObject( $cat, $humanSortkey, $row->page_len );
				} elseif ( $title->getNamespace() == NS_FILE ) {
					$this->addImage( $title, $humanSortkey, $row->page_len, $row->page_is_redirect );
				} elseif ( $title->getNamespace() == NS_VIDEO ) {
					$this->addVideo( $title );
				} else {
					$this->addPage( $title, $humanSortkey, $row->page_len, $row->page_is_redirect );
				}
			}
		}
	}
}
