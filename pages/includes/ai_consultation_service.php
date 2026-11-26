<?php

declare(strict_types=1);

function aiConsultationProviders(): array
{
    $providers = [];
    $configurationErrors = [];
    $environment = getenv();
    $providerIndexes = [];

    if (is_array($environment)) {
        foreach (array_keys($environment) as $environmentName) {
            if (preg_match('/^COMFSCI_AI_PROVIDER_([1-9][0-9]*)_(BASE_URL|API_KEY|MODEL)$/i', (string) $environmentName, $matches)) {
                $index = (int) $matches[1];
                if ($index > 0) {
                    $providerIndexes[$index] = $index;
                }
            }
        }
    }
    ksort($providerIndexes, SORT_NUMERIC);

    foreach ($providerIndexes as $index) {
        $baseUrlValue = getenv('COMFSCI_AI_PROVIDER_' . $index . '_BASE_URL');
        $apiKeyValue = getenv('COMFSCI_AI_PROVIDER_' . $index . '_API_KEY');
        $modelValue = getenv('COMFSCI_AI_PROVIDER_' . $index . '_MODEL');
        $baseUrl = $baseUrlValue === false ? '' : trim($baseUrlValue);
        $apiKey = $apiKeyValue === false ? '' : trim($apiKeyValue);
        $model = $modelValue === false ? '' : trim($modelValue);

        if ($baseUrl === '' && $apiKey === '' && $model === '') {
            continue;
        }
        if ($baseUrl === '' || $apiKey === '' || $model === '') {
            $configurationErrors[] = 'Provider ' . $index . ': incomplete configuration.';
            continue;
        }

        $urlParts = parse_url($baseUrl);
        if (filter_var($baseUrl, FILTER_VALIDATE_URL) === false
            || !is_array($urlParts)
            || empty($urlParts['scheme'])
            || empty($urlParts['host'])
            || !in_array(strtolower((string) $urlParts['scheme']), ['https', 'http'], true)
            || isset($urlParts['user'])
            || isset($urlParts['pass'])
            || isset($urlParts['query'])
            || isset($urlParts['fragment'])) {
            $configurationErrors[] = 'Provider ' . $index . ': invalid base URL.';
            continue;
        }

        $scheme = strtolower((string) $urlParts['scheme']);
        $host = strtolower((string) $urlParts['host']);
        $isLoopback = in_array(trim($host, '[]'), ['localhost', '127.0.0.1', '::1'], true);
        if ($scheme !== 'https' && !$isLoopback) {
            $configurationErrors[] = 'Provider ' . $index . ': HTTPS is required except for loopback hosts.';
            continue;
        }
        if ((isset($urlParts['port']) && ((int) $urlParts['port'] < 1 || (int) $urlParts['port'] > 65535))
            || preg_match('/[\r\n]/', $apiKey)
            || preg_match('/[\x00-\x1F\x7F]/', $model)) {
            $configurationErrors[] = 'Provider ' . $index . ': invalid provider settings.';
            continue;
        }

        $baseUrl = rtrim($baseUrl, '/');
        $endpoint = preg_match('~/chat/completions$~i', $baseUrl)
            ? $baseUrl
            : $baseUrl . '/chat/completions';
        $providers[] = [
            'index' => $index,
            'endpoint' => $endpoint,
            'apiKey' => $apiKey,
            'model' => $model,
        ];
    }

    return ['providers' => $providers, 'errors' => $configurationErrors];
}

