<?php

use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;
use Wikimedia\Rdbms\IResultWrapper;

class Video {

	/**
	 * @var string database key of the video
	 */
	public string $name;

	/**
	 * @var Title Title object associated with the current Video
	 */
	public Title $title;

	/**
	 * @var bool does this video exist? True = exists, false = doesn't.
	 */
	public bool $exists = false;

	/**
	 * @var int height of the video, is set to 400 in this class'
	 *               constructor
	 */
	public $height;

	/**
	 * @var int width of the video, is set to 400 in this class'
	 *               constructor
	 */
	public $width;

	/**
	 * @var float is set to 1 in this class' constructor. Provider classes
	 *               should calculate this by diving width by height.
	 */
	public $ratio;

	/**
	 * @var string URL to the video on the provider service
	 */
	public $url;

	/**
	 * @var int actor ID number of the person who added the current video
	 *               to the wiki
	 */
	public $submitter_actor;

	/**
	 * @var int timestamp when this video was added to the wiki
	 */
	public $create_date;

	/**
	 * @var string lowercase/internal name of the video provider service, such
	 *              as 'youtube' or 'archiveorg'
	 */
	public $type;

	/**
	 * @var bool has all the metadata been loaded into the cache?
	 */
	public bool $dataLoaded = false;

	/**
	 * @var int history pointer, see nextHistoryLine() for details
	 */
	public $historyLine;

	protected IContextSource $context;

	/**
	 * @var IResultWrapper
	 */
	private $historyRes;

	/**
	 * Array of providers codes to classes
	 *
	 * @var array<string,class-string<BaseVideoProvider>>
	 */
	private static array $providers = [
		'bliptv' => BlipTVVideoProvider::class,
		'dailymotion' => DailyMotionVideoProvider::class,
		'hulu' => HuluVideoProvider::class,
		'movieclips' => MovieClipsVideoProvider::class,
		'myvideo' => MyVideoVideoProvider::class,
		'southparkstudios' => SouthParkStudiosVideoProvider::class,
		'youtube' => YouTubeVideoProvider::class,
		'viddler' => ViddlerVideoProvider::class,
		'vimeo' => VimeoVideoProvider::class,
	];

	/**
	 * Array of domain name to provider codes
	 *
	 * @var string[]|null
	 */
	protected static $providerDomains = null;

	/**
	 * Constructor -- create a new Video object from the given Title and set
	 * some member variables
	 *
	 * @param Title $title Title object associated with the Video
	 * @param IContextSource $context Nearest context object
	 */
	public function __construct( Title $title, IContextSource $context ) {
		$this->title =& $title;
		$this->name = $title->getDBkey();
		$this->context = $context;
		$this->height = 400;
		$this->width = 400;
		$this->ratio = 1;
	}

	/**
	 * Create a Video object from a video name
	 *
	 * @param mixed $name Name of the video, used to create a title object using Title::makeTitleSafe
	 * @param IContextSource $context Nearest context object
	 * @return Video|null A Video object on success, null if the title is invalid
	 */
	public static function newFromName( $name, IContextSource $context ): ?self {
		$title = Title::makeTitleSafe( NS_VIDEO, $name );
		return $title ? new Video( $title, $context ) : null;
	}

