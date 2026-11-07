<?php

return [
    'greeting' => 'Hi :name,',
    'footer' => 'Marketing School · marketingschool.az. This is an automated message about your account.',
    'verify_email' => [
        'subject' => 'Confirm your email — Marketing School',
        'lines' => ['To finish signing up, open the link below, confirm your email and set your password.', 'The link is valid for 24 hours and can be used once.'],
        'action' => 'Confirm email',
    ],
    'account_exists' => [
        'subject' => 'An account with this email already exists — Marketing School',
        'lines' => ['Someone (possibly you) tried to sign up again with this email. You already have an account.', 'Use the link to log in. If you have forgotten your password, choose "Forgot your password?" on the login page. If this was not you, you can ignore this email.'],
        'action' => 'Log in',
    ],
    'password_reset' => [
        'subject' => 'Reset your password — Marketing School',
        'lines' => ['Open the link to set a new password. It is valid for 1 hour and can be used once.', 'If you did not request this, you can ignore this email — your password will not change.'],
        'action' => 'Set a new password',
    ],
    'demo_registered' => [
        'subject' => 'Your free demo access — :course',
        'lines' => ['Your 24-hour free access to the first 2 modules of ":course" is ready. The 24 hours count from the moment you signed up.', 'Open the link to confirm your email and set a password. The link can be used once.', 'Video lessons are in Azerbaijani.'],
        'action' => 'Go to the demo',
    ],
    'demo_unavailable' => [
        'subject' => 'Your account has been created — Marketing School',
        'lines' => ['Your account has been created, but a free demo has already been used with this email or phone number. The demo is available only once on the platform.', 'Open the link to set your password; you can start with full access by buying ":course" (99 AZN/month).'],
        'action' => 'Set password',
    ],
    'demo_existing_account' => [
        'subject' => 'You already have an account — Marketing School',
        'lines' => ['The demo form was submitted with this email, but you already have an account, so no new account was created.', 'Log in: if you have not used your demo yet, you can start it from your dashboard. If you forgot your password, reset it on the login page.'],
        'action' => 'Log in',
    ],
    'demo_ending_soon' => [
        'subject' => '6 hours left in your demo — :course',
        'lines' => ['Your free demo of ":course" ends in 6 hours.', 'If you buy the course, the same account unlocks every module, homework, live sessions and 1:1 mentoring — you keep everything you watched and noted.'],
        'action' => 'Open the course',
    ],
    'demo_ended' => [
        'subject' => 'Your free demo has ended — :course',
        'lines' => ['Your 24-hour demo of ":course" has ended.', 'Buy the course to continue (99 AZN/month). Your progress and notes are saved, so you will pick up where you left off.'],
        'action' => 'Buy the course',
    ],
    'assignment_graded' => [
        'subject' => 'Your assignment has been graded — Marketing School',
        'lines' => ['Your assignment ":assignment" has been graded: :points / :max points.', 'You can read the instructor\'s feedback in the Homework tab on the lesson page.'],
        'action' => 'View feedback',
    ],
    'file_quarantined' => [
        'subject' => 'A file you uploaded has been quarantined',
        'lines' => ['The virus scan flagged a threat in ":file". The file has been quarantined and is not shown to your instructor.', 'Please upload a clean copy of the file again.'],
        'action' => 'Go to the platform',
    ],
    'diploma_issued' => [
        'subject' => 'Congratulations! Your diploma is ready — :course',
        'lines' => ['Your diploma for ":course" is ready.', 'You can download it at any time — it stays valid even after your paid period ends.'],
        'action' => 'Download diploma',
    ],
    'live_reminder_24h' => [
        'subject' => 'Live session tomorrow — :course',
        'lines' => ['The live Q&A session for ":course" takes place tomorrow, :when (Baku time).', 'The join link is on the Live sessions page of the platform. You can send your question in advance.'],
        'action' => 'Session page',
    ],
    'live_reminder_1h' => [
        'subject' => 'Live session starts in 1 hour — :course',
        'lines' => ['The ":course" live session starts at :when (Baku time).'],
        'action' => 'Join the session',
    ],
    'live_changed' => [
        'subject' => 'Live session time changed — :course',
        'lines' => ['The new time for the ":course" live session is :when (Baku time).', 'The attached calendar file will update your existing entry.'],
        'action' => 'Session page',
    ],
    'live_canceled' => [
        'subject' => 'Live session canceled — :course',
        'lines' => ['The ":course" live session on :when has been canceled.', 'The attached file will remove it from your calendar. Other sessions stay on the schedule.'],
        'action' => 'Session page',
    ],
    'mentoring_booked' => [
        'subject' => 'Your 1:1 mentoring session is confirmed — :course',
        'lines' => ['Your session: :when (Baku time).', 'The join link is on the 1:1 mentoring page of the platform. A calendar file is attached.'],
        'action' => 'Session page',
    ],
    'mentoring_booked_instructor' => [
        'subject' => 'New 1:1 booking — :course',
        'lines' => [':student has booked a 1:1 session with you for :when (Baku time).'],
        'action' => 'Mentoring panel',
    ],
    'mentoring_canceled' => [
        'subject' => '1:1 mentoring session canceled — :course',
        'lines' => ['The session on :when has been canceled. Your session right is kept — you can pick a new time.'],
        'action' => 'Pick a new time',
    ],
    'mentoring_reminder_24h' => [
        'subject' => 'Your 1:1 mentoring session is tomorrow',
        'lines' => ['Your session is at :when (Baku time).'],
        'action' => 'Session page',
    ],
    'mentoring_reminder_1h' => [
        'subject' => 'Your 1:1 session starts in 1 hour',
        'lines' => ['Your session starts at :when (Baku time).'],
        'action' => 'Join the session',
    ],
    'mentoring_unlocked' => [
        'subject' => '1:1 mentoring unlocked — :course',
        'lines' => ['Congratulations! You have completed every lesson of ":course".', 'You can now book a one-to-one mentoring session with your instructor.'],
        'action' => 'Pick a time',
    ],
    'payment_succeeded' => [
        'subject' => 'Payment received — :course',
        'lines' => ['Your payment of :amount for ":course" has been received.', 'Full access to the course is open until :until.'],
        'action' => 'Go to the course',
    ],
    'payment_failed' => [
        'subject' => 'Payment failed — :course',
        'lines' => ['Your monthly payment for ":course" did not go through.', 'You keep access during the grace period. Update your card or pay manually on the platform.'],
        'action' => 'Update payment',
    ],
    'renewal_upcoming' => [
        'subject' => 'Next payment in 5 days — :course',
        'lines' => [':amount for ":course" will be charged automatically on :until.', 'You can stop automatic payment at any time from your dashboard; the period you have already paid for is not cancelled.'],
        'action' => 'Dashboard',
    ],
    'pay_link' => [
        'subject' => 'Payment for next month — :course',
        'lines' => ['Your paid period for ":course" ends on :until.', 'To continue, pay for the next month (:amount).'],
        'action' => 'Pay',
    ],
    'grace_started' => [
        'subject' => 'Your paid period has ended — 3-day grace period',
        'lines' => ['Your paid period for ":course" has ended.', 'Access is kept until :until. Please renew your payment so your access is not closed.'],
        'action' => 'Renew payment',
    ],
    'grace_ended' => [
        'subject' => 'Course access closed — :course',
        'lines' => ['The grace period has ended and access to ":course" is now closed.', 'Your progress, assignments, scores, notes and diploma are kept. When you pay, you will pick up where you left off.'],
        'action' => 'Buy the course again',
    ],
    'subscription_canceled' => [
        'subject' => 'Automatic payment stopped — :course',
        'lines' => ['Automatic monthly payment for ":course" has been stopped.', 'The period you paid for is not cancelled: access continues until :until (+3-day grace period).'],
        'action' => 'Dashboard',
    ],
    'refund_processed' => [
        'subject' => 'Refund — :course',
        'lines' => ['Your payment for ":course" has been refunded and the period it covered has been cancelled.', 'Your academic records (progress, scores, diploma) are kept.'],
        'action' => 'Dashboard',
    ],
];
