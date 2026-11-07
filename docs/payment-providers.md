# Epoint və Payriff — müqayisə (bölmə 2.10, ilk addım)

> **Diqqət:** bu sessiyada `epoint.az`, `payriff.com` və `docs.payriff.com` saytları şəbəkə siyasəti ilə bloklanıb.
> Cədvəl açıq mənbəli SDK-ların sənədlərinə (PyPI `epoint`, `integrify-epoint`; `payriff-ruby`, `payriff` (Python/npm/PHP)) əsaslanır.
> ✅ = SDK-larda təsdiqlənir; ⚠️ = rəsmi sənəddə/müqavilədə yoxlanmalıdır. Staging-də sandbox açarları ilə real yoxlama aparılmalıdır.

| Meyar | Epoint | Payriff |
|---|---|---|
| Ödəniş yaratma | `POST /api/1/request` (`data` = base64 JSON, `signature`) ✅ | `POST /api/v3/orders` (Authorization: secret key) ✅ |
| Kart tokenizasiyası / avtomatik aylıq | Kart qeydiyyatı (`card-registration`, ödənişlə birlikdə variant) + saxlanmış kartla `execute-pay` ✅ (endpoint adları ⚠️) | `save_card` + callback-də `cardUuid`, sonra `POST /api/v3/autoPay` ✅ |
| Webhook (callback) imzası | **Var**: `signature = base64(sha1(private_key + data + private_key))` (xam 20 bayt) ✅ | **Yoxdur** (HMAC/shared secret yoxdur) — status mütləq API ilə yoxlanmalıdır ✅ |
| Server-to-server status sorğusu | `get-status` ✅ | Sifariş məlumatı API ✅ |
| Geri qaytarma (refund) | Var; SDK-ya görə refund/reverse yalnız AZN ✅ (endpoint ⚠️) | `POST /api/v3/refund` (tam/qismən) ✅; 200 cavabda da gövdədəki kodu yoxlamaq lazımdır |
| Chargeback | Bank prosesi; API hadisəsi ⚠️ (merchant kabinetindən/e-maildən) | ⚠️ eyni |
| Komissiya | Müqavilə ilə ⚠️ (açıq sənəddə yoxdur) | Müqavilə ilə ⚠️ |
| Sınaq (sandbox) | Test açarları/rejimi ⚠️ | Test mühiti ⚠️ |
| Valyuta | AZN (+ checkout-da başqaları) | AZN |

## Tövsiyə: **Epoint**
1. Callback-lər imzalıdır, ona görə webhook-un saxtalaşdırılması imza ilə də bloklanır. Payriff-də isə yalnız status sorğusuna etibar etmək olar.
2. Saxlanmış kartla server-to-server çıxarış (`execute-pay`) aylıq 99 AZN modelinə uyğundur.
3. Hər iki provayder `PaymentProvider` adapteri arxasındadır. Payriff-ə keçid biznes məntiqini dəyişmir (yeni adapter sinfi ~150 sətir).

Hər iki halda platforma **imzaya təkbaşına etibar etmir**: webhook-dan sonra həmişə `fetchStatus` (server-to-server) ilə təsdiq alınır (bölmə 2.10).
SHA-1 əsaslı imza zəif sayılır; bu, provayderin sxemidir. Kompensasiya üçün status sorğusu məcburidir.

## Ehtiyat axın
Provayder saxlanmış kartla çıxarışı dəstəkləməsə (və ya `PAYMENT_PROVIDER=manual` olsa), `paid_until`-dən 5 gün əvvəl ödəniş linki e-maillə göndərilir (`renewal_upcoming` → `pay_link`).

## Mənbələr
- [PyPI: epoint](https://pypi.org/project/epoint/), [PyPI: integrify-epoint](https://pypi.org/project/integrify-epoint/1.0.0/)
- [GitHub: payriff-ruby wiki](https://github.com/payriff/payriff-ruby/wiki), [PyPI: payriff](https://pypi.org/project/payriff/), [npm: payriff](https://www.npmjs.com/package/payriff), [Packagist: payriff-php](https://packagist.org/packages/payriff/payriff-php)
