<?php

declare(strict_types=1);

namespace BlindPay\SDK\Tests\Resources;

use BlindPay\SDK\Resources\BankAccounts\CreateInternationalSwiftInput;
use BlindPay\SDK\Resources\BankAccounts\CreatePixInput;
use BlindPay\SDK\Resources\CustodialWallets\CreateCustodialWalletInput;
use BlindPay\SDK\Resources\Customers\AdditionalInfoItem;
use BlindPay\SDK\Resources\Customers\AdditionalInfoLabel;
use BlindPay\SDK\Resources\Customers\CreateIndividualWithStandardKYCInput;
use BlindPay\SDK\Resources\Customers\IdentificationDocument;
use BlindPay\SDK\Resources\Customers\ProofOfAddressDocType;
use BlindPay\SDK\Resources\Customers\UpdateCustomerInput;
use BlindPay\SDK\Types\AccountClass;
use BlindPay\SDK\Types\Country;
use BlindPay\SDK\Types\Network;
use BlindPay\SDK\Types\RecipientRelationship;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Request fields added in 4.1.0 (documented in the spec, previously listed in
 * .api-sync/unmodeled.json): sent when set, absent when not.
 */
final class RequestFieldCoverageTest extends TestCase
{
    /** @return list<AdditionalInfoItem> */
    private static function additionalInfo(): array
    {
        return [
            new AdditionalInfoItem(AdditionalInfoLabel::EIN_LETTER, 'https://example.com/ein.pdf'),
            new AdditionalInfoItem(AdditionalInfoLabel::FLOW_OF_FUNDS, 'Payroll deposits.'),
        ];
    }

    private const ADDITIONAL_INFO_WIRE = [
        ['label' => 'EIN_LETTER', 'value' => 'https://example.com/ein.pdf'],
        ['label' => 'FLOW_OF_FUNDS', 'value' => 'Payroll deposits.'],
    ];

    private static function individual(mixed ...$extra): CreateIndividualWithStandardKYCInput
    {
        return new CreateIndividualWithStandardKYCInput(
            '1 Main St',
            'Austin',
            Country::US,
            '1990-01-01',
            'jane@example.com',
            'Jane',
            null,
            Country::US,
            'https://example.com/id.png',
            IdentificationDocument::cases()[0],
            null,
            'Doe',
            '78701',
            'https://example.com/poa.png',
            ProofOfAddressDocType::cases()[0],
            'TX',
            '123456789',
            'to_000000000000',
            ...$extra,
        );
    }

    #[Test]
    public function it_sends_customer_additional_info_and_geolocation(): void
    {
        $body = self::individual(additionalInfo: self::additionalInfo(), latitude: 37.7749, longitude: -122.4194)->toArray();
        $this->assertSame(self::ADDITIONAL_INFO_WIRE, $body['additional_info']);
        $this->assertSame(37.7749, $body['latitude']);
        $this->assertSame(-122.4194, $body['longitude']);

        $update = (new UpdateCustomerInput('cu_000000000000', 'jane@example.com', Country::US, additionalInfo: self::additionalInfo(), latitude: 0.0, longitude: 10.5))->toArray();
        $this->assertSame(self::ADDITIONAL_INFO_WIRE, $update['additional_info']);
        $this->assertSame(0.0, $update['latitude']);
        $this->assertSame(10.5, $update['longitude']);

        foreach ([self::individual()->toArray(), (new UpdateCustomerInput('cu_000000000000', 'jane@example.com', Country::US))->toArray()] as $plain) {
            $this->assertArrayNotHasKey('additional_info', $plain);
            $this->assertArrayNotHasKey('latitude', $plain);
            $this->assertArrayNotHasKey('longitude', $plain);
        }
    }

    #[Test]
    public function it_sends_the_new_bank_account_fields(): void
    {
        $this->assertSame(['type' => 'pix', 'name' => 'Pix', 'pix_key' => '12345678909', 'force_cpf_cnpj' => true], (new CreatePixInput('cu_000000000000', 'Pix', '12345678909', forceCpfCnpj: true))->toArray());
        $this->assertArrayNotHasKey('force_cpf_cnpj', (new CreatePixInput('cu_000000000000', 'Pix', '12345678909'))->toArray());

        $swift = fn (mixed ...$extra) => new CreateInternationalSwiftInput(
            'cu_000000000000',
            'Swift',
            'John Doe',
            '123456789',
            '1 Main St',
            null,
            'Mumbai',
            Country::IN,
            'Bank',
            '400001',
            'MH',
            '1 Main St',
            null,
            'Mumbai',
            Country::IN,
            '400001',
            'MH',
            'BARBINBB',
            null,
            null,
            null,
            null,
            AccountClass::INDIVIDUAL,
            RecipientRelationship::FIRST_PARTY,
            ...$extra,
        );
        $body = $swift(swiftIfscBranchCode: 'BARB0AHIMCI', swiftIntermediaryBankRoutingNumber: '021000089')->toArray();
        $this->assertSame('BARB0AHIMCI', $body['swift_ifsc_branch_code']);
        $this->assertSame('021000089', $body['swift_intermediary_bank_routing_number']);
        $this->assertArrayNotHasKey('swift_ifsc_branch_code', $swift()->toArray());
        $this->assertArrayNotHasKey('swift_intermediary_bank_routing_number', $swift()->toArray());
    }

    #[Test]
    public function it_sends_the_custodial_wallet_external_id(): void
    {
        $this->assertSame(['network' => 'base', 'name' => 'Main', 'external_id' => 'order-42'], (new CreateCustodialWalletInput('cu_000000000000', Network::BASE, 'Main', 'order-42'))->toArray());
        $this->assertSame(['network' => 'base', 'name' => 'Main'], (new CreateCustodialWalletInput('cu_000000000000', Network::BASE, 'Main'))->toArray());
    }
}
