<?php
/** Copyright github.com/greezlu */

declare(strict_types = 1);

namespace WebServer\Core;

use WebServer\Core\LoaderResponse;

/**
 * @package greezlu/ws-loader
 */
class Loader
{
    protected const DEFAULT_HEADERS = [
        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/95.0.4638.69 Safari/537.36'
    ];

    protected const DEFAULT_LOAD_TIMEOUT = 500000;

    /**
     * @var int
     */
    private int $requestTimeout;

    /**
     * @var array
     */
    private array $requestParams;

    /**
     * @var array
     */
    private array $curlSettingsMerged = [];

    /**
     * @param string $address
     * @param string|null $method
     * @param array|null $requestParams [headers, cookie, curlSettings, postParams, getParams]
     * @param int|null $requestTimeout
     */
    public function __construct(
        string $address,
        string $method = 'GET',
        array $requestParams = [],
        int $requestTimeout = self::DEFAULT_LOAD_TIMEOUT
    ) {
        $this->requestParams = $requestParams + [
            'address'   => $address,
            'method'    => $method
        ];

        $this->requestTimeout = $requestTimeout;
    }

    /**
     * Send request and return response object or null.
     *
     * @return LoaderResponse|null
     */
    public function load(): ?LoaderResponse
    {
        $address        = $this->requestParams['address']       ?? [];
        $method         = $this->requestParams['method']        ?? [];
        $headers        = $this->requestParams['headers']       ?? [];
        $cookie         = $this->requestParams['cookie']        ?? [];
        $curlSettings   = $this->requestParams['curlSettings']  ?? [];

        $postParams     = $this->requestParams['postParams']    ?? [];
        $getParams      = $this->requestParams['getParams']     ?? [];

        if (!empty($getParams)) {
            $formattedGetParams = is_array($getParams)
                ? http_build_query($getParams)
                : $getParams;

            $address = http_build_url(
                $address,
                ['query' => $formattedGetParams]
            );
        }

        $curl = curl_init();

        if ($curl === false) {
            return null;
        }

        if ($method === 'POST' && !empty($postParams)) {
            $formattedPostParams = is_array($postParams)
                ? http_build_query($postParams)
                : $postParams;

            curl_setopt(
                $curl,
                CURLOPT_POSTFIELDS,
                $formattedPostParams
            );

            $headers['Content-Length'] = strlen($formattedPostParams);
        }

        if (!empty($cookie)) {
            $headers['Cookie'] = $this->cookieBuildQuery($cookie);
        }

        $formattedHeaders = $this->getFormattedHeaders($headers);

        $curlSettingsMerged = $curlSettings + [
            CURLOPT_URL             => $address,
            CURLOPT_RETURNTRANSFER  => true,
            CURLOPT_HEADER          => true,
            CURLOPT_FOLLOWLOCATION  => true,
            CURLOPT_TIMEOUT         => 60,
            CURLOPT_CUSTOMREQUEST   => $method,
            CURLOPT_SSL_VERIFYHOST  => false,
            CURLOPT_SSL_VERIFYPEER  => false,
            CURLOPT_HTTPHEADER      => $formattedHeaders
        ];

        $this->curlSettingsMerged = $curlSettingsMerged;

        curl_setopt_array($curl, $curlSettingsMerged);

        $response = curl_exec($curl);
        $error = curl_error($curl);

        if ($error) {
            return null;
        }

        $responseCode = curl_getinfo($curl,CURLINFO_RESPONSE_CODE);
        $responseHeaders = $this->separateResponseHeaders($curl, $response);
        $response = $this->cleanResponse($response);

        curl_close($curl);

        $this->timeout();

        return new LoaderResponse($response, $responseCode, $responseHeaders);
    }

    /**
     * Build cookie query ready to use in request.
     *
     * @param array $data
     * @return string
     */
    private function cookieBuildQuery(array $data): string
    {
        $query = '';

        foreach ($data as $key => $item) {
            $query .= "$key=$item; ";
        }

        return substr($query, 0, -2) ?: '';
    }

