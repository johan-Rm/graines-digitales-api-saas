<?php

namespace App\Uploader;

use App\Entity\MediaObject;
use Vich\UploaderBundle\Mapping\PropertyMapping;
use Vich\UploaderBundle\Naming\DirectoryNamerInterface;

final class ProjectDirectoryNamer implements DirectoryNamerInterface
{
    public function directoryName(object|array $object, PropertyMapping $mapping): string
    {
        if (!$object instanceof MediaObject) {
            return '';
        }

        return $object->getProject()?->getMediaPath() ?? '';
    }
}
