-- Table that contains information about all the active videos
CREATE TABLE /*_*/video (
  `video_name` varchar(255) NOT NULL PRIMARY KEY default '',
  `video_url` varchar(255) NOT NULL default '',
  `video_type` varchar(255) default 'unknown',
  `video_actor` bigint unsigned NOT NULL,
  `video_timestamp` varchar(14) NOT NULL default ''
)/*$wgDBTableOptions*/;

CREATE INDEX /*i*/video_timestamp ON /*_*/video (video_timestamp);