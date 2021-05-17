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
		// phpcs:ignore MediaWiki.Usage.DeprecatedGlobalVariables.Deprecated$wgUser
		global $wgUser, $wgLang;

		$services = MediaWikiServices::getInstance();
		$datetime = htmlspecialchars( $wgLang->timeanddate( $timestamp, true ), ENT_QUOTES );
		$cur = wfMessage( 'cur' )->escaped();

		if ( $isCur ) {
			$rlink = $cur;
		} else {
			if (
				!$wgUser->isAnon() &&
				$services->getPermissionManager()->userCan( 'edit', $wgUser, $title )
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
				$rlink = wfMessage( 'video-revert' )->escaped();
			}
		}

		$actor = User::newFromActorId( $actor_id );
		$user_id = $actor->getId();
		$user_name = $actor->getName();
		$userlink = Linker::userLink( $user_id, $user_name ) .
			Linker::userToolLinks( $user_id, $user_name );

		$style = htmlspecialchars( strtr( urldecode( $url ), '_', ' ' ) );

		$s = "<li>({$rlink}) <a href=\"{$url}\" title=\"{$style}\">{$datetime}</a> . . ({$type}) . . {$userlink}";

		$s .= Linker::commentBlock( /*$description*/'', $title );
		$s .= "</li>\n";
		return $s;
	}

}
