<?php

/**
 * @ingroup Pager
 */

use MediaWiki\Html\FormOptions;
use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;

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
	public function __construct( IContextSource $context, FormOptions $opts ) {
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

	/**
	 * @param stdClass|array $row Row from the video table
	 * @return string
	 */
	public function formatRow( $row ) {
		$name = $row->video_name;
		$user = User::newFromActorId( $row->video_actor );

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
		return '';
	}

	public function getQueryInfo() {
		$opts = $this->opts;
		$conds = $jconds = [];
		$tables = [ 'video' ];
		$fields = [ 'video_name', 'video_url', 'video_actor', 'video_timestamp' ];
		$options = [];

		$user = $opts->getValue( 'user' );
		if ( $user !== '' ) {
			$userObj = User::newFromName( $user );
			if ( $userObj ) {
				$conds['video_actor'] = $userObj->getActorId();
			}
		}

		if ( $opts->getValue( 'hidebots' ) ) {
			$groupsWithBotPermission = MediaWikiServices::getInstance()->getGroupPermissionsLookup()
				->getGroupsWithPermission( 'bot' );

			if ( count( $groupsWithBotPermission ) ) {
				$dbr = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_REPLICA );
				$tables[] = 'user_groups';
				$tables[] = 'actor';
				$fields[] = 'actor_id';
				$fields[] = 'actor_user';
				$conds[] = 'ug_group IS NULL';
				$jconds['user_groups'] = [
					'LEFT JOIN',
					[
						'ug_group' => $groupsWithBotPermission,
						// commented out because causes a query error, not sure *why* though...
						// 'ug_user = actor_user',
						'ug_expiry IS NULL OR ug_expiry >= ' . $dbr->addQuotes( $dbr->timestamp() )
					]
				];
				$jconds['actor'] = [
					'JOIN',
					[
						'actor_id = video_actor'
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
					'rc_actor = video_actor',
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
			$dbr = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_REPLICA );
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

	public function getIndexField() {
		return 'video_timestamp';
	}

	public function getStartBody() {
		if ( !$this->gallery ) {
			$this->gallery = new VideoGallery();
		}

		return '';
	}

	public function getEndBody() {
		return $this->gallery->toHTML();
	}

	public function getShownVideosCount() {
		return $this->gallery->count();
	}
}
