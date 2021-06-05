<?php


namespace AndersBjorkland\InstagramDisplayExtension\Service;


use AndersBjorkland\InstagramDisplayExtension\Exceptions\MissingArrayKeyException;
use AndersBjorkland\InstagramDisplayExtension\Extension;
use Bolt\Extension\ExtensionRegistry;

class FileUploader
{
    private $extensionConfig;

    /**
     * FileUploader constructor.
     * @param ExtensionRegistry $registry
     */
    public function __construct(ExtensionRegistry $registry)
    {
        $this->extensionConfig = $registry->getExtension(Extension::class)->getConfig();
    }


    /**
     * @param array $mediaArray with keys "media_type", "media_url", "timestamp"
     * @return string or bool. Return the file path if file was successfully written. Or false if it was not written successfully.
     * @throws MissingArrayKeyException
     */
    public function upload(array $mediaArray): ?string
    {
        $requiredKeys = ["media_type", "media_url", "timestamp"];

        foreach ($requiredKeys as $requiredKey) {
            if (!array_key_exists($requiredKey, $mediaArray)) {
                throw new MissingArrayKeyException("Missing the array key: $requiredKey");
            }
        }

        $path = $mediaArray["media_url"];
        $pathComponents = parse_url($path);
        $endingIndex = strrpos($pathComponents["path"], ".");
        $fileEnding = substr($pathComponents["path"], $endingIndex);

        $maxUpload = $this->extensionConfig->get('max_upload_size');
        $target = $this->extensionConfig->get('upload_location');

        $timestamp = $mediaArray["timestamp"];
        $timestampDate = substr($timestamp, 0, strpos($timestamp, "T"));
        $timestampDateArray = explode("-", $timestampDate);
        $year = $timestampDateArray[0];
        $month = $timestampDateArray[1];

        $file = file_get_contents($path);
        $fileName =  md5(uniqid()).'.'.$fileEnding;

        $expandedTarget = $target . '/' . $year . '/' . $month;

        if (!file_exists($expandedTarget)) {
            mkdir($expandedTarget, 0777, true);
        }

        $expandedTarget .= '/' . $fileName;

        $result = file_put_contents($expandedTarget, $file);

        return $result !== false ? $expandedTarget : false;
    }

    public function getTargetDirectory()
    {

    }
}