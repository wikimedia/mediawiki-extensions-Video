<?php

class VideoPage extends Article {

	public $title = null;

	/**
	 * Constructor and clear the article
	 * @param Title $title
	 */
	public function __construct( $title ) {
		parent::__construct( $title );
	}

	/**
	 * Overridden to return WikiVideoPage
	 *
	 * @param Title $title
	 * @return WikiVideoPage
	 */
	protected function newPage( Title $title ) {
		return new WikiVideoPage( $title );
	}

	/**
	 * Called on every video page view.
	 */
	public function view() {
		$ctx = $this->getContext();
		$this->video = new Video( $this->getTitle(), $ctx );
		$out = $ctx->getOutput();

		// No need to display noarticletext, we use our own message
		if ( $this->getID() ) {
			parent::view();
		} else {
			// Just need to set the right headers
			$out->setArticleFlag( true );
			$out->setRobotPolicy( 'index,follow' );
			$out->setPageTitle( $this->mTitle->getPrefixedText() );
		}

		if ( $this->video->exists() ) {
			// Display flash video
			$out->addHTML( $this->video->getEmbedCode() );

			// Force embed this code to have width of 300
			$this->video->setWidth( 300 );
			$out->addHTML( $this->getEmbedThisTag() );

			$this->videoHistory();

			$out->addWikiText( '== ' . $ctx->msg( 'video-links' )->escaped() . " ==\n" );
			$this->videoLinks();
		} else {
			// Video doesn't exist, so give a link allowing user to add one with this name
			$title = SpecialPage::getTitleFor( 'AddVideo' );
			$link = Linker::linkKnown(
				$title,
				$ctx->msg( 'video-novideo-linktext' )->plain(),
				array(),
				array( 'wpTitle' => $this->video->getName() )
			);
			$out->addHTML( $ctx->msg( 'video-novideo', $link )->text() );

			$out->addWikiText( '== ' . $ctx->msg( 'video-links' )->escaped() . " ==\n" );
			$this->videoLinks();
			$this->mPage->doViewUpdates( $ctx->getUser() );
		}
	}

	/**
	 * Display pages linking to that video on the video page.
	 */
	function videoLinks() {
		$out = $this->getContext()->getOutput();

		$limit = 100;

		$dbr = wfGetDB( DB_SLAVE );

		// WikiaVideo used the imagelinks table here because that extension
		// adds everything into core (archive, filearchive, imagelinks, etc.)
		// tables instead of using its own tables
		$res = $dbr->select(
			array( 'pagelinks', 'page' ),
			array( 'page_namespace', 'page_title' ),
			array(
				'pl_namespace' => NS_VIDEO,
				'pl_title' => $this->getTitle()->getDBkey(),
				'pl_from = page_id',
			),
			__METHOD__,
			array( 'LIMIT' => $limit + 1 )
		);

		$count = $dbr->numRows( $res );

		if ( $count == 0 ) {
			$out->addHTML( '<div id="mw-imagepage-nolinkstoimage">' . "\n" );
			$out->addWikiMsg( 'video-no-links-to-video' );
			$out->addHTML( "</div>\n" );
			return;
		}

		$out->addHTML( '<div id="mw-imagepage-section-linkstoimage">' . "\n" );
		$out->addWikiMsg( 'video-links-to-video', $count );
		$out->addHTML( '<ul class="mw-imagepage-linktoimage">' . "\n" );

		$count = 0;
		while ( $s = $res->fetchObject() ) {
			$count++;
			if ( $count <= $limit ) {
				// We have not yet reached the extra one that tells us there is
				// more to fetch
				$name = Title::makeTitle( $s->page_namespace, $s->page_title );
				$link = Linker::linkKnown( $name );
				$out->addHTML( "<li>{$link}</li>\n" );
			}
		}
		$out->addHTML( "</ul></div>\n" );
		$res->free();

		// Add a link to [[Special:WhatLinksHere]]
		if ( $count > $limit ) {
			$out->addWikiMsg(
				'video-more-links-to-video',
				$this->mTitle->getPrefixedDBkey()
			);
		}
	}

	/** @todo FIXME: is this needed? If not, remove! */
	function getContent() {
		return Article::getContent();
	}

