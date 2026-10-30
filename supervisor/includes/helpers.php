<?php
declare(strict_types=1);

function json_out(int $code, array $data): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

/* Keep only the fields we expect, with the right types. Schema value: 'int', 'str' or a list of allowed words. */
function clean_rows($rows, array $schema): array
{
    if (!is_array($rows) || count($rows) > 500) {
        throw new InvalidArgumentException('Invalid data.');
    }
    $out = [];
    foreach ($rows as $row) {
        if (!is_array($row)) {
            throw new InvalidArgumentException('Invalid data.');
        }
        $item = [];
        foreach ($schema as $field => $type) {
            $v = $row[$field] ?? '';
            if ($type === 'int') {
                $item[$field] = (int) $v;
            } elseif ($type === 'grade') {
                $item[$field] = ($v === '' || $v === null) ? '' : max(0, min(100, (int) $v));
            } elseif (is_array($type)) {
                if (!in_array($v, $type, true)) {
                    throw new InvalidArgumentException('Invalid value for ' . $field . '.');
                }
                $item[$field] = $v;
            } else {
                $item[$field] = mb_substr(trim((string) $v), 0, 2000);
            }
        }
        $out[] = $item;
    }
    return $out;
}
