<?php
/**
 * A hacked version of MediaWiki's standard Special:Undelete for supporting the
 * undeletion of videos without changing core MediaWiki code.
 *
 * Based on MediaWiki 1.30.0's /includes/specials/SpecialUndelete.php.
 *
 * Check the code comments to see what's changed.
 * The four major chunks of code which have been added are marked with "CORE HACK",
 * but I had to copy a lot of other, unrelated code here to prevent things from
 * falling apart. Almost everything in SpecialUndelete is private and it makes
 * me very sad and angry.
 *
 * @file
 * @ingroup SpecialPage
 * @date 15 January 2017
 */

class SpecialUndeleteWithVideoSupport extends SpecialUndelete {
	/** @var Title */
	private $mTargetObj;

	function __construct() {
		SpecialPage::__construct( 'Undelete', 'deletedhistory' );
	}

	function loadRequest( $par ) {
		$request = $this->getRequest();
		$user = $this->getUser();

		$this->mAction = $request->getVal( 'action' );
		if ( $par !== null && $par !== '' ) {
			$this->mTarget = $par;
		} else {
			$this->mTarget = $request->getVal( 'target' );
		}

		$this->mTargetObj = null;

		if ( $this->mTarget !== null && $this->mTarget !== '' ) {
			$this->mTargetObj = Title::newFromText( urldecode( $this->mTarget ) ); // CORE HACK: added urldecode() here
		}

		$this->mSearchPrefix = $request->getText( 'prefix' );
		$time = $request->getVal( 'timestamp' );
		$this->mTimestamp = $time ? wfTimestamp( TS_MW, $time ) : '';
		$this->mFilename = $request->getVal( 'file' );

		$posted = $request->wasPosted() &&
			$user->matchEditToken( $request->getVal( 'wpEditToken' ) );
		$this->mRestore = $request->getCheck( 'restore' ) && $posted;
		$this->mRevdel = $request->getCheck( 'revdel' ) && $posted;
		$this->mInvert = $request->getCheck( 'invert' ) && $posted;
		$this->mPreview = $request->getCheck( 'preview' ) && $posted;
		$this->mDiff = $request->getCheck( 'diff' );
		$this->mDiffOnly = $request->getBool( 'diffonly', $this->getUser()->getOption( 'diffonly' ) );
		$this->mComment = $request->getText( 'wpComment' );
		$this->mUnsuppress = $request->getVal( 'wpUnsuppress' ) && $user->isAllowed( 'suppressrevision' );
		$this->mToken = $request->getVal( 'token' );

		if ( $this->isAllowed( 'undelete' ) && !$user->isBlocked() ) {
			$this->mAllowed = true; // user can restore
			$this->mCanView = true; // user can view content
		} elseif ( $this->isAllowed( 'deletedtext' ) ) {
			$this->mAllowed = false; // user cannot restore
			$this->mCanView = true; // user can view content
			$this->mRestore = false;
		} else { // user can only view the list of revisions
			$this->mAllowed = false;
			$this->mCanView = false;
			$this->mTimestamp = '';
			$this->mRestore = false;
		}

		if ( $this->mRestore || $this->mInvert ) {
			$timestamps = [];
			$this->mFileVersions = [];
			foreach ( $request->getValues() as $key => $val ) {
				$matches = [];
				if ( preg_match( '/^ts(\d{14})$/', $key, $matches ) ) {
					array_push( $timestamps, $matches[1] );
				}

				if ( preg_match( '/^fileid(\d+)$/', $key, $matches ) ) {
					$this->mFileVersions[] = intval( $matches[1] );
				}
			}
			rsort( $timestamps );
			$this->mTargetTimestamp = $timestamps;
		}
	}