	/**
	 * Get the HTML table that contains the code for embedding the current
	 * video on a wiki page.
	 *
	 * @return string HTML
	 */
	public function getEmbedThisTag() {
		$code = $this->video->getEmbedThisCode();
		$code = preg_replace( '/[\n\r\t]/', '', $code ); // replace any non-space whitespace with a space
		$code = str_replace( '_', ' ', $code ); // replace underscores with spaces
		return '<br /><br />
		<table cellpadding="0" cellspacing="2" border="0">
			<tr>
				<td>
					<b>' . wfMessage( 'video-embed' )->plain() . '</b>
				</td>
				<td>
				<form name="embed_video" action="">
					<input name="embed_code" style="width: 300px; font-size: 10px;" type="text" value="' . $code . '" onclick="javascript:document.embed_video.embed_code.focus();document.embed_video.embed_code.select();" readonly="readonly" />
				</form>
				</td>
			</tr>
		</table>';
	}

	/**
	 * If the page we've just displayed is in the "Video" namespace,
	 * we follow it with an upload history of the video and its usage.
	 */
	function videoHistory() {
		$line = $this->video->nextHistoryLine();

		if ( $line ) {
			$list = new VideoHistoryList();
			$s = $list->beginVideoHistoryList() .
				$list->videoHistoryLine(
					true,
					wfTimestamp( TS_MW, $line->video_timestamp ),
					$this->mTitle->getDBkey(),
					$line->video_user_id,
					$line->video_user_name,
					strip_tags( $line->video_url ),
					$line->video_type,
					$this->getTitle()
				);

			while ( $line = $this->video->nextHistoryLine() ) {
				$s .= $list->videoHistoryLine( false, $line->video_timestamp,
			  		$line->ov_archive_name, $line->video_user_id,
					$line->video_user_name, strip_tags( $line->video_url ), $line->video_type,
					$this->getTitle()
				);
			}
			$s .= $list->endVideoHistoryList();
		} else {
			$s = '';
		}
		$this->getContext()->getOutput()->addHTML( $s );

		// Exist check because we don't want to show this on pages where a video
		// doesn't exist along with the novideo message, that would suck.
		if ( $this->video->exists() ) {
			$this->uploadLinksBox();
		}
	}

	/**
	 * Print out the reupload link at the bottom of a video page for privileged
	 * users.
	 */
	function uploadLinksBox() {
		$out = $this->getContext()->getOutput();
		$out->addHTML( '<br /><ul>' );

		// "Upload a new version of this video" link
		if ( $this->getContext()->getUser()->isAllowed( 'reupload' ) ) {
			$ulink = Linker::link(
				SpecialPage::getTitleFor( 'AddVideo' ),
				$this->getContext()->msg( 'video-upload-new-version' )->plain(),
				array(),
				array(
					'wpTitle' => $this->video->getName(),
					'forReUpload' => 1,
				)
			);
			$out->addHTML( "<li>{$ulink}</li>" );
		}

		$out->addHTML( '</ul>' );
	}
}

/**
 * @todo document
 */
class VideoHistoryList {
	function beginVideoHistoryList() {
		$s = "\n" .
			Xml::element( 'h2', array( 'id' => 'filehistory' ), wfMessage( 'video-history' )->plain() ) .
			"\n<p>" . wfMessage( 'video-histlegend' )->parse() . "</p>\n" . '<ul class="special">';
		return $s;
	}

	function endVideoHistoryList() {
		$s = "</ul>\n";
		return $s;
	}

	function videoHistoryLine( $isCur, $timestamp, $video, $user_id, $user_name, $url, $type, $title ) {
		global $wgUser, $wgLang;

		$datetime = $wgLang->timeanddate( $timestamp, true );
		$cur = wfMessage( 'cur' )->plain();

		if ( $isCur ) {
			$rlink = $cur;
		} else {
			if ( $wgUser->getId() != 0 && $title->userCan( 'edit' ) ) {
				$rlink = Linker::linkKnown(
					$title,
					wfMessage( 'video-revert' )->plain(),
					array(),
					array( 'action' => 'revert', 'oldvideo' => $video )
				);
			} else {
				# Having live active links for non-logged in users
				# means that bots and spiders crawling our site can
				# inadvertently change content. Baaaad idea.
				$rlink = wfMessage( 'video-revert' )->plain();
			}
		}

		$userlink = Linker::userLink( $user_id, $user_name ) .
			Linker::userToolLinks( $user_id, $user_name );

		$style = Linker::getInternalLinkAttributes( $url, $datetime );

		$s = "<li>({$rlink}) <a href=\"{$url}\"{$style}>{$datetime}</a> . . ({$type}) . . {$userlink}";

		$s .= Linker::commentBlock( /*$description*/'', $title );
		$s .= "</li>\n";
		return $s;
	}

}