	/**
	 * Add the video into the database
	 *
	 * @param string $url URL to the video on the provider service
	 * @param string $type (internal) provider name in lowercase
	 * @param string $categories Pipe-separated list of categories
	 * @param bool $watch Add the new video page to the user's watchlist?
	 */
	public function addVideo( $url, $type, $categories, $watch = false ) {
		$user = $this->context->getUser();
		$services = MediaWikiServices::getInstance();
		$dbw = $services->getConnectionProvider()->getPrimaryDatabase();

		$now = $dbw->timestamp();

		$logAction = 'add';

		// Test to see if the row exists using INSERT IGNORE
		// This avoids race conditions by locking the row until the commit, and also
		// doesn't deadlock. SELECT FOR UPDATE causes a deadlock for every race condition.
		$dbw->insert(
			'video',
			[
				'video_name' => $this->getName(),
				'video_url' => $url,
				'video_type' => $type,
				'video_actor' => $user->getActorId(),
				'video_timestamp' => $now
			],
			__METHOD__,
			'IGNORE'
		);

		$categoryWikiText = '';

		if ( $dbw->affectedRows() === 0 ) {
			$logAction = 'update';

			// Clear the cache
			$this->clearCache();

			// Collision, this is an update of a video
			// Insert previous contents into oldvideo
			$dbw->insertSelect(
				'oldvideo',
				'video',
				[
					'ov_name' => 'video_name',
					'ov_archive_name' => $dbw->addQuotes( gmdate( 'YmdHis' ) . '!' . $this->getName() ),
					'ov_url' => 'video_url',
					'ov_type' => 'video_type',
					'ov_actor' => 'video_actor',
					'ov_timestamp' => 'video_timestamp'
				],
				[ 'video_name' => $this->getName() ],
				__METHOD__
			);

			// Update the current video row
			$dbw->update(
				'video',
				[
					/* SET */
					'video_url' => $url,
					'video_type' => $type,
					'video_actor' => $user->getActorId(),
					'video_timestamp' => $now
				],
				[
					/* WHERE */
					'video_name' => $this->getName()
				],
				__METHOD__
			);
		}

		$descTitle = $this->getTitle();
		$page = $services->getWikiPageFactory()->newFromTitle( $descTitle );

		$watchlistManager = $services->getWatchlistManager();
		$watch = $watch || $watchlistManager->isWatched( $user, $descTitle );

		// Get the localized category name
		$videoCategoryName = wfMessage( 'video-category-name' )->inContentLanguage()->text();

		if ( $categories ) {
			$categories .= "|$videoCategoryName";
		} else {
			$categories = $videoCategoryName;
		}

		// Loop through category variable and individually build category tag for wiki text
		if ( $categories ) {
			$categories_array = explode( '|', $categories );
			foreach ( $categories_array as $ctg ) {
				$ctg = trim( $ctg );
				if ( $ctg ) {
					$catName = $services->getContentLanguage()->getNsText( NS_CATEGORY );
					$tag = "[[{$catName}:{$ctg}]]";
					if ( strpos( $categoryWikiText, $tag ) === false ) {
						$categoryWikiText .= "\n{$tag}";
					}
				}
			}
		}

		if ( $descTitle->exists() ) {
			# Invalidate the cache for the description page
			$descTitle->invalidateCache();
			$htmlCache = $services->getHtmlCacheUpdater();
			$htmlCache->purgeTitleUrls( $descTitle, $htmlCache::PURGE_INTENT_TXROUND_REFLECTED );
		} else {
			// New video; create the description page.
			// Suppress the recent changes bc it will appear in the log/video
			$page->doUserEditContent(
				ContentHandler::makeContent( $categoryWikiText, $page->getTitle() ),
				$user,
				'',
				EDIT_SUPPRESS_RC
			);
		}

		if ( $watch ) {
			$watchlistManager->addWatch( $user, $descTitle );
		}

		// Add the log entry
		$logEntry = new ManualLogEntry( 'video', $logAction );
		$logEntry->setPerformer( $user );
		$logEntry->setTarget( $descTitle );
		$logId = $logEntry->insert();
		$logEntry->publish( $logId );
	}

	/**
	 * Try to load video metadata from memcached.
	 *
	 * @return bool True on success.
	 */
	private function loadFromCache() {
		$this->dataLoaded = false;

		$key = $this->getCacheKey();
		$services = MediaWikiServices::getInstance();
		$data = $services->getMainWANObjectCache()->get( $key );

		if ( is_array( $data ) && $data ) {
			$this->url = $data['url'];
			$this->type = $data['type'];
			$this->submitter_actor = $data['actor'];
			$this->create_date = $data['create_date'];
			$this->dataLoaded = true;
			$this->exists = true;
		}

		$stats = $services->getStatsdDataFactory();
		if ( $this->dataLoaded ) {
			wfDebug( "Loaded Video:{$this->name} from cache\n" );
			$stats->increment( 'video_cache_hit' );
		} else {
			$stats->increment( 'video_cache_miss' );
		}

		return $this->dataLoaded;
	}

	/**
	 * Save the video data to cache
	 */
	private function saveToCache(): void {
		if ( $this->exists() ) {
			$cachedValues = [
				'url' => $this->url,
				'type' => $this->type,
				'actor' => $this->submitter_actor,
				'create_date' => $this->create_date
			];

			$cache = MediaWikiServices::getInstance()->getMainWANObjectCache();

			// Set cache for one week
			$cache->set( $this->getCacheKey(), $cachedValues, 60 * 60 * 24 * 7 );
		} else {
			// However we should clear them, so they aren't leftover
			// if we've deleted the file.
			$this->clearCache();
		}
	}

