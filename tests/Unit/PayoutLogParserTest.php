<?php

use App\Services\PayoutLogParser;

beforeEach(function () {
    $this->parser = new PayoutLogParser;
});

test('parses a single log line', function () {
    $log = '[2026-03-10 09:01:12] develop.INFO: terrapay.quotation.request {"payout_id":12345,"requestAmount":"100","requestCurrency":"USD"}';

    $result = $this->parser->parse($log);

    expect($result['summary']['total_payouts'])->toBe(1);
    expect($result['summary']['total_events'])->toBe(1);
    expect($result['payouts'][0]['payout_id'])->toBe(12345);
});

test('groups events by payout id', function () {
    $log = implode("\n", [
        '[2026-03-10 09:01:12] develop.INFO: terrapay.quotation.request {"payout_id":111,"requestAmount":"100"}',
        '[2026-03-10 09:01:13] develop.INFO: terrapay.quotation.created {"payout_id":111,"sending_amount":"100"}',
        '[2026-03-10 09:02:00] develop.INFO: terrapay.quotation.request {"payout_id":222,"requestAmount":"200"}',
    ]);

    $result = $this->parser->parse($log);

    expect($result['summary']['total_payouts'])->toBe(2);
    expect($result['summary']['total_events'])->toBe(3);
});

test('determines status as done when send completed', function () {
    $log = implode("\n", [
        '[2026-03-10 09:01:12] develop.INFO: terrapay.send.request {"payout_id":1}',
        '[2026-03-10 09:01:15] develop.INFO: terrapay.send.completed {"payout_id":1}',
    ]);

    $result = $this->parser->parse($log);

    expect($result['payouts'][0]['status'])->toBe('done');
});

test('determines status as failed on error', function () {
    $log = implode("\n", [
        '[2026-03-10 09:01:12] develop.INFO: terrapay.send.request {"payout_id":1}',
        '[2026-03-10 09:01:15] develop.ERROR: terrapay.send.request {"payout_id":1,"exception":"ConnectionTimeout"}',
    ]);

    $result = $this->parser->parse($log);

    expect($result['payouts'][0]['status'])->toBe('failed');
    expect($result['summary']['errors'])->toBe(1);
});

test('determines status as pending with check.pending', function () {
    $log = implode("\n", [
        '[2026-03-10 09:01:12] develop.INFO: terrapay.send.request {"payout_id":1}',
        '[2026-03-10 09:01:15] develop.INFO: terrapay.check.pending {"payout_id":1}',
        '[2026-03-10 09:01:20] develop.INFO: terrapay.check.pending {"payout_id":1}',
    ]);

    $result = $this->parser->parse($log);

    expect($result['payouts'][0]['status'])->toBe('pending');
});

test('collapses check.pending events', function () {
    $log = implode("\n", [
        '[2026-03-10 09:01:12] develop.INFO: terrapay.send.request {"payout_id":1}',
        '[2026-03-10 09:01:15] develop.INFO: terrapay.check.pending {"payout_id":1}',
        '[2026-03-10 09:01:20] develop.INFO: terrapay.check.pending {"payout_id":1}',
        '[2026-03-10 09:01:25] develop.INFO: terrapay.check.pending {"payout_id":1}',
    ]);

    $result = $this->parser->parse($log);
    $transaction = $result['payouts'][0]['categories']['transaction'];

    $pendingEntry = collect($transaction)->firstWhere('event_type', 'check.pending');
    expect($pendingEntry['payload']['count'])->toBe(3);
    expect($pendingEntry['collapsed'])->toBeTrue();
});

