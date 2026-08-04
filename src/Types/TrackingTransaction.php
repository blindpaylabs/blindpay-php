<?php

declare(strict_types=1);

namespace BlindPay\SDK\Types;

use DateTimeImmutable;

readonly class TrackingTransaction extends BaseTracking
{
    public function __construct(
        string $step,
        public string $status,
        public ?string $transactionHash,
        public ?string $externalId = null,
        public ?string $providerName = null,
        public ?string $providerTransactionId = null,
        public ?string $providerErrorReason = null,
        public ?string $senderName = null,
        public ?string $senderBankName = null,
        public ?string $senderTaxId = null,
        public ?string $senderBankCode = null,
        public ?string $senderAccountNumber = null,
        public ?string $endToEndId = null,
        public ?string $traceNumber = null,
        public ?string $transactionReference = null,
        public ?string $description = null,
        public ?array $pseInstruction = null,
        public ?array $transfersInstruction = null,
        public ?array $tedInstruction = null,
        public ?string $ledgerInTransactionId = null,
        public ?string $ledgerOutTransactionId = null,
        ?DateTimeImmutable $completedAt = null
    ) {
        parent::__construct($step, $completedAt);
    }

    public static function fromArray(array $data): self
    {
        return new self(
            step: $data['step'],
            status: $data['status'],
            transactionHash: $data['transaction_hash'] ?? null,
            externalId: $data['external_id'] ?? null,
            providerName: $data['provider_name'] ?? null,
            providerTransactionId: $data['provider_transaction_id'] ?? null,
            providerErrorReason: $data['provider_error_reason'] ?? null,
            senderName: $data['sender_name'] ?? null,
            senderBankName: $data['sender_bank_name'] ?? null,
            senderTaxId: $data['sender_tax_id'] ?? null,
            senderBankCode: $data['sender_bank_code'] ?? null,
            senderAccountNumber: $data['sender_account_number'] ?? null,
            endToEndId: $data['end_to_end_id'] ?? null,
            traceNumber: $data['trace_number'] ?? null,
            transactionReference: $data['transaction_reference'] ?? null,
            description: $data['description'] ?? null,
            pseInstruction: $data['pse_instruction'] ?? null,
            transfersInstruction: $data['transfers_instruction'] ?? null,
            tedInstruction: $data['ted_instruction'] ?? null,
            ledgerInTransactionId: $data['ledger_in_transaction_id'] ?? null,
            ledgerOutTransactionId: $data['ledger_out_transaction_id'] ?? null,
            completedAt: isset($data['completed_at'])
                ? new DateTimeImmutable($data['completed_at'])
                : null
        );
    }

    public function toArray(): array
    {
        return [
            'step' => $this->step,
            'status' => $this->status,
            'transaction_hash' => $this->transactionHash,
            'external_id' => $this->externalId,
            'provider_name' => $this->providerName,
            'provider_transaction_id' => $this->providerTransactionId,
            'provider_error_reason' => $this->providerErrorReason,
            'sender_name' => $this->senderName,
            'sender_bank_name' => $this->senderBankName,
            'sender_tax_id' => $this->senderTaxId,
            'sender_bank_code' => $this->senderBankCode,
            'sender_account_number' => $this->senderAccountNumber,
            'end_to_end_id' => $this->endToEndId,
            'trace_number' => $this->traceNumber,
            'transaction_reference' => $this->transactionReference,
            'description' => $this->description,
            'pse_instruction' => $this->pseInstruction,
            'transfers_instruction' => $this->transfersInstruction,
            'ted_instruction' => $this->tedInstruction,
            'ledger_in_transaction_id' => $this->ledgerInTransactionId,
            'ledger_out_transaction_id' => $this->ledgerOutTransactionId,
            'completed_at' => $this->completedAt?->format('c'),
        ];
    }
}
