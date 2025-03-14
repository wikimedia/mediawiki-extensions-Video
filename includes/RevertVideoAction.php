<?php

use MediaWiki\MediaWikiServices;

class RevertVideoAction extends FormAction {

	/**
	 * Row from the oldvideo table for the revision to revert to
	 *
	 * @var Wikimedia\Rdbms\IResultWrapper
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
	 * @return string lowercase
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
	 *
	 * @return string
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

		$dbr = MediaWikiServices::getInstance()->getConnectionProvider()->getReplicaDatabase();
		$row = $dbr->selectRow(
			'oldvideo',
			[ 'ov_url', 'ov_type', 'ov_timestamp', 'ov_url', 'ov_name' ],
			[ 'ov_archive_name' => urldecode( $oldvideo ) ],
			__METHOD__
		);
		if ( $row === false ) {
			throw new ErrorPageError( '', 'filerevert-badversion' );
		}
		// @phan-suppress-next-line PhanTypeMismatchProperty
		$this->oldvideo = $row;
	}

	protected function alterForm( HTMLForm $form ) {
		$form->setWrapperLegend( $this->msg( 'video-revert-legend' )->escaped() );
		$form->setSubmitText( $this->msg( 'filerevert-submit' )->escaped() );
		$form->addHiddenField( 'oldvideo', $this->getRequest()->getText( 'oldvideo' ) );
	}

	/**
	 * Get an HTMLForm descriptor array
	 *
	 * @suppress PhanUndeclaredProperty
	 * @return array
	 */
	protected function getFormFields() {
		$timestamp = $this->oldvideo->ov_timestamp;

		return [
			'intro' => [
				'type' => 'info',
				'vertical-label' => true,
				'raw' => true,
				'default' => $this->msg( 'video-revert-intro', $this->getTitle()->getText(),
					$this->getLanguage()->date( $timestamp, true ), $this->getLanguage()->time( $timestamp, true ),
					$this->oldvideo->ov_url )->parse()
			],
		];
	}

	/**
	 * Process the form on POST submission. If you return false from getFormFields(),
	 * this will obviously never be reached. If you don't want to do anything with the
	 * form, just return false here
	 *
	 * @suppress PhanUndeclaredProperty
	 * @param array $data
	 * @return bool
	 */
	public function onSubmit( $data ) {
		// Record upload and update metadata cache
		$video = Video::newFromName( $this->oldvideo->ov_name, $this->getContext() );
		if ( !$video ) {
			return false;
		}
		$this->video = $video;
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
		$out->setPageTitleMsg( $this->msg( 'actioncomplete' ) );
		$out->setRobotPolicy( 'noindex,nofollow' );
		$out->addHTML( $this->msg( 'video-revert-success' )->escaped() );

		$descTitle = $this->video->getTitle();
		$out->returnToMain( null, $descTitle->getPrefixedText() );
	}

	/** @inheritDoc */
	protected function getPageTitle() {
		return $this->msg( 'filerevert', $this->getTitle()->getText() )->escaped();
	}

	/** @inheritDoc */
	protected function getDescription() {
		$this->getOutput()->addBacklinkSubtitle( $this->getTitle() );
		return '';
	}

}
