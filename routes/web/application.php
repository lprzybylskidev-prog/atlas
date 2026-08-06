<?php

declare(strict_types=1);

use App\Modules\Core\Exports\Presentation\Http\Controllers\AdminDataTableExportController;
use App\Modules\Core\Exports\Presentation\Http\Controllers\DownloadReportArtifactController;
use App\Modules\Core\Exports\Presentation\Http\Controllers\PrintReportExportController;
use App\Modules\Core\Identity\Presentation\Http\Controllers\ActiveTeamController;
use App\Modules\Core\Notifications\Presentation\Http\Controllers\BulkMarkNotificationReadController;
use App\Modules\Core\Notifications\Presentation\Http\Controllers\MarkNotificationReadController;
use App\Modules\Core\Notifications\Presentation\Http\Controllers\NotificationCenterController;
use App\Modules\Core\Notifications\Presentation\Http\Controllers\RealtimeEventsController;
use App\Modules\Core\Users\Application\Permissions\UserPermissionCatalog;
use App\Modules\Core\Users\Presentation\Http\Controllers\StoreNotificationEmailAddressController;
use App\Modules\Core\Users\Presentation\Http\Controllers\UpdateNotificationEmailPreferenceController;
use App\Modules\Core\Users\Presentation\Http\Controllers\UpdateUserProfileAvatarController;
use App\Modules\Core\Users\Presentation\Http\Controllers\UpdateUserProfilePasswordController;
use App\Modules\Core\Users\Presentation\Http\Controllers\UserProfileAvatarImageController;
use App\Modules\Core\Users\Presentation\Http\Controllers\UserProfileController;
use App\Modules\Core\Users\Presentation\Http\Controllers\VerifyNotificationEmailAddressController;
use App\Modules\Optional\TimeTracking\Application\Permissions\TimeTrackingPermissionCatalog;
use App\Modules\Optional\TimeTracking\Presentation\Http\Controllers\ActivityTrackerController;
use App\Modules\Optional\TimeTracking\Presentation\Http\Controllers\AdminOtherWorkCategoryController;
use App\Modules\Optional\TimeTracking\Presentation\Http\Controllers\AdminTimeTrackingOperationActionController;
use App\Modules\Optional\TimeTracking\Presentation\Http\Controllers\AdminTimeTrackingOperationDetailController;
use App\Modules\Optional\TimeTracking\Presentation\Http\Controllers\BreakLockController;
use App\Modules\Optional\TimeTracking\Presentation\Http\Controllers\ManagerPanelController;
use App\Modules\Optional\TimeTracking\Presentation\Http\Controllers\ManagerTimeReportController;
use App\Modules\Optional\TimeTracking\Presentation\Http\Controllers\ManagerTimeTrackingOperationsController;
use App\Modules\Optional\TimeTracking\Presentation\Http\Controllers\OtherWorkLockController;
use App\Modules\Optional\TimeTracking\Presentation\Http\Controllers\StartBreakController;
use App\Modules\Optional\TimeTracking\Presentation\Http\Controllers\StartOtherWorkController;
use App\Modules\Optional\TimeTracking\Presentation\Http\Controllers\UserCorrectionRequestController;
use App\Modules\Optional\TimeTracking\Presentation\Http\Controllers\UserTimeReportController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware('auth')->group(function (): void {
    Route::get('/team/select', [ActiveTeamController::class, 'select'])->name('team.select');
    Route::post('/team/select', [ActiveTeamController::class, 'store'])->name('team.select.store');
    Route::post('/team/switch', [ActiveTeamController::class, 'switch'])->name('team.switch');
    Route::get('/user/notification-emails/{email}/verify/{token}', VerifyNotificationEmailAddressController::class)
        ->middleware('signed')
        ->name('users.profile.notification-emails.verify');
});

