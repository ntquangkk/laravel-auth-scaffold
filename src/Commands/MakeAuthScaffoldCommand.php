<?php

namespace TriQuang\LaravelAuthScaffold\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class MakeAuthScaffoldCommand extends Command
{
    private const AUTO_GEN_FLAG = '// AUTO-GEN-4-AUTH';
    private const AUTO_GEN_TAG = '// AUTO-GEN: Placeholder';
    private const AUTH_SCAFFOLD_TAG = '// AUTH-SCAFFOLD-MISSING-COLUMNS';
    private const PUBLISHED_STUB_PATH = 'stubs/vendor/triquang/laravel-auth-scaffold';

    protected $signature = 'make:auth-scaffold
                            {--model= : The model to scaffold auth for (default: User)}
                            {--module= : The module to generate the scaffold in (optional)}
                            {--web : Include web routes and views}';

    protected $description = 'Generate an authentication scaffold for a given model';

    protected $files;

    protected $model;

    protected $modelKebab;

    protected $modelPlural;

    protected $modelSnake;

    protected $modelVar;

    protected $module;

    protected $web;

    protected $baseNamespaceSlash;

    protected $appNamespace;

    protected $basePath;

    protected $appPath;

    public function __construct(Filesystem $files)
    {
        parent::__construct();
        $this->files = $files;
    }

    public function handle()
    {
        $this->model = $this->option('model') ?? 'User';
        $this->module = $this->option('module');
        $this->web = $this->option('web');
        $this->modelVar = Str::camel($this->model);
        $this->modelKebab = Str::kebab($this->model);
        $this->modelPlural = Str::plural($this->model);
        $this->modelSnake = Str::snake($this->model);

        $this->logAndOutput("\n🚀 Starting auth scaffold for model {$this->model}" . ($this->module ? " in module {$this->module}" : ''));

        // Validate model name
        if (!preg_match('/^[A-Z][a-zA-Z0-9]*$/', $this->model)) {
            $this->error('Invalid model name. It must be a valid PHP class name.');
            return 1;
        }

        try {
            $this->setupPaths();
            $this->ensureDirectoriesExist();
            $this->generateAuthScaffold();
            // $this->publishStubs();

            $this->logAndOutput("Auth scaffold generated successfully for model: {$this->model}");
            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Error generating Auth scaffold files: ' . $e->getMessage());
            Log::error('Error generating Auth scaffold files: ' . $e->getMessage());
            return self::FAILURE;
        }
    }

    protected function setupPaths()
    {
        $this->basePath = $this->module
            ? base_path("Modules/{$this->module}")
            : base_path();

        $this->appPath = $this->module
            ? $this->basePath . '/app'
            : base_path('app');

        $this->baseNamespaceSlash = $this->module
            ? "Modules\\{$this->module}\\"
            : '';

        $this->appNamespace = $this->module
            ? "Modules\\{$this->module}"
            : 'App';
    }

    protected function ensureDirectoriesExist()
    {
        $dirs = [
            "{$this->appPath}/Models",
            "{$this->appPath}/Http/Controllers/Auth",
            "{$this->appPath}/Http/Requests/Auth",
            "{$this->appPath}/Services/Auth",
            "{$this->basePath}/Routes",
            "{$this->basePath}/database/migrations",
        ];

        if ($this->web) {
            $dirs[] = "{$this->basePath}/resources/views/auth";
        }

        foreach ($dirs as $dir) {
            if (!$this->files->isDirectory($dir)) {
                $this->files->makeDirectory($dir, 0755, true);
                $this->log_info("Created directory: {$dir}");
            }
        }
    }

    protected function generateAuthScaffold()
    {
        $this->generateModel();
        $this->generateMigration();
        $this->generateController();
        $this->generateService();
        $this->generateRequests();
        $this->generateRoutes();
        if ($this->web) {
            $this->generateViews();
        }
        $this->updateAuthConfiguration();
    }

