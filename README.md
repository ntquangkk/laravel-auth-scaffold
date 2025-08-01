# Laravel Auth Scaffold

[![Latest Version on Packagist](https://img.shields.io/packagist/v/triquang/laravel-auth-scaffold.svg?style=flat-square)](https://packagist.org/packages/triquang/laravel-auth-scaffold)
[![Total Downloads](https://img.shields.io/packagist/dt/triquang/laravel-auth-scaffold.svg?style=flat-square)](https://packagist.org/packages/triquang/laravel-auth-scaffold)
[![License](https://img.shields.io/packagist/l/triquang/laravel-auth-scaffold.svg?style=flat-square)](https://github.com/ntquangkk/laravel-auth-scaffold?tab=MIT-1-ov-file)

🚀 Quickly scaffold full Laravel authentication for **any Eloquent model**, supporting both **API** and **Web UI** — monolith or modular apps.

This package accelerates authentication setup for **custom models** like `Admin`, `Client`, `Vendor`, etc., and works seamlessly with both **standard Laravel** and **modular Laravel** architectures (e.g., `nwidart/laravel-modules`).

---

## ✨ Features

- ✅ Supports **any Eloquent model** (`User`, `Admin`, `Client`, etc.)
- ✅ Scaffold for both **API routes/controllers/services** and **Web (views/forms)**
- ✅ **Works with modules** — fully compatible with `nwidart/laravel-modules`
- ✅ **Multi-auth ready** — configure multiple guards & providers automatically
- ✅ Generates:
  - Auth-ready Model (or updates existing one)
  - Migrations (with OTP, password reset)
  - Auth controllers (API + optional Web)
  - Requests, Services, Routes, Views (if `--web`)
  - Auth config updates (`guards`, `providers`, `passwords`)
- ✅ One single command does it all

### API functions
- Register
- Login
- Logout
- Forgot Password
- Reset Password
- Verify OTP

### WEB functions
- Includes all API functions above, plus:
  - Show register view
  - Show login view
  - Show Forgot Password view
  - Show Reset Password view
  - Show Verify OTP view

---

## 📦 Installation

```bash
composer require triquang/laravel-auth-scaffold --dev
```

### Optional: Publish Stubs

```bash
php artisan vendor:publish --provider="TriQuang\\LaravelAuthScaffold\\LaravelAuthScaffoldServiceProvider" --tag=auth-scaffold-stubs
```

This will publish stubs to:

```
/stubs/vendor/triquang/laravel-auth-scaffold/
```

---

## 🚀 Usage

```bash
php artisan make:auth-scaffold --model=Admin
```

### Options

| Option      | Description                                                                 |
|-------------|-----------------------------------------------------------------------------|
| `--model`   | (Required) Name of the model to generate authentication for. Defaults to `User`.|
| `--module`  | (Optional) Generate files inside `Modules/{ModuleName}`.                    |
| `--web`     | (Optional) Include web views and web routes.                                |

---

### Examples

#### 1. Generate basic API auth for default `User` model

```bash
php artisan make:auth-scaffold
```

#### 2. Generate API auth for `Admin` model

```bash
php artisan make:auth-scaffold --model=Admin
```

#### 3. Generate full auth for `Author` model inside a module

```bash
php artisan make:auth-scaffold --model=Author --module=Blog
```

#### 4. Generate web+API auth for `Client` model

```bash
php artisan make:auth-scaffold --model=Client --web
```

---

## 📂 File Structure Generated

Example with `--model=Admin`:

```
app/
├── Http/
│   └── Controllers/
│       └── Auth/
│           ├── AdminApiAuthController.php
│           └── AdminWebAuthController.php  # If --web
├── Models/
│   └── Admin.php
├── Services/
│   └── AdminAuthService.php
├── Requests/
│   ├── AdminLoginRequest.php
│   ├── AdminRegisterRequest.php
│   ├── AdminForgotPasswordRequest.php
│   └── AdminResetPasswordRequest.php
database/
└── migrations/
    ├── create_admins_table.php
    ├── create_admin_password_reset_tokens_table.php
    └── create_admin_otp_codes_table.php
routes/
├── api.php          # Updated
└── web.php          # If --web
resources/
└── views/
    └── auth/
        ├── admin-login.blade.php
        ├── admin-register.blade.php
        ├── admin-forgot-password.blade.php
        ├── admin-reset-password.blade.php
        └── admin-verify-otp.blade.php
config/
└── auth.php         # Auto-updated for guards/providers/passwords
```

---

## 🛡 Authentication Compatibility Check

- The command will:
  - Check if model extends `Illuminate\Foundation\Auth\User`
  - Check if model has required traits like `HasApiTokens`
  - Insert guidance comments if not

---

## 🧩 Customization

You can customize the stub files to suit your coding standards:

```bash
php artisan vendor:publish --tag=auth-scaffold-stubs
```

Edit stubs in:

```
stubs/vendor/triquang/laravel-auth-scaffold/
```

---

## 🧭 Auto-Generated Code Markers

This package adds **clear flags** in generated code to help developers easily find and review them.

### Example

```php
    // AUTO-GEN: Placeholder
    public function up()
    {
        Schema::create('admins', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        
			// AUTH-SCAFFOLD-MISSING-COLUMNS
			// Required authentication fields missing in 'admins' migration:
			// $table->string('email')->unique();
			// $table->string('password');
			// $table->string('otp')->nullable();
			// $table->timestamp('email_verified_at')->nullable();
			// $table->string('remember_token')->nullable();
			// AUTH-SCAFFOLD-MISSING-COLUMNS
        });
    }
```

### Available Markers

- `// AUTO-GEN-4-AUTH`
- `// AUTO-GEN: Placeholder`
- `// AUTH-SCAFFOLD-MISSING-COLUMNS`

You can quickly search these markers (`Ctrl/Cmd+Shift+F`) to locate auto-generated code and **remove them after review**.

---

## ❓ Why use this package?

Compared to Laravel Breeze, Jetstream or Fortify:

- ✅ Supports **any number of auth models** (not just `User`)
- ✅ Works in both **standard** and **modular** architectures
- ✅ Auth flow is fully generated and flexible for customizing
- ✅ Designed for **fast scaffolding** with clean, extensible code

---

## 💡 Example Use Cases

- Build multi separate auths for User, Admin, Vendor... in one app
- Use in modular apps like Modules/User, Modules/Admin
- Rapid implementing for auth APIs
- Auto-generate secure & clean Laravel auth structure

---

## 🚫 Limitations

- Does not include frontend assets like Vue/React.
- For OTP/email verification, you need to configure mail/notification system.

---

## ✅ Requirements

- PHP >= 8.0
- Laravel 11 / 12
- Composer
- Optional: `Laravel Sanctum`
- Optional: `nwidart/laravel-modules`

---

## 📄 License

MIT © [Nguyễn Trí Quang](mailto:ntquangkk@gmail.com)

---

## 🙌 Contributing

PRs are welcome! Feel free to improve functionality or report issues via GitHub Issues.

---

## 📬 Contact

- GitHub: [github.com/ntquangkk](https://github.com/ntquangkk)
- Email: [ntquangkk@gmail.com](mailto:ntquangkk@gmail.com)