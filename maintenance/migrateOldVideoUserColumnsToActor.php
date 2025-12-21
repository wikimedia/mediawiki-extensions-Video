<?php
/**
 * @file
 * @ingroup Maintenance
 */
$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

/**
 * Run automatically with update.php
 *
 * @since January 2020
 */
class MigrateOldVideoUserColumnsToActor extends MediaWiki\Maintenance\LoggedUpdateMaintenance {
	public function __construct() {
		parent::__construct();
		$this->addDescription( 'Migrates data from old _user_name/_user_id columns in video and '
			. 'oldvideo tables to the new actor columns.' );
	}

	/**
	 * Get the update key name to go in the update log table
	 *
	 * @return string
	 */
	protected function getUpdateKey() {
		return __CLASS__;
	}

	/**
	 * Message to show that the update was done already and was just skipped
	 *
	 * @return string
	 */
	public function updateSkippedMessage() {
		return 'video and oldvideo tables have already been migrated to use the actor columns.';
	}

	/**
	 * Do the actual work.
	 *
	 * @return bool True to log the update as done
	 */
	protected function doDBUpdates() {
		$dbw = $this->getPrimaryDB();
		$dbw->query(
			"UPDATE {$dbw->tableName( 'video' )}
			SET video_actor=(SELECT actor_id
				FROM {$dbw->tableName( 'actor' )}
				WHERE actor_user=video_user_id
				AND actor_name=video_user_name)",
			__METHOD__
		);
		$dbw->query(
			"UPDATE {$dbw->tableName( 'oldvideo' )}
			SET ov_actor=(SELECT actor_id
				FROM {$dbw->tableName( 'actor' )}
				WHERE actor_user=ov_user_id
				AND actor_name=ov_user_name)",
			__METHOD__
		);
		return true;
	}
}

$maintClass = MigrateOldVideoUserColumnsToActor::class;
require_once RUN_MAINTENANCE_IF_MAIN;
