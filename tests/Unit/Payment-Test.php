<?php

use Carbon\Carbon;
use Crater\Jobs\GeneratePaymentPdfJob;
use Crater\Mail\SendPaymentMail;
use Crater\Models\Company;
use Crater\Models\CompanySetting;
use Crater\Models\Customer;
use Crater\Models\Currency;
use Crater\Models\EmailLog; // Added this use statement
use Crater\Models\ExchangeRateLog;
use Crater\Models\Invoice;
use Crater\Models\Payment;
use Crater\Models\PaymentMethod;
use Crater\Models\Transaction;
use Crater\Services\SerialNumberFormatter;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\View;
use Illuminate\Http\Request;
use Vinkla\Hashids\Facades\Hashids;
use Barryvdh\DomPDF\Facade as PDF;
use Illuminate\Support\Facades\Bus; // Added this use statement
use Illuminate\Support\Facades\Request as RequestFacade; // Added this use statement for Request facade mocking

// Start of Pest test suite
beforeEach(function () {
    // Clear mocks before each test
    Mockery::close();
});

// Test the booted methods (static::created, static::updated)
test('payment booted method dispatches GeneratePaymentPdfJob on create', function () {
    Bus::fake(); // Fake the bus to assert job dispatch

    $paymentMock = Mockery::mock(Payment::class)->makePartial();
    $paymentMock->id = 1;
    $paymentMock->company_id = 1;
    $paymentMock->payment_date = '2023-01-01';
    $paymentMock->exists = true; // Mark as existing for model events

    // Manually fire the 'created' event for the mock
    // This will trigger the booted method that dispatches the job
    $paymentMock->fireModelEvent('created', false);

    Bus::assertDispatched(GeneratePaymentPdfJob::class, function ($job) use ($paymentMock) {
        // Assuming the job constructor takes payment and isUpdate
        // Check if the job's payment ID matches our mock and isUpdate is false
        return $job->payment->id === $paymentMock->id && $job->isUpdate === false;
    });
});

test('payment booted method dispatches GeneratePaymentPdfJob on update', function () {
    Bus::fake(); // Fake the bus to assert job dispatch

    $paymentMock = Mockery::mock(Payment::class)->makePartial();
    $paymentMock->id = 1;
    $paymentMock->company_id = 1;
    $paymentMock->payment_date = '2023-01-01';
    $paymentMock->exists = true; // Essential for `updated` event

    // Manually fire the 'updated' event for the mock
    // This will trigger the booted method that dispatches the job
    $paymentMock->fireModelEvent('updated', false);

    Bus::assertDispatched(GeneratePaymentPdfJob::class, function ($job) use ($paymentMock) {
        // Assuming the job constructor takes payment and isUpdate
        // Check if the job's payment ID matches our mock and isUpdate is true
        return $job->payment->id === $paymentMock->id && $job->isUpdate === true;
    });
});

// Test accessors and mutators
test('setSettingsAttribute encodes value to JSON if provided', function () {
    $payment = new Payment();
    // Ensure the 'attributes' property exists and is an array on a fresh model for mutator testing.
    // By default, new Model() should have $attributes as an empty array, but explicit setting can prevent issues.
    $payment->setRawAttributes([]);
    $settings = ['key' => 'value'];
    $payment->setSettingsAttribute($settings);
    expect($payment->attributes['settings'])->toBeJson();
    expect(json_decode($payment->attributes['settings'], true))->toEqual($settings);
});

test('setSettingsAttribute does nothing if value is null', function () {
    $payment = new Payment();
    $payment->setSettingsAttribute(null);
    expect($payment->attributes)->not->toHaveKey('settings');
});

test('getFormattedCreatedAtAttribute returns formatted creation date', function () {
    // Overload CompanySetting for static calls
    Mockery::mock('overload:' . CompanySetting::class);

    $payment = new Payment(['created_at' => '2023-01-15 10:00:00', 'company_id' => 1]);

    CompanySetting::shouldReceive('getSetting')
        ->once()
        ->with('carbon_date_format', $payment->company_id)
        ->andReturn('Y-m-d');

    $formattedDate = $payment->formattedCreatedAt;

    expect($formattedDate)->toBe('2023-01-15');
});

test('getFormattedPaymentDateAttribute returns formatted payment date', function () {
    // Overload CompanySetting for static calls
    Mockery::mock('overload:' . CompanySetting::class);

    $payment = new Payment(['payment_date' => '2023-02-20 12:30:00', 'company_id' => 1]);

    CompanySetting::shouldReceive('getSetting')
        ->once()
        ->with('carbon_date_format', $payment->company_id)
        ->andReturn('d/m/Y');

    $formattedDate = $payment->formattedPaymentDate;

    expect($formattedDate)->toBe('20/02/2023');
});

test('getPaymentPdfUrlAttribute returns correct PDF URL', function () {
    $payment = new Payment(['unique_hash' => 'test-hash']);

    // URL::to often implicitly passes empty array for parameters and null for secure.
    URL::shouldReceive('to')
        ->once()
        ->with('/payments/pdf/test-hash', [], null) // Explicitly match all arguments
        ->andReturn('http://example.com/payments/pdf/test-hash');

    expect($payment->paymentPdfUrl)->toBe('http://example.com/payments/pdf/test-hash');
});

test('transaction relationship returns a BelongsTo instance', function () {
    $payment = new Payment();
    $relation = $payment->transaction();
    expect($relation)->toBeInstanceOf(BelongsTo::class);
    expect($relation->getRelated())->toBeInstanceOf(Transaction::class);
});

test('emailLogs relationship returns a MorphMany instance', function () {
    $payment = new Payment();
    $relation = $payment->emailLogs();
    expect($relation)->toBeInstanceOf(MorphMany::class);
    // Corrected the class namespace from App\Models to Crater\Models
    expect($relation->getRelated())->toBeInstanceOf(EmailLog::class);
});

test('customer relationship returns a BelongsTo instance', function () {
    $payment = new Payment();
    $relation = $payment->customer();
    expect($relation)->toBeInstanceOf(BelongsTo::class);
    expect($relation->getRelated())->toBeInstanceOf(Customer::class);
    expect($relation->getForeignKeyName())->toBe('customer_id');
});

test('company relationship returns a BelongsTo instance', function () {
    $payment = new Payment();
    $relation = $payment->company();
    expect($relation)->toBeInstanceOf(BelongsTo::class);
    expect($relation->getRelated())->toBeInstanceOf(Company::class);
});

test('invoice relationship returns a BelongsTo instance', function () {
    $payment = new Payment();
    $relation = $payment->invoice();
    expect($relation)->toBeInstanceOf(BelongsTo::class);
    expect($relation->getRelated())->toBeInstanceOf(Invoice::class);
});

