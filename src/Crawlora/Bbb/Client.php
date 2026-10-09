<?php

declare(strict_types=1);

namespace Crawlora\Bbb;

class CrawloraException extends \RuntimeException
{
    public function __construct(string $message, public readonly ?int $status = null, public readonly ?string $operationId = null, public readonly ?string $responseBody = null, ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}

class ClientException extends CrawloraException {}
class ServerException extends CrawloraException {}
class NetworkException extends CrawloraException {}

final class Client
{
    private static array $operations;
    private bool $closed = false;
    private string $apiKey;
    private string $baseUrl;
    private float $timeout;
    private ?\Closure $transport;

    public const PLATFORM = 'bbb';
    public const VERSION = '0.1.3';
    public const OPERATION_COUNT = 9;
    public const OPERATION_IDS = ["bbb-business", "bbb-business-complaints", "bbb-business-more-info", "bbb-business-reviews", "bbb-category", "bbb-scamtracker-detail", "bbb-scamtracker-search", "bbb-scamtracker-state-stats", "bbb-search"];

    public function __construct(?string $apiKey = null, string $baseUrl = 'https://api.crawlora.net/api/v1', float $timeout = 30.0, ?callable $transport = null)
    {
        $this->apiKey = $apiKey ?? (getenv('CRAWLORA_API_KEY') ?: '');
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->timeout = $timeout;
        $this->transport = $transport === null ? null : \Closure::fromCallable($transport);
        self::$operations ??= json_decode(<<<'JSON'
{"bbb-business": {"id": "bbb-business", "method": "GET", "params": [{"description": "BBB business profile URL, from a bbb-search result's url or hq_profile_url", "in": "query", "name": "url", "required": true, "type": "string", "x-example": "https://www.bbb.org/us/tx/austin/profile/plumber/calixto-plumbing-0825-1000223803"}], "path": "/bbb/business", "pathParams": [], "produces": ["application/json"], "queryParams": [{"in": "query", "name": "url", "required": true, "type": "string"}], "security": ["ApiKeyAuth"]}, "bbb-business-complaints": {"id": "bbb-business-complaints", "method": "GET", "params": [{"description": "BBB business profile URL, from a bbb-search result's url or hq_profile_url", "in": "query", "name": "url", "required": true, "type": "string", "x-example": "https://www.bbb.org/us/tx/austin/profile/plumber/calixto-plumbing-0825-1000223803"}], "path": "/bbb/business/complaints", "pathParams": [], "produces": ["application/json"], "queryParams": [{"in": "query", "name": "url", "required": true, "type": "string"}], "security": ["ApiKeyAuth"]}, "bbb-business-more-info": {"id": "bbb-business-more-info", "method": "GET", "params": [{"description": "BBB business profile URL, from a bbb-search result's url or hq_profile_url", "in": "query", "name": "url", "required": true, "type": "string", "x-example": "https://www.bbb.org/us/tx/austin/profile/plumber/calixto-plumbing-0825-1000223803"}], "path": "/bbb/business/more-info", "pathParams": [], "produces": ["application/json"], "queryParams": [{"in": "query", "name": "url", "required": true, "type": "string"}], "security": ["ApiKeyAuth"]}, "bbb-business-reviews": {"id": "bbb-business-reviews", "method": "GET", "params": [{"description": "BBB business profile URL, from a bbb-search result's url or hq_profile_url", "in": "query", "name": "url", "required": true, "type": "string", "x-example": "https://www.bbb.org/us/tx/austin/profile/plumber/calixto-plumbing-0825-1000223803"}, {"description": "Result page (10 per page), default 1", "in": "query", "name": "page", "type": "integer"}], "path": "/bbb/business/reviews", "pathParams": [], "produces": ["application/json"], "queryParams": [{"in": "query", "name": "url", "required": true, "type": "string"}, {"in": "query", "name": "page", "type": "integer"}], "security": ["ApiKeyAuth"]}, "bbb-category": {"id": "bbb-category", "method": "GET", "params": [{"description": "BBB category browse URL, from a bbb-search result's related_categories", "in": "query", "name": "url", "required": true, "type": "string", "x-example": "https://www.bbb.org/us/tx/austin/category/plumber"}, {"description": "Result page, default 1", "in": "query", "name": "page", "type": "integer"}], "path": "/bbb/category", "pathParams": [], "produces": ["application/json"], "queryParams": [{"in": "query", "name": "url", "required": true, "type": "string"}, {"in": "query", "name": "page", "type": "integer"}], "security": ["ApiKeyAuth"]}, "bbb-scamtracker-detail": {"id": "bbb-scamtracker-detail", "method": "GET", "params": [{"description": "BBB Scam Tracker report id, from a bbb-scamtracker-search result's id or url", "in": "path", "name": "id", "required": true, "type": "string", "x-example": "1397968"}], "path": "/bbb/scamtracker/{id}", "pathParams": ["id"], "produces": ["application/json"], "queryParams": [], "security": ["ApiKeyAuth"]}, "bbb-scamtracker-search": {"id": "bbb-scamtracker-search", "method": "GET", "params": [{"description": "Free-text search (phone number, website, email, business name, scam ID, description). Omit to browse the most-recent feed", "in": "query", "name": "query", "type": "string", "x-example": "amazon"}, {"description": "Scam category filter", "enum": ["Advance Fee Loan", "Bank/Credit Card Company Imposter", "Business Email Compromise", "Charity", "Counterfeit Product", "COVID-19", "Credit Cards", "Credit Repair/Debt Relief", "CryptoCurrency", "Debt Collections", "Employment", "Fake Check/Money Order", "Fake Invoice/Supplier Bill", "Family/Friend Emergency", "Foreign Money Exchange", "Government Agency Imposter", "Government Grant", "Healthcare/Medicaid/Medicare", "Home Improvement", "Identity Theft", "Investment", "Moving", "Online Purchase", "Other", "Phishing", "Rental", "Retail Business", "Romance", "Scholarship", "Sweepstakes/Lottery/Prizes", "Tax Collection", "Tech Support", "Travel/Vacation/Timeshare", "Utility", "Vanity Award", "Worthless Problem-solving Service", "Yellow Pages/Directories"], "in": "query", "name": "scam_type", "type": "string"}, {"description": "Optional 2-letter targeted-victim state/province code (US state or Canadian province)", "in": "query", "name": "state", "type": "string", "x-example": "TX"}, {"description": "Optional 2-letter reported-scammer state/province code (US state or Canadian province) -- where the scammer is reported to be, not the victim", "in": "query", "name": "scammer_state", "type": "string", "x-example": "NY"}, {"description": "Optional report-date range start (YYYY-MM-DD), inclusive. Must be set together with date_to", "in": "query", "name": "date_from", "type": "string", "x-example": "2026-01-01"}, {"description": "Optional report-date range end (YYYY-MM-DD), inclusive. Must be set together with date_from", "in": "query", "name": "date_to", "type": "string", "x-example": "2026-01-31"}, {"description": "Optional minimum reported dollar loss. Must be set together with max_dollars_lost", "in": "query", "name": "min_dollars_lost", "type": "integer", "x-example": 500000}, {"description": "Optional maximum reported dollar loss. Must be set together with min_dollars_lost", "in": "query", "name": "max_dollars_lost", "type": "integer", "x-example": 1000000}, {"description": "Result page (10 per page), default 1", "in": "query", "name": "page", "type": "integer"}], "path": "/bbb/scamtracker/search", "pathParams": [], "produces": ["application/json"], "queryParams": [{"in": "query", "name": "query", "type": "string"}, {"enum": ["Advance Fee Loan", "Bank/Credit Card Company Imposter", "Business Email Compromise", "Charity", "Counterfeit Product", "COVID-19", "Credit Cards", "Credit Repair/Debt Relief", "CryptoCurrency", "Debt Collections", "Employment", "Fake Check/Money Order", "Fake Invoice/Supplier Bill", "Family/Friend Emergency", "Foreign Money Exchange", "Government Agency Imposter", "Government Grant", "Healthcare/Medicaid/Medicare", "Home Improvement", "Identity Theft", "Investment", "Moving", "Online Purchase", "Other", "Phishing", "Rental", "Retail Business", "Romance", "Scholarship", "Sweepstakes/Lottery/Prizes", "Tax Collection", "Tech Support", "Travel/Vacation/Timeshare", "Utility", "Vanity Award", "Worthless Problem-solving Service", "Yellow Pages/Directories"], "in": "query", "name": "scam_type", "type": "string"}, {"in": "query", "name": "state", "type": "string"}, {"in": "query", "name": "scammer_state", "type": "string"}, {"in": "query", "name": "date_from", "type": "string"}, {"in": "query", "name": "date_to", "type": "string"}, {"in": "query", "name": "min_dollars_lost", "type": "integer"}, {"in": "query", "name": "max_dollars_lost", "type": "integer"}, {"in": "query", "name": "page", "type": "integer"}], "security": ["ApiKeyAuth"]}, "bbb-scamtracker-state-stats": {"id": "bbb-scamtracker-state-stats", "method": "GET", "params": [{"description": "Aggregation window, default 90", "enum": ["30", "90", "365", "all"], "in": "query", "name": "period", "type": "string"}], "path": "/bbb/scamtracker/state-stats", "pathParams": [], "produces": ["application/json"], "queryParams": [{"enum": ["30", "90", "365", "all"], "in": "query", "name": "period", "type": "string"}], "security": ["ApiKeyAuth"]}, "bbb-search": {"id": "bbb-search", "method": "GET", "params": [{"description": "Business name or category/service keyword", "in": "query", "name": "query", "required": true, "type": "string", "x-example": "plumber"}, {"description": "City and state (e.g. 'Austin, TX') or a ZIP code", "in": "query", "name": "location", "required": true, "type": "string", "x-example": "Austin, TX"}, {"description": "Result page, default 1", "in": "query", "name": "page", "type": "integer"}], "path": "/bbb/search", "pathParams": [], "produces": ["application/json"], "queryParams": [{"in": "query", "name": "query", "required": true, "type": "string"}, {"in": "query", "name": "location", "required": true, "type": "string"}, {"in": "query", "name": "page", "type": "integer"}], "security": ["ApiKeyAuth"]}}
JSON, true, 512, JSON_THROW_ON_ERROR);
    }

