<?php
/**
 * Wiki Video Namespace
 *
 * @file
 * @ingroup Extensions
 * @author David Pean <david.pean@gmail.com> - original code/ideas
 * @author Jack Phoenix <jack@countervandalism.net>
 * @copyright Copyright © 2007 David Pean, Wikia Inc.
 * @copyright Copyright © 2008-2014 Jack Phoenix
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License 2.0 or later
 * @link https://www.mediawiki.org/wiki/Extension:Video Documentation
 */

// Bail out if we're not inside MediaWiki
if ( !defined( 'MEDIAWIKI' ) ) {
	die( "This is not a valid entry point.\n" );
}

// Extension credits that show up on Special:Version
$wgExtensionCredits['other'][] = array(
	'name' => 'Video',
	'version' => '1.5.0',
	'author' => array( 'David Pean', 'Jack Phoenix', 'John Du Hart' ),
	'description' => 'Allows new Video namespace for embeddable media on supported sites',
	'url' => 'https://www.mediawiki.org/wiki/Extension:Video',
);

// ResourceLoader support for MediaWiki 1.17+
$wgResourceModules['ext.video'] = array(
	'styles' => 'Video.css',
	'localBasePath' => __DIR__,
	'remoteExtPath' => 'Video'
);

// Global video namespace reference
if ( !defined( 'NS_VIDEO' ) ) {
	define( 'NS_VIDEO', 400 );
}

if ( !defined( 'NS_VIDEO_TALK' ) ) {
	define( 'NS_VIDEO_TALK', 401 );
}

// Define permissions
$wgAvailableRights[] = 'addvideo';
$wgGroupPermissions['*']['addvideo'] = false;
$wgGroupPermissions['user']['addvideo'] = true;

// Set up i18n and autoload the gazillion different classes we have
$wgMessagesDirs['Video'] = __DIR__ . '/i18n';
$wgExtensionMessagesFiles['VideoAlias'] = __DIR__ . '/Video.alias.php';
// Namespace translations
$wgExtensionMessagesFiles['VideoNamespaces'] = __DIR__ . '/Video.namespaces.php';

// Base Video class
$wgAutoloadClasses['Video'] = __DIR__ . '/VideoClass.php';

// ...and the dozen different provider classes
$wgAutoloadClasses['ArchiveOrgVideoProvider'] = __DIR__ . '/providers/ArchiveOrgVideo.php';
$wgAutoloadClasses['BlipTVVideoProvider'] = __DIR__ . '/providers/BlipTVVideo.php';
$wgAutoloadClasses['DailyMotionVideoProvider'] = __DIR__ . '/providers/DailyMotionVideo.php';
$wgAutoloadClasses['BaseVideoProvider'] = __DIR__ . '/providers/BaseVideoProvider.php';
$wgAutoloadClasses['GametrailersVideoProvider'] = __DIR__ . '/providers/GametrailersVideo.php';
$wgAutoloadClasses['GamevideosVideoProvider'] = __DIR__ . '/providers/GamevideosVideo.php';
$wgAutoloadClasses['GoGreenTubeVideoProvider'] = __DIR__ . '/providers/GoGreenTubeVideo.php';
$wgAutoloadClasses['GoogleVideoProvider'] = __DIR__ . '/providers/GoogleVideo.php';
$wgAutoloadClasses['HuluVideoProvider'] = __DIR__ . '/providers/HuluVideo.php';
$wgAutoloadClasses['MetaCafeVideoProvider'] = __DIR__ . '/providers/MetaCafeVideo.php';
$wgAutoloadClasses['MySpaceVideoProvider'] = __DIR__ . '/providers/MySpaceVideo.php';
$wgAutoloadClasses['MovieClipsVideoProvider'] = __DIR__ . '/providers/MovieClipsVideo.php';
$wgAutoloadClasses['MTVNetworksVideoProvider'] = __DIR__ . '/providers/MTVNetworksVideo.php';
$wgAutoloadClasses['MyVideoVideoProvider'] = __DIR__ . '/providers/MyVideoVideo.php';
$wgAutoloadClasses['NewsRoomVideoProvider'] = __DIR__ . '/providers/NewsRoomVideo.php';
$wgAutoloadClasses['SevenloadVideoProvider'] = __DIR__ . '/providers/SevenloadVideo.php';
$wgAutoloadClasses['SouthParkStudiosVideoProvider'] = __DIR__ . '/providers/SouthParkStudiosVideo.php';
$wgAutoloadClasses['ViddlerVideoProvider'] = __DIR__ . '/providers/ViddlerVideo.php';
$wgAutoloadClasses['VimeoVideoProvider'] = __DIR__ . '/providers/VimeoVideo.php';
$wgAutoloadClasses['WeGameVideoProvider'] = __DIR__ . '/providers/WeGameVideo.php';
$wgAutoloadClasses['YouTubeVideoProvider'] = __DIR__ . '/providers/YouTubeVideo.php';