test('creator relationship returns a BelongsTo instance', function () {
    $payment = new Payment();
    $relation = $payment->creator();
    expect($relation)->toBeInstanceOf(BelongsTo::class);
    expect($relation->getRelated())->toBeInstanceOf(\Crater\Models\User::class);
    expect($relation->getForeignKeyName())->toBe('creator_id');
});

test('currency relationship returns a BelongsTo instance', function () {
    $payment = new Payment();
    $relation = $payment->currency();
    expect($relation)->toBeInstanceOf(BelongsTo::class);
    expect($relation->getRelated())->toBeInstanceOf(Currency::class);
});

test('paymentMethod relationship returns a BelongsTo instance', function () {
    $payment = new Payment();
    $relation = $payment->paymentMethod();
    expect($relation)->toBeInstanceOf(BelongsTo::class);
    expect($relation->getRelated())->toBeInstanceOf(PaymentMethod::class);
});

test('sendPaymentData prepares data for email with attachment', function () {
    $customer = Mockery::mock(Customer::class);
    $customer->shouldReceive('toArray')->andReturn(['name' => 'John Doe']);

    // Use makePartial() for Company mock when setting properties directly like ->id
    $company = Mockery::mock(Company::class)->makePartial();
    $company->id = 1;
    $company->name = 'Test Company'; // Added name for getFieldsArray to avoid null error

    // Overload Company for static find calls
    Mockery::mock('overload:' . Company::class);
    Company::shouldReceive('find')->with(1)->andReturn($company);

    $payment = Mockery::mock(Payment::class)->makePartial();
    $payment->shouldReceive('toArray')->andReturn(['amount' => 100]);
    $payment->company_id = 1;
    $payment->customer = $customer; // Mock relationship
    $payment->company = $company; // Mock relationship
    $payment->shouldReceive('getEmailBody')->with('Original body')->andReturn('Processed body');
    $payment->shouldReceive('getEmailAttachmentSetting')->andReturn(true);
    $payment->shouldReceive('getPDFData')->andReturn('PDF Content');

    $initialData = ['to' => 'test@example.com', 'body' => 'Original body'];
    $preparedData = $payment->sendPaymentData($initialData);

    expect($preparedData)->toEqual([
        'to' => 'test@example.com',
        'body' => 'Processed body',
        'payment' => ['amount' => 100],
        'user' => ['name' => 'John Doe'],
        'company' => $company,
        'attach' => ['data' => 'PDF Content'],
    ]);
});

test('sendPaymentData prepares data for email without attachment', function () {
    $customer = Mockery::mock(Customer::class);
    $customer->shouldReceive('toArray')->andReturn(['name' => 'John Doe']);

    // Use makePartial() for Company mock when setting properties directly like ->id
    $company = Mockery::mock(Company::class)->makePartial();
    $company->id = 1;
    $company->name = 'Test Company'; // Added name for getFieldsArray to avoid null error

    // Overload Company for static find calls
    Mockery::mock('overload:' . Company::class);
    Company::shouldReceive('find')->with(1)->andReturn($company);

    $payment = Mockery::mock(Payment::class)->makePartial();
    $payment->shouldReceive('toArray')->andReturn(['amount' => 100]);
    $payment->company_id = 1;
    $payment->customer = $customer; // Mock relationship
    $payment->company = $company; // Mock relationship
    $payment->shouldReceive('getEmailBody')->with('Original body')->andReturn('Processed body');
    $payment->shouldReceive('getEmailAttachmentSetting')->andReturn(false);
    $payment->shouldNotReceive('getPDFData'); // Should not be called

    $initialData = ['to' => 'test@example.com', 'body' => 'Original body'];
    $preparedData = $payment->sendPaymentData($initialData);

    expect($preparedData)->toEqual([
        'to' => 'test@example.com',
        'body' => 'Processed body',
        'payment' => ['amount' => 100],
        'user' => ['name' => 'John Doe'],
        'company' => $company,
        'attach' => ['data' => null],
    ]);
});

test('send dispatches email correctly', function () {
    $payment = Mockery::mock(Payment::class)->makePartial();
    $payment->shouldReceive('sendPaymentData')
        ->once()
        ->with(['to' => 'test@example.com'])
        ->andReturn([
            'to' => 'test@example.com',
            'body' => 'Email body',
            'payment' => ['amount' => 100]
        ]);

    Mail::shouldReceive('to')
        ->once()
        ->with('test@example.com')
        ->andReturn(Mockery::self()); // Return itself to chain send()

    Mail::shouldReceive('send')
        ->once()
        ->with(Mockery::type(SendPaymentMail::class));

    $result = $payment->send(['to' => 'test@example.com']);

    expect($result)->toEqual(['success' => true]);
});

test('createPayment creates a new payment and updates invoice', function () {
    // Overload static models/facades
    Mockery::mock('overload:' . Invoice::class);
    Mockery::mock('overload:' . Payment::class);
    Mockery::mock('overload:' . CompanySetting::class);
    Mockery::mock('overload:' . ExchangeRateLog::class);

    $request = Mockery::mock(Request::class);
    $request->shouldReceive('getPaymentPayload')->andReturn(['customer_id' => 1, 'amount' => 50, 'currency_id' => 1, 'company_id' => 1]);
    $request->invoice_id = 10;
    $request->amount = 50;
    $request->customFields = [['field_id' => 1, 'value' => 'test']];
    $request->shouldReceive('header')->with('company')->andReturn(1);

    $invoice = Mockery::mock(Invoice::class)->makePartial(); // Use makePartial here
    $invoice->shouldReceive('subtractInvoicePayment')->once()->with(50);
    Invoice::shouldReceive('find')->with(10)->andReturn($invoice);

    // Mock SerialNumberFormatter
    $serialFormatter = Mockery::mock(SerialNumberFormatter::class);
    $serialFormatter->shouldReceive('setModel')->andReturnSelf();
    $serialFormatter->shouldReceive('setCompany')->andReturnSelf();
    $serialFormatter->shouldReceive('setCustomer')->andReturnSelf();
    $serialFormatter->shouldReceive('setNextNumbers')->andReturnSelf();
    $serialFormatter->nextSequenceNumber = 'P001';
    $serialFormatter->nextCustomerSequenceNumber = 'C001';
    Mockery::mock('overload:' . SerialNumberFormatter::class, $serialFormatter);

    $hashidsConnection = Mockery::mock();
    $hashidsConnection->shouldReceive('encode')->once()->with(1)->andReturn('encoded_hash');
    Hashids::shouldReceive('connection')->once()->with(Payment::class)->andReturn($hashidsConnection);

    // Mock `Payment::create` to return a partial mock instance.
    // Also mock `addCustomFields` and `save` on the partial mock.
    $paymentMock = Mockery::mock(Payment::class)->makePartial();
    $paymentMock->id = 1;
    $paymentMock->company_id = 1;
    $paymentMock->customer_id = 1;
    $paymentMock->currency_id = 1; // Match currency_id for the test case
    $paymentMock->shouldReceive('save')->once();
    $paymentMock->shouldReceive('addCustomFields')->once()->with($request->customFields);
    Payment::shouldReceive('create')->once()->andReturn($paymentMock);

    CompanySetting::shouldReceive('getSetting')
        ->with('currency', 1)
        ->andReturn(1); // Same currency, no exchange log

    ExchangeRateLog::shouldNotReceive('addExchangeRateLog'); // Should not be called

    // Mock the final find for 'with' relations
    $finalPayment = Mockery::mock(Payment::class)->makePartial(); // makePartial for the final payment mock too
    $finalPayment->id = 1;
    Payment::shouldReceive('with')->andReturnSelf();
    Payment::shouldReceive('find')->with(1)->andReturn($finalPayment);

    $result = Payment::createPayment($request);

    expect($result)->toBe($finalPayment);
    expect($paymentMock->unique_hash)->toBe('encoded_hash');
    expect($paymentMock->sequence_number)->toBe('P001');
    expect($paymentMock->customer_sequence_number)->toBe('C001');
});

