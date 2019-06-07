<?php

namespace SimpleS3\Commands\Handlers;

use Aws\Exception\MultipartUploadException;
use SimpleS3\Commands\CommandHandler;

class Transfer extends CommandHandler
{
    /**
     * @param array $params
     *
     * @return bool
     * @throws \Exception
     */
    public function handle($params = [])
    {
        $dest = $params['dest'];
        $source = $params['source'];
        $options = (isset($params['options'])) ? $params['options'] : [];

        return $this->transfer($dest, $source, $options);
    }

    /**
     * @param array $params
     *
     * @return bool
     */
    public function validateParams($params = [])
    {
        return (
            isset($params['dest']) and
            isset($params['source'])
        );
    }

    /**
     * @param string $dest
     * @param string $source
     * @param array $options
     *
     * @return bool
     * @throws \Exception
     */
    private function transfer($dest, $source, $options = [])
    {
        try {
            $manager = new \Aws\S3\Transfer($this->client->getConn(), $source, $dest, $options);
            $manager->transfer();

            $this->loggerWrapper->log(sprintf('Files were successfully transfered from \'%s\' to \'%s\'', $source, $dest));

            return true;
        } catch (\RuntimeException $e) {
            $this->loggerWrapper->logExceptionOrContinue($e);
        }
    }
}