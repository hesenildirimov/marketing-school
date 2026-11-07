<?php

return [
    'greeting' => 'Salam, :name!',
    'footer' => 'Marketing School · marketingschool.az. Bu məktub hesabınızla bağlı avtomatik göndərilib.',
    'verify_email' => [
        'subject' => 'E-mailinizi təsdiqləyin — Marketing School',
        'lines' => ['Qeydiyyatı tamamlamaq üçün aşağıdakı linkə keçin, e-mailinizi təsdiqləyin və parol təyin edin.', 'Link 24 saat etibarlıdır və bir dəfə istifadə olunur.'],
        'action' => 'E-maili təsdiqlə',
    ],
    'account_exists' => [
        'subject' => 'Bu e-maillə hesab artıq var — Marketing School',
        'lines' => ['Kimsə (bəlkə siz) bu e-maillə yenidən qeydiyyatdan keçməyə çalışdı. Hesabınız artıq mövcuddur.', 'Daxil olmaq üçün linkə keçin. Parolu unutmusunuzsa, giriş səhifəsində "Parolu unutmusunuz?" seçin. Bu siz deyildinizsə, məktuba məhəl qoymayın.'],
        'action' => 'Daxil ol',
    ],
    'password_reset' => [
        'subject' => 'Parolun bərpası — Marketing School',
        'lines' => ['Parolunuzu yeniləmək üçün linkə keçin. Link 1 saat etibarlıdır və bir dəfə istifadə olunur.', 'Bu sorğunu siz göndərməmisinizsə, məktuba məhəl qoymayın — parolunuz dəyişməyəcək.'],
        'action' => 'Yeni parol təyin et',
    ],
    'demo_registered' => [
        'subject' => 'Pulsuz demo girişiniz — :course',
        'lines' => ['":course" kursunun ilk 2 moduluna 24 saatlıq pulsuz girişiniz açıldı. 24 saat qeydiyyat anından hesablanır.', 'Linkə keçin, e-mailinizi təsdiqləyin və parol təyin edin. Link bir dəfə istifadə olunur.', 'Videodərslər Azərbaycan dilindədir.'],
        'action' => 'Demoya keç',
    ],
    'demo_unavailable' => [
        'subject' => 'Hesabınız yaradıldı — Marketing School',
        'lines' => ['Hesabınız yaradıldı, lakin bu e-mail və ya telefon nömrəsi ilə pulsuz demo artıq istifadə olunub. Demo platformada yalnız bir dəfə verilir.', 'Linkə keçib parol təyin edin; ":course" kursunu alaraq tam girişlə başlaya bilərsiniz (99 AZN/ay).'],
        'action' => 'Parol təyin et',
    ],
    'demo_existing_account' => [
        'subject' => 'Sizin artıq hesabınız var — Marketing School',
        'lines' => ['Bu e-maillə demo forması göndərildi, amma hesabınız artıq mövcuddur, ona görə yeni hesab yaradılmadı.', 'Daxil olun: demonu hələ istifadə etməmisinizsə, kabinetdən başlada bilərsiniz. Parolu unutmusunuzsa, giriş səhifəsində bərpa edin.'],
        'action' => 'Daxil ol',
    ],
    'demo_ending_soon' => [
        'subject' => 'Demonun bitməsinə 6 saat qalıb — :course',
        'lines' => ['":course" kursu üzrə pulsuz demonuzun bitməsinə 6 saat qalıb.', 'Kursu alsanız, eyni hesabla bütün modullar, ev tapşırıqları, canlı görüşlər və 1:1 mentorluq açılır; izlədiklərinizi və qeydlərinizi itirməyəcəksiniz.'],
        'action' => 'Kursa keç',
    ],
    'demo_ended' => [
        'subject' => 'Pulsuz demo bitdi — :course',
        'lines' => ['":course" kursu üzrə 24 saatlıq demonuz bitdi.', 'Davam etmək üçün kursu alın (99 AZN/ay). Progress və qeydləriniz saxlanılıb — qaldığınız yerdən davam edəcəksiniz.'],
        'action' => 'Kursu al',
    ],
    'assignment_graded' => [
        'subject' => 'Tapşırığınız qiymətləndirildi — Marketing School',
        'lines' => ['":assignment" tapşırığınız qiymətləndirildi: :points / :max bal.', 'Təlimçinin rəyini dərs səhifəsindəki "Ev tapşırığı" bölməsində görə bilərsiniz.'],
        'action' => 'Rəyə bax',
    ],
    'file_quarantined' => [
        'subject' => 'Yüklədiyiniz fayl karantinə alındı',
        'lines' => ['":file" faylında antivirus yoxlaması zamanı təhlükə aşkarlandı. Fayl karantinə alındı və təlimçiyə göstərilmir.', 'Faylı yenidən, təmiz nüsxə ilə yükləyin.'],
        'action' => 'Platformaya keç',
    ],
    'diploma_issued' => [
        'subject' => 'Təbriklər! Diplomunuz hazırdır — :course',
        'lines' => ['":course" kursu üzrə diplomunuz hazırdır.', 'Diplomu istənilən vaxt yükləyə bilərsiniz — ödəniş müddəti bitsə belə diplom qüvvədə qalır.'],
        'action' => 'Diplomu yüklə',
    ],
    'live_reminder_24h' => [
        'subject' => 'Sabah canlı görüş var — :course',
        'lines' => ['":course" kursu üzrə canlı sual-cavab görüşü sabah, :when (Bakı vaxtı) keçiriləcək.', 'Qoşulma linki platformadakı "Canlı görüşlər" səhifəsindədir. Sualınızı əvvəlcədən göndərə bilərsiniz.'],
        'action' => 'Görüş səhifəsi',
    ],
    'live_reminder_1h' => [
        'subject' => 'Canlı görüş 1 saatdan sonra başlayır — :course',
        'lines' => ['":course" canlı görüşü :when (Bakı vaxtı) başlayır.'],
        'action' => 'Görüşə qoşul',
    ],
    'live_changed' => [
        'subject' => 'Canlı görüşün vaxtı dəyişdi — :course',
        'lines' => ['":course" canlı görüşünün yeni vaxtı: :when (Bakı vaxtı).', 'Əlavə olunmuş təqvim faylı köhnə qeydi yeniləyəcək.'],
        'action' => 'Görüş səhifəsi',
    ],
    'live_canceled' => [
        'subject' => 'Canlı görüş ləğv edildi — :course',
        'lines' => ['":course" kursunun :when tarixli canlı görüşü ləğv edildi.', 'Əlavə olunmuş fayl təqvimdəki qeydi siləcək. Növbəti görüşlər cədvəldə qalır.'],
        'action' => 'Görüş səhifəsi',
    ],
    'mentoring_booked' => [
        'subject' => '1:1 mentorluq sessiyanız təsdiqləndi — :course',
        'lines' => ['Sessiyanız: :when (Bakı vaxtı).', 'Qoşulma linki platformadakı "1:1 mentorluq" səhifəsindədir. Təqvim faylı əlavə olunub.'],
        'action' => 'Sessiya səhifəsi',
    ],
    'mentoring_booked_instructor' => [
        'subject' => 'Yeni 1:1 rezerv — :course',
        'lines' => [':student sizinlə :when (Bakı vaxtı) üçün 1:1 sessiya rezerv etdi.'],
        'action' => 'Mentorluq paneli',
    ],
    'mentoring_canceled' => [
        'subject' => '1:1 mentorluq sessiyası ləğv edildi — :course',
        'lines' => [':when tarixli sessiya ləğv edildi. Hüququnuz qorunur — yeni vaxt seçə bilərsiniz.'],
        'action' => 'Yeni vaxt seç',
    ],
    'mentoring_reminder_24h' => [
        'subject' => 'Sabah 1:1 mentorluq sessiyanız var',
        'lines' => ['Sessiyanız :when (Bakı vaxtı).'],
        'action' => 'Sessiya səhifəsi',
    ],
    'mentoring_reminder_1h' => [
        'subject' => '1:1 sessiya 1 saatdan sonra başlayır',
        'lines' => ['Sessiyanız :when (Bakı vaxtı) başlayır.'],
        'action' => 'Sessiyaya qoşul',
    ],
    'mentoring_unlocked' => [
        'subject' => '1:1 mentorluq açıldı — :course',
        'lines' => ['Təbriklər! ":course" kursunun bütün dərslərini tamamladınız.', 'İndi təlimçi ilə fərdi (1:1) mentorluq sessiyası rezerv edə bilərsiniz.'],
        'action' => 'Vaxt seç',
    ],
    'payment_succeeded' => [
        'subject' => 'Ödəniş qəbul edildi — :course',
        'lines' => ['":course" kursu üzrə :amount ödənişiniz qəbul edildi.', 'Kursa tam giriş :until tarixinədək açıqdır.'],
        'action' => 'Kursa keç',
    ],
    'payment_failed' => [
        'subject' => 'Ödəniş alınmadı — :course',
        'lines' => ['":course" kursu üzrə aylıq ödəniş alınmadı.', 'Güzəşt müddətində girişiniz qalır. Kart məlumatını yeniləyin və ya platformadan əl ilə ödəyin.'],
        'action' => 'Ödənişi yenilə',
    ],
    'renewal_upcoming' => [
        'subject' => 'Növbəti ödənişə 5 gün qalıb — :course',
        'lines' => [':until tarixində ":course" kursu üzrə :amount avtomatik çıxılacaq.', 'Avtomatik ödənişi istənilən vaxt kabinetdən dayandıra bilərsiniz; ödənilmiş müddət ləğv edilmir.'],
        'action' => 'Kabinet',
    ],
    'pay_link' => [
        'subject' => 'Növbəti ay üçün ödəniş — :course',
        'lines' => ['":course" kursu üzrə ödənilmiş müddət :until tarixində bitir.', 'Davam etmək üçün növbəti ayı ödəyin (:amount).'],
        'action' => 'Ödə',
    ],
    'grace_started' => [
        'subject' => 'Ödəniş müddəti bitdi — 3 günlük güzəşt',
        'lines' => ['":course" kursu üzrə ödənilmiş müddət bitdi.', 'Giriş :until tarixinədək saxlanılır. Ödənişi yeniləyin ki, kursa giriş bağlanmasın.'],
        'action' => 'Ödənişi yenilə',
    ],
    'grace_ended' => [
        'subject' => 'Kursa giriş bağlandı — :course',
        'lines' => ['Güzəşt müddəti bitdi və ":course" kursuna giriş bağlandı.', 'Progress, tapşırıqlar, ballar, qeydlər və diplom saxlanılır. Ödəniş edəndə qaldığınız yerdən davam edəcəksiniz.'],
        'action' => 'Kursu yenidən al',
    ],
    'subscription_canceled' => [
        'subject' => 'Avtomatik ödəniş dayandırıldı — :course',
        'lines' => ['":course" kursu üzrə avtomatik aylıq ödəniş dayandırıldı.', 'Ödənilmiş müddət ləğv edilmir: giriş :until tarixinədək (+3 gün güzəşt) qalır.'],
        'action' => 'Kabinet',
    ],
    'refund_processed' => [
        'subject' => 'Geri qaytarma — :course',
        'lines' => ['":course" kursu üzrə ödəniş geri qaytarıldı. Ödənişin əhatə etdiyi müddət ləğv edildi.', 'Akademik məlumatlarınız (progress, ballar, diplom) saxlanılır.'],
        'action' => 'Kabinet',
    ],
];
