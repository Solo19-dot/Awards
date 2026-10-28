<?php
// Thin wrapper around Supabase's REST API (PostgREST). Server-side only.

function sb_request(string $method, string $table, array $query = [], $body = null, array $extraHeaders = []): array
{
    $url = rtrim((string)cfg('supabase_url'), '/') . '/rest/v1/' . $table;
    if ($query) {
        $url .= '?' . http_build_query($query);
    }
    $key = (string)cfg('supabase_service_key');
    $headers = array_merge([
        'apikey: ' . $key,
        'Authorization: Bearer ' . $key,
        'Content-Type: application/json',
        'Accept: application/json',
    ], $extraHeaders);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => 20,
    ]);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }
    $raw = curl_exec($ch);
    if ($raw === false) {
        $err = curl_error($ch);
        curl_close($ch);
        throw new RuntimeException('Could not reach the database: ' . $err);
    }
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $data = $raw === '' ? [] : json_decode($raw, true);
    if ($code >= 400) {
        $msg = is_array($data) ? ($data['message'] ?? $raw) : $raw;
        throw new RuntimeException('Database error (' . $code . '): ' . $msg);
    }
    return is_array($data) ? $data : [];
}

function sb_select(string $table, array $query = []): array
{
    return sb_request('GET', $table, $query);
}

function sb_insert(string $table, array $rows): array
{
    return sb_request('POST', $table, [], $rows, ['Prefer: return=representation']);
}

function sb_update(string $table, array $filters, array $row): array
{
    return sb_request('PATCH', $table, $filters, $row, ['Prefer: return=representation']);
}

function sb_delete(string $table, array $filters): array
{
    return sb_request('DELETE', $table, $filters, null, ['Prefer: return=representation']);
}

function sb_upsert(string $table, array $row, string $onConflict): array
{
    return sb_request('POST', $table, ['on_conflict' => $onConflict], $row,
        ['Prefer: resolution=merge-duplicates,return=representation']);
}

// Count rows without downloading them. Example: sb_count('payments', ['status' => 'eq.paid'])
function sb_count(string $table, array $filters = []): int
{
    $filters['select'] = 'id';
    $url = rtrim((string)cfg('supabase_url'), '/') . '/rest/v1/' . $table . '?' . http_build_query($filters);
    $key = (string)cfg('supabase_service_key');
    $range = '';

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_HTTPHEADER     => [
            'apikey: ' . $key,
            'Authorization: Bearer ' . $key,
            'Prefer: count=exact',
            'Range: 0-0',
        ],
        CURLOPT_HEADERFUNCTION => function ($ch, $line) use (&$range) {
            if (stripos($line, 'content-range:') === 0) {
                $range = trim(substr($line, 14));
            }
            return strlen($line);
        },
    ]);
    $raw = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($raw === false || $code >= 400) {
        throw new RuntimeException('Could not count rows in ' . $table);
    }
    $pos = strrpos($range, '/');
    return $pos === false ? 0 : (int)substr($range, $pos + 1);
}