    public function request(string $operationId, array $params = [], string $responseType = 'auto'): mixed
    {
        if ($this->closed) {
            throw new ClientException('Client is closed', null, $operationId);
        }
        $operation = self::$operations[$operationId] ?? null;
        if ($operation === null) {
            throw new ClientException('Unknown operation: ' . $operationId, null, $operationId);
        }
        if ($this->apiKey === '') {
            throw new ClientException('Crawlora API key is required', null, $operationId);
        }
        $url = $this->buildUrl($operation, $params);
        $headers = [
            'x-api-key: ' . $this->apiKey,
            'User-Agent: crawlora-bbb-php/0.1.3',
            'Accept: ' . (in_array('text/plain', $operation['produces'], true) ? 'application/json, text/plain' : 'application/json'),
        ];
        try {
            [$status, $contentType, $body] = $this->send($url, $headers, $operationId);
        } catch (CrawloraException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            throw new NetworkException('Crawlora request failed: ' . $exception->getMessage(), null, $operationId, null, $exception);
        }
        if ($status < 200 || $status >= 300) {
            $class = $status >= 500 ? ServerException::class : ClientException::class;
            throw new $class('Crawlora returned HTTP ' . $status, $status, $operationId, $body);
        }
        return $this->parseResponse($body, $contentType, $operation, $params, $responseType);
    }

