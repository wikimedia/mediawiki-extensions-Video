<?php

class WikiVideoPage extends WikiPage {

	/** @inheritDoc */
	public function getActionOverrides() {
		return [ 'revert' => 'RevertVideoAction' ];
	}

}
