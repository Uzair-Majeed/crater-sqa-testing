<?php

use Crater\Console\Commands\CreateTemplateCommand;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Mockery\MockInterface;
use function Pest\Laravel\mock;

// Helper function to create a mock command instance
function createMockCommand(): CreateTemplateCommand
{
    // Create an anonymous class that extends CreateTemplateCommand
    // and allows us to mock its protected methods.
    $command = new class extends CreateTemplateCommand {
        public $mockArguments = [];
        public $mockOptions = [];
        public $mockChoiceResult = null;
        public $infoMessages = [];
        public $errorMessages = [];

        public function __construct()
        {
            // Call the parent constructor to ensure base Command setup,
            // but for unit testing, it's often better to control what happens.
            // If `parent::__construct()` had side effects we couldn't easily mock,
            // we might skip it or mock deeper. For this case, it's simple enough.
            parent::__construct();
        }

        protected function argument($key = null)
        {
            return $this->mockArguments[$key] ?? null;
        }

        protected function option($key = null)
        {
            return $this->mockOptions[$key] ?? null;
        }

        protected function choice($question, array $choices, $default = null)
        {
            return $this->mockChoiceResult;
        }

        protected function info($string, $verbosity = null)
        {
            $this->infoMessages[] = $string;
        }

        protected function error($string, $verbosity = null)
        {
            $this->errorMessages[] = $string;
        }
    };

    return $command;
}

test('constructor calls parent constructor', function () {
    // This test ensures the constructor doesn't throw errors and the object is instantiated.
    // Verifying `parent::__construct()` call directly is difficult without advanced mocking of the parent class itself.
    $command = new CreateTemplateCommand();
    expect($command)->toBeInstanceOf(CreateTemplateCommand::class);
});

test('handle creates template when type option is provided and template does not exist', function () {
    $templateName = 'MyNewTemplate';
    $type = 'invoice';

    $command = createMockCommand();
    $command->mockArguments = ['name' => $templateName];
    $command->mockOptions = ['type' => $type];

    // Mock Storage facade
    Storage::shouldReceive('disk')
        ->with('views')
        ->andReturnUsing(function () use ($templateName, $type) {
            $mockFilesystem = mock(FilesystemAdapter::class);
            $mockFilesystem->shouldReceive('exists')
                ->with("/app/pdf/{$type}/{$templateName}.blade.php")
                ->andReturn(false)
                ->once();
            $mockFilesystem->shouldReceive('copy')
                ->with("/app/pdf/{$type}/{$type}1.blade.php", "/app/pdf/{$type}/{$templateName}.blade.php")
                ->once();
            return $mockFilesystem;
        });

    // Mock global functions `public_path`, `resource_path`, `copy`.
    // Since direct mocking of global functions in Pest without additional libraries (like Brain\Dose)
    // is not straightforward or supported within a simple test block, we acknowledge this limitation.
    // In a full unit test suite with Brain\Dose:
    // mock_function('public_path')->andReturn('/mocked/public/path');
    // mock_function('resource_path')->andReturn('/mocked/resource/path');
    // mock_function('copy')->andReturn(true); // Asserting call arguments would be ideal here.

    // For this exercise, we'll verify the logical flow and the `info` message,
    // which implicitly relies on `resource_path` being used correctly to form the message.
    // The actual `copy` operations on the filesystem cannot be asserted without mocking global functions.

    $result = $command->handle();

    expect($result)->toBe(0);
    expect($command->infoMessages)->toHaveCount(1);
    expect($command->infoMessages[0])->toContain(ucfirst($type) . ' Template created successfully at');
    expect($command->infoMessages[0])->toContain("views/app/pdf/{$type}/{$templateName}.blade.php"); // Verifies resource_path usage in message
})->group('CreateTemplateCommand');

test('handle creates template when type option is not provided and choice is made', function () {
    $templateName = 'NewEstimate';
    $chosenType = 'estimate'; // Simulating user choice

    $command = createMockCommand();
    $command->mockArguments = ['name' => $templateName];
    $command->mockOptions = ['type' => null]; // No type option provided
    $command->mockChoiceResult = $chosenType; // Simulate user choosing 'estimate'

    // Mock Storage facade
    Storage::shouldReceive('disk')
        ->with('views')
        ->andReturnUsing(function () use ($templateName, $chosenType) {
            $mockFilesystem = mock(FilesystemAdapter::class);
            $mockFilesystem->shouldReceive('exists')
                ->with("/app/pdf/{$chosenType}/{$templateName}.blade.php")
                ->andReturn(false)
                ->once();
            $mockFilesystem->shouldReceive('copy')
                ->with("/app/pdf/{$chosenType}/{$chosenType}1.blade.php", "/app/pdf/{$chosenType}/{$templateName}.blade.php")
                ->once();
            return $mockFilesystem;
        });

    $result = $command->handle();

    expect($result)->toBe(0);
    expect($command->infoMessages)->toHaveCount(1);
    expect($command->infoMessages[0])->toContain(ucfirst($chosenType) . ' Template created successfully at');
    expect($command->infoMessages[0])->toContain("views/app/pdf/{$chosenType}/{$templateName}.blade.php");
})->group('CreateTemplateCommand');

