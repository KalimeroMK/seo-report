<?php

declare(strict_types=1);

namespace KalimeroMK\SeoReport\Extractors;

use GuzzleHttp\Client as HttpClient;
use KalimeroMK\SeoReport\Config\SeoReportConfig;

final class DomainDataExtractor
{
    /**
     * @return array{
     *     server_ip: string|null,
     *     dns_servers: array<int, string>,
     *     dmarc_record: string|null,
     *     spf_record: string|null,
     *     ssl_certificate: array{valid: bool, valid_from: string, valid_to: string, issuer_cn: string|null, subject_cn: string|null}|null,
     *     reverse_dns: string|null,
     *     llms_txt_url: string|null
     * }
     */
    public function extract(string $baseUrl, string $hostStr, SeoReportConfig $config, ?HttpClient $httpClient = null): array
    {
        $serverIp = null;
        $dnsServers = [];
        $dmarcRecord = null;
        $spfRecord = null;
        if ($hostStr !== '') {
            $serverIp = gethostbyname($hostStr);
            if ($serverIp === $hostStr) {
                $serverIp = null;
            }
            $nsRecords = @dns_get_record($hostStr, DNS_NS);
            if (is_array($nsRecords)) {
                foreach ($nsRecords as $r) {
                    if (isset($r['target'])) {
                        $dnsServers[] = $r['target'];
                    }
                }
            }
            $dmarcRecords = @dns_get_record('_dmarc.' . $hostStr, DNS_TXT);
            if (is_array($dmarcRecords)) {
                foreach ($dmarcRecords as $r) {
                    if (isset($r['txt']) && preg_match('/v=DMARC1/i', $r['txt'])) {
                        $dmarcRecord = $r['txt'];
                        break;
                    }
                }
            }
            $txtRecords = @dns_get_record($hostStr, DNS_TXT);
            if (is_array($txtRecords)) {
                foreach ($txtRecords as $r) {
                    if (isset($r['txt']) && preg_match('/v=spf1/i', $r['txt'])) {
                        $spfRecord = $r['txt'];
                        break;
                    }
                }
            }
        }

        $sslCertificate = null;
        if ($hostStr !== '' && str_starts_with($baseUrl, 'https://')) {
            $sslCertificate = $this->fetchSslCertificate($hostStr, $config);
        }

        $reverseDns = null;
        if ($serverIp !== null) {
            $ptr = @gethostbyaddr($serverIp);
            if ($ptr !== false && $ptr !== $serverIp) {
                $reverseDns = $ptr;
            }
        }

        $llmsTxtUrl = null;
        if ($hostStr !== '') {
            $scheme = parse_url($baseUrl, PHP_URL_SCHEME) ?: 'https';
            $llmsUrl = $scheme . '://' . $hostStr . '/llms.txt';
            $client = $httpClient ?? new HttpClient();
            $proxy = $config->getRequestProxy();
            $proxyOpt = $proxy !== null ? ['http' => $proxy, 'https' => $proxy] : [];
            $opts = ['timeout' => 3, 'proxy' => $proxyOpt, 'headers' => ['User-Agent' => $config->getRequestUserAgent()], 'http_errors' => false];
            try {
                $headResp = $client->head($llmsUrl, $opts);
                if ($headResp->getStatusCode() === 200) {
                    $llmsTxtUrl = $llmsUrl;
                }
            } catch (\Exception) {
                try {
                    $resp = $client->get($llmsUrl, $opts);
                    if ($resp->getStatusCode() === 200) {
                        $llmsTxtUrl = $llmsUrl;
                    }
                } catch (\Exception) {
                    // ignore
                }
            }
        }

        return [
            'server_ip' => $serverIp,
            'dns_servers' => $dnsServers,
            'dmarc_record' => $dmarcRecord,
            'spf_record' => $spfRecord,
            'ssl_certificate' => $sslCertificate,
            'reverse_dns' => $reverseDns,
            'llms_txt_url' => $llmsTxtUrl,
        ];
    }

    /**
     * @return array{valid: bool, valid_from: string, valid_to: string, issuer_cn: string|null, subject_cn: string|null}|null
     */
    private function fetchSslCertificate(string $host, SeoReportConfig $config): ?array
    {
        $timeout = min($config->getRequestTimeout(), 10);
        $ctx = stream_context_create([
            'ssl' => [
                'capture_peer_cert' => true,
                'verify_peer' => false,
                'SNI_enabled' => true,
            ],
        ]);
        $socket = @stream_socket_client(
            'ssl://' . $host . ':443',
            $errno,
            $errstr,
            $timeout,
            STREAM_CLIENT_CONNECT,
            $ctx
        );
        if ($socket === false) {
            return null;
        }
        $params = stream_context_get_params($socket);
        fclose($socket);
        if (!isset($params['options']['ssl']['peer_certificate'])) {
            return null;
        }

        $cert = openssl_x509_parse($params['options']['ssl']['peer_certificate']);
        if ($cert === false) {
            return null;
        }

        $validFrom = date(DATE_ATOM, $cert['validFrom_time_t'] ?? 0);
        $validTo = date(DATE_ATOM, $cert['validTo_time_t'] ?? 0);
        $now = time();
        $valid = isset($cert['validFrom_time_t'], $cert['validTo_time_t'])
            && $cert['validFrom_time_t'] <= $now
            && $cert['validTo_time_t'] >= $now;

        return [
            'valid' => $valid,
            'valid_from' => $validFrom,
            'valid_to' => $validTo,
            'issuer_cn' => $cert['issuer']['CN'] ?? null,
            'subject_cn' => $cert['subject']['CN'] ?? null,
        ];
    }
}
