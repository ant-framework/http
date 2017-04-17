<?php
namespace Ant\Http;

use RuntimeException;
use InvalidArgumentException;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UploadedFileInterface;

/**
 * Todo 简化
 *
 * Class UploadedFile
 * @package Ant\Http
 */
class UploadedFile implements UploadedFileInterface
{
    /**
     * @var int[]
     */
    protected static $errors = [
        UPLOAD_ERR_OK,
        UPLOAD_ERR_INI_SIZE,
        UPLOAD_ERR_FORM_SIZE,
        UPLOAD_ERR_PARTIAL,
        UPLOAD_ERR_NO_FILE,
        UPLOAD_ERR_NO_TMP_DIR,
        UPLOAD_ERR_CANT_WRITE,
        UPLOAD_ERR_EXTENSION,
    ];


    /**
     * @var string
     */
    protected $clientFilename;

    /**
     * @var string
     */
    protected $clientMediaType;

    /**
     * @var int
     */
    protected $error;

    /**
     * @var null|string
     */
    protected $file;

    /**
     * @var bool
     */
    protected $moved = false;

    /**
     * @var int
     */
    protected $size;

    /**
     * @var StreamInterface|null
     */
    protected $stream;

    /**
     * 加载上传文件,仅限POST上传
     *
     * @param $uploadedFiles
     * @return array
     */
    public static function parseUploadedFiles($uploadedFiles)
    {
        $parsed = [];
        foreach ($uploadedFiles as $field => $uploadedFile) {
            if (!isset($uploadedFile['error'])) {
                continue;
            }

            $parsed[$field] = [];
            if (is_array($uploadedFile['error'])) {
                // 详见手册 [PHP多文件上传]
                $subArray = [];
                $count = count($uploadedFile['error']);
                $fileKey = array_keys($uploadedFile);
                for ($fileIdx = 0;$fileIdx < $count;$fileIdx++) {
                    foreach ($fileKey as $key) {
                        $subArray[$fileIdx][$key] = $uploadedFile[$key][$fileIdx];
                    }
                }
                $parsed[$field] = static::parseUploadedFiles($subArray);
            } else {
                $parsed[$field] = new static(
                    $uploadedFile['tmp_name'],
                    $uploadedFile['size'],
                    $uploadedFile['error'],
                    $uploadedFile['name'],
                    $uploadedFile['type']
                );
            }
        }

        return $parsed;
    }

    public function __construct(
        $streamOrFile,
        $size,
        $errorStatus,
        $clientFilename = null,
        $clientMediaType = null
    ) {
        $this->setError($errorStatus);
        $this->setSize($size);
        $this->setClientFilename($clientFilename);
        $this->setClientMediaType($clientMediaType);

        if (!$this->isError()) {
            $this->setStreamOrFile($streamOrFile);
        }
    }


    /**
     * Depending on the value set file or stream variable
     *
     * @param mixed $streamOrFile
     * @throws InvalidArgumentException
     */
    protected function setStreamOrFile($streamOrFile)
    {
        if (is_string($streamOrFile)) {
            $this->file = $streamOrFile;
        } elseif (is_resource($streamOrFile)) {
            $this->stream = new Stream($streamOrFile);
        } elseif ($streamOrFile instanceof StreamInterface) {
            $this->stream = $streamOrFile;
        } else {
            throw new InvalidArgumentException(
                'Invalid stream or file provided for UploadedFile'
            );
        }
    }

    /**
     * @param int $error
     * @throws InvalidArgumentException
     */
    protected function setError($error)
    {
        if (false === is_int($error)) {
            throw new InvalidArgumentException(
                'Upload file error status must be an integer'
            );
        }

        if (false === in_array($error, UploadedFile::$errors)) {
            throw new InvalidArgumentException(
                'Invalid error status for UploadedFile'
            );
        }

        $this->error = $error;
    }