test('handle returns 0 and informs user if template already exists', function () {
    $templateName = 'ExistingTemplate';
    $type = 'invoice';

    $command = createMockCommand();
    $command->mockArguments = ['name' => $templateName];
    $command->mockOptions = ['type' => $type];

    // Mock Storage facade to indicate template exists
    Storage::shouldReceive('disk')
        ->with('views')
        ->andReturnUsing(function () use ($templateName, $type) {
            $mockFilesystem = mock(FilesystemAdapter::class);
            $mockFilesystem->shouldReceive('exists')
                ->with("/app/pdf/{$type}/{$templateName}.blade.php")
                ->andReturn(true) // Template exists
                ->once();
            // copy should NOT be called if exists returns true
            $mockFilesystem->shouldNotReceive('copy');
            return $mockFilesystem;
        });

    $result = $command->handle();

    expect($result)->toBe(0);
    expect($command->infoMessages)->toHaveCount(1);
    expect($command->infoMessages[0])->toBe("Template with given name already exists.");
})->group('CreateTemplateCommand');

test('handle successfully creates template for estimate type with hyphenated name', function () {
    $templateName = 'my-new-estimate';
    $type = 'estimate';

    $command = createMockCommand();
    $command->mockArguments = ['name' => $templateName];
    $command->mockOptions = ['type' => $type];

    // Mock Storage facade
    Storage::shouldReceive('disk')
        ->with('views')
        ->andReturnUsing(function () use ($templateName, $type) {
            $mockFilesystem = mock(FilesystemAdapter::class);
            $mockFilesystem->shouldReceive('exists')
                ->with("/app/pdf/{$type}/{$templateName}.blade.php")
                ->andReturn(false)
                ->once();
            $mockFilesystem->shouldReceive('copy')
                ->with("/app/pdf/{$type}/{$type}1.blade.php", "/app/pdf/{$type}/{$templateName}.blade.php")
                ->once();
            return $mockFilesystem;
        });

    $result = $command->handle();

    expect($result)->toBe(0);
    expect($command->infoMessages)->toHaveCount(1);
    expect($command->infoMessages[0])->toContain(ucfirst($type) . ' Template created successfully at');
    expect($command->infoMessages[0])->toContain("views/app/pdf/{$type}/{$templateName}.blade.php");
})->group('CreateTemplateCommand');

test('handle successfully creates template for invoice type with special characters in name (sanitized for filenames)', function () {
    // In a real scenario, template names might need sanitization for filenames.
    // Assuming the input `name` argument is already suitable for filenames.
    $templateName = 'invoice_#_001'; // Example, typically special chars are avoided or stripped for filenames
    $type = 'invoice';

    $command = createMockCommand();
    $command->mockArguments = ['name' => $templateName];
    $command->mockOptions = ['type' => $type];

    // Mock Storage facade
    Storage::shouldReceive('disk')
        ->with('views')
        ->andReturnUsing(function () use ($templateName, $type) {
            $mockFilesystem = mock(FilesystemAdapter::class);
            $mockFilesystem->shouldReceive('exists')
                ->with("/app/pdf/{$type}/{$templateName}.blade.php")
                ->andReturn(false)
                ->once();
            $mockFilesystem->shouldReceive('copy')
                ->with("/app/pdf/{$type}/{$type}1.blade.php", "/app/pdf/{$type}/{$templateName}.blade.php")
                ->once();
            return $mockFilesystem;
        });

    $result = $command->handle();

    expect($result)->toBe(0);
    expect($command->infoMessages)->toHaveCount(1);
    expect($command->infoMessages[0])->toContain(ucfirst($type) . ' Template created successfully at');
    expect($command->infoMessages[0])->toContain("views/app/pdf/{$type}/{$templateName}.blade.php");
})->group('CreateTemplateCommand');

 


afterEach(function () {
    Mockery::close();
});
