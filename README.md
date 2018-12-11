# yii2-s3filemanager

A Human Yii2 S3 File Manager component, with functionality to upload, edit, and delete files

## Example config

```
return [
	'name' => 'APP_NAME',
	'language' => 'en-GB',
	'sourceLanguage' => 'en-GB',
	'timeZone' => 'UTC',
	...,
	'components' => [
		'db' => [
			...
		],
		's3filemanager' => [
			'class' => 'human\yii2-s3filemanager\PostmarkMailer',
			'bucketName' => '[name of the S3 bucket]',
			'environment' => '[local, qa, or production]',
			'localProfile' => '[aws profile for local development]',
		],
```