    protected function generateModel()
    {
        $modelPath = "{$this->appPath}/Models/{$this->model}.php";
    
        if (!$this->files->exists($modelPath)) {
            // Create new model if it doesn't exist
            $stub = $this->getStub('model');
            $stub = str_replace(
                ['{{ namespace }}', '{{ model }}', '{{ auto_gen_flag }}'],
                [$this->appNamespace, $this->model, self::AUTO_GEN_FLAG],
                $stub
            );
    
            $this->files->put($modelPath, $stub);
            $this->log_info("Created model: {$modelPath}");
            return;
        }

        $this->log_line("Model {$this->model} already exists, checking for authentication compatibility.");

        // Read existing model content
        $modelContent = $this->files->get($modelPath);

        // Check if model extends Authenticatable
        $isAuthCompatible = $this->isModelAuthCompatible($modelContent);

        if ($isAuthCompatible) {
            $this->log_info("Model {$this->model} is compatible with authentication, no changes needed.");
            return;
        }

        $this->log_warn("Model {$this->model} does not extend Authenticatable, which is required for authentication.");

        // Generate comments with guidance
        $comments = "\n    /* " . self::AUTO_GEN_FLAG . "\n" .
                    "     * This {$this->model} model is intended for use with Laravel authentication.\n" .
                    "     * To enable full authentication and API token support, ensure the following:\n" .
                    "     * - The class extends: Illuminate\Foundation\Auth\User (as Authenticatable)\n" .
                    "     * - The trait used: Laravel\Sanctum\HasApiTokens (for API token support)\n" .
                    "     * - The \$fillable array includes: ['name', 'email', 'password', 'otp']\n" .
                    "     */\n";

        // Find the class declaration and insert comments after it
        $pattern = '/(class\s+' . preg_quote($this->model, '/') . '\s*(?:extends\s+[^\{]*)?\s*\{)/';
        if (preg_match($pattern, $modelContent, $matches)) {
            // Check if comments already exist to avoid duplicates
            if (strpos($modelContent, "The following is a standard authentication-compatible model for {$this->model}") === false) {
                $newContent = preg_replace($pattern, '$1' . $comments, $modelContent);
                $this->files->put($modelPath, $newContent);
                $this->log_info("Inserted authentication compatibility comments in: {$modelPath}");
            } else {
                $this->log_line("Authentication compatibility comments already exist in {$modelPath}, skipping update.");
            }
        } else {
            $this->log_error("Could not find class declaration in {$modelPath}. Please manually add the following comments after the class declaration:");
            $this->log_comment($comments);
        }
    }

    protected function isModelAuthCompatible($modelContent)
    {
        // Check if model extends Authenticatable
        $extendsAuthenticatable = strpos($modelContent, 'extends Authenticatable') !== false
             || strpos($modelContent, 'Illuminate\Foundation\Auth\User') !== false
             || strpos($modelContent, 'email') !== false
             || strpos($modelContent, 'name') !== false
             || strpos($modelContent, 'password') !== false
             || strpos($modelContent, 'otp') !== false;

        if (!$extendsAuthenticatable) {
            $this->log_question("Model does not extend Authenticatable.");
        }

        return $extendsAuthenticatable;
    }

