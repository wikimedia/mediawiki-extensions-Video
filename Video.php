<?php
/**
 * Wiki Video Namespace
 *
 * @file
 * @ingroup Extensions
 * @version 1.3
 * @author David Pean <david.pean@gmail.com> - original code/ideas
 * @author Jack Phoenix <jack@countervandalism.net>
 * @copyright Copyright © 2007 David Pean, Wikia Inc.
 * @copyright Copyright © 2008-2011 Jack Phoenix
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License 2.0 or later
 * @link http://www.mediawiki.org/wiki/Extension:Video Documentation
 */

// Bail out if we're not inside MediaWiki
if ( !defined( 'MEDIAWIKI' ) ) {
	die( "This is not a valid entry point.\n" );
}

// Extension credits that show up on Special:Version
$wgExtensionCredits['other'][] = array(
	'name' => 'Video',
	'version' => '1.3',
	'author' => array( 'David Pean', 'Jack Phoenix' ),
	'description' => 'Allows new Video namespace for embeddable media on supported sites',
	'url' => 'http://www.mediawiki.org/wiki/Extension:Video',
);

// ResourceLoader support for MediaWiki 1.17+
$wgResourceModules['ext.video'] = array(
	'styles' => 'Video.css',
	'localBasePath' => dirname( __FILE__ ),
	'remoteExtPath' => 'Video'
);

// Global video namespace reference
if( !defined( 'NS_VIDEO' ) ) {
	define( 'NS_VIDEO', 400 );
}

if( !defined( 'NS_VIDEO_TALK' ) ) {
	define( 'NS_VIDEO_TALK', 401 );
}

// Define permissions
$wgAvailableRights[] = 'addvideo';
$wgGroupPermissions['*']['addvideo'] = false;
$wgGroupPermissions['user']['addvideo'] = true;

// Set up i18n and autoload the gazillion different classes we have
$dir = dirname( __FILE__ ) . '/';
$wgExtensionMessagesFiles['Video'] = $dir . 'Video.i18n.php';
$wgExtensionAliasesFiles['Video'] = $dir . 'Video.alias.php';
// Namespace translations
$wgExtensionMessagesFiles['VideoNamespaces'] = $dir . 'Video.namespaces.php';

// Base Video class
$wgAutoloadClasses['Video'] = $dir . 'VideoClass.php';

// ...and the dozen different provider classes
$wgAutoloadClasses['ArchiveOrgVideo'] = $dir . 'providers/ArchiveOrgVideo.php';
$wgAutoloadClasses['BlipTVVideo'] = $dir . 'providers/BlipTVVideo.php';
$wgAutoloadClasses['DailyMotionVideo'] = $dir . 'providers/DailyMotionVideo.php';
$wgAutoloadClasses['FlashVideo'] = $dir . 'providers/FlashVideo.php';
$wgAutoloadClasses['GametrailersVideo'] = $dir . 'providers/GametrailersVideo.php';
$wgAutoloadClasses['GamevideosVideo'] = $dir . 'providers/GamevideosVideo.php';
$wgAutoloadClasses['GoGreenTubeVideo'] = $dir . 'providers/GoGreenTubeVideo.php';
$wgAutoloadClasses['GoogleVideo'] = $dir . 'providers/GoogleVideo.php';
$wgAutoloadClasses['HuluVideo'] = $dir . 'providers/HuluVideo.php';
$wgAutoloadClasses['MetaCafeVideo'] = $dir . 'providers/MetaCafeVideo.php';
$wgAutoloadClasses['MySpaceVideo'] = $dir . 'providers/MySpaceVideo.php';
$wgAutoloadClasses['MovieClipsVideo'] = $dir . 'providers/MovieClipsVideo.php';
$wgAutoloadClasses['MyVideoVideo'] = $dir . 'providers/MyVideoVideo.php';
$wgAutoloadClasses['NewsRoomVideo'] = $dir . 'providers/NewsRoomVideo.php';
$wgAutoloadClasses['SevenloadVideo'] = $dir . 'providers/SevenloadVideo.php';
$wgAutoloadClasses['SouthParkStudiosVideo'] = $dir . 'providers/SouthParkStudiosVideo.php';
$wgAutoloadClasses['ViddlerVideo'] = $dir . 'providers/ViddlerVideo.php';
$wgAutoloadClasses['VimeoVideo'] = $dir . 'providers/VimeoVideo.php';
$wgAutoloadClasses['WeGameVideo'] = $dir . 'providers/WeGameVideo.php';
$wgAutoloadClasses['YouTubeVideo'] = $dir . 'providers/YouTubeVideo.php';

// User Interface stuff
$wgAutoloadClasses['VideoPage'] = $dir . 'VideoPage.php';
$wgAutoloadClasses['WikiVideoPage'] = $dir . 'WikiVideoPage.php';
$wgAutoloadClasses['RevertVideoAction'] = $dir . 'RevertVideoAction.php';
$wgAutoloadClasses['VideoHistoryList'] = $dir . 'VideoPage.php';
$wgAutoloadClasses['CategoryWithVideoViewer'] = $dir . 'VideoPage.php';

$wgAutoloadClasses['VideoGallery'] = $dir . 'VideoGallery.php';

// Class for undeleting previously deleted videos
$wgAutoloadClasses['VideoPageArchive'] = $dir . 'VideoPageArchive.php';

// New special pages
$wgAutoloadClasses['AddVideo'] = $dir . 'SpecialAddVideo.php';
$wgAutoloadClasses['NewVideos'] = $dir . 'SpecialNewVideos.php';
$wgSpecialPages['AddVideo'] = 'AddVideo';
$wgSpecialPages['NewVideos'] = 'NewVideos';
// Special page groups for MW 1.13+
$wgSpecialPageGroups['AddVideo'] = 'media';
$wgSpecialPageGroups['NewVideos'] = 'changes';

// Hook things up
$wgAutoloadClasses['VideoHooks'] = $dir . 'VideoHooks.php';

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