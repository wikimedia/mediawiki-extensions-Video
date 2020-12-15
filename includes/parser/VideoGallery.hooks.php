<?php
/**
 * <videogallery> parser hook tag
 *
 * @file
 * @ingroup Extensions
 */

class VideoGalleryHooks {

	/**
	 * @param Parser $parser
	 */
	public static function onParserFirstCallInit( $parser ) {
		$parser->setHook( 'videogallery', [ __CLASS__, 'renderVideoGallery' ] );
	}

	/**
	 * @param string $input
	 * @param string[] $argv
	 * @param Parser $parser
	 * @return string
	 */
	public static function renderVideoGallery( $input, $argv, $parser ) {
		$vg = new VideoGallery();
		$vg->setContextTitle( $parser->getTitle() );
		$vg->setShowFilename( true );

		if ( isset( $argv['perrow'] ) ) {
			$vg->setPerRow( $argv['perrow'] );
		}
		if ( isset( $params['widths'] ) ) {
			$vg->setWidths( $argv['widths'] );
		}
		if ( isset( $params['heights'] ) ) {
			$vg->setHeights( $argv['heights'] );
		}

		$lines = explode( "\n", $input );

		foreach ( $lines as $line ) {
			// match lines like these:
			// Video:Some video name|This is some video
			$matches = [];
			preg_match( "/^([^|]+)(\\|(.*))?$/", $line, $matches );

			// Skip empty lines
			if ( count( $matches ) == 0 ) {
				continue;
			}

			$tp = Title::newFromText( $matches[1] );
			$nt =& $tp;
			// exists() checks to see if there is such a page
			// i.e. "Sara Bareilles - Brave" _is_ a valid page title for both NS_MAIN and NS_VIDEO,
			// but if no NS was specified, we should assume the NS is NS_VIDEO and not NS_MAIN
			if ( !$nt || !$nt->exists() ) {
				// Maybe the user supplied a NS_VIDEO page name *without* the namespace?
				// Try that first before bailing out.
				$nt = Title::makeTitleSafe( NS_VIDEO, $matches[1] );
				if ( !$nt ) {
					// Bogus title. Ignore these so we don't bomb out later.
					continue;
				}
			}

			if ( isset( $matches[3] ) ) {
				$label = $matches[3];
			} else {
				$label = '';
			}

			$html = '';
			/*
			$pout = $this->parse( $label,
				$this->mTitle,
				$this->mOptions,
				false, // Strip whitespace...?
				false  // Don't clear state!
			);
			$html = $pout->getText();
			*/

			// Gah, there should be a better way to get context here
			$vg->add( new Video( $nt, RequestContext::getMain() ), $html );

		}
		return $vg->toHTML();
	}
}
