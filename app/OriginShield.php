<?php

namespace App;

use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client;
use League\Flysystem\Adapter\Local;
use League\Flysystem\Filesystem;

class OriginShield
{
    private $cfg;
    private $local;
    private $remote;

    public function __construct()
    {
        $this->cfg = require __DIR__ . '/../config.php';

        if (!isset($this->cfg['cache_path'])) {
            $this->error();
        }
    }

    public function run()
    {
        if (!isset($_REQUEST['pz'])) {
            $this->ok();
        }

        list($zone, $file) = explode('/', $_REQUEST['pz'], 2);

        if (!isset($this->cfg['pull_zones'][$zone]) || is_null($file)) {
            $this->unauthorized();
        }

        $this->local = new Filesystem(
            new Local("{$this->cfg['cache_path']}/{$zone}")
        );

        if ($this->local->has($file)) {
            $metadata = $this->local->getMetadata($file);

            if ($metadata['type'] === 'file') {
                $this->sendfile("{$zone}/$file");
            }

            $this->notFound();
        }

        $this->remote = new S3Client($this->cfg['pull_zones'][$zone]);

        try {
            $s3 = $this->remote->getObject([
                'Bucket' => $this->cfg['pull_zones'][$zone]['bucket'],
                'Key' => $file,
            ]);
        } catch (S3Exception $e) {
            $this->notFound();
        }

        $this->local->write($file, $s3['Body']);
        $this->sendfile("{$zone}/$file");
    }

    private function sendfile($file)
    {
        header("X-Accel-Redirect: /protected/{$file}");
        exit();
    }

    private function ok()
    {
        header('HTTP/1.1 200 OK');
        exit();
    }

    private function error()
    {
        header('HTTP/1.1 500 Internal Server Error');
        exit();
    }

    private function unauthorized()
    {
        header('HTTP/1.1 401 Unauthorized');
        exit();
    }

    private function notFound()
    {
        header('HTTP/1.1 404 Not Found');
        exit();
    }
}
