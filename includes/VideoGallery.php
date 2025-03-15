<?php
/**
 * Video gallery
 *
 * Add videos to the gallery using add(), then render that list to HTML using toHTML().
 */

use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;

class VideoGallery {
	/** @var array[] Pairs of Video objects and a HTML string */
	private array $mVideos = [];

	/** @var string|null */
	private $mCaption;

	private ?Title $contextTitle = null;

	private bool $mShowFilename = true;

	/** @var int How many videos wide should the gallery be? */
	private int $mPerRow = 3;

	private int $mWidths = 200;
	private int $mHeights = 200; // How wide/tall each thumbnail should be

	/**
	 * Get the caption (as plain text)
	 */
	public function getCaption(): string {
		return $this->mCaption ?? '';
	}

	/**
	 * Set the caption (as plain text)
	 *
	 * @param string $plainText
	 */
	public function setCaption( string $plainText ) {
		$this->mCaption = htmlspecialchars( $plainText );
	}

	/**
	 * Set the caption (as HTML)
	 */
	public function setCaptionHtml( string $html ) {
		$this->mCaption = $html;
	}

	/**
	 * Set how many videos will be displayed per row.
	 *
	 * @param int $num > 0; invalid numbers will be rejected
	 */
	public function setPerRow( int $num ): void {
		if ( $num > 0 ) {
			$this->mPerRow = $num;
		}
	}

	/**
	 * Set how wide each video will be, in pixels.
	 *
	 * @param int $num > 0; invalid numbers will be ignored
	 */
	public function setWidths( int $num ): void {
		if ( $num > 0 ) {
			$this->mWidths = $num;
		}
	}

	/**
	 * Set how high each video will be, in pixels.
	 *
	 * @param int $num > 0; invalid numbers will be ignored
	 */
	public function setHeights( int $num ): void {
		if ( $num > 0 ) {
			$this->mHeights = $num;
		}
	}

	/**
	 * Add a video to the gallery.
	 *
	 * @param Video $video object that is added to the gallery
	 * @param string $html additional HTML text to be shown. The name and size of the video are always shown.
	 */
	public function add( $video, $html = '' ): void {
		$this->mVideos[] = [ &$video, $html ];
		wfDebug( __METHOD__ . ':' . $video->getName() . "\n" );
	}

	/**
	 * Add a video at the beginning of the gallery.
	 *
	 * @param Video $video object that is added to the gallery
	 * @param string $html Additional HTML text to be shown. The name and size of the video are always shown.
	 */
	public function insert( $video, $html = '' ): void {
		array_unshift( $this->mVideos, [ &$video, $html ] );
	}

	/**
	 * @return bool True if the gallery contains no videos
	 */
	public function isEmpty(): bool {
		return $this->mVideos === [];
	}

	/**
	 * Enable/Disable showing of the filename of a video in the gallery.
	 * Enabled by default.
	 *
	 * @param bool $f Set to false to disable.
	 */
	public function setShowFilename( bool $f ): void {
		$this->mShowFilename = $f;
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
	public function toHTML(): string {
		$lang = RequestContext::getMain()->getLanguage();
		$linkRenderer = MediaWikiServices::getInstance()->getLinkRenderer();

		$s = '<table class="gallery" cellspacing="0" cellpadding="0">';
		if ( $this->getCaption() ) {
			$s .= "\n\t<caption>{$this->mCaption}</caption>";
		}

		$i = 0;
		foreach ( $this->mVideos as $pair ) {
			/** @var Video $video */
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
				$thumbhtml = "\n\t\t\t" . '<div class="thumb" style="padding: ' . $vpad
					. 'px 0; width: ' . ( $this->mWidths + 30 ) . 'px;">'
					. $video->getEmbedCode() . '</div>';
			}

			$nb = '';

			$textlink = $this->mShowFilename ?
					$linkRenderer->makeKnownLink( $nt,
						$lang->truncateForVisual(
							$nt->getText(), 30, '...'
						)
					) . "<br />\n"
				: '';

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
	public function count(): int {
		return count( $this->mVideos );
	}

	/**
	 * Set the contextual title
	 *
	 * @param Title $title Contextual title
	 */
	public function setContextTitle( Title $title ) {
		$this->contextTitle = $title;
	}

	/**
	 * Get the contextual title, if applicable
	 */
	public function getContextTitle(): ?Title {
		return $this->contextTitle;
	}

}
