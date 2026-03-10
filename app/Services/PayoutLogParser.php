<?php

namespace App\Services;

class PayoutLogParser
{
    /**
     * IBAN country code → expected country mapping.
     *
     * @var array<string, string>
     */
    private const IBAN_COUNTRY_MAP = [
        'AL' => 'Albania', 'AD' => 'Andorra', 'AT' => 'Austria', 'AZ' => 'Azerbaijan',
        'BH' => 'Bahrain', 'BY' => 'Belarus', 'BE' => 'Belgium', 'BA' => 'Bosnia and Herzegovina',
        'BR' => 'Brazil', 'BG' => 'Bulgaria', 'CR' => 'Costa Rica', 'HR' => 'Croatia',
        'CY' => 'Cyprus', 'CZ' => 'Czech Republic', 'DK' => 'Denmark', 'DO' => 'Dominican Republic',
        'EE' => 'Estonia', 'FO' => 'Faroe Islands', 'FI' => 'Finland', 'FR' => 'France',
        'GE' => 'Georgia', 'DE' => 'Germany', 'GI' => 'Gibraltar', 'GR' => 'Greece',
        'GL' => 'Greenland', 'GT' => 'Guatemala', 'HU' => 'Hungary', 'IS' => 'Iceland',
        'IQ' => 'Iraq', 'IE' => 'Ireland', 'IL' => 'Israel', 'IT' => 'Italy',
        'JO' => 'Jordan', 'KZ' => 'Kazakhstan', 'XK' => 'Kosovo', 'KW' => 'Kuwait',
        'LV' => 'Latvia', 'LB' => 'Lebanon', 'LI' => 'Liechtenstein', 'LT' => 'Lithuania',
        'LU' => 'Luxembourg', 'MT' => 'Malta', 'MR' => 'Mauritania', 'MU' => 'Mauritius',
        'MC' => 'Monaco', 'MD' => 'Moldova', 'ME' => 'Montenegro', 'NL' => 'Netherlands',
        'MK' => 'North Macedonia', 'NO' => 'Norway', 'PK' => 'Pakistan', 'PS' => 'Palestine',
        'PL' => 'Poland', 'PT' => 'Portugal', 'QA' => 'Qatar', 'RO' => 'Romania',
        'LC' => 'Saint Lucia', 'SM' => 'San Marino', 'ST' => 'São Tomé and Príncipe',
        'SA' => 'Saudi Arabia', 'RS' => 'Serbia', 'SC' => 'Seychelles', 'SK' => 'Slovakia',
        'SI' => 'Slovenia', 'ES' => 'Spain', 'SE' => 'Sweden', 'CH' => 'Switzerland',
        'TL' => 'Timor-Leste', 'TN' => 'Tunisia', 'TR' => 'Turkey', 'UA' => 'Ukraine',
        'AE' => 'United Arab Emirates', 'GB' => 'United Kingdom', 'VA' => 'Vatican City',
        'VG' => 'Virgin Islands',
    ];

    /**
     * Known FX rate ranges for common currency pairs.
     * Key format: "{FROM}_{TO}" — values are [min, max] approximate boundaries.
     *
     * @var array<string, array{min: float, max: float}>
     */
    private const FX_RATE_RANGES = [
        'USD_EUR' => ['min' => 0.75, 'max' => 1.15],
        'USD_GBP' => ['min' => 0.65, 'max' => 0.90],
        'USD_CAD' => ['min' => 1.15, 'max' => 1.50],
        'USD_AUD' => ['min' => 1.30, 'max' => 1.70],
        'USD_CHF' => ['min' => 0.80, 'max' => 1.10],
        'USD_JPY' => ['min' => 90.0, 'max' => 165.0],
        'USD_INR' => ['min' => 70.0, 'max' => 95.0],
        'USD_BRL' => ['min' => 3.50, 'max' => 6.50],
        'USD_MXN' => ['min' => 15.0, 'max' => 22.0],
        'USD_PLN' => ['min' => 3.50, 'max' => 5.00],
        'USD_SEK' => ['min' => 8.50, 'max' => 11.50],
        'USD_NOK' => ['min' => 8.50, 'max' => 12.00],
        'USD_DKK' => ['min' => 6.00, 'max' => 7.50],
        'USD_CZK' => ['min' => 20.0, 'max' => 26.0],
        'USD_TRY' => ['min' => 5.0, 'max' => 45.0],
        'USD_ZAR' => ['min' => 13.0, 'max' => 20.0],
        'USD_NGN' => ['min' => 300.0, 'max' => 2000.0],
        'USD_KES' => ['min' => 100.0, 'max' => 180.0],
        'USD_GHS' => ['min' => 8.0, 'max' => 18.0],
        'USD_EGP' => ['min' => 25.0, 'max' => 55.0],
        'USD_PHP' => ['min' => 48.0, 'max' => 60.0],
        'USD_IDR' => ['min' => 13000.0, 'max' => 17000.0],
        'USD_THB' => ['min' => 30.0, 'max' => 38.0],
        'USD_PKR' => ['min' => 200.0, 'max' => 330.0],
        'USD_BDT' => ['min' => 85.0, 'max' => 130.0],
        'USD_AED' => ['min' => 3.60, 'max' => 3.70],
        'USD_SAR' => ['min' => 3.70, 'max' => 3.80],
        'EUR_USD' => ['min' => 0.95, 'max' => 1.25],
        'EUR_GBP' => ['min' => 0.80, 'max' => 0.95],
        'EUR_CHF' => ['min' => 0.90, 'max' => 1.10],
        'EUR_PLN' => ['min' => 4.10, 'max' => 5.00],
        'EUR_CZK' => ['min' => 23.0, 'max' => 27.0],
        'EUR_SEK' => ['min' => 10.0, 'max' => 12.0],
        'EUR_NOK' => ['min' => 10.0, 'max' => 12.5],
        'EUR_DKK' => ['min' => 7.40, 'max' => 7.50],
        'EUR_HUF' => ['min' => 340.0, 'max' => 420.0],
        'EUR_RON' => ['min' => 4.80, 'max' => 5.10],
        'EUR_BGN' => ['min' => 1.90, 'max' => 2.00],
        'EUR_TRY' => ['min' => 8.0, 'max' => 45.0],
        'GBP_USD' => ['min' => 1.15, 'max' => 1.45],
        'GBP_EUR' => ['min' => 1.05, 'max' => 1.25],
    ];