	/**
	 * Checks whether a user is allowed the permission for the
	 * specific title if one is set.
	 *
	 * @param string $permission
	 * @param User $user
	 * @return bool
	 */
	function isAllowed( $permission, User $user = null ) {
		$user = $user ? : $this->getUser();
		if ( $this->mTargetObj !== null ) {
			return $this->mTargetObj->userCan( $permission, $user );
		} else {
			return $user->isAllowed( $permission );
		}
	}

	function execute( $par ) {
		$this->useTransactionalTimeLimit();

		$user = $this->getUser();

		$this->setHeaders();
		$this->outputHeader();

		$this->loadRequest( $par );
		$this->checkPermissions(); // Needs to be after mTargetObj is set

		$out = $this->getOutput();

		if ( is_null( $this->mTargetObj ) ) {
			$out->addWikiMsg( 'undelete-header' );

			# Not all users can just browse every deleted page from the list
			if ( $user->isAllowed( 'browsearchive' ) ) {
				$this->showSearchForm();
			}

			return;
		}

		$this->addHelpLink( 'Help:Undelete' );
		if ( $this->mAllowed ) {
			$out->setPageTitle( $this->msg( 'undeletepage' ) );
		} else {
			$out->setPageTitle( $this->msg( 'viewdeletedpage' ) );
		}

		$this->getSkin()->setRelevantTitle( $this->mTargetObj );

		if ( $this->mTimestamp !== '' ) {
			$this->showRevision( $this->mTimestamp );
		} elseif ( $this->mFilename !== null && $this->mTargetObj->inNamespace( NS_FILE ) ) {
			$file = new ArchivedFile( $this->mTargetObj, '', $this->mFilename );
			// Check if user is allowed to see this file
			if ( !$file->exists() ) {
				$out->addWikiMsg( 'filedelete-nofile', $this->mFilename );
			} elseif ( !$file->userCan( File::DELETED_FILE, $user ) ) {
				if ( $file->isDeleted( File::DELETED_RESTRICTED ) ) {
					throw new PermissionsError( 'suppressrevision' );
				} else {
					throw new PermissionsError( 'deletedtext' );
				}
			} elseif ( !$user->matchEditToken( $this->mToken, $this->mFilename ) ) {
				$this->showFileConfirmationForm( $this->mFilename );
			} else {
				$this->showFile( $this->mFilename );
			}
		}
		// CORE HACK for [[mw:Extension:Video]]
		elseif ( $this->mTargetObj->inNamespace( NS_VIDEO ) ) {
			$file = new ArchivedVideo( $this->mTargetObj, '', $this->mFilename );
			// Check if user is allowed to see this file
			if ( !$file->exists() ) {
				$out->addWikiMsg( 'filedelete-nofile', $this->mFilename );
			} elseif ( !$file->userCan( File::DELETED_FILE, $user ) ) {
				if ( $file->isDeleted( File::DELETED_RESTRICTED ) ) {
					throw new PermissionsError( 'suppressrevision' );
				} else {
					throw new PermissionsError( 'deletedtext' );
				}
			}
			if ( $this->mRestore && $this->mAction == 'submit' ) {
				$this->undelete();
			} else {
				$this->showHistory();
			}
		}
		// END CORE HACK
		elseif ( $this->mAction == 'submit' ) {
			if ( $this->mRestore ) {
				$this->undelete();
			} elseif ( $this->mRevdel ) {
				$this->redirectToRevDel();
			}
		} else {
			$this->showHistory();
		}
	}

	/**
	 * Convert submitted form data to format expected by RevisionDelete and
	 * redirect the request
	 */
	private function redirectToRevDel() {
		// CORE HACK
		if ( $this->mTargetObj->inNamespace( NS_VIDEO ) ) {
			$archive = new VideoPageArchive( $this->mTargetObj );
		} else {
			$archive = new PageArchive( $this->mTargetObj );
		}
		// CORE HACK END

		$revisions = [];

		foreach ( $this->getRequest()->getValues() as $key => $val ) {
			$matches = [];
			if ( preg_match( "/^ts(\d{14})$/", $key, $matches ) ) {
				$revisions[$archive->getRevision( $matches[1] )->getId()] = 1;
			}
		}
		$query = [
			'type' => 'revision',
			'ids' => $revisions,
			'target' => $this->mTargetObj->getPrefixedText()
		];
		$url = SpecialPage::getTitleFor( 'Revisiondelete' )->getFullURL( $query );
		$this->getOutput()->redirect( $url );
	}