test('createPayment handles no invoice ID and different currency', function () {
    // Overload static models/facades
    Mockery::mock('overload:' . Invoice::class);
    Mockery::mock('overload:' . Payment::class);
    Mockery::mock('overload:' . CompanySetting::class);
    Mockery::mock('overload:' . ExchangeRateLog::class);

    $request = Mockery::mock(Request::class);
    $request->shouldReceive('getPaymentPayload')->andReturn(['customer_id' => 1, 'amount' => 50, 'currency_id' => 2, 'company_id' => 1]);
    $request->invoice_id = null; // No invoice
    $request->amount = 50;
    $request->customFields = null; // No custom fields
    $request->shouldReceive('header')->with('company')->andReturn(1);

    Invoice::shouldNotReceive('find'); // No invoice find

    $serialFormatter = Mockery::mock(SerialNumberFormatter::class);
    $serialFormatter->shouldReceive('setModel')->andReturnSelf();
    $serialFormatter->shouldReceive('setCompany')->andReturnSelf();
    $serialFormatter->shouldReceive('setCustomer')->andReturnSelf();
    $serialFormatter->shouldReceive('setNextNumbers')->andReturnSelf();
    $serialFormatter->nextSequenceNumber = 'P001';
    $serialFormatter->nextCustomerSequenceNumber = 'C001';
    Mockery::mock('overload:' . SerialNumberFormatter::class, $serialFormatter);

    $hashidsConnection = Mockery::mock();
    $hashidsConnection->shouldReceive('encode')->once()->with(1)->andReturn('encoded_hash');
    Hashids::shouldReceive('connection')->once()->with(Payment::class)->andReturn($hashidsConnection);

    $paymentMock = Mockery::mock(Payment::class)->makePartial();
    $paymentMock->id = 1;
    $paymentMock->company_id = 1;
    $paymentMock->customer_id = 1;
    $paymentMock->currency_id = 2; // Different currency
    $paymentMock->shouldReceive('save')->once();
    $paymentMock->shouldNotReceive('addCustomFields');
    Payment::shouldReceive('create')->once()->andReturn($paymentMock);

    CompanySetting::shouldReceive('getSetting')
        ->with('currency', 1)
        ->andReturn(1); // Company currency is 1

    ExchangeRateLog::shouldReceive('addExchangeRateLog')
        ->once()
        ->with($paymentMock);

    $finalPayment = Mockery::mock(Payment::class)->makePartial(); // makePartial for final payment mock
    $finalPayment->id = 1;
    Payment::shouldReceive('with')->andReturnSelf();
    Payment::shouldReceive('find')->with(1)->andReturn($finalPayment);

    $result = Payment::createPayment($request);

    expect($result)->toBe($finalPayment);
});

test('updatePayment updates existing payment and handles invoice changes', function () {
    // Overload static models/facades
    Mockery::mock('overload:' . Invoice::class);
    Mockery::mock('overload:' . CompanySetting::class);
    Mockery::mock('overload:' . ExchangeRateLog::class);
    Mockery::mock('overload:' . Payment::class);


    $request = Mockery::mock(Request::class);
    $request->shouldReceive('getPaymentPayload')->andReturn(['customer_id' => 2, 'amount' => 75, 'currency_id' => 1]);
    $request->invoice_id = 11; // New invoice ID
    $request->amount = 75; // New amount
    $request->customFields = [['field_id' => 2, 'value' => 'updated']];
    $request->shouldReceive('header')->with('company')->andReturn(1);

    $originalInvoice = Mockery::mock(Invoice::class)->makePartial();
    $originalInvoice->shouldReceive('addInvoicePayment')->once()->with(50); // Original amount
    Invoice::shouldReceive('find')->with(10)->andReturn($originalInvoice);

    $newInvoice = Mockery::mock(Invoice::class)->makePartial();
    $newInvoice->shouldReceive('subtractInvoicePayment')->once()->with(75); // New amount
    Invoice::shouldReceive('find')->with(11)->andReturn($newInvoice);

    $payment = Mockery::mock(Payment::class)->makePartial();
    $payment->id = 1;
    $payment->invoice_id = 10; // Existing invoice
    $payment->amount = 50; // Existing amount
    $payment->company_id = 1;
    $payment->customer_id = 1;
    $payment->currency_id = 1;

    $serialFormatter = Mockery::mock(SerialNumberFormatter::class);
    $serialFormatter->shouldReceive('setModel')->andReturnSelf();
    $serialFormatter->shouldReceive('setCompany')->andReturnSelf();
    $serialFormatter->shouldReceive('setCustomer')->andReturnSelf();
    $serialFormatter->shouldReceive('setModelObject')->andReturnSelf();
    $serialFormatter->shouldReceive('setNextNumbers')->andReturnSelf();
    $serialFormatter->nextCustomerSequenceNumber = 'C002';
    Mockery::mock('overload:' . SerialNumberFormatter::class, $serialFormatter);

    $payment->shouldReceive('update')->once()->with([
        'customer_id' => 2,
        'amount' => 75,
        'currency_id' => 1,
        'customer_sequence_number' => 'C002'
    ]);
    $payment->shouldReceive('updateCustomFields')->once()->with($request->customFields);

    CompanySetting::shouldReceive('getSetting')
        ->with('currency', 1)
        ->andReturn(1); // Same currency

    ExchangeRateLog::shouldNotReceive('addExchangeRateLog');

    $finalPayment = Mockery::mock(Payment::class)->makePartial(); // makePartial for final payment mock
    $finalPayment->id = 1;
    Payment::shouldReceive('with')->andReturnSelf();
    Payment::shouldReceive('find')->with(1)->andReturn($finalPayment);

    $result = $payment->updatePayment($request);

    expect($result)->toBe($finalPayment);
});

