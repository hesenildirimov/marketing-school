<?php

use App\Http\Controllers;
use App\Http\Controllers\Auth;
use Illuminate\Support\Facades\Route;

Route::prefix('platform')->group(function () {
    Route::get('/', fn () => redirect()->route(auth()->check() ? 'dashboard' : 'login'));

    // ---- Qonaq (giriş, qeydiyyat, təsdiq, parol bərpası) ----
    Route::middleware('guest')->group(function () {
        Route::get('login', [Auth\LoginController::class, 'show'])->name('login');
        Route::post('login', [Auth\LoginController::class, 'login'])->middleware('throttle:login');
        Route::get('register', [Auth\RegisterController::class, 'show'])->name('register');
        Route::post('register', [Auth\RegisterController::class, 'store'])->middleware('throttle:register');
        Route::get('password/forgot', [Auth\PasswordResetController::class, 'showForgot'])->name('password.forgot');
        Route::post('password/forgot', [Auth\PasswordResetController::class, 'send'])->middleware('throttle:forgot');
    });
    Route::get('legal/{page}', fn (string $page) => view('legal', ['page' => $page]))->whereIn('page', ['privacy', 'terms'])->name('legal');
    Route::view('check-email', 'auth.check-email')->name('check-email');
    Route::post('api/payments/webhook/{provider}', [Controllers\BillingController::class, 'webhook'])->middleware('throttle:webhook')->name('payments.webhook');
    Route::get('diplom/{verifyId}', [Controllers\CertificateController::class, 'verify'])->middleware('throttle:verify-diploma')->name('certificates.verify');

    // ---- Demo (bölmə 2.2) ----
    Route::get('demo', [Controllers\DemoController::class, 'form'])->name('demo.form');
    Route::post('api/demo/register', [Controllers\DemoController::class, 'register'])->middleware('throttle:demo')->name('demo.register');
    Route::get('demo/resend', [Controllers\DemoController::class, 'resendForm'])->name('demo.resend.form');
    Route::post('demo/resend', [Controllers\DemoController::class, 'resend'])->middleware('throttle:resend')->name('demo.resend');
    Route::get('verify', [Auth\VerifyController::class, 'show'])->name('verify.show');
    Route::post('verify', [Auth\VerifyController::class, 'complete'])->middleware('throttle:token')->name('verify.complete');
    Route::get('password/reset', [Auth\PasswordResetController::class, 'showReset'])->name('password.reset');
    Route::post('password/reset', [Auth\PasswordResetController::class, 'reset'])->middleware('throttle:token');

    // ---- Autentifikasiya olunmuş ----
    Route::middleware(['auth', 'session.valid'])->group(function () {
        Route::post('logout', [Auth\LoginController::class, 'logout'])->name('logout');
        Route::get('2fa', [Auth\TwoFactorController::class, 'challenge'])->name('2fa.challenge');
        Route::post('2fa', [Auth\TwoFactorController::class, 'verify'])->middleware('throttle:2fa');

        Route::middleware('2fa')->group(function () {
            Route::get('reauth', [Auth\ReauthController::class, 'show'])->name('reauth');
            Route::post('reauth', [Auth\ReauthController::class, 'confirm'])->middleware('throttle:login');
            Route::get('dashboard', [Controllers\CourseController::class, 'dashboard'])->name('dashboard');
            Route::get('courses/{course}', [Controllers\CourseController::class, 'show'])->name('course.show');
            Route::post('assignments/{assignment}/submit', [Controllers\AssignmentController::class, 'submit'])->middleware('throttle:upload')->name('assignments.submit');
            Route::get('files/{file}', [Controllers\FileController::class, 'download'])->name('files.download');
            Route::get('certificates/{certificate}/pdf', [Controllers\CertificateController::class, 'pdf'])->name('certificates.pdf');
            Route::get('courses/{course}/live', [Controllers\LiveController::class, 'index'])->name('live.index');
            Route::get('live/{session}/ics', [Controllers\LiveController::class, 'ics'])->name('live.ics');
            Route::post('live/{session}/questions', [Controllers\LiveController::class, 'ask'])->middleware('throttle:api')->name('live.ask');
            Route::get('live/{session}/recording', [Controllers\LiveController::class, 'recording'])->name('live.recording');
            Route::post('api/live/{session}/playback', [Controllers\LiveController::class, 'playback'])->middleware('throttle:video')->name('api.live.playback');
            Route::get('courses/{course}/mentoring', [Controllers\MentoringController::class, 'index'])->name('mentoring.index');
            Route::post('courses/{course}/mentoring', [Controllers\MentoringController::class, 'book'])->name('mentoring.book');
            Route::post('mentoring/{booking}/cancel', [Controllers\MentoringController::class, 'cancel'])->name('mentoring.cancel');
            Route::post('mentoring/{booking}/reschedule', [Controllers\MentoringController::class, 'reschedule'])->name('mentoring.reschedule');
            Route::post('billing/checkout/{course}', [Controllers\BillingController::class, 'checkout'])->middleware('throttle:api')->name('billing.checkout');
            Route::get('billing/return/{order}', [Controllers\BillingController::class, 'return'])->name('billing.return');
            Route::get('api/billing/orders/{order}', [Controllers\BillingController::class, 'status'])->name('api.billing.status');
            Route::post('billing/subscriptions/{subscription}/cancel', [Controllers\BillingController::class, 'cancelAutoRenew'])->name('billing.cancel');
            Route::post('billing/subscriptions/{subscription}/resume', [Controllers\BillingController::class, 'resumeAutoRenew'])->name('billing.resume');
            Route::post('demo/start/{course}', [Controllers\DemoController::class, 'start'])->name('demo.start');
            Route::get('courses/{course}/lessons/{lesson}', [Controllers\CourseController::class, 'lesson'])->name('lesson.show');

            Route::prefix('api')->middleware('throttle:api')->group(function () {
                Route::post('lessons/{lesson}/playback', [Controllers\Api\LessonApiController::class, 'playback'])->middleware('throttle:video')->name('api.playback');
                Route::post('lessons/{lesson}/progress', [Controllers\Api\LessonApiController::class, 'progress'])->middleware('throttle:progress')->name('api.progress');
                Route::post('lessons/{lesson}/complete', [Controllers\Api\LessonApiController::class, 'complete'])->name('api.complete');
                Route::get('lessons/{lesson}/notes', [Controllers\Api\LessonApiController::class, 'notes'])->name('api.notes');
                Route::post('lessons/{lesson}/notes', [Controllers\Api\LessonApiController::class, 'storeNote'])->name('api.notes.store');
                Route::delete('lessons/{lesson}/notes/{note}', [Controllers\Api\LessonApiController::class, 'deleteNote'])->name('api.notes.delete');
                Route::get('assignments/{assignment}', [Controllers\AssignmentController::class, 'show'])->name('api.assignments.show');
                Route::post('assignments/{assignment}/uploads', [Controllers\AssignmentController::class, 'uploadInit'])->middleware('throttle:upload')->name('api.uploads.init');
                Route::put('uploads/{upload}', [Controllers\AssignmentController::class, 'uploadChunk'])->name('api.uploads.chunk');
                Route::post('uploads/{upload}/complete', [Controllers\AssignmentController::class, 'uploadComplete'])->middleware('throttle:upload')->name('api.uploads.complete');
                Route::get('courses/{course}/scores', [Controllers\AssignmentController::class, 'scores'])->name('api.scores');
                Route::post('lessons/{lesson}/metrics', [Controllers\Api\LessonApiController::class, 'metrics'])->middleware('throttle:progress')->name('api.metrics');
            });
            Route::get('account/sessions', [Controllers\AccountController::class, 'sessions'])->name('account.sessions');
            Route::delete('account/sessions/{id}', [Controllers\AccountController::class, 'revokeSession'])->name('account.sessions.revoke');
            Route::get('account/privacy', [Controllers\PrivacyController::class, 'show'])->name('account.privacy');
            Route::get('account/privacy/export', [Controllers\PrivacyController::class, 'export'])->middleware('reauth')->name('account.export');
            Route::delete('account', [Controllers\PrivacyController::class, 'destroy'])->middleware('reauth')->name('account.destroy');
            Route::get('notifications', [Controllers\NotificationController::class, 'index'])->name('notifications.index');
            Route::post('account/locale', [Controllers\AccountController::class, 'locale'])->name('account.locale');

            Route::prefix('admin')->name('admin.')->middleware('role:admin,instructor')->group(function () {
                Route::get('/', [Controllers\Admin\AdminController::class, 'home'])->name('home');
                Route::get('courses', [Controllers\Admin\CourseAdminController::class, 'index'])->name('courses.index');
                Route::get('live', [Controllers\Admin\LiveAdminController::class, 'index'])->name('live.index');
                Route::post('live/generate/{course}', [Controllers\Admin\LiveAdminController::class, 'generate'])->name('live.generate');
                Route::get('live/{session}', [Controllers\Admin\LiveAdminController::class, 'edit'])->name('live.edit');
                Route::put('live/{session}', [Controllers\Admin\LiveAdminController::class, 'update'])->name('live.update');
                Route::post('live/{session}/cancel', [Controllers\Admin\LiveAdminController::class, 'cancel'])->name('live.cancel');
                Route::post('live/{session}/recording', [Controllers\Admin\LiveAdminController::class, 'recordingTicket'])->name('live.recording');
                Route::get('mentoring', [Controllers\Admin\MentoringAdminController::class, 'index'])->name('mentoring.index');
                Route::post('mentoring/slots', [Controllers\Admin\MentoringAdminController::class, 'store'])->name('mentoring.store');
                Route::delete('mentoring/slots/{slot}', [Controllers\Admin\MentoringAdminController::class, 'destroy'])->name('mentoring.destroy');
                Route::post('mentoring/bookings/{booking}', [Controllers\Admin\MentoringAdminController::class, 'mark'])->name('mentoring.mark');
                Route::get('grading', [Controllers\Admin\GradingController::class, 'index'])->name('grading.index');
                Route::get('grading/{submission}', [Controllers\Admin\GradingController::class, 'show'])->name('grading.show');
                Route::post('grading/{submission}', [Controllers\Admin\GradingController::class, 'grade'])->name('grading.grade');
                Route::post('courses/{course}/modules/{module}/assignments', [Controllers\Admin\AssignmentAdminController::class, 'store'])->name('assignments.store');
                Route::get('courses/{course}/assignments/{assignment}', [Controllers\Admin\AssignmentAdminController::class, 'edit'])->name('assignments.edit');
                Route::put('courses/{course}/assignments/{assignment}', [Controllers\Admin\AssignmentAdminController::class, 'update'])->name('assignments.update');
                Route::put('courses/{course}/assignments/{assignment}/scoring', [Controllers\Admin\AssignmentAdminController::class, 'scoring'])->name('assignments.scoring');
                Route::middleware('role:admin')->group(function () {
                    Route::get('certificates', [Controllers\Admin\GradingController::class, 'certificates'])->name('certificates.index');
                    Route::post('certificates/{certificate}/revoke', [Controllers\Admin\GradingController::class, 'revoke'])->middleware('reauth')->name('certificates.revoke');
                    Route::get('students', [Controllers\Admin\StudentAdminController::class, 'index'])->name('students.index');
                    Route::get('students/{user}', [Controllers\Admin\StudentAdminController::class, 'show'])->name('students.show');
                    Route::post('students/{user}/sessions/revoke', [Controllers\Admin\StudentAdminController::class, 'revokeSessions'])->name('students.revoke');
                    Route::post('enrollments/{enrollment}/cancel', [Controllers\Admin\StudentAdminController::class, 'cancelEnrollment'])->middleware('reauth')->name('enrollments.cancel');
                    Route::get('notifications', [Controllers\Admin\SystemAdminController::class, 'notifications'])->name('notifications.index');
                    Route::post('notifications/{notification}/resend', [Controllers\Admin\SystemAdminController::class, 'resend'])->name('notifications.resend');
                    Route::get('audit', [Controllers\Admin\SystemAdminController::class, 'audit'])->name('audit');
                    Route::get('analytics', [Controllers\Admin\SystemAdminController::class, 'analytics'])->name('analytics');
                    Route::get('billing', [Controllers\Admin\BillingAdminController::class, 'index'])->name('billing.index');
                    Route::post('billing/webhooks/{event}/retry', [Controllers\Admin\BillingAdminController::class, 'retryWebhook'])->name('billing.retry');
                    Route::post('billing/orders/{order}/refund', [Controllers\Admin\BillingAdminController::class, 'refund'])->middleware('reauth')->name('billing.refund');
                    Route::post('billing/activate', [Controllers\Admin\BillingAdminController::class, 'activate'])->middleware('reauth')->name('billing.activate');
                    Route::put('billing/rules', [Controllers\Admin\BillingAdminController::class, 'rules'])->middleware('reauth')->name('billing.rules');
                    Route::post('billing/prices', [Controllers\Admin\BillingAdminController::class, 'price'])->middleware('reauth')->name('billing.price');
                    Route::get('demos', [Controllers\Admin\DemoAdminController::class, 'index'])->name('demos.index');
                    Route::post('demos/{user}/resend', [Controllers\Admin\DemoAdminController::class, 'resend'])->name('demos.resend');
                });
                Route::get('courses/{course}', [Controllers\Admin\CourseAdminController::class, 'show'])->name('courses.show');
                Route::post('courses/{course}/draft', [Controllers\Admin\CourseAdminController::class, 'createDraft'])->name('courses.draft');
                Route::post('courses/{course}/publish', [Controllers\Admin\CourseAdminController::class, 'publish'])->middleware('reauth')->name('courses.publish');
                Route::post('courses/{course}/modules', [Controllers\Admin\CourseAdminController::class, 'storeModule'])->name('modules.store');
                Route::put('courses/{course}/modules/{module}', [Controllers\Admin\CourseAdminController::class, 'updateModule'])->name('modules.update');
                Route::post('courses/{course}/modules/{module}/action', [Controllers\Admin\CourseAdminController::class, 'moduleAction'])->name('modules.action');
                Route::post('courses/{course}/modules/{module}/lessons', [Controllers\Admin\CourseAdminController::class, 'storeLesson'])->name('lessons.store');
                Route::get('courses/{course}/lessons/{lesson}', [Controllers\Admin\CourseAdminController::class, 'editLesson'])->name('lessons.edit');
                Route::put('courses/{course}/lessons/{lesson}', [Controllers\Admin\CourseAdminController::class, 'updateLesson'])->name('lessons.update');
                Route::post('courses/{course}/lessons/{lesson}/action', [Controllers\Admin\CourseAdminController::class, 'lessonAction'])->name('lessons.action');
                Route::post('courses/{course}/lessons/{lesson}/video', [Controllers\Admin\CourseAdminController::class, 'uploadTicket'])->name('lessons.video');
                Route::post('courses/{course}/lessons/{lesson}/video/refresh', [Controllers\Admin\CourseAdminController::class, 'refreshVideo'])->name('lessons.video.refresh');
            });
        });
    });
});