	function showHistory() {
		$this->checkReadOnly();

		$out = $this->getOutput();
		if ( $this->mAllowed ) {
			$out->addModules( 'mediawiki.special.undelete' );
		}
		$out->wrapWikiMsg(
			"<div class='mw-undelete-pagetitle'>\n$1\n</div>\n",
			[ 'undeletepagetitle', wfEscapeWikiText( $this->mTargetObj->getPrefixedText() ) ]
		);

		// CORE HACK
		if ( $this->mTargetObj->inNamespace( NS_VIDEO ) ) {
			$archive = new VideoPageArchive( $this->mTargetObj, $this->getConfig() );
		} else {
			$archive = new PageArchive( $this->mTargetObj, $this->getConfig() );
		}
		// CORE HACK END
		Hooks::run( 'UndeleteForm::showHistory', [ &$archive, $this->mTargetObj ] );

		$out->addHTML( '<div class="mw-undelete-history">' );
		if ( $this->mAllowed ) {
			$out->addWikiMsg( 'undeletehistory' );
			$out->addWikiMsg( 'undeleterevdel' );
		} else {
			$out->addWikiMsg( 'undeletehistorynoadmin' );
		}
		$out->addHTML( '</div>' );

		# List all stored revisions
		$revisions = $archive->listRevisions();
		$files = $archive->listFiles();

		$haveRevisions = $revisions && $revisions->numRows() > 0;
		$haveFiles = $files && $files->numRows() > 0;

		# Batch existence check on user and talk pages
		if ( $haveRevisions ) {
			$batch = new LinkBatch();
			foreach ( $revisions as $row ) {
				$batch->addObj( Title::makeTitleSafe( NS_USER, $row->ar_user_text ) );
				$batch->addObj( Title::makeTitleSafe( NS_USER_TALK, $row->ar_user_text ) );
			}
			$batch->execute();
			$revisions->seek( 0 );
		}
		if ( $haveFiles ) {
			$batch = new LinkBatch();
			foreach ( $files as $row ) {
				// CORE HACK for [[mw:Extension:Video]]
				if ( isset( $row->ov_user_name ) && $row->ov_user_name ) {
					$batch->addObj( Title::makeTitleSafe( NS_USER, $row->ov_user_name ) );
					$batch->addObj( Title::makeTitleSafe( NS_USER_TALK, $row->ov_user_name ) );
				} else {
					$batch->addObj( Title::makeTitleSafe( NS_USER, $row->fa_user_text ) );
					$batch->addObj( Title::makeTitleSafe( NS_USER_TALK, $row->fa_user_text ) );
				}
				// END CORE HACK
			}
			$batch->execute();
			$files->seek( 0 );
		}

		if ( $this->mAllowed ) {
			$out->enableOOUI();

			$action = $this->getPageTitle()->getLocalURL( [ 'action' => 'submit' ] );
			# Start the form here
			$form = new OOUI\FormLayout( [
				'method' => 'post',
				'action' => $action,
				'id' => 'undelete',
			] );
		}

		# Show relevant lines from the deletion log:
		$deleteLogPage = new LogPage( 'delete' );
		$out->addHTML( Xml::element( 'h2', null, $deleteLogPage->getName()->text() ) . "\n" );
		LogEventsList::showLogExtract( $out, 'delete', $this->mTargetObj );
		# Show relevant lines from the suppression log:
		$suppressLogPage = new LogPage( 'suppress' );
		if ( $this->getUser()->isAllowed( 'suppressionlog' ) ) {
			$out->addHTML( Xml::element( 'h2', null, $suppressLogPage->getName()->text() ) . "\n" );
			LogEventsList::showLogExtract( $out, 'suppress', $this->mTargetObj );
		}

		if ( $this->mAllowed && ( $haveRevisions || $haveFiles ) ) {
			$fields[] = new OOUI\Layout( [
				'content' => new OOUI\HtmlSnippet( $this->msg( 'undeleteextrahelp' )->parseAsBlock() )
			] );

			$fields[] = new OOUI\FieldLayout(
				new OOUI\TextInputWidget( [
					'name' => 'wpComment',
					'inputId' => 'wpComment',
					'infusable' => true,
					'value' => $this->mComment,
					'autofocus' => true,
				] ),
				[
					'label' => $this->msg( 'undeletecomment' )->text(),
					'align' => 'top',
				]
			);

			$fields[] = new OOUI\FieldLayout(
				new OOUI\Widget( [
					'content' => new OOUI\HorizontalLayout( [
						'items' => [
							new OOUI\ButtonInputWidget( [
								'name' => 'restore',
								'inputId' => 'mw-undelete-submit',
								'value' => '1',
								'label' => $this->msg( 'undeletebtn' )->text(),
								'flags' => [ 'primary', 'progressive' ],
								'type' => 'submit',
							] ),
							new OOUI\ButtonInputWidget( [
								'name' => 'invert',
								'inputId' => 'mw-undelete-invert',
								'value' => '1',
								'label' => $this->msg( 'undeleteinvert' )->text()
							] ),
						]
					] )
				] )
			);

			if ( $this->getUser()->isAllowed( 'suppressrevision' ) ) {
				$fields[] = new OOUI\FieldLayout(
					new OOUI\CheckboxInputWidget( [
						'name' => 'wpUnsuppress',
						'inputId' => 'mw-undelete-unsuppress',
						'value' => '1',
					] ),
					[
						'label' => $this->msg( 'revdelete-unsuppress' )->text(),
						'align' => 'inline',
					]
				);
			}

			$fieldset = new OOUI\FieldsetLayout( [
				'label' => $this->msg( 'undelete-fieldset-title' )->text(),
				'id' => 'mw-undelete-table',
				'items' => $fields,
			] );

			$form->appendContent(
				new OOUI\PanelLayout( [
					'expanded' => false,
					'padded' => true,
					'framed' => true,
					'content' => $fieldset,
				] ),
				new OOUI\HtmlSnippet(
					Html::hidden( 'target', $this->mTarget ) .
					Html::hidden( 'wpEditToken', $this->getUser()->getEditToken() )
				)
			);
		}

		$history = '';
		$history .= Xml::element( 'h2', null, $this->msg( 'history' )->text() ) . "\n";

		if ( $haveRevisions ) {
			# Show the page's stored (deleted) history

			if ( $this->getUser()->isAllowed( 'deleterevision' ) ) {
				$history .= Html::element(
					'button',
					[
						'name' => 'revdel',
						'type' => 'submit',
						'class' => 'deleterevision-log-submit mw-log-deleterevision-button'
					],
					$this->msg( 'showhideselectedversions' )->text()
				) . "\n";
			}

			$history .= '<ul class="mw-undelete-revlist">';
			$remaining = $revisions->numRows();
			$earliestLiveTime = $this->mTargetObj->getEarliestRevTime();

			foreach ( $revisions as $row ) {
				$remaining--;
				$history .= $this->formatRevisionRow( $row, $earliestLiveTime, $remaining );
			}
			$revisions->free();
			$history .= '</ul>';
		} else {
			$out->addWikiMsg( 'nohistory' );
		}

		if ( $haveFiles ) {
			$history .= Xml::element( 'h2', null, $this->msg( 'filehist' )->text() ) . "\n";
			$history .= '<ul class="mw-undelete-revlist">';
			foreach ( $files as $row ) {
				$history .= $this->formatFileRow( $row );
			}
			$files->free();
			$history .= '</ul>';
		}

		if ( $this->mAllowed ) {
			# Slip in the hidden controls here
			$misc = Html::hidden( 'target', $this->mTarget );
			$misc .= Html::hidden( 'wpEditToken', $this->getUser()->getEditToken() );
			$history .= $misc;

			$form->appendContent( new OOUI\HtmlSnippet( $history ) );
			$out->addHTML( $form );
		} else {
			$out->addHTML( $history );
		}

		return true;
	}

