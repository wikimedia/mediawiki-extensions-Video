<?php
/**
 * A hacked version of MediaWiki's standard Special:Undelete for supporting the
 * undeletion of videos without changing core MediaWiki code.
 *
 * Based on MediaWiki 1.24.1's /includes/specials/SpecialUndelete.php.
 *
 * Check the code comments to see what's changed.
 * The four major chunks of code which have been added are marked with "CORE HACK",
 * but I had to copy a lot of other, unrelated code here to prevent things from
 * falling apart. Almost everything in SpecialUndelete is private and it makes
 * me very sad and angry.
 *
 * @file
 * @ingroup SpecialPage
 * @date 4 May 2015
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
			$timestamps = array();
			$this->mFileVersions = array();
			foreach ( $request->getValues() as $key => $val ) {
				$matches = array();
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

		if ( method_exists( $out, 'addHelpLink' ) ) { // MW 1.25 or 1.26+ thing
			$out->addHelpLink( 'Help:Undelete' );
		}
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
		elseif ( $this->mRestore && $this->mAction == 'submit' ) {
			$this->undelete();
		} else {
			$this->showHistory();
		}
	}

	private function showHistory() {
		$out = $this->getOutput();
		if ( $this->mAllowed ) {
			$out->addModules( 'mediawiki.special.undelete' );
		}
		$out->wrapWikiMsg(
			"<div class='mw-undelete-pagetitle'>\n$1\n</div>\n",
			array( 'undeletepagetitle', wfEscapeWikiText( $this->mTargetObj->getPrefixedText() ) )
		);

		$archive = new PageArchive( $this->mTargetObj, $this->getConfig() );
		Hooks::run( 'UndeleteForm::showHistory', array( &$archive, $this->mTargetObj ) );
		/*
		$text = $archive->getLastRevisionText();
		if( is_null( $text ) ) {
			$out->addWikiMsg( 'nohistory' );
			return;
		}
		*/
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
			$action = $this->getPageTitle()->getLocalURL( array( 'action' => 'submit' ) );
			# Start the form here
			$top = Xml::openElement(
				'form',
				array( 'method' => 'post', 'action' => $action, 'id' => 'undelete' )
			);
			$out->addHTML( $top );
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
			# Format the user-visible controls (comment field, submission button)
			# in a nice little table
			if ( $this->getUser()->isAllowed( 'suppressrevision' ) ) {
				$unsuppressBox =
					"<tr>
						<td>&#160;</td>
						<td class='mw-input'>" .
						Xml::checkLabel( $this->msg( 'revdelete-unsuppress' )->text(),
							'wpUnsuppress', 'mw-undelete-unsuppress', $this->mUnsuppress ) .
						"</td>
					</tr>";
			} else {
				$unsuppressBox = '';
			}

			$table = Xml::fieldset( $this->msg( 'undelete-fieldset-title' )->text() ) .
				Xml::openElement( 'table', array( 'id' => 'mw-undelete-table' ) ) .
				"<tr>
					<td colspan='2' class='mw-undelete-extrahelp'>" .
				$this->msg( 'undeleteextrahelp' )->parseAsBlock() .
				"</td>
			</tr>
			<tr>
				<td class='mw-label'>" .
				Xml::label( $this->msg( 'undeletecomment' )->text(), 'wpComment' ) .
				"</td>
				<td class='mw-input'>" .
				Xml::input(
					'wpComment',
					50,
					$this->mComment,
					array( 'id' => 'wpComment', 'autofocus' => '' )
				) .
				"</td>
			</tr>
			<tr>
				<td>&#160;</td>
				<td class='mw-submit'>" .
				Xml::submitButton(
					$this->msg( 'undeletebtn' )->text(),
					array( 'name' => 'restore', 'id' => 'mw-undelete-submit' )
				) . ' ' .
				Xml::submitButton(
					$this->msg( 'undeleteinvert' )->text(),
					array( 'name' => 'invert', 'id' => 'mw-undelete-invert' )
				) .
				"</td>
			</tr>" .
				$unsuppressBox .
				Xml::closeElement( 'table' ) .
				Xml::closeElement( 'fieldset' );

			$out->addHTML( $table );
		}

		$out->addHTML( Xml::element( 'h2', null, $this->msg( 'history' )->text() ) . "\n" );

		if ( $haveRevisions ) {
			# The page's stored (deleted) history:
			$out->addHTML( '<ul>' );
			$remaining = $revisions->numRows();
			$earliestLiveTime = $this->mTargetObj->getEarliestRevTime();

			foreach ( $revisions as $row ) {
				$remaining--;
				$out->addHTML( $this->formatRevisionRow( $row, $earliestLiveTime, $remaining ) );
			}
			$revisions->free();
			$out->addHTML( '</ul>' );
		} else {
			$out->addWikiMsg( 'nohistory' );
		}

		if ( $haveFiles ) {
			$out->addHTML( Xml::element( 'h2', null, $this->msg( 'filehist' )->text() ) . "\n" );
			$out->addHTML( '<ul>' );
			foreach ( $files as $row ) {
				$out->addHTML( $this->formatFileRow( $row ) );
			}
			$files->free();
			$out->addHTML( '</ul>' );
		}

		if ( $this->mAllowed ) {
			# Slip in the hidden controls here
			$misc = Html::hidden( 'target', $this->mTarget );
			$misc .= Html::hidden( 'wpEditToken', $this->getUser()->getEditToken() );
			$misc .= Xml::closeElement( 'form' );
			$out->addHTML( $misc );
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

		$link = Linker::linkKnown(
			$titleObj,
			htmlspecialchars( $time ),
			array(),
			array(
				'target' => $this->mTargetObj->getPrefixedText(),
				'timestamp' => $ts
			)
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
				/* $attribs */ array(), $titleObj
			);
		} else {
			$link = Linker::linkKnown(
				$titleObj,
				htmlspecialchars( $time ),
				array(),
				array(
					'target' => $this->mTargetObj->getPrefixedText(),
					'file' => $key,
					'token' => $user->getEditToken( $key )
				)
			);
		}
		// END CORE HACK

		if ( $file->isDeleted( File::DELETED_FILE ) ) {
			$link = '<span class="history-deleted">' . $link . '</span>';
		}

		return $link;
	}

	private function formatRevisionRow( $row, $earliestLiveTime, $remaining ) {
		$rev = Revision::newFromArchiveRow( $row,
			array(
				'title' => $this->mTargetObj
			) );

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
				$last = Linker::linkKnown(
					$titleObj,
					$this->msg( 'diff' )->escaped(),
					array(),
					array(
						'target' => $this->mTargetObj->getPrefixedText(),
						'timestamp' => $ts,
						'diff' => 'prev'
					)
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
		$attribs = array();
		list( $tagSummary, $classes ) = ChangeTags::formatSummaryRow( $row->ts_tags, 'deletedhistory' );
		if ( $classes ) {
			$attribs['class'] = implode( ' ', $classes );
		}

		// Revision delete links
		$revdlink = Linker::getRevDeleteLink( $user, $rev, $this->mTargetObj );

		$revisionRow = $this->msg( 'undelete-revision-row' )
			->rawParams(
				$checkBox,
				$revdlink,
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
		if ( $this->getConfig()->get( 'UploadMaintenance' ) && $this->mTargetObj->getNamespace() == NS_FILE ) {
			throw new ErrorPageError( 'undelete-error', 'filedelete-maintenance' );
		}

		if ( wfReadOnly() ) {
			throw new ReadOnlyError;
		}

		$out = $this->getOutput();
		$archive = new PageArchive( $this->mTargetObj, $this->getConfig() );
		Hooks::run( 'UndeleteForm::undelete', array( &$archive, $this->mTargetObj ) );
		$ok = $archive->undelete(
			$this->mTargetTimestamp,
			$this->mComment,
			$this->mFileVersions,
			$this->mUnsuppress,
			$this->getUser()
		);

		if ( is_array( $ok ) ) {
			if ( $ok[1] ) { // Undeleted file count
				Hooks::run( 'FileUndeleteComplete', array(
					$this->mTargetObj, $this->mFileVersions,
					$this->getUser(), $this->mComment ) );
			}

			$link = Linker::linkKnown( $this->mTargetObj );
			$out->addHTML( $this->msg( 'undeletedpage' )->rawParams( $link )->parse() );
		} else {
			$out->setPageTitle( $this->msg( 'undelete-error' ) );
		}

		// Show revision undeletion warnings and errors
		$status = $archive->getRevisionStatus();
		if ( $status && !$status->isGood() ) {
			$out->addWikiText( '<div class="error">' .
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
}