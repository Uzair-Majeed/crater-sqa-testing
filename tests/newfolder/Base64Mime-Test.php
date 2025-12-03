<?php

use Crater\Rules\Base64Mime;

// Helper functions for accessing private/protected properties for white-box testing.
// In a typical Pest setup, these might be defined in a bootstrap file or a custom test case.
// For a self-contained test block, they are defined globally within this test file.
function getProperty(object $object, string $property)
{
    $reflection = new \ReflectionClass($object);
    $prop = $reflection->getProperty($property);
    $prop->setAccessible(true);
    return $prop->getValue($object);
}

function setProperty(object $object, string $property, $value)
{
    $reflection = new \ReflectionClass($object);
    $prop = $reflection->getProperty($property);
    $prop->setAccessible(true);
    $prop->setValue($object, $value);
}

test('constructor correctly initializes the extensions property', function () {
    $extensions = ['jpg', 'jpeg', 'png'];
    $rule = new Base64Mime($extensions);
    expect(getProperty($rule, 'extensions'))->toBe($extensions);
});

test('message returns the correct validation error message including attribute and extensions', function () {
    $extensions = ['jpg', 'jpeg'];
    $rule = new Base64Mime($extensions);

    // Set the private 'attribute' property using reflection for isolated testing of message()
    setProperty($rule, 'attribute', 'document_file');

    expect($rule->message())->toBe('The document_file must be a json with file of type: jpg, jpeg encoded in base64.');
});

test('message returns a correct validation error message when the extensions array is empty', function () {
    $extensions = [];
    $rule = new Base64Mime($extensions);
    setProperty($rule, 'attribute', 'avatar');
    expect($rule->message())->toBe('The avatar must be a json with file of type:  encoded in base64.');
});

test('passes returns false for input that is not a valid JSON string', function () {
    $rule = new Base64Mime(['jpg']);
    expect($rule->passes('file', 'this is not a json string but plain text'))->toBeFalse();
});

test('passes returns false for valid JSON that does not contain a "data" key', function () {
    $rule = new Base64Mime(['jpg']);
    expect($rule->passes('file', '{"name": "test.jpg", "size": 100}'))->toBeFalse();
});


test('passes returns false for a "data" string that does not match the base64 data URI pattern (missing prefix)', function () {
    $rule = new Base64Mime(['jpg']);
    // Missing 'data:' prefix
    $value = json_encode(['data' => 'image/jpeg;base64,R0lGODlhAQABAIAAAP///wAAACH5BAEAAAAALAAAAAABAAEAAAICRAEAOw==']);
    expect($rule->passes('file', $value))->toBeFalse();
});

test('passes returns false for a "data" string that does not match the base64 data URI pattern (invalid characters)', function () {
    $rule = new Base64Mime(['jpg']);
    // Contains an invalid character '$' in the base64 part
    $value = json_encode(['data' => 'data:image/jpeg;base64,R0lGODlhAQABAIAAAP///wAAACH5BAEAAAAALAAAAAABAAEAAAICRAEAOw$']);
    expect($rule->passes('file', $value))->toBeFalse();
});

test('passes returns false if the base64 data string has no comma separator', function () {
    $rule = new Base64Mime(['jpg']);
    $value = json_encode(['data' => 'data:image/jpeg;base64R0lGODlhAQABAIAAAP///wAAACH5BAEAAAAALAAAAAABAAEAAAICRAEAOw==']);
    expect($rule->passes('file', $value))->toBeFalse();
});

test('passes returns false if the base64 encoded part is empty after the comma separator', function () {
    $rule = new Base64Mime(['jpg']);
    $value = json_encode(['data' => 'data:image/jpeg;base64,']);
    expect($rule->passes('file', $value))->toBeFalse();
});

test('passes returns false if finfo_buffer reports "???" or an unallowed type for unrecognized binary data', function () {
    // This crafts binary data unlikely to match a known file type. `finfo_buffer` might return 'application/octet-stream',
    // 'text/plain', or '???'. In any such case, as these are not in ['jpg'], the rule should return false.
    // This test covers the scenarios where `finfo_buffer` fails to identify a type or identifies an unallowed one,
    // including the explicit '???' branch if that's the result.
    $binaryGarbage = hex2bin('0102030405060708090A0B0C0D0E0F101112131415161718191A1B1C1D1E1F20'); // 32 bytes of sequential data
    $base64Garbage = base64_encode($binaryGarbage);

    $rule = new Base64Mime(['jpg']);
    $value = json_encode(['data' => 'data:application/octet-stream;base64,' . $base64Garbage]);

    expect($rule->passes('file', $value))->toBeFalse();
});

