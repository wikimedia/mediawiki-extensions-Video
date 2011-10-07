<?php

class MySpaceVideo extends FlashVideo {

	var $video,
		$id,
		$url;

	public function __construct( $video ) {
		if( !is_object( $video ) ) {
			throw new MWException( 'MySpace constructor given bogus video object.' );
		}
		$this->video =& $video;
		$this->video->ratio = 430/346;
		$this->id = $this->extractID( $this->video->getURL() );
		$this->url = "http://lads.myspace.com/videos/vplayer.swf?m={$this->id}&v=2&type=video";
		return $this;
	}

	private function extractID() {
		$url = $this->video->getURL();
		//http://myspacetv.com/index.cfm?fuseaction=vids.individual&videoid=1388509
		//http://lads.myspace.com/videos/vplayer.swf?m=1505336&v=2&type=video
		$id = preg_replace( "%http\:\/\/(vids\.|www\.)?myspace(tv)?\.com/index\.cfm\?fuseaction=vids\.individual&VideoID=%i", '', $url );
		$id = preg_replace( "%http\:\/\/(vids\.|www\.|lads\.)?myspace(tv)?\.com\/videos\/vplayer\.swf\?m=%i", '', $id );
		//$id = preg_replace( "%&v=2&type=video%i", '', $id );
		return $id;
	}

}