	/**
	 * Clear the video data from cache
	 */
	public function clearCache(): void {
		$cache = MediaWikiServices::getInstance()->getMainWANObjectCache();
		$cache->delete( $this->getCacheKey() );
	}

	/**
	 * Get the cache key for the current video.
	 */
	public function getCacheKey(): string {
		// memcached does not like spaces, so replace 'em with an underscore
		$safeVideoName = str_replace( ' ', '_', $this->getName() );
		$cache = MediaWikiServices::getInstance()->getMainWANObjectCache();

		return $cache->makeKey( 'video', 'page', $safeVideoName );
	}

	/**
	 * Load video from the database
	 */
	public function loadFromDB(): void {
		$dbr = MediaWikiServices::getInstance()->getConnectionProvider()->getPrimaryDatabase();

		$row = $dbr->selectRow(
			'video',
			[
				'video_url',
				'video_type',
				'video_actor',
				'video_timestamp'
			],
			[ 'video_name' => $this->name ],
			__METHOD__
		);

		if ( $row ) {
			$this->url = $row->video_url;
			$this->exists = true;
			$this->type = $row->video_type;
			$this->submitter_actor = $row->video_actor;
			$this->create_date = $row->video_timestamp;
		}

		# Unconditionally set loaded=true, we don't want the accessors constantly rechecking
		$this->dataLoaded = true;
	}

	/**
	 * Load video metadata from cache or database, unless it's already loaded.
	 */
	public function load(): void {
		if ( !$this->dataLoaded ) {
			if ( !$this->loadFromCache() ) {
				$this->loadFromDB();
				$this->saveToCache();
			}
			$this->dataLoaded = true;
		}
	}

	/**
	 * Return the name of this video
	 */
	public function getName(): string {
		return $this->name;
	}

	public function getTitle(): Title {
		return $this->title;
	}

	/**
	 * Return the URL of this video
	 */
	public function getURL(): string {
		$this->load();
		return strip_tags( $this->url );
	}

	public function getType(): string {
		$this->load();
		return $this->type;
	}

	public function exists(): bool {
		$this->load();
		return $this->exists;
	}

	/**
	 * Get the embed code for this Video
	 *
	 * @return string Video embed code
	 */
	public function getEmbedCode(): string {
		if ( !isset( self::$providers[$this->type] ) ) {
			return '';
		}

		$class = self::$providers[$this->type];
		/** @var BaseVideoProvider $provider */
		$provider = new $class( $this );

		return $provider->getEmbedCode();
	}

	/**
	 * Is the supplied value a URL?
	 *
	 * @param string $code
	 * @return bool True if it is, otherwise false
	 */
	public static function isURL( string $code ): bool {
		return (bool)preg_match( '%^(?:https?|ftp)://(?:www\.)?.*$%i', $code );
	}

	/**
	 * Try to figure out the video's URL from the embed code that the provider
	 * allows users to copy & paste on their own sites.
	 *
	 * @param string $code The video's HTML embedding code
	 * @return string URL of the video
	 */
	public static function getURLfromEmbedCode( string $code ): string {
		preg_match(
			// iframe for YouTube support. Who uses <embed> these days anyway?
			// It's 2016, get on with the times!
			// phpcs:ignore Generic.Files.LineLength
			"/(embed .*src=(\"([^<\"].*?)\"|\'([^<\"].*?)\'|[^<\"].*?)(.*flashvars=(\"([^<\"].*?)\"|\'([^<\"].*?)\'|[^<\"].*?\s))?|iframe .*src=(\"([^<\"].*?)\"|\'([^<\"].*?)\'|[^<\"].*?))/i",
			$code,
			$matches
		);

		$embedCode = '';
		if ( isset( $matches[2] ) && !empty( $matches[2] ) ) {
			$embedCode = $matches[2];
		} elseif ( isset( $matches[10] ) && !empty( $matches[10] ) ) {
			// New (as of 2016) YouTube <iframe>-based embed code
			// The string YT offers to the user is like this:
			// <iframe width="560" height="315" src="https://www.youtube.com/embed/cBOE1aUNZVo" frameborder="0" allowfullscreen></iframe>
			// $matches[0] and $matches[1] contain a meaningless HTML fragment
			// (iframe and the width, height and src attributes and their values),
			// array indexes from 2 to 8 are empty, 9 contains the URL with
			// "double quotes" and 10 finally contains what we want, i.e. just
			// the plain video URL and nothing else
			$embedCode = $matches[10];
		}

		// Some providers (such as MySpace) have flashvars='' in the embed
		// code, and the base URL in the src='' so we need to grab the
		// flashvars and append it to get the real URL
		if ( isset( $matches[6] ) && !empty( $matches[6] ) ) {
			$flash_vars = $matches[6];
			if ( strpos( $flash_vars, '?' ) !== false ) {
				$embedCode .= '&';
			} else {
				$embedCode .= '?';
			}
			$embedCode .= $flash_vars;
		}

		return $embedCode;
	}

