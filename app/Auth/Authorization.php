<?php

namespace App\Auth;

use App\Exceptions\ModelDBException;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class Authorization
{
	/**
	 * Checks wether the the admin is unconfigured.
	 * The method is not side-effect free.
	 * If the admin user happens to not exist at all, the method creates an unconfigured admin.
	 *
	 * @return bool
	 *
	 * @throws ModelDBException
	 */
	public static function isAdminNotRegistered(): bool
	{
		/** @var User|null $adminUser */
		$adminUser = User::query()->find(0);
		if ($adminUser !== null) {
			return $adminUser->password === '' || $adminUser->username === '';
		}
		self::resetAdmin();

		return true;
	}

	/**
	 * TODO: Once the admin user registration is moved to the installation phase this methode can finally be removed.
	 *
	 * Login as admin temporarily when unconfigured.
	 *
	 * @return bool true of successful
	 *
	 * @throws ModelDBException
	 */
	public static function loginAsAdminIfNotRegistered(): bool
	{
		if (self::isAdminNotRegistered()) {
			/** @var User|null $adminUser */
			$adminUser = User::query()->find(0);
			Auth::login($adminUser);

			return true;
		}

		return false;
	}

	/**
	 * Reset admin user: set username and password to empty string ''.
	 *
	 * @return void
	 *
	 * @throws ModelDBException
	 */
	public static function resetAdmin(): void
	{
		/** @var User $user */
		$user = User::query()->findOrNew(0);
		$user->incrementing = false; // disable auto-generation of ID
		$user->id = 0;
		$user->username = '';
		$user->password = '';
		$user->save();
	}
}