<?php
test('get_list returns a list of date formats with formatted dates', function () {
    // 1. Arrange: Mock Carbon::now()->format() calls
    $carbonMock = Mockery::mock('alias:' . \Carbon\Carbon::class);
    $carbonInstanceMock = Mockery::mock(\Carbon\Carbon::class);

    // Set up a fixed date for consistent testing
    $fixedDate = '2023-01-15 10:30:00';
    $carbonMock->shouldReceive('now')
               ->andReturn($carbonInstanceMock);

    // Mock format method for each format string in DateFormatter::$formats
    $carbonInstanceMock->shouldReceive('format')
                       ->with('Y M d')
                       ->andReturn('2023 Jan 15');

    $carbonInstanceMock->shouldReceive('format')
                       ->with('d M Y')
                       ->andReturn('15 Jan 2023');

    $carbonInstanceMock->shouldReceive('format')
                       ->with('d/m/Y')
                       ->andReturn('15/01/2023');

    $carbonInstanceMock->shouldReceive('format')
                       ->with('d.m.Y')
                       ->andReturn('15.01.2023');

    $carbonInstanceMock->shouldReceive('format')
                       ->with('d-m-Y')
                       ->andReturn('15-01-2023');

    $carbonInstanceMock->shouldReceive('format')
                       ->with('m/d/Y')
                       ->andReturn('01/15/2023');

    $carbonInstanceMock->shouldReceive('format')
                       ->with('Y/m/d')
                       ->andReturn('2023/01/15');

    $carbonInstanceMock->shouldReceive('format')
                       ->with('Y-m-d')
                       ->andReturn('2023-01-15');

    // 2. Act: Call the static method
    $result = \Crater\Space\DateFormatter::get_list();

    // 3. Assert: Verify the structure and values of the returned array
    expect($result)->toBeArray()
                   ->toHaveCount(8); // Based on the initial static::$formats

    expect($result[0])->toMatchArray([
        "display_date" => "2023 Jan 15",
        "carbon_format_value" => "Y M d",
        "moment_format_value" => "YYYY MMM DD",
    ]);

    expect($result[1])->toMatchArray([
        "display_date" => "15 Jan 2023",
        "carbon_format_value" => "d M Y",
        "moment_format_value" => "DD MMM YYYY",
    ]);

    expect($result[2])->toMatchArray([
        "display_date" => "15/01/2023",
        "carbon_format_value" => "d/m/Y",
        "moment_format_value" => "DD/MM/YYYY",
    ]);

    expect($result[3])->toMatchArray([
        "display_date" => "15.01.2023",
        "carbon_format_value" => "d.m.Y",
        "moment_format_value" => "DD.MM.YYYY",
    ]);

    expect($result[4])->toMatchArray([
        "display_date" => "15-01-2023",
        "carbon_format_value" => "d-m-Y",
        "moment_format_value" => "DD-MM-YYYY",
    ]);

    expect($result[5])->toMatchArray([
        "display_date" => "01/15/2023",
        "carbon_format_value" => "m/d/Y",
        "moment_format_value" => "MM/DD/YYYY",
    ]);

    expect($result[6])->toMatchArray([
        "display_date" => "2023/01/15",
        "carbon_format_value" => "Y/m/d",
        "moment_format_value" => " YYYY/MM/DD",
    ]);

    expect($result[7])->toMatchArray([
        "display_date" => "2023-01-15",
        "carbon_format_value" => "Y-m-d",
        "moment_format_value" => "YYYY-MM-DD",
    ]);
})->group('DateFormatter', 'success');

test('get_list returns an empty array when no formats are defined', function () {
    // 1. Arrange: Use reflection to modify the protected static::$formats property
    $reflection = new ReflectionClass(\Crater\Space\DateFormatter::class);
    $property = $reflection->getProperty('formats');
    $property->setAccessible(true);
    $originalFormats = $property->getValue(); // Store original value

    // Set the property to an empty array for this test
    $property->setValue(null, []);

    // Also mock Carbon, as the method still attempts to call Carbon::now()
    $carbonMock = Mockery::mock('alias:' . \Carbon\Carbon::class);
    $carbonMock->shouldNotReceive('now'); // Expect no calls to now() if foreach loop isn't entered

    // 2. Act: Call the static method
    $result = \Crater\Space\DateFormatter::get_list();

    // 3. Assert: Verify an empty array is returned
    expect($result)->toBeArray()
                   ->toBeEmpty();

    // Cleanup: Restore original formats after the test
    $property->setValue(null, $originalFormats);
})->group('DateFormatter', 'edge_case');

test('get_list handles a single format correctly', function () {
    // 1. Arrange: Use reflection to set a single format
    $reflection = new ReflectionClass(\Crater\Space\DateFormatter::class);
    $property = $reflection->getProperty('formats');
    $property->setAccessible(true);
    $originalFormats = $property->getValue(); // Store original value

    $singleFormat = [
        [
            "carbon_format" => "Y/m/d H:i:s",
            "moment_format" => "YYYY/MM/DD hh:mm:ss",
        ],
    ];
    $property->setValue(null, $singleFormat);

    // Mock Carbon::now()->format() for the single format
    $carbonMock = Mockery::mock('alias:' . \Carbon\Carbon::class);
    $carbonInstanceMock = Mockery::mock(\Carbon\Carbon::class);

    $carbonMock->shouldReceive('now')
               ->andReturn($carbonInstanceMock);

    $carbonInstanceMock->shouldReceive('format')
                       ->with('Y/m/d H:i:s')
                       ->andReturn('2023/01/15 10:30:00');

    // 2. Act: Call the static method
    $result = \Crater\Space\DateFormatter::get_list();

    // 3. Assert: Verify the result for the single format
    expect($result)->toBeArray()
                   ->toHaveCount(1);

    expect($result[0])->toMatchArray([
        "display_date" => "2023/01/15 10:30:00",
        "carbon_format_value" => "Y/m/d H:i:s",
        "moment_format_value" => "YYYY/MM/DD hh:mm:ss",
    ]);

    // Cleanup: Restore original formats after the test
    $property->setValue(null, $originalFormats);
})->group('DateFormatter', 'edge_case');

// Ensure mocks are torn down




afterEach(function () {
    Mockery::close();
});
