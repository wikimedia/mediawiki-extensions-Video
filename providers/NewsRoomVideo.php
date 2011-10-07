<?php

class NewsRoomVideo extends FlashVideo {

	public function __construct( &$video ) {
		parent::__construct( $video );
		$this->video->width = 300;
		$this->video->height = 325;
		$this->video->ratio = 300 / 325;
	}

}