    public function close(): void
    {
        $this->closed = true;
    }

    public function isClosed(): bool
    {
        return $this->closed;
    }

    public function operationCount(): int
    {
        return self::OPERATION_COUNT;
    }

    public function operationIds(): array
    {
        return self::OPERATION_IDS;
    }

    public function operations(): array
    {
        return self::$operations;
    }

    public function business(mixed ...$params): mixed
    {
        $responseType = $params['_response_type'] ?? $params['response_type'] ?? 'auto';
        unset($params['_response_type'], $params['response_type']);
        return $this->request("bbb-business", $params, $responseType);
    }
    public function business_complaints(mixed ...$params): mixed
    {
        $responseType = $params['_response_type'] ?? $params['response_type'] ?? 'auto';
        unset($params['_response_type'], $params['response_type']);
        return $this->request("bbb-business-complaints", $params, $responseType);
    }
    public function business_more_info(mixed ...$params): mixed
    {
        $responseType = $params['_response_type'] ?? $params['response_type'] ?? 'auto';
        unset($params['_response_type'], $params['response_type']);
        return $this->request("bbb-business-more-info", $params, $responseType);
    }
    public function business_reviews(mixed ...$params): mixed
    {
        $responseType = $params['_response_type'] ?? $params['response_type'] ?? 'auto';
        unset($params['_response_type'], $params['response_type']);
        return $this->request("bbb-business-reviews", $params, $responseType);
    }
    public function category(mixed ...$params): mixed
    {
        $responseType = $params['_response_type'] ?? $params['response_type'] ?? 'auto';
        unset($params['_response_type'], $params['response_type']);
        return $this->request("bbb-category", $params, $responseType);
    }
    public function scamtracker_search(mixed ...$params): mixed
    {
        $responseType = $params['_response_type'] ?? $params['response_type'] ?? 'auto';
        unset($params['_response_type'], $params['response_type']);
        return $this->request("bbb-scamtracker-search", $params, $responseType);
    }
    public function scamtracker_state_stats(mixed ...$params): mixed
    {
        $responseType = $params['_response_type'] ?? $params['response_type'] ?? 'auto';
        unset($params['_response_type'], $params['response_type']);
        return $this->request("bbb-scamtracker-state-stats", $params, $responseType);
    }
    public function scamtracker_detail(mixed ...$params): mixed
    {
        $responseType = $params['_response_type'] ?? $params['response_type'] ?? 'auto';
        unset($params['_response_type'], $params['response_type']);
        return $this->request("bbb-scamtracker-detail", $params, $responseType);
    }
    public function search(mixed ...$params): mixed
    {
        $responseType = $params['_response_type'] ?? $params['response_type'] ?? 'auto';
        unset($params['_response_type'], $params['response_type']);
        return $this->request("bbb-search", $params, $responseType);
    }

