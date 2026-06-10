<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\AuthController;
use App\Http\Controllers\MeetingRoomController;
use App\Http\Controllers\ReservationController;
use App\Http\Controllers\ApprovalController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\DeviceTagController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\TenantController;
use App\Http\Controllers\AdminReportController;
use App\Http\Controllers\OAuthController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\ApprovalCallbackController;

Route::post('/login', [AuthController::class, 'login']);

// OAuth登录路由
Route::get('/oauth/config', [OAuthController::class, 'getOAuthConfig']);
Route::get('/oauth/jsapi-config', [OAuthController::class, 'getJsApiConfig']);
Route::get('/oauth/callback/dingtalk', [OAuthController::class, 'dingtalkCallback']);
Route::get('/oauth/callback/feishu', [OAuthController::class, 'feishuCallback']);

// 组织架构同步路由
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/organization/department-tree', [OrganizationController::class, 'getDepartmentTree']);
    Route::get('/organization/users-by-department', [OrganizationController::class, 'getUsersByDepartment']);
    Route::post('/organization/sync', [OrganizationController::class, 'sync']);
    Route::post('/organization/sync-to-local', [OrganizationController::class, 'syncToLocal']);
});

// 审批回调路由（不需要认证，由IM平台验证，需要携带tenant_id）
Route::post('/callback/approval/dingtalk', [ApprovalCallbackController::class, 'dingtalkCallback']);
Route::post('/callback/approval/feishu', [ApprovalCallbackController::class, 'feishuCallback']);
Route::get('/callback/verify', [ApprovalCallbackController::class, 'verify']);

Route::get('/pad/{id}', [MeetingRoomController::class, 'padDisplay']);
Route::post('/pad/checkin/{roomCode}', [MeetingRoomController::class, 'padCheckin']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    Route::middleware('check.tenant')->group(function () {
        Route::get('/meeting-rooms', [MeetingRoomController::class, 'index']);
        Route::get('/meeting-rooms/floors', [MeetingRoomController::class, 'getFloors']);
        Route::post('/meeting-rooms/check-availability', [MeetingRoomController::class, 'checkAvailability']);
        Route::get('/meeting-rooms/{id}', [MeetingRoomController::class, 'show']);
        Route::post('/meeting-rooms', [MeetingRoomController::class, 'store']);
        Route::put('/meeting-rooms/{id}', [MeetingRoomController::class, 'update']);
        Route::delete('/meeting-rooms/{id}', [MeetingRoomController::class, 'destroy']);

        Route::get('/reservations', [ReservationController::class, 'index']);
        Route::get('/reservations/{id}', [ReservationController::class, 'show']);
        Route::post('/reservations', [ReservationController::class, 'store']);
        Route::put('/reservations/{id}', [ReservationController::class, 'update']);
        Route::delete('/reservations/{id}', [ReservationController::class, 'destroy']);
        Route::post('/reservations/{id}/checkin', [ReservationController::class, 'checkin']);
        Route::post('/reservations/{id}/checkout', [ReservationController::class, 'checkout']);
        Route::post('/reservations/{id}/extend', [ReservationController::class, 'extend']);
        Route::get('/reservations/{id}/export-ics', [ReservationController::class, 'exportICS']);

        Route::get('/approvals', [ApprovalController::class, 'index']);
        Route::get('/approvals/{id}', [ApprovalController::class, 'show']);
        Route::post('/approvals/{id}/approve', [ApprovalController::class, 'approve']);
        Route::post('/approvals/{id}/reject', [ApprovalController::class, 'reject']);
        Route::post('/approvals/{id}/remind', [ApprovalController::class, 'remind']);

        Route::get('/favorites', [FavoriteController::class, 'index']);
        Route::post('/favorites', [FavoriteController::class, 'store']);
        Route::delete('/favorites/{meetingRoomId}', [FavoriteController::class, 'destroy']);

        Route::get('/reports/utilization', [ReportController::class, 'roomUtilization']);
        Route::get('/reports/department-ranking', [ReportController::class, 'departmentRanking']);
        Route::get('/reports/no-show-rate', [ReportController::class, 'noShowRate']);
        Route::get('/reports/overview', [ReportController::class, 'overview']);

        Route::get('/users', [UserController::class, 'index']);
        Route::post('/users', [UserController::class, 'store']);
        Route::get('/users/departments', [UserController::class, 'getDepartments']);
        Route::get('/users/available-attendees', [UserController::class, 'getAvailableAttendees']);
        Route::post('/users/import', [UserController::class, 'import']);
        Route::get('/users/{id}', [UserController::class, 'show']);
        Route::put('/users/{id}', [UserController::class, 'update']);
        Route::delete('/users/{id}', [UserController::class, 'destroy']);

        Route::get('/device-tags', [DeviceTagController::class, 'index']);
        Route::post('/device-tags', [DeviceTagController::class, 'store']);
        Route::put('/device-tags/{id}', [DeviceTagController::class, 'update']);
        Route::delete('/device-tags/{id}', [DeviceTagController::class, 'destroy']);

        Route::get('/departments', [DepartmentController::class, 'index']);
        Route::post('/departments', [DepartmentController::class, 'store']);
        Route::put('/departments/{id}', [DepartmentController::class, 'update']);
        Route::delete('/departments/{id}', [DepartmentController::class, 'destroy']);

        // IM配置路由（租户管理员）
        Route::get('/tenants/im-config', [TenantController::class, 'getIMConfig']);
        Route::put('/tenants/im-config', [TenantController::class, 'updateIMConfig']);
    });

    Route::prefix('admin')->middleware('super_admin')->group(function () {
        Route::get('/reports/overview', [AdminReportController::class, 'overview']);
        Route::get('/reports/tenants', [AdminReportController::class, 'allTenantsOverview']);
        Route::get('/tenants/{id}/stats', [AdminReportController::class, 'tenantStats']);

        Route::get('/tenants', [TenantController::class, 'index']);
        Route::get('/tenants/{id}', [TenantController::class, 'show']);
        Route::post('/tenants', [TenantController::class, 'store']);
        Route::put('/tenants/{id}', [TenantController::class, 'update']);
        Route::put('/tenants/{id}/renew', [TenantController::class, 'renew']);
        Route::delete('/tenants/{id}', [TenantController::class, 'destroy']);
        Route::post('/tenants/{id}/create-admin', [TenantController::class, 'createAdmin']);
    });
});
