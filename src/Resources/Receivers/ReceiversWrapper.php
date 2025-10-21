<?php

declare(strict_types=1);

namespace BlindPay\SDK\Resources\Receivers;

use BlindPay\SDK\Resources\BankAccounts\BankAccounts;
use BlindPay\SDK\Types\BlindPayApiResponse;

readonly class ReceiversWrapper
{
    public function __construct(
        private Receivers $base,
        public BankAccounts $bankAccounts
    ) {}

    /*
     * List all receivers
     *
     * @return BlindPayApiResponse<array<IndividualWithStandardKYC|IndividualWithEnhancedKYC|BusinessWithStandardKYB>>
     */
    public function list(): BlindPayApiResponse
    {
        return $this->base->list();
    }

    /*
     * Create individual with standard KYC
     *
     * @param CreateIndividualWithStandardKYCInput $data
     * @return BlindPayApiResponse<CreateReceiverResponse>
     */
    public function createIndividualWithStandardKYC(CreateIndividualWithStandardKYCInput $data): BlindPayApiResponse
    {
        return $this->base->createIndividualWithStandardKYC($data);
    }

    /*
     * Create individual with enhanced KYC
     *
     * @param CreateIndividualWithEnhancedKYCInput $data
     * @return BlindPayApiResponse<CreateReceiverResponse>
     */
    public function createIndividualWithEnhancedKYC(CreateIndividualWithEnhancedKYCInput $data): BlindPayApiResponse
    {
        return $this->base->createIndividualWithEnhancedKYC($data);
    }

    /*
     * Create business with standard KYB
     *
     * @param CreateBusinessWithStandardKYBInput $data
     * @return BlindPayApiResponse<CreateReceiverResponse>
     */
    public function createBusinessWithStandardKYB(CreateBusinessWithStandardKYBInput $data): BlindPayApiResponse
    {
        return $this->base->createBusinessWithStandardKYB($data);
    }

    /*
     * Get a receiver by ID
     *
     * @param string $receiverId
     * @return BlindPayApiResponse<IndividualWithStandardKYC|IndividualWithEnhancedKYC|BusinessWithStandardKYB>
     */
    public function get(string $receiverId): BlindPayApiResponse
    {
        return $this->base->get($receiverId);
    }

    /*
     * Update a receiver
     *
     * @param UpdateReceiverInput $input
     * @return BlindPayApiResponse<null>
     */
    public function update(UpdateReceiverInput $input): BlindPayApiResponse
    {
        return $this->base->update($input);
    }

    /*
     * Delete a receiver
     *
     * @param string $receiverId
     * @return BlindPayApiResponse<null>
     */
    public function delete(string $receiverId): BlindPayApiResponse
    {
        return $this->base->delete($receiverId);
    }

    /*
     * Get receiver limits
     *
     * @param string $receiverId
     * @return BlindPayApiResponse<GetReceiverLimitsResponse>
     */
    public function getLimits(string $receiverId): BlindPayApiResponse
    {
        return $this->base->getLimits($receiverId);
    }

    /*
     * Get limit increase requests
     *
     * @param string $receiverId
     * @return BlindPayApiResponse<LimitIncreaseRequest[]>
     */
    public function getLimitIncreaseRequests(string $receiverId): BlindPayApiResponse
    {
        return $this->base->getLimitIncreaseRequests($receiverId);
    }

    /*
     * Request limit increase
     *
     * @param RequestLimitIncreaseInput $input
     * @return BlindPayApiResponse<RequestLimitIncreaseResponse>
     */
    public function requestLimitIncrease(RequestLimitIncreaseInput $input): BlindPayApiResponse
    {
        return $this->base->requestLimitIncrease($input);
    }
}

