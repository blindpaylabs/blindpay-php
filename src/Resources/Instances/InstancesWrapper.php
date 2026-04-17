<?php

declare(strict_types=1);

namespace BlindPay\SDK\Resources\Instances;

use BlindPay\SDK\Resources\ApiKeys\ApiKeys;
use BlindPay\SDK\Resources\Webhooks\Webhooks;
use BlindPay\SDK\Types\BlindPayApiResponse;

readonly class InstancesWrapper
{
    public function __construct(
        private Instances $base,
        public ApiKeys $apiKeys,
        public Webhooks $webhookEndpoints
    ) {}

    /*
     * List instance members
     *
     * @return BlindPayApiResponse
     */
    public function getMembers(): BlindPayApiResponse
    {
        return $this->base->getMembers();
    }

    /*
     * Update instance
     *
     * @param UpdateInstanceInput $input
     * @return BlindPayApiResponse
     */
    public function update(UpdateInstanceInput $input): BlindPayApiResponse
    {
        return $this->base->update($input);
    }

    /*
     * Delete instance
     *
     * @return BlindPayApiResponse
     */
    public function delete(): BlindPayApiResponse
    {
        return $this->base->delete();
    }

    /*
     * Delete instance member
     *
     * @param string $memberId
     * @return BlindPayApiResponse
     */
    public function deleteMember(string $memberId): BlindPayApiResponse
    {
        return $this->base->deleteMember($memberId);
    }

    /*
     * Update member role
     *
     * @param UpdateMemberRoleInput $input
     * @return BlindPayApiResponse
     */
    public function updateMemberRole(UpdateMemberRoleInput $input): BlindPayApiResponse
    {
        return $this->base->updateMemberRole($input);
    }
}
