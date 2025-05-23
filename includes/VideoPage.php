<?php

use MediaWiki\MediaWikiServices;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Title\Title;

class VideoPage extends Article {

	/**
	 * @var Video
	 */
	private $video;

	/**
	 * Overridden to return WikiVideoPage
	 */
	protected function newPage( Title $title ): WikiVideoPage {
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
		if ( $this->getPage()->getId() ) {
			parent::view();
		} else {
			// Just need to set the right headers
			$out->setArticleFlag( true );
			$out->setRobotPolicy( 'index,follow' );
			$out->setPageTitle( $this->getTitle()->getPrefixedText() );
		}

		if ( $this->video->exists() ) {
			// Display flash video
			$out->addHTML( $this->video->getEmbedCode() );

			// Force embed this code to have width of 300
			$this->video->setWidth( 300 );
			$out->addHTML( $this->getEmbedThisTag() );

			$this->videoHistory();

			$out->addWikiTextAsInterface( '== ' . $ctx->msg( 'video-links' )->escaped() . " ==\n" );
			$this->videoLinks();
		} else {
			// Video doesn't exist, so give a link allowing user to add one with this name
			$title = SpecialPage::getTitleFor( 'AddVideo' );
			$link = $this->linkRenderer->makeKnownLink(
				$title,
				$ctx->msg( 'video-novideo-linktext' )->plain(),
				[],
				[ 'wpTitle' => $this->video->getName() ]
			);
			$out->addHTML( $ctx->msg( 'video-novideo' )->rawParams( $link )->escaped() );

			$out->addWikiTextAsInterface( '== ' . $ctx->msg( 'video-links' )->escaped() . " ==\n" );
			$this->videoLinks();
			$this->mPage->doViewUpdates( $ctx->getUser() );
		}
	}

	/**
	 * Display pages linking to that video on the video page.
	 */
	public function videoLinks() {
		$out = $this->getContext()->getOutput();

		$limit = 100;

		$services = MediaWikiServices::getInstance();
		$dbr = $services->getConnectionProvider()->getReplicaDatabase();

		// WikiaVideo used the imagelinks table here because that extension
		// adds everything into core (archive, filearchive, imagelinks, etc.)
		// tables instead of using its own tables
		$linksMigration = $services->getLinksMigration();
		[ $nsField, $titleField ] = $linksMigration->getTitleFields( 'pagelinks' );
		$queryInfo = $linksMigration->getQueryInfo( 'pagelinks' );
		$res = $dbr->select(
			array_merge( $queryInfo['tables'], [ 'page' ] ),
			[ 'page_namespace', 'page_title' ],
			array_merge( reset( $queryInfo['joins'] )[1], [
				$nsField => NS_VIDEO,
				$titleField => $this->getTitle()->getDBkey(),
				'pl_from = page_id',
			] ),
			__METHOD__,
			[ 'LIMIT' => $limit + 1 ]
		);

		$count = $res->numRows();

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
		foreach ( $res as $s ) {
			$count++;
			if ( $count <= $limit ) {
				// We have not yet reached the extra one that tells us there is
				// more to fetch
				$name = Title::makeTitle( $s->page_namespace, $s->page_title );
				$link = $this->linkRenderer->makeKnownLink( $name );
				$out->addHTML( "<li>{$link}</li>\n" );
			}
		}
		$out->addHTML( "</ul></div>\n" );

		// Add a link to [[Special:WhatLinksHere]]
		if ( $count > $limit ) {
			$out->addWikiMsg(
				'video-more-links-to-video',
				$this->getTitle()->getPrefixedDBkey()
			);
		}
	}

	/**
	 * Get the HTML table that contains the code for embedding the current
	 * video on a wiki page.
	 *
	 * @return-taint none
	 */
	public function getEmbedThisTag(): string {
		$code = $this->video->getEmbedThisCode();
		$code = preg_replace( '/[\n\r\t]/', '', $code ); // replace any non-space whitespace with a space
		$code = str_replace( '_', ' ', $code ); // replace underscores with spaces
		return '<br /><br />
		<table cellpadding="0" cellspacing="2" border="0">
			<tr>
				<td>
					<b>' . $this->getContext()->msg( 'video-embed' )->escaped() . '</b>
				</td>
				<td>
				<form name="embed_video" action="">
					<input name="embed_code" style="width: 300px; font-size: 10px;" type="text" value="' .
						$code . '" onclick="document.embed_video.embed_code.focus();' .
						'document.embed_video.embed_code.select();" readonly="readonly" />
				</form>
				</td>
			</tr>
		</table>';
	}

	/**
	 * If the page we've just displayed is in the "Video" namespace,
	 * we follow it with an upload history of the video and its usage.
	 */
	public function videoHistory(): void {
		$line = $this->video->nextHistoryLine();

		if ( $line ) {
			$list = new VideoHistoryList();
			$s = $list->beginVideoHistoryList() .
				$list->videoHistoryLine(
					true,
					wfTimestamp( TS_MW, $line->video_timestamp ),
					$this->getTitle()->getDBkey(),
					$line->video_actor,
					strip_tags( $line->video_url ),
					$line->video_type,
					$this->getTitle()
				);

			// phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition
			while ( $line = $this->video->nextHistoryLine() ) {
				$s .= $list->videoHistoryLine(
					false,
					$line->video_timestamp,
					$line->ov_archive_name,
					$line->video_actor,
					strip_tags( $line->video_url ),
					$line->video_type,
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
	public function uploadLinksBox(): void {
		$out = $this->getContext()->getOutput();
		$out->addHTML( '<br /><ul>' );

		// "Upload a new version of this video" link
		if ( $this->getContext()->getUser()->isAllowed( 'reupload' ) ) {
			$ulink = $this->linkRenderer->makeLink(
				SpecialPage::getTitleFor( 'AddVideo' ),
				$this->getContext()->msg( 'video-upload-new-version' )->text(),
				[],
				[
					'wpTitle' => $this->video->getName(),
					'forReUpload' => 1,
				]
			);
			$out->addHTML( "<li>{$ulink}</li>" );
		}

		$out->addHTML( '</ul>' );
	}
}
