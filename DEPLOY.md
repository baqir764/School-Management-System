# راهنمای آپلود روی InfinityFree

این فایل همه مراحلی را که برای بالا بردن سایت روی هاست لازم است یک‌جا جمع
کرده تا در آینده چیزی از قلم نیفتد. راهنمای نصب لوکال جداست (`INSTALL.md`).

> ⚠️ داخل این فایل هیچ رمزی نوشته نشده. رمز دیتابیس فقط در فایل
> `Claude outputs\config.dat` است — آن فایل و زیپ‌ها را خصوصی نگه دارید.

## ۱. نقشه کلی (چرا دو زیپ؟)

هاست InfinityFree دو محدودیت دارد که شکل آپلود را تعیین می‌کند:

1. **هر فایل به اسم `.env` را خودکار پاک می‌کند.** به‌جایش برنامه از فایل
   `config.dat` تنظیمات را می‌خواند (نگاه کنید به `bootstrap/app.php`).
   پس روی سرور `.env` وجود ندارد و همه‌چیز در `config.dat` است.
2. **دسترسی SSH/terminal ندارد** (نمی‌شود `php artisan` اجرا کرد). پس:
   - سیم‌لینک `public/storage` را نمی‌شود ساخت → عکس‌ها از مسیر
     `storage.local` در `routes/web.php` سرو می‌شوند.
   - کشِ مسیرها روی سرور ساخته نمی‌شود → در زیپ، فایل‌های
     `bootstrap/cache/*.php` را نمی‌بریم تا لاراول خودش تازه بسازد.

به همین خاطر خروجی دو زیپ جدا است:

| زیپ | محتوا | کجا استخراج شود |
|---|---|---|
| `app-root.zip` | همه برنامه **به‌جز** `public` (شامل `config.dat`، `vendor`، `storage`) | داخل `school-management-system.freedev.app` (همان پوشه‌ای که `htdocs` را در خودش دارد — **نه داخل `htdocs`**) |
| `htdocs-contents.zip` | محتویات `public` (`index.php`، `.htaccess`، `build`، `vendor`) | داخل `school-management-system.freedev.app/htdocs` |

> پوشه `htdocs` که در صفحه اول فایل‌منجر کنار پوشه سایت دیده می‌شود مال
> دامین اصلی است — **به آن دست نزنید.**

## ۲. فایل `config.dat` (تنظیمات سرور)

اصل این فایل این‌جاست (در زیپ گنجانده می‌شود، نه در خود پروژه):

```
Claude outputs\config.dat
```

فرمتش دقیقاً مثل `.env` است (`KEY=VALUE`). هر بار که چیزی در هاست عوض شد
(دامنه، دیتابیس، رمز)، **همین فایل را اصلاح کنید** و زیپ‌ها را از نو بسازید.
مهم‌ترین خط‌ها:

```
APP_URL=https://school-management-system.freedev.app   (بدون / در آخر)
DB_HOST=sql207.infinityfree.com
DB_PORT=3306
DB_DATABASE=if0_42647154_school_management_system
DB_USERNAME=if0_42647154
DB_PASSWORD=...رمز از پنل هاست (آیکن چشم)...
```

مقادیر دیتابیس باید دقیقاً همان باشد که در پنل InfinityFree بخش
**MySQL Databases** دیده می‌شود.

## ۳. ساخت زیپ‌ها

پیش‌نیاز: Node.js (فقط وقتی فایل‌های `resources/css` یا `resources/js`
عوض شده باشند):

```powershell
npm run build
```

بعد اسکریپت ساخت زیپ (پروژه اصلی دست نمی‌خورد، همه کار در یک کپی انجام
می‌شود):

```powershell
powershell -ExecutionPolicy Bypass -File "Claude outputs\prepare-deploy.ps1"
```

خروجی این‌جاست:

```
C:\Users\AAIWO\Document\Desktop\School-Management-System\_deploy\
```

