<?php

declare(strict_types=1);

namespace BlindPay\SDK\Resources\Payins;

use BlindPay\SDK\Types\BlindPayApiResponse;

readonly class PayinsWrapper
{
    public function __construct(
        private Payins $base,
        public Quotes $quotes
    ) {}

    /*
     * List all payins with optional filters
     *
     * @param ListPayinsInput|null $params Optional filters for listing payins
     * @return BlindPayApiResponse<ListPayinsResponse>
     */
    public function list(?ListPayinsInput $params = null): BlindPayApiResponse
    {
        return $this->base->list($params);
    }

    /*
     * Get a specific payin by ID
     *
     * @param string $payinId The payin ID
     * @return BlindPayApiResponse<Payin>
     */
    public function get(string $payinId): BlindPayApiResponse
    {
        return $this->base->get($payinId);
    }

    /*
     * Get payin tracking information
     *
     * @param string $payinId The payin ID to track
     * @return BlindPayApiResponse<Payin>
     */
    public function getTrack(string $payinId): BlindPayApiResponse
    {
        return $this->base->getTrack($payinId);
    }

    /*
     * Export payins by status
     *
     * @param ExportPayinsInput $params Export parameters
     * @return BlindPayApiResponse<Payin[]>
     */
    public function export(ExportPayinsInput $params): BlindPayApiResponse
    {
        return $this->base->export($params);
    }

    /*
     * Create an EVM payin from a quote
     *
     * @param string $payinQuoteId The payin quote ID
     * @return BlindPayApiResponse<CreateEvmPayinResponse>
     */
    public function createEvm(string $payinQuoteId): BlindPayApiResponse
    {
        return $this->base->createEvm($payinQuoteId);
    }
}