    private function buildUrl(array $operation, array $params): string
    {
        $known = array_column($operation['params'], 'name');
        $unknown = array_diff(array_keys($params), $known, ['response_type', '_response_type']);
        if ($unknown !== []) {
            throw new ClientException('Unknown parameters: ' . implode(', ', $unknown), null, $operation['id']);
        }
        $path = $operation['path'];
        foreach ($operation['params'] as $param) {
            if ($param['in'] !== 'path') {
                continue;
            }
            $name = $param['name'];
            if (!array_key_exists($name, $params) || $params[$name] === null) {
                throw new ClientException('Missing path parameter: ' . $name, null, $operation['id']);
            }
            $path = str_replace('{' . $name . '}', rawurlencode((string) $params[$name]), $path);
        }
        $pairs = [];
        foreach ($operation['queryParams'] as $param) {
            $name = $param['name'];
            $value = $params[$name] ?? ($param['default'] ?? null);
            if ($value === null) {
                if ($param['required'] ?? false) {
                    throw new ClientException('Missing query parameter: ' . $name, null, $operation['id']);
                }
                continue;
            }
            $enumValues = $param['enum'] ?? ($param['items']['enum'] ?? null);
            $values = is_array($value) ? $value : [$value];
            $invalidEnum = false;
            foreach ($values as $item) {
                if ($enumValues !== null && !in_array((string) $item, array_map('strval', $enumValues), true)) {
                    $invalidEnum = true;
                    break;
                }
            }
            if ($invalidEnum) {
                throw new ClientException('Invalid value for ' . $name, null, $operation['id']);
            }
            if (is_array($value)) {
                $format = $param['collectionFormat'] ?? 'csv';
                if ($format === 'multi') {
                    foreach ($value as $item) {
                        $pairs[] = [rawurlencode($name), rawurlencode($this->stringify($item))];
                    }
                } else {
                    $separator = ['csv' => ',', 'ssv' => ' ', 'tsv' => "\t", 'pipes' => '|'][$format] ?? ',';
                    $pairs[] = [rawurlencode($name), rawurlencode(implode($separator, array_map([$this, 'stringify'], $value)))];
                }
            } else {
                $pairs[] = [rawurlencode($name), rawurlencode($this->stringify($value))];
            }
        }
        $query = implode('&', array_map(static fn(array $pair): string => $pair[0] . '=' . $pair[1], $pairs));
        return $this->baseUrl . $path . ($query === '' ? '' : '?' . $query);
    }

