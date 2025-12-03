<?php

use Illuminate\Support\Facades\Artisan;
use org\bovigo\vfs\vfsStream;
use Brain\Monkey\Functions;

// Setup for Mockery and Brain\Monkey (for global function mocking)
beforeEach(function () {
    // The 'alias:' mock often conflicts with Laravel's test environment
    // where facades are already set up to be spied/mocked.
    // Removing this line to prevent 'class already exists' error.
    // Mockery::mock('alias:'.Artisan::class); 
    Functions\setUp(); // Initialize Brain Monkey for global function mocking
});

// Teardown for Mockery and Brain\Monkey
afterEach(function () {
    Mockery::close();
    Functions\tearDown(); // Clean up global function mocks after each test
});

test('constructor initializes parent command and properties', function () {
    $command = new \Crater\Console\Commands\ResetApp();
    expect($command)->toBeInstanceOf(\Crater\Console\Commands\ResetApp::class);

    // Using reflection to access protected properties for white-box testing
    $reflection = new \ReflectionClass($command);

    $signatureProperty = $reflection->getProperty('signature');
    $signatureProperty->setAccessible(true);
    expect($signatureProperty->getValue($command))->toBe('reset:app {--force}');

    $descriptionProperty = $reflection->getProperty('description');
    $descriptionProperty->setAccessible(true);
    expect($descriptionProperty->getValue($command))->toBe('Clean database, database_created and public/storage folder');
});

test('handle method returns early if confirmation is not given', function () {
    // Create a partial mock of the command to control `confirmToProceed` and `info`
    $command = Mockery::mock(\Crater\Console\Commands\ResetApp::class)->makePartial();
    // Allow mocking of protected methods from the `ConfirmableTrait`
    $command->shouldAllowMockingProtectedMethods();

    $command->shouldReceive('confirmToProceed')
            ->once()
            ->andReturn(false);

    // When testing facades, `partialMock()` is often preferred over `alias:`
    // as it works with Laravel's existing facade resolution.
    Artisan::partialMock();
    // Ensure no Artisan calls are made
    Artisan::shouldNotReceive('call');

    // Ensure no info messages are displayed
    $command->shouldNotReceive('info');

    $command->handle();
});

test('handle method executes all steps when confirmed and .env exists', function () {
    // Set up vfsStream root for the virtual filesystem
    $root = vfsStream::setup('root', null, [
        'app_root' => [ // This will be our `base_path`
            '.env' => 'APP_NAME=Crater\nAPP_ENV=local\nAPP_DEBUG=true\nAPP_URL=http://localhost'
        ]
    ]);
    $envFilePath = $root->url() . '/app_root/.env';

    // Mock `base_path` global helper to point to our vfsStream root
    Functions\when('base_path')->justReturn($root->url() . '/app_root');

    // Create a partial mock of the command to control `confirmToProceed` and `info` messages
    $command = Mockery::mock(\Crater\Console\Commands\ResetApp::class)->makePartial();
    $command->shouldAllowMockingProtectedMethods();

    $command->shouldReceive('confirmToProceed')
            ->once()
            ->andReturn(true);

    $command->shouldReceive('info')
            ->once()
            ->with('Running migrate:fresh')
            ->ordered();

    $command->shouldReceive('info')
            ->once()
            ->with('Seeding database')
            ->ordered();

    $command->shouldReceive('info')
            ->once()
            ->with('App has been reset successfully')
            ->ordered();

    // Mock Artisan calls
    Artisan::partialMock(); // Ensure Artisan is mockable for this test
    Artisan::shouldReceive('call')
            ->once()
            ->with('migrate:fresh --seed --force')
            ->ordered();

    Artisan::shouldReceive('call')
            ->once()
            ->with('db:seed', ['--class' => 'DemoSeeder', '--force' => true])
            ->ordered();

    // Execute the command handle method
    $command->handle();

    // Assert that the .env file content was updated
    expect(file_get_contents($envFilePath))->toBe('APP_NAME=Crater\nAPP_ENV=local\nAPP_DEBUG=false\nAPP_URL=http://localhost');
});

test('handle method skips .env update if file does not exist', function () {
    // Set up vfsStream root for the virtual filesystem without the .env file
    $root = vfsStream::setup('root', null, [
        'app_root' => [] // Empty directory, no .env file
    ]);

    // Mock `base_path` global helper to point to our vfsStream root
    Functions\when('base_path')->justReturn($root->url() . '/app_root');

    // Create a partial mock of the command to control `confirmToProceed` and `info` messages
    $command = Mockery::mock(\Crater\Console\Commands\ResetApp::class)->makePartial();
    $command->shouldAllowMockingProtectedMethods();

    $command->shouldReceive('confirmToProceed')
            ->once()
            ->andReturn(true);

    $command->shouldReceive('info')
            ->once()
            ->with('Running migrate:fresh')
            ->ordered();

    $command->shouldReceive('info')
            ->once()
            ->with('Seeding database')
            ->ordered();

    $command->shouldReceive('info')
            ->once()
            ->with('App has been reset successfully')
            ->ordered();

    // Mock Artisan calls
    Artisan::partialMock(); // Ensure Artisan is mockable for this test
    Artisan::shouldReceive('call')
            ->once()
            ->with('migrate:fresh --seed --force')
            ->ordered();

    Artisan::shouldReceive('call')
            ->once()
            ->with('db:seed', ['--class' => 'DemoSeeder', '--force' => true])
            ->ordered();

    // Since `file_exists` will return false due to vfsStream configuration, `file_put_contents` should not be called.
    // We explicitly expect `file_put_contents` never to be called to assert this branch.
    Functions\expect('file_put_contents')->never();

    // Execute the command handle method
    $command->handle();

    // Verify .env still doesn't exist in our virtual filesystem
    expect($root->getChild('app_root')->hasChild('.env'))->toBeFalse();
});

test('handle method does not alter .env if APP_DEBUG=true is not found', function () {
    // Set up vfsStream root with .env content that does not contain 'APP_DEBUG=true'
    $originalContent = 'APP_NAME=Crater\nAPP_ENV=local\nAPP_DEBUG=false\nAPP_URL=http://localhost';
    $root = vfsStream::setup('root', null, [
        'app_root' => [
            '.env' => $originalContent
        ]
    ]);
    $envFilePath = $root->url() . '/app_root/.env';

    Functions\when('base_path')->justReturn($root->url() . '/app_root');

    $command = Mockery::mock(\Crater\Console\Commands\ResetApp::class)->makePartial();
    $command->shouldAllowMockingProtectedMethods();

    $command->shouldReceive('confirmToProceed')->once()->andReturn(true);
    $command->shouldReceive('info')->times(3); // Expect all info messages

    Artisan::partialMock(); // Ensure Artisan is mockable for this test
    Artisan::shouldReceive('call')->twice(); // migrate:fresh and db:seed

    // In this scenario, `str_replace` won't find 'APP_DEBUG=true', so the content remains the same.
    // `file_put_contents` will still be called with the original content.
    Functions\expect('file_put_contents')
        ->once()
        ->with($envFilePath, $originalContent);

    // Execute the command handle method
    $command->handle();

    // Assert that the .env file content was NOT effectively altered
    expect(file_get_contents($envFilePath))->toBe($originalContent);
});