    protected function generateMigration()
    {
        $modelTable = Str::plural($this->modelSnake);
        $migrationDir = $this->basePath . '/database/migrations';

        // Define required auth columns
        $requiredColumns = [
            'name' => '$table->string(\'name\');',
            'email' => '$table->string(\'email\')->unique();',
            'password' => '$table->string(\'password\');',
            'otp' => '$table->string(\'otp\')->nullable();',
            'email_verified_at' => '$table->timestamp(\'email_verified_at\')->nullable();',
            'remember_token' => '$table->string(\'remember_token\')->nullable();',
        ];

        // Check for existing create migration
        $existingMigrations = $this->files->glob($migrationDir . '/*_create_' . $modelTable . '_table.php');

        if (empty($existingMigrations)) {
            // Create new table migration
            $migrationPath = $migrationDir . '/' . now()->format('Y_m_d_His') . "_create_{$modelTable}_table.php";
            $stub = $this->getStub('migrations/model');
            $stub = str_replace(
                ['{{ namespace }}', '{{ model_table }}', '{{ model }}', '{{ auto_gen_flag }}'],
                [
                    $this->baseNamespaceSlash . 'Database\\Migrations',
                    $modelTable,
                    $this->model,
                    self::AUTO_GEN_FLAG
                ],
                $stub
            );
            $this->files->put($migrationPath, $stub);
            $this->log_info("Created migration: {$migrationPath}");
        } else {
            // Check existing migration for missing columns
            $existingMigrationPath = $existingMigrations[0];
            $existingContent = $this->files->get($existingMigrationPath);
            $missingColumns = [];

            // Extract the Schema::create block for the specific table
            $createPattern = '/Schema::create\s*\(\s*[\'"]' . preg_quote($modelTable, '/') . '[\'"],\s*function\s*\(Blueprint\s*\$table\)\s*\{([^}]*)\}\s*\);/s';
            if (preg_match($createPattern, $existingContent, $matches)) {
                $tableBlockContent = $matches[1]; // Content inside Schema::create for $modelTable

                foreach ($requiredColumns as $column => $definition) {
                    // Use regex to match column name in common Schema Builder methods within the table block
                    $columnPattern = '/\$table->(?:string|timestamp|text|integer|bigInteger|boolean)\s*\(\s*[\'"]' . preg_quote($column, '/') . '[\'"]\s*\)/';
                    if (!preg_match($columnPattern, $tableBlockContent)) {
                        $missingColumns[$column] = $definition;
                    }
                }
            } else {
                $this->log_error("Could not find Schema::create for table {$modelTable} in {$existingMigrationPath}.");
                $missingColumns = $requiredColumns; // Assume all columns are missing if block not found
            }

            if (!empty($missingColumns)) {
                // Append missing columns as comments with tag
                $commentedColumns = "\n            " . self::AUTH_SCAFFOLD_TAG . "\n";
                $commentedColumns .= "            // Required authentication fields missing in '{$modelTable}' migration:\n";
                foreach ($missingColumns as $definition) {
                    $commentedColumns .= "            // $definition\n";
                }
                $commentedColumns .= "            " . self::AUTH_SCAFFOLD_TAG . "\n\n        ";
                // Find the up method's Schema::create block for the specific table
                $pattern = '/(Schema::create\s*\(\s*[\'"]' . preg_quote($modelTable, '/') . '[\'"],\s*function\s*\(Blueprint\s*\$table\)\s*\{[^}]*)(?=\s*\}\s*\);)/s';
                if (preg_match($pattern, $existingContent, $matches)) {
                    // Check if the tag already exists within the specific Schema::create block to avoid duplicates
                    $blockContent = $matches[0];
                    if (strpos($blockContent, self::AUTH_SCAFFOLD_TAG) === false) {
                        $newContent = preg_replace($pattern, '$1' . $commentedColumns, $existingContent);
                        $this->files->put($existingMigrationPath, $newContent);
                        $this->log_warn("Appended missing auth columns as comments in: {$existingMigrationPath} for table {$modelTable}");
                    } else {
                        $this->log_line("Auth column comments already exist in {$existingMigrationPath} for table {$modelTable}, skipping update.");
                    }
                } else {
                    $this->log_question("Could not append comments to {$existingMigrationPath} for table {$modelTable}. Please manually add the following columns:");
                    foreach ($missingColumns as $column => $definition) {
                        $this->log_line("- $definition");
                    }
                }
            } else {
                $this->log_info("Migration for {$modelTable} table already includes all required auth columns, no changes needed.");
            }
        }

        // Migration for model-specific password_reset_tokens
        $passwordResetTable = "{$this->modelSnake}_password_reset_tokens";
        $passwordResetMigrationPath = $migrationDir . '/' . now()->addSecond()->format('Y_m_d_His') . "_create_{$passwordResetTable}_table.php";
        $existingPasswordReset = $this->files->glob($migrationDir . '/*_create_' . $passwordResetTable . '_table.php');
        if (empty($existingPasswordReset)) {
            $stub = $this->getStub('migrations/password_reset_tokens');
            $stub = str_replace(
                ['{{ password_reset_table }}', '{{ auto_gen_flag }}'],
                [$passwordResetTable, self::AUTO_GEN_FLAG],
                $stub
            );
            $this->files->put($passwordResetMigrationPath, $stub);
            $this->log_info("Created migration: {$passwordResetMigrationPath}");
        } else {
            $this->log_line("Migration for {$passwordResetTable} table already exists, skipping creation.");
        }

        // Migration for model-specific otp_codes
        $otpTable = "{$this->modelSnake}_otp_codes";
        $otpMigrationPath = $migrationDir . '/' . now()->addSeconds(2)->format('Y_m_d_His') . "_create_{$otpTable}_table.php";
        $existingOtpMigrations = $this->files->glob($migrationDir . '/*_create_' . $otpTable . '_table.php');
        if (empty($existingOtpMigrations)) {
            $stub = $this->getStub('migrations/otp_codes');
            $stub = str_replace(
                ['{{ otp_table }}', '{{ model_table }}', '{{ auto_gen_flag }}'],
                [$otpTable, $modelTable, self::AUTO_GEN_FLAG],
                $stub
            );
            $this->files->put($otpMigrationPath, $stub);
            $this->log_info("Created migration: {$otpMigrationPath}");
        } else {
            $this->log_line("Migration for {$otpTable} table already exists, skipping creation.");
        }
    }

