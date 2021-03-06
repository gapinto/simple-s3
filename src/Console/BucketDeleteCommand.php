<?php

namespace Matecat\SimpleS3\Console;

use Matecat\SimpleS3\Client;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class BucketDeleteCommand extends Command
{
    /**
     * @var Client
     */
    private $s3Client;

    /**
     * CacheFlushCommand constructor.
     *
     * @param Client $s3Client
     * @param null   $name
     */
    public function __construct(Client $s3Client, $name = null)
    {
        parent::__construct($name);

        $this->s3Client = $s3Client;
    }

    protected function configure()
    {
        $this
            ->setName('ss3:bucket:delete')
            ->setDescription('Deletes a bucket.')
            ->setHelp('This command deletes a bucket on S3.')
            ->addArgument('bucket', InputArgument::REQUIRED, 'The name of the bucket')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $bucket = $input->getArgument('bucket');
        $io = new SymfonyStyle($input, $output);

        try {
            if (true === $this->s3Client->deleteBucket(['bucket' => $bucket])) {
                $io->success('The bucket was successfully deleted');
            } else {
                $io->error('There was an error in deleting bucket');
            }
        } catch (\Exception $e) {
            $io->error($e->getMessage());
        }
    }
}
