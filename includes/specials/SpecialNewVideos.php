<?php
/**
 * Special:NewVideos - a special page for showing recently added videos
 * Reuses various bits and pieces from SpecialNewimages.php
 *
 * @file
 * @ingroup Extensions
 */

class NewVideos extends IncludableSpecialPage {
	/** @var FormOptions */
	protected $opts;

	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct( 'NewVideos' );
	}

	/**
	 * Group this special page under the correct header in Special:SpecialPages.
	 *
	 * @return string
	 */
	public function getGroupName() {
		return 'changes';
	}

	/**
	 * Show the special page
	 *
	 * @param mixed|null $par Parameter passed to the page or null
	 */
	public function execute( $par ) {
		$context = new DerivativeContext( $this->getContext() );

		$out = $this->getOutput();
		$lang = $this->getLanguage();

		$out->setPageTitle( $this->msg( 'newvideos' ) );

		$opts = new FormOptions();

		$opts->add( 'wpIlMatch', '' ); // Known as 'like', but uses old name for back-compat
		$opts->add( 'user', '' );
		$opts->add( 'hidebots', true );
		$opts->add( 'newbies', false );
		$opts->add( 'hidepatrolled', false );
		$opts->add( 'limit', 48 ); // Back-compat, old value has always been 48
		$opts->add( 'offset', '' );
		$opts->add( 'start', '' );
		$opts->add( 'end', '' );

		$opts->fetchValuesFromRequest( $this->getRequest() );

		if ( $par !== null ) {
			$opts->setValue( is_numeric( $par ) ? 'limit' : 'wpIlMatch', $par );
		}

		$data = [ 'hidebots' => 1 ];

		// If start date comes after end date chronologically, swap them.
		// They are swapped in the interface by JS.
		$start = $opts->getValue( 'start' );
		$end = $opts->getValue( 'end' );
		if ( $start !== '' && $end !== '' && $start > $end ) {
			$temp = $end;
			$end = $start;
			$start = $temp;

			$opts->setValue( 'start', $start, true );
			$opts->setValue( 'end', $end, true );

			// Since the request values must always be adjusted for backwards compatibility with the
			// previous parameter mix, parameter values are added to $data here and the request
			// re-creation is moved outside this block
			$data['start'] = $start;
			$data['end'] = $end;
		}

		// Swap values in request object, which is used by HTMLForm to pre-populate the fields with
		// the previous input. Done every request to maintain backwards parameter compatibility
		$request = $context->getRequest();
		$context->setRequest( new DerivativeRequest(
			$request,
			$data + $request->getValues(),
			$request->wasPosted()
		) );

		$opts->validateIntBounds( 'limit', 0, 500 );

		$this->opts = $opts;

		$pager = new NewVideosPager( $context, $opts );
		// Store html output for a moment so the toptext can be shown first.
		// A workaround, as the number of videos isn't available before calling getBody(),
		// but the toptext must be shown before the gallery
		$pagerBody = $pager->getBody();

		if ( !$this->including() ) {
			$lt = $lang->formatNum( min( $pager->getShownVideosCount(), $opts->getValue( 'limit' ) ) );
			$this->setTopText( $lt );
			$this->buildForm( $context );
		}

		$out->addHTML( $pagerBody );
		if ( !$this->including() ) {
			$out->addHTML( $pager->getNavigationBar() );
		}
	}

	protected function buildForm( IContextSource $context ) {
		$formDescriptor = [
			'wpIlMatch' => [
				'type' => 'text',
				'label-message' => 'newimages-label',
				'name' => 'wpIlMatch',
			],
			'user' => [
				'type' => 'text',
				'label-message' => 'newimages-user',
				'name' => 'user',
			],
			'newbies' => [
				'type' => 'check',
				'label-message' => 'newimages-newbies',
				'name' => 'newbies',
			],
			'hidebots' => [
				'type' => 'check',
				'label-message' => 'video-hidebots',
				'default' => $this->opts->getValue( 'hidebots' ),
				'name' => 'hidebots',
			],
			'hidepatrolled' => [
				'type' => 'check',
				'label-message' => 'newimages-hidepatrolled',
				'name' => 'hidepatrolled',
			],
			'limit' => [
				'type' => 'hidden',
				'default' => $this->opts->getValue( 'limit' ),
				'name' => 'limit',
			],
			'offset' => [
				'type' => 'hidden',
				'default' => $this->opts->getValue( 'offset' ),
				'name' => 'offset',
			],
			'start' => [
				'type' => 'date',
				'label-message' => 'date-range-from',
				'name' => 'start',
			],
			'end' => [
				'type' => 'date',
				'label-message' => 'date-range-to',
				'name' => 'end',
			],
		];

		if ( $this->getConfig()->get( 'MiserMode' ) ) {
			unset( $formDescriptor['wpIlMatch'] );
		}

		if ( !$this->getUser()->useFilePatrol() ) {
			unset( $formDescriptor['hidepatrolled'] );
		}

		HTMLForm::factory( 'ooui', $formDescriptor, $context )
			// For the 'multiselect' field values to be preserved on submit
			->setFormIdentifier( 'specialnewvideos' )
			->setWrapperLegendMsg( 'newimages-legend' )
			->setSubmitTextMsg( 'ilsubmit' )
			->setMethod( 'get' )
			->prepareForm()
			->displayForm( false );
	}

	/**
	 * Send the text to be displayed above the options
	 *
	 * @param string $lt number of videos displayed
	 */
	public function setTopText( $lt ) {
		global $wgContLang;

		$message = $this->msg( 'video-newvideos-list-text', $lt )->inContentLanguage();
		if ( !$message->isDisabled() ) {
			$this->getOutput()->addWikiText(
				Html::rawElement( 'p',
					[ 'lang' => $wgContLang->getHtmlCode(), 'dir' => $wgContLang->getDir() ],
					"\n" . $message->parse() . "\n"
				),
				/* $lineStart */ false,
				/* $interface */ false
			);
		}
	}
}