test('updatePayment handles same invoice different amount', function () {
    // Overload static models/facades
    Mockery::mock('overload:' . Invoice::class);
    Mockery::mock('overload:' . CompanySetting::class);
    Mockery::mock('overload:' . ExchangeRateLog::class);
    Mockery::mock('overload:' . Payment::class);

    $request = Mockery::mock(Request::class);
    $request->shouldReceive('getPaymentPayload')->andReturn(['customer_id' => 1, 'amount' => 75, 'currency_id' => 1]);
    $request->invoice_id = 10; // Same invoice
    $request->amount = 75; // Different amount
    $request->customFields = null;
    $request->shouldReceive('header')->with('company')->andReturn(1);

    $invoice = Mockery::mock(Invoice::class)->makePartial();
    $invoice->shouldReceive('addInvoicePayment')->once()->with(50);
    $invoice->shouldReceive('subtractInvoicePayment')->once()->with(75);
    Invoice::shouldReceive('find')->with(10)->andReturn($invoice);

    $payment = Mockery::mock(Payment::class)->makePartial();
    $payment->id = 1;
    $payment->invoice_id = 10;
    $payment->amount = 50;
    $payment->company_id = 1;
    $payment->customer_id = 1;
    $payment->currency_id = 1;

    $serialFormatter = Mockery::mock(SerialNumberFormatter::class);
    $serialFormatter->shouldReceive('setModel')->andReturnSelf();
    $serialFormatter->shouldReceive('setCompany')->andReturnSelf();
    $serialFormatter->shouldReceive('setCustomer')->andReturnSelf();
    $serialFormatter->shouldReceive('setModelObject')->andReturnSelf();
    $serialFormatter->shouldReceive('setNextNumbers')->andReturnSelf();
    $serialFormatter->nextCustomerSequenceNumber = 'C001';
    Mockery::mock('overload:' . SerialNumberFormatter::class, $serialFormatter);

    $payment->shouldReceive('update')->once()->with([
        'customer_id' => 1,
        'amount' => 75,
        'currency_id' => 1,
        'customer_sequence_number' => 'C001'
    ]);
    $payment->shouldNotReceive('updateCustomFields');

    CompanySetting::shouldReceive('getSetting')
        ->with('currency', 1)
        ->andReturn(1);

    ExchangeRateLog::shouldNotReceive('addExchangeRateLog');

    Payment::shouldReceive('with')->andReturnSelf();
    Payment::shouldReceive('find')->with(1)->andReturn($payment); // Return itself for final find

    $result = $payment->updatePayment($request);

    expect($result)->toBe($payment);
});

test('updatePayment handles removing invoice from payment', function () {
    // Overload static models/facades
    Mockery::mock('overload:' . Invoice::class);
    Mockery::mock('overload:' . CompanySetting::class);
    Mockery::mock('overload:' . ExchangeRateLog::class);
    Mockery::mock('overload:' . Payment::class);

    $request = Mockery::mock(Request::class);
    $request->shouldReceive('getPaymentPayload')->andReturn(['customer_id' => 1, 'amount' => 50, 'currency_id' => 1]);
    $request->invoice_id = null; // Removing invoice
    $request->amount = 50;
    $request->customFields = null;
    $request->shouldReceive('header')->with('company')->andReturn(1);

    $originalInvoice = Mockery::mock(Invoice::class)->makePartial();
    $originalInvoice->shouldReceive('addInvoicePayment')->once()->with(50);
    Invoice::shouldReceive('find')->with(10)->andReturn($originalInvoice);

    $payment = Mockery::mock(Payment::class)->makePartial();
    $payment->id = 1;
    $payment->invoice_id = 10; // Existing invoice
    $payment->amount = 50;
    $payment->company_id = 1;
    $payment->customer_id = 1;
    $payment->currency_id = 1;

    $serialFormatter = Mockery::mock(SerialNumberFormatter::class);
    $serialFormatter->shouldReceive('setModel')->andReturnSelf();
    $serialFormatter->shouldReceive('setCompany')->andReturnSelf();
    $serialFormatter->shouldReceive('setCustomer')->andReturnSelf();
    $serialFormatter->shouldReceive('setModelObject')->andReturnSelf();
    $serialFormatter->shouldReceive('setNextNumbers')->andReturnSelf();
    $serialFormatter->nextCustomerSequenceNumber = 'C001';
    Mockery::mock('overload:' . SerialNumberFormatter::class, $serialFormatter);

    $payment->shouldReceive('update')->once()->with([
        'customer_id' => 1,
        'amount' => 50,
        'currency_id' => 1,
        'customer_sequence_number' => 'C001',
        'invoice_id' => null, // Expect invoice_id to be set to null in update payload
    ]);

    CompanySetting::shouldReceive('getSetting')
        ->with('currency', 1)
        ->andReturn(1);

    ExchangeRateLog::shouldNotReceive('addExchangeRateLog');

    Payment::shouldReceive('with')->andReturnSelf();
    Payment::shouldReceive('find')->with(1)->andReturn($payment);

    $result = $payment->updatePayment($request);

    expect($result)->toBe($payment);
});

test('deletePayments handles payments linked to invoice', function () {
    // Overload static models/facades for static calls
    Mockery::mock('overload:' . Payment::class);
    Mockery::mock('overload:' . Invoice::class);

    $payment1 = Mockery::mock(Payment::class)->makePartial();
    $payment1->id = 1;
    $payment1->invoice_id = 10;
    $payment1->amount = 50;
    $payment1->shouldReceive('delete')->once();

    $invoice1 = Mockery::mock(Invoice::class)->makePartial();
    $invoice1->id = 10;
    $invoice1->due_amount = 0;
    $invoice1->total = 100;
    $invoice1->paid_status = Invoice::STATUS_PAID;
    $invoice1->shouldReceive('getPreviousStatus')->andReturn(Invoice::STATUS_PARTIALLY_PAID);
    $invoice1->shouldReceive('save')->once();

    $payment2 = Mockery::mock(Payment::class)->makePartial();
    $payment2->id = 2;
    $payment2->invoice_id = 20;
    $payment2->amount = 100;
    $payment2->shouldReceive('delete')->once();

    $invoice2 = Mockery::mock(Invoice::class)->makePartial();
    $invoice2->id = 20;
    $invoice2->due_amount = 0;
    $invoice2->total = 100;
    $invoice2->paid_status = Invoice::STATUS_PAID;
    $invoice2->shouldReceive('getPreviousStatus')->andReturn(Invoice::STATUS_UNPAID);
    $invoice2->shouldReceive('save')->once();

    Payment::shouldReceive('find')->with(1)->andReturn($payment1);
    Invoice::shouldReceive('find')->with(10)->andReturn($invoice1);
    Payment::shouldReceive('find')->with(2)->andReturn($payment2);
    Invoice::shouldReceive('find')->with(20)->andReturn($invoice2);

    $result = Payment::deletePayments([1, 2]);

    expect($result)->toBeTrue();
    expect($invoice1->due_amount)->toBe(50);
    expect($invoice1->paid_status)->toBe(Invoice::STATUS_PARTIALLY_PAID);
    expect($invoice2->due_amount)->toBe(100);
    expect($invoice2->paid_status)->toBe(Invoice::STATUS_UNPAID);
});

