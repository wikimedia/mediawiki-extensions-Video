<?php
/**
 * Deleted video in the 'oldvideo' table.
 *
 * Based on MW 1.23's ArchivedFile.php.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @file
 * @ingroup FileAbstraction
 * @date 22 September 2014
 */

/**
 * Class representing a row of the 'oldvideo' table
 *
 * @ingroup FileAbstraction
 */
class ArchivedVideo extends ArchivedFile {
	/** @var int filearchive row ID */
	private $id;

	/** @var string Video name */
	private $name;

	/** @var string FileStore storage group */
	private $group;

	/** @var string FileStore SHA-1 key */
	private $key;

	/** @var int File size in bytes */
	private $size;

	/** @var int size in bytes */
	private $bits;

	/** @var int Width */
	private $width;

	/** @var int Height */
	private $height;

	/** @var string Metadata string */
	private $metadata;

	/** @var string MIME type */
	private $mime;

	/** @var string Media type */
	private $media_type;

	/** @var string Upload description */
	private $description;

	/** @var int Actor ID of uploader */
	private $actor;

	/** @var string Time of upload */
	private $timestamp;

	/** @var bool Whether or not all this has been loaded from the database (loadFromXxx) */
	private $dataLoaded;

	/** @var int Bitfield akin to rev_deleted */
	private $deleted;

	/** @var string SHA-1 hash of file content */
	private $sha1;

	/** @var string Number of pages of a multipage document, or false for
	 * documents which aren't multipage documents
	 */
	private $pageCount;

	/** @var string Original base filename */
	private $archive_name;

	/** @var MediaHandler */
	protected $handler;

	/** @var Title */
	protected $title; # video title

	/** @var string Video URL */
	protected $url;

	/**
	 * @throws MWException
	 * @param Title $title
	 * @param int $id
	 * @param string $key
	 */
	function __construct( $title, $id = 0, $key = '' ) {
		$this->id = -1;
		$this->title = false;
		$this->name = false;
		$this->group = 'deleted'; // needed for direct use of constructor
		$this->key = '';
		$this->size = 0;
		$this->bits = 0;
		$this->width = 0;
		$this->height = 0;
		$this->metadata = '';
		$this->mime = 'unknown/unknown';
		$this->media_type = '';
		$this->description = '';
		$this->actor = 0;
		$this->timestamp = null;
		$this->deleted = 0;
		$this->dataLoaded = false;
		$this->exists = false;
		$this->sha1 = '';
		$this->url = '';

		if ( $title instanceof Title ) {
			$this->title = Title::makeTitleSafe( NS_VIDEO, $title->getDBkey() );
			$this->name = $title->getDBkey();
		} else {
			// Convert strings to Title objects
			$this->title = Title::makeTitleSafe( NS_FILE, (string)$title );
			$this->name = $title->getDBkey();
		}

		/*
		if ( $id ) {
			$this->id = $id;
		}

		if ( $key ) {
			$this->key = $key;
		}
		*/

		if ( !$id && !$key && !( $title instanceof Title ) ) {
			throw new MWException( 'No specifications provided to ArchivedVideo constructor.' );
		}
	}

	/**
	 * Loads a video object from the oldvideo table
	 * @throws MWException
	 * @return bool|null True on success or null
	 */
	public function load() {
		if ( $this->dataLoaded ) {
			return true;
		}
		$conds = [];

		/**
		if ( $this->id > 0 ) {
			$conds['fa_id'] = $this->id;
		}
		if ( $this->key ) {
			$conds['fa_storage_group'] = $this->group;
			$conds['fa_storage_key'] = $this->key;
		}
		**/
		if ( $this->title ) {
			$conds['ov_name'] = $this->title->getDBkey();
		}

		if ( !count( $conds ) ) {
			throw new MWException( 'No specific information for retrieving archived video' );
		}

		if ( !$this->title || $this->title->getNamespace() == NS_VIDEO ) {
			$this->dataLoaded = true; // set it here, to have also true on miss
			$dbr = wfGetDB( DB_REPLICA );
			$row = $dbr->selectRow(
				'oldvideo',
				self::selectFields(),
				$conds,
				__METHOD__,
				[ 'ORDER BY' => 'ov_timestamp DESC' ]
			);
			if ( !$row ) {
				// this revision does not exist?
				return null;
			}

			// initialize fields for filestore video object
			$this->loadFromRow( $row );
		} else {
			throw new MWException( 'This title does not correspond to a video page.' );
		}
		$this->exists = true;

		return true;
	}