    /**
     * Common currency → country code associations (primary).
     *
     * @var array<string, list<string>>
     */
    private const CURRENCY_COUNTRY_MAP = [
        'EUR' => ['AT', 'BE', 'CY', 'EE', 'FI', 'FR', 'DE', 'GR', 'IE', 'IT', 'LV', 'LT', 'LU', 'MT', 'NL', 'PT', 'SK', 'SI', 'ES', 'MC', 'SM', 'VA', 'AD', 'ME', 'XK'],
        'GBP' => ['GB'],
        'CHF' => ['CH', 'LI'],
        'SEK' => ['SE'],
        'NOK' => ['NO'],
        'DKK' => ['DK'],
        'PLN' => ['PL'],
        'CZK' => ['CZ'],
        'HUF' => ['HU'],
        'RON' => ['RO'],
        'BGN' => ['BG'],
        'HRK' => ['HR'],
        'TRY' => ['TR'],
        'RUB' => ['RU'],
        'UAH' => ['UA'],
        'GEL' => ['GE'],
        'AED' => ['AE'],
        'SAR' => ['SA'],
        'QAR' => ['QA'],
        'KWD' => ['KW'],
        'BHD' => ['BH'],
        'JOD' => ['JO'],
        'ILS' => ['IL'],
        'PKR' => ['PK'],
        'INR' => ['IN'],
        'BDT' => ['BD'],
        'NGN' => ['NG'],
        'KES' => ['KE'],
        'GHS' => ['GH'],
        'ZAR' => ['ZA'],
        'EGP' => ['EG'],
        'MAD' => ['MA'],
        'TND' => ['TN'],
        'PHP' => ['PH'],
        'IDR' => ['ID'],
        'MYR' => ['MY'],
        'THB' => ['TH'],
        'VND' => ['VN'],
        'JPY' => ['JP'],
        'CNY' => ['CN'],
        'KRW' => ['KR'],
        'BRL' => ['BR'],
        'MXN' => ['MX'],
        'COP' => ['CO'],
        'PEN' => ['PE'],
        'CLP' => ['CL'],
        'ARS' => ['AR'],
        'USD' => ['US'],
        'CAD' => ['CA'],
        'AUD' => ['AU'],
        'NZD' => ['NZ'],
    ];

    /**
     * Parse raw log text and return grouped payout data with anomalies.
     *
     * @return array{payouts: list<array<string, mixed>>, summary: array<string, int>}
     */
    public function parse(string $rawLog): array
    {
        $lines = preg_split('/\r?\n/', trim($rawLog));
        $events = [];

        foreach ($lines as $line) {
            $parsed = $this->parseLine($line);
            if ($parsed) {
                $events[] = $parsed;
            }
        }

        $grouped = $this->groupByPayoutId($events);
        $payouts = $this->buildPayouts($grouped);

        $errorCount = 0;
        $anomalyCount = 0;
        foreach ($payouts as $payout) {
            $errorCount += count(array_filter($payout['events'], fn ($e) => $e['level'] === 'ERROR'));
            $anomalyCount += count($payout['anomalies']);
        }

        return [
            'payouts' => $payouts,
            'summary' => [
                'total_payouts' => count($payouts),
                'total_events' => count($events),
                'errors' => $errorCount,
                'anomalies' => $anomalyCount,
            ],
        ];
    }

    /**
     * Parse a single Laravel log line.
     *
     * @return array<string, mixed>|null
     */
    private function parseLine(string $line): ?array
    {
        $line = trim($line);
        if ($line === '') {
            return null;
        }

        // Laravel log format: [2026-03-10 09:01:12] environment.LEVEL: message {"json":"data"}
        $pattern = '/^\[(\d{4}-\d{2}-\d{2}\s\d{2}:\d{2}:\d{2})\]\s+\w+\.(\w+):\s+(.+)$/s';
        if (! preg_match($pattern, $line, $matches)) {
            return null;
        }

        $timestamp = $matches[1];
        $level = strtoupper($matches[2]);
        $rest = $matches[3];

        // Extract event type and JSON payload
        $eventType = $rest;
        $payload = [];

        // Try to extract JSON from the end
        $jsonStart = strpos($rest, '{');
        if ($jsonStart !== false) {
            $eventType = trim(substr($rest, 0, $jsonStart));
            $jsonStr = substr($rest, $jsonStart);
            $decoded = json_decode($jsonStr, true);
            if (is_array($decoded)) {
                $payload = $decoded;
            }
        }

        // Normalize event type: "terrapay.quotation.request" -> "quotation.request"
        $eventType = preg_replace('/^terrapay\./', '', $eventType);

        return [
            'timestamp' => $timestamp,
            'level' => $level,
            'event_type' => $eventType,
            'payload' => $payload,
            'raw' => $line,
        ];
    }