	private function formatFileRow( $row ) {
		// CORE HACK for [[mw:Extension:Video]]
		if ( isset( $row->ov_name ) && $row->ov_name ) {
			$file = ArchivedVideo::newFromRow( $row );
			$ts = wfTimestamp( TS_MW, $row->ov_timestamp );
			$user = $this->getUser();

			if ( $this->mAllowed ) { //&& $row->fa_storage_key ) {
				$checkBox = Xml::check( 'fileid' ); //. $row->fa_id );
				$key = rand();//urlencode( $row->fa_storage_key );
				$pageLink = $this->getFileLink( $file, $this->getPageTitle(), $ts, $key );
			} else {
				$checkBox = '';
				$pageLink = $this->getLanguage()->userTimeAndDate( $ts, $user );
			}
			$userLink = $this->getFileUser( $file );
			$data = $comment = $revdlink = '';

			return "<li>$checkBox $revdlink $pageLink . . ({$row->ov_type}) . . $userLink $data $comment</li>\n";
		}
		// END CORE HACK
		return parent::formatFileRow( $row );
	}

	// The following is copied as-is because otherwise it results in this error
	// whenever trying to undelete a video:
	// Fatal error: Call to a member function getPrefixedText() on a non-object in ..\includes\specials\SpecialUndelete.php on line 1538
	/**
	 * Fetch revision text link if it's available to all users
	 *
	 * @param Revision $rev
	 * @param Title $titleObj
	 * @param string $ts Timestamp
	 * @return string
	 */
	function getPageLink( $rev, $titleObj, $ts ) {
		$user = $this->getUser();
		$time = $this->getLanguage()->userTimeAndDate( $ts, $user );

		if ( !$rev->userCan( Revision::DELETED_TEXT, $user ) ) {
			return '<span class="history-deleted">' . $time . '</span>';
		}

		$link = $this->getLinkRenderer()->makeKnownLink(
			$titleObj,
			$time,
			[],
			[
				'target' => $this->mTargetObj->getPrefixedText(),
				'timestamp' => $ts
			]
		);

		if ( $rev->isDeleted( Revision::DELETED_TEXT ) ) {
			$link = '<span class="history-deleted">' . $link . '</span>';
		}

		return $link;
	}

