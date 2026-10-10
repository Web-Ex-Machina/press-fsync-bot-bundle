Extension "Press Fsync Bot" for Contao Open Source CMS
========

2.0.0
---
- ADDED: Contao 5.7 compatibility, the Contao Manager plugin loads the routes of the controllers (`config/routes.yaml`)
- REMOVED: Contao 4.9 compatibility (requires `contao/core-bundle` ^5.7 and `webexmachina/contao-utils` ^2.0)
- REMOVED: `plenta/contao-encryption`. The API credentials of the settings (Twitch client id and secret, Discord token, Rawg secret) are now encrypted with the `wem.encryption_util` service of `webexmachina/contao-utils`: they must be entered again in the settings
- REMOVED: the Rawg integration (`Classes/Rawg`, `Backend/RawgCallback`)
- REMOVED: the Discord messages back end (`tl_pfs_discord_message`, its model and its back end route). `DiscordService::syncMessages()` is kept for legacy but is not called anymore, and the `tl_pfs_discord_message` table is no longer managed
- REMOVED: the `docker/` folder, `GeneratePageListener`, the legacy `Module` / `Classes` / `Event` / `Backend` classes
- CHANGED: the resources moved from `src/Resources/contao/` to `contao/` (DCA, languages, templates, config), the classmap autoload is removed
- CHANGED: the front end modules are fragment controllers (`DisplayScheduleController`, `DisplayRuvonShamelistController`), the Contao 4 modules `DisplaySchedule` and `ShamelistRuvon` are removed. The templates are still `.html5` files
- CHANGED: the API moved to dedicated controllers (`ScheduleApiController`: `/api/psync/schedule`, `RuvonApiController`: `/api/psync/ruvon`) using the `Symfony\Component\Routing\Attribute\Route` attribute
- ADDED: services: `TwitchService`, `DiscordService`, `TaskService`, `ConfigService`, `SteamService`, `BackendActionGuard`
- ADDED: cron jobs: `SyncSchedulesJob` (every 10 minutes: Twitch and Discord events) and `ExecuteTasksJob` (every 10 minutes, offset by 5: pending tasks). They only run when the new settings `pfsSyncSchedulesJob` / `pfsExecuteTasksJob` are set to `prod` (default), so they can be disabled on a development instance
- ADDED: back end actions to run manually the Twitch sync, the Discord events sync, and the tasks (execute, dry run, clean)
- ADDED: the next events can be retrieved through the API
- CHANGED: the services work when called by the cron (no request, no back end user), and `Runner` does not depend on `FrameworkAwareInterface` anymore
- CHANGED: the bundle reads the URL and the visitor from the Symfony request instead of the legacy static helpers `Input` and `Environment`, the database through Doctrine `Connection` instead of `Database::getInstance()`, and logs through `LoggerInterface` instead of `System::log()`. The settings are read through the `ContaoFramework` adapter of `Config`
- CHANGED: the back end `key=...` actions check the request token of the link (like the Contao operations do, the user is sent to the back end confirmation page without a valid one), and redirect using the request instead of `Environment` and `Controller::redirect()`. The "clean tasks" action truncates a table, it could be triggered by a forged link
- CHANGED: the Steam wishlist is read by `SteamService` (Symfony HTTP client, timeouts) instead of raw `curl`
- FIXED: the Ruvon shamelist crashed (no answer from Steam, private wishlist) or divided by zero when the wishlist of Ruvon was empty. The SteamID64 must now be 17 digits, otherwise an error message is displayed. The wishlist count endpoint answers `502` when Steam fails
- FIXED: `$GGLOBALS` typo in the users filter of the schedule (the placeholder was never displayed), and the filter used an undefined `$this->pids` instead of the pids of the module
- CHANGED: a task whose Twitch event does not exist anymore is logged through the Contao logger and skipped, so it does not block the task process
- UPDATED: the Composer file (`support.website`, dev dependencies, plugins), `.gitignore`, `LICENSE`
- ADDED: ECS (Contao coding standard) and Rector configuration
