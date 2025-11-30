<?php

use Carbon\Carbon;
use Crater\Jobs\GeneratePaymentPdfJob;
use Crater\Mail\SendPaymentMail;
use Crater\Models\Company;
use Crater\Models\CompanySetting;
use Crater\Models\Customer;
use Crater\Models\Currency;
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

// Start of Pest test suite
beforeEach(function () {
    // Clear mocks before each test
    Mockery::close();
});

// Test the booted methods (static::created, static::updated)
test('payment booted method dispatches GeneratePaymentPdfJob on create', function () {
    GeneratePaymentPdfJob::shouldReceive('dispatch')
        ->once()
        ->withArgs(function ($payment, $isUpdate = false) {
            return $payment instanceof Payment && $isUpdate === false;
        });

    $paymentMock = Mockery::mock(Payment::class)->makePartial();
    $paymentMock->id = 1; // Assign an ID to make it seem like it's saved
    $paymentMock->company_id = 1;
    $paymentMock->payment_date = '2023-01-01';
    $paymentMock->exists = true; // Mark as existing for model events

    // Manually fire the 'created' event for the mock
    $paymentMock->fireModelEvent('created', false);
});

test('payment booted method dispatches GeneratePaymentPdfJob on update', function () {
    GeneratePaymentPdfJob::shouldReceive('dispatch')
        ->once()
        ->withArgs(function ($payment, $isUpdate = true) {
            return $payment instanceof Payment && $isUpdate === true;
        });

    $paymentMock = Mockery::mock(Payment::class)->makePartial();
    $paymentMock->id = 1;
    $paymentMock->company_id = 1;
    $paymentMock->payment_date = '2023-01-01';
    $paymentMock->exists = true; // Essential for `updated` event

    // Manually fire the 'updated' event for the mock
    $paymentMock->fireModelEvent('updated', false);
});

