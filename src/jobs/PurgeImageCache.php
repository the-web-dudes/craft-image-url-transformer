<?php

namespace thewebdudes\craftimageurltransformer\jobs;

use Craft;
use craft\helpers\App;
use GuzzleHttp\Client;
use craft\queue\BaseJob;
use GuzzleHttp\RequestOptions;
use thewebdudes\craftimageurltransformer\ImageUrlTransformer as Plugin;

class PurgeImageCache extends BaseJob
{

    public array $files;

    public function execute($queue): void
    {
        $client = new Client();
        $settings = Plugin::getInstance()->getSettings();
        $zoneId = App::parseEnv($settings->zoneId);
        $apiKey = App::parseEnv($settings->apiKey);

        $response = $client->post(
            "https://api.cloudflare.com/client/v4/zones/{$zoneId}/purge_cache",
            [
                RequestOptions::HEADERS => [
                    'Authorization' => "Bearer $apiKey"
                ],
                RequestOptions::JSON => [
                    'files' => $this->files
                ]
            ]
        );

        if($response->getStatusCode() !== 200) {
            throw new \Exception($response->getBody());
        }
    }


    /**
     * {@inheritdoc}
     */
    protected function defaultDescription(): string
    {
        return Craft::t('image-url-transformer', "Purging urls for image");
    }
}