test('categorizes events correctly', function () {
    $log = implode("\n", [
        '[2026-03-10 09:00:00] develop.INFO: terrapay.verify.request {"payout_id":1}',
        '[2026-03-10 09:00:01] develop.INFO: terrapay.verify.completed {"payout_id":1}',
        '[2026-03-10 09:00:02] develop.INFO: terrapay.quotation.request {"payout_id":1,"requestAmount":"100"}',
        '[2026-03-10 09:00:03] develop.INFO: terrapay.quotation.created {"payout_id":1,"sending_amount":"100"}',
        '[2026-03-10 09:00:04] develop.INFO: terrapay.send.request {"payout_id":1}',
        '[2026-03-10 09:00:05] develop.INFO: terrapay.send.completed {"payout_id":1}',
    ]);

    $result = $this->parser->parse($log);
    $categories = $result['payouts'][0]['categories'];

    expect($categories)->toHaveKeys(['verify', 'quotation', 'transaction']);
    expect($categories['verify'])->toHaveCount(2);
    expect($categories['quotation'])->toHaveCount(2);
    expect($categories['transaction'])->toHaveCount(2);
});

test('detects error anomalies', function () {
    $log = '[2026-03-10 09:01:12] develop.ERROR: terrapay.send.request {"payout_id":1,"exception":"Timeout"}';

    $result = $this->parser->parse($log);
    $anomalies = $result['payouts'][0]['anomalies'];

    expect(collect($anomalies)->firstWhere('type', 'error'))->not->toBeNull();
    expect(collect($anomalies)->firstWhere('type', 'error')['severity'])->toBe('error');
});

test('detects verify failed anomaly', function () {
    $log = '[2026-03-10 09:01:12] develop.INFO: terrapay.verify.failed {"payout_id":1}';

    $result = $this->parser->parse($log);
    $anomalies = $result['payouts'][0]['anomalies'];

    expect(collect($anomalies)->firstWhere('type', 'verify_failed'))->not->toBeNull();
});

test('detects abnormal fx rate', function () {
    $log = '[2026-03-10 09:01:12] develop.INFO: terrapay.quotation.created {"payout_id":1,"fx_rate":"99999","sending_amount":"100","receiving_amount":"9999900"}';

    $result = $this->parser->parse($log);
    $anomalies = $result['payouts'][0]['anomalies'];

    expect(collect($anomalies)->firstWhere('type', 'abnormal_fx_rate'))->not->toBeNull();
});

test('detects abnormal fx rate for known currency pair', function () {
    $log = '[2026-03-10 09:01:12] develop.INFO: terrapay.quotation.created {"payout_id":1,"fx_rate":"87.9","sending_amount":"90","receiving_amount":"7911","sending_currency":"USD","receiving_currency":"EUR"}';

    $result = $this->parser->parse($log);
    $anomalies = $result['payouts'][0]['anomalies'];

    // USD→EUR rate of 87.9 is wildly outside expected range (~0.85–1.10)
    $fxAnomaly = collect($anomalies)->firstWhere('type', 'abnormal_fx_rate');
    expect($fxAnomaly)->not->toBeNull();
    expect($fxAnomaly['severity'])->toBe('error');

    // Should also detect suspicious receiving amount
    expect(collect($anomalies)->firstWhere('type', 'suspicious_amount'))->not->toBeNull();
});

test('no fx rate anomaly for normal currency pair rate', function () {
    $log = '[2026-03-10 09:01:12] develop.INFO: terrapay.quotation.created {"payout_id":1,"fx_rate":"0.92","sending_amount":"100","receiving_amount":"92","sending_currency":"USD","receiving_currency":"EUR"}';

    $result = $this->parser->parse($log);
    $anomalies = $result['payouts'][0]['anomalies'];

    expect(collect($anomalies)->firstWhere('type', 'abnormal_fx_rate'))->toBeNull();
    expect(collect($anomalies)->firstWhere('type', 'suspicious_amount'))->toBeNull();
});

test('detects fx rate mismatch', function () {
    $log = '[2026-03-10 09:01:12] develop.INFO: terrapay.quotation.created {"payout_id":1,"fx_rate":"1.5","sending_amount":"100","receiving_amount":"200"}';

    $result = $this->parser->parse($log);
    $anomalies = $result['payouts'][0]['anomalies'];

    // computed rate = 200/100 = 2.0, reported = 1.5, deviation > 5%
    expect(collect($anomalies)->firstWhere('type', 'fx_rate_mismatch'))->not->toBeNull();
});

