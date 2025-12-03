<?php

namespace Tests\Unit;

use Crater\Space\EnvironmentManager;
use Crater\Http\Requests\DatabaseEnvironmentRequest;
use Crater\Http\Requests\DiskEnvironmentRequest;
use Crater\Http\Requests\DomainEnvironmentRequest;
use Crater\Http\Requests\MailEnvironmentRequest;
use Exception;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Pest\Support\Reflection;
use Pest\Facades\Mocks;
use Mockery;

beforeEach(function () {
    $this->envPath = '/tmp/.env.test';
    Mocks::mockFunction('base_path', fn($path = null) => $this->envPath);
    file_put_contents($this->envPath, '');

    // Reset config mocks and provide default values
    Config::shouldReceive('get')->andReturnUsing(function ($key, $default = null) {
        $mockedConfig = [
            'database.default' => 'mysql',
            'database.connections.mysql.host' => 'old_mysql_host',
            'database.connections.mysql.port' => '3306',
            'database.connections.mysql.database' => 'old_mysql_db',
            'database.connections.mysql.username' => 'old_mysql_user',
            'database.connections.mysql.password' => 'old_mysql_password',
            'database.connections.pgsql.host' => 'old_pgsql_host',
            'database.connections.pgsql.port' => '5432',
            'database.connections.pgsql.database' => 'old_pgsql_db',
            'database.connections.pgsql.username' => 'old_pgsql_user',
            'database.connections.pgsql.password' => 'old_pgsql_password',
            'database.connections.sqlite.database' => ':memory:',
            'app.url' => 'http://localhost',
            'session.domain' => 'localhost',
            'mail.driver' => 'smtp',
            'mail.host' => 'smtp.mailtrap.io',
            'mail.port' => '2525',
            'mail.username' => null,
            'mail.password' => null,
            'mail.encryption' => null,
            'mail.from.address' => 'hello@example.com',
            'mail.from.name' => 'Example',
            'services.mailgun.domain' => 'mg.example.com',
            'services.mailgun.secret' => 'mailgun_secret',
            'services.mailgun.endpoint' => 'api.mailgun.net',
            'services.ses.key' => 'ses_key',
            'services.ses.secret' => 'ses_secret',
            'filesystems.default' => 'local',
            'filesystems.disks.s3.key' => 'old_aws_key',
            'filesystems.disks.s3.secret' => 'old_aws_secret',
            'filesystems.disks.s3.region' => 'us-east-1',
            'filesystems.disks.s3.bucket' => 'old_aws_bucket',
            'filesystems.disks.s3.root' => '/',
            'filesystems.disks.doSpaces.key' => 'old_do_key',
            'filesystems.disks.doSpaces.secret' => 'old_do_secret',
            'filesystems.disks.doSpaces.region' => 'nyc3',
            'filesystems.disks.doSpaces.bucket' => 'old_do_bucket',
            'filesystems.disks.doSpaces.endpoint' => 'https://nyc3.digitaloceanspaces.com',
            'filesystems.disks.doSpaces.root' => '/',
            'filesystems.disks.dropbox.token' => 'old_dropbox_token',
            'filesystems.disks.dropbox.key' => 'old_dropbox_key',
            'filesystems.disks.dropbox.secret' => 'old_dropbox_secret',
            'filesystems.disks.dropbox.app' => 'old_dropbox_app',
            'filesystems.disks.dropbox.root' => '/',
            'crater.min_php_version' => '7.3',
            'crater.min_mysql_version' => '5.7',
            'crater.min_pgsql_version' => '9.6',
            'crater.min_sqlite_version' => '3.8',
        ];
        return $mockedConfig[$key] ?? $default;
    });

    Mocks::mockFunction('env', function ($key, $default = null) {
        $envValues = [
            'DB_CONNECTION' => 'mysql',
            'DB_HOST' => 'old_mysql_host',
            'DB_PORT' => '3306',
            'DB_DATABASE' => 'old_mysql_db',
            'DB_USERNAME' => 'old_mysql_user',
            'DB_PASSWORD' => 'old_mysql_password',
            'APP_URL' => 'http://localhost',
            'SANCTUM_STATEFUL_DOMAINS' => 'localhost',
            'SESSION_DOMAIN' => 'localhost',
            'MAIL_FROM_ADDRESS' => 'hello@example.com',
            'MAIL_FROM_NAME' => 'Example',
            'MAIL_DRIVER' => 'smtp',
            'MAIL_HOST' => 'smtp.mailtrap.io',
            'MAIL_PORT' => '2525',
            'MAIL_USERNAME' => null,
            'MAIL_PASSWORD' => null,
            'MAIL_ENCRYPTION' => null,
            'MAILGUN_DOMAIN' => 'mg.example.com',
            'MAILGUN_SECRET' => 'mailgun_secret',
            'MAILGUN_ENDPOINT' => 'api.mailgun.net',
            'SES_KEY' => 'ses_key',
            'SES_SECRET' => 'ses_secret',
            'FILESYSTEM_DRIVER' => 'local',
            'AWS_KEY' => 'old_aws_key',
            'AWS_SECRET' => 'old_aws_secret',
            'AWS_REGION' => 'us-east-1',
            'AWS_BUCKET' => 'old_aws_bucket',
            'AWS_ROOT' => '/',
            'DO_SPACES_KEY' => 'old_do_key',
            'DO_SPACES_SECRET' => 'old_do_secret',
            'DO_SPACES_REGION' => 'nyc3',
            'DO_SPACES_BUCKET' => 'old_do_bucket',
            'DO_SPACES_ENDPOINT' => 'https://nyc3.digitaloceanspaces.com',
            'DO_SPACES_ROOT' => '/',
            'DROPBOX_TOKEN' => 'old_dropbox_token',
            'DROPBOX_KEY' => 'old_dropbox_key',
            'DROPBOX_SECRET' => 'old_dropbox_secret',
            'DROPBOX_APP' => 'old_dropbox_app',
            'DROPBOX_ROOT' => '/',
        ];
        return $envValues[$key] ?? $default;
    });

    $this->manager = new EnvironmentManager();
});

