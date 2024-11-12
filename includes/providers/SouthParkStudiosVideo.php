<?php

class SouthParkStudiosVideoProvider extends MTVNetworksVideoProvider {

	public static function getDomains() {
		return [ 'southparkstudios.com' ];
	}

	protected function extractVideoId( $url ) {
		if ( !preg_match( '#/clips/(\d+)/#', $url, $matches ) ) {
			return null;
		}

		return "mgid:cms:item:southparkstudios.com:{$matches[1]}";
	}
}
