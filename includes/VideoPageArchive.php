<?php
/**
 * A subclass of PageArchive for restoring deleted videos.
 * Based on Bartek Łapiński's code.
 *
 * @file
 */

class VideoPageArchive extends PageArchive {

	/**
	 * List the deleted file revisions for this video page.
	 * Returns a result wrapper with various oldvideo fields.
	 *
	 * @return \Wikimedia\Rdbms\IResultWrapper
	 */
	function listFiles() {
		$dbr = wfGetDB( DB_REPLICA );
		$res = $dbr->select(
			'oldvideo',
			[
				'ov_name', 'ov_archive_name', 'ov_url', 'ov_type',
				'ov_user_id', 'ov_user_name', 'ov_timestamp'
			],
			[ 'ov_name' => $this->title->getDBkey() ],
			__METHOD__,
			[ 'ORDER BY' => 'ov_timestamp DESC' ]
		);
		return $res;
	}

	/**
	 * Restore the given (or all) text and video revisions for the page.
	 * Once restored, the items will be removed from the archive tables.
	 * The deletion log will be updated with an undeletion notice.
	 *
	 * @param array $timestamps Pass an empty array to restore all revisions,
	 *   otherwise list the ones to undelete.
	 * @param string $comment
	 * @param array $fileVersions
	 * @param bool $unsuppress
	 * @param User $user User performing the action, or null to use $wgUser
	 * @param string|string[] $tags Change tags to add to log entry
	 *   ($user should be able to add the specified tags before this is called)
	 * @return array|bool array(number of file revisions restored, number of image revisions
	 *   restored, log message) on success, false on failure.
	 */
	function undelete( $timestamps, $comment = '', $fileVersions = [],
		$unsuppress = false, User $user = null, $tags = null
	) {
		// We currently restore only whole deleted videos, a restore link from
		// log could take us here...
		if ( $this->title->exists() ) {
			return;
		}

		$dbw = wfGetDB( DB_MASTER );

		$result = $dbw->select(
			'oldvideo',
			'*',
			[ 'ov_name' => $this->title->getDBkey() ],
			__METHOD__,
			[ 'ORDER BY' => 'ov_timestamp DESC' ]
		);

		$insertBatch = [];
		$insertCurrent = false;
		$archiveName = '';
		$first = true;

		foreach ( $result as $row ) {
			if ( $first ) { // this is our new current revision
				$insertCurrent = [
					'video_name' => $row->ov_name,
					'video_url' => $row->ov_url,
					'video_type' => $row->ov_type,
					'video_user_id' => $row->ov_user_id,
					'video_user_name' => $row->ov_user_name,
					'video_timestamp' => $row->ov_timestamp
				];
			} else { // older revisions, they could be even elder current ones from ancient deletions
				$insertBatch = [
					'ov_name' => $row->ov_name,
					'ov_archive_name' => $archiveName,
					'ov_url' => $row->ov_url,
					'ov_type' => $row->ov_type,
					'ov_user_id' => $row->ov_user_id,
					'ov_user_name' => $row->ov_user_name,
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
			$dbw->delete( 'oldvideo', [ 'ov_name' => $this->title->getDBkey() ], __METHOD__ );
		}
		if ( $insertBatch ) {
			$dbw->insert( 'oldvideo', $insertBatch, __METHOD__ );
		}

		// run parent version, because it uses a private function inside
		// files will not be touched anyway here, because it's not NS_FILE
		parent::undelete( $timestamps, $comment, $fileVersions, $unsuppress );

		return [ '', '', '' ];
	}
}