    /**
     * @param int $size
     * @throws InvalidArgumentException
     */
    protected function setSize($size)
    {
        if (false === is_int($size)) {
            throw new InvalidArgumentException(
                'Upload file size must be an integer'
            );
        }

        $this->size = $size;
    }

    /**
     * @param mixed $param
     * @return boolean
     */
    protected function isStringOrNull($param)
    {
        return in_array(gettype($param), ['string', 'NULL']);
    }

    /**
     * @param mixed $param
     * @return boolean
     */
    protected function isStringNotEmpty($param)
    {
        return is_string($param) && false === empty($param);
    }

    /**
     * @param string|null $clientFilename
     * @throws InvalidArgumentException
     */
    protected function setClientFilename($clientFilename)
    {
        if (false === $this->isStringOrNull($clientFilename)) {
            throw new InvalidArgumentException(
                'Upload file client filename must be a string or null'
            );
        }

        $this->clientFilename = $clientFilename;
    }

    /**
     * @param string|null $clientMediaType
     * @throws InvalidArgumentException
     */
    protected function setClientMediaType($clientMediaType)
    {
        if (false === $this->isStringOrNull($clientMediaType)) {
            throw new InvalidArgumentException(
                'Upload file client media type must be a string or null'
            );
        }

        $this->clientMediaType = $clientMediaType;
    }

    /**
     * @return bool
     */
    public function isError()
    {
        return $this->getError() !== UPLOAD_ERR_OK;
    }

    /**
     * @return boolean
     */
    protected function isMoved()
    {
        return $this->moved;
    }

    /**
     * @throws RuntimeException if is moved or not ok
     */
    protected function validateActive()
    {
        if (true === $this->isError()) {
            throw new RuntimeException('Cannot retrieve stream due to upload error');
        }

        if ($this->isMoved()) {
            throw new RuntimeException('Cannot retrieve stream after it has already been moved');
        }
    }

    /**
     * {@inheritdoc}
     * @throws RuntimeException if the upload was not successful.
     */
    public function getStream()
    {
        $this->validateActive();

        if ($this->stream instanceof StreamInterface) {
            return $this->stream;
        }

        return new Stream(fopen($this->file, "r+"));
    }

    /**
     * 移动文件到指定位置,可以移动到特定流上,第二次无法使用
     *
     * @param string $targetPath Path to which to move the uploaded file.
     * @throws \InvalidArgumentException if the $targetPath specified is invalid.
     * @throws \RuntimeException on any error during the move operation.
     * @throws \RuntimeException on the second or subsequent call to the method.
     */
    public function moveTo($targetPath)
    {
        $this->validateActive();

        if (false === $this->isStringNotEmpty($targetPath)) {
            throw new InvalidArgumentException(
                'Invalid path provided for move operation; must be a non-empty string'
            );
        }

        if ($this->file) {
            $this->moved = php_sapi_name() == 'cli'
                ? rename($this->file, $targetPath)
                : move_uploaded_file($this->file, $targetPath);
        } else {
            // 将目标作为流打开
            $targetStream = fopen($targetPath, 'w+');
            // 将流拷贝到目标文件上
            stream_copy_to_stream($this->stream->detach(), $targetStream);
            // 关闭流,减少损耗
            fclose($targetStream);

            $this->moved = true;
        }

        if (false === $this->moved) {
            throw new RuntimeException(
                sprintf('Uploaded file could not be moved to %s', $targetPath)
            );
        }
    }

    /**
     * @return int|null
     */
    public function getSize()
    {
        return $this->size;
    }

    /**
     * @return int One of PHP's UPLOAD_ERR_XXX constants
     */
    public function getError()
    {
        return $this->error;
    }

    /**
     * @return string|null
     */
    public function getClientFilename()
    {
        return $this->clientFilename;
    }

    /**
     * @return string|null
     */
    public function getClientMediaType()
    {
        return $this->clientMediaType;
    }
}