test('constructor sets envPath correctly', function () {
    expect(Reflection::getProperty($this->manager, 'envPath'))
        ->toBe($this->envPath);
});

test('saveDatabaseVariables returns error if database table users exists', function () {
    $request = Mockery::mock(DatabaseEnvironmentRequest::class);
    $request->shouldReceive('has')->with('database_username')->andReturn(true);
    $request->shouldReceive('has')->with('database_password')->andReturn(true);
    $request->database_connection = 'mysql';
    $request->database_hostname = 'new_host';
    $request->database_port = '3306';
    $request->database_name = 'new_db';
    $request->database_username = 'new_user';
    $request->database_password = 'new_password';
    $request->app_url = 'http://new-app.test';
    $request->app_domain = 'new-app.test';

    $initialEnvContent =
        "DB_CONNECTION=mysql\n" .
        "DB_HOST=old_mysql_host\n" .
        "DB_PORT=3306\n" .
        "DB_DATABASE=old_mysql_db\n" .
        "DB_USERNAME=old_mysql_user\n" .
        'DB_PASSWORD="old_mysql_password"' . "\n\n" .
        "APP_URL=http://localhost\n" .
        "SANCTUM_STATEFUL_DOMAINS=localhost\n" .
        "SESSION_DOMAIN=localhost\n";
    file_put_contents($this->envPath, $initialEnvContent);

    $mockPdo = Mockery::mock(\PDO::class);
    $this->manager = Mockery::mock(EnvironmentManager::class)->makePartial();
    $this->manager->shouldAllowMockingProtectedMethods()->shouldReceive('checkDatabaseConnection')->andReturn($mockPdo);
    Reflection::setProperty($this->manager, 'envPath', $this->envPath);

    Schema::shouldReceive('hasTable')->with('users')->andReturn(true);

    $result = $this->manager->saveDatabaseVariables($request);

    expect($result)->toEqual(['error' => 'database_should_be_empty']);
});

test('saveDatabaseVariables saves variables successfully with username and password', function () {
    $request = Mockery::mock(DatabaseEnvironmentRequest::class);
    $request->shouldReceive('has')->with('database_username')->andReturn(true);
    $request->shouldReceive('has')->with('database_password')->andReturn(true);
    $request->database_connection = 'mysql';
    $request->database_hostname = 'new_host';
    $request->database_port = '3306';
    $request->database_name = 'new_db';
    $request->database_username = 'new_user';
    $request->database_password = 'new_password';
    $request->app_url = 'http://new-app.test';
    $request->app_domain = 'new-app.test';

    $initialEnvContent =
        "DB_CONNECTION=mysql\n" .
        "DB_HOST=old_mysql_host\n" .
        "DB_PORT=3306\n" .
        "DB_DATABASE=old_mysql_db\n" .
        "DB_USERNAME=old_mysql_user\n" .
        'DB_PASSWORD="old_mysql_password"' . "\n\n" .
        "APP_URL=http://localhost\n" .
        "SANCTUM_STATEFUL_DOMAINS=localhost\n" .
        "SESSION_DOMAIN=localhost\n";
    file_put_contents($this->envPath, $initialEnvContent);

    $mockPdo = Mockery::mock(\PDO::class);
    $this->manager = Mockery::mock(EnvironmentManager::class)->makePartial();
    $this->manager->shouldAllowMockingProtectedMethods()->shouldReceive('checkDatabaseConnection')->andReturn($mockPdo);
    Reflection::setProperty($this->manager, 'envPath', $this->envPath);

    Schema::shouldReceive('hasTable')->with('users')->andReturn(false);

    $result = $this->manager->saveDatabaseVariables($request);

    expect($result)->toEqual(['success' => 'database_variables_save_successfully']);

    $expectedEnvContent =
        "DB_CONNECTION=mysql\n" .
        "DB_HOST=new_host\n" .
        "DB_PORT=3306\n" .
        "DB_DATABASE=new_db\n" .
        "DB_USERNAME=new_user\n" .
        'DB_PASSWORD="new_password"' . "\n\n" .
        "APP_URL=http://new-app.test\n" .
        "SANCTUM_STATEFUL_DOMAINS=new-app.test\n" .
        "SESSION_DOMAIN=new-app.test\n";

    expect(file_get_contents($this->envPath))->toBe($expectedEnvContent);
});

