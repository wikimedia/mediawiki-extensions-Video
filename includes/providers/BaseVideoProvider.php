<?php

abstract class BaseVideoProvider {

	/**
	 * Video ID
	 *
	 * @var string
	 */
	protected $videoId = null;

	/**
	 * Regular expression used to extract the video ID
	 *
	 * @var string|null
	 */
	protected $videoIdRegex = null;

	/**
	 * Template for embedding
	 *
	 * @var string|null
	 */
	protected $embedTemplate = null;

	public function __construct(
		protected readonly Video $video,
	) {
		// TODO: This sucks; fix it
		$this->video->ratio = $this->getRatio();

		$matches = [];
		if ( $this->videoIdRegex !== null && preg_match( $this->videoIdRegex, $this->video->getURL(), $matches ) ) {
			$this->videoId = $matches[1];
		} else {
			$this->videoId = $this->extractVideoId( $this->video->getURL() );
		}

		if ( $this->videoId === null ) {
			throw new MWException( 'Could not determine video ID!' );
		}
	}

	/**
	 * Function to extract the video ID
	 *
	 * Override to use instead of regular expression
	 *
	 * @param string $url
	 * @return string|null
	 */
	protected function extractVideoId( $url ) {
		return null;
	}

	/**
	 * Returns the raw HTML to embed the video
	 *
	 * @return string
	 */
	public function getEmbedCode() {
		if ( $this->embedTemplate === null ) {
			return '';
		}

		return str_replace( [
				'$video_id',
				'$height',
				'$width',
			], [
				$this->videoId,
				$this->video->getHeight(),
				$this->video->getWidth(),
			], $this->embedTemplate );
	}

	/**
	 * Returns the (aspect?) ratio for the video
	 *
	 * @return float
	 */
	protected function getRatio() {
		return 1;
	}

	/**
	 * Returns all domains associated with the provider
	 *
	 * @return string[]
	 */
	public static function getDomains() {
		return [];
	}

}
