<?php
/**
 * Class that contains all Video extension's hooked functions.
 * All functions are naturally public and static (what were you expecting?).
 *
 * @file
 * @ingroup Extensions
 */
class VideoHooks {

	/**
	 * Convert [[Video:Video Name]] tags to <video/> hook; calls
	 * VideoHooks::renderVideo to do that.
	 *
	 * @param Parser $parser
	 * @param string $text Input text to search for [[Video:]] tags
	 * @param $strip_state [unused]
	 * @return Boolean: true
	 */
	public static function videoTag( &$parser, &$text, &$strip_state ) {
		$contLang = MediaWiki\MediaWikiServices::getInstance()->getContentLanguage();
		$localizedVideoName = $contLang->getNsText( NS_VIDEO );
		// Fallback code...is this needed?
		if ( $localizedVideoName === false ) {
			$localizedVideoName = 'Video';
		}
		$pattern = '@(\[\[' . $localizedVideoName . ':)([^\]]*?)].*?\]@si';
		$text = preg_replace_callback( $pattern, 'VideoHooks::renderVideo', $text );
		return true;
	}

	/**
	 * Callback function for the preg_replace_callback call in
	 * VideoHooks::videoTag.
	 * Converts [[Video:]] links to <video> parser hooks.
	 * @param $matches
	 * @return string
	 */
	public static function renderVideo( $matches ) {
		$name = $matches[2];
		$params = explode( '|', $name );
		$video_name = $params[0];
		$video = Video::newFromName( $video_name, RequestContext::getMain() );
		$x = 1;

		foreach ( $params as $param ) {
			if ( $x > 1 ) {
				$width_check = preg_match( '/px/i', $param );

				if ( $width_check ) {
					$width = preg_replace( '/px/i', '', $param );
				} else {
					$align = $param;
				}
			}
			$x++;
		}

		if ( is_object( $video ) ) {
			if ( $video->exists() ) {
				$widthTag = $alignTag = '';
				if ( !empty( $width ) ) {
					$widthTag = " width=\"{$width}\"";
				}
				if ( !empty( $align ) ) {
					$alignTag = " align=\"{$align}\"";
				}
				return "<video name=\"{$video->getName()}\"{$widthTag}{$alignTag} />";
			}
			return $matches[0];
		}
	}

	/**
	 * Calls VideoPage instead of standard Article for pages in the NS_VIDEO
	 * namespace.
	 *
	 * @param Title $title Title object for the current page
	 * @param Article $article Article object for the current page
	 * @return void
	 */
	public static function videoFromTitle( &$title, &$article ) {
		global $wgRequest;

		if ( $title->getNamespace() === NS_VIDEO ) {
			if ( $wgRequest->getVal( 'action' ) === 'edit' ) {
				$addTitle = SpecialPage::getTitleFor( 'AddVideo' );
				$video = Video::newFromName( $title->getText(), RequestContext::getMain() );
				if ( !$video->exists() ) {
					global $wgOut;
					$wgOut->redirect(
						$addTitle->getFullURL( 'wpTitle=' . $video->getName() )
					);
				}
			}
			$article = new VideoPage( $title );
		}
	}

	/**
	 * Register the new <video> hook with MediaWiki's parser.
	 *
	 * @param Parser $parser
	 * @return void
	 */
	public static function onParserFirstCallInit( &$parser ) {
		$parser->setHook( 'video', 'VideoHooks::videoEmbed' );
	}

	/**
	 * Callback function for VideoHooks::registerVideoHook.
	 *
	 * @param string $input [unused]
	 * @param array $argv Array of user-supplied arguments; name must be present.
	 *                     Optional args include width, height and align.
	 * @param Parser $parser
	 * @return string Video HTML code suitable for outputting
	 */
	public static function videoEmbed( $input, $argv, Parser $parser ) {
		$video_name = $argv['name'];
		if ( !$video_name ) {
			return '';
		}

		$width = $width_max = 425;
		$height = $height_max = 350;
		$validAlign = [ 'LEFT', 'CENTER', 'RIGHT' ];

		if ( !empty( $argv['width'] ) && ( $width_max >= $argv['width'] ) ) {
			$width = $argv['width'];
		}

		if ( !empty( $argv['height'] ) && ( $height_max >= $argv['height'] ) ) {
			$height = $argv['height'];
		}

		$align = isset( $argv['align'] ) ? $argv['align'] : 'left';
		$alignTag = '';

		if ( in_array( strtoupper( $align ), $validAlign ) ) {
			$alignTag = " class=\"float{$align}\" ";
		}

		$output = '';
		$video = Video::newFromName( $video_name, RequestContext::getMain() );
		if ( $video->exists() ) {
			// If there's such a video, register an internal link
			// so that Special:WhatLinksHere works as intended.
			$parser->getOutput()->addLink( $video->getTitle() );

			$video->setWidth( $width );
			$video->setHeight( $height );

			$output .= "<div{$alignTag}>";
			$output .= $video->getEmbedCode();
			$output .= '</div>';
		}

		return $output;
	}

	/**
	 * Injects Video Gallery into Category pages
	 *
	 * @param CategoryPage $cat
	 * @return void
	 */
	public static function categoryPageWithVideo( &$cat ) {
		$article = new Article( $cat->mTitle );
		$article->view();

		if ( $cat->mTitle->getNamespace() === NS_CATEGORY ) {
			global $wgOut, $wgRequest;
			$from = $wgRequest->getVal( 'from' );
			// @todo CHECKME/FIXME: is this correct? I just added something
			// here to prevent an E_NOTICE about an undefined variable...
			$until = $wgRequest->getVal( 'until' );

			$viewer = new CategoryWithVideoViewer( $cat->mTitle, $cat->getContext(), $from, $until );
			$wgOut->addHTML( $viewer->getHTML() );
		}
	}

