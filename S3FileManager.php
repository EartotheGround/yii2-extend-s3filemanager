<?php

namespace Human\Yii2ExtendS3FileManager;

use Yii;
use yii\base\InvalidConfigException;
use yii\web\ServerErrorHttpException;
use yii\web\NotFoundHttpException;
use Aws\S3\S3Client;

class S3FileManager extends \yii\base\Component
{
	private
		$bucketName,
		$region = 'eu-west-1',
		$environment,
		$localProfile,
		$temporaryFileLocation,
		$publicAccess = false,

		$client,
		$baseRoute;

	/**
	 * Create the S3 client. `bucketName`, `environment`, `temporaryFileLocation`, and `localProfile` must have been set in the config of the component. `region` is optional with the default of 'eu-west-1' and `publicAccess` with the default false.
	 *
	 * @return $this
	 */
	public function init()
	{
		parent::init();

		if (!isset($this->bucketName)) {
			throw new InvalidConfigException('bucketName must be set');
		}

		if (!isset($this->region)) {
			throw new InvalidConfigException('region must be set');
		}

		if (!isset($this->environment)) {
			throw new InvalidConfigException('environment must be set');
		}

		if (!isset($this->temporaryFileLocation)) {
			throw new InvalidConfigException('temporaryFileLocation must be set');
		}

		$this->environment = strtolower($this->environment);

		$this->baseRoute = $this->environment.'/';

		$params = [
			'version' => 'latest',
			'region' => $this->region,
		];
		
		// For local development, use a profile from your ~/.aws/credentials file
		if($this->environment === 'local')
		{
			// We need to get an absolute path
			$parts = explode('/', __DIR__);
			$provider = \Aws\Credentials\CredentialProvider::ini($this->localProfile, '/Users/'.$parts[2].'/.aws/credentials');
			$provider = \Aws\Credentials\CredentialProvider::memoize($provider);

			$params['credentials'] = $provider;
			$params['debug'] = false; // Prints loads of helpful info to the page
		}
		
		
		$this->client = new \Aws\S3\S3Client($params);

		return $this;
	}

	/**
	 * Allow $this->bucketName to be set in the config of the component.
	 *
	 * @param string $value The name of the bucket the files will be uploaded to.
	 */
	public function setBucketName($value)
	{
		$this->bucketName = $value;
	}

	/**
	 * Allow $this->region to be set in the config of the component.
	 *
	 * @param string $value The region the bucket is in, default 'eu-west-1'.
	 */
	public function setRegion($value)
	{
		$this->region = $value;
	}

	/**
	 * Allow $this->environment to be set in the config of the component.
	 *
	 * @param string $value The current environment, assumed to be 'local', 'QA' or 'staging', or 'production' (case insensitive).
	 */
	public function setEnvironment($value)
	{
		$this->environment = $value;
	}

	/**
	 * Allow $this->temporaryFileLocation to be set in the config of the component.
	 *
	 * @param string $value The location to save files in temporarily before being used by the app (assumed to be an alias).
	 */
	public function setTemporaryFileLocation($value)
	{
		$this->temporaryFileLocation = Yii::getAlias($value);
	}

	/**
	 * Allow $this->localProfile to be set in the config of the component.
	 *
	 * @param string $value The name of the profile from your ~/.aws/credentials file for local development.
	 */
	public function setLocalProfile($value)
	{
		$this->localProfile = $value;
	}

	/**
	 * Allow $this->publicAccess to be set in the config of the component.
	 *
	 * @param bool $value Whether the files should be uploaded as publically accessible.
	 */
	public function setPublicAccess($value)
	{
		$this->publicAccess = $value;
	}

	/**
	 * Upload a file.
	 *
	 * @param string $s3FilePath The bucket path name to upload the file as.
	 * @param string $localFilePath The path to the local file to upload.
	 * @return string $url The URL of the file, or false if the upload was unsuccessful.
	 */
	public function upload($s3FilePath, $localFilePath)
	{
		$fullS3FilePath = $this->baseRoute.$s3FilePath;

		if (file_get_contents($localFilePath) === false) {
			throw new ServerErrorHttpException("Failed to read file ".$localFilePath);
		}
		
		if ($this->fileExists($fullS3FilePath)) {
			throw new ServerErrorHttpException("A file already exists with the path ".$s3FilePath);
		}

		$settings = [
			'Bucket' 		=> $this->bucketName,
			'Key'    		=> $fullS3FilePath,
			'SourceFile'	=> $localFilePath,
			'ContentType'	=> mime_content_type($localFilePath),
		];

		if ($this->publicAccess) {
			$settings['ACL'] = 'public-read';
		}

		$result = $this->client->putObject($settings);

		return $result['ObjectURL'];
	}

	/**
	 * Move or rename a file.
	 *
	 * @param string $s3FilePath The current bucket path name of the file.
	 * @param string $newFilePath The new bucket path name of the file.
	 * @return string $url The URL of the file, or false if the move was unsuccessful.
	 */
	public function move($s3FilePath, $newFilePath)
	{
		$fullS3FilePath = $this->baseRoute.$s3FilePath;
		$newFilePath = $this->baseRoute.$newFilePath;

		if (!$this->fileExists($fullS3FilePath)) {
			throw new NotFoundHttpException("Current file does not exist at ".$s3FilePath);
		}

		$result = $this->client->copyObject([
			'Bucket' 		=> $this->bucketName,
			'Key'    		=> $newFilePath,
			'CopySource'	=> $this->bucketName.'/'.$fullS3FilePath,
		]);

		$this->delete($fullS3FilePath);
	
		return $result['ObjectURL'];
	}

	/**
	 * Delete a file. If the file does not exist it returns true.
	 *
	 * @param string $s3FilePath The bucket path name of the file to delete.
	 * @return bool $url Whether deletion was successful.
	 */
	public function delete($s3FilePath)
	{
		$fullS3FilePath = $this->baseRoute.$s3FilePath;

		if (!$this->fileExists($fullS3FilePath)) {
			return true;
		}

		$result = $this->client->deleteObject([
			'Bucket' 		=> $this->bucketName,
			'Key'    		=> $fullS3FilePath,
		]);

		return $result['DeleteMarker'];
	}

	/**
	 * Download a file
	 *
	 * @param string $s3FilePath The current bucket path name of the file.
	 * @param string $newFilePath The new bucket path name of the file.
	 * @return string $url The URL of the file, or false if the move was unsuccessful.
	 */
	public function download($s3FilePath, $name)
	{
		$fullS3FilePath = $this->baseRoute.$s3FilePath;

		$result = $this->client->getObject([
			'Bucket'	=> $this->bucketName,
			'Key'		=> $fullS3FilePath,
		]);

		$tempFilePath = $this->temporaryFileLocation.$name;
		file_put_contents($tempFilePath, $result['Body']);
		return $tempFilePath;
	}

	/**
	 * Get a public temporary URL of a private file
	 *
	 * @param string $s3FilePath The current bucket path name of the file.
	 * @return string $url The URL of the file.
	 */
	public function getSignedUrl($s3FilePath)
	{
		$fullS3FilePath = $this->baseRoute.$s3FilePath;

		try {

			$request = $this->client->getCommand('GetObject', [
				'Bucket'	=> $this->bucketName,
				'Key'		=> $fullS3FilePath,
			]);

			$result = $this->client->createPresignedRequest($request, '+10 minutes');

		} catch (\Exception $e) {
			return null;
		}

		return (string)$result->getUri();
	}

	private function fileExists($fullS3FilePath)
	{
		return $this->client->doesObjectExist($this->bucketName, $fullS3FilePath, []);
	}
}