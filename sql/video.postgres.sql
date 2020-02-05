CREATE TABLE oldvideo (
  ov_name TEXT NOT NULL default '',
  ov_archive_name TEXT NOT NULL default '',
  ov_url TEXT NOT NULL default '',
  ov_type TEXT default 'unknown',
  ov_actor INTEGER NOT NULL,
  ov_timestamp TIMESTAMPTZ NOT NULL default NOW()
);

CREATE INDEX ov_name ON oldvideo (ov_name);
CREATE INDEX ov_timestamp ON oldvideo (ov_timestamp);

CREATE TABLE video (
  video_name TEXT NOT NULL PRIMARY KEY default '',
  video_url TEXT NOT NULL default '',
  video_type TEXT default 'unknown',
  video_actor INTEGER NOT NULL,
  video_timestamp TIMESTAMPTZ NOT NULL default NOW()
);

CREATE INDEX video_timestamp ON video (video_timestamp);