    /**
     * Group events by payout_id extracted from payload.
     *
     * @param  list<array<string, mixed>>  $events
     * @return array<string, list<array<string, mixed>>>
     */
    private function groupByPayoutId(array $events): array
    {
        $grouped = [];

        foreach ($events as $event) {
            $p = $event['payload'];
            $payoutId = $p['payout_id']
                ?? $p['withdrawal_request_id']
                ?? $p['requisite_id']
                ?? 'unknown';

            $key = (string) $payoutId;
            $grouped[$key][] = $event;
        }

        return $grouped;
    }

    /**
     * Build payout result objects with anomaly detection.
     *
     * @param  array<string, list<array<string, mixed>>>  $grouped
     * @return list<array<string, mixed>>
     */
    private function buildPayouts(array $grouped): array
    {
        $payouts = [];

        foreach ($grouped as $payoutId => $events) {
            usort($events, fn ($a, $b) => $a['timestamp'] <=> $b['timestamp']);

            $status = $this->determineStatus($events);
            $anomalies = $this->detectAnomalies($events);
            $categories = $this->categorizeEvents($events);
            $highlights = $this->buildHighlights($events);

            $payouts[] = [
                'payout_id' => $payoutId,
                'status' => $status,
                'events' => array_map(fn ($e) => [
                    'timestamp' => $e['timestamp'],
                    'level' => $e['level'],
                    'event_type' => $e['event_type'],
                    'payload' => $e['payload'],
                ], $events),
                'categories' => $categories,
                'anomalies' => $anomalies,
                'highlights' => $highlights,
                'event_count' => count($events),
            ];
        }

        // Sort by first event timestamp descending
        usort($payouts, function ($a, $b) {
            $aTime = $a['events'][0]['timestamp'] ?? '';
            $bTime = $b['events'][0]['timestamp'] ?? '';

            return $bTime <=> $aTime;
        });

        return $payouts;
    }

