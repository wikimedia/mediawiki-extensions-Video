<?php

class YouTubeVideoProvider extends BaseVideoProvider {

	// @see http://linuxpanda.wordpress.com/2013/07/24/ultimate-best-regex-pattern-to-get-grab-parse-youtube-video-id-from-any-youtube-link-url/
	protected $videoIdRegex = '~(?:http|https|)(?::\/\/|)(?:www.|)(?:youtu\.be\/|youtube\.com(?:\/embed\/|\/v\/|\/watch\?v=|\/ytscreeningroom\?v=|\/feeds\/api\/videos\/|\/user\S*[^\w\-\s]|\S*[^\w\-\s]))([\w\-]{11})[a-z0-9;:@?&%=+\/\$_.-]*~i';

	protected $embedTemplate = '<iframe width="$width" height="$height" src="https://www.youtube.com/embed/$video_id" frameborder="0" allowfullscreen></iframe>';

	protected function getRatio() {
		return 560 / 315;
	}

	/**
	 * Function to extract the video ID
	 *
	 * @param string $url Video URL
	 * @return string Video ID
	 */
	protected function extractVideoId( $url ) {
		$matches = [];

		if ( preg_match( $this->videoIdRegex, $url, $matches ) ) {
			$this->videoId = $matches[1];
		} elseif ( preg_match( '/([0-9A-Za-z_-]+)/', $url, $matches ) ) {
			// @todo FIXME: This doesn't actually work. The regex should match,
			// and matches (according to a few online regex testers) stuff like
			// XPDEqUnNulg (which is a real, valid video ID) but Special:AddVideo
			// still rejects it.
			$this->videoId = $matches[1];
		}

		if ( isset( $this->videoId ) && $this->videoId !== null ) {
			return $this->videoId;
		} else {
			return null;
		}
	}

	public static function getDomains() {
		return [
			'youtu.be',
			'youtube.com',
			// YouTube's "enhanced privacy mode", in which "YouTube won’t
			// store information about visitors on your web page unless they
			// play the video"
			// @see https://support.google.com/youtube/answer/171780?expand=PrivacyEnhancedMode#privacy
			'youtube-nocookie.com'
		];
	}
}