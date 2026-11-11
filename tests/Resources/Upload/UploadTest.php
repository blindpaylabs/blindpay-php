<?php

declare(strict_types=1);

namespace BlindPay\SDK\Tests\Resources;

use BlindPay\SDK\BlindPay;
use BlindPay\SDK\Resources\Upload\AnalyzeUploadInput;
use BlindPay\SDK\Resources\Upload\UploadAnalyzeType;
use BlindPay\SDK\Resources\Upload\UploadBucket;
use BlindPay\SDK\Resources\Upload\UploadFile;
use BlindPay\SDK\Resources\Upload\UploadInput;
use BlindPay\SDK\Types\ApprovalRate;
use BlindPay\SDK\Types\RequestOptions;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;

class UploadTest extends TestCase
{
    private BlindPay $blindpay;

    private MockHandler $mockHandler;

    private array $history = [];

    protected function setUp(): void
    {
        $this->mockHandler = new MockHandler;
        $stack = HandlerStack::create($this->mockHandler);
        $stack->push(Middleware::history($this->history));
        $this->blindpay = new BlindPay(apiKey: 'test_key', instanceId: 'in_000000000000', handler: $stack);
    }

    private function request(): RequestInterface
    {
        return $this->history[0]['request'];
    }

    /** @return array<string, array{headers: string, body: string}> multipart parts by field name */
    private function parts(RequestInterface $request): array
    {
        preg_match('/boundary=(.+)$/', $request->getHeaderLine('Content-Type'), $m);
        $parts = [];
        foreach (explode('--'.$m[1], (string) $request->getBody()) as $chunk) {
            if (! str_contains($chunk, "\r\n\r\n")) {
                continue;
            }
            [$headers, $body] = explode("\r\n\r\n", ltrim($chunk, "\r\n"), 2);
            preg_match('/name="([^"]+)"/', $headers, $name);
            $parts[$name[1]] = ['headers' => $headers, 'body' => substr($body, 0, -2)];
        }

        return $parts;
    }

    #[Test]
    public function it_uploads_a_file_as_multipart_with_the_instance_id(): void
    {
        $this->mockHandler->append(new Response(200, [], json_encode(['file_url' => 'https://files.blindpay.com/doc.pdf'])));

        $response = $this->blindpay->upload->create(
            new UploadInput(UploadBucket::DOCUMENTS, new UploadFile('%PDF-1.4 bytes', 'doc.pdf', 'application/pdf')),
            new RequestOptions(idempotencyKey: 'ignored-for-multipart'),
        );

        $this->assertTrue($response->isSuccess());
        $this->assertSame('https://files.blindpay.com/doc.pdf', $response->data->fileUrl);
        $request = $this->request();
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('/v1/upload', $request->getUri()->getPath());
        $this->assertSame('instance_id=in_000000000000', $request->getUri()->getQuery());
        $this->assertStringStartsWith('multipart/form-data; boundary=', $request->getHeaderLine('Content-Type'));
        $this->assertFalse($request->hasHeader('Idempotency-Key'));
        $parts = $this->parts($request);
        $this->assertSame('%PDF-1.4 bytes', $parts['file']['body']);
        $this->assertStringContainsString('filename="doc.pdf"', $parts['file']['headers']);
        $this->assertStringContainsString('Content-Type: application/pdf', $parts['file']['headers']);
        $this->assertSame('documents', $parts['bucket']['body']);
    }

    #[Test]
    public function it_uploads_from_a_path(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'bp');
        file_put_contents($path, 'avatar bytes');
        $this->mockHandler->append(new Response(200, [], json_encode(['file_url' => 'https://files.blindpay.com/a.png'])));

        $response = $this->blindpay->upload->create(new UploadInput(UploadBucket::AVATAR, UploadFile::fromPath($path, 'image/png', 'a.png')));

        unlink($path);
        $this->assertTrue($response->isSuccess());
        $parts = $this->parts($this->request());
        $this->assertSame('avatar bytes', $parts['file']['body']);
        $this->assertStringContainsString('filename="a.png"', $parts['file']['headers']);
        $this->assertSame('avatar', $parts['bucket']['body']);
    }

    #[Test]
    public function it_analyzes_a_document_with_metadata_as_a_json_string(): void
    {
        $this->mockHandler->append(new Response(200, [], json_encode(['approval_rate' => 'high', 'description' => 'Looks valid.'])));

        $response = $this->blindpay->upload->analyze(new AnalyzeUploadInput(
            new UploadFile('jpeg', 'bill.jpg', 'image/jpeg'),
            UploadAnalyzeType::PROOF_OF_ADDRESS,
            ['full_name' => 'Jane Doe'],
        ));

        $this->assertSame(ApprovalRate::HIGH, $response->data->approvalRate);
        $request = $this->request();
        $this->assertSame('/v1/upload/analyze', $request->getUri()->getPath());
        $this->assertSame('instance_id=in_000000000000', $request->getUri()->getQuery());
        $parts = $this->parts($request);
        $this->assertSame('proof_of_address', $parts['type']['body']);
        $this->assertSame('{"full_name":"Jane Doe"}', $parts['metadata']['body']);
        $this->assertSame('jpeg', $parts['file']['body']);
    }

    #[Test]
    public function it_presigns_a_file_url_as_json(): void
    {
        $this->mockHandler->append(new Response(200, [], json_encode(['file_url' => 'https://signed', 'expires_at' => '2026-01-01T00:00:00Z'])));

        $response = $this->blindpay->upload->presign('https://files.blindpay.com/doc.pdf');

        $this->assertSame('https://signed', $response->data->fileUrl);
        $request = $this->request();
        $this->assertSame('/v1/presign', $request->getUri()->getPath());
        $this->assertSame('instance_id=in_000000000000', $request->getUri()->getQuery());
        $this->assertSame('{"file_url":"https:\/\/files.blindpay.com\/doc.pdf"}', (string) $request->getBody());
        $this->assertNotSame('', $request->getHeaderLine('Idempotency-Key'));
    }

    #[Test]
    public function it_handles_an_upload_error_without_retrying(): void
    {
        $this->mockHandler->append(new Response(400, [], json_encode(['success' => false, 'code' => 'ONBOARDING_INCOMPLETE', 'message' => 'onboarding_incomplete'])));

        $response = $this->blindpay->upload->create(new UploadInput(UploadBucket::ONBOARDING, new UploadFile('x', 'x.txt', 'text/plain')));

        $this->assertTrue($response->isError());
        $this->assertSame('ONBOARDING_INCOMPLETE', $response->error->code);
        $this->assertCount(1, $this->history);
    }
}