اسکریپت خودش این کارها را می‌کند: کپی `config.dat` به ریشه، حذف `.env`
لوکال، پاک کردن کشِ `bootstrap/cache`، حذف کپی `public/storage`، و ساخت
هر دو زیپ.

## ۴. آپلود

1. در فایل‌منجر هاست وارد `school-management-system.freedev.app` شوید.
2. `app-root.zip` را Upload و در **همان‌جا** Extract کنید. باید مستقیم
   همین‌جا دیده شوند: `config.dat` و پوشه‌های `app`، `bootstrap`،
   `config`، `vendor`، `storage` و...
3. وارد `htdocs` داخل همان پوشه شوید.
4. `htdocs-contents.zip` را Upload و در **همان‌جا** Extract کنید. باید
   مستقیم همین‌جا دیده شوند: `index.php`، `.htaccess`، `build` و `vendor`.

## ۵. دفعه اول: دیتابیس

جدول‌ها هنوز ساخته نشده‌اند. دو راه هست:

- **الف) ایمپورت از لوکال** (اگر همان داده‌های لوکال را می‌خواهید): در
  phpMyAdmin لوکال از `school_management_system` خروجی (Export) بگیرید و
  در phpMyAdmin هاست داخل دیتابیس سایت ایمپورت (Import) کنید.
- **ب) ساخت تازه + داده نمایشی:** چون SSH نیست، موقتاً این مسیر را آخر
  `routes/web.php` اضافه کنید، آپلودش کنید، یک بار در مرورگر بازش کنید،
  بعد **حتماً حذفش کنید** و دوباره آپلود کنید:
  ```php
  Route::get('/install-once-9f3k2', function () {
      \Illuminate\Support\Facades\Artisan::call('migrate:fresh', ['--seed' => true, '--force' => true]);
      return '<pre>'.\Illuminate\Support\Facades\Artisan::output().'</pre>';
  });
  ```

حساب‌های نمایشی بعد از seed (رمز همه `Passw0rd!123`):
`admin@school.local` ،`attendance.clerk@school.local` و بقیه در `UserSeeder`.

## ۶. آپلودهای بعدی (بعد از هر تغییر)

- فقط کد PHP / مسیرها / کانفیگ عوض شد → فقط **`app-root.zip`** را از نو
  بسازید و دوباره استخراج کنید.
- فقط `public` عوض شد (مثلاً `npm run build` تازه) → فقط
  **`htdocs-contents.zip`**.
- شک دارید → هر دو.
- بعد از تغییر مسیرها در لوکال، حتماً `php artisan route:clear` (و بعد
  `php artisan route:cache`) بزنید وگرنه کشِ کهنه مسیر جدید را قایم می‌کند.

## ۷. عیب‌یابی (از تجربه همین پروژه)

| علامت | علت | راه‌حل |
|---|---|---|
| صفحه سفید «Oops! An Error Occurred» (۵۰۰) | خطای PHP قبل از نمایش جزئیات | فایل `storage/logs/laravel.log` روی سرور را باز کنید؛ چند خط آخر را بخوانید. برای دیدن موقت خطا: در پنل هاست Display Errors = On، و در `config.dat` موقتاً `APP_DEBUG=true` (بعداً برگردانید به `false`) |
| `Vite manifest not found .../public/build/manifest.json` | برنامه پوشه وب را اشتباه حدس زده | در کد با `usePublicPath` حل شده (`bootstrap/app.php`) — فقط `app-root.zip` جدید را آپلود کنید |
| عکس‌ها/لوگو 403 یا باز نمی‌شوند | تداخل سرو خودکار لاراول ۱۲ با مسیر `storage` | در کد حل شده (`serve => false` در `config/filesystems.php` + مسیر `storage.local` در `routes/web.php`) — فقط `app-root.zip` جدید را آپلود کنید. اگر دیتابیس seed تازه است، عکس‌ها خالی‌اند: لوگو را از Settings آپلود کنید |
| `Unknown database ...` یا خطای اتصال MySQL | مشخصات دیتابیس غلط یا جدول‌ها ساخته نشده | `DB_*` در `config.dat` را با پنل هاست چک کنید (بخش ۲)؛ بعد بخش ۵ (دیتابیس) |
| اکسل Import/Export کار نمی‌کند | نبودن `ext-zip` | روی InfinityFree پیش‌فرض فعال است. در لوکال (XAMPP) خط `extension=zip` در `C:\xampp\php\php.ini` باید بدون `;` باشد + ری‌استارت Apache |
| CSS/JS یا عکس‌ها آدرس دامنه قدیمی دارند | `APP_URL` اشتباه | در `config.dat` باید `https://school-management-system.freedev.app` باشد (بدون `/` آخر) |
| فایل `.env` روی سرور نیست | طبیعی است — هاست پاکش می‌کند | دست نزنید؛ `config.dat` جای آن است (بخش ۱) |
| خطای ۵۰۰ بعد از آپلود با FTP | دسترسی نوشتن | پوشه‌های `storage` و `bootstrap/cache` باید قابل‌نوشتن باشند (775) |

