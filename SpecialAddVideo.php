<?php
/**
 * Special:AddVideo - special page for adding Videos
 *
 * @ingroup extensions
 * @file
 */

class AddVideo extends SpecialPage {

	/**
	 * New video object created when the title field is validated
	 *
	 * @var Video
	 */
	protected $video;

	/**
	 * Constructor -- set up the new special page
	 */
	public function __construct() {
		parent::__construct( 'AddVideo' /*class*/, 'addvideo' /*restriction*/);
	}

	/**
	 * Group this special page under the correct header in Special:SpecialPages.
	 *
	 * @return string
	 */
	function getGroupName() {
		return 'media';
	}

	/**
	 * Show the special page
	 *
	 * @param mixed|null $par Parameter passed to the page or null
	 */
	public function execute( $par ) {
		$out = $this->getOutput();

		// If the user doesn't have the required 'addvideo' permission, display an error
		if ( !$this->userCanExecute( $this->getUser() ) ) {
			$this->displayRestrictionError();
			return;
		}

		// Show a message if the database is in read-only mode
		if ( wfReadOnly() ) {
			throw new ReadOnlyError;
		}

		// If user is blocked, s/he doesn't need to access this page
		if ( $this->getUser()->isBlocked() ) {
			throw new UserBlockedError( $this->getUser()->mBlock );
		}

		// Add CSS
		$out->addModuleStyles( 'ext.video' );

		$this->setHeaders();

		$form = new HTMLForm( $this->getFormFields(), $this->getContext() );
		$form->setIntro( $this->msg( 'video-addvideo-instructions' )->parse() );
		$form->setWrapperLegend( $this->msg( 'video-addvideo-title' )->plain() );
		$form->setSubmitText( $this->msg( 'video-addvideo-button' )->plain() );
		$form->setSubmitCallback( array( $this, 'submit' ) );

		if ( $this->getRequest()->getCheck( 'forReUpload' ) ) {
			$form->addHiddenField( 'forReUpload', true );
		}

		$form->show();
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

		return array( $url, Video::getProviderByURL( $url ) );
	}

	/**
	 * Custom validator for the Video field
	 *
	 * Checks to see if the string given is a valid URL and corresponds
	 * to a supported provider.
	 *
	 * @param array $value
	 * @param array $allData
	 * @return bool|string
	 */
	public function validateVideoField( $value, $allData ) {
		list( , $provider ) = $this->getUrlAndProvider( $value );

		if ( $provider == 'unknown' ) {
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
	 * @param $value Array
	 * @param $allData Array
	 * @return bool|String
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
	public function submit( array $data ) {
		list( $url, $provider ) = $this->getUrlAndProvider( $data['Video'] );

		$this->video->addVideo( $url, $provider, false, $data['Watch'] );

		$this->getOutput()->redirect( $this->video->getTitle()->getFullURL() );

		return true;
	}

	/**
	 * Fields for HTMLForm
	 *
	 * @return array
	 */
	protected function getFormFields() {
		$fields = array(
			'Title' => array(
				'type' => 'text',
				'label-message' => 'video-addvideo-title-label',
				'size' => '30',
				'required' => true,
				'validation-callback' => array( $this, 'validateTitleField' ),
			),
			'Video' => array(
				'type' => 'textarea',
				'label-message' => 'video-addvideo-embed-label',
				'rows' => '5',
				'cols' => '65',
				'required' => true,
				'validation-callback' => array( $this, 'validateVideoField' ),
			),
			'Watch' => array(
				'type' => 'check',
				'label-message' => 'watchthisupload',
				'default' => $this->getUser()->getOption( 'watchdefault' ),
			),
		);

		return $fields;
	}
}