test('saveDatabaseVariables saves variables successfully without username and password', function () {
    $request = Mockery::mock(DatabaseEnvironmentRequest::class);
    $request->shouldReceive('has')->with('database_username')->andReturn(false);
    $request->shouldReceive('has')->with('database_password')->andReturn(false);
    $request->database_connection = 'mysql';
    $request->database_hostname = 'new_host';
    $request->database_port = '3306';
    $request->database_name = 'new_db_no_creds';
    $request->app_url = 'http://no-creds.test';
    $request->app_domain = 'no-creds.test';

    $initialEnvContent =
        "DB_CONNECTION=mysql\n" .
        "DB_DATABASE=old_mysql_db\n\n" .
        "APP_URL=http://localhost\n" .
        "SANCTUM_STATEFUL_DOMAINS=localhost\n" .
        "SESSION_DOMAIN=localhost\n";
    file_put_contents($this->envPath, $initialEnvContent);

    $mockPdo = Mockery::mock(\PDO::class);
    $this->manager = Mockery::mock(EnvironmentManager::class)->makePartial();
    $this->manager->shouldAllowMockingProtectedMethods()->shouldReceive('checkDatabaseConnection')->andReturn($mockPdo);
    Reflection::setProperty($this->manager, 'envPath', $this->envPath);

    Schema::shouldReceive('hasTable')->with('users')->andReturn(false);

    $result = $this->manager->saveDatabaseVariables($request);

    expect($result)->toEqual(['success' => 'database_variables_save_successfully']);

    $expectedEnvContent =
        "DB_CONNECTION=mysql\n" .
        "DB_DATABASE=new_db_no_creds\n\n" .
        "APP_URL=http://no-creds.test\n" .
        "SANCTUM_STATEFUL_DOMAINS=no-creds.test\n" .
        "SESSION_DOMAIN=no-creds.test\n";

    expect(file_get_contents($this->envPath))->toBe($expectedEnvContent);
});

test('saveDatabaseVariables handles checkDatabaseConnection exception', function () {
    $request = Mockery::mock(DatabaseEnvironmentRequest::class);
    $request->shouldReceive('has')->with('database_username')->andReturn(true);
    $request->shouldReceive('has')->with('database_password')->andReturn(true);
    $request->database_connection = 'mysql';
    $request->database_hostname = 'bad_host';
    $request->database_port = '3306';
    $request->database_name = 'bad_db';
    $request->database_username = 'bad_user';
    $request->database_password = 'bad_password';
    $request->app_url = 'http://new-app.test';
    $request->app_domain = 'new-app.test';

    $initialEnvContent = "APP_URL=http://localhost\n";
    file_put_contents($this->envPath, $initialEnvContent);

    $this->manager = Mockery::mock(EnvironmentManager::class)->makePartial();
    $this->manager->shouldAllowMockingProtectedMethods()->shouldReceive('checkDatabaseConnection')->andThrow(new Exception('Database connection failed'));
    Reflection::setProperty($this->manager, 'envPath', $this->envPath);

    $result = $this->manager->saveDatabaseVariables($request);

    expect($result)->toEqual(['error_message' => 'Database connection failed']);
    expect(file_get_contents($this->envPath))->toBe($initialEnvContent);
});

test('saveDatabaseVariables handles file_put_contents exception', function () {
    $request = Mockery::mock(DatabaseEnvironmentRequest::class);
    $request->shouldReceive('has')->with('database_username')->andReturn(true);
    $request->shouldReceive('has')->with('database_password')->andReturn(true);
    $request->database_connection = 'mysql';
    $request->database_hostname = 'new_host';
    $request->database_port = '3306';
    $request->database_name = 'new_db';
    $request->database_username = 'new_user';
    $request->database_password = 'new_password';
    $request->app_url = 'http://new-app.test';
    $request->app_domain = 'new-app.test';

    $initialEnvContent =
        "DB_CONNECTION=mysql\n" .
        "DB_HOST=old_mysql_host\n" .
        "DB_PORT=3306\n" .
        "DB_DATABASE=old_mysql_db\n" .
        "DB_USERNAME=old_mysql_user\n" .
        'DB_PASSWORD="old_mysql_password"' . "\n\n" .
        "APP_URL=http://localhost\n" .
        "SANCTUM_STATEFUL_DOMAINS=localhost\n" .
        "SESSION_DOMAIN=localhost\n";
    file_put_contents($this->envPath, $initialEnvContent);

    $mockPdo = Mockery::mock(\PDO::class);
    $this->manager = Mockery::mock(EnvironmentManager::class)->makePartial();
    $this->manager->shouldAllowMockingProtectedMethods()->shouldReceive('checkDatabaseConnection')->andReturn($mockPdo);
    Reflection::setProperty($this->manager, 'envPath', $this->envPath);

    Schema::shouldReceive('hasTable')->with('users')->andReturn(false);

    // Patch file_put_contents to throw an Exception on first call
    $called = false;
    Mocks::mockFunction('file_put_contents', function ($path, $content, $flags = 0) use (&$called) {
        if (!$called) {
            $called = true;
            throw new Exception('File write error');
        }
        return \file_put_contents($path, $content, $flags);
    });
    Mocks::mockFunction('file_get_contents', fn($path) => \file_get_contents($path));

    $result = $this->manager->saveDatabaseVariables($request);

    expect($result)->toEqual(['error' => 'database_variables_save_error']);
});

