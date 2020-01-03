<?php
/**
 * @todo document
 */
use MediaWiki\MediaWikiServices;

class VideoHistoryList {
	function beginVideoHistoryList() {
		$s = "\n" .
			Xml::element( 'h2', [ 'id' => 'filehistory' ], wfMessage( 'video-history' )->plain() ) .
			"\n<p>" . wfMessage( 'video-histlegend' )->parse() . "</p>\n" . '<ul class="special">';
		return $s;
	}

	function endVideoHistoryList() {
		$s = "</ul>\n";
		return $s;
	}

	function videoHistoryLine( $isCur, $timestamp, $video, $actor_id, $url, $type, $title ) {
		global $wgUser, $wgLang;

		$services = MediaWikiServices::getInstance();
		$datetime = $wgLang->timeanddate( $timestamp, true );
		$cur = wfMessage( 'cur' )->plain();

		if ( $isCur ) {
			$rlink = $cur;
		} else {
			if (
				!$wgUser->isAnon() &&
				$services->getPermissionManager()->userCan( 'edit', $wgUser )
			) {
				$rlink = $services->getLinkRenderer()->makeKnownLink(
					$title,
					wfMessage( 'video-revert' )->plain(),
					[],
					[ 'action' => 'revert', 'oldvideo' => $video ]
				);
			} else {
				# Having live active links for non-logged in users
				# means that bots and spiders crawling our site can
				# inadvertently change content. Baaaad idea.
				$rlink = wfMessage( 'video-revert' )->plain();
			}
		}

		$actor = User::newFromActorId( $actor_id );
		$user_id = $actor->getId();
		$user_name = $actor->getName();
		$userlink = Linker::userLink( $user_id, $user_name ) .
			Linker::userToolLinks( $user_id, $user_name );

		$style = htmlspecialchars( strtr( urldecode( $url ), '_', ' ') );

		$s = "<li>({$rlink}) <a href=\"{$url}\" title=\"{$style}\">{$datetime}</a> . . ({$type}) . . {$userlink}";

		$s .= Linker::commentBlock( /*$description*/'', $title );
		$s .= "</li>\n";
		return $s;
	}

}