	/**
	 * Called on video deletion; this is the main logic for deleting videos.
	 * There is no logic related to video deletion on the VideoPage class.
	 *
	 * @param Article $articleObj Instance of Article or its subclass
	 * @param User $user Current User object ($wgUser)
	 * @param string $reason Reason for the deletion [unused]
	 * @param string $error Error message, if any [unused]
	 * @return void
	 */
	public static function onVideoDelete( &$articleObj, &$user, &$reason, &$error ) {
		if ( $articleObj->getTitle()->getNamespace() === NS_VIDEO ) {
			global $wgRequest;

			$context = ( is_callable( $articleObj, 'getContext' ) ? $articleObj->getContext() : RequestContext::getMain() );
			$videoObj = new Video( $articleObj->getTitle(), $context );
			$videoName = $videoObj->getName();
			$oldVideo = $wgRequest->getVal( 'wpOldVideo', false );
			$where = [
				'video_name' => $videoName
			];
			/*
			BEWARE! THIS DOES NOT WORK HOW YOU WOULD THINK IT DOES...
			IT GENERATES INVALID SQL LIKE video_name = \'(Ayumi_Hamasaki_-_Ladies_Night) OR (Video:Ayumi Hamasaki - Ladies Night)\'
			AND GENERALLY CAUSES THINGS TO EXPLODE!
			$where = [
				'video_name' => $dbw->makeList( [
					$articleObj->getTitle()->getDBkey(),
					$articleObj->getTitle()->getPrefixedText()
				], LIST_OR )
			];
			*/
			if ( !empty( $oldVideo ) ) {
				$where['video_timestamp'] = $oldVideo;
			}

			$dbw = wfGetDB( DB_MASTER );
			// Delicious copypasta from Article.php, function doDeleteArticle()
			// with some modifications
			$archiveName = gmdate( 'YmdHis' ) . "!{$videoName}";
			if ( !empty( $videoName ) ) {
				$dbw->startAtomic( __METHOD__ );
				$dbw->insertSelect(
					'oldvideo',
					'video',
					[
						'ov_name' => 'video_name',
						'ov_archive_name' => $dbw->addQuotes( $archiveName ),
						'ov_url' => 'video_url',
						'ov_type' => 'video_type',
						'ov_user_id' => 'video_user_id',
						'ov_user_name' => 'video_user_name',
						'ov_timestamp' => 'video_timestamp'
					],
					$where,
					__METHOD__
				);

				// Now that it's safely backed up, delete it
				$dbw->delete(
					'video',
					$where,
					__METHOD__
				);

				$dbw->endAtomic( __METHOD__ );
			}

			// Purge caches
			// None of these help, the video is still displayed on pages where
			// it was used until someone edits or does ?action=purge :-(
			/*
			$articleObj->getTitle()->invalidateCache();
			$articleObj->getTitle()->purgeSquid();
			$articleObj->doPurge();
			global $wgMemc;
			$wgMemc->delete( $videoObj->getCacheKey() );
			*/
		}
	}

	/**
	 * Use VideoPageArchive class to properly restore deleted Video pages.
	 * Standard PageArchive allows only to restore the wiki page, not the
	 * associated video.
	 *
	 * @param PageArchive|VideoPageArchive $archive PageArchive object or a child class
	 * @param Title $title Title for the current page that we're about to
	 *                     undelete or view
	 */
	public static function specialUndeleteSwitchArchive( $archive, $title ) {
		if ( $title->getNamespace() === NS_VIDEO ) {
			$archive = new VideoPageArchive( $title );
		}
	}

	/**
	 * Applies the schema changes when the user runs maintenance/update.php.
	 *
	 * @param DatabaseUpdater $updater
	 * @return void
	 */
	public static function addTables( $updater ) {
		$dir = __DIR__;
		$file = "$dir/../sql/video.sql";
		$updater->addExtensionTable( 'video', $file );
		$updater->addExtensionTable( 'oldvideo', $file );
	}

	/**
	 * For the Renameuser extension.
	 *
	 * @param RenameuserSQL $renameUserSQL
	 */
	public static function onUserRename( $renameUserSQL ) {
		$renameUserSQL->tables['oldvideo'] = [ 'ov_user_name', 'ov_user_id' ];
		$renameUserSQL->tables['video'] = [ 'video_user_name', 'video_user_id' ];
	}

	/**
	 * Register the canonical names for our namespace and its talkspace.
	 *
	 * @param array $list Array of namespace numbers with corresponding
	 *                     canonical names
	 */
	public static function onCanonicalNamespaces( &$list ) {
		$list[NS_VIDEO] = 'Video';
		$list[NS_VIDEO_TALK] = 'Video_talk';
	}

	/**
	 * Hook to add Special:UnusedVideos to the list generated by QueryPage::getPages.
	 * Used by the maintenance script updateSpecialPages.
	 *
	 * @param array $wgQueryPages
	 */
	public static function onwgQueryPages( &$wgQueryPages ) {
		$wgQueryPages[] = [ 'SpecialUnusedVideos', 'UnusedVideos' ];
	}
}
