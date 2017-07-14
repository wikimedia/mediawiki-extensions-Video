<?php
/**
 * Video gallery
 *
 * Add videos to the gallery using add(), then render that list to HTML using toHTML().
 */

class VideoGallery {
	public $mVideos, $mShowFilename;

	/**
	 * Is the gallery on a wiki page (i.e. not a special page)
	 */
	public $mParsing;

	private $mPerRow = 3; // How many videos wide should the gallery be?
	private $mWidths = 200, $mHeights = 200; // How wide/tall each thumbnail should be

	/**
	 * Create a new video gallery object.
	 */
	function __construct() {
		$this->mVideos = array();
		$this->mShowFilename = true;
		$this->mParsing = false;
	}

	/**
	 * Set the "parse" bit so we know to hide "bad" videos
	 */
	function setParsing( $val = true ) {
		$this->mParsing = $val;
	}

	/**
	 * Get the caption (as plain text)
	 */
	function getCaption() {
		return ( isset( $this->mCaption ) ) ? $this->mCaption : '';
	}

	/**
	 * Set the caption (as plain text)
	 *
	 * @param string $caption Caption
	 */
	function setCaption( $caption ) {
		$this->mCaption = htmlspecialchars( $caption );
	}

	/**
	 * Set the caption (as HTML)
	 *
	 * @param string $caption Caption
	 */
	public function setCaptionHtml( $caption ) {
		$this->mCaption = $caption;
	}

	/**
	 * Set how many videos will be displayed per row.
	 *
	 * @param int $num > 0; invalid numbers will be rejected
	 */
	public function setPerRow( $num ) {
		if ( $num > 0 ) {
			$this->mPerRow = (int)$num;
		}
	}

	/**
	 * Set how wide each video will be, in pixels.
	 *
	 * @param int $num > 0; invalid numbers will be ignored
	 */
	public function setWidths( $num ) {
		if ( $num > 0 ) {
			$this->mWidths = (int)$num;
		}
	}

	/**
	 * Set how high each video will be, in pixels.
	 *
	 * @param int $num > 0; invalid numbers will be ignored
	 */
	public function setHeights( $num ) {
		if ( $num > 0 ) {
			$this->mHeights = (int)$num;
		}
	}

	/**
	 * Add a video to the gallery.
	 *
	 * @param $video Video object that is added to the gallery
	 * @param $html String: additional HTML text to be shown. The name and size of the video are always shown.
	 */
	function add( $video, $html = '' ) {
		$this->mVideos[] = array( &$video, $html );
		wfDebug( __METHOD__ . ':' . $video->getName() . "\n" );
	}

	/**
 	 * Add a video at the beginning of the gallery.
 	 *
 	 * @param $video Video object that is added to the gallery
 	 * @param $html String: Additional HTML text to be shown. The name and size of the video are always shown.
 	 */
	function insert( $video, $html = '' ) {
		array_unshift( $this->mVideos, array( &$video, $html ) );
	}

	/**
	 * @return bool True if the gallery contains no videos
	 */
	function isEmpty() {
		return empty( $this->mVideos );
	}

	/**
	 * Enable/Disable showing of the filename of a video in the gallery.
	 * Enabled by default.
	 *
	 * @param bool $f Set to false to disable.
	 */
	function setShowFilename( $f ) {
		$this->mShowFilename = ( $f == true );
	}

	/**
	 * Return a HTML representation of the video gallery
	 *
	 * For each video in the gallery, display
	 * - a thumbnail
	 * - the video name
	 * - the additional text provided when adding the video
	 * - the size of the video
	 */
	function toHTML() {
		global $wgLang;

		$s = '<table class="gallery" cellspacing="0" cellpadding="0">';
		if ( $this->getCaption() ) {
			$s .= "\n\t<caption>{$this->mCaption}</caption>";
		}

		$i = 0;
		foreach ( $this->mVideos as $pair ) {
			$video =& $pair[0];
			$text = $pair[1];

			$nt = $video->getTitle();

			if ( $nt->getNamespace() != NS_VIDEO ) {
				# We're dealing with a non-video, spit out the name and be done with it.
				$thumbhtml = "\n\t\t\t" . '<div style="height: ' . ( $this->mHeights * 1.25 + 2 ) . 'px;">'
					. htmlspecialchars( $nt->getText() ) . '</div>';
 			} else {
				$video->load(); // Just in case to ensure that all the fields we need are populated, etc.
				$video->setWidth( $this->mWidths );
				$video->setHeight( $this->mHeights );
				$vpad = floor( ( 1.25 * $this->mHeights - $this->mWidths ) / 2 ) - 2;
				$thumbhtml = "\n\t\t\t" . '<div class="thumb" style="padding: ' . $vpad . 'px 0; width: ' . ( $this->mWidths + 30 ) . 'px;">'
					. $video->getEmbedCode() . '</div>';
			}

			$nb = '';

			$textlink = $this->mShowFilename ?
				Linker::linkKnown( $nt, htmlspecialchars( $wgLang->truncate( $nt->getText(), 30, '...' ) ) ) . "<br />\n" :
				'';

			# ATTENTION: The newline after <div class="gallerytext"> is needed to accommodate htmltidy which
			# in version 4.8.6 generated crackpot html in its absence, see:
			# http://bugzilla.wikimedia.org/show_bug.cgi?id=1765 -Ævar

			if ( $i % $this->mPerRow == 0 ) {
				$s .= "\n\t<tr>";
			}
			$s .=
				"\n\t\t" . '<td><div class="gallerybox" style="width: ' . ( $this->mWidths * 1.25 ) . 'px;">'
					. $thumbhtml
					. "\n\t\t\t" . '<div class="gallerytext">' . "\n"
						. $textlink . $text . $nb
					. "\n\t\t\t</div>"
				. "\n\t\t</div></td>";
			if ( $i % $this->mPerRow == $this->mPerRow - 1 ) {
				$s .= "\n\t</tr>";
			}
			++$i;
		}
		if ( $i % $this->mPerRow != 0 ) {
			$s .= "\n\t</tr>";
		}
		$s .= "\n</table>";

		return $s;
	}

	/**
	 * @return int Number of videos in the gallery
	 */
	public function count() {
		return count( $this->mVideos );
	}

	/**
	 * Set the contextual title
	 *
	 * @param Title $title Contextual title
	 */
	public function setContextTitle( $title ) {
		$this->contextTitle = $title;
	}

	/**
	 * Get the contextual title, if applicable
	 *
	 * @return mixed Title or false
	 */
	public function getContextTitle() {
		return is_object( $this->contextTitle ) && $this->contextTitle instanceof Title
				? $this->contextTitle
				: false;
	}

} //class