    protected function generateController()
    {
        // API Controller
        $apiControllerPath = "{$this->appPath}/Http/Controllers/Auth/{$this->model}ApiAuthController.php";
        if (!$this->files->exists($apiControllerPath)) {
            $stub = $this->getStub('controller.api');
            $stub = str_replace(
                ['{{ namespace }}', '{{ model }}', '{{ modelVar }}', '{{ modelSnake }}', '{{ auto_gen_flag }}'],
                [$this->appNamespace, $this->model, $this->modelVar, $this->modelSnake, self::AUTO_GEN_FLAG],
                $stub
            );
            $this->files->put($apiControllerPath, $stub);
            $this->log_info("Created controller: {$apiControllerPath}");
        } else {
            $this->log_line("Controller {$this->model}ApiAuthController already exists, skipping creation.");
        }

        // Web Controller (if --web option is provided)
        if ($this->web) {
            $webControllerPath = "{$this->appPath}/Http/Controllers/Auth/{$this->model}WebAuthController.php";
            if (!$this->files->exists($webControllerPath)) {
                $stub = $this->getStub('controller.web');
                $stub = str_replace(
                    ['{{ namespace }}', '{{ model }}', '{{ modelVar }}', '{{ modelSnake }}', '{{ auto_gen_flag }}'],
                    [$this->appNamespace, $this->model, $this->modelVar, $this->modelSnake, self::AUTO_GEN_FLAG],
                    $stub
                );
                $this->files->put($webControllerPath, $stub);
                $this->log_info("Created controller: {$webControllerPath}");
            } else {
                $this->log_line("Controller {$this->model}WebAuthController already exists, skipping creation.");
            }
        }
    }

    protected function generateService()
    {
        $servicePath = "{$this->appPath}/Services/Auth/{$this->model}AuthService.php";
        
        if ($this->files->exists($servicePath)) {
            $this->log_line("Service {$this->model}AuthService already exists, skipping creation.");
            return;
        }

        $stub = $this->getStub('service');
        $stub = str_replace(
            ['{{ namespace }}', '{{ model }}', '{{ modelVar }}', '{{ modelSnake }}', '{{ auto_gen_flag }}'],
            [$this->appNamespace, $this->model, $this->modelVar, $this->modelSnake, self::AUTO_GEN_FLAG],
            $stub
        );

        $this->files->put($servicePath, $stub);
        $this->log_info("Created service: {$servicePath}");
    }

