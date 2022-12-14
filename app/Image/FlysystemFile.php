<?php

namespace App\Image;

use App\Exceptions\MediaFileOperationException;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Contracts\Filesystem\Filesystem;
use League\Flysystem\Adapter\Local as LocalAdapter;
use League\Flysystem\Exception as FlyException;
use function Safe\fclose;

/**
 * Class FlysystemFile.
 *
 * This class is based on legacy Flysystem v1 which ships with Laravel 8.
 * Laravel 9 will migrate to Flysystem v2 which provides a different and
 * more consistent API.
 *
 * For v1, this documentation is relevant:
 * https://flysystem.thephpleague.com/v1/docs/usage/filesystem-api/
 */
class FlysystemFile extends MediaFile
{
	protected Filesystem $disk;
	protected string $relativePath;

	public function __construct(Filesystem $disk, string $relativePath)
	{
		$this->disk = $disk;
		$this->relativePath = $relativePath;
	}

	/**
	 * {@inheritDoc}
	 */
	public function read()
	{
		try {
			if (is_resource($this->stream)) {
				fclose($this->stream);
			}

			$this->stream = $this->disk->readStream($this->relativePath);
			if (!is_resource($this->stream)) {
				$this->stream = null;
				throw new FlyException('Filesystem::readStream failed');
			}
		} catch (\ErrorException|FlyException|FileNotFoundException $e) {
			throw new MediaFileOperationException($e->getMessage(), $e);
		}

		return $this->stream;
	}

	/**
	 * {@inheritDoc}
	 */
	public function write($stream, bool $collectStatistics = false): ?StreamStat
	{
		try {
			$streamStat = $collectStatistics ? static::appendStatFilter($stream) : null;

			// TODO: `put` must be replaced by `writeStream` when Flysystem 2 is shipped with Laravel 9
			// This will also be more consistent with `readStream`.
			// Note that v1 also provides a method `writeStream`, but this is a misnomer.
			// See: https://flysystem.thephpleague.com/v2/docs/what-is-new/
			if (!$this->disk->put($this->relativePath, $stream)) {
				throw new FlyException('Filesystem::put failed');
			}

			return $streamStat;
		} catch (\ErrorException|FlyException $e) {
			throw new MediaFileOperationException($e->getMessage(), $e);
		}
	}

	/**
	 * {@inheritDoc}
	 */
	public function delete(): void
	{
		try {
			if (!$this->disk->delete($this->relativePath)) {
				throw new FlyException('Filesystem::delete failed');
			}
		} catch (\ErrorException|FlyException $e) {
			throw new MediaFileOperationException($e->getMessage(), $e);
		}
	}

	/**
	 * {@inheritDoc}
	 */
	public function move(string $newPath): void
	{
		if (!$this->disk->move($this->relativePath, $newPath)) {
			throw new MediaFileOperationException('could not move file');
		}
		$this->relativePath = $newPath;
	}

	/**
	 * {@inheritDoc}
	 */
	public function exists(): bool
	{
		return $this->disk->exists($this->relativePath);
	}

	/**
	 * {@inheritDoc}
	 */
	public function lastModified(): int
	{
		return $this->disk->lastModified($this->relativePath);
	}

	/**
	 * {@inheritDoc}
	 */
	public function getFilesize(): int
	{
		return $this->disk->size($this->relativePath);
	}

	/**
	 * Returns the relative path of the file wrt. the underlying Flysystem disk.
	 *
	 * @return string the relative path
	 */
	public function getRelativePath(): string
	{
		return $this->relativePath;
	}

	/**
	 * @return Filesystem the disk this file is stored on
	 */
	public function getDisk(): Filesystem
	{
		return $this->disk;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getExtension(): string
	{
		$ext = pathinfo($this->relativePath, PATHINFO_EXTENSION);

		return $ext !== '' ? '.' . $ext : '';
	}

	/**
	 * {@inheritDoc}
	 */
	public function getBasename(): string
	{
		return pathinfo($this->relativePath, PATHINFO_FILENAME);
	}

	/**
	 * Determines if this file is a local file.
	 *
	 * @return bool
	 */
	public function isLocalFile(): bool
	{
		return $this->disk->getDriver()->getAdapter() instanceof LocalAdapter;
	}

	/**
	 * @throws MediaFileOperationException
	 */
	public function toLocalFile(): NativeLocalFile
	{
		if (!$this->isLocalFile()) {
			throw new MediaFileOperationException('file is not hosted locally');
		}

		return new NativeLocalFile($this->disk->path($this->relativePath));
	}
}