test('detects amount mismatch between request and quotation', function () {
    $log = implode("\n", [
        '[2026-03-10 09:01:12] develop.INFO: terrapay.quotation.request {"payout_id":1,"requestAmount":"100"}',
        '[2026-03-10 09:01:13] develop.INFO: terrapay.quotation.created {"payout_id":1,"sending_amount":"95"}',
    ]);

    $result = $this->parser->parse($log);
    $anomalies = $result['payouts'][0]['anomalies'];

    expect(collect($anomalies)->firstWhere('type', 'amount_mismatch'))->not->toBeNull();
});

test('detects missing response after send', function () {
    $log = '[2026-03-10 09:01:12] develop.INFO: terrapay.send.request {"payout_id":1}';

    $result = $this->parser->parse($log);
    $anomalies = $result['payouts'][0]['anomalies'];

    expect(collect($anomalies)->firstWhere('type', 'missing_response'))->not->toBeNull();
});

test('no anomalies for normal complete flow', function () {
    $log = implode("\n", [
        '[2026-03-10 09:00:00] develop.INFO: terrapay.verify.request {"payout_id":1}',
        '[2026-03-10 09:00:01] develop.INFO: terrapay.verify.completed {"payout_id":1}',
        '[2026-03-10 09:00:02] develop.INFO: terrapay.quotation.request {"payout_id":1,"requestAmount":"100"}',
        '[2026-03-10 09:00:03] develop.INFO: terrapay.quotation.created {"payout_id":1,"sending_amount":"100","receiving_amount":"150","fx_rate":"1.5"}',
        '[2026-03-10 09:00:04] develop.INFO: terrapay.send.request {"payout_id":1}',
        '[2026-03-10 09:00:05] develop.INFO: terrapay.send.completed {"payout_id":1}',
    ]);

    $result = $this->parser->parse($log);

    expect($result['payouts'][0]['anomalies'])->toBeEmpty();
    expect($result['payouts'][0]['status'])->toBe('done');
    expect($result['summary']['errors'])->toBe(0);
    expect($result['summary']['anomalies'])->toBe(0);
});

test('ignores empty lines', function () {
    $log = "\n\n[2026-03-10 09:01:12] develop.INFO: terrapay.quotation.request {\"payout_id\":1}\n\n";

    $result = $this->parser->parse($log);

    expect($result['summary']['total_events'])->toBe(1);
});

test('ignores malformed lines', function () {
    $log = implode("\n", [
        'This is not a log line',
        '[2026-03-10 09:01:12] develop.INFO: terrapay.quotation.request {"payout_id":1}',
        'Another bad line',
    ]);

    $result = $this->parser->parse($log);

    expect($result['summary']['total_events'])->toBe(1);
});

test('uses withdrawal_request_id as fallback for grouping', function () {
    $log = '[2026-03-10 09:01:12] develop.INFO: terrapay.send.request {"withdrawal_request_id":999}';

    $result = $this->parser->parse($log);

    expect($result['payouts'][0]['payout_id'])->toBe(999);
});

test('strips terrapay prefix from event types', function () {
    $log = '[2026-03-10 09:01:12] develop.INFO: terrapay.quotation.request {"payout_id":1}';

    $result = $this->parser->parse($log);

    expect($result['payouts'][0]['events'][0]['event_type'])->toBe('quotation.request');
});

test('detects IBAN country mismatch', function () {
    $log = implode("\n", [
        '[2026-03-10 09:00:00] develop.INFO: terrapay.verify.request {"payout_id":1,"beneficiary_country":"IT","destination_currency":"EUR"}',
        '[2026-03-10 09:00:01] develop.INFO: terrapay.quotation.request {"payout_id":1,"creditParty":[{"key":"IBAN","value":"DE89370400440532013000"},{"key":"receivingCountry","value":"IT"}]}',
    ]);

    $result = $this->parser->parse($log);
    $anomalies = $result['payouts'][0]['anomalies'];

    expect(collect($anomalies)->firstWhere('type', 'iban_country_mismatch'))->not->toBeNull();
});