    protected function generateRequests()
    {
        $requests = ['Register', 'Login', 'ForgotPassword', 'ResetPassword'];
        foreach ($requests as $request) {
            $requestPath = "{$this->appPath}/Http/Requests/Auth/{$this->model}{$request}Request.php";

            if ($this->files->exists($requestPath)) {
                $this->log_line("Request {$this->model}{$request}Request already exists, skipping creation.");
                continue;
            }

            $stub = $this->getStub('requests/' . Str::lower($request));
            $stub = str_replace(
                ['{{ namespace }}', '{{ model }}', '{{ modelVar }}', '{{ modelSnake }}', '{{ request }}', '{{ auto_gen_flag }}'],
                [$this->appNamespace, $this->model, $this->modelVar, $this->modelSnake, $request, self::AUTO_GEN_FLAG],
                $stub
            );

            $this->files->put($requestPath, $stub);
            $this->log_info("Created request: {$requestPath}");
        }
    }

    protected function generateRoutes()
    {
        // Define route paths and controller names based on $this->web
        $routePath = $this->web ? "{$this->basePath}/Routes/web.php" : "{$this->basePath}/Routes/api.php";
        $controllerName = $this->web ? "{$this->model}WebAuthController" : "{$this->model}ApiAuthController";
        $controllerUseStatement = "use {$this->appNamespace}\\Http\\Controllers\\Auth\\{$controllerName};";

        // Initialize route file content
        $routeContent = $this->files->exists($routePath) 
            ? $this->files->get($routePath) 
            : "<?php\n\n";

        $routeContent = preg_replace("/^<\?php\s*/", "<?php\n\n", $routeContent);

        // Define required use statements
        $requiredUseStatements = [
            "use Illuminate\Support\Facades\Route;",
            "use Illuminate\Http\Request;",
            $controllerUseStatement,
        ];

        // Add missing use statements
        foreach ($requiredUseStatements as $useStatement) {
            if (strpos($routeContent, $useStatement) === false) {
                $routeContent = preg_replace("/^<\?php\n\n/", "<?php\n\n{$useStatement}\n", $routeContent);
            }
        }

        // Check if route group for this model already exists
        if (strpos($routeContent, "auth:{$this->modelSnake}_api") !== false || strpos($routeContent, "auth:{$this->modelSnake}_web") !== false) {
            $this->log_line("Routes for {$this->model} already exist in {$routePath}, skipping.");
            return;
        }

        // Load and replace placeholders in the appropriate stub
        $stub = $this->getStub($this->web ? 'routes/web' : 'routes/api');
        $stub = str_replace(
            ['{{ namespace }}', '{{ model }}', '{{ modelSnake }}', '{{ modelKebab }}', '{{ controller }}', '{{ auto_gen_tag }}'],
            [$this->appNamespace, $this->model, $this->modelSnake, $this->modelKebab, $controllerName, self::AUTO_GEN_TAG],
            $stub
        );

        // Append the stub to route content
        $routeContent .= "\n" . $stub;
        $this->files->put($routePath, $routeContent);
        $this->log_info("Updated routes: {$routePath}");
    }

    protected function generateViews()
    {
        $views = ['login', 'register', 'forgot-password', 'reset-password', 'verify-otp'];
        foreach ($views as $view) {
            $viewPath = "{$this->basePath}/resources/views/auth/{$this->modelSnake}-{$view}.blade.php";

            if ($this->files->exists($viewPath)) {
                $this->log_line("View {$view} already exists, skipping creation.");
                continue;
            }

            $stub = $this->getStub("views/{$view}");
            $stub = str_replace(
                ['{{ model }}', '{{ modelSnake }}', '{{ auto_gen_flag }}'],
                [$this->model, $this->modelSnake, self::AUTO_GEN_FLAG],
                $stub
            );

            $this->files->put($viewPath, $stub);
            $this->log_info("Created view: {$viewPath}");
        }
    }

