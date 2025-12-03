<?php

use Crater\Listeners\Updates\Listener;

test('isListenerFired returns true when static::VERSION is less than event->old', function () {
    // Create an anonymous class to override the protected method and VERSION constant
    $listener = new class extends Listener {
        public const VERSION = '1.0.0'; // Simulate current listener version

        public function publicIsListenerFired($event) {
            return $this->isListenerFired($event);
        }
    };

    // Mock an event object where the 'old' version is newer than the listener's version
    $event = (object) ['old' => '1.0.1'];

    expect($listener->publicIsListenerFired($event))->toBeTrue();
});

test('isListenerFired returns true when static::VERSION is equal to event->old', function () {
    $listener = new class extends Listener {
        public const VERSION = '1.0.0'; // Simulate current listener version

        public function publicIsListenerFired($event) {
            return $this->isListenerFired($event);
        }
    };

    // Mock an event object where the 'old' version is the same as the listener's version
    $event = (object) ['old' => '1.0.0'];

    expect($listener->publicIsListenerFired($event))->toBeTrue();
});

test('isListenerFired returns false when static::VERSION is greater than event->old', function () {
    $listener = new class extends Listener {
        public const VERSION = '1.0.1'; // Simulate current listener version

        public function publicIsListenerFired($event) {
            return $this->isListenerFired($event);
        }
    };

    // Mock an event object where the 'old' version is older than the listener's version
    $event = (object) ['old' => '1.0.0'];

    expect($listener->publicIsListenerFired($event))->toBeFalse();
});

test('isListenerFired handles complex version strings correctly (less than)', function () {
    $listener = new class extends Listener {
        public const VERSION = '2.0.0-alpha.1'; // Simulate an alpha version

        public function publicIsListenerFired($event) {
            return $this->isListenerFired($event);
        }
    };

    // Beta version is considered "newer" than alpha by version_compare
    $event = (object) ['old' => '2.0.0-beta.1'];

    expect($listener->publicIsListenerFired($event))->toBeTrue();
});

test('isListenerFired handles complex version strings correctly (equal)', function () {
    $listener = new class extends Listener {
        public const VERSION = '2.0.0-RC1'; // Simulate a Release Candidate version

        public function publicIsListenerFired($event) {
            return $this->isListenerFired($event);
        }
    };

    // Same RC version
    $event = (object) ['old' => '2.0.0-RC1'];

    expect($listener->publicIsListenerFired($event))->toBeTrue();
});

test('isListenerFired handles complex version strings correctly (greater than)', function () {
    $listener = new class extends Listener {
        public const VERSION = '2.0.0-RC2'; // Simulate a newer Release Candidate version

        public function publicIsListenerFired($event) {
            return $this->isListenerFired($event);
        }
    };

    // Older RC version
    $event = (object) ['old' => '2.0.0-RC1'];

    expect($listener->publicIsListenerFired($event))->toBeFalse();
});

test('isListenerFired handles empty version strings (both empty, should be true)', function () {
    $listener = new class extends Listener {
        public const VERSION = ''; // Simulate an empty listener version

        public function publicIsListenerFired($event) {
            return $this->isListenerFired($event);
        }
    };

    // Empty event version
    $event = (object) ['old' => ''];

    expect($listener->publicIsListenerFired($event))->toBeTrue();
});

test('isListenerFired handles empty version strings (static::VERSION empty, event->old not empty, should be true)', function () {
    $listener = new class extends Listener {
        public const VERSION = ''; // Simulate an empty listener version

        public function publicIsListenerFired($event) {
            return $this->isListenerFired($event);
        }
    };

    // Non-empty event version (considered "greater" than empty)
    $event = (object) ['old' => '1.0.0'];

    expect($listener->publicIsListenerFired($event))->toBeTrue();
});

test('isListenerFired handles empty version strings (static::VERSION not empty, event->old empty, should be false)', function () {
    $listener = new class extends Listener {
        public const VERSION = '1.0.0'; // Simulate a non-empty listener version

        public function publicIsListenerFired($event) {
            return $this->isListenerFired($event);
        }
    };

    // Empty event version (considered "less" than non-empty)
    $event = (object) ['old' => ''];

    expect($listener->publicIsListenerFired($event))->toBeFalse();
});




afterEach(function () {
    Mockery::close();
});