function aiConsultationRequest(array $provider, array $messages): array
{
    $payload = json_encode([
        'model' => $provider['model'],
        'messages' => $messages,
        'temperature' => 0.4,
        'max_tokens' => 1200,
    ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);

    if ($payload === false || !function_exists('curl_init')) {
        return ['retryable' => false, 'failure' => 'transport_unavailable'];
    }

    $curl = curl_init($provider['endpoint']);
    if ($curl === false) {
        return ['retryable' => true, 'failure' => 'connection'];
    }

    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $provider['apiKey'],
            'Content-Type: application/json',
            'Accept: application/json',
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT => 35,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_MAXREDIRS => 0,
    ]);

    $responseBody = '';
    $responseTooLarge = false;
    curl_setopt($curl, CURLOPT_WRITEFUNCTION, static function ($handle, string $chunk) use (&$responseBody, &$responseTooLarge): int {
        if (strlen($responseBody) + strlen($chunk) > 131072) {
            $responseTooLarge = true;
            return 0;
        }
        $responseBody .= $chunk;
        return strlen($chunk);
    });
    curl_exec($curl);
    $statusCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $curlError = curl_errno($curl);
    curl_close($curl);

    if ($curlError !== 0 || $responseTooLarge) {
        return ['retryable' => true, 'failure' => $responseTooLarge ? 'response_too_large' : 'connection'];
    }
    if (in_array($statusCode, [401, 402, 403, 408, 425, 429], true) || $statusCode >= 500) {
        return ['retryable' => true, 'failure' => 'http_status', 'statusCode' => $statusCode];
    }
    if ($statusCode < 200 || $statusCode >= 300) {
        return ['retryable' => false, 'failure' => 'http_status', 'statusCode' => $statusCode];
    }

    $decoded = json_decode($responseBody, true);
    $content = $decoded['choices'][0]['message']['content'] ?? null;
    if (!is_string($content) || trim($content) === '') {
        return ['retryable' => false, 'failure' => 'invalid_response', 'statusCode' => $statusCode];
    }

    return ['retryable' => false, 'answer' => trim($content)];
}

function aiConsultationGenerateAnswer(array $providers, array $messages): array
{
    foreach ($providers as $provider) {
        $result = aiConsultationRequest($provider, $messages);
        if (isset($result['answer'])) {
            return ['answer' => $result['answer']];
        }
        $logMessage = 'AI consultation provider ' . (int) $provider['index'] . ' failed: ' . $result['failure'];
        if (isset($result['statusCode'])) {
            $logMessage .= ' (HTTP ' . (int) $result['statusCode'] . ')';
        }
        error_log($logMessage);
        if (empty($result['retryable'])) {
            return ['error' => 'The AI provider could not complete this request. Please try again later.'];
        }
    }

    return ['error' => 'The AI service is temporarily unavailable. Please try again later.'];
}

function aiConsultationUsageCount(PDO $database, int $studentId, string $date): int
{
    $statement = $database->prepare(
        'SELECT requestCount FROM ai_consultation_daily_usage WHERE studentID = ? AND usageDate = ?'
    );
    $statement->execute([$studentId, $date]);
    return (int) ($statement->fetchColumn() ?: 0);
}

function aiConsultationReserveStudentRequest(PDO $database, int $studentId, string $date, int $limit): array
{
    $database->beginTransaction();
    try {
        $accessStatement = $database->prepare(
            'SELECT isRevoked FROM ai_consultation_access WHERE studentID = ? FOR UPDATE'
        );
        $accessStatement->execute([$studentId]);
        if ((int) $accessStatement->fetchColumn() === 1) {
            $database->commit();
            return ['allowed' => false, 'reason' => 'revoked', 'count' => 0];
        }

        $insertStatement = $database->prepare(
            'INSERT IGNORE INTO ai_consultation_daily_usage (studentID, usageDate, requestCount) VALUES (?, ?, 0)'
        );
        $insertStatement->execute([$studentId, $date]);

        $usageStatement = $database->prepare(
            'SELECT requestCount FROM ai_consultation_daily_usage WHERE studentID = ? AND usageDate = ? FOR UPDATE'
        );
        $usageStatement->execute([$studentId, $date]);
        $count = (int) $usageStatement->fetchColumn();
        if ($count >= $limit) {
            $database->commit();
            return ['allowed' => false, 'reason' => 'limit', 'count' => $count];
        }

        $updateStatement = $database->prepare(
            'UPDATE ai_consultation_daily_usage SET requestCount = requestCount + 1 WHERE studentID = ? AND usageDate = ?'
        );
        $updateStatement->execute([$studentId, $date]);
        $database->commit();

        return ['allowed' => true, 'reason' => '', 'count' => $count + 1];
    } catch (Throwable $exception) {
        if ($database->inTransaction()) {
            $database->rollBack();
        }
        throw $exception;
    }
}