    protected function updateAuthConfiguration()
    {
        $authConfigPath = config_path('auth.php');
        $authContent = $this->files->exists($authConfigPath) 
            ? $this->files->get($authConfigPath) 
            : "<?php\n\nreturn [\n    'guards' => [],\n    'providers' => [],\n    'passwords' => [],\n];\n";

        if (strpos($authContent, "'{$this->modelSnake}_api' => [") !== false || 
            strpos($authContent, "'{$this->modelSnake}_web' => [") !== false) {
            $this->log_line("Auth configuration for {$this->model} already exists, skipping.");
            return;
        }

        $stub = $this->getStub('auth.config');
        $stub = str_replace(
            ['{{ namespace }}', '{{ model }}', '{{ modelSnake }}', '{{ auto_gen_tag }}'],
            [$this->appNamespace, $this->model, $this->modelSnake, self::AUTO_GEN_TAG],
            $stub
        );

        // Split stub into guards, providers, and passwords parts
        $stubParts = explode("\n\n", $stub);
        $guardStub = $stubParts[0];
        $providerStub = $stubParts[1] ?? '';
        $passwordStub = $stubParts[2] ?? '';

        // Insert guard entry right after 'guards' => [
        $authContent = preg_replace(
            "/('guards'\s*=>\s*\[)/",
            "$1\n$guardStub\n",
            $authContent
        );

        // Insert provider entry right after 'providers' => [
        $authContent = preg_replace(
            "/('providers'\s*=>\s*\[)/",
            "$1\n$providerStub\n",
            $authContent
        );

        // Insert password broker entry right after 'passwords' => [
        $authContent = preg_replace(
            "/('passwords'\s*=>\s*\[)/",
            "$1\n$passwordStub\n",
            $authContent
        );

        $this->files->put($authConfigPath, $authContent);
        $this->log_info("Updated auth configuration: {$authConfigPath}");
    }

    protected function publishStubs()
    {
        $stubPath = base_path(self::PUBLISHED_STUB_PATH);
        if ($this->files->isDirectory($stubPath)) {
            $this->log_line("Stubs already published at {$stubPath}, skipping.");
            return;
        }

        Artisan::call('vendor:publish', [
            '--provider' => 'TriQuang\LaravelAuthScaffold\LaravelAuthScaffoldServiceProvider',
            '--tag' => 'auth-scaffold-stubs'
        ]);

        $this->log_info("Published stubs to {$stubPath}");
    }

    protected function getStub($name)
    {
        $customPath = base_path(self::PUBLISHED_STUB_PATH . "/{$name}.stub");
        $defaultPath = __DIR__ . "/../../stubs/{$name}.stub";

        return $this->files->get($this->files->exists($customPath) ? $customPath : $defaultPath);
    }

    protected function logAndOutput($message)
    {
        $this->info($message);
        Log::info($message);
    }

    protected function log_info($message)
    {
        $this->info('✅ '.$message); // green
        Log::info($message);
    }

    protected function log_warn($message)
    {
        $this->warn('⚠️ '.$message); // yellow
        Log::warning($message);
    }

    protected function log_line($message)
    {
        $this->line('➖ '.$message); // white
        Log::notice($message);
    }

    protected function log_error($message)
    {
        $this->error('❌ '.$message); // red
        Log::error($message);
    }

    protected function log_comment($message)
    {
        $this->comment('💬 '.$message);  // gray
        Log::info($message);
    }

    protected function log_question($message)
    {
        $this->question('❔ '.$message);  // 
        Log::notice($message);
    }
}