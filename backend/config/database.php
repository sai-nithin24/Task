<?php
declare(strict_types=1);

/**
 * FirestoreClient — lightweight Firestore REST API client.
 *
 * Uses the Firebase Firestore REST API (no SDK required).
 * Authenticates via a Google Service Account JSON key using JWT (RS256).
 *
 * Collections used:
 *   users/          — user profiles
 *   projects/       — projects (field: user_id)
 *   tasks/          — tasks (fields: project_id, is_deleted)
 *   activity_logs/  — append-only activity log
 */
class FirestoreClient
{
    private static ?FirestoreClient $instance = null;

    private string $projectId;
    private string $baseUrl;
    private string $accessToken = '';
    private int    $tokenExpiry  = 0;

    // Service account credentials (loaded from env)
    private string $saEmail;
    private string $saPrivateKey;

    private function __construct()
    {
        $this->projectId   = $_ENV['FIREBASE_PROJECT_ID'] ?? '';
        $this->saEmail     = $_ENV['FIREBASE_CLIENT_EMAIL'] ?? '';
        $this->saPrivateKey = $_ENV['FIREBASE_PRIVATE_KEY'] ?? '';

        // Render stores the key with literal \n — convert to real newlines
        $this->saPrivateKey = str_replace('\\n', "\n", $this->saPrivateKey);

        if (!$this->projectId || !$this->saEmail || !$this->saPrivateKey) {
            throw new RuntimeException('Firebase credentials are not configured.', 500);
        }

        $this->baseUrl = "https://firestore.googleapis.com/v1/projects/{$this->projectId}/databases/(default)/documents";
    }

    public static function getInstance(): FirestoreClient
    {
        if (self::$instance === null) {
            self::$instance = new FirestoreClient();
        }
        return self::$instance;
    }

    // ── Token ────────────────────────────────────────────────