    /**
     * Build a summary highlights block for quick reading.
     *
     * @return array<string, mixed>
     */
    private function buildHighlights(array $events): array
    {
        $highlights = [];
        $sender = [];
        $recipient = [];

        foreach ($events as $event) {
            $p = $event['payload'];
            $flat = $this->flattenPayloadValues($p);

            if ($event['event_type'] === 'verify.request') {
                // verify.request may have data at top-level or nested in "payload"
                $verifyData = $p['payload'] ?? $p;

                $highlights['beneficiary_country'] = $verifyData['beneficiary_country'] ?? $p['beneficiary_country'] ?? null;
                $highlights['destination_currency'] = $verifyData['destination_currency'] ?? $p['destination_currency'] ?? null;
                $highlights['payout_method'] = $verifyData['payout_method'] ?? $p['payout_method'] ?? null;

                $benName = $verifyData['beneficiary_name'] ?? $p['beneficiary_name'] ?? null;
                if (is_array($benName)) {
                    $highlights['beneficiary_name'] = $benName['fullName'] ?? $benName['firstName'] ?? null;
                    $recipient['first_name'] = $benName['firstName'] ?? null;
                    $recipient['middle_name'] = $benName['middleName'] ?? null;
                    $recipient['last_name'] = $benName['lastName'] ?? null;
                    $recipient['full_name'] = $benName['fullName'] ?? null;
                }

                // Extract bank_account from verify.request (e.g. bank_account.iban)
                $bankAccount = $verifyData['bank_account'] ?? $p['bank_account'] ?? null;
                if (is_array($bankAccount)) {
                    if (isset($bankAccount['iban']) && ! isset($recipient['iban'])) {
                        $recipient['iban'] = $bankAccount['iban'];
                        $highlights['account_number'] = $bankAccount['iban'];
                    }
                    if (isset($bankAccount['accountNumber']) && ! isset($recipient['bank_account'])) {
                        $recipient['bank_account'] = $bankAccount['accountNumber'];
                    }
                }

                // Extract msisdn from verify.request
                $msisdn = $verifyData['msisdn'] ?? $p['msisdn'] ?? null;
                if ($msisdn && ! isset($recipient['msisdn'])) {
                    $recipient['msisdn'] = $msisdn;
                }
            }

            if ($event['event_type'] === 'quotation.request') {
                $highlights['request_amount'] = $p['requestAmount'] ?? $p['payload']['requestAmount'] ?? null;
                $highlights['request_currency'] = $p['requestCurrency'] ?? $p['payload']['requestCurrency'] ?? null;

                // Extract creditParty (recipient) and debitParty (sender)
                $this->extractCreditPartyInfo($p, $highlights);
                $this->extractCreditPartyFull($p, $recipient);
                $this->extractDebitPartyInfo($p, $highlights);
            }

            if ($event['event_type'] === 'quotation.created') {
                $highlights['sending_amount'] = $p['sending_amount'] ?? null;
                $highlights['sending_currency'] = $p['sending_currency'] ?? null;
                $highlights['receiving_amount'] = $p['receiving_amount'] ?? null;
                $highlights['receiving_currency'] = $p['receiving_currency'] ?? null;
                $highlights['fx_rate'] = $p['fx_rate'] ?? null;
                $highlights['quote_id'] = $p['quote_id'] ?? null;
            }

            if ($event['event_type'] === 'send.request') {
                $sendPayload = $p['payload'] ?? $p;
                $highlights['send_amount'] = $sendPayload['amount'] ?? null;
                $highlights['send_currency'] = $sendPayload['currency'] ?? null;
                $highlights['transaction_type'] = $sendPayload['type'] ?? null;

                // Transfer info from send request
                $intlInfo = $sendPayload['internationalTransferInformation'] ?? null;
                $highlights['remittance_purpose'] = $intlInfo['remittancePurpose'] ?? $flat['remittancePurpose'] ?? null;
                $highlights['source_of_funds'] = $intlInfo['sourceOfFunds'] ?? $flat['sourceOfFunds'] ?? null;

                // Extract structured business KYC (senderKyc / recipientKyc)
                $business = $sendPayload['business'] ?? null;
                if (is_array($business)) {
                    $senderKyc = $business['senderKyc'] ?? null;
                    if (is_array($senderKyc)) {
                        $this->extractBusinessKyc($senderKyc, $sender);
                    }

                    $recipientKyc = $business['recipientKyc'] ?? null;
                    if (is_array($recipientKyc)) {
                        $this->extractBusinessKyc($recipientKyc, $recipient);
                    }
                }

                // Extract creditParty and debitParty from send.request
                $this->extractCreditPartyInfo($sendPayload, $highlights);
                $this->extractCreditPartyFull($sendPayload, $recipient);
                $this->extractDebitPartyInfo($sendPayload, $highlights);
            }

            if ($event['event_type'] === 'send.completed') {
                $highlights['transaction_id'] = $p['transaction_id'] ?? null;
            }

            // Extract sender info from flat payload (non-business fields like senderFullName)
            $this->extractSenderInfo($flat, $sender);
        }

        // Build top-level sender fields for backward compatibility
        $highlights['sender_name'] = $sender['name'] ?? null;
        $highlights['sender_business_name'] = $sender['business_name'] ?? null;
        $highlights['sender_business_country'] = $sender['business_country'] ?? null;
        $highlights['sender_address_country'] = $sender['address_country'] ?? null;
        $highlights['sender_country'] = $sender['country'] ?? null;
        $highlights['sender_msisdn'] = $sender['msisdn'] ?? null;

        // Attach structured objects
        $highlights['sender'] = array_filter($sender, fn ($v) => $v !== null && $v !== '');
        $highlights['recipient'] = array_filter($recipient, fn ($v) => $v !== null && $v !== '');

        return array_filter($highlights, fn ($v) => $v !== null && $v !== '' && $v !== []);
    }

    /**
     * Extract debitParty (sender) info from key-value array.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $highlights
     */
    private function extractDebitPartyInfo(array $payload, array &$highlights): void
    {
        $debitParty = $payload['debitParty'] ?? $payload['payload']['debitParty'] ?? [];
        if (! is_array($debitParty)) {
            return;
        }

        foreach ($debitParty as $item) {
            if (! is_array($item)) {
                continue;
            }
            $key = $item['key'] ?? '';
            $value = $item['value'] ?? '';

            if (in_array($key, ['msisdn', 'MSISDN'], true)) {
                $highlights['sender_msisdn'] = $value;
            }
            if (in_array($key, ['senderCountry', 'sendingCountry'], true)) {
                $highlights['sender_country'] = $value;
            }
        }
    }

    /**
     * Extract full creditParty details (recipient).
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $recipient
     */
    private function extractCreditPartyFull(array $payload, array &$recipient): void
    {
        $creditParty = $payload['creditParty'] ?? $payload['payload']['creditParty'] ?? [];
        if (! is_array($creditParty)) {
            return;
        }

        $keyMap = [
            'firstName' => 'first_name',
            'middleName' => 'middle_name',
            'lastName' => 'last_name',
            'fullName' => 'full_name',
            'bankaccountno' => 'bank_account',
            'bankAccountNo' => 'bank_account',
            'accountIBAN' => 'iban',
            'IBAN' => 'iban',
            'iban' => 'iban',
            'organisationid' => 'bank_name',
            'organisationId' => 'bank_name',
            'addressLine1' => 'address',
            'city' => 'city',
            'postalCode' => 'postal_code',
            'country' => 'country',
            'receivingCountry' => 'receiving_country',
            'instrumentType' => 'instrument_type',
        ];

        foreach ($creditParty as $item) {
            if (! is_array($item)) {
                continue;
            }
            $key = $item['key'] ?? '';
            $value = $item['value'] ?? '';

            if (isset($keyMap[$key]) && $value !== '' && ! isset($recipient[$keyMap[$key]])) {
                $recipient[$keyMap[$key]] = $value;
            }
        }
    }

