<?php

class Video {

	/**
	 * @var String: database key of the video
	 */
	public $name;

	/**
	 * @var Title: Title object associated with the current Video
	 */
	public $title;

	/**
	 * @var Boolean: does this video exist? True = exists, false = doesn't.
	 */
	public $exists;

	/**
	 * @var Integer: height of the video, is set to 400 in this class'
	 *               constructor
	 */
	public $height;

	/**
	 * @var Integer: width of the video, is set to 400 in this class'
	 *               constructor
	 */
	public $width;

	/**
	 * @var Integer: is set to 1 in this class' constructor. Provider classes
	 *               should calculate this by diving width by height.
	 */
	public $ratio;

	/**
	 * @var String: URL to the video on the provider service
	 */
	public $url;

	/**
	 * @var String: username of the person who added the current video to the
	 *              wiki
	 */
	public $submitter_user_name;

	/**
	 * @var Integer: user ID number of the person who added the current video
	 *               to the wiki
	 */
	public $submitter_user_id;

	/**
	 * @var Integer: timestamp when this video was added to the wiki
	 */
	public $create_date;

	/**
	 * @var String: lowercase/internal name of the video provider service, such
	 *              as 'youtube' or 'archiveorg'
	 */
	public $type;

	/**
	 * @var Boolean: has all the metadata been loaded into the cache?
	 */
	public $dataLoaded;

	/**
	 * @var Integer: history pointer, see nextHistoryLine() for details
	 */
	public $historyLine;

	/**
	 * @var IContextSource
	 */
	protected $context;

	/**
	 * @var \Wikimedia\Rdbms\IResultWrapper
	 */
	private $historyRes;

	/**
	 * Array of providers codes to classes
	 *
	 * @var array
	 */
	static $providers = [
		'bliptv' => 'BlipTVVideoProvider',
		'dailymotion' => 'DailyMotionVideoProvider',
		'gametrailers' => 'GametrailersVideoProvider',
		'hulu' => 'HuluVideoProvider',
		'metacafe' => 'MetaCafeVideoProvider',
		'movieclips' => 'MovieClipsVideoProvider',
		'myvideo' => 'MyVideoVideoProvider',
		'southparkstudios' => 'SouthParkStudiosVideoProvider',
		'youtube' => 'YouTubeVideoProvider',
		'viddler' => 'ViddlerVideoProvider',
		'vimeo' => 'VimeoVideoProvider',
		'wegame' => 'WeGameVideoProvider',
	];

	/**
	 * Array of domain name to provider codes
	 *
	 * @var null
	 */
	static protected $providerDomains = null;

	/**
	 * Constructor -- create a new Video object from the given Title and set
	 * some member variables
	 *
	 * @param Title $title Title object associated with the Video
	 * @param IContextSource $context Nearest context object
	 */
	public function __construct( $title, IContextSource $context ) {
		if ( !is_object( $title ) ) {
			throw new MWException( 'Video constructor given bogus title.' );
		}
		$this->title =& $title;
		$this->name = $title->getDBkey();
		$this->context = $context;
		$this->height = 400;
		$this->width = 400;
		$this->ratio = 1;
		$this->dataLoaded = false;
	}

