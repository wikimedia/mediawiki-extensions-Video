<?php

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
	 * Show the special page
	 *
	 * @param $par Mixed: parameter passed to the page or null
	 */
	public function execute( $par ) {
		global $wgRequest, $wgOut, $wgUser, $wgScriptPath;

		// Add CSS
		if ( defined( 'MW_SUPPORTS_RESOURCE_MODULES' ) ) {
			$wgOut->addModuleStyles( 'ext.video' );
		} else {
			$wgOut->addExtensionStyle( $wgScriptPath . '/extensions/Video/Video.css' );
		}

		// If the user doesn't have the required 'addvideo' permission, display an error
		if( !$this->userCanExecute( $this->getUser() ) ) {
			$this->displayRestrictionError();
			return;
		}

		// Show a message if the database is in read-only mode
		if ( wfReadOnly() ) {
			throw new ReadOnlyError;
		}

		// If user is blocked, s/he doesn't need to access this page
		if( $wgUser->isBlocked() ) {
			$wgOut->blockedPage( false );
			return false;
		}

		$this->setHeaders();

		$form = new HTMLForm( $this->getFormFields(), $this->getContext() );
		$form->setIntro( wfMsgExt( 'video-addvideo-instructions', 'parse' ) );
		$form->setWrapperLegend( wfMsg( 'video-addvideo-title' ) );
		$form->setSubmitText( wfMsg( 'video-addvideo-button' ) );
		$form->setSubmitCallback( array( $this, 'submit' ) );

		if ( $this->getRequest()->getCheck( 'forReUpload' ) ) {
			$form->addHiddenField( 'forReUpload', true );
		}

		$form->show();
	}

	protected function getUrlAndProvider( $value ) {
		$url = $value;
		if ( !Video::isURL( $url ) ) {
			$url = Video::getURLfromEmbedCode( $value );
		}

		return array( $url, Video::getProviderByURL( $url ) );
	}

	public function validateVideoField( $value, $allData ) {
		list( , $provider ) = $this->getUrlAndProvider( $value );

		if ( $provider == 'unknown' ) {
			return wfMsg( 'video-addvideo-invalidcode' );
		}

		return true;
	}

	public function validateTitleField( $value, $allData ) {
		$video = Video::newFromName( $value );

		if ( $video === null || !( $video instanceof Video ) ) {
			return wfMsg( 'badtitle' );
		}

		// TODO: Check to see if this is a new version
		if ( $video->exists() && !$this->getRequest()->getCheck( 'forReUpload' ) ) {
			return wfMsgHtml( 'video-addvideo-exists' );
		}

		$this->video = $video;

		return true;
	}

	public function submit( array $data ) {
		list( $url, $provider ) = $this->getUrlAndProvider( $data['Video'] );

		$this->video->addVideo( $url, $provider, false, $data['Watch'] );

		$this->getOutput()->redirect( $this->video->getTitle()->getFullURL() );

		return true;
	}

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