<?php
/** Copyright github.com/greezlu */

declare(strict_types = 1);

namespace WebServer\Core;

/**
 * @package greezlu/ws-loader
 */
class LoaderResponse
{
    /**
     * @var string
     */
    private string $response;

    /**
     * @var int
     */
    private int $statusCode;

    /**
     * @var array
     */
    private array $responseHeaders;

    /**
     * @param string $response
     * @param int $statusCode
     * @param array $responseHeaders
     */
    public function __construct(
        string $response,
        int $statusCode,
        array $responseHeaders
    ) {
        $this->response         = $response;
        $this->statusCode       = $statusCode;
        $this->responseHeaders  = $responseHeaders;
    }

    /**
     * @return string
     */
    public function __toString(): string
    {
        return $this->response;
    }

    /**
     * Get raw response as string data.
     *
     * @return string
     */
    public function toString(): string
    {
        return $this->response;
    }

    /**
     * Attempt to decode response. Return result or empty array.
     *
     * @return array
     */
    public function toArray(): array
    {
        $decodedResponse = json_decode($this->response, true);
        return is_array($decodedResponse) ? $decodedResponse : [];
    }

    /**
     * Get list of response headers.
     *
     * @return array
     */
    public function getResponseHeaders(): array
    {
        return $this->responseHeaders;
    }

    /**
     * Get response status code.
     *
     * @return int
     */
    public function getResponseStatusCode(): int
    {
        return $this->statusCode;
    }
}