	/**
	 * Fetch video or file view link if it's available to all users
	 *
	 * @param Video|ArchivedVideo|File|ArchivedFile $file
	 * @param Title $titleObj
	 * @param string $ts A timestamp
	 * @param string $key A storage key
	 *
	 * @return string HTML fragment
	 */
	function getFileLink( $file, $titleObj, $ts, $key ) {
		$user = $this->getUser();
		$time = $this->getLanguage()->userTimeAndDate( $ts, $user );

		if ( !$file->userCan( File::DELETED_FILE, $user ) ) {
			return '<span class="history-deleted">' . $time . '</span>';
		}

		// CORE HACK for [[mw:Extension:Video]]
		if ( $file instanceof ArchivedVideo ) {
			$link = Linker::makeExternalLink(
				$file->getURL(), $time, /* $escape */ true, /* $linktype */ '',
				/* $attribs */ [], $titleObj
			);
		} else {
			$link = $this->getLinkRenderer()->makeKnownLink(
				$titleObj,
				$time,
				[],
				[
					'target' => $this->mTargetObj->getPrefixedText(),
					'file' => $key,
					'token' => $user->getEditToken( $key )
				]
			);
		}
		// END CORE HACK

		if ( $file->isDeleted( File::DELETED_FILE ) ) {
			$link = '<span class="history-deleted">' . $link . '</span>';
		}

		return $link;
	}