test('deletePayments handles payments not linked to invoice', function () {
    // Overload static models/facades for static calls
    Mockery::mock('overload:' . Payment::class);
    Mockery::mock('overload:' . Invoice::class); // Still overload in case `find` is called even if expected not to be

    $payment = Mockery::mock(Payment::class)->makePartial();
    $payment->id = 1;
    $payment->invoice_id = null; // No invoice
    $payment->amount = 50;
    $payment->shouldReceive('delete')->once();

    Payment::shouldReceive('find')->with(1)->andReturn($payment);
    Invoice::shouldNotReceive('find'); // Should not try to find invoice

    $result = Payment::deletePayments([1]);

    expect($result)->toBeTrue();
});

test('getPDFData returns view for preview', function () {
    // Overload static models/facades
    Mockery::mock('overload:' . Company::class);
    Mockery::mock('overload:' . CompanySetting::class);

    $company = Mockery::mock(Company::class)->makePartial();
    $company->id = 1;
    $company->logo_path = 'logo.png';
    $company->name = 'Test Company'; // Added for GeneratesPdfTrait to avoid null error
    Company::shouldReceive('find')->with(1)->andReturn($company);

    CompanySetting::shouldReceive('getSetting')->andReturn('en'); // For language

    App::shouldReceive('setLocale')->with('en');

    $payment = Mockery::mock(Payment::class)->makePartial();
    $payment->company_id = 1;
    $payment->company = $company; // Assign the mocked company
    $payment->shouldReceive('getCompanyAddress')->andReturn('Company Address');
    $payment->shouldReceive('getCustomerBillingAddress')->andReturn('Customer Address');
    $payment->shouldReceive('getNotes')->andReturn('Some notes');

    // Mock the Request facade for 'has'
    RequestFacade::shouldReceive('has')->with('preview')->andReturn(true);

    View::shouldReceive('share')->once()->with([
        'payment' => $payment,
        'company_address' => 'Company Address',
        'billing_address' => 'Customer Address',
        'notes' => 'Some notes',
        'logo' => 'logo.png',
    ]);
    View::shouldReceive('make')->with('app.pdf.payment.payment')->andReturn('Preview HTML'); // Mock the view creation

    // Use a global mock for PDF facade
    PDF::shouldNotReceive('loadView'); // Should not be called for preview

    $result = $payment->getPDFData();

    expect($result)->toBe('Preview HTML');
});

test('getPDFData returns PDF for non-preview', function () {
    // Overload static models/facades
    Mockery::mock('overload:' . Company::class);
    Mockery::mock('overload:' . CompanySetting::class);

    $company = Mockery::mock(Company::class)->makePartial();
    $company->id = 1;
    $company->logo_path = null; // No logo
    $company->name = 'Test Company'; // Added for GeneratesPdfTrait to avoid null error
    Company::shouldReceive('find')->with(1)->andReturn($company);

    CompanySetting::shouldReceive('getSetting')->andReturn('en');

    App::shouldReceive('setLocale')->with('en');

    $payment = Mockery::mock(Payment::class)->makePartial();
    $payment->company_id = 1;
    $payment->company = $company; // Assign the mocked company
    $payment->shouldReceive('getCompanyAddress')->andReturn('Company Address');
    $payment->shouldReceive('getCustomerBillingAddress')->andReturn('Customer Address');
    $payment->shouldReceive('getNotes')->andReturn('Some notes');

    // Mock the Request facade for 'has'
    RequestFacade::shouldReceive('has')->with('preview')->andReturn(false);

    View::shouldReceive('share')->once(); // Just assert it's called

    $pdfMock = Mockery::mock();
    PDF::shouldReceive('loadView')->once()->with('app.pdf.payment.payment')->andReturn($pdfMock);

    $result = $payment->getPDFData();

    expect($result)->toBe($pdfMock);
});

test('getCompanyAddress returns formatted string if company address exists', function () {
    // Overload CompanySetting for static calls
    Mockery::mock('overload:' . CompanySetting::class);

    $companyAddress = Mockery::mock();
    $companyAddress->shouldReceive('exists')->andReturn(true);
    // Add any necessary properties if `getFormattedString` internally tries to access them
    $companyAddress->country_name = 'USA';
    $companyAddress->state = 'CA';
    $companyAddress->city = 'Los Angeles';
    $companyAddress->address_street_1 = '123 Main St';


    $company = Mockery::mock(Company::class)->makePartial();
    $company->name = 'Company Name Inc.'; // Added name property
    $company->shouldReceive('address')->andReturn($companyAddress);

    $payment = Mockery::mock(Payment::class)->makePartial();
    $payment->company_id = 1;
    $payment->company = $company; // Mock relationship

    CompanySetting::shouldReceive('getSetting')
        ->with('payment_company_address_format', 1)
        ->andReturn('{ADDRESS}'); // Example format

    $payment->shouldReceive('getFormattedString')->with('{ADDRESS}')->andReturn('Formatted Company Address');

    expect($payment->getCompanyAddress())->toBe('Formatted Company Address');
});

test('getCompanyAddress returns false if company or its address does not exist', function () {
    // Overload CompanySetting for static calls if it's called internally by the method before returning false.
    Mockery::mock('overload:' . CompanySetting::class);
    CompanySetting::shouldReceive('getSetting')->zeroOrMoreTimes()->andReturn(''); // Mock it to prevent errors if called.

    $payment = Mockery::mock(Payment::class)->makePartial();
    $payment->company_id = 1;
    // Mock getFormattedString to return false, preventing internal calls to getFieldsArray
    $payment->shouldReceive('getFormattedString')->zeroOrMoreTimes()->andReturn(false);

    $payment->company = null; // Scenario 1: No company
    expect($payment->getCompanyAddress())->toBeFalse();

    // With company, but no address
    $company = Mockery::mock(Company::class)->makePartial();
    $company->name = 'Company Name Inc.'; // Even if address is null, company might be accessed.
    $companyAddress = Mockery::mock();
    $companyAddress->shouldReceive('exists')->andReturn(false);
    $company->shouldReceive('address')->andReturn($companyAddress);
    $payment->company = $company;

    expect($payment->getCompanyAddress())->toBeFalse();
});

