<?php

namespace Traktor;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use Traktor\Exception\AuthorizationException;
use Traktor\Exception\AvailabilityException;
use Traktor\Exception\MissingApiKeyException;
use Traktor\Exception\UnknownMethodException;
use Traktor\Exception\RequestException;

/**
 * @author Alan Ly <hello@alan.ly>
 */
class Client
{

    /**
     * Constant containing the end-point for the Trakt.tv API.
     */
    const TRAKT_API_ENDPOINT = 'http://api.trakt.tv';

    /**
     * Constant containing the desired return format from the API. Currently,
     * Trakt only supports JSON.
     */
    const RETURN_FORMAT = 'json';

    /**
     * @var string
     */
    protected $apiKey = '';

    /**
     * @var GuzzleHttp\Client
     */
    protected $client = null;

    /**
     * Construct a new instance of Traktor.
     *
     * @param null|GuzzleHttp\Client $client
     */
    public function __construct(GuzzleClient $client = null)
    {
        if (! $client) {
            $this->client = new GuzzleClient;
        } else {
            $this->client = $client;
        }
    }

    /**
     * Set the user key for the API session.
     *
     * @param  string  $key
     * @return void
     */
    public function setApiKey($key)
    {
        $this->apiKey = $key;
    }

    /**
     * Get the user key for the API session.
     *
     * @return string
     */
    public function getApiKey()
    {
        return $this->apiKey;
    }

    /**
     * Performs a GET request against the API and returns the results as
     * instance(s) of `stdClass`.
     *
     * @param  string      $method
     * @param  null|array  $params
     * @return mixed
     */
    public function get($method, $params = null)
    {
        if (! $this->apiKey) {
            throw new MissingApiKeyException('The request API key is unset.');
        }

        if (! $params) {
            $params = [];
        }

        $target = $this->assembleGetRequestTarget($method);

        $response = $this->performGetRequest($target);

        return $this->parseResponse($response);
    }

    /**
     * Creates the complete request target based on the requested method and
     * any associated parameters.
     *
     * @param  string  $method
     * @return string
     */
    protected function assembleGetRequestTarget($method)
    {
        $method = preg_replace('/\./', '/', $method);

        $target = self::TRAKT_API_ENDPOINT
                    . '/' . $method . '.' . self::RETURN_FORMAT;

        return $target;
    }

    /**
     * Executes the GET request specified by `$target`.
     *
     * @param  string  $target
     * @return GuzzleHttp\Psr7\Response
     */
    protected function performGetRequest($target)
    {
        return $this->client->get($target, [
            "headers" => [
                "Content-Type" => "application/json",
                "trakt-api-version" => "2",
                "trakt-api-key" => $this->apiKey
            ]
        ]);
    }

    /**
     * Parse a response, appropriately converting from JSON to `stdClass` as
     * well as handling errors.
     *
     * @param  GuzzleHttp\Psr7\Response
     * @return mixed
     */
    protected function parseResponse(GuzzleResponse $response)
    {
        $this->checkResponseErrors($response);

        try {
            $body = $response->getBody();
            $decodedBody = json_decode($body, false);
        } catch (GuzzleHttp\Exception\ParseException $e) {
            throw new RequestException('Unable to parse response: '
                . $response->getBody());
        }

        return $decodedBody;
    }

    /**
     * Checks a GuzzleHttp response for errors, throwing the appropriate
     * exception if necessary.
     *
     * @param  GuzzleHttp\Psr7\Response
     * @return void
     */
    protected function checkResponseErrors($response)
    {
        $responseStatusCode = intval($response->getStatusCode());

        if ($responseStatusCode === 200) return;

        try {
            $body = $response->getBody();
            $decodedBody = json_decode($body, false);
        } catch (GuzzleHttp\Exception\ParseException $e) {
            throw new RequestException('Unable to parse response: '
                . $response->getBody());
        }

        switch ($responseStatusCode) {
            case 401:
                throw new AuthorizationException($decodedBody->error);
            case 404:
                throw new UnknownMethodException($decodedBody->error);
            case 503:
                throw new AvailabilityException($decodedBody->error);
            default:
                throw new RequestException('Unrecognized status code ('
                    . $responseStatusCode . '): '
                    . $response->getBody());
        }
    }
    
}
