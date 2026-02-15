<?php

namespace XLaravel\Listmonk\Concerns;

use Illuminate\Http\Client\Response;
use XLaravel\Listmonk\Exceptions\ListmonkApiException;
use XLaravel\Listmonk\Exceptions\ListmonkConnectionException;

trait MakesApiCalls
{
    /**
     * Execute an API call with unified error handling.
     *
     * @throws ListmonkApiException
     * @throws ListmonkConnectionException
     */
    protected function apiCall(\Closure $request, string $operation): Response
    {
        try {
            $response = $request();

            if ($response->failed()) {
                throw new ListmonkApiException(
                    "Failed to {$operation}: " . $response->body(),
                    $response->status()
                );
            }

            return $response;
        } catch (ListmonkApiException $e) {
            throw $e;
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            $message = str_contains($e->getMessage(), 'timed out') || str_contains($e->getMessage(), 'timeout')
                ? "Request timed out while connecting to Listmonk API. Please check network connectivity or increase timeout."
                : "Cannot connect to Listmonk API: " . $e->getMessage();

            throw new ListmonkConnectionException($message, 0, $e);
        } catch (\Exception $e) {
            throw new ListmonkApiException(
                "Unexpected error during {$operation}: " . $e->getMessage(),
                0,
                $e
            );
        }
    }
}