test('checkDatabaseConnection returns PDO object on success with credentials', function () {
    $request = Mockery::mock(DatabaseEnvironmentRequest::class);
    $request->database_connection = 'mysql';
    $request->database_name = 'test_db';
    $request->shouldReceive('has')->with('database_username')->andReturn(true);
    $request->shouldReceive('has')->with('database_password')->andReturn(true);
    $request->database_username = 'test_user';
    $request->database_password = 'test_password';
    $request->database_hostname = 'test_host';
    $request->database_port = '3306';

    $mockPdo = Mockery::mock(\PDO::class);
    DB::shouldReceive('connection')->andReturnSelf();
    DB::shouldReceive('getPdo')->andReturn($mockPdo);

    $result = Reflection::callMethod($this->manager, 'checkDatabaseConnection', [$request]);

    expect($result)->toBe($mockPdo);
    expect(Config::get('database.connections.mysql.username'))->toBe('test_user');
    expect(Config::get('database.connections.mysql.password'))->toBe('test_password');
    expect(Config::get('database.connections.mysql.host'))->toBe('test_host');
    expect(Config::get('database.connections.mysql.port'))->toBe('3306');
    expect(Config::get('database.connections.mysql.database'))->toBe('test_db');
    expect(Config::get('database.default'))->toBe('mysql');
});

test('checkDatabaseConnection returns PDO object on success without credentials', function () {
    $request = Mockery::mock(DatabaseEnvironmentRequest::class);
    $request->database_connection = 'mysql';
    $request->database_name = 'test_db_no_creds';
    $request->shouldReceive('has')->with('database_username')->andReturn(false);
    $request->shouldReceive('has')->with('database_password')->andReturn(false);

    $mockPdo = Mockery::mock(\PDO::class);
    DB::shouldReceive('connection')->andReturnSelf();
    DB::shouldReceive('getPdo')->andReturn($mockPdo);

    $result = Reflection::callMethod($this->manager, 'checkDatabaseConnection', [$request]);

    expect($result)->toBe($mockPdo);
    expect(Config::get('database.connections.mysql.username'))->not->toBe('test_user');
    expect(Config::get('database.connections.mysql.database'))->toBe('test_db_no_creds');
    expect(Config::get('database.default'))->toBe('mysql');
});

test('checkVersionRequirements returns false if all requirements pass for mysql', function () {
    $request = Mockery::mock(DatabaseEnvironmentRequest::class);
    $request->database_connection = 'mysql';
    $mockConn = Mockery::mock(\PDO::class);

    $mockChecker = Mockery::mock(\Crater\Space\RequirementsChecker::class);
    $mockChecker->shouldReceive('checkPHPVersion')
        ->once()
        ->andReturn(['supported' => true]);
    $mockChecker->shouldReceive('checkMysqlVersion')
        ->once()
        ->with($mockConn)
        ->andReturn(['supported' => true]);

    $this->manager = Mockery::mock(EnvironmentManager::class)->makePartial();
    Reflection::setProperty($this->manager, 'envPath', $this->envPath);
    Mocks::mock('\Crater\Space\RequirementsChecker', $mockChecker);

    $result = Reflection::callMethod($this->manager, 'checkVersionRequirements', [$request, $mockConn]);

    expect($result)->toBeFalse();
});

test('checkVersionRequirements returns php support info if php version is not supported', function () {
    $request = Mockery::mock(DatabaseEnvironmentRequest::class);
    $request->database_connection = 'mysql';
    $mockConn = Mockery::mock(\PDO::class);

    $mockChecker = Mockery::mock(\Crater\Space\RequirementsChecker::class);
    $mockChecker->shouldReceive('checkPHPVersion')
        ->once()
        ->andReturn(['supported' => false, 'minimum' => '7.3', 'current' => '7.1']);
    $mockChecker->shouldNotReceive('checkMysqlVersion');

    $this->manager = Mockery::mock(EnvironmentManager::class)->makePartial();
    Reflection::setProperty($this->manager, 'envPath', $this->envPath);
    Mocks::mock('\Crater\Space\RequirementsChecker', $mockChecker);

    $result = Reflection::callMethod($this->manager, 'checkVersionRequirements', [$request, $mockConn]);

    expect($result)->toEqual(['supported' => false, 'minimum' => '7.3', 'current' => '7.1']);
});

