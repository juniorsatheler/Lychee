<?php

namespace App\Http\Controllers\Administration;

use App\Actions\Update\Apply as ApplyUpdate;
use App\Actions\Update\Check as CheckUpdate;
use App\Auth\Authorization;
use App\Contracts\LycheeException;
use App\Exceptions\VersionControlException;
use App\Legacy\Legacy;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Session;
use Illuminate\View\View;

/**
 * Class UpdateController.
 *
 * Most likely, this controller should be liquidated and its methods become
 * integrated into the controllers below `App\Http\Controllers\Install\`
 * as far as the methods are not already covered.
 *
 * For example, {@link UpdateController::migrate()} serves a similar purpose
 * as {@link \App\Http\Controllers\Install\MigrationController::view()}.
 * An initial installation and an upgrade share many similar steps.
 * There is no (technical) need why the DB migration after an upgrade and
 * the DB migration after an initial installation should be distinct.
 *
 * For example, we check that the server meets the requirements during
 * an initial installation
 * (see {@link \App\Http\Controllers\Install\RequirementsController}),
 * but we don't check if the server meets the requirements before an upgrade.
 * This has already raised some bug reports.
 *
 * The main obstacle of such a refactoring is that the individual steps of
 * an installation are not properly decomposed such that they are not
 * generally usable.
 * For example,
 * {@link \App\Http\Controllers\Install\MigrationController::view()}
 * does not only migrate the DB (as the name suggests),
 * but also generates a new API key.
 * However, the latter is only necessary for an initial installation.
 *
 * TODO: Revise and refactor the whole logic around installation/upgrade/migration.
 */
class UpdateController extends Controller
{
	protected CheckUpdate $checkUpdate;
	protected ApplyUpdate $applyUpdate;

	public function __construct(CheckUpdate $checkUpdate, ApplyUpdate $applyUpdate)
	{
		$this->checkUpdate = $checkUpdate;
		$this->applyUpdate = $applyUpdate;
	}

	/**
	 * Return if up to date or the number of commits behind
	 * This invalidates the cache for the url.
	 *
	 * @return array{updateStatus: string}
	 *
	 * @throws VersionControlException
	 */
	public function check(): array
	{
		return ['updateStatus' => $this->checkUpdate->getText()];
	}

	/**
	 * Updates Lychee and returns the messages as a JSON object.
	 *
	 * The method requires PHP to have shell access.
	 * Except for the return type this method is identical to
	 * {@link UpdateController::view()}.
	 *
	 * @return array{updateMsgs: array<string>}
	 *
	 * @throws LycheeException
	 */
	public function apply(): array
	{
		$this->checkUpdate->assertUpdatability();

		return ['updateMsgs' => $this->applyUpdate->run()];
	}

	/**
	 * Updates Lychee and returns the messages as an HTML view.
	 *
	 * The method requires PHP to have shell access.
	 * Except for the return type this method is identical to
	 * {@link UpdateController::apply()}.
	 *
	 * @return View
	 *
	 * @throws LycheeException
	 */
	public function view(): View
	{
		$this->checkUpdate->assertUpdatability();
		$output = $this->applyUpdate->run();

		return view('update.results', ['code' => '200', 'message' => 'Upgrade results', 'output' => $output]);
	}

	/**
	 * Migrates the Lychee DB and returns a HTML view.
	 *
	 * **TODO:** Consolidate with {@link \App\Http\Controllers\Install\MigrationController::view()}.
	 *
	 * **ATTENTION:** This method serves a somewhat similar purpose as
	 * `MigrationController::view()` except that the latter does not only
	 * trigger a migration, but also generates a new API key.
	 * Also note, that this method internally uses
	 * {@link ApplyUpdate::migrate()} while `MigrationController::view`
	 * uses {@link \App\Actions\Install\ApplyMigration::migrate()}.
	 * However, both methods are very similar, too.
	 * The whole code around installation/upgrade/migration should
	 * thoroughly be revised an refactored.
	 */
	public function migrate(Request $request): View|Response
	{
		// This conditional code makes use of lazy boolean evaluation: a || b does not execute b if a is true.
		// 1. We check if we are logged in
		// 2. if not, we check if the admin user is registered (if not login in such case)
		// 3. We have an admin user set up, try to login with Legacy method: hash(username) + hash(password).
		// 4. if Legacy login failed, try to login the normal way.
		$isLoggedIn = Auth::check();
		$isLoggedIn = $isLoggedIn || Authorization::loginAsAdminIfNotRegistered();
		$isLoggedIn = $isLoggedIn || Legacy::loginAsAdmin($request->input('username', ''), $request->input('password', ''), $request->ip());
		$isLoggedIn = $isLoggedIn || Auth::attempt(['username' => $request->input('username', ''), 'password' => $request->input('password', '')]);

		// Check if logged in AND is admin
		if ($isLoggedIn && Gate::check('admin')) {
			$output = [];
			$this->applyUpdate->migrate($output);
			$this->applyUpdate->filter($output);

			if (Authorization::isAdminNotRegistered()) {
				Auth::logout();
				Session::flush();
			}

			return view('update.results', ['code' => '200', 'message' => 'Migration results', 'output' => $output]);
		}

		// Rather than returning a view directly (which implies code 200, we use response in order to ensure code 403)
		return response()->view('update.error', ['code' => '403', 'message' => 'Incorrect username or password'], 403);
	}
}
