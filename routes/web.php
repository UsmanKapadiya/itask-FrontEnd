<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ListController;
use App\Http\Controllers\ProjectController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/{any}', function () {
    return view('app');
})->where('any', '.*');


// Route::get('/', function () {
//     if (\Illuminate\Support\Facades\Auth::user()) {
//         return redirect('dashboard');
//     } else {
//         return view('app');
//     }
// });
Auth::routes(['register' => false]);

Route::get('/register', function () {
    return view('auth.register');
});
Route::get('/verify-code/{email}', [HomeController::class, 'loadVerifyCode']);
Route::post('/user-register', [HomeController::class, 'register']);
Route::post('/verify-code', [HomeController::class, 'verifyCode']);
Route::post('/resend-code', [HomeController::class, 'resendCode']);
Route::post('/user-login', [HomeController::class, 'vefiryLogin']);
Route::get('/password-reset', function () {
    return view('auth.passwords.email');
});
Route::post('/password-reset', [HomeController::class, 'forgotPassword'])->name("password-reset");
Route::get('/password-reset/{email}', [HomeController::class, 'loadOTPView']);
Route::get('/change-password/{email}', [HomeController::class, 'loadChangePassword']);
Route::post('/change-password', [HomeController::class, 'changePassword'])->name("change-password");
Route::post('/verify-otp', [HomeController::class, 'verifyOtp'])->name("verify-otp");
Route::get('/resend-otp/{email}', [HomeController::class, 'resendOtp']);
Route::get('/logout', [LoginController::class, 'logout'])->name('logout');
Route::get('/send-notification', [ProjectController::class, 'sendCreateNotification'])->name('send-notification');
Route::group(['middleware' => ['auth']], function () {
    Route::post('/unassign-task', [ListController::class, 'unassignTaskList']);
});
// Route::group(['middleware' => ['auth']], function () {
//     Route::get('/dashboard', function () {
//         return view('projectdetail');
//     });
//     Route::get('/home', [HomeController::class, 'index'])->name('home');
//     Route::post('/search-list', [ListController::class, 'searchList']);
//     Route::get('/notification-list', [ListController::class, 'notificationList']);
//     Route::post('/read-notification', [HomeController::class, 'readNotification']);
//     Route::get('/member-information', [HomeController::class, 'memberInformation']);
//     Route::post('/user-update', [HomeController::class, 'userUpdate']);
//     Route::post('/update-timezone', [HomeController::class, 'timezoneUpdate']);
//     Route::get('/member-notification-information', [HomeController::class, 'memberNotificationInformation']);
//     Route::post('/update-via-notification', [HomeController::class, 'vianotificationUpdate']);
//     Route::post('/update-auto-reminder', [HomeController::class, 'autoreminderUpdate']);
//     Route::get('/notification-information', [HomeController::class, 'notificationInformation']);
//     Route::post('/update-notification-setting', [HomeController::class, 'notificationsettingUpdate']);
//     Route::post('/projects', [ListController::class, 'projectList']);
//     Route::get('member-to-assign/{projectId}', [ListController::class, 'memberDetail']);
//     Route::post('/unassign-task', [ListController::class, 'unassignTaskList']);
//     Route::get('/inbox-task-count', [ListController::class, 'inboxTasksCount']);
//     Route::post('/member-projects', [ListController::class, 'parentProjects']);
//     Route::get('/members-list/{project_id}', [ListController::class, 'membersList']);
//     Route::get('/member-tags', [ListController::class, 'memberTags']);
//     Route::post('/review-project', [ListController::class, 'reviewProjectList']);

//     Route::post('/project-detail-list', [ListController::class, 'projectDetailList']);
//     Route::get('/member-detail-list/{projectid}', [ListController::class, 'memberDetailList']);

//     Route::get('/unassign-members-list/{projectid}', [ListController::class, 'unassignMemberDetailList']);

//     Route::get('/comment-detail-list/{ptid}', [ListController::class, 'commentDetailList']);
//     Route::get('/attachment-detail-list/{projectid}', [ListController::class, 'attachmentDetailList']);

//     Route::post('/list-by-flag', [ListController::class, 'listByFlag']);
//     Route::post('/add-project', [ProjectController::class, 'addProjectTask']);
//     Route::post('/invite-member', [ProjectController::class, 'inviteMember']);
//     Route::post('/add-task', [ProjectController::class, 'addProjectTask']);
//     Route::post('/save-update-task', [ProjectController::class, 'updateTask']);
//     Route::post('/add-comment', [ProjectController::class, 'addComment']);
//     Route::post('/save-update-comment', [ProjectController::class, 'updateComment']);
//     Route::post('/delete-comment', [ProjectController::class, 'deleteComment']);
//     Route::post('/remove-member', [ProjectController::class, 'removeMember']);
//     Route::post('/delete-attachment', [ProjectController::class, 'deleteDocument']);
//     Route::post('/delete-multiple-attachment', [ProjectController::class, 'deleteMultipleDocument']);
//     Route::post('/update-attachment', [ProjectController::class, 'updateAttachment']);
//     Route::post('/add-tag', [ProjectController::class, 'addTag']);
//     Route::post('/edit-tag', [ProjectController::class, 'editTag']);
//     Route::post('/delete-tag', [ProjectController::class, 'deleteTag']);
//     Route::post('/delete-project-task', [ProjectController::class, 'deleteProjectTask']);
//     Route::get('/notification-count', [ListController::class, 'getNotificationCount']);
//     Route::post('/project-detail-by-id', [ProjectController::class, 'getProjectDetails']);
//     Route::post('/update-project', [ProjectController::class, 'updateProject']);
//     Route::post('/complete-uncomplete-task', [ProjectController::class, 'completeTask']);
//     Route::post('/complete-project', [ProjectController::class, 'completeProject']);
//     Route::post('/assign-member-task', [ProjectController::class, 'assignMemberTask']);
//     Route::post('/project-task-by-tag', [ListController::class, 'getProjectTaskByTag']);
//     Route::post('/delete-user', [ProjectController::class, 'deleteAccount']);
//     Route::post('/move-task-project', [ProjectController::class, 'moveTaskProject']);
// });

