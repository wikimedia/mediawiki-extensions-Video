<?php

class RevertVideoAction extends FormAction {

	/**
	 * Row from the oldvideo table for the revision to revert to
	 *
	 * @var ResultWrapper
	 */
	protected $oldvideo;

	/**
	 * Video object defined in onSubmit() and used again in onSuccess()
	 *
	 * @var Video
	 */
	protected $video;

	/**
	 * Return the name of the action this object responds to
	 * @return String lowercase
	 */
	public function getName() {
		return 'revert';
	}

	public function doesWrites() {
		return true;
	}

	/**
	 * Get the permission required to perform this action.  Often, but not always,
	 * the same as the action name
	 */
	public function getRestriction() {
		return 'edit';
	}

	protected function checkCanExecute( User $user ) {
		parent::checkCanExecute( $user );

		$oldvideo = $this->getRequest()->getText( 'oldvideo' );
		if ( strlen( $oldvideo ) < 16 ) {
			throw new ErrorPageError( 'internalerror', 'unexpected', [ 'oldvideo', $oldvideo ] );
		}

		$dbr = wfGetDB( DB_REPLICA );
		$row = $dbr->selectRow(
			'oldvideo',
			[ 'ov_url', 'ov_type', 'ov_timestamp', 'ov_url', 'ov_name' ],
			[ 'ov_archive_name' => urldecode( $oldvideo ) ],
			__METHOD__
		);
		if ( $row === false ) {
			throw new ErrorPageError( '', 'filerevert-badversion' );
		}
		$this->oldvideo = $row;
	}

	protected function alterForm( HTMLForm $form ) {
		$form->setWrapperLegend( wfMessage( 'video-revert-legend' )->escaped() );
		$form->setSubmitText( wfMessage( 'filerevert-submit' )->escaped() );
		$form->addHiddenField( 'oldvideo', $this->getRequest()->getText( 'oldvideo' ) );
	}

	/**
	 * Get an HTMLForm descriptor array
	 * @return array
	 */
	protected function getFormFields() {
		$timestamp = $this->oldvideo->ov_timestamp;

		return [
			'intro' => [
				'type' => 'info',
				'vertical-label' => true,
				'raw' => true,
				'default' => wfMessage( 'video-revert-intro', $this->getTitle()->getText(),
					$this->getLanguage()->date( $timestamp, true ), $this->getLanguage()->time( $timestamp, true ),
					$this->oldvideo->ov_url )->parse()
			],
		];
	}

	/**
	 * Process the form on POST submission. If you return false from getFormFields(),
	 * this will obviously never be reached. If you don't want to do anything with the
	 * form, just return false here
	 * @param array $data
	 * @return bool|array True for success, false for didn't-try, array of errors on failure
	 */
	public function onSubmit( $data ) {
		// Record upload and update metadata cache
		$this->video = Video::newFromName( $this->oldvideo->ov_name, $this->getContext() );
		$this->video->addVideo( $this->oldvideo->ov_url, $this->oldvideo->ov_type, '' );

		return true;
	}

	/**
	 * Do something exciting on successful processing of the form.
	 * This might be to show a confirmation message (watch, rollback, etc.) or
	 * to redirect somewhere else (edit, protect, etc).
	 */
	public function onSuccess() {
		$out = $this->getOutput();
		$out->setPageTitle( wfMessage( 'actioncomplete' )->escaped() );
		$out->setRobotPolicy( 'noindex,nofollow' );
		$out->addHTML( wfMessage( 'video-revert-success' )->escaped() );

		$descTitle = $this->video->getTitle();
		$out->returnToMain( null, $descTitle->getPrefixedText() );
	}

	protected function getPageTitle() {
		return wfMessage( 'filerevert', $this->getTitle()->getText() )->escaped();
	}

	protected function getDescription() {
		$this->getOutput()->addBacklinkSubtitle( $this->getTitle() );
		return '';
	}

}
