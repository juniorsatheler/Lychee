<?php

namespace App\Http\Livewire\Sidebar;

use App\Facades\Helpers;
use App\Models\Photo as PhotoModel;
use Livewire\Component;

class Photo extends Component
{
	public string $title;
	public string $description;
	public string $created_at;

	public string $filesize;
	public string $type;
	public string $resolution;
	public string $tags;

	public bool $is_public;
	public bool $has_password;

	public string $owner_name = '';
	public string $license;

	public string $duration = 'xxx';
	public string $fps = 'xxx';

	public string $model;
	public string $make;
	public string $lens;

	public bool $has_exif = false;
	public string $iso;
	public string $focal;
	public string $aperture;
	public string $shutter;

	public bool $has_location = false;
	public string $latitude;
	public string $longitude;
	public string $altitude;
	public string $location;

	public function mount(PhotoModel $photo)
	{
		$this->title = $photo->title;
		$this->created_at = $photo->created_at->format('F Y');
		$this->description = $photo->description ?? '';

		$this->type = $photo->type;
		$original = $photo->size_variants->getOriginal();
		$this->filesize = Helpers::getSymbolByQuantity($original->filesize);
		$this->resolution = $original->width . ' x ' . $original->height;

		$this->is_video = $photo->isVideo();
		// $this->is_public = $photo->is_public;
		$this->license = $photo->license;

		$this->taken_at = $photo->taken_at->toString(); // for simplicity for now.
		$this->make = $photo->make;
		$this->model = $photo->model;

		$this->has_exif = $this->genExifHash($photo) != '';
		if ($this->has_exif) {
			$this->lens = $photo->lens;
			$this->shutter = $photo->shutter;
			$this->aperture = str_replace('f/', '', $photo->aperture);
			$this->focal = $photo->focal;
			$this->iso = $photo->iso;
		}

		$this->has_location = $this->has_location($photo);
		if ($this->has_location) {
			$this->latitude = $this->decimalToDegreeMinutesSeconds($photo->latitude, true);
			$this->longitude = $this->decimalToDegreeMinutesSeconds($photo->longitude, false);
			$this->altitude = round($photo->altitude, 1) . 'm';
			$this->location = $photo->location;
		}
	}

	public function render()
	{
		return view('livewire.sidebar.photo');
	}

	/**
	 * Converts a decimal degree into integer degree, minutes and seconds.
	 *
	 * TODO: Consider to make this method part of `lychee.locale`.
	 *
	 * @param {number}  decimal
	 * @param {boolean} type    - indicates if the passed decimal indicates a
	 *                            latitude (`true`) or a longitude (`false`)
	 * @returns {string}
	 */
	private function decimalToDegreeMinutesSeconds(int $decimal, bool $type): string
	{
		$d = abs($decimal);

		// absolute value of decimal must be smaller than 180;
		if ($d > 180) {
			return '';
		}

		// set direction; north assumed
		if ($type && $decimal < 0) {
			$direction = 'S';
		} elseif (!$type && $decimal < 0) {
			$direction = 'W';
		} elseif (!$type) {
			$direction = 'E';
		} else {
			$direction = 'N';
		}

		// get degrees
		$degrees = floor($d);

		// get seconds
		$seconds = ($d - $degrees) * 3600;

		// get minutes
		$minutes = floor($seconds / 60);

		// reset seconds
		$seconds = floor($seconds - $minutes * 60);

		return $degrees + '° ' + $minutes + "' " + $seconds + '" ' + $direction;
	}

	private function genExifHash(PhotoModel $photo): string
	{
		$exifHash = $photo->make;
		$exifHash .= $photo->model;
		$exifHash .= $photo->shutter;
		if ($photo->isVideo()) {
			$exifHash .= $photo->aperture;
			$exifHash .= $photo->focal;
		}
		$exifHash .= $photo->iso;

		return $exifHash;
	}

	private function has_location(PhotoModel $photo): bool
	{
		return $photo->longitude !== null || $photo->latitude !== null || $photo->altitude !== null;
	}
}