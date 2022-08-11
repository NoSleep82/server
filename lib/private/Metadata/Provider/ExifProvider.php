<?php

namespace OC\Metadata\Provider;

use OC\Metadata\FileMetadata;
use OC\Metadata\IMetadataProvider;
use OCP\Files\File;

class ExifProvider implements IMetadataProvider {
	public static function groupsProvided(): array {
		return ['size'];
	}

	public static function isAvailable(): bool {
		return extension_loaded('exif');
	}

	public function execute(File $file): array {
		$exifData = [];
		// $fileDescriptor = $file->fopen('rb');
		// $data = exif_read_data($fileDescriptor, 'ANY_TAG', true);
		// $data = exif_read_data($file->getContent(), 'ANY_TAG', true);
		$data = exif_read_data($file->getStorage()->getLocalFile($file->getInternalPath()), 'ANY_TAG', true);

		$size = new FileMetadata();
		$size->setGroupName('size');
		$size->setId($file->getId());
		$size->setMetadata([]);

		if (!$data) {
			$sizeResult = getimagesizefromstring($file->getContent());
			if ($sizeResult !== false) {
				$size->setMetadata([
					'width' => $sizeResult[0],
					'height' => $sizeResult[1],
				]);
			}

			$exifData['size'] = $size;
		} else if (array_key_exists('COMPUTED', $data)) {
			if (array_key_exists('Width', $data['COMPUTED']) && array_key_exists('Height', $data['COMPUTED'])) {
				$size->setMetadata([
					'width' => $data['COMPUTED']['Width'],
					'height' => $data['COMPUTED']['Height'],
				]);
			}

			$exifData['size'] = $size;
		}

		if ($data && array_key_exists('GPS', $data)) {
			$gps = new FileMetadata();
			$gps->setGroupName('gps');
			$gps->setId($file->getId());
			$gps->setMetadata([
				'coordinate' => [
					'latitude' => $this->gpsDegreesToDecimal($data['GPS']['GPSLatitude'], $data['GPS']['GPSLatitudeRef']),
					'longitude' => $this->gpsDegreesToDecimal($data['GPS']['GPSLongitude'], $data['GPS']['GPSLongitudeRef']),
				],
			]);

			$exifData['gps'] = $gps;
		}

		return $exifData;
	}

	public static function getMimetypesSupported(): string {
		return '/image\/.*/';
	}

	private static function gpsDegreesToDecimal(array $coordinates, string $hemisphere): float {
		if (is_string($coordinates)) {
			$coordinates = array_map("trim", explode(",", $coordinates));
		}

		[$degrees, $minutes, $seconds] = array_map(function ($rawDegree) {
			[$degree, $dividend] = explode('/', $rawDegree);
			return floatval($degree)/floatval($dividend ?? 1);
		}, $coordinates);

		$sign = ($hemisphere === 'W' || $hemisphere === 'S') ? -1 : 1;
		return $sign * ($degrees + $minutes/60 + $seconds/3600);
	}
}
