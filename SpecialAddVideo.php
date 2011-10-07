<?php

class AddVideo extends SpecialPage {

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
		if( !$wgUser->isAllowed( 'addvideo' ) ) {
			$wgOut->permissionRequired( 'addvideo' );
			return;
		}

		// Show a message if the database is in read-only mode
		if ( wfReadOnly() ) {
			$wgOut->readOnlyPage();
			return;
		}

		// If user is blocked, s/he doesn't need to access this page
		if( $wgUser->isBlocked() ) {
			$wgOut->blockedPage( false );
			return false;
		}

		$categories = $wgRequest->getVal( 'wpCategories' );

		// wpDestName URL parameter is set in VideoPage.php; when viewing a
		// video page of a video that does not yet exist, there is a link to
		// Special:AddVideo and the wpDestName parameter will be set to the
		// name of the video
		$destination = $wgRequest->getVal( 'destName' );
		if( !$destination ) {
			$destination = $wgRequest->getVal( 'wpDestName' );
		}

		// Posted items
		$video_code = $wgRequest->getVal( 'wpVideo' );
		$title = str_replace( '#', '', $wgRequest->getVal( 'wpTitle' ) );

		$pageTitle = wfMsg( 'video-addvideo-title' );
		if( $destination ) {
			$pageTitle = wfMsg( 'video-addvideo-dest', str_replace( '_', ' ', $destination ) );
		}

		$wgOut->setPageTitle( $pageTitle );

		if( $destination ) {
			$title = $destination;
		}

		$output = '<div class="add-video-container">
		<form name="videoadd" action="" method="post">';

		$output .= '<p class="addvideo-subtitle">' .
			wfMsgExt( 'video-addvideo-instructions', 'parse' ) . '</p>';
		$output .= '<table border="0" cellpadding="3" cellspacing="5">';
		$output .= '<tr>';

		// If we're not adding a new version of a pre-existing video, allow the
		// user to supply the video's title, obviously...
		if( !$destination ) {
			$output .= '<td><label for="wpTitle">' .
				wfMsgHtml( 'video-addvideo-title-label' ) .
				'</label></td><td>';

			$output .= Xml::element( 'input',
				array(
					'type' => 'text',
					'name' => 'wpTitle',
					'size' => '30',
					'value' => $wgRequest->getVal( 'wpTitle' ),
				)
			);
			$output .= '</td></tr>';
		}

		$watchChecked = $wgUser->getOption( 'watchdefault' )
			? ' checked="checked"'
			: '';

		$output .= '<tr>
			<td valign="top">' . wfMsg( 'video-addvideo-embed-label' ) . '</td>
			<td><textarea rows="5" cols="65" name="wpVideo" id="wpVideo">' .
				$wgRequest->getVal( 'wpVideo' ) .
			'</textarea></td>
		</tr>
		<tr>
			<td></td>
			<td>
				<input type="checkbox" name="wpWatchthis" id="wpWatchthis"' . $watchChecked . ' value="true" />
				<label for="wpWatchthis">' . wfMsgHtml( 'watchthisupload' ) . '</label>

			</td>
	</tr>';

		$output .= '<tr>
				<td></td>
				<td>';
				$output .= Xml::element( 'input',
					array(
						'type' => 'button',
						'value' => wfMsg( 'video-addvideo-button' ),
						'onclick' => 'document.videoadd.submit();',
					)
				);
				$output .= '</td>
			</tr>
			</table>
		</form>
		</div>';

		if( $wgRequest->wasPosted() ) {
			$video = Video::newFromName( $title );

			// Page title for Video has already been taken
			if( $video->exists() && !$destination ) {
				$error = '<div class="video-error">' .
					wfMsgHtml( 'video-addvideo-exists' ) . '</div>';
				$wgOut->addHTML( $error );
			} else {
				// Get URL based on user input
				// It could be a straight URL to the page or the embed code
				if ( $video->isURL( $video_code ) ) {
					$url = $video_code;
				} else {
					$urlFromEmbed = $video->getURLfromEmbedCode( $video_code );
					if ( $video->isURL( $urlFromEmbed ) ) {
						$url = $urlFromEmbed;
					}
				}
				$provider = $video->getProviderByURL( $url );
				if( !$url || $provider == 'unknown' ) {
					$error = '<div class="video-error">' .
						wfMsg( 'video-addvideo-invalidcode' ) . '</div>';
					$wgOut->addHTML( $error );
				} else {
					$video->addVideo(
						$url, $provider, $categories,
						$wgRequest->getVal( 'wpWatchthis' )
					);
					$wgOut->redirect( $video->title->getFullURL() );
				}
			}
		}
		$wgOut->addHTML( $output );
	}
}