/**
 * This is like a normal CategoryViewer, except that it supports videos.
 * This is initialized for every category page by
 * VideoHooks::categoryPageWithVideo function in VideoHooks.php.
 */
class CategoryWithVideoViewer extends CategoryViewer {

	function clearCategoryState() {
		$this->articles = array();
		$this->articles_start_char = array();
		$this->children = array();
		$this->children_start_char = array();
		if ( $this->showGallery ) {
			$this->gallery = new ImageGallery();
		}
		#if ( $this->showVideoGallery ) {
			$this->videogallery = new VideoGallery();
			$this->videogallery->setParsing();
		#}
	}

	/**
	 * Format the category data list.
	 *
	 * @return string HTML output
	 */
	function getHTML() {
		global $wgOut, $wgCategoryMagicGallery;
		wfProfileIn( __METHOD__ );

		$this->showGallery = $wgCategoryMagicGallery && !$wgOut->mNoGallery;

		$this->clearCategoryState();
		$this->doCategoryQuery();
		$this->finaliseCategoryState();

		$r = $this->getCategoryTop() .
			$this->getSubcategorySection() .
			$this->getPagesSection() .
			$this->getImageSection() .
			$this->getVideoSection() .
			$this->getCategoryBottom();

		wfProfileOut( __METHOD__ );
		return $r;
	}

	/**
	 * If there are videos on the category, display a message indicating how
	 * many videos are in the category and render the gallery of videos.
	 *
	 * @return string HTML when there are videos on the category
	 */
	function getVideoSection() {
		if ( !$this->videogallery->isEmpty() ) {
			return "<div id=\"mw-category-media\">\n" . '<h2>' .
				wfMessage(
					'category-video-header',
					htmlspecialchars( $this->title->getText() )
				)->text() . "</h2>\n" .
				wfMessage(
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
	function addVideo( $title, $sortkey, $pageLength ) {
		$video = new Video( $title, $this->getContext() );
		if ( $this->flip ) {
			$this->videogallery->insert( $video );
		} else {
			$this->videogallery->add( $video );
		}
	}

	function doCategoryQuery() {
		$dbr = wfGetDB( DB_SLAVE, 'category' );

		$this->nextPage = array(
			'page' => null,
			'subcat' => null,
			'file' => null,
		);
		$this->flip = array( 'page' => false, 'subcat' => false, 'file' => false );

		foreach ( array( 'page', 'subcat', 'file' ) as $type ) {
			# Get the sortkeys for start/end, if applicable.  Note that if
			# the collation in the database differs from the one
			# set in $wgCategoryCollation, pagination might go totally haywire.
			$extraConds = array( 'cl_type' => $type );
			if ( isset( $this->from[$type] ) && $this->from[$type] !== null ) {
				$extraConds[] = 'cl_sortkey >= '
					. $dbr->addQuotes( $this->collation->getSortKey( $this->from[$type] ) );
			} elseif ( isset( $this->until[$type] ) && $this->until[$type] !== null ) {
				$extraConds[] = 'cl_sortkey < '
					. $dbr->addQuotes( $this->collation->getSortKey( $this->until[$type] ) );
				$this->flip[$type] = true;
			}

			$res = $dbr->select(
				array( 'page', 'categorylinks', 'category' ),
				array( 'page_id', 'page_title', 'page_namespace', 'page_len',
					'page_is_redirect', 'cl_sortkey', 'cat_id', 'cat_title',
					'cat_subcats', 'cat_pages', 'cat_files',
					'cl_sortkey_prefix', 'cl_collation' ),
				array_merge( array( 'cl_to' => $this->title->getDBkey() ), $extraConds ),
				__METHOD__,
				array(
					'USE INDEX' => array( 'categorylinks' => 'cl_sortkey' ),
					'LIMIT' => $this->limit + 1,
					'ORDER BY' => $this->flip[$type] ? 'cl_sortkey DESC' : 'cl_sortkey',
				),
				array(
					'categorylinks' => array( 'INNER JOIN', 'cl_from = page_id' ),
					'category' => array( 'LEFT JOIN', array(
						'cat_title = page_title',
						'page_namespace' => NS_CATEGORY
					) )
				)
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
					$this->addVideo( $title, $row->cl_sortkey, $row->page_len, $row->page_is_redirect );
				} else {
					$this->addPage( $title, $humanSortkey, $row->page_len, $row->page_is_redirect );
				}
			}
		}
	}

}
