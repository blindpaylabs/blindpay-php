<?php

declare(strict_types=1);

namespace BlindPay\SDK\Resources\TermsOfService;

use BlindPay\SDK\Internal\ApiClientInterface;
use BlindPay\SDK\Internal\Path;
use BlindPay\SDK\Types\BlindPayApiResponse;
use BlindPay\SDK\Types\ErrorResponse;
use BlindPay\SDK\Types\RequestOptions;

readonly class InitiateInput
{
    public function __construct(
        public string $idempotencyKey,
        public ?string $customerId = null,
        public ?string $redirectUrl = null
    ) {}

    public function toArray(): array
    {
        $data = [
            'idempotency_key' => $this->idempotencyKey,
        ];

        if ($this->customerId !== null) {
            $data['customer_id'] = $this->customerId;
        }

        if ($this->redirectUrl !== null) {
            $data['redirect_url'] = $this->redirectUrl;
        }

        return $data;
    }
}

readonly class InitiateResponse
{
    public function __construct(
        public string $url
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            url: $data['url']
        );
    }
}

class TermsOfService
{
    public function __construct(
        private readonly string $instanceId,
        private readonly ApiClientInterface $client
    ) {}

    /**
     * Initiate Terms of Service acceptance flow
     *
     * @return BlindPayApiResponse<InitiateResponse>
     */
    public function initiate(
        InitiateInput $input,
        ?RequestOptions $options = null
    ): BlindPayApiResponse {
        if ($input->idempotencyKey === '') {
            return BlindPayApiResponse::error(
                ErrorResponse::validation('Idempotency key cannot be empty')
            );
        }

        $response = $this->client->post(
            Path::of('e/instances/%s/tos', $this->instanceId),
            $input->toArray(),
            $options
        );

        return $response->map(fn (array $data) => InitiateResponse::fromArray($data));
    }
}
