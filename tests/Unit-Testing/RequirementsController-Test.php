<?php

use Crater\Http\Controllers\V1\Installation\RequirementsController;
use Crater\Space\RequirementsChecker;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Config;
uses(\Mockery::class);

uses(Tests\TestCase::class);

beforeEach(function () {
    // Mock the RequirementsChecker dependency
    $this->requirementsChecker = Mockery::mock(RequirementsChecker::class);

    // Instantiate the controller with the mock
    $this->controller = new RequirementsController($this->requirementsChecker);
});

afterEach(function () {
    Mockery::close();
});

test('constructor correctly injects and sets the requirements checker', function () {
    // Assert that the 'requirements' protected property is set with the mocked instance
    $reflectionProperty = new \ReflectionProperty($this->controller, 'requirements');
    $reflectionProperty->setAccessible(true);
    expect($reflectionProperty->getValue($this->controller))->toBe($this->requirementsChecker);
});

test('requirements method returns php support and system requirements as json', function () {
    // Arrange: Define mock data for the checker's responses
    $minPhpVersion = '7.4.0';
    $mockPhpSupportInfo = [
        'minPhpVersion' => $minPhpVersion,
        'currentPhpVersion' => PHP_VERSION,
        'supported' => true,
        'sections' => ['pdo', 'ctype']
    ];
    $mockSystemRequirements = [
        'php' => true,
        'openssl' => true,
        'pdo' => true,
        'mbstring' => true,
    ];
    $installerRequirementsConfig = ['php', 'openssl', 'pdo', 'mbstring'];

    // Arrange: Set expected config values using the Config facade
    Config::set('installer.core.minPhpVersion', $minPhpVersion);
    Config::set('installer.requirements', $installerRequirementsConfig);

    // Arrange: Expect calls on the mocked RequirementsChecker
    $this->requirementsChecker->shouldReceive('checkPHPVersion')
        ->once()
        ->with($minPhpVersion)
        ->andReturn($mockPhpSupportInfo);

    $this->requirementsChecker->shouldReceive('check')
        ->once()
        ->with($installerRequirementsConfig)
        ->andReturn($mockSystemRequirements);

    // Act
    $response = $this->controller->requirements();

    // Assert: Check the response type and content
    expect($response)->toBeInstanceOf(JsonResponse::class);
    expect($response->getData(true))->toEqual([
        'phpSupportInfo' => $mockPhpSupportInfo,
        'requirements' => $mockSystemRequirements,
    ]);
});

test('requirements method handles empty requirements config gracefully', function () {
    // Arrange: Define mock data for the checker's responses
    $minPhpVersion = '7.4.0';
    $mockPhpSupportInfo = [
        'minPhpVersion' => $minPhpVersion,
        'currentPhpVersion' => PHP_VERSION,
        'supported' => true,
        'sections' => [] // No specific PHP requirements
    ];
    $mockSystemRequirements = []; // Empty system requirements
    $installerRequirementsConfig = []; // Empty config for installer requirements

    // Arrange: Set expected config values
    Config::set('installer.core.minPhpVersion', $minPhpVersion);
    Config::set('installer.requirements', $installerRequirementsConfig);

    // Arrange: Expect calls on the mocked RequirementsChecker
    $this->requirementsChecker->shouldReceive('checkPHPVersion')
        ->once()
        ->with($minPhpVersion)
        ->andReturn($mockPhpSupportInfo);

    $this->requirementsChecker->shouldReceive('check')
        ->once()
        ->with($installerRequirementsConfig) // Should be called with an empty array
        ->andReturn($mockSystemRequirements);

    // Act
    $response = $this->controller->requirements();

    // Assert
    expect($response)->toBeInstanceOf(JsonResponse::class);
    expect($response->getData(true))->toEqual([
        'phpSupportInfo' => $mockPhpSupportInfo,
        'requirements' => $mockSystemRequirements,
    ]);
});

