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

namespace SimpleS3\Commands\Handlers;

use Aws\ResultInterface;
use Aws\S3\Exception\S3Exception;
use SimpleS3\Commands\CommandHandler;

class GetBucketLifeCycleConfiguration extends CommandHandler
{
    /**
     * @param array $params
     *
     * @return ResultInterface|mixed
     * @throws \Exception
     */
    public function handle($params = [])
    {
        $bucketName = $params['bucket'];

        try {
            $result = $this->client->getConn()->getBucketLifecycle([
                'Bucket' => $bucketName
            ]);

            $this->loggerWrapper->log(sprintf('LifeCycleConfiguration of \'%s\' bucket was successfully obtained', $bucketName));

            return $result;
        } catch (S3Exception $e) {
            $this->loggerWrapper->logExceptionAndContinue($e);
        }
    }

    /**
     * @param array $params
     *
     * @return bool
     */
    public function validateParams($params = [])
    {
        return isset($params['bucket']);
    }
}