test('passes returns false if the detected file extension is not in the allowed extensions (single part)', function () {
    $rule = new Base64Mime(['pdf']); // Only PDF allowed

    // A 1x1 black JPG image (base64 encoded)
    $onePixelJpeg = '/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAAMCAgMCAgMDAwMEAwMEBQgFBQQEBQoHBwYIDAoMDAsKCwsNDhIQDQ4RDgsLEBYQERMUFRUVDA8XGBYUGBIUFRT/2wBDAQMEBAUEBQoFBQoUDAsJDg8ODg4ODg4ODg4ODg4ODg4ODg4ODg4ODg4ODg4ODg4ODg4ODg4ODg4ODg4ODg4ODg//wAARCAABAAEDAREAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAD/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFAEBAAAAAAAAAAAAAAAAAAAAAP/EABQRAQAAAAAAAAAAAAAAAAAAAAD/2gAMAwAAhEDEQA/AJ2AAA//Z';
    $value = json_encode(['data' => 'data:image/jpeg;base64,' . $onePixelJpeg]);

    expect($rule->passes('image', $value))->toBeFalse();
});

test('passes returns false if the detected file extension is not in the allowed extensions (multi-part, no match)', function () {
    $rule = new Base64Mime(['jpg']); // Only JPG allowed

    // A 1x1 black PNG image (base64 encoded)
    $onePixelPng = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';
    $value = json_encode(['data' => 'data:image/png;base64,' . $onePixelPng]);

    expect($rule->passes('image', $value))->toBeFalse();
});

test('passes returns true for an allowed single-part extension', function () {
    $rule = new Base64Mime(['jpeg', 'png']); // JPG/PNG allowed

    // A 1x1 black JPG image (base64 encoded)
    $onePixelJpeg = '/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAAMCAgMCAgMDAwMEAwMEBQgFBQQEBQoHBwYIDAoMDAsKCwsNDhIQDQ4RDgsLEBYQERMUFRUVDA8XGBYUGBIUFRT/2wBDAQMEBAUEBQoFBQoUDAsJDg8ODg4ODg4ODg4ODg4ODg4ODg4ODg4ODg4ODg4ODg4ODg4ODg4ODg4ODg4ODg4ODg//wAARCAABAAEDAREAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAD/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFAEBAAAAAAAAAAAAAAAAAAAAAP/EABQRAQAAAAAAAAAAAAAAAAAAAAD/2gAMAwAAhEDEQA/AJ2AAA//Z';
    $value = json_encode(['data' => 'data:image/jpeg;base64,' . $onePixelJpeg]);

    expect($rule->passes('image', $value))->toBeTrue();
});

test('passes returns true for an allowed multi-part extension', function () {
    $rule = new Base64Mime(['jpg', 'png']); // JPG/PNG allowed

    // A 1x1 black PNG image (base64 encoded)
    $onePixelPng = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';
    $value = json_encode(['data' => 'data:image/png;base64,' . $onePixelPng]);

    expect($rule->passes('image', $value))->toBeTrue();
});

test('passes returns true when an alias for the extension is allowed (e.g., pjpeg detected as jpeg)', function () {
    $rule = new Base64Mime(['jpeg']);

    // A 1x1 black JPG image, with 'image/pjpeg' as the explicit MIME type in data URL.
    // `finfo_buffer` should typically identify this as 'jpeg', which is in the allowed list.
    $onePixelJpeg = '/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAAMCAgMCAgMDAwMEAwMEBQgFBQQEBQoHBwYIDAoMDAsKCwsNDhIQDQ4RDgsLEBYQERMUFRUVDA8XGBYUGBIUFRT/2wBDAQMEBAUEBQoFBQoUDAsJDg8ODg4ODg4ODg4ODg4ODg4ODg4ODg4ODg4ODg4ODg4ODg4ODg4ODg4ODg4ODg4ODg//wAARCAABAAEDAREAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAD/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFAEBAAAAAAAAAAAAAAAAAAAAAP/EABQRAQAAAAAAAAAAAAAAAAAAAAD/2gAMAwAAhEDEQA/AJ2AAA//Z';
    $value = json_encode(['data' => 'data:image/pjpeg;base64,' . $onePixelJpeg]);

    expect($rule->passes('image', $value))->toBeTrue();
});



afterEach(function () {
    Mockery::close();
});