test('requirements method handles missing or null config values gracefully', function () {
    // Arrange: Define mock data for the checker's responses assuming null inputs
    // If config returns null, checker methods should be able to handle it or return default
    $mockPhpSupportInfo = [
        'minPhpVersion' => null, // Reflects the null input
        'currentPhpVersion' => PHP_VERSION,
        'supported' => false, // Example output if min version is null
        'sections' => []
    ];
    $mockSystemRequirements = []; // Example output if requirements array is null
    
    // Arrange: Simulate missing config keys by setting them to null
    Config::set('installer.core.minPhpVersion', null);
    Config::set('installer.requirements', null);

    // Arrange: Expect calls on the mocked RequirementsChecker with nulls
    $this->requirementsChecker->shouldReceive('checkPHPVersion')
        ->once()
        ->with(null) // Expect null to be passed
        ->andReturn($mockPhpSupportInfo);

    $this->requirementsChecker->shouldReceive('check')
        ->once()
        ->with(null) // Expect null to be passed
        ->andReturn($mockSystemRequirements);

    // Act
    $response = $this->controller->requirements();

    // Assert
    expect($response)->toBeInstanceOf(JsonResponse::class);
    expect($response->getData(true))->toEqual([
        'phpSupportInfo' => $mockPhpSupportInfo,
        'requirements' => $mockSystemRequirements,
    ]);
});

test('requirements method propagates different php support info', function () {
    // Arrange: Test with different php support scenario (e.g., unsupported version)
    $minPhpVersion = '8.0.0';
    $mockPhpSupportInfo = [
        'minPhpVersion' => $minPhpVersion,
        'currentPhpVersion' => '7.4.0', // Current is lower than min
        'supported' => false,
        'sections' => ['pdo', 'json']
    ];
    $mockSystemRequirements = [
        'php' => false, // Fails because of PHP version
        'openssl' => true,
    ];
    $installerRequirementsConfig = ['php', 'openssl'];

    Config::set('installer.core.minPhpVersion', $minPhpVersion);
    Config::set('installer.requirements', $installerRequirementsConfig);

    $this->requirementsChecker->shouldReceive('checkPHPVersion')
        ->once()
        ->with($minPhpVersion)
        ->andReturn($mockPhpSupportInfo);

    $this->requirementsChecker->shouldReceive('check')
        ->once()
        ->with($installerRequirementsConfig)
        ->andReturn($mockSystemRequirements);

    // Act
    $response = $this->controller->requirements();

    // Assert
    expect($response)->toBeInstanceOf(JsonResponse::class);
    expect($response->getData(true))->toEqual([
        'phpSupportInfo' => $mockPhpSupportInfo,
        'requirements' => $mockSystemRequirements,
    ]);
});

test('requirements method propagates different system requirements outcomes', function () {
    // Arrange: Test with different system requirements scenario (e.g., missing extension)
    $minPhpVersion = '7.4.0';
    $mockPhpSupportInfo = [
        'minPhpVersion' => $minPhpVersion,
        'currentPhpVersion' => PHP_VERSION,
        'supported' => true,
        'sections' => ['dom']
    ];
    $mockSystemRequirements = [
        'php' => true,
        'openssl' => true,
        'gd' => false, // Simulating missing GD extension
    ];
    $installerRequirementsConfig = ['php', 'openssl', 'gd'];

    Config::set('installer.core.minPhpVersion', $minPhpVersion);
    Config::set('installer.requirements', $installerRequirementsConfig);

    $this->requirementsChecker->shouldReceive('checkPHPVersion')
        ->once()
        ->with($minPhpVersion)
        ->andReturn($mockPhpSupportInfo);

    $this->requirementsChecker->shouldReceive('check')
        ->once()
        ->with($installerRequirementsConfig)
        ->andReturn($mockSystemRequirements);

    // Act
    $response = $this->controller->requirements();

    // Assert
    expect($response)->toBeInstanceOf(JsonResponse::class);
    expect($response->getData(true))->toEqual([
        'phpSupportInfo' => $mockPhpSupportInfo,
        'requirements' => $mockSystemRequirements,
    ]);
});