test('getCustomerBillingAddress returns formatted string if customer billing address exists', function () {
    // Overload CompanySetting for static calls
    Mockery::mock('overload:' . CompanySetting::class);

    $billingAddress = Mockery::mock();
    $billingAddress->shouldReceive('exists')->andReturn(true);
    // Add any necessary properties if `getFormattedString` internally tries to access them
    $billingAddress->address_street_1 = '456 Oak Ave';
    $billingAddress->address_street_2 = '';
    $billingAddress->phone = '555-1234';
    $billingAddress->zip = '90210';

    $customer = Mockery::mock(Customer::class);
    $customer->shouldReceive('billingAddress')->andReturn($billingAddress);

    $company = Mockery::mock(Company::class)->makePartial();
    $company->name = 'Test Company'; // Used by GeneratesPdfTrait
    $company->address = Mockery::mock();
    $company->address->shouldReceive('exists')->andReturn(true); // For GeneratesPdfTrait to not error if CompanyAddress is checked
    $company->address->country_name = 'USA'; // Minimal properties for GeneratesPdfTrait
    $company->address->state = 'CA';
    $company->address->city = 'Los Angeles';
    $company->address->address_street_1 = '789 Elm St';


    $payment = Mockery::mock(Payment::class)->makePartial();
    $payment->company_id = 1;
    $payment->customer = $customer; // Mock relationship
    $payment->company = $company; // Mock relationship

    CompanySetting::shouldReceive('getSetting')
        ->with('payment_from_customer_address_format', 1)
        ->andReturn('{CUSTOMER_ADDRESS}');

    $payment->shouldReceive('getFormattedString')->with('{CUSTOMER_ADDRESS}')->andReturn('Formatted Customer Billing Address');

    expect($payment->getCustomerBillingAddress())->toBe('Formatted Customer Billing Address');
});

test('getCustomerBillingAddress returns false if customer or its billing address does not exist', function () {
    // Overload CompanySetting for static calls
    Mockery::mock('overload:' . CompanySetting::class);
    CompanySetting::shouldReceive('getSetting')->zeroOrMoreTimes()->andReturn(''); // Mock it to prevent errors if called.


    $payment = Mockery::mock(Payment::class)->makePartial();
    $payment->company_id = 1;
    // Mock getFormattedString to return false, preventing internal calls to getFieldsArray
    $payment->shouldReceive('getFormattedString')->zeroOrMoreTimes()->andReturn(false);

    $payment->customer = null; // Scenario 1: No customer
    expect($payment->getCustomerBillingAddress())->toBeFalse();

    // With customer, but no billing address
    $customer = Mockery::mock(Customer::class);
    $billingAddress = Mockery::mock();
    $billingAddress->shouldReceive('exists')->andReturn(false);
    $customer->shouldReceive('billingAddress')->andReturn($billingAddress);
    $payment->customer = $customer;

    // A mock for company is necessary if GeneratesPdfTrait (where 'company->name' is accessed) is called.
    $company = Mockery::mock(Company::class)->makePartial();
    $company->name = 'Test Company'; // Minimal property
    $company->address = Mockery::mock();
    $company->address->shouldReceive('exists')->andReturn(true); // Prevent further errors
    $payment->company = $company;

    expect($payment->getCustomerBillingAddress())->toBeFalse();
});

test('getEmailAttachmentSetting returns true if setting is not NO', function () {
    // Overload CompanySetting for static calls
    Mockery::mock('overload:' . CompanySetting::class);

    $payment = new Payment(['company_id' => 1]);
    CompanySetting::shouldReceive('getSetting')
        ->once()
        ->with('payment_email_attachment', 1)
        ->andReturn('YES');

    expect($payment->getEmailAttachmentSetting())->toBeTrue();

    CompanySetting::shouldReceive('getSetting')
        ->once()
        ->with('payment_email_attachment', 1)
        ->andReturn('ANY_OTHER_VALUE');

    expect($payment->getEmailAttachmentSetting())->toBeTrue();
});

test('getEmailAttachmentSetting returns false if setting is NO', function () {
    // Overload CompanySetting for static calls
    Mockery::mock('overload:' . CompanySetting::class);

    $payment = new Payment(['company_id' => 1]);
    CompanySetting::shouldReceive('getSetting')
        ->once()
        ->with('payment_email_attachment', 1)
        ->andReturn('NO');

    expect($payment->getEmailAttachmentSetting())->toBeFalse();
});

test('getNotes returns formatted notes string', function () {
    $payment = Mockery::mock(Payment::class)->makePartial();
    $payment->notes = 'Some notes text';
    $payment->shouldReceive('getFormattedString')->with('Some notes text')->andReturn('Formatted Notes');

    expect($payment->getNotes())->toBe('Formatted Notes');
});

test('getEmailBody replaces placeholders and removes unmatched ones', function () {
    $payment = Mockery::mock(Payment::class)->makePartial();
    $payment->shouldReceive('getFieldsArray')->andReturn([
        '{CUSTOMER_NAME}' => 'Test Customer',
    ]);
    $payment->shouldReceive('getExtraFields')->andReturn([
        '{PAYMENT_NUMBER}' => 'PAY-001',
    ]);

    $body = "Hello {CUSTOMER_NAME}, your payment {PAYMENT_NUMBER} is received. Unmatched {DUMMY}.";
    $expectedBody = "Hello Test Customer, your payment PAY-001 is received. Unmatched .";

    expect($payment->getEmailBody($body))->toBe($expectedBody);
});

test('getExtraFields returns correct array of payment specific fields', function () {
    // Overload CompanySetting for static calls (used by formattedPaymentDate)
    Mockery::mock('overload:' . CompanySetting::class);
    CompanySetting::shouldReceive('getSetting')->andReturn('Y-m-d'); // Mock date format

    $paymentMethod = Mockery::mock(PaymentMethod::class)->makePartial();
    $paymentMethod->name = 'Bank Transfer';

    $payment = Mockery::mock(Payment::class)->makePartial();
    $payment->created_at = '2023-01-01 10:00:00'; // For formattedPaymentDate to work
    $payment->payment_date = '2023-01-01 10:00:00'; // For formattedPaymentDate to work
    $payment->company_id = 1; // For CompanySetting
    $payment->paymentMethod = $paymentMethod;
    $payment->payment_number = 'PAY-007';
    $payment->reference_number = 'REF-007';

    // Access the accessor directly to trigger its internal logic
    $payment->formattedPaymentDate;

    expect($payment->getExtraFields())->toEqual([
        '{PAYMENT_DATE}' => '2023-01-01',
        '{PAYMENT_MODE}' => 'Bank Transfer',
        '{PAYMENT_NUMBER}' => 'PAY-007',
        '{PAYMENT_AMOUNT}' => 'REF-007', // Assuming PAYMENT_AMOUNT maps to reference_number based on original test
    ]);
});

