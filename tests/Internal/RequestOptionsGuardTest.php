<?php

declare(strict_types=1);

namespace BlindPay\SDK\Tests\Internal;

use BlindPay\SDK\BlindPay;
use BlindPay\SDK\Types\BlindPayApiResponse;
use BlindPay\SDK\Types\RequestOptions;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;

/**
 * Every public resource method that calls the API takes `?RequestOptions $options = null` as its
 * last parameter (C2). SpecFixtureDecodeTest proves each one forwards it on the wire.
 */
final class RequestOptionsGuardTest extends TestCase
{
    #[Test]
    public function every_resource_method_accepts_request_options_last(): void
    {
        $blindpay = new BlindPay(apiKey: 'test_key', instanceId: 'in_000000000000');
        $missing = [];
        $checked = 0;

        foreach ($this->resources($blindpay) as $path => $resource) {
            foreach ((new ReflectionClass($resource))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                $return = $method->getReturnType();
                if (! $return instanceof ReflectionNamedType || $return->getName() !== BlindPayApiResponse::class) {
                    continue;
                }
                $checked++;
                $params = $method->getParameters();
                $last = end($params);
                $type = $last ? $last->getType() : null;
                if (! $last || $last->getName() !== 'options' || ! $type instanceof ReflectionNamedType
                    || $type->getName() !== RequestOptions::class || ! $type->allowsNull() || ! $last->isOptional()) {
                    $missing[] = "{$path}->{$method->getName()}()";
                }
            }
        }

        $this->assertGreaterThan(80, $checked);
        $this->assertSame([], $missing);
    }

    /** @return iterable<string, object> every resource reachable from the client, by access path */
    private function resources(object $node, string $path = '$blindpay', array &$seen = []): iterable
    {
        foreach ((new ReflectionClass($node))->getProperties() as $property) {
            if (! $property->isPublic() || ! $property->isInitialized($node)) {
                continue;
            }
            $value = $property->getValue($node);
            if (! is_object($value) || ! str_starts_with($value::class, 'BlindPay\\SDK\\Resources\\') || isset($seen[spl_object_id($value)])) {
                continue;
            }
            $seen[spl_object_id($value)] = true;
            yield "{$path}->{$property->getName()}" => $value;
            yield from $this->resources($value, "{$path}->{$property->getName()}", $seen);
        }
    }
}
