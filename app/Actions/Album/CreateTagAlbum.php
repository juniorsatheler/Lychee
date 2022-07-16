<?php

namespace App\Actions\Album;

use App\Auth\Authorization;
use App\Exceptions\ModelDBException;
use App\Models\TagAlbum;

class CreateTagAlbum extends Action
{
	/**
	 * Create a new smart album based on tags.
	 *
	 * @param string   $title
	 * @param string[] $show_tags
	 *
	 * @return TagAlbum
	 *
	 * @throws ModelDBException
	 */
	public function create(string $title, array $show_tags): TagAlbum
	{
		$album = new TagAlbum();
		$album->title = $title;
		$album->show_tags = $show_tags;
		$album->owner_id = Authorization::idOrFail();
		$album->save();

		return $album;
	}
}