	/**
	 * Loads a video object from the oldvideo table
	 *
	 * @param stdClass $row
	 * @return ArchivedFile
	 */
	public static function newFromRow( $row ) {
		$video = new ArchivedVideo( Title::makeTitle( NS_VIDEO, $row->ov_name ) );
		$video->loadFromRow( $row );

		return $video;
	}

	/**
	 * Fields in the oldvideo table
	 * @return array
	 */
	static function selectFields() {
		return [
			'ov_name',
			'ov_archive_name',
			'ov_url',
			'ov_type',
			'ov_actor',
			'ov_timestamp'
		];
	}

	/**
	 * Load ArchivedVideo object fields from a DB row.
	 *
	 * @param stdClass $row Object database row
	 */
	public function loadFromRow( $row ) {
		//$this->id = intval( $row->fa_id );
		$this->name = $row->ov_name;
		$this->archive_name = $row->ov_archive_name;
		/**
		$this->group = $row->fa_storage_group;
		$this->key = $row->fa_storage_key;
		$this->size = $row->fa_size;
		$this->bits = $row->fa_bits;
		$this->width = $row->fa_width;
		$this->height = $row->fa_height;
		$this->metadata = $row->fa_metadata;
		**/
		$this->mime = 'video/x-flv'; // @todo FIXME/CHECKME: is hard-coding the minor MIME type like this OK?
		$this->media_type = 'VIDEO';
		//$this->description = $row->fa_description;
		$this->actor = $row->ov_actor;
		$this->timestamp = $row->ov_timestamp;
		$this->url = $row->ov_url;
		/**
		$this->deleted = $row->fa_deleted;
		if ( isset( $row->fa_sha1 ) ) {
			$this->sha1 = $row->fa_sha1;
		} else {
			// old row, populate from key
			$this->sha1 = LocalRepo::getHashFromKey( $this->key );
		}
		**/
	}

	/**
	 * Return the video URL
	 * This is a custom method
	 * @return string
	 */
	public function getURL() {
		$this->load();

		return $this->url;
	}

	/**
	 * Return the actor ID of the uploader.
	 * This is a custom method
	 *
	 * @return int
	 */
	public function getRawActor() {
		$this->load();

		return $this->actor;
	}

	/** Getters mostly inherited from the parent class **/

	/**
	 * Return the FileStore key
	 * @return string
	 */
	public function getKey() {
		$this->load();

		return $this->key;
	}

	/**
	 * Return the FileStore key (overriding base File class)
	 * @return string
	 */
	public function getStorageKey() {
		return $this->getKey();
	}

	/**
	 * Return the FileStore storage group
	 * @return string
	 */
	public function getGroup() {
		return $this->group;
	}

	/**
	 * Return the width of the image
	 * @return int
	 */
	public function getWidth() {
		$this->load();

		return $this->width;
	}

	/**
	 * Return the height of the image
	 * @return int
	 */
	public function getHeight() {
		$this->load();

		return $this->height;
	}

	/**
	 * Get handler-specific metadata
	 * @return string
	 */
	public function getMetadata() {
		$this->load();

		return $this->metadata;
	}

	/**
	 * Return the size of the image file, in bytes
	 * @return int
	 */
	public function getSize() {
		$this->load();

		return $this->size;
	}

	/**
	 * Return the bits of the image file, in bytes
	 * @return int
	 */
	public function getBits() {
		$this->load();

		return $this->bits;
	}

	/**
	 * Returns the mime type of the file.
	 * @return string
	 */
	public function getMimeType() {
		$this->load();

		return $this->mime;
	}

	/**
	 * @return bool False for documents which aren't multipage documents
	 */
	function pageCount() {
		return false;
	}

	/**
	 * Return the type of the media in the file.
	 * Use the value returned by this function with the MEDIATYPE_xxx constants.
	 * @return string
	 */
	public function getMediaType() {
		$this->load();

		return 'VIDEO';
	}

	/**
	 * Get the SHA-1 base 36 hash of the file
	 *
	 * @return string
	 */
	function getSha1() {
		$this->load();

		return $this->sha1;
	}
}