    /**
     * Extract sender-related fields from flattened payload.
     *
     * @param  array<string, string>  $flat
     * @param  array<string, mixed>  $sender
     */
    private function extractSenderInfo(array $flat, array &$sender): void
    {
        // Sender name
        if (! isset($sender['name'])) {
            $sender['name'] = $flat['senderFullName'] ?? $flat['sender_full_name'] ?? null;

            if (! $sender['name']) {
                $first = $flat['senderFirstName'] ?? $flat['sender_first_name'] ?? '';
                $last = $flat['senderLastName'] ?? $flat['sender_last_name'] ?? '';
                if ($first || $last) {
                    $sender['name'] = trim("{$first} {$last}");
                }
            }
        }

        if (! isset($sender['country'])) {
            $sender['country'] = $flat['senderCountry'] ?? null;
        }

        // Also try msisdn from debitParty (already extracted) or flat payload
        if (! isset($sender['msisdn'])) {
            $sender['msisdn'] = $flat['msisdn'] ?? $flat['MSISDN'] ?? null;
        }
    }

    /**
     * Extract business KYC fields into a target array (sender or recipient).
     *
     * @param  array<string, mixed>  $kyc
     * @param  array<string, mixed>  $target
     */
    private function extractBusinessKyc(array $kyc, array &$target): void
    {
        $map = [
            'businessName' => 'business_name',
            'businessCountryCode' => 'business_country',
            'businessAddressCountryCode' => 'address_country',
            'businessAddress1' => 'address',
            'businessAddressCity' => 'city',
            'businessAddressState' => 'state',
            'businessAddressZip' => 'zip',
            'businessPrimaryContactNo' => 'phone',
            'businessPrimaryContactCountryCode' => 'phone_country',
            'businessEmail' => 'email',
            'businessRegistrationType' => 'registration_type',
            'businessRegistrationNumber' => 'registration_number',
            'businessRegistrationIssueDate' => 'registration_issue_date',
            'businessIDValidThru' => 'registration_valid_thru',
        ];

        foreach ($map as $kycKey => $targetKey) {
            if (! isset($target[$targetKey]) && isset($kyc[$kycKey]) && $kyc[$kycKey] !== '') {
                $target[$targetKey] = $kyc[$kycKey];
            }
        }
    }

    /**
     * Extract IBAN/account info from creditParty array.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $highlights
     */
    private function extractCreditPartyInfo(array $payload, array &$highlights): void
    {
        $creditParty = $payload['creditParty'] ?? $payload['payload']['creditParty'] ?? [];
        if (! is_array($creditParty)) {
            return;
        }

        foreach ($creditParty as $item) {
            if (! is_array($item)) {
                continue;
            }
            $key = $item['key'] ?? '';
            $value = $item['value'] ?? '';

            if ($key === 'receivingCountry') {
                $highlights['receiving_country'] = $value;
            }
            if (in_array($key, ['IBAN', 'iban', 'bankAccountNo'], true)) {
                $highlights['account_number'] = $value;
            }
            if ($key === 'instrumentType') {
                $highlights['instrument_type'] = $value;
            }
        }
    }

    /**
     * Determine payout status from events.
     */
    private function determineStatus(array $events): string
    {
        $types = array_column($events, 'event_type');
        $levels = array_column($events, 'level');

        if (in_array('ERROR', $levels, true)) {
            return 'failed';
        }
        if (in_array('send.completed', $types, true)) {
            return 'done';
        }
        if (in_array('check.pending', $types, true)) {
            return 'pending';
        }
        if (in_array('send.request', $types, true)) {
            return 'in_progress';
        }
        if (in_array('verify.failed', $types, true)) {
            return 'failed';
        }

        return 'in_progress';
    }

    /**
     * Categorize events into groups.
     *
     * @return array<string, list<array<string, mixed>>>
     */
    private function categorizeEvents(array $events): array
    {
        $categories = [
            'verify' => [],
            'quotation' => [],
            'transaction' => [],
            'other' => [],
        ];

        $verifyTypes = ['prepare.started', 'verify.request', 'verify.completed', 'verify.failed'];
        $quotationTypes = ['quotation.request', 'quotation.created'];
        $transactionTypes = ['send.request', 'send.completed', 'check.pending'];

        $pendingCount = 0;

        foreach ($events as $event) {
            $type = $event['event_type'];

            if ($type === 'check.pending') {
                $pendingCount++;

                continue;
            }

            $entry = [
                'timestamp' => $event['timestamp'],
                'level' => $event['level'],
                'event_type' => $type,
                'payload' => $event['payload'],
            ];

            if (in_array($type, $verifyTypes, true)) {
                $categories['verify'][] = $entry;
            } elseif (in_array($type, $quotationTypes, true)) {
                $categories['quotation'][] = $entry;
            } elseif (in_array($type, $transactionTypes, true)) {
                $categories['transaction'][] = $entry;
            } else {
                $categories['other'][] = $entry;
            }
        }

        // Collapse check.pending into a single entry
        if ($pendingCount > 0) {
            $categories['transaction'][] = [
                'timestamp' => null,
                'level' => 'INFO',
                'event_type' => 'check.pending',
                'payload' => ['count' => $pendingCount],
                'collapsed' => true,
            ];
        }

        // Remove empty categories
        return array_filter($categories, fn ($cat) => count($cat) > 0);
    }

