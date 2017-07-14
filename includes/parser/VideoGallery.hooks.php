<?php
/**
 * <videogallery> parser hook tag
 *
 * @file
 * @ingroup Extensions
 */

class VideoGalleryHooks {

	public static function onParserFirstCallInit( &$parser ) {
		$parser->setHook( 'videogallery', array( __CLASS__, 'renderVideoGallery' ) );
		return true;
	}

	public static function renderVideoGallery( $input, $argv, $parser ) {
		$vg = new VideoGallery();
		$vg->setContextTitle( $parser->getTitle()->getText() );
		$vg->setShowFilename( true );
		$vg->setParsing();

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
			$matches = array();
			preg_match( "/^([^|]+)(\\|(.*))?$/", $line, $matches );

			// Skip empty lines
			if ( count( $matches ) == 0 ) {
				continue;
			}

			$tp = Title::newFromText( $matches[1] );
			$nt =& $tp;
			if ( is_null( $nt ) ) {
				// Bogus title. Ignore these so we don't bomb out later.
				continue;
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