test('checkVersionRequirements returns mysql support info if mysql version is not supported', function () {
    $request = Mockery::mock(DatabaseEnvironmentRequest::class);
    $request->database_connection = 'mysql';
    $mockConn = Mockery::mock(\PDO::class);

    $mockChecker = Mockery::mock(\Crater\Space\RequirementsChecker::class);
    $mockChecker->shouldReceive('checkPHPVersion')
        ->once()
        ->andReturn(['supported' => true]);
    $mockChecker->shouldReceive('checkMysqlVersion')
        ->once()
        ->with($mockConn)
        ->andReturn(['supported' => false, 'minimum' => '5.7', 'current' => '5.6']);

    $this->manager = Mockery::mock(EnvironmentManager::class)->makePartial();
    Reflection::setProperty($this->manager, 'envPath', $this->envPath);
    Mocks::mock('\Crater\Space\RequirementsChecker', $mockChecker);

    $result = Reflection::callMethod($this->manager, 'checkVersionRequirements', [$request, $mockConn]);

    expect($result)->toEqual(['supported' => false, 'minimum' => '5.7', 'current' => '5.6']);
});

test('checkVersionRequirements returns pgsql support info if pgsql version is not supported', function () {
    $request = Mockery::mock(DatabaseEnvironmentRequest::class);
    $request->database_connection = 'pgsql';
    $request->database_hostname = 'pg_host';
    $request->database_port = '5432';
    $request->database_name = 'pg_db';
    $request->database_username = 'pg_user';
    $request->database_password = 'pg_password';
    $mockConn = Mockery::mock(\PDO::class);

    Mocks::mockFunction('pg_connect', fn($connStr) => Mockery::mock('PgSqlConnection'));
    $mockPgConn = Mockery::mock('PgSqlConnection');

    $mockChecker = Mockery::mock(\Crater\Space\RequirementsChecker::class);
    $mockChecker->shouldReceive('checkPHPVersion')
        ->once()
        ->andReturn(['supported' => true]);
    $mockChecker->shouldReceive('checkPgsqlVersion')
        ->once()
        ->with(Mockery::type('PgSqlConnection'), Config::get('crater.min_pgsql_version'))
        ->andReturn(['supported' => false, 'minimum' => '9.6', 'current' => '9.5']);

    $this->manager = Mockery::mock(EnvironmentManager::class)->makePartial();
    Reflection::setProperty($this->manager, 'envPath', $this->envPath);
    Mocks::mock('\Crater\Space\RequirementsChecker', $mockChecker);

    $result = Reflection::callMethod($this->manager, 'checkVersionRequirements', [$request, $mockConn]);

    expect($result)->toEqual(['supported' => false, 'minimum' => '9.6', 'current' => '9.5']);
});

test('checkVersionRequirements returns false if all requirements pass for pgsql', function () {
    $request = Mockery::mock(DatabaseEnvironmentRequest::class);
    $request->database_connection = 'pgsql';
    $request->database_hostname = 'pg_host';
    $request->database_port = '5432';
    $request->database_name = 'pg_db';
    $request->database_username = 'pg_user';
    $request->database_password = 'pg_password';
    $mockConn = Mockery::mock(\PDO::class);

    Mocks::mockFunction('pg_connect', fn($connStr) => Mockery::mock('PgSqlConnection'));
    $mockPgConn = Mockery::mock('PgSqlConnection');

    $mockChecker = Mockery::mock(\Crater\Space\RequirementsChecker::class);
    $mockChecker->shouldReceive('checkPHPVersion')
        ->once()
        ->andReturn(['supported' => true]);
    $mockChecker->shouldReceive('checkPgsqlVersion')
        ->once()
        ->with(Mockery::type('PgSqlConnection'), Config::get('crater.min_pgsql_version'))
        ->andReturn(['supported' => true]);

    $this->manager = Mockery::mock(EnvironmentManager::class)->makePartial();
    Reflection::setProperty($this->manager, 'envPath', $this->envPath);
    Mocks::mock('\Crater\Space\RequirementsChecker', $mockChecker);

    $result = Reflection::callMethod($this->manager, 'checkVersionRequirements', [$request, $mockConn]);

    expect($result)->toBeFalse();
});