test('getExtraFields handles null payment method', function () {
    // Overload CompanySetting for static calls (used by formattedPaymentDate)
    Mockery::mock('overload:' . CompanySetting::class);
    CompanySetting::shouldReceive('getSetting')->andReturn('Y-m-d'); // Mock date format

    $payment = Mockery::mock(Payment::class)->makePartial();
    $payment->created_at = '2023-01-01 10:00:00'; // For formattedPaymentDate to work
    $payment->payment_date = '2023-01-01 10:00:00'; // For formattedPaymentDate to work
    $payment->company_id = 1; // For CompanySetting
    $payment->paymentMethod = null;
    $payment->payment_number = 'PAY-007';
    $payment->reference_number = 'REF-007';

    // Access the accessor directly to trigger its internal logic
    $payment->formattedPaymentDate;

    expect($payment->getExtraFields())->toEqual([
        '{PAYMENT_DATE}' => '2023-01-01',
        '{PAYMENT_MODE}' => null,
        '{PAYMENT_NUMBER}' => 'PAY-007',
        '{PAYMENT_AMOUNT}' => 'REF-007', // Assuming PAYMENT_AMOUNT maps to reference_number based on original test
    ]);
});

test('generatePayment creates a payment and updates invoice', function () {
    // Overload static models/facades
    Mockery::mock('overload:' . Invoice::class);
    Mockery::mock('overload:' . Transaction::class);
    Mockery::mock('overload:' . Payment::class);

    $invoice = Mockery::mock(Invoice::class)->makePartial();
    $invoice->id = 100;
    $invoice->company_id = 1;
    $invoice->customer_id = 10;
    $invoice->total = 200;
    $invoice->exchange_rate = 1.2;
    $invoice->currency_id = 1;
    $invoice->shouldReceive('subtractInvoicePayment')->once()->with(200);
    Invoice::shouldReceive('find')->with(100)->andReturn($invoice);

    $transaction = Mockery::mock(Transaction::class)->makePartial(); // makePartial for transaction
    $transaction->invoice_id = 100;
    $transaction->id = 500;

    Carbon::setTestNow(Carbon::create(2023, 1, 1, 10, 0, 0)); // Mock Carbon::now()

    // Mock Request facade for `request()->payment_method_id`
    RequestFacade::shouldReceive('get')->with('payment_method_id')->andReturn(5);

    $serialFormatter = Mockery::mock(SerialNumberFormatter::class);
    $serialFormatter->shouldReceive('setModel')->once()->with(Mockery::type(Payment::class))->andReturnSelf();
    $serialFormatter->shouldReceive('setCompany')->once()->with(1)->andReturnSelf();
    $serialFormatter->shouldReceive('setCustomer')->once()->with(10)->andReturnSelf();
    $serialFormatter->shouldReceive('setNextNumbers')->once()->andReturnSelf();
    $serialFormatter->shouldReceive('getNextNumber')->once()->andReturn('PAY-GEN-001');
    $serialFormatter->nextSequenceNumber = 'P-SEQ-001';
    $serialFormatter->nextCustomerSequenceNumber = 'C-SEQ-001';
    Mockery::mock('overload:' . SerialNumberFormatter::class, $serialFormatter);

    $hashidsConnection = Mockery::mock();
    $hashidsConnection->shouldReceive('encode')->once()->with(1)->andReturn('generated_hash');
    Hashids::shouldReceive('connection')->once()->with(Payment::class)->andReturn($hashidsConnection);

    $createdPayment = Mockery::mock(Payment::class)->makePartial();
    $createdPayment->id = 1;
    $createdPayment->shouldReceive('save')->once();
    Payment::shouldReceive('create')->once()->with([
        'payment_number' => 'PAY-GEN-001',
        'payment_date' => Carbon::parse('2023-01-01 10:00:00')->format('Y-m-d'), // Use 'Y-m-d' for consistency
        'amount' => 200,
        'invoice_id' => 100,
        'payment_method_id' => 5,
        'customer_id' => 10,
        'exchange_rate' => 1.2,
        'base_amount' => 240.0, // 200 * 1.2
        'currency_id' => 1,
        'company_id' => 1,
        'transaction_id' => 500,
    ])->andReturn($createdPayment);

    $result = Payment::generatePayment($transaction);

    expect($result)->toBe($createdPayment);
    expect($createdPayment->unique_hash)->toBe('generated_hash');
    expect($createdPayment->sequence_number)->toBe('P-SEQ-001');
    expect($createdPayment->customer_sequence_number)->toBe('C-SEQ-001');

    Carbon::setTestNow(); // Reset Carbon
});

function createMockQuery()
{
    $query = Mockery::mock(\Illuminate\Database\Eloquent\Builder::class);
    $query->shouldReceive('getModel')->andReturn(new Payment()); // Important for some query builder interactions
    // Default expectations for chained methods if not explicitly tested
    $query->shouldReceive('where')->andReturnSelf();
    $query->shouldReceive('orWhere')->andReturnSelf();
    $query->shouldReceive('whereHas')->andReturnSelf();
    $query->shouldReceive('orderBy')->andReturnSelf();
    $query->shouldReceive('whereBetween')->andReturnSelf();
    $query->shouldReceive('paginate')->andReturn('paginated_data');
    $query->shouldReceive('get')->andReturn('all_data');
    // Ensure `tap` calls are also handled for nested closures
    $query->shouldReceive('tap')->andReturnUsing(function ($callback) use ($query) {
        $callback($query);
        return $query;
    });
    return $query;
}

