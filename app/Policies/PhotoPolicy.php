<?php

namespace App\Policies;

use App\Exceptions\Internal\FrameworkException;
use App\Exceptions\Internal\QueryBuilderException;
use App\Models\Photo;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Contracts\Container\BindingResolutionException;

class PhotoPolicy
{
	use HandlesAuthorization;

	protected AlbumPolicy $albumPolicy;
	protected UserPolicy $userPolicy;

	// constants to be used in GATE
	public const OWN = 'own';
	public const VISIBLE = 'visible';
	public const DOWNLOAD = 'download';
	public const EDIT = 'edit';

	/**
	 * @throws FrameworkException
	 */
	public function __construct()
	{
		try {
			$this->albumPolicy = resolve(AlbumPolicy::class);
			$this->userPolicy = resolve(UserPolicy::class);
		} catch (BindingResolutionException $e) {
			throw new FrameworkException('Laravel\'s provider component', $e);
		}
	}

	/**
	 * Perform pre-authorization checks.
	 *
	 * @param \App\Models\User $user
	 * @param string           $ability
	 *
	 * @return void|bool
	 */
	public function before(?User $user, $ability)
	{
		if ($this->userPolicy->admin($user)) {
			return true;
		}
	}

	/**
	 * This gate policy ensures that the Photo is owned by current user.
	 * Do note that in case of current user being admin, it will be skipped due to the before method.
	 *
	 * @param User|null $user
	 * @param Photo     $photo
	 *
	 * @return bool
	 */
	public function own(?User $user, Photo $photo): bool
	{
		return $user !== null && $photo->owner_id === $user->id;
	}

	/**
	 * Defines whether the photo is visible to the current user.
	 *
	 * @param User|null $user
	 * @param Photo     $photo
	 *
	 * @return bool
	 */
	public function visible(?User $user, Photo $photo): bool
	{
		return $this->own($user, $photo) ||
		$photo->is_public ||
		$this->albumPolicy->access($user, $photo->album);
	}

	/**
	 * Checks whether the photo may be downloaded by the current user.
	 *
	 * Previously, this code was part of {@link Archive::extractFileInfo()}.
	 * In particular, the method threw to {@link UnauthorizedException} with
	 * custom error messages:
	 *
	 *  - `'User is not allowed to download the image'`, if the user was not
	 *    the owner, the user was allowed to see the photo (i.e. the album
	 *    is shared with the user), but the album does not allow to dowload
	 *    photos
	 *  - `'Permission to download is disabled by configuration'`, if the
	 *    user was not the owner, the photo was not part of any album (i.e.
	 *    unsorted), the photo was public and downloading was disabled by
	 *    configuration.
	 *
	 * TODO: Check if these custom error messages are still needed. If yes, consider not to return a boolean value but rename the method to `assert...` and throw exceptions with custom error messages.
	 *
	 * @param User|null $user
	 * @param Photo     $photo
	 *
	 * @return bool
	 */
	public function download(?User $user, Photo $photo): bool
	{
		if (!$this->visible($user, $photo)) {
			return false;
		}

		return $this->albumPolicy->download($user, $photo->album);
	}

	/**
	 * Checks whether the photo is editable by the current user.
	 *
	 * A photo is called _editable_ if the current user is allowed to edit
	 * the photo's properties.
	 * A photo is _editable_ if any of the following conditions hold
	 * (OR-clause)
	 *
	 *  - the user is an admin
	 *  - the user is the owner of the photo
	 *
	 * @param Photo $photo
	 *
	 * @return bool
	 */
	public function edit(User $user, Photo $photo)
	{
		return $this->own($user, $photo);
	}

	// The following methods are not to be called by Gate.

	/**
	 * Checks whether the designated photos are editable by the current user.
	 *
	 * See {@link PhotoQueryPolicy::isEditable()} for the definition
	 * when a photo is editable.
	 *
	 * This method is mostly only useful during deletion of photos, when no
	 * photo models are loaded for efficiency reasons.
	 * If a photo model is required anyway (because it shall be edited),
	 * then first load the photo once and use
	 * {@link PhotoQueryPolicy::isEditable()}
	 * instead in order to avoid several DB requests.
	 *
	 * @param User     $user
	 * @param string[] $photoIDs
	 *
	 * @return bool
	 *
	 * @throws QueryBuilderException
	 */
	public function editByID(User $user, array $photoIDs): bool
	{
		if ($this->before($user, 'editById') === true) {
			return true;
		}

		// Make IDs unique as otherwise count will fail.
		$photoIDs = array_unique($photoIDs);

		return
			count($photoIDs) === 0 ||
			Photo::query()
				->whereIn('id', $photoIDs)
				->where('owner_id', $user->id)
				->count() === count($photoIDs);
	}
}