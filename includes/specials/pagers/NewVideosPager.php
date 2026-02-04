<?php

/**
 * @ingroup Pager
 */

use MediaWiki\Html\FormOptions;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Permissions\GroupPermissionsLookup;
use MediaWiki\RecentChanges\RecentChange;
use MediaWiki\Title\Title;
use MediaWiki\User\UserFactory;
use Wikimedia\Rdbms\IConnectionProvider;

class NewVideosPager extends RangeChronologicalPager {

	/**
	 * @var VideoGallery
	 */
	protected $gallery;

	public function __construct(
		private readonly IConnectionProvider $connectionProvider,
		private readonly GroupPermissionsLookup $groupPermissionsLookup,
		private readonly LinkRenderer $linkRenderer,
		private readonly UserFactory $userFactory,
		IContextSource $context,
		protected readonly FormOptions $opts,
	) {
		parent::__construct( $context );

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
		$user = $this->userFactory->newFromActorId( $row->video_actor );

		$title = Title::makeTitle( NS_VIDEO, $name );
		$video = new Video( $title, $this->getContext() );
		$ul = $this->linkRenderer->makeLink(
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

	public function getQueryInfo(): array {
		$opts = $this->opts;
		$conds = $jconds = [];
		$tables = [ 'video' ];
		$fields = [ 'video_name', 'video_url', 'video_actor', 'video_timestamp' ];
		$options = [];

		$user = $opts->getValue( 'user' );
		if ( $user !== '' ) {
			$userObj = $this->userFactory->newFromName( $user );
			if ( $userObj ) {
				$conds['video_actor'] = $userObj->getActorId();
			}
		}

		if ( $opts->getValue( 'hidebots' ) ) {
			$groupsWithBotPermission = $this->groupPermissionsLookup
				->getGroupsWithPermission( 'bot' );

			if ( count( $groupsWithBotPermission ) ) {
				$dbr = $this->connectionProvider->getReplicaDatabase();
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
			$conds['rc_source'] = RecentChange::SRC_LOG;
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
			$dbr = $this->connectionProvider->getReplicaDatabase();
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

	public function getIndexField(): string {
		return 'video_timestamp';
	}

	public function getStartBody(): string {
		if ( !$this->gallery ) {
			$this->gallery = new VideoGallery();
		}

		return '';
	}

	public function getEndBody(): string {
		return $this->gallery->toHTML();
	}

	public function getShownVideosCount(): int {
		return $this->gallery->count();
	}
}
