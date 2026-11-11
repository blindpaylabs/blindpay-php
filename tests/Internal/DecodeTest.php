<?php

declare(strict_types=1);

namespace BlindPay\SDK\Tests\Internal;

use BlindPay\SDK\Internal\Decode;
use BlindPay\SDK\Types\Network;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DecodeTest extends TestCase
{
    #[Test]
    public function it_decodes_known_values_and_keeps_unknown_ones_raw(): void
    {
        $this->assertSame(Network::BASE, Decode::enum(Network::class, 'base'));
        $this->assertSame('future_chain', Decode::enum(Network::class, 'future_chain'));
        $this->assertNull(Decode::enum(Network::class, null));
    }

    #[Test]
    public function it_never_throws_on_a_value_of_the_wrong_json_type(): void
    {
        $this->assertSame('123', Decode::enum(Network::class, 123));
        $this->assertSame('["base"]', Decode::enum(Network::class, ['base']));
        $this->assertSame('true', Decode::enum(Network::class, true));
    }

    #[Test]
    public function it_returns_the_backing_value_or_the_raw_string_as_the_wire_value(): void
    {
        $this->assertSame('base', Decode::wire(Network::BASE));
        $this->assertSame('future_chain', Decode::wire('future_chain'));
        $this->assertNull(Decode::wire(null));
    }
}
