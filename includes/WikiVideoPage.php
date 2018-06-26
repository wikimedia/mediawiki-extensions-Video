<?php

class WikiVideoPage extends WikiPage {
	public function getActionOverrides() {
		return [ 'revert' => 'RevertVideoAction' ];
	}

}