	/**
	 * Create a Video object from a video name
	 *
	 * @param mixed $name Name of the video, used to create a title object using Title::makeTitleSafe
	 * @param IContextSource $context Nearest context object
	 * @return Video|null A Video object on success, null if the title is invalid
	 */
	public static function newFromName( $name, IContextSource $context ) {
		$title = Title::makeTitleSafe( NS_VIDEO, $name );
		if ( is_object( $title ) ) {
			return new Video( $title, $context );
		} else {
			return null;
		}
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
		$dbw = wfGetDB( DB_MASTER );

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
				'video_user_id' => $user->getId(),
				'video_user_name' => $user->getName(),
				'video_timestamp' => $now
			],
			__METHOD__,
			'IGNORE'
		);

		$categoryWikiText = '';

		if ( $dbw->affectedRows() === 0 ) {
			$logAction = 'update';

			// Clear cache
			global $wgMemc;
			$key = $this->getCacheKey();
			$wgMemc->delete( $key );

			// Collision, this is an update of a video
			// Insert previous contents into oldvideo
			$dbw->insertSelect(
				'oldvideo', 'video',
				[
					'ov_name' => 'video_name',
					'ov_archive_name' => $dbw->addQuotes( gmdate( 'YmdHis' ) . "!{$this->getName()}" ),
					'ov_url' => 'video_url',
					'ov_type' => 'video_type',
					'ov_user_id' => 'video_user_id',
					'ov_user_name' => 'video_user_name',
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
					'video_user_id' => $user->getId(),
					'video_user_name' => $user->getName(),
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
		$page = WikiPage::factory( $descTitle );
		$watch = $watch || $user->isWatched( $descTitle );

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
					$catName = $this->context->getConfig()->get( 'ContLang' )->getNsText( NS_CATEGORY );
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
			$descTitle->purgeSquid();
		} else {
			// New video; create the description page.
			// Supress the recent changes bc it will appear in the log/video
			$page->doEditContent(
				ContentHandler::makeContent( $categoryWikiText, $page->getTitle() ),
				'',
				EDIT_SUPPRESS_RC
			);
		}

		if ( $watch ) {
			$user->addWatch( $descTitle );
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
		global $wgMemc;

		$this->dataLoaded = false;

		$key = $this->getCacheKey();
		$data = $wgMemc->get( $key );

		if ( !empty( $data ) && is_array( $data ) ) {
			$this->url = $data['url'];
			$this->type = $data['type'];
			$this->submitter_user_id = $data['user_id'];
			$this->submitter_user_name = $data['user_name'];
			$this->create_date = $data['create_date'];
			$this->dataLoaded = true;
			$this->exists = true;
		}

		if ( $this->dataLoaded ) {
			wfDebug( "Loaded Video:{$this->name} from cache\n" );
			wfIncrStats( 'video_cache_hit' );
		} else {
			wfIncrStats( 'video_cache_miss' );
		}

		return $this->dataLoaded;
	}

	/**
	 * Save the video data to memcached
	 */
	private function saveToCache() {
		global $wgMemc;
		$key = $this->getCacheKey();
		if ( $this->exists() ) {
			$cachedValues = [
				'url' => $this->url,
				'type' => $this->type,
				'user_id' => $this->submitter_user_id,
				'user_name' => $this->submitter_user_name,
				'create_date' => $this->create_date
			];
			$wgMemc->set( $key, $cachedValues, 60 * 60 * 24 * 7 ); // A week
		} else {
			// However we should clear them, so they aren't leftover
			// if we've deleted the file.
			$wgMemc->delete( $key );
		}
	}

	/**
	 * Get the memcached key for the current video.
	 */
	public function getCacheKey() {
		global $wgMemc;
		// memcached does not like spaces, so replace 'em with an underscore
		$safeVideoName = str_replace( ' ', '_', $this->getName() );
		return $wgMemc->makeKey( 'video', 'page', $safeVideoName );
	}

	/**
	 * Load video from the database
	 */
	function loadFromDB() {
		$dbr = wfGetDB( DB_MASTER );

		$row = $dbr->selectRow(
			'video',
			[
				'video_url', 'video_type', 'video_user_name', 'video_user_id',
				'video_timestamp'
			],
			[ 'video_name' => $this->name ],
			__METHOD__
		);

		if ( $row ) {
			$this->url = $row->video_url;
			$this->exists = true;
			$this->type = $row->video_type;
			$this->submitter_user_name = $row->video_user_name;
			$this->submitter_user_id = $row->video_user_id;
			$this->create_date = $row->video_timestamp;
		}

		# Unconditionally set loaded=true, we don't want the accessors constantly rechecking
		$this->dataLoaded = true;
	}

	/**
	 * Load video metadata from cache or database, unless it's already loaded.
	 */
	function load() {
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
	public function getName() {
		return $this->name;
	}

	/**
	 * Return the associated Title object
	 */
	public function getTitle() {
		return $this->title;
	}

	/**
	 * Return the URL of this video
	 */
	public function getURL() {
		$this->load();
		return strip_tags( $this->url );
	}

	/**
	 * Return the type of this video
	 */
	public function getType() {
		$this->load();
		return $this->type;
	}

	/**
	 * @return bool True if the Video exists
	 */
	public function exists() {
		$this->load();
		return $this->exists;
	}

	/**
	 * Get the embed code for this Video
	 *
	 * @return string Video embed code
	 */
	public function getEmbedCode() {
		if ( !isset( self::$providers[$this->type] ) ) {
			return '';
		}

		$class = self::$providers[$this->type];
		$provider = new $class( $this );

		return $provider->getEmbedCode();
	}

	/**
	 * Is the supplied value a URL?
	 *
	 * @return bool True if it is, otherwise false
	 */
	public static function isURL( $code ) {
		return preg_match( '%^(?:http|https|ftp)://(?:www\.)?.*$%i', $code ) ? true : false;
	}

	/**
	 * Try to figure out the video's URL from the embed code that the provider
	 * allows users to copy & paste on their own sites.
	 *
	 * @param string $code The video's HTML embedding code
	 * @return string URL of the video
	 */
	public static function getURLfromEmbedCode( $code ) {
		preg_match(
			// iframe for YouTube support. Who uses <embed> these days anyway?
			// It's 2016, get on with the times!
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
			if ( strpos( '?', $flash_vars ) !== false ) {
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
	 *
	 * @return void
	 */
	protected static function getDomainsForProviders() {
		if ( self::$providerDomains !== null ) {
			return;
		}

		self::$providerDomains = [];
		foreach ( self::$providers as $name => $class ) {
			$domains = $class::getDomains();
			foreach ( $domains as $domain ) {
				self::$providerDomains[$domain] = $name;
			}
		}
	}

	/**
	 * Returns if $haystack ends with $needle
	 *
	 * @param string $haystack
	 * @param string $needle
	 * @return bool
	 */
	protected static function endsWith( $haystack, $needle ) {
		return ( substr( $haystack, ( strlen( $needle ) * -1 ) ) === $needle );
	}

	/**
	 * Figure out the provider's name (lowercased) from a given URL.
	 *
	 * @param string $url URL to check
	 * @return string Provider name or 'unknown' if we were unable to figure
	 *                 it out
	 */
	public static function getProviderByURL( $url ) {
		$host = wfParseUrl( $url );
		$host = $host['host'];

		self::getDomainsForProviders();
		foreach ( self::$providerDomains as $domain => $provider ) {
			if ( self::endsWith( $host, $domain ) ) {
				return $provider;
			}
		}

		return 'unknown';
	}

	public function setWidth( $width ) {
		if ( is_numeric( $width ) ) {
			$this->width = $width;
		}
	}

	public function setHeight( $height ) {
		if ( is_numeric( $height ) ) {
			$this->height = $height;
		}
	}

	public function getWidth() {
		return $this->width;
	}

	public function getHeight() {
		return floor( $this->getWidth() / $this->ratio );
		//return $this->height;
	}

	/**
	 * Get the code for embedding the current video on a wiki page.
	 *
	 * @return string Wikitext to insert on a wiki page
	 */
	public function getEmbedThisCode() {
		$videoName = htmlspecialchars( $this->getName(), ENT_QUOTES );
		return "[[Video:{$videoName}|{$this->getWidth()}px]]";
		//return "<video name=\"{$this->getName()}\" width=\"{$this->getWidth()}\" height=\"{$this->getHeight()}\"></video>";
	}

	/**
	 * Return the image history of this video, line by line.
	 * starts with current version, then old versions.
	 * uses $this->historyLine to check which line to return:
	 *  0      return line for current version
	 *  1      query for old versions, return first one
	 *  2, ... return next old version from above query
	 */
	public function nextHistoryLine() {
		$dbr = wfGetDB( DB_REPLICA );

		if ( empty( $this->historyLine ) ) { // called for the first time, return line from cur
			$this->historyRes = $dbr->select(
				'video',
				[
					'video_url',
					'video_type',
					'video_user_id',
					'video_user_name',
					'video_timestamp',
					"'' AS ov_archive_name"
				],
				[ 'video_name' => $this->title->getDBkey() ],
				__METHOD__
			);
			if ( $dbr->numRows( $this->historyRes ) === 0 ) {
				return false;
			}
		} elseif ( $this->historyLine === 1 ) {
			$this->historyRes = $dbr->select(
				'oldvideo',
				[
					'ov_url AS video_url',
					'ov_type AS video_type',
					'ov_user_id AS video_user_id',
					'ov_user_name AS video_user_name',
					'ov_timestamp AS video_timestamp',
					'ov_archive_name'
				],
				[ 'ov_name' => $this->title->getDBkey() ],
				__METHOD__,
				[ 'ORDER BY' => 'ov_timestamp DESC' ]
			);
		}
		$this->historyLine++;

		return $dbr->fetchObject( $this->historyRes );
	}

	/**
	 * Reset the history pointer to the first element of the history
	 */
	public function resetHistory() {
		$this->historyLine = 0;
	}

}