    private function getAccessToken(): string
    {
        // Reuse token if still valid (with 60s buffer)
        if ($this->accessToken && time() < $this->tokenExpiry - 60) {
            return $this->accessToken;
        }

        $now    = time();
        $expiry = $now + 3600;
        $scope  = 'https://www.googleapis.com/auth/datastore';

        $header  = $this->base64url(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $payload = $this->base64url(json_encode([
            'iss'   => $this->saEmail,
            'scope' => $scope,
            'aud'   => 'https://oauth2.googleapis.com/token',
            'iat'   => $now,
            'exp'   => $expiry,
        ]));

        $signingInput = "{$header}.{$payload}";
        $signature    = '';

        $key = openssl_pkey_get_private($this->saPrivateKey);
        if (!$key) {
            throw new RuntimeException('Failed to load Firebase private key.', 500);
        }
        openssl_sign($signingInput, $signature, $key, OPENSSL_ALGO_SHA256);

        $jwt = "{$signingInput}." . $this->base64url($signature);

        // Exchange JWT for access token
        $response = $this->httpPost('https://oauth2.googleapis.com/token', http_build_query([
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion'  => $jwt,
        ]), 'application/x-www-form-urlencoded');

        if (empty($response['access_token'])) {
            throw new RuntimeException('Failed to obtain Firebase access token.', 500);
        }

        $this->accessToken = $response['access_token'];
        $this->tokenExpiry = $expiry;

        return $this->accessToken;
    }

    // ── Firestore REST operations ────────────────────────────

    /**
     * Get a single document.
     * Returns the decoded document fields as a plain PHP array, or null if not found.
     */
    public function getDocument(string $collection, string $docId): ?array
    {
        $url  = "{$this->baseUrl}/{$collection}/{$docId}";
        $resp = $this->request('GET', $url);
        if (isset($resp['error'])) return null;
        return $this->decodeDocument($resp);
    }

    /**
     * Query a collection with optional filters, ordering, and limit.
     *
     * $filters = [['field', 'op', 'value'], ...]
     * $orderBy = [['field', 'ASCENDING'|'DESCENDING'], ...]
     */
    public function query(
        string $collection,
        array  $filters  = [],
        array  $orderBy  = [],
        int    $limit    = 500
    ): array {
        $url = "https://firestore.googleapis.com/v1/projects/{$this->projectId}/databases/(default)/documents:runQuery";

        $structuredQuery = [
            'from'  => [['collectionId' => $collection]],
            'limit' => $limit,
        ];

        if ($filters) {
            $firestoreFilters = [];
            foreach ($filters as [$field, $op, $value]) {
                $firestoreFilters[] = [
                    'fieldFilter' => [
                        'field'  => ['fieldPath' => $field],
                        'op'     => $this->mapOp($op),
                        'value'  => $this->encodeValue($value),
                    ],
                ];
            }

            $structuredQuery['where'] = count($firestoreFilters) === 1
                ? $firestoreFilters[0]
                : ['compositeFilter' => ['op' => 'AND', 'filters' => $firestoreFilters]];
        }

        if ($orderBy) {
            $structuredQuery['orderBy'] = array_map(fn($o) => [
                'field'     => ['fieldPath' => $o[0]],
                'direction' => $o[1] ?? 'ASCENDING',
            ], $orderBy);
        }

        $resp = $this->request('POST', $url, ['structuredQuery' => $structuredQuery]);

        $results = [];
        foreach ((array)$resp as $item) {
            if (isset($item['document'])) {
                $doc = $this->decodeDocument($item['document']);
                if ($doc !== null) {
                    $results[] = $doc;
                }
            }
        }
        return $results;
    }

    /**
     * Create a new document with an auto-generated ID.
     * Returns the new document ID.
     */
    public function addDocument(string $collection, array $data): string
    {
        $url  = "{$this->baseUrl}/{$collection}";
        $body = ['fields' => $this->encodeFields($data)];
        $resp = $this->request('POST', $url, $body);

        if (isset($resp['error'])) {
            throw new RuntimeException('Firestore add failed: ' . json_encode($resp['error']), 500);
        }

        // Extract document ID from the name field: .../documents/collection/ID
        $name = $resp['name'] ?? '';
        return basename($name);
    }

    /**
     * Create or overwrite a document with a known ID.
     */
    public function setDocument(string $collection, string $docId, array $data): void
    {
        $url  = "{$this->baseUrl}/{$collection}/{$docId}";
        $body = ['fields' => $this->encodeFields($data)];
        $resp = $this->request('PATCH', $url, $body);

        if (isset($resp['error'])) {
            throw new RuntimeException('Firestore set failed: ' . json_encode($resp['error']), 500);
        }
    }

    /**
     * Update specific fields in a document (partial update).
     * Only the provided fields are updated; others are left unchanged.
     */
    public function updateDocument(string $collection, string $docId, array $data): void
    {
        $fieldPaths = implode('&', array_map(
            fn($k) => 'updateMask.fieldPaths=' . urlencode($k),
            array_keys($data)
        ));
        $url  = "{$this->baseUrl}/{$collection}/{$docId}?{$fieldPaths}";
        $body = ['fields' => $this->encodeFields($data)];
        $resp = $this->request('PATCH', $url, $body);

        if (isset($resp['error'])) {
            throw new RuntimeException('Firestore update failed: ' . json_encode($resp['error']), 500);
        }
    }

    /**
     * Delete a document.
     */
    public function deleteDocument(string $collection, string $docId): void
    {
        $url = "{$this->baseUrl}/{$collection}/{$docId}";
        $this->request('DELETE', $url);
    }

    // ── Value encoding / decoding ────────────────────────────

    /** Encode a PHP array of fields to Firestore field format. */
    private function encodeFields(array $data): array
    {
        $fields = [];
        foreach ($data as $key => $value) {
            $fields[$key] = $this->encodeValue($value);
        }
        return $fields;
    }

    /** Encode a single PHP value to Firestore typed value. */
    public function encodeValue(mixed $value): array
    {
        if ($value === null)             return ['nullValue'    => null];
        if (is_bool($value))            return ['booleanValue' => $value];
        if (is_int($value))             return ['integerValue'  => (string)$value];
        if (is_float($value))           return ['doubleValue'   => $value];
        if (is_array($value)) {
            // Associative → map, sequential → array
            if (array_keys($value) !== range(0, count($value) - 1)) {
                return ['mapValue' => ['fields' => $this->encodeFields($value)]];
            }
            return ['arrayValue' => ['values' => array_map([$this, 'encodeValue'], $value)]];
        }
        return ['stringValue' => (string)$value];
    }

    /** Decode a Firestore document (with name + fields) to a plain PHP array. */
    private function decodeDocument(array $doc): ?array
    {
        if (!isset($doc['fields'])) return null;

        $data = [];
        // Extract the document ID from the name
        if (isset($doc['name'])) {
            $data['id'] = basename($doc['name']);
        }
        foreach ($doc['fields'] as $key => $typedValue) {
            $data[$key] = $this->decodeValue($typedValue);
        }
        return $data;
    }

    /** Decode a Firestore typed value to a plain PHP value. */
    private function decodeValue(array $typedValue): mixed
    {
        if (array_key_exists('nullValue', $typedValue))    return null;
        if (isset($typedValue['booleanValue']))            return (bool)$typedValue['booleanValue'];
        if (isset($typedValue['integerValue']))            return (int)$typedValue['integerValue'];
        if (isset($typedValue['doubleValue']))             return (float)$typedValue['doubleValue'];
        if (isset($typedValue['stringValue']))             return (string)$typedValue['stringValue'];
        if (isset($typedValue['timestampValue']))          return (string)$typedValue['timestampValue'];
        if (isset($typedValue['mapValue']['fields'])) {
            $map = [];
            foreach ($typedValue['mapValue']['fields'] as $k => $v) {
                $map[$k] = $this->decodeValue($v);
            }
            return $map;
        }
        if (isset($typedValue['arrayValue']['values'])) {
            return array_map([$this, 'decodeValue'], $typedValue['arrayValue']['values']);
        }
        return null;
    }

    /** Map SQL-style operator strings to Firestore operator names. */
    private function mapOp(string $op): string
    {
        return match($op) {
            '=='        => 'EQUAL',
            '!='        => 'NOT_EQUAL',
            '<'         => 'LESS_THAN',
            '<='        => 'LESS_THAN_OR_EQUAL',
            '>'         => 'GREATER_THAN',
            '>='        => 'GREATER_THAN_OR_EQUAL',
            'array-contains' => 'ARRAY_CONTAINS',
            default     => strtoupper($op),
        };
    }

    // ── HTTP helpers ─────────────────────────────────────────

    private function request(string $method, string $url, ?array $body = null): array
    {
        $token   = $this->getAccessToken();
        $headers = [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
            'Accept: application/json',
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }

        $raw  = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($method === 'DELETE') return [];
        if (!$raw)               return [];

        return json_decode($raw, true) ?? [];
    }

    private function httpPost(string $url, string $body, string $contentType): array
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: {$contentType}"]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $raw = curl_exec($ch);
        curl_close($ch);
        return json_decode($raw ?: '{}', true) ?? [];
    }

    private function base64url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    // Prevent direct instantiation / cloning
    private function __clone() {}
}
