<?php
/**
 *  This file is part of the Simple S3 package.
 *
 * (c) Mauro Cassani<https://github.com/mauretto78>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 */

namespace Matecat\SimpleS3\Commands\Handlers;

use Aws\S3\Exception\S3Exception;
use Matecat\SimpleS3\Commands\CommandHandler;
use Matecat\SimpleS3\Helpers\File;

class GetItemsInABucket extends CommandHandler
{
    /**
     * Get the list of keys in a bucket.
     * If 'hydrate' parameter is set to true, an array of hydrated Aws\Result is returned instead.
     *
     * @param array $params
     *
     * @return array|mixed
     * @throws \Exception
     */
    public function handle($params = [])
    {
        $bucketName = $params['bucket'];

        try {
            $config = [
                'Bucket' => $bucketName,
            ];

            if (isset($params['prefix'])) {

                // add a final slash to prefix
                if (false === File::endsWith($params['prefix'], $this->client->getPrefixSeparator())) {
                    $params['prefix'] .= $this->client->getPrefixSeparator();
                }

                $config['Delimiter'] = (isset($params['delimiter'])) ? $params['delimiter'] : $this->client->getPrefixSeparator();
                $config['Prefix'] = $params['prefix'];
            }

            // 1. If 'exclude-cache' is set, return records always from S3
            if (isset($params['exclude-cache']) and true === $params['exclude-cache']) {
                return $this->returnItemsFromS3($bucketName, $config, (isset($params['hydrate'])) ? $params['hydrate'] : null);
            }

            // 2. If the cache is set and there is a prefix, return records from cache
            if ($this->client->hasCache() and isset($config['Prefix'])) {
                return $this->returnItemsFromCache($bucketName, $config, (isset($params['hydrate'])) ? $params['hydrate'] : null);
            }

            // 3. Otherwise, return records from S3
            return $this->returnItemsFromS3($bucketName, $config, (isset($params['hydrate'])) ? $params['hydrate'] : null);
        } catch (S3Exception $e) {
            if (null !== $this->commandHandlerLogger) {
                $this->commandHandlerLogger->logExceptionAndReturnFalse($e);
            }

            throw $e;
        }
    }

    /**
     * @param array $params
     *
     * @return bool
     */
    public function validateParams($params = [])
    {
        return (isset($params['bucket']));
    }

    /**
     * @param string $bucketName
     * @param array $config
     * @param null $hydrate
     *
     * @return array
     */
    protected function returnItemsFromCache($bucketName, $config, $hydrate = null)
    {
        $itemsFromCache = $this->client->getCache()->search($bucketName, $config['Prefix']);

        // no data was found, try to retrieve data from S3
        if (count($itemsFromCache) == 0) {
            return $this->returnItemsFromS3($bucketName, $config, $hydrate);
        }

        // no hydrate, simply return the array of keys stored in redis
        if (null == $hydrate) {
            if (null !== $this->commandHandlerLogger) {
                $this->commandHandlerLogger->log($this, sprintf('Files of \'%s\' bucket were successfully obtained from CACHE', $bucketName));
            }

            return $itemsFromCache;
        }

        // hydrate the key with the entire AWS\Result Object
        $items = [];
        foreach ($itemsFromCache as $key) {
            $version = null;
            $originalKey = $key;

            if (strpos($key, '<VERSION_ID:') !== false) {
                $v = explode('<VERSION_ID:', $key);
                $version = str_replace('>', '', $v[1]);
                $key = $v[0];
            }

            if ($this->client->hasEncoder()) {
                $key = $this->client->getEncoder()->decode($key);
            }

            $items[$originalKey] = $this->client->getItem(['bucket' => $bucketName, 'key' => $key, 'version' => $version]);
        }

        if (null !== $this->commandHandlerLogger) {
            $this->commandHandlerLogger->log($this, sprintf('Files of \'%s\' bucket were successfully obtained from CACHE', $bucketName));
        }

        return $items;
    }

    /**
     * @param string $bucketName
     * @param array $config
     *
     * @return array
     */
    protected function returnItemsFromS3($bucketName, $config, $hydrate = null)
    {
        if ($this->client->isBucketVersioned(['bucket' => $bucketName])) {
            return $this->returnVersionedItemsFromS3($bucketName, $config, $hydrate);
        }

        $resultPaginator = $this->client->getConn()->getPaginator('ListObjects', $config);
        $items = [];

        foreach ($resultPaginator as $result) {
            if (is_array($contents = $result->get('Contents'))) {
                for ($i = 0; $i < count($contents); $i++) {
                    $key = $contents[$i]['Key'];

                    if (false === File::endsWith($key, $this->client->getPrefixSeparator())) {
                        if ($this->client->hasEncoder()) {
                            $key = $this->client->getEncoder()->decode($key);
                        }

                        if (null != $hydrate and true === $hydrate) {
                            $items[$key] = $this->client->getItem(['bucket' => $bucketName, 'key' => $key]);
                        } else {
                            $items[] = $key;
                        }

                        // send to cache, just to be sure that S3 is syncronized with cache
                        if ($this->client->hasCache()) {
                            $this->client->getCache()->set($bucketName, $contents[$i]['Key'], $this->client->getItem(['bucket' => $bucketName, 'key' => $key]));
                        }
                    }
                }
            }
        }

        if (null !== $this->commandHandlerLogger) {
            $this->commandHandlerLogger->log($this, sprintf('Files were successfully obtained from \'%s\' bucket', $bucketName));
        }

        return $items;
    }

    /**
     * @param string $bucketName
     * @param array $config
     * @param null $hydrate
     *
     * @return array
     */
    protected function returnVersionedItemsFromS3($bucketName, $config, $hydrate = null)
    {
        $results = $this->client->getConn()->listObjectVersions($config);
        $items = [];

        if (false === isset($results['Versions'])) {
            return $items;
        }

        foreach ($results['Versions'] as $result) {
            $key = $result['Key'];
            $version = $result['VersionId'];

            if (false === File::endsWith($key, $this->client->getPrefixSeparator())) {
                if ($this->client->hasEncoder()) {
                    $key = $this->client->getEncoder()->decode($key);
                }

                $index = $key.'<VERSION_ID:'.$version.'>';

                if (null != $hydrate and true === $hydrate) {
                    $items[$index] = $this->client->getItem(['bucket' => $bucketName, 'key' => $key, 'version' => $version]);
                } else {
                    $items[] = $index;
                }

                // send to cache, just to be sure that S3 is syncronized with cache
                if ($this->client->hasCache()) {
                    $this->client->getCache()->set($bucketName, $result['Key'], $this->client->getItem(['bucket' => $bucketName, 'key' => $key, 'version' => $version]), $version);
                }
            }
        }

        if (null !== $this->commandHandlerLogger) {
            $this->commandHandlerLogger->log($this, sprintf('Files (versioned) were successfully obtained from \'%s\' bucket', $bucketName));
        }

        return $items;
    }
}