    /**
     * Detect anomalies in payout events.
     *
     * @return list<array{type: string, severity: string, description: string}>
     */
    private function detectAnomalies(array $events): array
    {
        $anomalies = [];

        // Check for errors
        foreach ($events as $event) {
            if ($event['level'] === 'ERROR') {
                $exception = $event['payload']['exception'] ?? $event['event_type'];
                $anomalies[] = [
                    'type' => 'error',
                    'severity' => 'error',
                    'description' => "Error in {$event['event_type']}: {$exception}",
                ];
            }
        }

        // Check for verify failures
        $types = array_column($events, 'event_type');
        if (in_array('verify.failed', $types, true)) {
            $anomalies[] = [
                'type' => 'verify_failed',
                'severity' => 'error',
                'description' => 'Account verification failed',
            ];
        }

        // Check for FX rate anomalies
        $this->detectFxAnomalies($events, $anomalies);

        // Check for amount mismatches
        $this->detectAmountMismatches($events, $anomalies);

        // Check for IBAN ↔ country mismatches
        $this->detectIbanCountryMismatch($events, $anomalies);

        // Check for currency ↔ country mismatches
        $this->detectCurrencyCountryMismatch($events, $anomalies);

        // Check for data consistency across steps
        $this->detectCrossStepMismatches($events, $anomalies);

        // Check for payload field inconsistencies (e.g. businessCountryCode vs businessAddressCountryCode)
        $this->detectPayloadFieldMismatches($events, $anomalies);

        // Check for missing expected events
        if (in_array('send.request', $types, true) && ! in_array('send.completed', $types, true) && ! in_array('check.pending', $types, true)) {
            $anomalies[] = [
                'type' => 'missing_response',
                'severity' => 'warning',
                'description' => 'Transaction sent but no completion or pending status received',
            ];
        }

        return $anomalies;
    }

    /**
     * @param  list<array{type: string, severity: string, description: string}>  $anomalies
     */
    private function detectFxAnomalies(array $events, array &$anomalies): void
    {
        $sendingCurrency = null;
        $receivingCurrency = null;

        // Collect currencies from quotation.created
        foreach ($events as $event) {
            if ($event['event_type'] === 'quotation.created') {
                $sendingCurrency = strtoupper($event['payload']['sending_currency'] ?? '');
                $receivingCurrency = strtoupper($event['payload']['receiving_currency'] ?? '');
            }
        }

        foreach ($events as $event) {
            if ($event['event_type'] !== 'quotation.created') {
                continue;
            }

            $fxRate = $event['payload']['fx_rate'] ?? null;
            $sendingAmount = $event['payload']['sending_amount'] ?? null;
            $receivingAmount = $event['payload']['receiving_amount'] ?? null;

            if ($fxRate !== null && is_numeric($fxRate)) {
                $rate = (float) $fxRate;

                // 1. Check against known currency pair ranges
                $pairKey = "{$sendingCurrency}_{$receivingCurrency}";
                $knownRange = self::FX_RATE_RANGES[$pairKey] ?? null;

                if ($knownRange !== null) {
                    if ($rate < $knownRange['min'] || $rate > $knownRange['max']) {
                        $anomalies[] = [
                            'type' => 'abnormal_fx_rate',
                            'severity' => 'error',
                            'description' => sprintf(
                                'Suspicious FX rate for %s→%s: %.4f (expected range: %.2f–%.2f)',
                                $sendingCurrency,
                                $receivingCurrency,
                                $rate,
                                $knownRange['min'],
                                $knownRange['max']
                            ),
                        ];
                    }
                } elseif ($rate > 10000 || $rate < 0.0001) {
                    // 2. Fallback: flag extremely abnormal rates for unknown pairs
                    $anomalies[] = [
                        'type' => 'abnormal_fx_rate',
                        'severity' => 'warning',
                        'description' => "Unusual FX rate: {$rate}",
                    ];
                }
            }

            // 3. Cross-check computed vs reported rate
            if ($sendingAmount && $receivingAmount && $fxRate) {
                $sending = (float) $sendingAmount;
                $receiving = (float) $receivingAmount;
                $reported = (float) $fxRate;

                if ($sending > 0 && $reported > 0) {
                    $computed = $receiving / $sending;
                    $deviation = abs($computed - $reported) / $reported;

                    if ($deviation > 0.05) {
                        $anomalies[] = [
                            'type' => 'fx_rate_mismatch',
                            'severity' => 'warning',
                            'description' => sprintf(
                                'FX rate mismatch: reported %.4f, computed %.4f (%.1f%% deviation)',
                                $reported,
                                $computed,
                                $deviation * 100
                            ),
                        ];
                    }
                }

                // 4. Check if receiving amount is suspiciously high/low vs sending
                if ($knownRange !== null && $sending > 0) {
                    $expectedMin = $sending * $knownRange['min'];
                    $expectedMax = $sending * $knownRange['max'];

                    if ($receiving < $expectedMin * 0.8 || $receiving > $expectedMax * 1.2) {
                        $anomalies[] = [
                            'type' => 'suspicious_amount',
                            'severity' => 'error',
                            'description' => sprintf(
                                'Receiving amount %.2f %s is outside expected range (%.2f–%.2f) for %.2f %s',
                                $receiving,
                                $receivingCurrency,
                                $expectedMin,
                                $expectedMax,
                                $sending,
                                $sendingCurrency
                            ),
                        ];
                    }
                }
            }
        }
    }

