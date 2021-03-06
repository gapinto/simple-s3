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

use Aws\ResultInterface;
use Matecat\SimpleS3\Commands\CommandHandler;
use Matecat\SimpleS3\Components\Validators\S3ObjectSafeNameValidator;
use Matecat\SimpleS3\Components\Validators\S3StorageClassNameValidator;
use Matecat\SimpleS3\Exceptions\InvalidS3NameException;
use Matecat\SimpleS3\Helpers\File;
use Matecat\SimpleS3\Helpers\FilenameValidator;

class UploadItemFromBody extends CommandHandler
{
    /**
     * Upload a content to S3.
     * For a complete reference of put object see:
     * https://docs.aws.amazon.com/cli/latest/reference/s3api/put-object.html?highlight=put
     *
     * @param array $params
     *
     * @return bool
     * @throws \Exception
     */
    public function handle($params = [])
    {
        $bucketName = $params['bucket'];
        $keyName = $params['key'];
        $body = $params['body'];

        if (isset($params['bucket_check']) and true === $params['bucket_check']) {
            $this->client->createBucketIfItDoesNotExist(['bucket' => $bucketName]);
        }

        if (false === S3ObjectSafeNameValidator::isValid($keyName)) {
            throw new InvalidS3NameException(sprintf('%s is not a valid S3 object name. ['.implode(', ', S3ObjectSafeNameValidator::validate($keyName)).']', $keyName));
        }

        if ((isset($params['storage']) and false === S3StorageClassNameValidator::isValid($params['storage']))) {
            throw new \InvalidArgumentException(S3StorageClassNameValidator::validate($params['storage'])[0]);
        }

        if ($this->client->hasEncoder()) {
            $keyName = $this->client->getEncoder()->encode($keyName);
        }

        return $this->upload($bucketName, $keyName, $body, (isset($params['storage'])) ? $params['storage'] : null, (isset($params['meta'])) ? $params['meta'] : null);
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
            isset($params['key']) and
            isset($params['body'])
        );
    }

    /**
     * @param string $bucketName
     * @param string $keyName
     * @param string $body
     * @param null $storage
     * @param null $meta
     *
     * @return bool
     * @throws \Exception
     */
    private function upload($bucketName, $keyName, $body, $storage = null, $meta = null)
    {
        try {
            $config = [
                'Bucket' => $bucketName,
                'Key'    => $keyName,
                'Body'   => $body,
                'MetadataDirective' => 'REPLACE',
            ];

            if (null != $storage) {
                $config['StorageClass'] = $storage;
            }

            if (null != $meta) {
                $config['Metadata'] = $meta;
            }

            $config['Metadata']['original_name'] = File::getBaseName($keyName);

            $result = $this->client->getConn()->putObject($config);

            if (($result instanceof ResultInterface) and $result['@metadata']['statusCode'] === 200) {
                if (null !== $this->commandHandlerLogger) {
                    $this->commandHandlerLogger->log($this, sprintf('File \'%s\' was successfully uploaded in \'%s\' bucket', $keyName, $bucketName));
                }

                if (null == $storage and $this->client->hasCache()) {
                    $version = null;
                    if (isset($result['@metadata']['headers']['x-amz-version-id'])) {
                        $version = $result['@metadata']['headers']['x-amz-version-id'];
                    }

                    $this->client->getCache()->set($bucketName, $keyName, '', $version);
                }

                return true;
            }

            if (null !== $this->commandHandlerLogger) {
                $this->commandHandlerLogger->log($this, sprintf('Something went wrong during upload of file \'%s\' in \'%s\' bucket', $keyName, $bucketName), 'warning');
            }

            return false;
        } catch (\InvalidArgumentException $e) {
            if (null !== $this->commandHandlerLogger) {
                $this->commandHandlerLogger->logExceptionAndReturnFalse($e);
            }

            throw $e;
        }
    }
}
