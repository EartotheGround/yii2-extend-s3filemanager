<?php

namespace Human\Yii2ExtendS3FileManager;

use Aws\S3\S3Client;

class S3FileManager extends \yii\base\Component
{
	private
		$bucketName,
		$region = 'eu-west-1',
		$environment,
		$localProfile,

		$baseRoute;
	/**
	 * Create the Postmark client. bucketName, environment, and localProfile must have been set in the config of the component. region is optional with the default of 'eu-west-1'
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

		$this->environment = strtolower($this->environment);

		$this->baseRoute = $this->environment.'/';

		$params =[
			'version'     => 'latest',
			'region'      => $this->region,
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
	 * Allow $this->localProfile to be set in the config of the component.
	 *
	 * @param string $value The name of the profile from your ~/.aws/credentials file for local development.
	 */
	public function setLocalProfile($value)
	{
		$this->localProfile = $value;
	}

	public function upload($s3FilePath, $currentFilePath)
	{
		if (file_get_contents($currentFilePath) === false) {
			throw new HttpException(500,"Failed to read file " . $currentFilePath);
		}
		
		// Prepend the key with the basePath to namespace by environment
		$s3FilePath = $this->baseRoute.$s3FilePath;

		try {
			$result = $this->client->putObject([
				'ACL' 			=> 'public-read',
				'Bucket' 		=> self::BUCKET_NAME,
				'Key'    		=> $s3FilePath,
				'SourceFile'	=> $currentFilePath,
				'ContentType'	=> mime_content_type($currentFilePath),
			]);

		} catch (S3Exception $e) {
			return false;

		} catch (\Exception $e) {
			return false;
		}

		return $result['ObjectURL'];
	}
}