<?php

declare(strict_types=1);

namespace BlindPay\SDK\Resources\Customers;

use BlindPay\SDK\Resources\BankAccounts\BankAccounts;
use BlindPay\SDK\Types\BlindPayApiResponse;

readonly class CustomersWrapper
{
    public function __construct(
        private Customers $base,
        public BankAccounts $bankAccounts
    ) {}

    /*
     * List all customers
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
     * @return BlindPayApiResponse<CreateCustomerResponse>
     */
    public function createIndividualWithStandardKYC(CreateIndividualWithStandardKYCInput $data): BlindPayApiResponse
    {
        return $this->base->createIndividualWithStandardKYC($data);
    }

    /*
     * Create individual with enhanced KYC
     *
     * @param CreateIndividualWithEnhancedKYCInput $data
     * @return BlindPayApiResponse<CreateCustomerResponse>
     */
    public function createIndividualWithEnhancedKYC(CreateIndividualWithEnhancedKYCInput $data): BlindPayApiResponse
    {
        return $this->base->createIndividualWithEnhancedKYC($data);
    }

    /*
     * Create business with standard KYB
     *
     * @param CreateBusinessWithStandardKYBInput $data
     * @return BlindPayApiResponse<CreateCustomerResponse>
     */
    public function createBusinessWithStandardKYB(CreateBusinessWithStandardKYBInput $data): BlindPayApiResponse
    {
        return $this->base->createBusinessWithStandardKYB($data);
    }

    /*
     * Get a receiver by ID
     *
     * @param string $customerId
     * @return BlindPayApiResponse<IndividualWithStandardKYC|IndividualWithEnhancedKYC|BusinessWithStandardKYB>
     */
    public function get(string $customerId): BlindPayApiResponse
    {
        return $this->base->get($customerId);
    }

    /*
     * Update a receiver
     *
     * @param UpdateCustomerInput $input
     * @return BlindPayApiResponse<null>
     */
    public function update(UpdateCustomerInput $input): BlindPayApiResponse
    {
        return $this->base->update($input);
    }

    /*
     * Delete a receiver
     *
     * @param string $customerId
     * @return BlindPayApiResponse<null>
     */
    public function delete(string $customerId): BlindPayApiResponse
    {
        return $this->base->delete($customerId);
    }

    /*
     * Get receiver limits
     *
     * @param string $customerId
     * @return BlindPayApiResponse<GetCustomerLimitsResponse>
     */
    public function getLimits(string $customerId): BlindPayApiResponse
    {
        return $this->base->getLimits($customerId);
    }

    /*
     * Get limit increase requests
     *
     * @param string $customerId
     * @return BlindPayApiResponse<LimitIncreaseRequest[]>
     */
    public function getLimitIncreaseRequests(string $customerId): BlindPayApiResponse
    {
        return $this->base->getLimitIncreaseRequests($customerId);
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
