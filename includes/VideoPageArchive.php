<?php

use MediaWiki\MediaWikiServices;
use Wikimedia\Rdbms\IResultWrapper;

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
	 * @return IResultWrapper
	 */
	public function listFiles() {
		$dbr = MediaWikiServices::getInstance()->getConnectionProvider()->getReplicaDatabase();
		$res = $dbr->select(
			'oldvideo',
			[
				'ov_name', 'ov_archive_name', 'ov_url', 'ov_type',
				'ov_actor', 'ov_timestamp'
			],
			[ 'ov_name' => $this->title->getDBkey() ],
			__METHOD__,
			[ 'ORDER BY' => 'ov_timestamp DESC' ]
		);

		return $res;
	}
}
