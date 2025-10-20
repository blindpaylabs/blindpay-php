<?php

declare(strict_types=1);

namespace BlindPay\SDK\Types;

readonly class BankDetail
{
    /*
     * @param BankDetailItem[] $items
     */
    public function __construct(
        public string $label,
        public string $regex,
        public string $key,
        public array $items,
        public bool $required
    ) {}

    public static function fromArray(array $data): self
    {
        $items = [];
        if (isset($data['items']) && is_array($data['items'])) {
            foreach ($data['items'] as $item) {
                $items[] = BankDetailItem::fromArray($item);
            }
        }

        return new self(
            label: $data['label'],
            regex: $data['regex'],
            key: $data['key'],
            items: $items,
            required: $data['required']
        );
    }
}