test('no IBAN anomaly when country matches', function () {
    $log = implode("\n", [
        '[2026-03-10 09:00:00] develop.INFO: terrapay.verify.request {"payout_id":1,"beneficiary_country":"IT","destination_currency":"EUR"}',
        '[2026-03-10 09:00:01] develop.INFO: terrapay.quotation.request {"payout_id":1,"creditParty":[{"key":"IBAN","value":"IT60X0542811101000000123456"},{"key":"receivingCountry","value":"IT"}]}',
        '[2026-03-10 09:00:02] develop.INFO: terrapay.quotation.created {"payout_id":1,"sending_amount":"100","receiving_amount":"100","sending_currency":"EUR","receiving_currency":"EUR","fx_rate":"1.0"}',
        '[2026-03-10 09:00:03] develop.INFO: terrapay.send.request {"payout_id":1,"payload":{"amount":"100","currency":"EUR"}}',
        '[2026-03-10 09:00:04] develop.INFO: terrapay.send.completed {"payout_id":1}',
    ]);

    $result = $this->parser->parse($log);
    $anomalies = $result['payouts'][0]['anomalies'];

    expect(collect($anomalies)->firstWhere('type', 'iban_country_mismatch'))->toBeNull();
});

test('detects currency step mismatch between verify and quotation', function () {
    $log = implode("\n", [
        '[2026-03-10 09:00:00] develop.INFO: terrapay.verify.request {"payout_id":1,"beneficiary_country":"IT","destination_currency":"GBP"}',
        '[2026-03-10 09:00:01] develop.INFO: terrapay.quotation.created {"payout_id":1,"sending_amount":"100","receiving_amount":"115","sending_currency":"USD","receiving_currency":"EUR","fx_rate":"1.15"}',
    ]);

    $result = $this->parser->parse($log);
    $anomalies = $result['payouts'][0]['anomalies'];

    expect(collect($anomalies)->firstWhere('type', 'currency_step_mismatch'))->not->toBeNull();
});

test('detects country step mismatch between verify and quotation', function () {
    $log = implode("\n", [
        '[2026-03-10 09:00:00] develop.INFO: terrapay.verify.request {"payout_id":1,"beneficiary_country":"IT","destination_currency":"EUR"}',
        '[2026-03-10 09:00:01] develop.INFO: terrapay.quotation.request {"payout_id":1,"creditParty":[{"key":"receivingCountry","value":"DE"}]}',
    ]);

    $result = $this->parser->parse($log);
    $anomalies = $result['payouts'][0]['anomalies'];

    expect(collect($anomalies)->firstWhere('type', 'country_step_mismatch'))->not->toBeNull();
});

test('uses requisite_id as fallback and extracts nested payload', function () {
    $log = implode("\n", [
        '[2026-03-10 15:46:10] develop.INFO: terrapay.verify.request {"requisite_id":84377,"payload":{"payout_method":"BANK_AC","beneficiary_country":"UA","destination_currency":"USD","beneficiary_name":{"firstName":"Kira Vladimirovna Naitlzy","fullName":"Kira Vladimirovna Naitlzy"},"bank_account":{"iban":"UA213223130000026007233566001"},"msisdn":"06756576769"}}',
    ]);

    $result = $this->parser->parse($log);

    expect($result['summary']['total_payouts'])->toBe(1);
    expect((string) $result['payouts'][0]['payout_id'])->toBe('84377');

    $highlights = $result['payouts'][0]['highlights'];
    expect($highlights['beneficiary_country'])->toBe('UA');
    expect($highlights['destination_currency'])->toBe('USD');
    expect($highlights['payout_method'])->toBe('BANK_AC');
    expect($highlights['beneficiary_name'])->toBe('Kira Vladimirovna Naitlzy');
    expect($highlights['account_number'])->toBe('UA213223130000026007233566001');

    $recipient = $highlights['recipient'];
    expect($recipient['full_name'])->toBe('Kira Vladimirovna Naitlzy');
    expect($recipient['iban'])->toBe('UA213223130000026007233566001');
    expect($recipient['msisdn'])->toBe('06756576769');
});