	function formatRevisionRow( $row, $earliestLiveTime, $remaining ) {
		$rev = Revision::newFromArchiveRow( $row,
			[
				'title' => $this->mTargetObj
			] );

		$revTextSize = '';
		$ts = wfTimestamp( TS_MW, $row->ar_timestamp );
		// Build checkboxen...
		if ( $this->mAllowed ) {
			if ( $this->mInvert ) {
				if ( in_array( $ts, $this->mTargetTimestamp ) ) {
					$checkBox = Xml::check( "ts$ts" );
				} else {
					$checkBox = Xml::check( "ts$ts", true );
				}
			} else {
				$checkBox = Xml::check( "ts$ts" );
			}
		} else {
			$checkBox = '';
		}

		// Build page & diff links...
		$user = $this->getUser();
		if ( $this->mCanView ) {
			$titleObj = $this->getPageTitle();
			# Last link
			if ( !$rev->userCan( Revision::DELETED_TEXT, $this->getUser() ) ) {
				$pageLink = htmlspecialchars( $this->getLanguage()->userTimeAndDate( $ts, $user ) );
				$last = $this->msg( 'diff' )->escaped();
			} elseif ( $remaining > 0 || ( $earliestLiveTime && $ts > $earliestLiveTime ) ) {
				$pageLink = $this->getPageLink( $rev, $titleObj, $ts );
				$last = $this->getLinkRenderer()->makeKnownLink(
					$titleObj,
					$this->msg( 'diff' )->text(),
					[],
					[
						'target' => $this->mTargetObj->getPrefixedText(),
						'timestamp' => $ts,
						'diff' => 'prev'
					]
				);
			} else {
				$pageLink = $this->getPageLink( $rev, $titleObj, $ts );
				$last = $this->msg( 'diff' )->escaped();
			}
		} else {
			$pageLink = htmlspecialchars( $this->getLanguage()->userTimeAndDate( $ts, $user ) );
			$last = $this->msg( 'diff' )->escaped();
		}

		// User links
		$userLink = Linker::revUserTools( $rev );

		// Minor edit
		$minor = $rev->isMinor() ? ChangesList::flag( 'minor' ) : '';

		// Revision text size
		$size = $row->ar_len;
		if ( !is_null( $size ) ) {
			$revTextSize = Linker::formatRevisionSize( $size );
		}

		// Edit summary
		$comment = Linker::revComment( $rev );

		// Tags
		$attribs = [];
		list( $tagSummary, $classes ) = ChangeTags::formatSummaryRow(
			$row->ts_tags,
			'deletedhistory',
			$this->getContext()
		);
		if ( $classes ) {
			$attribs['class'] = implode( ' ', $classes );
		}

		$revisionRow = $this->msg( 'undelete-revision-row2' )
			->rawParams(
				$checkBox,
				$last,
				$pageLink,
				$userLink,
				$minor,
				$revTextSize,
				$comment,
				$tagSummary
			)
			->escaped();

		return Xml::tags( 'li', $attribs, $revisionRow ) . "\n";
	}

