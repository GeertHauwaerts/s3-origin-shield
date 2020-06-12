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
    private $pullzone;
    private $remote;
    private $request;

    public function __construct()
    {
        $this->cfg = require __DIR__ . '/../config.php';

        if (!isset($this->cfg['cache_path'])) {
            $this->error();
        }

        $this->setRequest();
    }

    public function run()
    {
        if (!$this->pullzone) {
            $this->ok();
        }

        if (!isset($this->cfg['pull_zones'][$this->pullzone]) || !$this->request) {
            $this->unauthorized();
        }

        $this->local = new Filesystem(
            new Local("{$this->cfg['cache_path']}/{$this->pullzone}")
        );

        if ($this->local->has($this->request)) {
            $metadata = $this->local->getMetadata($this->request);

            if ($metadata['type'] === 'file') {
                $this->sendfile("{$this->pullzone}/{$this->request}");
            }

            $this->notFound();
        }

        $this->remote = new S3Client($this->cfg['pull_zones'][$this->pullzone]);

        try {
            $s3 = $this->remote->getObject([
                'Bucket' => $this->cfg['pull_zones'][$this->pullzone]['bucket'],
                'Key' => $this->request,
            ]);
        } catch (S3Exception $e) {
            $this->notFound();
        }

        $this->local->write($this->request, $s3['Body']);
        $this->sendfile("{$this->pullzone}/{$this->request}");
    }

    private function setRequest()
    {
        $this->pullzone = false;
        $this->request = false;

        if (!isset($_SERVER['REQUEST_URI']) || empty($_SERVER['REQUEST_URI'])) {
            return;
        }

        list(, $this->pullzone, $this->request) = explode('/', $_SERVER['REQUEST_URI'], 3) + [false, false, false];

        if (strpos($this->request, '?') !== false) {
            $this->request = strstr($this->request, '?', true);
        }
    }

    private function sendfile($file)
    {
        header('Content-Type: ');
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