    /**
     * @param  list<array{type: string, severity: string, description: string}>  $anomalies
     */
    private function detectAmountMismatches(array $events, array &$anomalies): void
    {
        $requestAmount = null;
        $quotationAmount = null;
        $sendAmount = null;
        $quotationReceivingAmount = null;

        foreach ($events as $event) {
            if ($event['event_type'] === 'quotation.request') {
                $requestAmount = $event['payload']['requestAmount']
                    ?? $event['payload']['payload']['requestAmount'] ?? null;
            }
            if ($event['event_type'] === 'quotation.created') {
                $quotationAmount = $event['payload']['sending_amount'] ?? null;
                $quotationReceivingAmount = $event['payload']['receiving_amount'] ?? null;
            }
            if ($event['event_type'] === 'send.request') {
                $sendPayload = $event['payload']['payload'] ?? $event['payload'];
                $sendAmount = $sendPayload['amount'] ?? null;
            }
        }

        if ($requestAmount !== null && $quotationAmount !== null) {
            if ((float) $requestAmount !== (float) $quotationAmount) {
                $anomalies[] = [
                    'type' => 'amount_mismatch',
                    'severity' => 'warning',
                    'description' => "Request amount ({$requestAmount}) differs from quotation sending amount ({$quotationAmount})",
                ];
            }
        }

        // Check send amount vs quotation receiving amount
        if ($sendAmount !== null && $quotationReceivingAmount !== null) {
            if (abs((float) $sendAmount - (float) $quotationReceivingAmount) > 0.01) {
                $anomalies[] = [
                    'type' => 'send_amount_mismatch',
                    'severity' => 'warning',
                    'description' => "Send amount ({$sendAmount}) differs from quotation receiving amount ({$quotationReceivingAmount})",
                ];
            }
        }
    }

    /**
     * Detect IBAN country code ↔ beneficiary_country mismatch.
     *
     * @param  list<array{type: string, severity: string, description: string}>  $anomalies
     */
    private function detectIbanCountryMismatch(array $events, array &$anomalies): void
    {
        $beneficiaryCountry = null;
        $iban = null;

        foreach ($events as $event) {
            if ($event['event_type'] === 'verify.request') {
                $vp = $event['payload']['payload'] ?? $event['payload'];
                $beneficiaryCountry = $vp['beneficiary_country'] ?? $event['payload']['beneficiary_country'] ?? null;

                // Also check for IBAN in bank_account object
                $bankAcc = $vp['bank_account'] ?? $event['payload']['bank_account'] ?? null;
                if (is_array($bankAcc) && isset($bankAcc['iban'])) {
                    $iban = $bankAcc['iban'];
                }
            }
            if ($event['event_type'] === 'quotation.request') {
                $creditParty = $event['payload']['creditParty'] ?? $event['payload']['payload']['creditParty'] ?? [];
                if (is_array($creditParty)) {
                    foreach ($creditParty as $item) {
                        if (! is_array($item)) {
                            continue;
                        }
                        $key = $item['key'] ?? '';
                        if (in_array($key, ['IBAN', 'iban', 'bankAccountNo'], true)) {
                            $iban = $item['value'] ?? null;
                        }
                    }
                }
            }
        }

        if ($iban && $beneficiaryCountry) {
            $ibanCountry = strtoupper(substr($iban, 0, 2));
            if (
                isset(self::IBAN_COUNTRY_MAP[$ibanCountry])
                && strtoupper($beneficiaryCountry) !== $ibanCountry
            ) {
                $anomalies[] = [
                    'type' => 'iban_country_mismatch',
                    'severity' => 'error',
                    'description' => "IBAN country ({$ibanCountry}) does not match beneficiary country ({$beneficiaryCountry})",
                ];
            }
        }
    }

    /**
     * Detect destination_currency ↔ beneficiary_country mismatch.
     *
     * @param  list<array{type: string, severity: string, description: string}>  $anomalies
     */
    private function detectCurrencyCountryMismatch(array $events, array &$anomalies): void
    {
        $beneficiaryCountry = null;
        $destinationCurrency = null;

        foreach ($events as $event) {
            if ($event['event_type'] === 'verify.request') {
                $vp = $event['payload']['payload'] ?? $event['payload'];
                $beneficiaryCountry = strtoupper($vp['beneficiary_country'] ?? $event['payload']['beneficiary_country'] ?? '');
                $destinationCurrency = strtoupper($vp['destination_currency'] ?? $event['payload']['destination_currency'] ?? '');
            }
        }

        if (! $beneficiaryCountry || ! $destinationCurrency) {
            return;
        }

        $expectedCountries = self::CURRENCY_COUNTRY_MAP[$destinationCurrency] ?? null;
        if ($expectedCountries !== null && ! in_array($beneficiaryCountry, $expectedCountries, true)) {
            // USD/EUR are often used internationally, only flag non-standard pairings with warning
            $severity = in_array($destinationCurrency, ['USD', 'EUR'], true) ? 'info' : 'warning';
            $anomalies[] = [
                'type' => 'currency_country_mismatch',
                'severity' => $severity,
                'description' => "Destination currency ({$destinationCurrency}) is uncommon for beneficiary country ({$beneficiaryCountry})",
            ];
        }
    }

