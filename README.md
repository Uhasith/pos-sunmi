# Sunmi Cash POS

Cash-register MVP consisting of a Laravel backend/Filament admin and a native Kotlin Android application. Laravel is authoritative for products, VAT, checkout, orders, payments, and stock. Android owns the cashier interface, local retry state, scanner input, and ESC/POS network printing.

## Requirements

- Laravel Herd with PHP 8.4
- Composer and Node.js/npm
- MySQL for the production-like database, or SQLite for basic local development
- Android Studio with Android SDK 37 installed
- Android 13 (API 33) or newer emulator/device

## Run the Laravel backend with Herd

The repository is already located in the Herd directory as `pos-sunmi`, so Herd serves it as `http://pos-sunmi.test`.

For a new local installation:

```bash
cp .env.example .env
composer install
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
npm install
npm run build
```

The default `.env.example` uses SQLite. To use MySQL, set `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD` before running the migrations.

Confirm the backend is available:

```bash
curl --header 'Host: pos-sunmi.test' http://127.0.0.1/up
```

Open the Filament admin at `http://pos-sunmi.test/admin`. Local seed credentials are:

- Email: `admin@example.com`
- Password: `password`

These credentials are for local development only.

## Provision the POS device and printer

1. Sign in to the Filament admin.
2. Open **POS → Devices**.
3. Create an active device and copy its generated device UUID.
4. Optionally configure the printer model, IP address, port, and 58 mm or 80 mm paper width.
5. Use that UUID when signing in to the Android app.

Port `9100` is the normal raw TCP default for a network ESC/POS printer. Checkout remains successful when no printer is configured or the printer is offline.

## Open the Android project

In Android Studio, choose **Open** and select the repository's `android` directory, not the Laravel repository root.

Create `android/local.properties` if Android Studio has not created it:

```properties
sdk.dir=/Users/your-name/Library/Android/sdk
POS_API_BASE_URL=http://10.0.2.2/
POS_API_HOST=pos-sunmi.test
```

`local.properties` is ignored by Git. The `Host` header lets Laravel Herd route the emulator request to this project.

### Run with the Android emulator

1. Open **Tools → Device Manager**.
2. Create a **Pixel 7** virtual device.
3. Select **Android 13 / API 33**, **Google APIs**, and **ARM 64 v8a** on an Apple silicon Mac.
4. Start the emulator and wait for the Android home screen.
5. Select **Pixel 7** in Android Studio's device selector.
6. Select the **app** run configuration and press **Run**.

The special emulator address `10.0.2.2` connects to the Mac's localhost. Herd can therefore remain bound to localhost during emulator development.

### Run on a physical Sunmi device

Put the Mac and Sunmi on the same trusted Wi-Fi network, find the Mac's LAN IP, and update `android/local.properties`:

```properties
POS_API_BASE_URL=http://192.168.1.10/
POS_API_HOST=pos-sunmi.test
```

Herd must also listen on that LAN address. Be aware that changing Herd's loopback address can expose every configured Herd site—not only this POS—to other devices on the network. Only make that change on a trusted network and after reviewing the other local sites.

Enable USB debugging on the Sunmi, connect it to the Mac, select it in Android Studio, and press **Run**.

## Use the Android app

1. Tap **Test server** on the login screen.
2. Sign in with the local administrator credentials and the device UUID created in Filament.
3. Scan or manually enter one of the seeded barcodes:
   - `8710000001001`
   - `8710000001002`
   - `8710000001003`
4. Complete a cash checkout.
5. Retry printing from the confirmation screen if the configured printer was unavailable.

## Build and test

Backend checks:

```bash
php artisan test --compact
vendor/bin/phpstan analyse --no-progress
vendor/bin/pint --dirty --format agent
```

Android checks and debug APK:

```bash
cd android
./gradlew testDebugUnitTest lintDebug assembleDebug
```

The generated APK is located at:

```text
android/app/build/outputs/apk/debug/app-debug.apk
```

Debug builds allow cleartext HTTP for local Herd development. Release builds reject cleartext traffic and require `POS_RELEASE_API_BASE_URL` to use HTTPS.

## Emulator troubleshooting

If the emulator reports **System UI isn't responding**:

1. Stop the emulator from Device Manager.
2. Use **Cold Boot Now** from the virtual device menu.
3. If it continues, edit the virtual device and allocate about 4 GB RAM and 4 CPU cores, then disable snapshots.
4. Close other memory-heavy applications before starting the emulator.
5. As a last resort, use **Wipe Data**; this removes only that emulator's apps and local state.

If an Android Studio build remains stuck, open its Terminal and run:

```bash
./gradlew --stop
./gradlew assembleDebug --no-daemon -Pkotlin.compiler.execution.strategy=in-process
```