	/**
	 * Populates the $providerDomains variable
	 */
	protected static function getDomainsForProviders(): void {
		if ( self::$providerDomains !== null ) {
			return;
		}

		self::$providerDomains = [];
		foreach ( self::$providers as $name => $class ) {
			/** @var BaseVideoProvider $class */
			$domains = $class::getDomains();
			foreach ( $domains as $domain ) {
				self::$providerDomains[$domain] = $name;
			}
		}
	}

	/**
	 * Figure out the provider's name (lowercased) from a given URL.
	 *
	 * @param string $url URL to check
	 * @return string Provider name or 'unknown' if we were unable to figure
	 *                 it out
	 */
	public static function getProviderByURL( string $url ): string {
		$host = wfParseUrl( $url );
		if ( !$host ) {
			return 'unknown';
		}
		$host = $host['host'];

		self::getDomainsForProviders();
		foreach ( self::$providerDomains as $domain => $provider ) {
			if ( str_ends_with( $host, $domain ) ) {
				return $provider;
			}
		}

		return 'unknown';
	}

	/**
	 * @param int $width
	 */
	public function setWidth( $width ): void {
		if ( is_numeric( $width ) ) {
			$this->width = $width;
		}
	}

	/**
	 * @param int $height
	 */
	public function setHeight( $height ): void {
		if ( is_numeric( $height ) ) {
			$this->height = $height;
		}
	}

	/**
	 * @return int
	 */
	public function getWidth() {
		return $this->width;
	}

	public function getHeight(): int {
		return (int)floor( $this->getWidth() / $this->ratio );
		// return $this->height;
	}

	/**
	 * Get the code for embedding the current video on a wiki page.
	 *
	 * @return string Wikitext to insert on a wiki page
	 */
	public function getEmbedThisCode(): string {
		$videoName = htmlspecialchars( $this->getName(), ENT_QUOTES );
		return "[[Video:{$videoName}|{$this->getWidth()}px]]";
		// return "<video name=\"{$this->getName()}\" width=\"{$this->getWidth()}\" height=\"{$this->getHeight()}\"></video>";
	}

	/**
	 * Return the image history of this video, line by line.
	 * starts with current version, then old versions.
	 * uses $this->historyLine to check which line to return:
	 *  0      return line for current version
	 *  1      query for old versions, return first one
	 *  2, ... return next old version from above query
	 *
	 * @return stdClass|false
	 */
	public function nextHistoryLine() {
		$dbr = MediaWikiServices::getInstance()->getConnectionProvider()->getReplicaDatabase();

		if ( !$this->historyLine ) { // called for the first time, return line from cur
			$this->historyRes = $dbr->select(
				'video',
				[
					'video_url',
					'video_type',
					'video_actor',
					'video_timestamp',
					"'' AS ov_archive_name"
				],
				[ 'video_name' => $this->title->getDBkey() ],
				__METHOD__
			);
			if ( $this->historyRes->numRows() === 0 ) {
				return false;
			}
		} elseif ( $this->historyLine === 1 ) {
			$this->historyRes = $dbr->select(
				'oldvideo',
				[
					'ov_url AS video_url',
					'ov_type AS video_type',
					'ov_actor AS video_actor',
					'ov_timestamp AS video_timestamp',
					'ov_archive_name'
				],
				[ 'ov_name' => $this->title->getDBkey() ],
				__METHOD__,
				[ 'ORDER BY' => 'ov_timestamp DESC' ]
			);
		}
		$this->historyLine++;

		return $this->historyRes->fetchObject();
	}

	/**
	 * Reset the history pointer to the first element of the history
	 */
	public function resetHistory(): void {
		$this->historyLine = 0;
	}

}
