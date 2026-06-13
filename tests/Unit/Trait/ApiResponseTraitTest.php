<?php

namespace App\Tests\Unit\Trait;

use App\Trait\ApiResponseTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class ApiResponseTraitTest extends TestCase
{
    /**
     * Test object exposing the trait's protected methods publicly.
     */
    private object $sut;

    protected function setUp(): void
    {
        $this->sut = new class {
            use ApiResponseTrait;

            public function callSuccess(array $data = [], int $status = Response::HTTP_OK): JsonResponse
            {
                return $this->success($data, $status);
            }

            public function callError(string $message, int $status = Response::HTTP_BAD_REQUEST): JsonResponse
            {
                return $this->error($message, $status);
            }

            public function callCreated(array $data = []): JsonResponse
            {
                return $this->created($data);
            }

            public function callNoContent(): JsonResponse
            {
                return $this->noContent();
            }
        };
    }

    public function testSuccessWrapsDataWithSuccessFlag(): void
    {
        $response = $this->sut->callSuccess(['user' => ['id' => 1]]);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertSame(
            ['success' => true, 'user' => ['id' => 1]],
            json_decode($response->getContent(), true),
        );
    }

    public function testErrorReturnsFailureContract(): void
    {
        $response = $this->sut->callError('Boom', Response::HTTP_CONFLICT);

        $this->assertSame(Response::HTTP_CONFLICT, $response->getStatusCode());
        $this->assertSame(
            ['success' => false, 'message' => 'Boom'],
            json_decode($response->getContent(), true),
        );
    }

    public function testCreatedUsesStatus201(): void
    {
        $response = $this->sut->callCreated(['id' => 7]);

        $this->assertSame(Response::HTTP_CREATED, $response->getStatusCode());
        $this->assertTrue(json_decode($response->getContent(), true)['success']);
    }

    public function testNoContentReturns204(): void
    {
        $response = $this->sut->callNoContent();

        $this->assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
    }
}
