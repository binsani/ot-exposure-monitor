<?php

declare(strict_types=1);

const AGENT_VERSION = '1.0.0';

function fail(string $message, int $code = 1): never
{
    fwrite(STDERR, '['.date(DATE_ATOM)."] {$message}\n");
    exit($code);
}

function request(string $method, string $url, string $token, ?array $payload = null, int $timeout = 10): array
{
    $handle = curl_init($url);
    $headers = ['Authorization: Bearer '.$token, 'Accept: application/json', 'User-Agent: OTEIM-Agent/'.AGENT_VERSION];
    curl_setopt_array($handle, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => $timeout, CURLOPT_CUSTOMREQUEST => $method, CURLOPT_HTTPHEADER => $headers]);
    if ($payload !== null) {
        $headers[] = 'Content-Type: application/json';
        curl_setopt($handle, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($handle, CURLOPT_POSTFIELDS, json_encode($payload, JSON_THROW_ON_ERROR));
    }
    $body = curl_exec($handle);
    $status = curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
    $error = curl_error($handle);
    curl_close($handle);
    if ($body === false || $status < 200 || $status >= 300) fail("Dashboard request failed ({$status}): ".($error ?: substr((string) $body, 0, 200)));

    return json_decode((string) $body, true, flags: JSON_THROW_ON_ERROR);
}

function probe(array $asset, array $override, int $timeout): array
{
    $ip = $asset['internal_ip'] ?? null;
    $port = (int) ($override['probe_port'] ?? $asset['probe_port'] ?? 0);
    $snapshot = array_filter(['internal_ip' => $ip, 'firmware_version' => $override['firmware_version'] ?? $asset['firmware_version'] ?? null], fn ($value) => $value !== null && $value !== '');
    $raw = [];
    if ($ip && $port > 0) {
        $started = microtime(true);
        $socket = @stream_socket_client("tcp://{$ip}:{$port}", $errorCode, $errorMessage, $timeout);
        $raw = ['probe_port' => $port, 'reachable' => (bool) $socket, 'latency_ms' => (int) round((microtime(true) - $started) * 1000)];
        if ($socket) {
            stream_set_timeout($socket, 1);
            $banner = fread($socket, 1024);
            fclose($socket);
            if ($banner !== false && $banner !== '') $snapshot['fingerprint'] = hash('sha256', $banner);
        } else {
            $raw['probe_error'] = $errorMessage;
        }
    }
    $configFile = $override['config_file'] ?? null;
    if ($configFile && is_file($configFile) && is_readable($configFile)) $snapshot['config_checksum'] = hash_file('sha256', $configFile);

    return ['asset_id' => $asset['id'], 'snapshot' => $snapshot, 'raw' => $raw];
}

$configPath = $argv[1] ?? __DIR__.'/config.json';
if (! is_file($configPath)) fail("Configuration not found: {$configPath}");
$config = json_decode((string) file_get_contents($configPath), true, flags: JSON_THROW_ON_ERROR);
$baseUrl = rtrim((string) ($config['dashboard_url'] ?? ''), '/');
$token = (string) ($config['token'] ?? '');
if (! str_starts_with($baseUrl, 'https://') && ! str_starts_with($baseUrl, 'http://localhost')) fail('dashboard_url must use HTTPS (localhost is allowed for testing).');
if (! str_starts_with($token, 'oteim_')) fail('A valid enrollment token is required.');
$timeout = max(1, min(30, (int) ($config['timeout_seconds'] ?? 5)));
$inventory = request('GET', $baseUrl.'/api/agent/v1/assets', $token, timeout: $timeout);
$readings = [];
foreach ($inventory['assets'] ?? [] as $asset) {
    $readings[] = probe($asset, $config['asset_overrides'][(string) $asset['id']] ?? [], $timeout);
}
$result = request('POST', $baseUrl.'/api/agent/v1/reports', $token, ['agent_version' => AGENT_VERSION, 'readings' => $readings], $timeout);
fwrite(STDOUT, '['.date(DATE_ATOM).'] Reported '.($result['processed'] ?? 0)." asset readings.\n");
