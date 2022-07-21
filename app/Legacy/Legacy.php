<?php

namespace App\Legacy;

use App\Exceptions\ConfigurationException;
use App\Exceptions\Internal\QueryBuilderException;
use App\Models\Configs;
use App\Models\Logs;
use App\Models\User;
use App\Rules\IntegerIDRule;
use App\Rules\RandomIDRule;
use Auth;
use Hash;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Stuff we need to delete in the future.
 */
class Legacy
{
	public static function isLegacyModelID(string $id): bool
	{
		$modernIDRule = new RandomIDRule(true);
		$legacyIDRule = new IntegerIDRule(false);

		return !$modernIDRule->passes('id', $id) &&
			$legacyIDRule->passes('id', $id);
	}

	/**
	 * Translates an ID from legacy format to modern format.
	 *
	 * @param int     $id        the legacy ID
	 * @param string  $tableName the table name which should be used to look
	 *                           up the ID; either `photos` or `base_albums`
	 * @param Request $request   the request which triggered the lookup
	 *                           (required for proper logging)
	 *
	 * @return string|null the modern ID
	 *
	 * @throws QueryBuilderException  thrown by the ORM layer in case of error
	 * @throws ConfigurationException thrown, if the translation between
	 *                                legacy and modern IDs is disabled
	 */
	private static function translateLegacyID(int $id, string $tableName, Request $request): ?string
	{
		try {
			$newID = (string) DB::table($tableName)
				->where('legacy_id', '=', intval($id))
				->value('id');

			if ($newID !== '') {
				$referer = strval($request->header('Referer', '(unknown)'));
				$msg = 'Request for ' . $tableName .
					' with legacy ID ' . $id .
					' instead of new ID ' . $newID .
					' from ' . $referer;
				if (!Configs::getValueAsBool('legacy_id_redirection')) {
					$msg .= ' (translation disabled by configuration)';
					throw new ConfigurationException($msg);
				}
				Logs::warning(__METHOD__, __LINE__, $msg);

				return $newID;
			}

			return null;
		} catch (\InvalidArgumentException $e) {
			throw new QueryBuilderException($e);
		}
	}

	/**
	 * Translates an album ID from legacy format to modern format.
	 *
	 * @param int     $albumID the legacy ID
	 * @param Request $request the request which triggered the lookup
	 *                         (required for proper logging)
	 *
	 * @return string|null the modern ID
	 *
	 * @throws QueryBuilderException  thrown by the ORM layer in case of error
	 * @throws ConfigurationException thrown, if the translation between
	 *                                legacy and modern IDs is disabled
	 */
	public static function translateLegacyAlbumID(int $albumID, Request $request): ?string
	{
		return self::translateLegacyID($albumID, 'base_albums', $request);
	}

	/**
	 * Translates a photo ID from legacy format to modern format.
	 *
	 * @param int     $photoID the legacy ID
	 * @param Request $request the request which triggered the lookup
	 *                         (required for proper logging)
	 *
	 * @return string|null the modern ID
	 *
	 * @throws QueryBuilderException  thrown by the ORM layer in case of error
	 * @throws ConfigurationException thrown, if the translation between
	 *                                legacy and modern IDs is disabled
	 */
	public static function translateLegacyPhotoID(int $photoID, Request $request): ?string
	{
		return self::translateLegacyID($photoID, 'photos', $request);
	}

	/**
	 * Given a username, password and ip (for logging), try to log the user as admin.
	 * Returns true if succeeded, false if failed.
	 *
	 * Note that this method will only be successful "once".
	 * Upon success, the admin username is updated to a clear text value.
	 *
	 * @param string $username
	 * @param string $password
	 * @param string $ip
	 *
	 * @return bool true if login is successful
	 */
	public static function loginAsAdmin(string $username, string $password, string $ip): bool
	{
		/** @var User|null $adminUser */
		$adminUser = User::query()->find(0);
		// findOrFail could be used, but we just want to make sure to handle the cases where that user in not in the DB even though it should not happen.

		// Admin User exists, so we check against it.
		if ($adminUser !== null && Hash::check($username, $adminUser->username) && Hash::check($password, $adminUser->password)) {
			Auth::login($adminUser);
			Logs::notice(__METHOD__, __LINE__, 'User (' . $username . ') has logged in from ' . $ip);

			// update the admin username so we do not need to go through here anymore.
			$adminUser->username = $username;
			$adminUser->save();

			return true;
		}

		return false;
	}
}
