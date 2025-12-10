<?php

class BmkgService {
    protected int $timeout = 15;
    protected string $cacheDir;
    protected int $cacheTTL = 1800; // 30 minutes
    protected array $admin4Lookup = [];
    protected bool $cacheEnabled = true;

    public function __construct(int $timeout = 15) {
        $this->timeout = $timeout;
        $this->cacheDir = __DIR__ . '/../storage/cache/bmkg';
        $this->ensureCacheDir();
        $this->loadAdmin4Lookup();
    }

    protected function ensureCacheDir() {
        if (!is_dir($this->cacheDir)) {
            @mkdir($this->cacheDir, 0777, true);
        }

        // Disable cache if folder is not writable
        if (!is_writable($this->cacheDir)) {
            $this->cacheEnabled = false;
        }
    }

    /**
     * Load ADM4 lookup from config/admin4_lookup.json
     */
    protected function loadAdmin4Lookup() {
        $path = __DIR__ . '/../config/admin4_lookup.json';

        if (file_exists($path)) {
            $json = file_get_contents($path);
            $this->admin4Lookup = json_decode($json, true) ?? [];
        }
    }

    // =============================
    // FETCH BMKG GENERIC
    // =============================
    public function fetch(?string $source_url = null, ?string $external_id = null): array {

        if ($source_url) {
            $url = $source_url;
        } else {
            if (!$external_id)
                throw new InvalidArgumentException("external_id or source_url required");

            $url = "https://data.bmkg.go.id/endpoint/example?station=" . urlencode($external_id);
        }

        // Caching only for prakiraan cuaca endpoint
        $cacheKey = null;
        if ($this->cacheEnabled && strpos($url, 'prakiraan-cuaca') !== false) {
            $cacheKey = md5($url);
            $cached = $this->cacheGet($cacheKey);
            if ($cached !== null) return $cached;
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'User-Agent: DesaverseFetcher/1.0'
            ]
        ]);

        $resp = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        // curl_close deprecated → tidak perlu ditutup

        if ($resp === false || $httpCode >= 400) {
            throw new RuntimeException("BMKG fetch error: HTTP {$httpCode}");
        }

        $data = json_decode($resp, true);

        // If JSON fails, try XML
        if (json_last_error() !== JSON_ERROR_NONE) {
            libxml_use_internal_errors(true);
            $xml = simplexml_load_string($resp);

            if ($xml === false) {
                throw new RuntimeException("BMKG parse error");
            }

            $data = json_decode(json_encode($xml), true);
        }

        if ($cacheKey !== null && $this->cacheEnabled)
            $this->cacheSet($cacheKey, $data, $this->cacheTTL);

        return $data;
    }

    // =============================
    // PUBLIC PRAKIRAAN CUACA
    // =============================
    public function fetchPrakiraanCuacaPublic(string $adm4): array {
        $url = "https://api.bmkg.go.id/publik/prakiraan-cuaca?adm4=" . urlencode($adm4);
        return $this->fetch($url);
    }


    // =============================
    // NOWCAST RSS / ALERT BMKG
    // =============================
    public function fetchNowcastAlerts(): array {
        $rss = "https://www.bmkg.go.id/alerts/nowcast/id/rss.xml";

        $xmlString = @file_get_contents($rss);
        if (!$xmlString) {
            throw new RuntimeException("Gagal mengambil RSS Nowcast BMKG");
        }

        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($xmlString);
        if (!$xml) {
            throw new RuntimeException("RSS XML tidak valid");
        }

        $items = [];
        foreach ($xml->channel->item as $item) {
            $items[] = [
                'title' => (string) $item->title,
                'link' => (string) $item->link,
                'description' => (string) $item->description,
                'pubDate' => (string) $item->pubDate,
                'author' => (string) $item->author
            ];
        }

        return [
            'alerts' => $items,
            'fetched_at' => date('Y-m-d H:i:s')
        ];
    }


    // =============================
    // RESOLVE DESA → ADM4 FROM JSON
    // =============================
    public function resolveAdmin4ForDesa(string $desa): ?string {
        if (empty($this->admin4Lookup)) {
            return null;
        }

        // Normalisasi input
        $desaNorm = strtolower(trim($desa));

        // Exact match (case-insensitive)
        foreach ($this->admin4Lookup as $key => $value) {
            if (strtolower($key) === $desaNorm) {
                return $value;
            }
        }

        // Partial fuzzy match
        foreach ($this->admin4Lookup as $key => $value) {
            if (strpos(strtolower($key), $desaNorm) !== false) {
                return $value;
            }
        }

        return null; // Not found
    }


    // =============================
    // CACHE FUNCTIONS
    // =============================
    protected function cacheGet(string $key) {
        if (!$this->cacheEnabled) return null;

        $path = $this->cacheDir . '/' . $key . '.json';
        if (!file_exists($path)) return null;

        $payload = json_decode(file_get_contents($path), true);

        if (!$payload || time() > $payload['expires_at']) return null;

        return $payload['value'];
    }

    protected function cacheSet(string $key, $value, int $ttl) {
        if (!$this->cacheEnabled) return;

        $path = $this->cacheDir . '/' . $key . '.json';
        $data = [
            'expires_at' => time() + $ttl,
            'value' => $value
        ];

        @file_put_contents($path, json_encode($data));
    }
}