test('checkVersionRequirements returns sqlite support info if sqlite version is not supported', function () {
    $request = Mockery::mock(DatabaseEnvironmentRequest::class);
    $request->database_connection = 'sqlite';
    $mockConn = Mockery::mock(\PDO::class);

    $mockChecker = Mockery::mock(\Crater\Space\RequirementsChecker::class);
    $mockChecker->shouldReceive('checkPHPVersion')
        ->once()
        ->andReturn(['supported' => true]);
    $mockChecker->shouldReceive('checkSqliteVersion')
        ->once()
        ->with(Config::get('crater.min_sqlite_version'))
        ->andReturn(['supported' => false, 'minimum' => '3.8', 'current' => '3.7']);

    $this->manager = Mockery::mock(EnvironmentManager::class)->makePartial();
    Reflection::setProperty($this->manager, 'envPath', $this->envPath);
    Mocks::mock('\Crater\Space\RequirementsChecker', $mockChecker);

    $result = Reflection::callMethod($this->manager, 'checkVersionRequirements', [$request, $mockConn]);

    expect($result)->toEqual(['supported' => false, 'minimum' => '3.8', 'current' => '3.7']);
});

test('saveMailVariables saves variables successfully for smtp driver', function () {
    $request = Mockery::mock(MailEnvironmentRequest::class);
    $request->mail_driver = 'smtp';
    $request->mail_host = 'new.smtp.host';
    $request->mail_port = '587';
    $request->mail_username = 'new_user';
    $request->mail_password = 'new_pass';
    $request->mail_encryption = 'tls';
    $request->from_mail = 'new@example.com';
    $request->from_name = 'New App';

    $initialEnvContent =
        "MAIL_DRIVER=smtp\n" .
        "MAIL_HOST=smtp.mailtrap.io\n" .
        "MAIL_PORT=2525\n" .
        "MAIL_USERNAME=\n" .
        "MAIL_PASSWORD=\n" .
        "MAIL_ENCRYPTION=\n\n" .
        "MAIL_FROM_ADDRESS=hello@example.com\n" .
        'MAIL_FROM_NAME="Example"' . "\n\n";
    file_put_contents($this->envPath, $initialEnvContent);

    $mailData = [
        'old_mail_data' =>
            "MAIL_DRIVER=smtp\n" .
            "MAIL_HOST=smtp.mailtrap.io\n" .
            "MAIL_PORT=2525\n" .
            "MAIL_USERNAME=\n" .
            "MAIL_PASSWORD=\n" .
            "MAIL_ENCRYPTION=\n\n" .
            "MAIL_FROM_ADDRESS=hello@example.com\n" .
            'MAIL_FROM_NAME="Example"' . "\n\n",
        'new_mail_data' =>
            "MAIL_DRIVER=smtp\n" .
            "MAIL_HOST=new.smtp.host\n" .
            "MAIL_PORT=587\n" .
            "MAIL_USERNAME=new_user\n" .
            "MAIL_PASSWORD=new_pass\n" .
            "MAIL_ENCRYPTION=tls\n\n" .
            "MAIL_FROM_ADDRESS=new@example.com\n" .
            'MAIL_FROM_NAME="New App"' . "\n\n",
        'extra_mail_data' => '',
        'extra_old_mail_data' => '',
    ];
    $this->manager = Mockery::mock(EnvironmentManager::class)->makePartial();
    $this->manager->shouldAllowMockingProtectedMethods()->shouldReceive('getMailData')->andReturn($mailData);
    Reflection::setProperty($this->manager, 'envPath', $this->envPath);

    $result = $this->manager->saveMailVariables($request);

    expect($result)->toEqual(['success' => 'mail_variables_save_successfully']);

    $expectedEnvContent =
        "MAIL_DRIVER=smtp\n" .
        "MAIL_HOST=new.smtp.host\n" .
        "MAIL_PORT=587\n" .
        "MAIL_USERNAME=new_user\n" .
        "MAIL_PASSWORD=new_pass\n" .
        "MAIL_ENCRYPTION=tls\n\n" .
        "MAIL_FROM_ADDRESS=new@example.com\n" .
        'MAIL_FROM_NAME="New App"' . "\n\n";
    expect(file_get_contents($this->envPath))->toBe($expectedEnvContent);
});