	// aaaaaaand if we don't copy *this* function over, actually doing the
	// undeletion will be impossible because PageArchive (sic) is passed a null
	// Title when trying to undelete a Video. Fuck this is so fucking awesome...not.
	function undelete() {
		if ( $this->getConfig()->get( 'UploadMaintenance' )
			&& $this->mTargetObj->getNamespace() == NS_FILE
		) {
			throw new ErrorPageError( 'undelete-error', 'filedelete-maintenance' );
		}

		$this->checkReadOnly();

		$out = $this->getOutput();
		// CORE HACK
		if ( $this->mTargetObj->inNamespace( NS_VIDEO ) ) {
			$archive = new VideoPageArchive( $this->mTargetObj, $this->getConfig() );
		} else {
			$archive = new PageArchive( $this->mTargetObj, $this->getConfig() );
		}
		// CORE HACK END
		Hooks::run( 'UndeleteForm::undelete', [ &$archive, $this->mTargetObj ] );
		$ok = $archive->undelete(
			$this->mTargetTimestamp,
			$this->mComment,
			$this->mFileVersions,
			$this->mUnsuppress,
			$this->getUser()
		);

		if ( is_array( $ok ) ) {
			if ( $ok[1] ) { // Undeleted file count
				Hooks::run( 'FileUndeleteComplete', [
					$this->mTargetObj, $this->mFileVersions,
					$this->getUser(), $this->mComment ] );
			}

			$link = $this->getLinkRenderer()->makeKnownLink( $this->mTargetObj );
			$out->addHTML( $this->msg( 'undeletedpage' )->rawParams( $link )->parse() );
		} else {
			$out->setPageTitle( $this->msg( 'undelete-error' ) );
		}

		// Show revision undeletion warnings and errors
		$status = $archive->getRevisionStatus();
		if ( $status && !$status->isGood() ) {
			$out->addWikiText( '<div class="error" id="mw-error-cannotundelete">' .
				$status->getWikiText(
					'cannotundelete',
					'cannotundelete'
				) . '</div>'
			);
		}

		// Show file undeletion warnings and errors
		$status = $archive->getFileStatus();
		if ( $status && !$status->isGood() ) {
			$out->addWikiText( '<div class="error">' .
				$status->getWikiText(
					'undelete-error-short',
					'undelete-error-long'
				) . '</div>'
			);
		}
	}

