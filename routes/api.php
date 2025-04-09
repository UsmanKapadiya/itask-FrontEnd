<?php

use App\Http\Controllers\Api\DinningController;
use App\Http\Controllers\Api\ListController;
use App\Http\Controllers\Api\LogUserController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});
Route::get('table-list', [DinningController::class, 'getTableList']);
Route::get('resident-list', [DinningController::class, 'getResidentList']);
Route::get('rooms-list', [DinningController::class, 'getRoomList']);
Route::post('order-list', [DinningController::class, 'getOrderList']);
Route::post('item-list', [DinningController::class, 'getItemList']);
Route::post('update-order', [DinningController::class, 'updateOrder']);
Route::post('get-categorywise-data', [DinningController::class, 'getCategoryWiseData']);
Route::post('get-room-data', [DinningController::class, 'getRoomData']);
Route::post('user-registration', [UserController::class, 'registration']);
Route::post('user-login', [UserController::class, 'login']);
Route::post('verify-code', [UserController::class, 'verifyVerificationCode']);
Route::post('resend-code', [UserController::class, 'ResendVerificationCode']);
Route::post('forgot-password', [UserController::class, 'forgotPassword']);
Route::post('verify-otp', [UserController::class, 'verifyOtp']);
Route::post('resend-otp', [UserController::class, 'resendOtp']);
Route::post('change-password', [UserController::class, 'changePassword']);
Route::group(['middleware' => 'APIToken'], function () {
    Route::post('update-device-token', [UserController::class, 'updateDeviceToken']);
    Route::post('create-project-task', [ProjectController::class, 'createProjectTask']);
    Route::post('upload-document', [ProjectController::class, 'addDocument']);
    Route::post('project-list', [ListController::class, 'projectList']);
    Route::post('add-tag', [ProjectController::class, 'addtag']);
    Route::post('tag-list', [ListController::class, 'taglist']);
    Route::post('add-comment', [ProjectController::class, 'addComment']);
    Route::post('edit-comment', [ProjectController::class, 'editComment']);
    Route::post('delete-comment', [ProjectController::class, 'deleteComment']);
    Route::post('send-invitation', [ProjectController::class, 'sendInvitation']);
    Route::post('project-task-detail', [ProjectController::class, 'projectTaskDetails']);
    Route::post('project-task-member-list', [ListController::class, 'projectTaskMemberList']);
    Route::post('project-tag-list', [ListController::class, 'projectTagList']);
    Route::post('edit-tag', [ProjectController::class, 'editTag']);
    Route::post('delete-tag', [ProjectController::class, 'deleteTag']);
    Route::post('tasks-by-project-id', [ListController::class, 'getTaskByProjectID']);
    Route::post('tasks-by-flag', [ListController::class, 'getTasksByFlag']);
    Route::post('inbox-tasks', [ListController::class, 'getInboxTasks']);
    Route::post('update-duedate-time', [ProjectController::class, 'updateDueDateTime']);
    Route::post('update-reminder', [ProjectController::class, 'updateReminder']);
    Route::post('document-list-by-project-id', [ListController::class, 'documentListByProjectId']);
    Route::post('delete-document', [ProjectController::class, 'deleteDocument']);
    Route::post('comments-by-project-id', [ListController::class, 'getCommentsByProjectId']);
    Route::post('projects', [ListController::class, 'getProjectsForAdd']);
    Route::post('notification-list', [ListController::class, 'getNotificationList']);
    Route::post('user-details', [UserController::class, 'getUserDetails']);
    Route::post('update-user-details', [UserController::class, 'updateUserDetails']);
    Route::post('delete-project-task', [ProjectController::class, 'deleteProjectTask']);
    Route::post('update-notification-setting', [UserController::class, 'updateNotificationSetting']);
    Route::post('update-timezone', [UserController::class, 'updateTimezone']);
    Route::post('update-project', [ProjectController::class, 'updateProject']);
    Route::post('update-task', [ProjectController::class, 'updateTask']);
    Route::post('mark-as-read', [ProjectController::class, 'markAsReadNotification']);
    Route::post('update-default-reminder', [UserController::class, 'updateDefaultReminder']);
    Route::post('update-remind-via', [UserController::class, 'updateRemindVia']);
    Route::post('complete-uncomplete-task', [ProjectController::class, 'completeTask']);
    Route::post('send-to-review', [ProjectController::class, 'sendToReview']);
    Route::post('review-project-list', [ListController::class, 'getUnderReviewProjects']);
    Route::post('search-list', [ListController::class, 'searchProjectTask']);
    Route::post('associated-member-list', [ListController::class, 'associatedMemberList']);
    Route::post('assign-member-task', [ProjectController::class, 'assignMemberTask']);
    Route::post('complete-project', [ProjectController::class, 'completeProject']);
    Route::post('update-priority', [ProjectController::class, 'updatePriority']);
    Route::post('project-task-by-tag', [ListController::class, 'getProjectTaskByTag']);
    Route::post('delete-account', [ProjectController::class, 'deleteAccount']);
    Route::post('notification-count', [ListController::class, 'getNotificationCount']);
    Route::post('move-task', [ProjectController::class, 'moveTaskToParent']);
});
Route::group(['middleware' => 'LogAPIToken'], function () {
    Route::post('update-log-device-token', [LogUserController::class, 'updateDeviceToken']);
    Route::post('update-log-timezone', [LogUserController::class, 'updateTimezone']);
    Route::post('log-user-details', [LogUserController::class, 'getUserDetails']);
    Route::post('update-log-user-details', [LogUserController::class, 'updateUserDetails']);
    Route::post('add-log', [LogUserController::class, 'addLogData']);
    Route::post('add-log-temp', [LogUserController::class, 'addLogDataTemp']);
    Route::post('log-list', [LogUserController::class, 'logDataList']);
    Route::post('log-data-by-date', [LogUserController::class, 'getLogDataByDate']);
});
Route::post('log-user-registration', [LogUserController::class, 'registration']);
Route::post('log-verify-code', [LogUserController::class, 'verifyVerificationCode']);
Route::post('log-resend-code', [LogUserController::class, 'ResendVerificationCode']);
Route::post('log-user-login', [LogUserController::class, 'login']);