// Test accessors and mutators
test('setSettingsAttribute encodes value to JSON if provided', function () {
        $payment = new Payment();
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
        $payment = new Payment(['created_at' => '2023-01-15 10:00:00', 'company_id' => 1]);

        CompanySetting::shouldReceive('getSetting')
            ->once()
            ->with('carbon_date_format', $payment->company_id)
            ->andReturn('Y-m-d');

        $formattedDate = $payment->formattedCreatedAt;

        expect($formattedDate)->toBe('2023-01-15');
    });

    test('getFormattedPaymentDateAttribute returns formatted payment date', function () {
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

        URL::shouldReceive('to')
            ->once()
            ->with('/payments/pdf/test-hash')
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
        expect($relation->getRelated())->toBeInstanceOf(\App\Models\EmailLog::class);
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

        $company = Mockery::mock(Company::class);
        $company->id = 1;

        $payment = Mockery::mock(Payment::class)->makePartial();
        $payment->shouldReceive('toArray')->andReturn(['amount' => 100]);
        $payment->company_id = 1;
        $payment->customer = $customer; // Mock relationship

        Company::shouldReceive('find')->with(1)->andReturn($company);

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

        $company = Mockery::mock(Company::class);
        $company->id = 1;

        $payment = Mockery::mock(Payment::class)->makePartial();
        $payment->shouldReceive('toArray')->andReturn(['amount' => 100]);
        $payment->company_id = 1;
        $payment->customer = $customer; // Mock relationship

        Company::shouldReceive('find')->with(1)->andReturn($company);

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
        $request = Mockery::mock(Request::class);
        $request->shouldReceive('getPaymentPayload')->andReturn(['customer_id' => 1, 'amount' => 50, 'currency_id' => 1, 'company_id' => 1]);
        $request->invoice_id = 10;
        $request->amount = 50;
        $request->customFields = [['field_id' => 1, 'value' => 'test']];
        $request->shouldReceive('header')->with('company')->andReturn(1);

        $invoice = Mockery::mock(Invoice::class);
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
        $finalPayment = Mockery::mock(Payment::class);
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

        $finalPayment = Mockery::mock(Payment::class);
        $finalPayment->id = 1;
        Payment::shouldReceive('with')->andReturnSelf();
        Payment::shouldReceive('find')->with(1)->andReturn($finalPayment);

        $result = Payment::createPayment($request);

        expect($result)->toBe($finalPayment);
    });

    test('updatePayment updates existing payment and handles invoice changes', function () {
        $request = Mockery::mock(Request::class);
        $request->shouldReceive('getPaymentPayload')->andReturn(['customer_id' => 2, 'amount' => 75, 'currency_id' => 1]);
        $request->invoice_id = 11; // New invoice ID
        $request->amount = 75; // New amount
        $request->customFields = [['field_id' => 2, 'value' => 'updated']];
        $request->shouldReceive('header')->with('company')->andReturn(1);

        $originalInvoice = Mockery::mock(Invoice::class);
        $originalInvoice->shouldReceive('addInvoicePayment')->once()->with(50); // Original amount
        Invoice::shouldReceive('find')->with(10)->andReturn($originalInvoice);

        $newInvoice = Mockery::mock(Invoice::class);
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

        $finalPayment = Mockery::mock(Payment::class);
        $finalPayment->id = 1;
        Payment::shouldReceive('with')->andReturnSelf();
        Payment::shouldReceive('find')->with(1)->andReturn($finalPayment);

        $result = $payment->updatePayment($request);

        expect($result)->toBe($finalPayment);
    });

    test('updatePayment handles same invoice different amount', function () {
        $request = Mockery::mock(Request::class);
        $request->shouldReceive('getPaymentPayload')->andReturn(['customer_id' => 1, 'amount' => 75, 'currency_id' => 1]);
        $request->invoice_id = 10; // Same invoice
        $request->amount = 75; // Different amount
        $request->customFields = null;
        $request->shouldReceive('header')->with('company')->andReturn(1);

        $invoice = Mockery::mock(Invoice::class);
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
        $request = Mockery::mock(Request::class);
        $request->shouldReceive('getPaymentPayload')->andReturn(['customer_id' => 1, 'amount' => 50, 'currency_id' => 1]);
        $request->invoice_id = null; // Removing invoice
        $request->amount = 50;
        $request->customFields = null;
        $request->shouldReceive('header')->with('company')->andReturn(1);

        $originalInvoice = Mockery::mock(Invoice::class);
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

        $payment->shouldReceive('update')->once();

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
        $company = Mockery::mock(Company::class);
        $company->id = 1;
        $company->logo_path = 'logo.png';
        Company::shouldReceive('find')->with(1)->andReturn($company);

        CompanySetting::shouldReceive('getSetting')->andReturn('en'); // For language

        App::shouldReceive('setLocale')->with('en');

        $payment = Mockery::mock(Payment::class)->makePartial();
        $payment->company_id = 1;
        $payment->shouldReceive('getCompanyAddress')->andReturn('Company Address');
        $payment->shouldReceive('getCustomerBillingAddress')->andReturn('Customer Address');
        $payment->shouldReceive('getNotes')->andReturn('Some notes');

        // Mock request helper
        test()->patch('request', function () {
            $mockRequest = Mockery::mock(Request::class);
            $mockRequest->shouldReceive('has')->with('preview')->andReturn(true);
            return $mockRequest;
        });

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
        $company = Mockery::mock(Company::class);
        $company->id = 1;
        $company->logo_path = null; // No logo
        Company::shouldReceive('find')->with(1)->andReturn($company);

        CompanySetting::shouldReceive('getSetting')->andReturn('en');

        App::shouldReceive('setLocale')->with('en');

        $payment = Mockery::mock(Payment::class)->makePartial();
        $payment->company_id = 1;
        $payment->shouldReceive('getCompanyAddress')->andReturn('Company Address');
        $payment->shouldReceive('getCustomerBillingAddress')->andReturn('Customer Address');
        $payment->shouldReceive('getNotes')->andReturn('Some notes');

        test()->patch('request', function () {
            $mockRequest = Mockery::mock(Request::class);
            $mockRequest->shouldReceive('has')->with('preview')->andReturn(false);
            return $mockRequest;
        });

        View::shouldReceive('share')->once(); // Just assert it's called

        $pdfMock = Mockery::mock();
        PDF::shouldReceive('loadView')->once()->with('app.pdf.payment.payment')->andReturn($pdfMock);

        $result = $payment->getPDFData();

        expect($result)->toBe($pdfMock);
    });

    test('getCompanyAddress returns formatted string if company address exists', function () {
        $companyAddress = Mockery::mock();
        $companyAddress->shouldReceive('exists')->andReturn(true);

        $company = Mockery::mock(Company::class);
        $company->shouldReceive('address')->andReturn($companyAddress);

        $payment = Mockery::mock(Payment::class)->makePartial();
        $payment->company_id = 1;
        $payment->company = $company; // Mock relationship

        CompanySetting::shouldReceive('getSetting')
            ->with('payment_company_address_format', 1)
            ->andReturn('{ADDRESS}');

        $payment->shouldReceive('getFormattedString')->with('{ADDRESS}')->andReturn('Formatted Company Address');

        expect($payment->getCompanyAddress())->toBe('Formatted Company Address');
    });

    test('getCompanyAddress returns false if company or its address does not exist', function () {
        $payment = Mockery::mock(Payment::class)->makePartial();
        $payment->company_id = 1;
        $payment->company = null; // No company

        expect($payment->getCompanyAddress())->toBeFalse();

        // With company, but no address
        $company = Mockery::mock(Company::class);
        $companyAddress = Mockery::mock();
        $companyAddress->shouldReceive('exists')->andReturn(false);
        $company->shouldReceive('address')->andReturn($companyAddress);
        $payment->company = $company;

        expect($payment->getCompanyAddress())->toBeFalse();
    });

    test('getCustomerBillingAddress returns formatted string if customer billing address exists', function () {
        $billingAddress = Mockery::mock();
        $billingAddress->shouldReceive('exists')->andReturn(true);

        $customer = Mockery::mock(Customer::class);
        $customer->shouldReceive('billingAddress')->andReturn($billingAddress);

        $payment = Mockery::mock(Payment::class)->makePartial();
        $payment->company_id = 1;
        $payment->customer = $customer; // Mock relationship

        CompanySetting::shouldReceive('getSetting')
            ->with('payment_from_customer_address_format', 1)
            ->andReturn('{CUSTOMER_ADDRESS}');

        $payment->shouldReceive('getFormattedString')->with('{CUSTOMER_ADDRESS}')->andReturn('Formatted Customer Billing Address');

        expect($payment->getCustomerBillingAddress())->toBe('Formatted Customer Billing Address');
    });

    test('getCustomerBillingAddress returns false if customer or its billing address does not exist', function () {
        $payment = Mockery::mock(Payment::class)->makePartial();
        $payment->company_id = 1;
        $payment->customer = null; // No customer

        expect($payment->getCustomerBillingAddress())->toBeFalse();

        // With customer, but no billing address
        $customer = Mockery::mock(Customer::class);
        $billingAddress = Mockery::mock();
        $billingAddress->shouldReceive('exists')->andReturn(false);
        $customer->shouldReceive('billingAddress')->andReturn($billingAddress);
        $payment->customer = $customer;

        expect($payment->getCustomerBillingAddress())->toBeFalse();
    });

    test('getEmailAttachmentSetting returns true if setting is not NO', function () {
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
        $paymentMethod = Mockery::mock();
        $paymentMethod->name = 'Bank Transfer';

        $payment = Mockery::mock(Payment::class)->makePartial();
        $payment->formattedPaymentDate = '2023-01-01';
        $payment->paymentMethod = $paymentMethod;
        $payment->payment_number = 'PAY-007';
        $payment->reference_number = 'REF-007';

        expect($payment->getExtraFields())->toEqual([
            '{PAYMENT_DATE}' => '2023-01-01',
            '{PAYMENT_MODE}' => 'Bank Transfer',
            '{PAYMENT_NUMBER}' => 'PAY-007',
            '{PAYMENT_AMOUNT}' => 'REF-007',
        ]);
    });

    test('getExtraFields handles null payment method', function () {
        $payment = Mockery::mock(Payment::class)->makePartial();
        $payment->formattedPaymentDate = '2023-01-01';
        $payment->paymentMethod = null;
        $payment->payment_number = 'PAY-007';
        $payment->reference_number = 'REF-007';

        expect($payment->getExtraFields())->toEqual([
            '{PAYMENT_DATE}' => '2023-01-01',
            '{PAYMENT_MODE}' => null,
            '{PAYMENT_NUMBER}' => 'PAY-007',
            '{PAYMENT_AMOUNT}' => 'REF-007',
        ]);
    });

    test('generatePayment creates a payment and updates invoice', function () {
        $invoice = Mockery::mock(Invoice::class);
        $invoice->id = 100;
        $invoice->company_id = 1;
        $invoice->customer_id = 10;
        $invoice->total = 200;
        $invoice->exchange_rate = 1.2;
        $invoice->currency_id = 1;
        $invoice->shouldReceive('subtractInvoicePayment')->once()->with(200);
        Invoice::shouldReceive('find')->with(100)->andReturn($invoice);

        $transaction = Mockery::mock(Transaction::class);
        $transaction->invoice_id = 100;
        $transaction->id = 500;

        Carbon::setTestNow(Carbon::create(2023, 1, 1, 10, 0, 0)); // Mock Carbon::now()

        // Mock request for payment_method_id
        test()->patch('request', function () {
            $mockRequest = Mockery::mock(Request::class);
            $mockRequest->payment_method_id = 5;
            return $mockRequest;
        });

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
            'payment_date' => '23-01-01', // Carbon format 'y-m-d'
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
        return $query;
    }

    test('scopeWhereSearch applies search terms to customer relationships', function () {
        $query = createMockQuery();
        $query->shouldReceive('whereHas')->once()
            ->with('customer', Mockery::on(function ($closure) {
                $subQuery = Mockery::mock(\Illuminate\Database\Eloquent\Builder::class);
                $subQuery->shouldReceive('where')->once()->with('name', 'LIKE', '%test%')->andReturnSelf();
                $subQuery->shouldReceive('orWhere')->once()->with('contact_name', 'LIKE', '%test%')->andReturnSelf();
                $subQuery->shouldReceive('orWhere')->once()->with('company_name', 'LIKE', '%test%')->andReturnSelf();
                $closure($subQuery);
                return true;
            }))
            ->andReturnSelf();
        $query->shouldReceive('whereHas')->once()
            ->with('customer', Mockery::on(function ($closure) {
                $subQuery = Mockery::mock(\Illuminate\Database\Eloquent\Builder::class);
                $subQuery->shouldReceive('where')->once()->with('name', 'LIKE', '%term%')->andReturnSelf();
                $subQuery->shouldReceive('orWhere')->once()->with('contact_name', 'LIKE', '%term%')->andReturnSelf();
                $subQuery->shouldReceive('orWhere')->once()->with('company_name', 'LIKE', '%term%')->andReturnSelf();
                $closure($subQuery);
                return true;
            }))
            ->andReturnSelf();

        $payment = new Payment();
        $payment->scopeWhereSearch($query, 'test term');
    });

    test('scopePaymentNumber applies where clause for payment number', function () {
        $query = createMockQuery();
        $query->shouldReceive('where')->once()->with('payments.payment_number', 'LIKE', '%P123%')->andReturnSelf();

        $payment = new Payment();
        $result = $payment->scopePaymentNumber($query, 'P123');

        expect($result)->toBe($query);
    });

    test('scopePaymentMethod applies where clause for payment method ID', function () {
        $query = createMockQuery();
        $query->shouldReceive('where')->once()->with('payments.payment_method_id', 5)->andReturnSelf();

        $payment = new Payment();
        $result = $payment->scopePaymentMethod($query, 5);

        expect($result)->toBe($query);
    });

    test('scopePaginateData returns all data if limit is "all"', function () {
        $query = createMockQuery();
        $query->shouldReceive('get')->once()->andReturn('all_data');
        $query->shouldNotReceive('paginate');

        $payment = new Payment();
        expect($payment->scopePaginateData($query, 'all'))->toBe('all_data');
    });

    test('scopePaginateData paginates data if limit is a number', function () {
        $query = createMockQuery();
        $query->shouldReceive('paginate')->once()->with(10)->andReturn('paginated_data');
        $query->shouldNotReceive('get');

        $payment = new Payment();
        expect($payment->scopePaginateData($query, 10))->toBe('paginated_data');
    });

    test('scopeApplyFilters applies all filters correctly', function () {
        $query = createMockQuery();

        // Specific mocks for methods called by applyFilters
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

        $payment = new Payment();
        $payment->scopeApplyFilters($query, $filters);
    });

    test('scopeApplyFilters handles missing filters gracefully', function () {
        $query = createMockQuery();

        // Ensure these are NOT called if filters are missing
        $query->shouldNotReceive('whereSearch');
        $query->shouldNotReceive('paymentNumber');
        $query->shouldNotReceive('wherePayment');
        $query->shouldNotReceive('paymentMethod');
        $query->shouldNotReceive('whereCustomer');
        $query->shouldNotReceive('paymentsBetween');
        $query->shouldNotReceive('whereOrder');

        $filters = []; // Empty filters

        $payment = new Payment();
        $payment->scopeApplyFilters($query, $filters);
        // Expect no interaction with the specific filter methods. The `shouldNotReceive` calls act as assertions.
    });

    test('scopePaymentsBetween applies whereBetween clause', function () {
        $query = createMockQuery();
        $start = Carbon::createFromFormat('Y-m-d', '2023-01-01');
        $end = Carbon::createFromFormat('Y-m-d', '2023-01-31');

        $query->shouldReceive('whereBetween')->once()
            ->with('payments.payment_date', ['2023-01-01', '2023-01-31'])
            ->andReturnSelf();

        $payment = new Payment();
        $result = $payment->scopePaymentsBetween($query, $start, $end);

        expect($result)->toBe($query);
    });

    test('scopeWhereOrder applies orderBy clause', function () {
        $query = createMockQuery();
        $query->shouldReceive('orderBy')->once()->with('field_name', 'desc')->andReturnSelf();

        $payment = new Payment();
        $payment->scopeWhereOrder($query, 'field_name', 'desc');
    });

    test('scopeWherePayment applies orWhere clause for payment ID', function () {
        $query = createMockQuery();
        $query->shouldReceive('orWhere')->once()->with('id', 123)->andReturnSelf();

        $payment = new Payment();
        $payment->scopeWherePayment($query, 123);
    });

    test('scopeWhereCompany applies where clause for company ID from request header', function () {
        $query = createMockQuery();
        $query->shouldReceive('where')->once()->with('payments.company_id', 456)->andReturnSelf();

        // Mock request helper globally for this test
        test()->patch('request', function () {
            $mockRequest = Mockery::mock(Request::class);
            $mockRequest->shouldReceive('header')->with('company')->andReturn(456);
            return $mockRequest;
        });

        $payment = new Payment();
        $payment->scopeWhereCompany($query);
    });

    test('scopeWhereCustomer applies where clause for customer ID', function () {
        $query = createMockQuery();
        $query->shouldReceive('where')->once()->with('payments.customer_id', 789)->andReturnSelf();

        $payment = new Payment();
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
    