	// This one's from MW 1.31.0. It's a private function in the parent class so
	// this class cannot call it, not to mention we still need to use our own
	// archive class here anyway.
	private function showRevision( $timestamp ) {
		if ( !preg_match( '/[0-9]{14}/', $timestamp ) ) {
			return;
		}

		// CORE HACK
		if ( $this->mTargetObj->inNamespace( NS_VIDEO ) ) {
			$archive = new VideoPageArchive( $this->mTargetObj, $this->getConfig() );
		} else {
			$archive = new PageArchive( $this->mTargetObj, $this->getConfig() );
		}
		// END CORE HACK
		if ( !Hooks::run( 'UndeleteForm::showRevision', [ &$archive, $this->mTargetObj ] ) ) {
			return;
		}
		$rev = $archive->getRevision( $timestamp );

		$out = $this->getOutput();
		$user = $this->getUser();

		if ( !$rev ) {
			$out->addWikiMsg( 'undeleterevision-missing' );

			return;
		}

		if ( $rev->isDeleted( Revision::DELETED_TEXT ) ) {
			if ( !$rev->userCan( Revision::DELETED_TEXT, $user ) ) {
				$out->wrapWikiMsg(
					"<div class='mw-warning plainlinks'>\n$1\n</div>\n",
				$rev->isDeleted( Revision::DELETED_RESTRICTED ) ?
					'rev-suppressed-text-permission' : 'rev-deleted-text-permission'
				);

				return;
			}

			$out->wrapWikiMsg(
				"<div class='mw-warning plainlinks'>\n$1\n</div>\n",
				$rev->isDeleted( Revision::DELETED_RESTRICTED ) ?
					'rev-suppressed-text-view' : 'rev-deleted-text-view'
			);
			$out->addHTML( '<br />' );
			// and we are allowed to see...
		}

		if ( $this->mDiff ) {
			$previousRev = $archive->getPreviousRevision( $timestamp );
			if ( $previousRev ) {
				$this->showDiff( $previousRev, $rev );
				if ( $this->mDiffOnly ) {
					return;
				}

				$out->addHTML( '<hr />' );
			} else {
				$out->addWikiMsg( 'undelete-nodiff' );
			}
		}

		$link = $this->getLinkRenderer()->makeKnownLink(
			$this->getPageTitle( $this->mTargetObj->getPrefixedDBkey() ),
			$this->mTargetObj->getPrefixedText()
		);

		$lang = $this->getLanguage();

		// date and time are separate parameters to facilitate localisation.
		// $time is kept for backward compat reasons.
		$time = $lang->userTimeAndDate( $timestamp, $user );
		$d = $lang->userDate( $timestamp, $user );
		$t = $lang->userTime( $timestamp, $user );
		$userLink = Linker::revUserTools( $rev );

		$content = $rev->getContent( Revision::FOR_THIS_USER, $user );

		$isText = ( $content instanceof TextContent );

		if ( $this->mPreview || $isText ) {
			$openDiv = '<div id="mw-undelete-revision" class="mw-warning">';
		} else {
			$openDiv = '<div id="mw-undelete-revision">';
		}
		$out->addHTML( $openDiv );

		// Revision delete links
		if ( !$this->mDiff ) {
			$revdel = Linker::getRevDeleteLink( $user, $rev, $this->mTargetObj );
			if ( $revdel ) {
				$out->addHTML( "$revdel " );
			}
		}

		$out->addHTML( $this->msg( 'undelete-revision' )->rawParams( $link )->params(
			$time )->rawParams( $userLink )->params( $d, $t )->parse() . '</div>' );

		if ( !Hooks::run( 'UndeleteShowRevision', [ $this->mTargetObj, $rev ] ) ) {
			return;
		}

		if ( ( $this->mPreview || !$isText ) && $content ) {
			// NOTE: non-text content has no source view, so always use rendered preview

			$popts = $out->parserOptions();

			$pout = $content->getParserOutput( $this->mTargetObj, $rev->getId(), $popts, true );
			$out->addParserOutput( $pout, [
				'enableSectionEditLinks' => false,
			] );
		}

		$out->enableOOUI();
		$buttonFields = [];

		if ( $isText ) {
			// source view for textual content
			$sourceView = Xml::element( 'textarea', [
				'readonly' => 'readonly',
				'cols' => 80,
				'rows' => 25
			], $content->getNativeData() . "\n" );

			$buttonFields[] = new OOUI\ButtonInputWidget( [
				'type' => 'submit',
				'name' => 'preview',
				'label' => $this->msg( 'showpreview' )->text()
			] );
		} else {
			$sourceView = '';
			$previewButton = '';
		}

		$buttonFields[] = new OOUI\ButtonInputWidget( [
			'name' => 'diff',
			'type' => 'submit',
			'label' => $this->msg( 'showdiff' )->text()
		] );

		$out->addHTML(
			$sourceView .
				Xml::openElement( 'div', [
					'style' => 'clear: both' ] ) .
				Xml::openElement( 'form', [
					'method' => 'post',
					'action' => $this->getPageTitle()->getLocalURL( [ 'action' => 'submit' ] ) ] ) .
				Xml::element( 'input', [
					'type' => 'hidden',
					'name' => 'target',
					'value' => $this->mTargetObj->getPrefixedDBkey() ] ) .
				Xml::element( 'input', [
					'type' => 'hidden',
					'name' => 'timestamp',
					'value' => $timestamp ] ) .
				Xml::element( 'input', [
					'type' => 'hidden',
					'name' => 'wpEditToken',
					'value' => $user->getEditToken() ] ) .
				new OOUI\FieldLayout(
					new OOUI\Widget( [
						'content' => new OOUI\HorizontalLayout( [
							'items' => $buttonFields
						] )
					] )
				) .
				Xml::closeElement( 'form' ) .
				Xml::closeElement( 'div' )
		);
	}

}