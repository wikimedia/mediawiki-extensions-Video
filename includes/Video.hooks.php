<?php

use MediaWiki\MediaWikiServices;
use MediaWiki\Page\ProperPageIdentity;
use MediaWiki\Permissions\Authority;

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
	 * @param string &$text Input text to search for [[Video:]] tags
	 * @param StripState $strip_state [unused]
	 */
	public static function videoTag( $parser, &$text, $strip_state ) {
		$contLang = MediaWikiServices::getInstance()->getContentLanguage();
		$localizedVideoName = $contLang->getNsText( NS_VIDEO );
		// Fallback code...is this needed?
		if ( $localizedVideoName === false ) {
			$localizedVideoName = 'Video';
		}
		$pattern = '@(\[\[' . $localizedVideoName . ':)([^\]]*?)].*?\]@si';
		$text = preg_replace_callback( $pattern, [ self::class, 'renderVideo' ], $text );
	}

	/**
	 * Callback function for the preg_replace_callback call in
	 * VideoHooks::videoTag.
	 * Converts [[Video:]] links to <video> parser hooks.
	 * @param string[] $matches
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
	 * namespace, and VideoCategoryPage instead of CategoryPage for NS_CATEGORY
	 * pages.
	 *
	 * @param Title $title Title object for the current page
	 * @param Article &$article Article object for the current page
	 * @param IContextSource $context
	 */
	public static function onArticleFromTitle( $title, &$article, $context ) {
		if ( $title->getNamespace() === NS_VIDEO ) {
			if ( $context->getRequest()->getRawVal( 'action' ) === 'edit' ) {
				$addTitle = SpecialPage::getTitleFor( 'AddVideo' );
				$video = Video::newFromName( $title->getText(), $context );
				if ( !$video->exists() ) {
					$context->getOutput()->redirect(
						$addTitle->getFullURL( 'wpTitle=' . $video->getName() )
					);
				}
			}

			$article = new VideoPage( $title );
		} elseif ( $title->inNamespace( NS_CATEGORY ) ) {
			// For grep: this category is what initializes an instance of CategoryWithVideoViewer
			// but the logic is prone to giving you a headache. Not my fault, though!
			$article = new VideoCategoryPage( $title );
		}
	}

	/**
	 * Register the new <video> hook with MediaWiki's parser.
	 *
	 * @param Parser $parser
	 * @return void
	 */
	public static function onParserFirstCallInit( $parser ) {
		$parser->setHook( 'video', [ self::class, 'videoEmbed' ] );
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

		$align = $argv['align'] ?? 'left';
		$alignTag = '';

		if ( in_array( strtoupper( $align ), $validAlign ) ) {
			// phan doesn't want to let me suppress the issue about alleged XSS since
			// phan doesn't understand our custom validation logic here.
			// Per discussion with Skizzerz, just make the issue go away with a
			// simple htmlspecialchars() call, it'll have no adverse effects.
			// @todo FIXME: but as per the discussion, this code needs some TLC.
			$alignTag = htmlspecialchars( " class=\"float{$align}\" " );
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
	 * Called on video deletion; this is the main logic for deleting videos.
	 * There is no logic related to video deletion on the VideoPage class.
	 *
	 * @param ProperPageIdentity $page
	 * @param Authority $deleter
	 * @param string $reason
	 * @param StatusValue $status
	 * @param bool $suppress
	 */
	public static function onPageDelete(
		ProperPageIdentity $page,
		Authority $deleter,
		string $reason,
		StatusValue $status,
		bool $suppress
	) {
		if ( $page->getNamespace() !== NS_VIDEO ) {
			return;
		}

		$title = MediaWikiServices::getInstance()->getWikiPageFactory()
			->newFromTitle( $page )->getTitle();

		$context = RequestContext::getMain();
		$videoObj = new Video( $title, $context );
		$videoName = $videoObj->getName();
		$oldVideo = $context->getRequest()->getVal( 'wpOldVideo', '' );
		$where = [
			'video_name' => $videoName
		];
		/*
		BEWARE! THIS DOES NOT WORK HOW YOU WOULD THINK IT DOES...
		IT GENERATES INVALID SQL LIKE video_name = \'(Ayumi_Hamasaki_-_Ladies_Night) OR (Video:Ayumi Hamasaki - Ladies Night)\'
		AND GENERALLY CAUSES THINGS TO EXPLODE!
		$where = [
			'video_name' => $dbw->makeList( [
				$page->getDBkey(),
				$title->getPrefixedText()
			], LIST_OR )
		];
		*/
		if ( $oldVideo ) {
			$where['video_timestamp'] = $oldVideo;
		}

		$dbw = MediaWikiServices::getInstance()->getConnectionProvider()->getPrimaryDatabase();
		// Delicious copypasta from Article.php, function doDeleteArticle()
		// with some modifications
		$archiveName = gmdate( 'YmdHis' ) . "!{$videoName}";
		if ( $videoName ) {
			$dbw->startAtomic( __METHOD__ );
			$dbw->insertSelect(
				'oldvideo',
				'video',
				[
					'ov_name' => 'video_name',
					'ov_archive_name' => $dbw->addQuotes( $archiveName ),
					'ov_url' => 'video_url',
					'ov_type' => 'video_type',
					'ov_actor' => 'video_actor',
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

			$videoObj->clearCache();
		}
	}

	/**
	 * Called on video undeletion; this is the main logic for undeleting videos.
	 * There is no logic related to video undeletion on the VideoPage class.
	 *
	 * @param ProperPageIdentity $page
	 * @param Authority $performer
	 * @param string $reason
	 * @param bool $unsuppress
	 * @param array $timestamps
	 * @param array $fileVersions
	 * @param StatusValue $status
	 */
	public static function onPageUndelete(
		ProperPageIdentity $page,
		Authority $performer,
		string $reason,
		bool $unsuppress,
		array $timestamps,
		array $fileVersions,
		StatusValue $status
	) {
		// We currently restore only whole deleted videos, a restore link from
		// log could take us here...
		if ( $page->exists() || $page->getNamespace() !== NS_VIDEO ) {
			return;
		}

		$dbw = MediaWikiServices::getInstance()->getConnectionProvider()->getPrimaryDatabase();

		$result = $dbw->select(
			'oldvideo',
			'*',
			[ 'ov_name' => $page->getDBkey() ],
			__METHOD__,
			[ 'ORDER BY' => 'ov_timestamp DESC' ]
		);

		$insertBatch = [];
		$insertCurrent = false;
		$archiveName = '';
		$first = true;

		foreach ( $result as $row ) {
			if ( $first ) {
				// this is our new current revision
				$insertCurrent = [
					'video_name' => $row->ov_name,
					'video_url' => $row->ov_url,
					'video_type' => $row->ov_type,
					'video_actor' => $row->ov_actor,
					'video_timestamp' => $row->ov_timestamp
				];
			} else {
				// older revisions, they could be even elder current ones from ancient deletions
				$insertBatch = [
					'ov_name' => $row->ov_name,
					'ov_archive_name' => $archiveName,
					'ov_url' => $row->ov_url,
					'ov_type' => $row->ov_type,
					'ov_actor' => $row->ov_actor,
					'ov_timestamp' => $row->ov_timestamp
				];
			}

			$first = false;
		}

		unset( $result );

		if ( $insertCurrent ) {
			$dbw->insert( 'video', $insertCurrent, __METHOD__ );
			// At this point there are two entries for our video, in both tables,
			// even if the video had only one (video) history entry.
			// We need to delete the oldvideo entry here so that "duplicate"
			// entries won't show up under "Video History" on the appropriate Video:
			// page.
			$dbw->delete( 'oldvideo', [ 'ov_name' => $page->getDBkey() ], __METHOD__ );
		}

		if ( $insertBatch ) {
			$dbw->insert( 'oldvideo', $insertBatch, __METHOD__ );
		}
	}

	/**
	 * Use VideoPageArchive class to properly list files using VideoPageArchive::listFiles().
	 * Standard PageArchive allows only to restore the wiki page, not the
	 * associated video.
	 *
	 * @param PageArchive &$archive PageArchive object
	 * @param Title $title Title for the current page that we're about to
	 *                     undelete or view
	 * @phpcs:disable MediaWiki.NamingConventions.LowerCamelFunctionsName.FunctionName
	 */
	public static function onUndeleteForm__showHistory( &$archive, $title ) {
		// phpcs:enable MediaWiki.NamingConventions.LowerCamelFunctionsName.FunctionName

		if ( $title->getNamespace() === NS_VIDEO ) {
			$archive = new VideoPageArchive( $title );
		}
	}

	/**
	 * Applies the schema changes when the user runs maintenance/update.php.
	 *
	 * @param DatabaseUpdater $updater
	 */
	public static function onLoadExtensionSchemaUpdates( $updater ) {
		$dir = __DIR__ . '/../sql';

		$db = $updater->getDB();
		$dbType = $db->getType();

		$oldvideo = 'oldvideo.sql';
		$video = 'video.sql';
		if ( !in_array( $dbType, [ 'mysql', 'sqlite' ] ) ) {
			$oldvideo = "oldvideo.{$dbType}.sql";
			$video = "video.{$dbType}.sql";
		}

		$updater->addExtensionTable( 'video', "$dir/$video" );
		$updater->addExtensionTable( 'oldvideo', "$dir/$oldvideo" );

		$videoTableHasActorField = $db->fieldExists( 'video', 'video_actor', __METHOD__ );
		$oldvideoTableHasActorField = $db->fieldExists( 'oldvideo', 'ov_actor', __METHOD__ );

		// Actor support
		if ( !$videoTableHasActorField ) {
			// 1) add new actor column
			$updater->addExtensionField( 'video', 'video_actor', $dir . '/patches/actor/add_video_actor_field_to_video.sql' );
		}

		if ( !$oldvideoTableHasActorField ) {
			// 1) add new actor column
			$updater->addExtensionField( 'oldvideo', 'ov_actor', $dir . '/patches/actor/add_ov_actor_field_to_oldvideo.sql' );
		}

		// The only time both tables have both an _actor and a _user_name column at
		// the same time is when upgrading from an older version to v. 1.9.0;
		// all versions prior to that will have only the _user_name columns (and the
		// corresponding _user_id columns, but we assume here that if the _user_name
		// columns are present, the _user_id ones must also be) and v. 1.9.0 and newer
		// will only have the _actor columns.
		// If both are present, then we know that we're in the middle of migration and
		// we should complete the migration ASAP.
		if (
			$db->fieldExists( 'video', 'video_actor', __METHOD__ ) &&
			$db->fieldExists( 'video', 'video_user_name', __METHOD__ ) &&
			$db->fieldExists( 'oldvideo', 'ov_actor', __METHOD__ ) &&
			$db->fieldExists( 'oldvideo', 'ov_user_name', __METHOD__ )
		) {
			// 2) populate the columns with correct values
			// PITFALL WARNING! Do NOT change this to $updater->runMaintenance,
			// THEY ARE NOT THE SAME THING and this MUST be using addExtensionUpdate
			// instead for the code to work as desired!
			// HT Skizzerz
			$updater->addExtensionUpdate( [
				'runMaintenance',
				'MigrateOldVideoUserColumnsToActor',
				'../maintenance/migrateOldVideoUserColumnsToActor.php'
			] );

			// 3) drop old columns
			$updater->dropExtensionField( 'video', 'video_user_name', $dir . '/patches/actor/drop_video_user_name_field_from_video.sql' );
			$updater->dropExtensionField( 'video', 'video_user_id', $dir . '/patches/actor/drop_video_user_id_field_from_video.sql' );

			$updater->dropExtensionField( 'oldvideo', 'ov_user_name', $dir . '/patches/actor/drop_ov_user_name_field_from_oldvideo.sql' );
			$updater->dropExtensionField( 'oldvideo', 'ov_user_id', $dir . '/patches/actor/drop_ov_user_id_field_from_oldvideo.sql' );
		}
	}

	/**
	 * Hook to add Special:UnusedVideos to the list generated by QueryPage::getPages.
	 * Used by the maintenance script updateSpecialPages.
	 *
	 * @param array[] &$qp List of QueryPages
	 */
	public static function onWgQueryPages( &$qp ) {
		$qp[] = [ SpecialUnusedVideos::class, 'UnusedVideos' ];
	}
}