    /**
     * Build headers array ready to use in request.
     *
     * @param array $headers
     * @return string[]
     */
    private function getFormattedHeaders(array $headers): array
    {
        $formattedHeaders = [];

        foreach (array_merge(self::DEFAULT_HEADERS, $headers) as $headerTitle => $headerValue) {
            $formattedHeaders[] = "$headerTitle: $headerValue";
        }

        return $formattedHeaders;
    }

    /**
     * Separate response headers from the actual response.
     * Modify provided $response by removing headers.
     * Return response headers or empty array.
     *
     * @param resource $curl Curl instance.
     * @param string $response Response.
     * @return array Response headers.
     */
    private function separateResponseHeaders($curl, string &$response): array
    {
        if (empty($this->curlSettingsMerged[CURLOPT_HEADER])) {
            return [];
        }

        $responseHeaderSize = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
        $responseHeaders = substr($response, 0, $responseHeaderSize);

        if (is_string($responseHeaders)) {
            $responseHeaders = $this->formatResponseHeaders($responseHeaders);
            $response = trim(substr($response, $responseHeaderSize)) ?: '';
        }

        return $responseHeaders;
    }

    /**
     * Parse response headers to array.
     *
     * @param string $responseHeaders
     * @return array
     */
    private function formatResponseHeaders(string $responseHeaders): array
    {
        $responseHeaders = trim($responseHeaders);
        $responseHeaderList = preg_split('/\r\n|\r|\n/', $responseHeaders);

        if (!is_array($responseHeaderList)) {
            return [];
        }

        $finalHeaderList = [];

        foreach ($responseHeaderList as $responseHeaderString) {
            $responseHeaderString = trim($responseHeaderString);

            if (empty($responseHeaderString)) {
                continue;
            }

            $separatorPosition = strpos($responseHeaderString, ':');

            if (!is_int($separatorPosition)) {
                continue;
            }

            $mainHeaderName = trim(substr($responseHeaderString, 0, $separatorPosition));
            $mainHeaderValue = trim(substr($responseHeaderString, $separatorPosition + 1));

            if (strtolower($mainHeaderName) !== 'set-cookie') {
                $finalHeaderList[$mainHeaderName] = $mainHeaderValue;
                continue;
            }

            $cookieList = explode(';', $mainHeaderValue);

            foreach ($cookieList as $cookie) {
                if (empty(trim($cookie))) {
                    continue;
                }

                $cookieSeparatorPosition = strpos($cookie, '=');

                if (!is_int($cookieSeparatorPosition)) {
                    $finalHeaderList[$mainHeaderName][trim($cookie)] = '';
                    continue;
                }

                $cookieName = trim(substr($cookie, 0, $cookieSeparatorPosition));
                $cookieValue = trim(substr($cookie, $cookieSeparatorPosition + 1));

                $finalHeaderList[$mainHeaderName][$cookieName] = $cookieValue;
            }
        }

        return $finalHeaderList;
    }

    /**
     * Clean response from unwanted characters.
     *
     * @param string $response
     * @return string
     */
    private function cleanResponse(string $response): string
    {
        // Remove unwanted characters.
        // Check http://www.php.net/chr for details
        for ($i = 0; $i <= 31; ++$i) {
            $response = str_replace(chr($i), "", $response);
        }

        $response = str_replace(chr(127), "", $response);

        // Some files begin with 'efbbbf' to mark the beginning of the file. (binary level)
        // here we detect and remove it, basically it is the first 3 characters 
        if (0 === strpos(bin2hex($response), 'efbbbf')) {
            $response = substr($response, 3);
        }

        return $response;
    }

    /**
     * Script pause after successful request.
     *
     * @return void
     */
    private function timeout(): void
    {
        if ($this->requestTimeout > 0) {
            usleep($this->requestTimeout);
        }
    }
}