    /**
     * Detect data inconsistencies across different steps.
     *
     * @param  list<array{type: string, severity: string, description: string}>  $anomalies
     */
    private function detectCrossStepMismatches(array $events, array &$anomalies): void
    {
        $verifyCurrency = null;
        $verifyCountry = null;
        $quotationSendCurrency = null;
        $quotationReceiveCurrency = null;
        $sendCurrency = null;
        $quotationReceivingCountry = null;

        foreach ($events as $event) {
            $p = $event['payload'];

            if ($event['event_type'] === 'verify.request') {
                $vp = $p['payload'] ?? $p;
                $verifyCurrency = strtoupper($vp['destination_currency'] ?? $p['destination_currency'] ?? '');
                $verifyCountry = strtoupper($vp['beneficiary_country'] ?? $p['beneficiary_country'] ?? '');
            }

            if ($event['event_type'] === 'quotation.request') {
                $creditParty = $p['creditParty'] ?? $p['payload']['creditParty'] ?? [];
                if (is_array($creditParty)) {
                    foreach ($creditParty as $item) {
                        if (! is_array($item)) {
                            continue;
                        }
                        if (($item['key'] ?? '') === 'receivingCountry') {
                            $quotationReceivingCountry = strtoupper($item['value'] ?? '');
                        }
                    }
                }
            }

            if ($event['event_type'] === 'quotation.created') {
                $quotationSendCurrency = strtoupper($p['sending_currency'] ?? '');
                $quotationReceiveCurrency = strtoupper($p['receiving_currency'] ?? '');
            }

            if ($event['event_type'] === 'send.request') {
                $sendPayload = $p['payload'] ?? $p;
                $sendCurrency = strtoupper($sendPayload['currency'] ?? '');
            }
        }

        // Verify destination currency should match quotation receiving currency
        if ($verifyCurrency && $quotationReceiveCurrency && $verifyCurrency !== $quotationReceiveCurrency) {
            $anomalies[] = [
                'type' => 'currency_step_mismatch',
                'severity' => 'error',
                'description' => "Verify destination currency ({$verifyCurrency}) ≠ quotation receiving currency ({$quotationReceiveCurrency})",
            ];
        }

        // Send currency should match quotation receiving currency
        if ($sendCurrency && $quotationReceiveCurrency && $sendCurrency !== $quotationReceiveCurrency) {
            $anomalies[] = [
                'type' => 'send_currency_mismatch',
                'severity' => 'error',
                'description' => "Send currency ({$sendCurrency}) ≠ quotation receiving currency ({$quotationReceiveCurrency})",
            ];
        }

        // Verify country should match quotation receivingCountry
        if ($verifyCountry && $quotationReceivingCountry && $verifyCountry !== $quotationReceivingCountry) {
            $anomalies[] = [
                'type' => 'country_step_mismatch',
                'severity' => 'error',
                'description' => "Verify beneficiary country ({$verifyCountry}) ≠ quotation receiving country ({$quotationReceivingCountry})",
            ];
        }
    }

    /**
     * Detect mismatches between related fields within payloads.
     * E.g. businessCountryCode vs businessAddressCountryCode.
     *
     * @param  list<array{type: string, severity: string, description: string}>  $anomalies
     */
    private function detectPayloadFieldMismatches(array $events, array &$anomalies): void
    {
        // Pairs of fields that should match when both are present
        $fieldPairs = [
            ['businessCountryCode', 'businessAddressCountryCode', 'Business country code', 'business address country code'],
            ['beneficiary_country', 'businessCountryCode', 'Beneficiary country', 'business country code'],
            ['beneficiary_country', 'businessAddressCountryCode', 'Beneficiary country', 'business address country code'],
        ];

        $seen = [];

        // Collect all field values across all events for this payout
        $allValues = [];
        foreach ($events as $event) {
            $flat = $this->flattenPayloadValues($event['payload']);
            foreach ($flat as $k => $v) {
                if (! isset($allValues[$k]) && $v !== '') {
                    $allValues[$k] = $v;
                }
            }
        }

        foreach ($fieldPairs as [$fieldA, $fieldB, $labelA, $labelB]) {
            $valueA = $allValues[$fieldA] ?? null;
            $valueB = $allValues[$fieldB] ?? null;

            if ($valueA !== null && $valueB !== null && strtoupper($valueA) !== strtoupper($valueB)) {
                $key = "{$fieldA}_{$fieldB}";
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;

                $anomalies[] = [
                    'type' => 'field_mismatch',
                    'severity' => 'warning',
                    'description' => "{$labelA} ({$valueA}) ≠ {$labelB} ({$valueB})",
                ];
            }
        }
    }

    /**
     * Flatten nested payload into key => value map for field comparison.
     *
     * @return array<string, string>
     */
    private function flattenPayloadValues(array $payload, string $prefix = ''): array
    {
        $result = [];

        foreach ($payload as $key => $value) {
            $fullKey = $prefix ? "{$prefix}.{$key}" : $key;

            if (is_array($value)) {
                $result = array_merge($result, $this->flattenPayloadValues($value, $fullKey));
            } else {
                // Store by short key (last segment) for easy matching
                $result[$key] = (string) ($value ?? '');
                $result[$fullKey] = (string) ($value ?? '');
            }
        }

        return $result;
    }
}
