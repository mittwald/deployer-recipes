<?php
namespace Mittwald\Deployer\Error;

use Exception;
use Psr\Http\Message\ResponseInterface;
use Throwable;

/**
 * Exception class for unexpected responses from the API.
 */
class UnexpectedResponseException extends Exception
{
    public ?ResponseInterface $response;

    public function __construct(string $message, object $response, ?Throwable $previous = null)
    {
        if (isset($response->httpResponse) && $response->httpResponse instanceof ResponseInterface) {
            $httpResponse = $response->httpResponse;

            $message .= " (HTTP {$httpResponse->getStatusCode()}; response body: {$httpResponse->getBody()->getContents()})";
            $this->response = $httpResponse;
        } else {
            $message .= ' (no HTTP response available)';
            $this->response = null;
        }

        parent::__construct($message, previous: $previous);
    }
}