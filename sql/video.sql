-- Table that contains information about the deleted videos
CREATE TABLE /*_*/oldvideo (
  `ov_name` varchar(255) NOT NULL default '',
  `ov_archive_name` varchar(255) NOT NULL default '',
  `ov_url` varchar(255) NOT NULL default '',
  `ov_type` varchar(255) default 'unknown',
  `ov_actor` bigint unsigned NOT NULL,
  `ov_timestamp` varchar(14) NOT NULL default ''
)/*$wgDBTableOptions*/;

CREATE INDEX /*i*/ov_name ON /*_*/oldvideo (ov_name);
CREATE INDEX /*i*/ov_timestamp ON /*_*/oldvideo (ov_timestamp);

-- Table that contains information about all the active videos
CREATE TABLE /*_*/video (
  `video_name` varchar(255) NOT NULL PRIMARY KEY default '',
  `video_url` varchar(255) NOT NULL default '',
  `video_type` varchar(255) default 'unknown',
  `video_actor` bigint unsigned NOT NULL,
  `video_timestamp` varchar(14) NOT NULL default ''
)/*$wgDBTableOptions*/;

CREATE INDEX /*i*/video_timestamp ON /*_*/video (video_timestamp);