test('separates senderKyc and recipientKyc from b2b send.request', function () {
    $sendPayload = json_encode([
        'payout_id' => 54705,
        'payload' => [
            'amount' => '9.000',
            'currency' => 'USD',
            'type' => 'b2b',
            'debitParty' => [['key' => 'msisdn', 'value' => '+4491509874561']],
            'creditParty' => [
                ['key' => 'bankaccountno', 'value' => 'AE070331234567890123456'],
                ['key' => 'organisationid', 'value' => 'MASHREQBANK'],
                ['key' => 'accountIBAN', 'value' => 'AE070331234567890123456'],
            ],
            'business' => [
                'senderKyc' => [
                    'businessName' => 'ScaleLab LLC',
                    'businessAddress1' => '9465 Wilshire Boulevard',
                    'businessAddressCity' => 'Some city',
                    'businessAddressCountryCode' => 'CA',
                    'businessCountryCode' => 'CA',
                    'businessEmail' => 'support@scalelab.com',
                ],
                'recipientKyc' => [
                    'businessName' => 'Marriot',
                    'businessAddress1' => '0',
                    'businessAddressCity' => 'Dubai',
                    'businessAddressCountryCode' => 'AE',
                    'businessCountryCode' => 'AE',
                    'businessAddressZip' => '0654',
                ],
            ],
            'internationalTransferInformation' => [
                'receivingCountry' => 'AE',
                'quoteId' => 'QR0T8Y6CEAA7H848',
                'remittancePurpose' => 'Other Business',
                'sourceOfFunds' => 'Others',
            ],
        ],
    ]);

    $log = "[2026-03-10 18:41:11] develop.INFO: terrapay.send.request {$sendPayload}";

    $result = $this->parser->parse($log);
    $highlights = $result['payouts'][0]['highlights'];

    // Sender should have ScaleLab LLC data
    $sender = $highlights['sender'];
    expect($sender['business_name'])->toBe('ScaleLab LLC');
    expect($sender['business_country'])->toBe('CA');
    expect($sender['address'])->toBe('9465 Wilshire Boulevard');
    expect($sender['city'])->toBe('Some city');
    expect($sender['email'])->toBe('support@scalelab.com');

    // Recipient should have Marriot data
    $recipient = $highlights['recipient'];
    expect($recipient['business_name'])->toBe('Marriot');
    expect($recipient['business_country'])->toBe('AE');
    expect($recipient['city'])->toBe('Dubai');
    expect($recipient['zip'])->toBe('0654');

    // Sender must NOT have recipient data
    expect($sender)->not->toHaveKey('business_name', 'Marriot');
    expect($sender['business_name'])->not->toBe('Marriot');
});

test('builds highlights from events', function () {
    $log = implode("\n", [
        '[2026-03-10 09:00:00] develop.INFO: terrapay.verify.request {"payout_id":1,"beneficiary_country":"IT","destination_currency":"EUR","payout_method":"BANK_AC","beneficiary_name":{"firstName":"Alla","fullName":"Alla Test"}}',
        '[2026-03-10 09:00:01] develop.INFO: terrapay.quotation.created {"payout_id":1,"sending_amount":"90","receiving_amount":"7911","sending_currency":"USD","receiving_currency":"EUR","fx_rate":"87.9","quote_id":"QR123"}',
        '[2026-03-10 09:00:02] develop.INFO: terrapay.send.completed {"payout_id":1,"transaction_id":"TX456"}',
    ]);

    $result = $this->parser->parse($log);
    $highlights = $result['payouts'][0]['highlights'];

    expect($highlights['beneficiary_country'])->toBe('IT');
    expect($highlights['beneficiary_name'])->toBe('Alla Test');
    expect($highlights['sending_amount'])->toBe('90');
    expect($highlights['receiving_currency'])->toBe('EUR');
    expect($highlights['fx_rate'])->toBe('87.9');
    expect($highlights['quote_id'])->toBe('QR123');
    expect($highlights['transaction_id'])->toBe('TX456');
});
