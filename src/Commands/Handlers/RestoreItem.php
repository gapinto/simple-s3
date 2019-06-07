<?php

namespace SimpleS3\Commands\Handlers;

use Aws\ResultInterface;
use Psr\Http\Message\UriInterface;
use SimpleS3\Commands\CommandHandler;

class RestoreItem extends CommandHandler
{
    /**
     * Send a basic restore request for an archived copy of an object back into Amazon S3
     *
     * For a complete reference:
     * https://docs.aws.amazon.com/cli/latest/reference/s3api/restore-object.html
     *
     * @param array $params
     *
     * @return mixed|UriInterface
     * @throws \Exception
     */
    public function handle($params = [])
    {
        $bucketName = $params['bucket'];
        $keyName = $params['key'];
        $days =(isset($params['days'])) ? $params['days'] : 5;
        $tier = (isset($params['tier'])) ? $params['tier'] : 'Expedited';

        $allowedTiers = [
            'Bulk',
            'Expedited',
            'Standard',
        ];

        if ($tier and !in_array($tier, $allowedTiers)) {
            throw new \InvalidArgumentException(sprintf('%s is not a valid tier value. Allowed values are: ['.implode(',', $allowedTiers).']', $tier));
        }

        try {
            $request = $this->client->getConn()->restoreObject([
                'Bucket' => $bucketName,
                'Key' => $keyName,
                'RestoreRequest' => [
                    'Days'       => $days,
                    'GlacierJobParameters' => [
                        'Tier'  => $tier,
                    ],
                ],
            ]);

            if (($request instanceof ResultInterface) and $request['@metadata']['statusCode'] === 202) {
                $this->loggerWrapper->log(sprintf('A request for restore \'%s\' item in \'%s\' bucket was successfully sended', $keyName, $bucketName));

                return true;
            }

            $this->loggerWrapper->log(sprintf('Something went wrong during sending restore questo for \'%s\' item in \'%s\' bucket', $keyName, $bucketName), 'warning');

            return false;
        } catch (\Exception $e) {
            $this->loggerWrapper->logExceptionOrContinue($e);
        }
    }

    /**
     * @param array $params
     *
     * @return bool
     */
    public function validateParams($params = [])
    {
        return (
            isset($params['bucket']) and
            isset($params['key'])
        );
    }
}