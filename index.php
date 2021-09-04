<?php

require __DIR__ . '/vendor/autoload.php';

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

define('DOMAIN', getenv('DOMAIN'));
define('ZONE_ID', getenv('ZONE_ID'));
define('API_TOKEN', getenv('API_TOKEN'));
define('INTERVAL_TIME', getenv('INTERVAL_TIME') ?? 1800);

const CONTENT_TYPE = 'content-type';
const APPLICATION_JSON = 'application/json';
const AUTHORIZATION = 'authorization';

const IPINFO_ENDPOINT = 'http://ipinfo.io/ip';
const CLOUDFLARE_ENDPOINT = 'https://api.cloudflare.com/client/v4';

const GET = 'GET';
const PUT = 'PUT';

const HEADERS = [
    CONTENT_TYPE => APPLICATION_JSON,
    AUTHORIZATION => 'Bearer ' . API_TOKEN,
];

function fetch($method, $url, $options)
{
    $isJSON = false;

    try {
        $client = new Client();
        $contentType = '';
        $response = $client->request($method, $url, $options);

        foreach ($response->getHeaders() as $name => $values) {
            if (CONTENT_TYPE === strtolower($name)) {
                $contentType = $values;
                break;
            }
        }

        $types = explode('; ', $contentType[0]);
        foreach ($types as $type) {
            if (APPLICATION_JSON === strtolower($type)) {
                $isJSON = true;
                break;
            }
        }
        unset($types);
        unset($contentType);

        if ($isJSON) {
            $result = json_decode($response->getBody()->getContents(), true);
        } else {
            $result = $response->getBody()->getContents();
        }

        return $result;
    } catch (GuzzleException $e) {
        echo $e->getMessage(), PHP_EOL;
        exit(255);
    }
}

function getCurrentIp()
{
    return fetch(GET, IPINFO_ENDPOINT, []);
}

function updateDNSRecord($ip)
{
    $id = listDNSRecords();
    $url = CLOUDFLARE_ENDPOINT . '/zones/' . ZONE_ID . '/dns_records/' . $id;
    $options = [
        'headers' => HEADERS,
        'json' => [
            'type' => 'A',
            'name' => DOMAIN,
            'content' => $ip,
            'ttl', '1',
            'proxied' => false,
        ],
    ];

    $response = fetch(PUT, $url, $options);

    if ($response['success']) {
        echo 'update', PHP_EOL;
    } else {
        echo __FUNCTION__ . ' error', PHP_EOL;
    }
}

function listDNSRecords()
{
    $url = CLOUDFLARE_ENDPOINT . '/zones/' . ZONE_ID . '/dns_records';
    $options = [
        'headers' => HEADERS,
        'query' => [
            'type' => 'A',
        ],
    ];

    $response = fetch(GET, $url, $options);
    if (!$response['success']) {
        echo __FUNCTION__ . ' error', PHP_EOL;
    }
    $id = '';
    foreach ($response['result'] as $result) {
        if (DOMAIN === $result['name']) {
            $id = $result['id'];
            break;
        }
    }
    return $id;
}

function main()
{
    if (!DOMAIN || !ZONE_ID || !API_TOKEN) {
        echo 'must set `DOMAIN`, `ZONE_ID`, `API_TOKEN` environment variables', PHP_EOL;
        exit(255);
    }

    $oldIp = '';
    $currentIP = getCurrentIp();

    while (true) {
        if ($oldIp !== $currentIP) {
            updateDNSRecord($currentIP);
            $oldIp = $currentIP;
        }
        sleep(INTERVAL_TIME);
    }
}

main();