    private function stringify(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_array($value)) {
            return json_encode($value, JSON_THROW_ON_ERROR);
        }
        return (string) $value;
    }

    private function send(string $url, array $headers, string $operationId): array
    {
        if ($this->transport !== null) {
            $result = ($this->transport)($url, $headers, $this->timeout);
            return [(int) $result['status'], (string) ($result['content_type'] ?? ''), (string) ($result['body'] ?? '')];
        }
        $handle = curl_init($url);
        if ($handle === false) {
            throw new NetworkException('Could not initialize cURL', null, $operationId);
        }
        curl_setopt_array($handle, [
            CURLOPT_HTTPGET => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT_MS => (int) ($this->timeout * 1000),
            CURLOPT_CONNECTTIMEOUT_MS => (int) ($this->timeout * 1000),
        ]);
        $body = curl_exec($handle);
        if ($body === false) {
            $message = curl_error($handle);
            curl_close($handle);
            throw new NetworkException('Crawlora request failed: ' . $message, null, $operationId);
        }
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $contentType = (string) curl_getinfo($handle, CURLINFO_CONTENT_TYPE);
        curl_close($handle);
        return [$status, $contentType, (string) $body];
    }

    private function parseResponse(string $body, string $contentType, array $operation, array $params, string $responseType): mixed
    {
        if (!in_array($responseType, ['auto', 'json', 'text'], true)) {
            throw new ClientException('responseType must be auto, json, or text', null, $operation['id']);
        }
        $format = null;
        foreach ($operation['params'] as $param) {
            if ($param['name'] === 'format') {
                $format = $param;
                break;
            }
        }
        $textFormats = array_values(array_filter($format['enum'] ?? [], static fn($value): bool => !in_array(strtolower((string) $value), ['json', 'application/json'], true)));
        $rawFormat = isset($params['format']) && in_array((string) $params['format'], array_map('strval', $textFormats), true);
        $jsonFormat = isset($params['format']) && in_array(strtolower((string) $params['format']), ['json', 'application/json'], true);
        $isJson = $jsonFormat || stripos($contentType, 'json') !== false || $operation['produces'] === ['application/json'];
        if ($responseType === 'text' || $rawFormat || ($responseType === 'auto' && !$isJson)) {
            return $body;
        }
        try {
            return json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new CrawloraException('Invalid JSON response from Crawlora: ' . $exception->getMessage(), null, $operation['id'], $body, $exception);
        }
    }
}
