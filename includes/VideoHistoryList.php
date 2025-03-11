<?php

use MediaWiki\Linker\Linker;
use MediaWiki\MediaWikiServices;

class VideoHistoryList {
	public function beginVideoHistoryList() {
		$s = "\n" .
			Xml::element( 'h2', [ 'id' => 'filehistory' ], wfMessage( 'video-history' )->plain() ) .
			"\n<p>" . wfMessage( 'video-histlegend' )->parse() . "</p>\n" . '<ul class="special">';
		return $s;
	}

	public function endVideoHistoryList() {
		$s = "</ul>\n";
		return $s;
	}

	public function videoHistoryLine( $isCur, $timestamp, $video, $actor_id, $url, $type, $title ) {
		$services = MediaWikiServices::getInstance();
		$context = RequestContext::getMain();
		$lang = $context->getLanguage();
		$user = $context->getUser();
		$datetime = htmlspecialchars( $lang->timeanddate( $timestamp, true ), ENT_QUOTES );
		$cur = wfMessage( 'cur' )->escaped();

		if ( $isCur ) {
			$rlink = $cur;
		} else {
			if ( $user->isRegistered() &&
				$services->getPermissionManager()->userCan( 'edit', $user, $title )
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
		$type = htmlspecialchars( $type );
		$url = htmlspecialchars( $url );

		$s = "<li>({$rlink}) <a href=\"{$url}\" title=\"{$style}\">{$datetime}</a> . . ({$type}) . . {$userlink}";
		$s .= $services->getCommentFormatter()->formatBlock( /*$description*/'', $title );
		$s .= "</li>\n";

		return $s;
	}

}
