<?php

namespace APP\plugins\importexport\simplexml\elements;

use APP\core\Services;
use APP\facades\Repo;
use APP\file\PublicFileManager;
use DOMElement;
use Illuminate\Support\Facades\DB;
use PKP\core\PKPApplication;
use PKP\db\DAORegistry;
use PKP\file\FileManager;
use PKP\file\TemporaryFileManager;

class CoverElement {

    public $file_name, $alt, $extension;
    private $file_contents;

    public function __debugInfo(){
    
        $vars = get_object_vars($this);
        
        $vars['file_contents'] = '~~base64~~';
    
        return $vars;
    }

    public function __construct(DOMElement $element) {
        $this->extension = 'jpg'; // TODO: figure out properly

        foreach($element->childNodes as $child) {
            switch($child->nodeName) {
                case 'cover_image':
                    $this->file_name = trim(
                        preg_replace(
                            "/[^a-z0-9\.\-]+/",
                            "",
                            str_replace(
                                [' ', '_', ':'],
                                '-',
                                strtolower($child->nodeValue)
                            )
                        )
                    );
                    break;
                case 'cover_image_alt_text':
                    $this->alt = $child->nodeValue;
                case 'embed':
                    $this->file_contents = $child->childNodes[0]->nodeValue;
                    break;
                default:
                    echo "WARN: unknown nodeName for file " . $child->nodeName . "\n";
            }
        }
    }

    public function save($context) {

        $publicFileManager = new PublicFileManager();
        $filePath = $publicFileManager->getContextFilesPath($context->getId()) . '/' . $this->file_name;
        $extension = pathinfo(strtolower($filePath), PATHINFO_EXTENSION);
        file_put_contents($filePath, base64_decode($this->file_contents));

        return [
            'en' => [
                'uploadName' => $this->file_name,
                'altText' => $this->alt,
            ]
        ];

    }

}