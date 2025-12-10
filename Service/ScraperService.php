<?php
class ScraperService {
    protected $timeout = 15;

    /**
     * Fetch a HTML page and parse values by CSS/XPath-like selectors or regex.
     * $config is an array describing how to extract fields, example:
     * [
     *   'temperature' => ['xpath' => "//span[@id='temp']", 'regex' => null],
     *   'humidity' => ['css' => '.hum', 'regex' => '/(\d+)%/']
     * ]
     */
    public function fetch(string $url, array $config = []): array {
        $html = $this->httpGet($url);
        if ($html === false) throw new RuntimeException("Failed to fetch HTML");

        $doc = new DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML($html);
        libxml_clear_errors();

        $xpath = new DOMXPath($doc);

        $result = [
            'temperature' => null,
            'humidity' => null,
            'wind_speed' => null,
            'reading_time' => date('Y-m-d H:i:s'),
            'raw_payload' => ['html' => substr($html, 0, 10000)] // simpan sebagian untuk audit
        ];

        foreach ($config as $field => $rule) {
            $value = null;
            if (!empty($rule['xpath'])) {
                $nodes = $xpath->query($rule['xpath']);
                if ($nodes->length > 0) {
                    $value = trim($nodes->item(0)->nodeValue);
                }
            } elseif (!empty($rule['css'])) {
                // simple CSS -> XPath conversion for basic selectors
                $xpathQuery = $this->cssToXpath($rule['css']);
                $nodes = $xpath->query($xpathQuery);
                if ($nodes->length > 0) $value = trim($nodes->item(0)->nodeValue);
            }

            if ($value === null && !empty($rule['regex']) && !empty($html)) {
                if (preg_match($rule['regex'], $html, $m)) {
                    $value = $m[1] ?? $m[0];
                }
            }

            if ($value !== null) {
                // optionally normalize numeric values
                if (!empty($rule['type']) && $rule['type'] === 'float') {
                    $value = floatval(preg_replace('/[^0-9\.\-]/','', $value));
                }
                $result[$field] = $value;
            }
        }

        return $result;
    }

    protected function httpGet($url) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTPHEADER => ['User-Agent: DesaverseScraper/1.0']
        ]);
        $resp = curl_exec($ch);
        $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($resp === false || $http >= 400) {
            throw new RuntimeException("HTTP GET failed: {$http} - {$err}");
        }
        return $resp;
    }

    protected function cssToXpath($css) {
        // VERY simple conversion for single class or id selectors (extend as needed)
        if (preg_match('/^\.(\w[\w\-]*)$/', $css, $m)) {
            return "//*[contains(concat(' ', normalize-space(@class), ' '), ' {$m[1]} ')]";
        }
        if (preg_match('/^\#(\w[\w\-]*)$/', $css, $m)) {
            return "//*[@id='{$m[1]}']";
        }
        // fallback: treat css as tag
        return "//{$css}";
    }
}
