<?php

/**
 * Special:AddVideo - special page for adding Videos
 *
 * @ingroup extensions
 * @file
 */

use MediaWiki\User\UserOptionsManager;

class AddVideo extends FormSpecialPage {
	/**
	 * New video object created when the title field is validated
	 *
	 * @var Video
	 */
	protected $video;

	private UserOptionsManager $userOptionsManager;

	public function __construct(
		UserOptionsManager $userOptionsManager
	) {
		parent::__construct( 'AddVideo' /*class*/, 'addvideo' /*restriction*/ );
		$this->userOptionsManager = $userOptionsManager;
	}

	public function doesWrites() {
		return true;
	}

	/**
	 * Group this special page under the correct header in Special:SpecialPages.
	 *
	 * @return string
	 */
	public function getGroupName() {
		return 'media';
	}

	/**
	 * Add pre-text to the form
	 * @return string HTML which will be sent to $form->addPreHtml()
	 */
	protected function preHtml() {
		$this->getOutput()->addModuleStyles( 'ext.video' );

		return '';
	}

	/**
	 * Play with the HTMLForm if you need to more substantially
	 * @param HTMLForm $form
	 */
	protected function alterForm( HTMLForm $form ) {
		$form->setPreHtml( $this->msg( 'video-addvideo-instructions' )->parse() );
		$form->setWrapperLegend( $this->msg( 'video-addvideo-title' )->plain() );
		$form->setSubmitText( $this->msg( 'video-addvideo-button' )->plain() );

		if ( $this->getRequest()->getCheck( 'forReUpload' ) ) {
			$form->addHiddenField( 'forReUpload', true );
		}
	}

	/**
	 * Get display format for the form.
	 *
	 * @return string
	 */
	protected function getDisplayFormat() {
		return 'ooui';
	}

	/**
	 * Extracts the URL and provider type from a raw string
	 *
	 * @param string $value Value from the Video input
	 * @return array Element 0 is the URL, 1 is the provider
	 */
	protected function getUrlAndProvider( $value ) {
		$url = $value;
		if ( !Video::isURL( $url ) ) {
			$url = Video::getURLfromEmbedCode( $value );
		}

		return [ $url, Video::getProviderByURL( $url ) ];
	}

	/**
	 * Custom validator for the Video field
	 *
	 * Checks to see if the string given is a valid URL and corresponds
	 * to a supported provider.
	 *
	 * @param string $value
	 * @param array $allData
	 * @return bool|string
	 */
	public function validateVideoField( $value, $allData ) {
		[ , $provider ] = $this->getUrlAndProvider( $value );

		if ( $provider === 'unknown' ) {
			return $this->msg( 'video-addvideo-invalidcode' )->plain();
		}

		return true;
	}

	/**
	 * Custom validator for the Title field
	 *
	 * Just checks that it's a valid title name and that it doesn't already
	 * exist (unless it's an overwrite)
	 *
	 * @param string $value User-supplied video name passed to Video::newFromName()
	 * @param array $allData Unused
	 * @return bool|string Error message on failure, bool true on success
	 */
	public function validateTitleField( $value, $allData ) {
		$video = Video::newFromName( $value, $this->getContext() );

		if ( $video === null || !( $video instanceof Video ) ) {
			return $this->msg( 'badtitle' )->plain();
		}

		// TODO: Check to see if this is a new version
		if ( $video->exists() && !$this->getRequest()->getCheck( 'forReUpload' ) ) {
			return $this->msg( 'video-addvideo-exists' )->escaped();
		}

		$this->video = $video;

		return true;
	}

	/**
	 * Actually inserts the Video into the DB if validation passes
	 *
	 * @param array $data
	 * @return bool
	 */
	public function onSubmit( array $data ) {
		[ $url, $provider ] = $this->getUrlAndProvider( $data['Video'] );

		$this->video->addVideo( $url, $provider, '', $data['Watch'] );

		$this->getOutput()->redirect( $this->video->getTitle()->getFullURL() );

		return true;
	}

	/**
	 * Fields for HTMLForm
	 *
	 * @return array
	 */
	protected function getFormFields() {
		return [
			'Title' => [
				'type' => 'text',
				'label-message' => 'video-addvideo-title-label',
				'size' => '30',
				'required' => true,
				'validation-callback' => [ $this, 'validateTitleField' ],
			],
			'Video' => [
				'type' => 'textarea',
				'label-message' => 'video-addvideo-embed-label',
				'rows' => '5',
				'required' => true,
				'validation-callback' => [ $this, 'validateVideoField' ],
			],
			'Watch' => [
				'type' => 'check',
				'label-message' => 'video-addvideo-watchlist',
				'default' => $this->userOptionsManager
					->getOption( $this->getUser(), 'watchdefault' ),
			],
		];
	}
}