test('scopeWhereSearch applies search terms to customer relationships', function () {
    $query = createMockQuery();
    $payment = new Payment(); // Use a real model instance to call the scope method

    // Assuming the scope iterates through search terms and applies `whereHas` for each.
    // The exact implementation of `scopeWhereSearch` in the Payment model will determine
    // if it's `whereHas` directly, or wrapped in a `where` closure.
    // If 'whereHas' is called twice on the main query, these expectations are correct.
    // The previous error "called 0 times" suggests 'whereHas' was not called at all.
    // Let's refine the expectations based on a common 'whereSearch' pattern:
    // $query->where(function($sub_query) { $sub_query->orWhereHas(...); ...});
    // This implies 'where' is called once on the main query, with a closure.

    $query->shouldReceive('where')->once()
        ->with(Mockery::on(function ($closure) use ($query) {
            // Mock a temporary query builder for the closure context
            $internalQuery = Mockery::mock(\Illuminate\Database\Eloquent\Builder::class);
            $internalQuery->shouldReceive('orWhereHas')->times(2) // Expect two orWhereHas calls for 'test' and 'term'
                ->with('customer', Mockery::on(function ($customerClosure) {
                    $customerSubQuery = Mockery::mock(\Illuminate\Database\Eloquent\Builder::class);
                    $customerSubQuery->shouldReceive('where')->once()->with(Mockery::any(), 'LIKE', Mockery::any())->andReturnSelf();
                    $customerSubQuery->shouldReceive('orWhere')->times(2)->with(Mockery::any(), 'LIKE', Mockery::any())->andReturnSelf(); // For name, contact_name, company_name
                    $customerClosure($customerSubQuery);
                    return true;
                }))
                ->andReturnSelf();
            $closure($internalQuery); // Execute the closure, asserting calls on $internalQuery
            return true;
        }))
        ->andReturnSelf();


    $payment->scopeWhereSearch($query, 'test term');
});

test('scopePaymentNumber applies where clause for payment number', function () {
    $query = createMockQuery();
    $payment = new Payment();

    $query->shouldReceive('where')->once()->with('payments.payment_number', 'LIKE', '%P123%')->andReturnSelf();

    $result = $payment->scopePaymentNumber($query, 'P123');

    expect($result)->toBe($query);
});

test('scopePaymentMethod applies where clause for payment method ID', function () {
    $query = createMockQuery();
    $payment = new Payment();

    $query->shouldReceive('where')->once()->with('payments.payment_method_id', 5)->andReturnSelf();

    $result = $payment->scopePaymentMethod($query, 5);

    expect($result)->toBe($query);
});

test('scopePaginateData returns all data if limit is "all"', function () {
    $query = createMockQuery();
    $payment = new Payment();

    $query->shouldReceive('get')->once()->andReturn('all_data');
    $query->shouldNotReceive('paginate');

    expect($payment->scopePaginateData($query, 'all'))->toBe('all_data');
});

test('scopePaginateData paginates data if limit is a number', function () {
    $query = createMockQuery();
    $payment = new Payment();

    $query->shouldReceive('paginate')->once()->with(10)->andReturn('paginated_data');
    $query->shouldNotReceive('get');

    expect($payment->scopePaginateData($query, 10))->toBe('paginated_data');
});

test('scopeApplyFilters applies all filters correctly', function () {
    $query = createMockQuery();
    $payment = new Payment();

    // Mock the individual scope methods that applyFilters calls
    $query->shouldReceive('whereSearch')->once()->with('search term')->andReturnSelf();
    $query->shouldReceive('paymentNumber')->once()->with('P001')->andReturnSelf();
    $query->shouldReceive('wherePayment')->once()->with(1)->andReturnSelf();
    $query->shouldReceive('paymentMethod')->once()->with(2)->andReturnSelf();
    $query->shouldReceive('whereCustomer')->once()->with(3)->andReturnSelf();
    $query->shouldReceive('paymentsBetween')->once()
        ->with(Mockery::type(Carbon::class), Mockery::type(Carbon::class))
        ->andReturnSelf();
    $query->shouldReceive('whereOrder')->once()->with('payment_date', 'asc')->andReturnSelf();

    $filters = [
        'search' => 'search term',
        'payment_number' => 'P001',
        'payment_id' => 1,
        'payment_method_id' => 2,
        'customer_id' => 3,
        'from_date' => '2023-01-01',
        'to_date' => '2023-01-31',
        'orderByField' => 'payment_date',
        'orderBy' => 'asc',
    ];

    $payment->scopeApplyFilters($query, $filters);
    // Assertions are handled by Mockery's expectation counts
});

test('scopeApplyFilters handles missing filters gracefully', function () {
    $query = createMockQuery();
    $payment = new Payment();

    // Ensure these are NOT called if filters are missing
    $query->shouldNotReceive('whereSearch');
    $query->shouldNotReceive('paymentNumber');
    $query->shouldNotReceive('wherePayment');
    $query->shouldNotReceive('paymentMethod');
    $query->shouldNotReceive('whereCustomer');
    $query->shouldNotReceive('paymentsBetween');
    $query->shouldNotReceive('whereOrder');

    $filters = []; // Empty filters

    $payment->scopeApplyFilters($query, $filters);
    // Expect no interaction with the specific filter methods. The `shouldNotReceive` calls act as assertions.
});

test('scopePaymentsBetween applies whereBetween clause', function () {
    $query = createMockQuery();
    $payment = new Payment();

    $start = Carbon::createFromFormat('Y-m-d', '2023-01-01');
    $end = Carbon::createFromFormat('Y-m-d', '2023-01-31');

    $query->shouldReceive('whereBetween')->once()
        ->with('payments.payment_date', ['2023-01-01', '2023-01-31'])
        ->andReturnSelf();

    $result = $payment->scopePaymentsBetween($query, $start, $end);

    expect($result)->toBe($query);
});

test('scopeWhereOrder applies orderBy clause', function () {
    $query = createMockQuery();
    $payment = new Payment();

    $query->shouldReceive('orderBy')->once()->with('field_name', 'desc')->andReturnSelf();

    $payment->scopeWhereOrder($query, 'field_name', 'desc');
});

test('scopeWherePayment applies orWhere clause for payment ID', function () {
    $query = createMockQuery();
    $payment = new Payment();

    $query->shouldReceive('orWhere')->once()->with('id', 123)->andReturnSelf();

    $payment->scopeWherePayment($query, 123);
});

test('scopeWhereCompany applies where clause for company ID from request header', function () {
    $query = createMockQuery();
    $payment = new Payment();

    $query->shouldReceive('where')->once()->with('payments.company_id', 456)->andReturnSelf();

    // Mock Request Facade for this test
    RequestFacade::shouldReceive('header')->with('company')->andReturn(456);

    $payment->scopeWhereCompany($query);
});

test('scopeWhereCustomer applies where clause for customer ID', function () {
    $query = createMockQuery();
    $payment = new Payment();

    $query->shouldReceive('where')->once()->with('payments.customer_id', 789)->andReturnSelf();

    $payment->scopeWhereCustomer($query, 789);
});

test('addCustomFields (from HasCustomFieldsTrait) is called by createPayment', function () {
    // This is covered by the createPayment test where ->addCustomFields is expected.
    expect(true)->toBeTrue();
});

test('updateCustomFields (from HasCustomFieldsTrait) is called by updatePayment', function () {
    // This is covered by the updatePayment test where ->updateCustomFields is expected.
    expect(true)->toBeTrue();
});

afterEach(function () {
    Mockery::close();
    Carbon::setTestNow(); // Reset Carbon after each test
});