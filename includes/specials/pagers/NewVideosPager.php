<?php
/**
 * @ingroup Pager
 */
use MediaWiki\MediaWikiServices;

class NewVideosPager extends RangeChronologicalPager {

	/**
	 * @var VideoGallery
	 */
	protected $gallery;

	/**
	 * @var FormOptions
	 */
	protected $opts;

	/**
	 * @param IContextSource $context
	 * @param FormOptions $opts
	 */
	function __construct( IContextSource $context, FormOptions $opts ) {
		parent::__construct( $context );

		$this->opts = $opts;
		$this->setLimit( $opts->getValue( 'limit' ) );

		$startTimestamp = '';
		$endTimestamp = '';
		if ( $opts->getValue( 'start' ) ) {
			$startTimestamp = $opts->getValue( 'start' ) . ' 00:00:00';
		}
		if ( $opts->getValue( 'end' ) ) {
			$endTimestamp = $opts->getValue( 'end' ) . ' 23:59:59';
		}
		$this->getDateRangeCond( $startTimestamp, $endTimestamp );
	}

	function formatRow( $row ) {
		$name = $row->video_name;
		$user = User::newFromId( $row->video_user_id );

		$title = Title::makeTitle( NS_VIDEO, $name );
		$video = new Video( $title, $this->getContext() );
		$ul = MediaWikiServices::getInstance()->getLinkRenderer()->makeLink(
			$user->getUserPage(),
			$user->getName()
		);
		$time = $this->getLanguage()->userTimeAndDate( $row->video_timestamp, $this->getUser() );

		$this->gallery->add(
			$video,
			"$ul<br />\n<i>"
			. htmlspecialchars( $time )
			. "</i><br />\n"
		);
	}

	function getQueryInfo() {
		$opts = $this->opts;
		$conds = $jconds = [];
		$tables = [ 'video' ];
		$fields = [ 'video_name', 'video_url', 'video_user_name', 'video_user_id', 'video_timestamp' ];
		$options = [];

		$user = $opts->getValue( 'user' );
		if ( $user !== '' ) {
			$userId = User::idFromName( $user );
			if ( $userId ) {
				$conds['video_user_id'] = $userId;
			} else {
				$conds['video_user_name'] = $user;
			}
		}

		if ( $opts->getValue( 'newbies' ) ) {
			// newbie = most recent 1% of users
			$dbr = wfGetDB( DB_REPLICA );
			$max = $dbr->selectField( 'user', 'max(user_id)', false, __METHOD__ );
			$conds[] = 'video_user_id >' . (int)( $max - $max / 100 );

			// there's no point in looking for new user activity in a far past;
			// beyond a certain point, we'd just end up scanning the rest of the
			// table even though the users we're looking for didn't yet exist...
			// see T140537, (for ContribsPages, but similar to this)
			$conds[] = 'video_timestamp > ' .
			           $dbr->addQuotes( $dbr->timestamp( wfTimestamp() - 30 * 24 * 60 * 60 ) );
		}

		if ( $opts->getValue( 'hidebots' ) ) {
			$groupsWithBotPermission = User::getGroupsWithPermission( 'bot' );

			if ( count( $groupsWithBotPermission ) ) {
				$dbr = wfGetDB( DB_REPLICA );
				$tables[] = 'user_groups';
				$conds[] = 'ug_group IS NULL';
				$jconds['user_groups'] = [
					'LEFT JOIN',
					[
						'ug_group' => $groupsWithBotPermission,
						'ug_user = video_user_id',
						'ug_expiry IS NULL OR ug_expiry >= ' . $dbr->addQuotes( $dbr->timestamp() )
					]
				];
			}
		}

		if ( $opts->getValue( 'hidepatrolled' ) ) {
			$tables[] = 'recentchanges';
			$conds['rc_type'] = RC_LOG;
			$conds['rc_log_type'] = 'upload';
			$conds['rc_patrolled'] = 0;
			$conds['rc_namespace'] = NS_FILE;
			$jconds['recentchanges'] = [
				'INNER JOIN',
				[
					'rc_title = video_name',
					'rc_user = video_user_id',
					'rc_timestamp = video_timestamp'
				]
			];
			// We're ordering by video_timestamp, so we have to make sure MariaDB queries `video` first.
			// It sometimes decides to query `recentchanges` first and filesort the result set later
			// to get the right ordering. T124205 / https://mariadb.atlassian.net/browse/MDEV-8880
			$options[] = 'STRAIGHT_JOIN';
		}

		$likeVal = $opts->getValue( 'wpIlMatch' );
		if ( $likeVal !== '' && !$this->getConfig()->get( 'MiserMode' ) ) {
			$dbr = wfGetDB( DB_REPLICA );
			$likeObj = Title::newFromText( $likeVal );
			if ( $likeObj instanceof Title ) {
				$like = $dbr->buildLike(
					$dbr->anyString(),
					strtolower( $likeObj->getDBkey() ),
					$dbr->anyString()
				);
				// LOWER() & friends don't work as-is on varbinary fields
				// @see https://phabricator.wikimedia.org/T157197
				$conds[] = "LOWER(CONVERT(video_name USING utf8)) $like";
			}
		}

		$query = [
			'tables' => $tables,
			'fields' => $fields,
			'join_conds' => $jconds,
			'conds' => $conds,
			'options' => $options,
		];

		return $query;
	}

	function getIndexField() {
		return 'video_timestamp';
	}

	function getStartBody() {
		if ( !$this->gallery ) {
			$this->gallery = new VideoGallery();
		}

		return '';
	}

	function getEndBody() {
		return $this->gallery->toHTML();
	}

	function getShownVideosCount() {
		return $this->gallery->count();
	}
}