// User Interface stuff
$wgAutoloadClasses['VideoPage'] = __DIR__ . '/VideoPage.php';
$wgAutoloadClasses['WikiVideoPage'] = __DIR__ . '/WikiVideoPage.php';
$wgAutoloadClasses['RevertVideoAction'] = __DIR__ . '/RevertVideoAction.php';
$wgAutoloadClasses['VideoHistoryList'] = __DIR__ . '/VideoPage.php';
$wgAutoloadClasses['CategoryWithVideoViewer'] = __DIR__ . '/VideoPage.php';

$wgAutoloadClasses['VideoGallery'] = __DIR__ . '/VideoGallery.php';

// Class for undeleting previously deleted videos
$wgAutoloadClasses['VideoPageArchive'] = __DIR__ . '/VideoPageArchive.php';
$wgAutoloadClasses['ArchivedVideo'] = __DIR__ . '/ArchivedVideo.php';

// New special pages
$wgAutoloadClasses['AddVideo'] = __DIR__ . '/SpecialAddVideo.php';
$wgAutoloadClasses['NewVideos'] = __DIR__ . '/SpecialNewVideos.php';
$wgSpecialPages['AddVideo'] = 'AddVideo';
$wgSpecialPages['NewVideos'] = 'NewVideos';

// Hook things up
$wgAutoloadClasses['VideoHooks'] = __DIR__ . '/VideoHooks.php';

$wgHooks['ArticleFromTitle'][] = 'VideoHooks::videoFromTitle';
$wgHooks['CategoryPageView'][] = 'VideoHooks::categoryPageWithVideo';
$wgHooks['ParserBeforeStrip'][] = 'VideoHooks::videoTag';
$wgHooks['ParserFirstCallInit'][] = 'VideoHooks::registerVideoHook';
$wgHooks['ArticleDelete'][] = 'VideoHooks::onVideoDelete';
$wgHooks['UndeleteForm::showRevision'][] = 'VideoHooks::specialUndeleteSwitchArchive';
$wgHooks['UndeleteForm::showHistory'][] = 'VideoHooks::specialUndeleteSwitchArchive';
$wgHooks['UndeleteForm::undelete'][] = 'VideoHooks::specialUndeleteSwitchArchive';
$wgHooks['LoadExtensionSchemaUpdates'][] = 'VideoHooks::addTables';
$wgHooks['RenameUserSQL'][] = 'VideoHooks::onUserRename'; // For the Renameuser extension
$wgHooks['CanonicalNamespaces'][] = 'VideoHooks::onCanonicalNamespaces';

// Set up logging
$wgLogTypes[] = 'video';
$wgLogNames['video'] = 'video-log-page';
$wgLogHeaders['video'] = 'video-log-page-text';
$wgLogActions['video/video'] = 'video-log-entry';