test('saveMailVariables returns error when file_put_contents fails', function () {
    $request = Mockery::mock(MailEnvironmentRequest::class);
    $request->mail_driver = 'smtp';
    $request->mail_host = 'new.smtp.host';
    $request->mail_port = '587';
    $request->mail_username = 'new_user';
    $request->mail_password = 'new_pass';
    $request->mail_encryption = 'tls';
    $request->from_mail = 'new@example.com';
    $request->from_name = 'New App';

    $initialEnvContent =
        "MAIL_DRIVER=smtp\n" .
        "MAIL_HOST=smtp.mailtrap.io\n" .
        "MAIL_PORT=2525\n" .
        "MAIL_USERNAME=\n" .
        "MAIL_PASSWORD=\n" .
        "MAIL_ENCRYPTION=\n\n" .
        "MAIL_FROM_ADDRESS=hello@example.com\n" .
        'MAIL_FROM_NAME="Example"' . "\n\n";
    file_put_contents($this->envPath, $initialEnvContent);

    $mailData = [
        'old_mail_data' =>
            "MAIL_DRIVER=smtp\n" .
            "MAIL_HOST=smtp.mailtrap.io\n" .
            "MAIL_PORT=2525\n" .
            "MAIL_USERNAME=\n" .
            "MAIL_PASSWORD=\n" .
            "MAIL_ENCRYPTION=\n\n" .
            "MAIL_FROM_ADDRESS=hello@example.com\n" .
            'MAIL_FROM_NAME="Example"' . "\n\n",
        'new_mail_data' =>
            "MAIL_DRIVER=smtp\n" .
            "MAIL_HOST=new.smtp.host\n" .
            "MAIL_PORT=587\n" .
            "MAIL_USERNAME=new_user\n" .
            "MAIL_PASSWORD=new_pass\n" .
            "MAIL_ENCRYPTION=tls\n\n" .
            "MAIL_FROM_ADDRESS=new@example.com\n" .
            'MAIL_FROM_NAME="New App"' . "\n\n",
        'extra_mail_data' => '',
        'extra_old_mail_data' => '',
    ];
    $this->manager = Mockery::mock(EnvironmentManager::class)->makePartial();
    $this->manager->shouldAllowMockingProtectedMethods()->shouldReceive('getMailData')->andReturn($mailData);
    Reflection::setProperty($this->manager, 'envPath', $this->envPath);

    $called = false;
    Mocks::mockFunction('file_put_contents', function ($path, $content, $flags = 0) use (&$called) {
        if (!$called) {
            $called = true;
            throw new Exception('Mail file write error');
        }
        return \file_put_contents($path, $content, $flags);
    });

    $result = $this->manager->saveMailVariables($request);

    expect($result)->toEqual(['error' => 'mail_variables_save_error']);
});

test('saveDiskVariables saves disk config for s3 driver', function () {
    $request = Mockery::mock(DiskEnvironmentRequest::class);
    $request->disk_driver = 's3';
    $request->aws_key = 'new_aws_key';
    $request->aws_secret = 'new_aws_secret';
    $request->aws_region = 'us-west-2';
    $request->aws_bucket = 'new_aws_bucket';
    $request->aws_root = '/newpath';

    $initialEnvContent =
        "FILESYSTEM_DRIVER=local\n" .
        "AWS_KEY=old_aws_key\n" .
        "AWS_SECRET=old_aws_secret\n" .
        "AWS_REGION=us-east-1\n" .
        "AWS_BUCKET=old_aws_bucket\n" .
        "AWS_ROOT=/\n";
    file_put_contents($this->envPath, $initialEnvContent);

    $diskData = [
        'old_disk_data' =>
            "FILESYSTEM_DRIVER=local\n" .
            "AWS_KEY=old_aws_key\n" .
            "AWS_SECRET=old_aws_secret\n" .
            "AWS_REGION=us-east-1\n" .
            "AWS_BUCKET=old_aws_bucket\n" .
            "AWS_ROOT=/\n",
        'new_disk_data' =>
            "FILESYSTEM_DRIVER=s3\n" .
            "AWS_KEY=new_aws_key\n" .
            "AWS_SECRET=new_aws_secret\n" .
            "AWS_REGION=us-west-2\n" .
            "AWS_BUCKET=new_aws_bucket\n" .
            "AWS_ROOT=/newpath\n",
        'extra_disk_data' => '',
        'extra_old_disk_data' => '',
    ];

    $this->manager = Mockery::mock(EnvironmentManager::class)->makePartial();
    $this->manager->shouldAllowMockingProtectedMethods()->shouldReceive('getDiskData')->andReturn($diskData);
    Reflection::setProperty($this->manager, 'envPath', $this->envPath);

    $result = $this->manager->saveDiskVariables($request);

    expect($result)->toEqual(['success' => 'disk_variables_save_successfully']);

    $expectedEnvContent =
        "FILESYSTEM_DRIVER=s3\n" .
        "AWS_KEY=new_aws_key\n" .
        "AWS_SECRET=new_aws_secret\n" .
        "AWS_REGION=us-west-2\n" .
        "AWS_BUCKET=new_aws_bucket\n" .
        "AWS_ROOT=/newpath\n";
    expect(file_get_contents($this->envPath))->toBe($expectedEnvContent);
});

