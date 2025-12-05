<?php

test('the command signature and description are correctly defined', function () {
    $command = new \Crater\Console\Commands\InstallModuleCommand();

    expect($command->getName())->toBe('install:module');
    expect($command->getDescription())->toBe('Install cloned module.');

    $reflectionProperty = new \ReflectionProperty(\Crater\Console\Commands\InstallModuleCommand::class, 'signature');
    $reflectionProperty->setAccessible(true);
    expect($reflectionProperty->getValue($command))->toBe('install:module {module} {version}');
});

test('constructor can be instantiated without errors', function () {
    $command = new \Crater\Console\Commands\InstallModuleCommand();
    expect($command)->toBeInstanceOf(\Crater\Console\Commands\InstallModuleCommand::class);
    expect($command)->toBeInstanceOf(\Illuminate\Console\Command::class);
});

test('handle method calls ModuleInstaller::complete with correct arguments and returns success', function () {
    $moduleName = 'test-module';
    $modelVersion = '1.0.0';

    \Mockery::mock('alias:' . \Crater\Space\ModuleInstaller::class)
        ->shouldReceive('complete')
        ->once()
        ->with($moduleName, $modelVersion)
        ->andReturnNull();

    $command = \Mockery::mock(\Crater\Console\Commands\InstallModuleCommand::class . '[argument]');

    $command->shouldReceive('argument')
        ->once()
        ->with('module')
        ->andReturn($moduleName);

    $command->shouldReceive('argument')
        ->once()
        ->with('version')
        ->andReturn($modelVersion);

    $result = $command->handle();

    expect($result)->toBe(\Illuminate\Console\Command::SUCCESS);

    \Mockery::close();
});

test('handle method calls ModuleInstaller::complete with empty arguments if provided', function () {
    $moduleName = '';
    $modelVersion = '';

    \Mockery::mock('alias:' . \Crater\Space\ModuleInstaller::class)
        ->shouldReceive('complete')
        ->once()
        ->with($moduleName, $modelVersion)
        ->andReturnNull();

    $command = \Mockery::mock(\Crater\Console\Commands\InstallModuleCommand::class . '[argument]');

    $command->shouldReceive('argument')
        ->once()
        ->with('module')
        ->andReturn($moduleName);

    $command->shouldReceive('argument')
        ->once()
        ->with('version')
        ->andReturn($modelVersion);

    $result = $command->handle();

    expect($result)->toBe(\Illuminate\Console\Command::SUCCESS);

    \Mockery::close();
});

test('handle method propagates exceptions from ModuleInstaller::complete', function () {
    $moduleName = 'error-module';
    $modelVersion = '1.0.0';
    $exceptionMessage = 'Module installation failed!';

    \Mockery::mock('alias:' . \Crater\Space\ModuleInstaller::class)
        ->shouldReceive('complete')
        ->once()
        ->with($moduleName, $modelVersion)
        ->andThrow(new \Exception($exceptionMessage));

    $command = \Mockery::mock(\Crater\Console\Commands\InstallModuleCommand::class . '[argument]');

    $command->shouldReceive('argument')
        ->once()
        ->with('module')
        ->andReturn($moduleName);

    $command->shouldReceive('argument')
        ->once()
        ->with('version')
        ->andReturn($modelVersion);

    expect(fn() => $command->handle())
        ->toThrow(\Exception::class, $exceptionMessage);

    \Mockery::close();
});




afterEach(function () {
    Mockery::close();
});