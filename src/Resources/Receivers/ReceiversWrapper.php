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
     * List receivers with optional filters and pagination
     *
     * @param ListReceiversInput|null $params Optional filters and pagination
     * @return BlindPayApiResponse<ListReceiversResponse>
     */
    public function list(?ListReceiversInput $params = null): BlindPayApiResponse
    {
        return $this->base->list($params);
    }

    /*
     * Create a receiver
     *
     * @param CreateReceiverInput $input
     * @return BlindPayApiResponse<CreateReceiverResponse>
     */
    public function create(CreateReceiverInput $input): BlindPayApiResponse
    {
        return $this->base->create($input);
    }

    /*
     * Get a receiver by ID
     *
     * @param string $receiverId
     * @return BlindPayApiResponse<ReceiverOut>
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