test('saveDiskVariables returns error when file_put_contents fails', function () {
    $request = Mockery::mock(DiskEnvironmentRequest::class);
    $request->disk_driver = 's3';
    $request->aws_key = 'new_aws_key';
    $request->aws_secret = 'new_aws_secret';
    $request->aws_region = 'us-west-2';
    $request->aws_bucket = 'new_aws_bucket';
    $request->aws_root = '/newpath';

    $initialEnvContent =
        "FILESYSTEM_DRIVER=local\n" .
        "AWS_KEY=old_aws_key\n" .
        "AWS_SECRET=old_aws_secret\n" .
        "AWS_REGION=us-east-1\n" .
        "AWS_BUCKET=old_aws_bucket\n" .
        "AWS_ROOT=/\n";
    file_put_contents($this->envPath, $initialEnvContent);

    $diskData = [
        'old_disk_data' =>
            "FILESYSTEM_DRIVER=local\n" .
            "AWS_KEY=old_aws_key\n" .
            "AWS_SECRET=old_aws_secret\n" .
            "AWS_REGION=us-east-1\n" .
            "AWS_BUCKET=old_aws_bucket\n" .
            "AWS_ROOT=/\n",
        'new_disk_data' =>
            "FILESYSTEM_DRIVER=s3\n" .
            "AWS_KEY=new_aws_key\n" .
            "AWS_SECRET=new_aws_secret\n" .
            "AWS_REGION=us-west-2\n" .
            "AWS_BUCKET=new_aws_bucket\n" .
            "AWS_ROOT=/newpath\n",
        'extra_disk_data' => '',
        'extra_old_disk_data' => '',
    ];

    $this->manager = Mockery::mock(EnvironmentManager::class)->makePartial();
    $this->manager->shouldAllowMockingProtectedMethods()->shouldReceive('getDiskData')->andReturn($diskData);
    Reflection::setProperty($this->manager, 'envPath', $this->envPath);

    $called = false;
    Mocks::mockFunction('file_put_contents', function ($path, $content, $flags = 0) use (&$called) {
        if (!$called) {
            $called = true;
            throw new Exception('Disk file write error');
        }
        return \file_put_contents($path, $content, $flags);
    });

    $result = $this->manager->saveDiskVariables($request);

    expect($result)->toEqual(['error' => 'disk_variables_save_error']);
});

test('saveDomainVariables saves domain config', function () {
    $request = Mockery::mock(DomainEnvironmentRequest::class);
    $request->app_url = 'https://myapp.example.com';
    $request->app_domain = 'myapp.example.com';

    $initialEnvContent =
        "APP_URL=http://localhost\n" .
        "SANCTUM_STATEFUL_DOMAINS=localhost\n" .
        "SESSION_DOMAIN=localhost\n";
    file_put_contents($this->envPath, $initialEnvContent);

    $domainData = [
        'old_domain_data' =>
            "APP_URL=http://localhost\n" .
            "SANCTUM_STATEFUL_DOMAINS=localhost\n" .
            "SESSION_DOMAIN=localhost\n",
        'new_domain_data' =>
            "APP_URL=https://myapp.example.com\n" .
            "SANCTUM_STATEFUL_DOMAINS=myapp.example.com\n" .
            "SESSION_DOMAIN=myapp.example.com\n",
        'extra_domain_data' => '',
        'extra_old_domain_data' => '',
    ];

    $this->manager = Mockery::mock(EnvironmentManager::class)->makePartial();
    $this->manager->shouldAllowMockingProtectedMethods()->shouldReceive('getDomainData')->andReturn($domainData);
    Reflection::setProperty($this->manager, 'envPath', $this->envPath);

    $result = $this->manager->saveDomainVariables($request);

    expect($result)->toEqual(['success' => 'domain_variables_save_successfully']);

    $expectedEnvContent =
        "APP_URL=https://myapp.example.com\n" .
        "SANCTUM_STATEFUL_DOMAINS=myapp.example.com\n" .
        "SESSION_DOMAIN=myapp.example.com\n";
    expect(file_get_contents($this->envPath))->toBe($expectedEnvContent);
});

test('saveDomainVariables returns error when file_put_contents fails', function () {
    $request = Mockery::mock(DomainEnvironmentRequest::class);
    $request->app_url = 'https://myapp.example.com';
    $request->app_domain = 'myapp.example.com';

    $initialEnvContent =
        "APP_URL=http://localhost\n" .
        "SANCTUM_STATEFUL_DOMAINS=localhost\n" .
        "SESSION_DOMAIN=localhost\n";
    file_put_contents($this->envPath, $initialEnvContent);

    $domainData = [
        'old_domain_data' =>
            "APP_URL=http://localhost\n" .
            "SANCTUM_STATEFUL_DOMAINS=localhost\n" .
            "SESSION_DOMAIN=localhost\n",
        'new_domain_data' =>
            "APP_URL=https://myapp.example.com\n" .
            "SANCTUM_STATEFUL_DOMAINS=myapp.example.com\n" .
            "SESSION_DOMAIN=myapp.example.com\n",
        'extra_domain_data' => '',
        'extra_old_domain_data' => '',
    ];

    $this->manager = Mockery::mock(EnvironmentManager::class)->makePartial();
    $this->manager->shouldAllowMockingProtectedMethods()->shouldReceive('getDomainData')->andReturn($domainData);
    Reflection::setProperty($this->manager, 'envPath', $this->envPath);

    $called = false;
    Mocks::mockFunction('file_put_contents', function ($path, $content, $flags = 0) use (&$called) {
        if (!$called) {
            $called = true;
            throw new Exception('Domain file write error');
        }
        return \file_put_contents($path, $content, $flags);
    });

    $result = $this->manager->saveDomainVariables($request);

    expect($result)->toEqual(['error' => 'domain_variables_save_error']);
});