## ۸. اپ اندروید حاضری (API)

اپ آفلاین (`C:\SchoolManagementAPK`) با همین سرور حرف می‌زند:

- `POST /api/login` با `{email, password, device_name?}` → `{token, name, email, role}`.
  فقط حساب فعالِ دارای `attendance.mark` توکن می‌گیرد (401/403 در غیر این صورت).
- `POST /api/attendance/sync` با هدر `Authorization: Bearer TOKEN` و بدنه
  `{records: [{admission_number, date, status, remarks?}]}` (حداکثر ۵۰۰ ردیف).
  جواب همیشه 200 است: `{imported, failed, errors: [{index, admission_number, code, message}]}`.
  قوانین هر ردیف دقیقاً همان ایمپورت اکسل وب است (شاگرد موجود، ثبت‌نام فعال
  سال جاری، شعبه در دسترس، تاریخ شمسی معتبرِ غیرآینده، وضعیت معتبر).
- `POST /api/session` (با همان توکن) → `{url}`: لینک ورود یک‌بارمصرف
  ۲دقیقه‌ای (`GET /auth/token/{nonce}`) که تب لایو اپ را بدون ورود دوباره
  مستقیم وارد داشبورد می‌کند. توکن هیچ‌وقت در URL نمی‌آید؛ کش استفاده‌شده
  همان کش دیتابیسی سرور است و چیز اضافه‌ای لازم ندارد.

نکته‌ها:

- جدول `personal_access_tokens` (Sanctum) لازم است. چون سرور SSH ندارد،
  بعد از آپلود `app-root.zip` جدید، **یک بار** مایگریشن معوقه را با همان
  ترفند مسیر موقت بخش ۵ اجرا کنید ولی با دستور **غیرمخرب**:
  ```php
  Route::get('/migrate-once-9f3k2', function () {
      \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
      return '<pre>'.\Illuminate\Support\Facades\Artisan::output().'</pre>';
  });
  ```
  (دقت: `migrate --force` نه `migrate:fresh` — دومی همه داده‌ها را پاک می‌کند!)
  بعد از اجرا حتماً مسیر را حذف و دوباره آپلود کنید.
- پوشه `vendor` هم عوض شده (Sanctum اضافه شد) — پس `app-root.zip` را کامل
  دوباره استخراج کنید، نه فقط چند فایل.

## ۹. چیزهایی که هرگز نباید کرد

- فایل `.env` لوکال را روی سرور نبرید (اسکریپت خودش حذفش می‌کند).
- در `config/filesystems.php` گزینه `serve` را `true` نکنید (توضیح در
  کامنت همان فایل).
- مسیر `/storage/{path}` دستی دیگری به `routes/web.php` اضافه نکنید
  (توضیح در کامنت همان فایل).
- رمز دیتابیس را در هیچ فایل راهنما، پیام یا جایی به‌جز `config.dat`
  ننویسید.
