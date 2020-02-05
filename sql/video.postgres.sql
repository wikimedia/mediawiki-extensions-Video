CREATE TABLE video (
  video_name TEXT NOT NULL PRIMARY KEY default '',
  video_url TEXT NOT NULL default '',
  video_type TEXT default 'unknown',
  video_actor INTEGER NOT NULL,
  video_timestamp TIMESTAMPTZ NOT NULL default NOW()
);

CREATE INDEX video_timestamp ON video (video_timestamp);