Route::middleware(['auth', 'route.permission'])->group(function (): void {
    Route::get('/', fn () => Inertia::render('Dashboard'))->name('dashboard');
    Route::get('/user', UserProfileController::class)->name(UserPermissionCatalog::USERS_PROFILE);
    Route::get('/user/avatar-image', UserProfileAvatarImageController::class)->name(UserPermissionCatalog::USERS_PROFILE_AVATAR_IMAGE);
    Route::put('/user/password', UpdateUserProfilePasswordController::class)->name(UserPermissionCatalog::USERS_PROFILE_PASSWORD_UPDATE);
    Route::post('/user/avatar', UpdateUserProfileAvatarController::class)->name(UserPermissionCatalog::USERS_PROFILE_AVATAR_UPDATE);
    Route::post('/user/notification-emails', StoreNotificationEmailAddressController::class)->name(UserPermissionCatalog::USERS_PROFILE_NOTIFICATION_EMAILS_STORE);
    Route::patch('/user/notification-emails/{email}', UpdateNotificationEmailPreferenceController::class)->name(UserPermissionCatalog::USERS_PROFILE_NOTIFICATION_EMAILS_UPDATE);
    Route::get('/manager', ManagerPanelController::class)->name(TimeTrackingPermissionCatalog::MANAGER_PANEL);
    Route::get('/exports/{artifact}/download', DownloadReportArtifactController::class)->name('exports.download');
    Route::get('/exports/{export}/print', PrintReportExportController::class)->name('exports.print');
    Route::post('/exports/data-table', AdminDataTableExportController::class)->name('exports.data-table');
    Route::get('/user/notifications', NotificationCenterController::class)->name('users.notifications.index');
    Route::post('/user/notifications/read', BulkMarkNotificationReadController::class)->name('users.notifications.read.bulk');
    Route::post('/user/notifications/{notification}/read', MarkNotificationReadController::class)->name('users.notifications.read');
    Route::get('/realtime/events', RealtimeEventsController::class)->name('notifications.realtime.events');
    Route::post('/user/work-time/break/start', StartBreakController::class)->name(TimeTrackingPermissionCatalog::BREAK_START);
    Route::get('/user/work-time/other-work/start', [StartOtherWorkController::class, 'create'])->name(TimeTrackingPermissionCatalog::OTHER_WORK_CREATE);
    Route::post('/user/work-time/other-work/start', [StartOtherWorkController::class, 'store'])->name(TimeTrackingPermissionCatalog::OTHER_WORK_START);
    Route::get('/user/work-time/break', [BreakLockController::class, 'show'])->name(TimeTrackingPermissionCatalog::BREAK_SHOW);
    Route::post('/user/work-time/break/end', [BreakLockController::class, 'end'])->name(TimeTrackingPermissionCatalog::BREAK_END);
    Route::get('/user/work-time/other-work', [OtherWorkLockController::class, 'show'])->name(TimeTrackingPermissionCatalog::OTHER_WORK_SHOW);
    Route::post('/user/work-time/other-work/end', [OtherWorkLockController::class, 'end'])->name(TimeTrackingPermissionCatalog::OTHER_WORK_END);
    Route::post('/time-tracking/activity', ActivityTrackerController::class)->name('time-tracking.activity.record');
    Route::get('/user/work-time', UserTimeReportController::class)->name(TimeTrackingPermissionCatalog::USER_REPORT);
    Route::post('/user/work-time/corrections', [UserCorrectionRequestController::class, 'store'])->name(TimeTrackingPermissionCatalog::USER_CORRECTION_REQUEST_STORE);
    Route::get('/time-tracking/manager-report', ManagerTimeReportController::class)->name('time-tracking.reports.manager');
    Route::get('/manager/work-time/summary', [ManagerTimeTrackingOperationsController::class, 'daily'])->name(TimeTrackingPermissionCatalog::MANAGER_WORK_TIME_SUMMARY);
    Route::get('/manager/work-time/other-work', [ManagerTimeTrackingOperationsController::class, 'otherWork'])->name(TimeTrackingPermissionCatalog::MANAGER_WORK_TIME_OTHER_WORK);
    Route::get('/manager/work-time/breaks', [ManagerTimeTrackingOperationsController::class, 'breaks'])->name(TimeTrackingPermissionCatalog::MANAGER_WORK_TIME_BREAKS);
    Route::get('/manager/work-time/corrections', [ManagerTimeTrackingOperationsController::class, 'corrections'])->name(TimeTrackingPermissionCatalog::MANAGER_WORK_TIME_CORRECTIONS);
    Route::get('/manager/work-time/work-sessions', [ManagerTimeTrackingOperationsController::class, 'workSessions'])->name(TimeTrackingPermissionCatalog::MANAGER_WORK_TIME_WORK_SESSIONS);
    Route::get('/manager/work-time/other-work/categories', [AdminOtherWorkCategoryController::class, 'index'])->name(TimeTrackingPermissionCatalog::MANAGER_OTHER_WORK_CATEGORY_INDEX);
    Route::get('/manager/work-time/other-work/categories/create', [AdminOtherWorkCategoryController::class, 'create'])->name(TimeTrackingPermissionCatalog::MANAGER_OTHER_WORK_CATEGORY_CREATE);
    Route::post('/manager/work-time/other-work/categories', [AdminTimeTrackingOperationActionController::class, 'storeCategory'])->name(TimeTrackingPermissionCatalog::MANAGER_OTHER_WORK_CATEGORY_STORE);
    Route::delete('/manager/work-time/other-work/categories/{category}', [AdminTimeTrackingOperationActionController::class, 'deactivateCategory'])->name(TimeTrackingPermissionCatalog::MANAGER_OTHER_WORK_CATEGORY_DEACTIVATE);
    Route::get('/manager/work-time/work-sessions/{session}', [AdminTimeTrackingOperationDetailController::class, 'workSession'])->name(TimeTrackingPermissionCatalog::MANAGER_WORK_TIME_WORK_SESSION_SHOW);
    Route::get('/manager/work-time/breaks/{break}', [AdminTimeTrackingOperationDetailController::class, 'break'])->name(TimeTrackingPermissionCatalog::MANAGER_WORK_TIME_BREAK_SHOW);
    Route::get('/manager/work-time/other-work/{otherWork}', [AdminTimeTrackingOperationDetailController::class, 'otherWork'])->name(TimeTrackingPermissionCatalog::MANAGER_WORK_TIME_OTHER_WORK_SHOW);
    Route::get('/manager/work-time/corrections/{correction}', [AdminTimeTrackingOperationDetailController::class, 'correction'])->name(TimeTrackingPermissionCatalog::MANAGER_WORK_TIME_CORRECTION_SHOW);
    Route::post('/manager/work-time/work-sessions/{session}/terminate', [AdminTimeTrackingOperationActionController::class, 'terminateWorkSession'])->name(TimeTrackingPermissionCatalog::MANAGER_TERMINATE_SESSION);
    Route::post('/manager/work-time/breaks/{break}/force-close', [AdminTimeTrackingOperationActionController::class, 'forceCloseBreak'])->name(TimeTrackingPermissionCatalog::MANAGER_BREAK_FORCE_CLOSE);
    Route::post('/manager/work-time/breaks/{break}/convert-excess', [AdminTimeTrackingOperationActionController::class, 'convertExcessBreak'])->name(TimeTrackingPermissionCatalog::MANAGER_BREAK_CONVERT_EXCESS);
    Route::post('/manager/work-time/other-work/{otherWork}/force-close', [AdminTimeTrackingOperationActionController::class, 'forceCloseOtherWork'])->name(TimeTrackingPermissionCatalog::MANAGER_OTHER_WORK_FORCE_CLOSE);
    Route::post('/manager/work-time/other-work/{otherWork}/decide', [AdminTimeTrackingOperationActionController::class, 'decideOtherWork'])->name(TimeTrackingPermissionCatalog::MANAGER_OTHER_WORK_DECIDE);
    Route::post('/manager/work-time/corrections/{correction}/decide', [AdminTimeTrackingOperationActionController::class, 'decideCorrection'])->name(TimeTrackingPermissionCatalog::MANAGER_CORRECTION_DECIDE);
});
