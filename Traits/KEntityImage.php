<?php


namespace Kimerikal\UtilBundle\Traits;

use Kimerikal\UtilBundle\Entity\ExceptionUtil;
use Kimerikal\UtilBundle\Entity\ImgUtil;
use Kimerikal\UtilBundle\Entity\StrUtil;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Doctrine\Common\Persistence\Event\LifecycleEventArgs;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Symfony\Component\Validator\Constraints as Assert;

Trait KEntityImage
{
    /**
     * @Assert\File(maxSize="6000000")
     * @FormData(type="file", label="Imagen", col=4, newLine=true)
     */
    private $file;

    /**
     * @FormData(type="hidden", customAttrs={"class"="crop crop-x"})
     */
    private $xCrop;

    /**
     * @FormData(type="hidden", customAttrs={"class"="crop crop-y"})
     */
    private $yCrop;

    /**
     * @FormData(type="hidden", customAttrs={"class"="crop crop-width"})
     */
    private $widthCrop;

    /**
     * @FormData(type="hidden", customAttrs={"class"="crop crop-height"})
     */
    private $heightCrop;

    /**
     * @ORM\Column(name="cover", type="string", length=255, nullable=true)
     */
    private $cover;

    /**
     * @ORM\Column(name="gallery", type="json", nullable=true)
     */
    private $gallery;

    /**
     * Sets file.
     *
     * @param UploadedFile $file
     */
    public function setFile(UploadedFile $file = null)
    {
        $this->file = $file;
    }

    /**
     * Get file.
     *
     * @return UploadedFile
     */
    public function getFile()
    {
        return $this->file;
    }

    public function getAbsolutePath()
    {
        return null === $this->cover ? '' : $this->getUploadRootDir() . '/' . $this->cover;
    }

    public function getWebPath()
    {
        return null === $this->cover ? $this->getDefaultLogo() : $this->getUploadDir() . $this->cover;
    }

    public function getUploadRootDir()
    {
        return dirname(__DIR__, 6) . '/web/' . $this->getUploadDir();
    }

    public function getUploadDir()
    {
        return 'media/images/' . StrUtil::slug(get_class($this)) . '/' . $this->id . "/";
    }

    public function getDefaultLogo()
    {
        return 'media/images/no-image.jpg';
    }

    public function getAPIPath($baseUrl)
    {
        return $baseUrl . (null === $this->cover ? $this->getDefaultLogo() : $this->getUploadDir() . $this->cover);
    }

    /**
     * @ORM\PostPersist
     * @ORM\PostUpdate
     * @return bool|null
     */
    public function upload(LifecycleEventArgs $event)
    {
        if (null === $this->getFile())
            return;

        $fileSystem = new Filesystem();
        if (!empty($this->cover) && $fileSystem->exists($this->getAbsolutePath())) {
            try {
                $fileSystem->remove($this->getAbsolutePath());
            } catch (IOException $e) {
                ExceptionUtil::logException($e, 'KEntityImage::upload::unlimk');
            }
        }

        try {
            $path = $this->getUploadRootDir();
            if (!$fileSystem->exists($this->getUploadRootDir()))
                $fileSystem->mkdir($this->getUploadRootDir());

            $ext = "." . $this->getFile()->getClientOriginalExtension();
            $fileName = StrUtil::slug(str_replace($ext, '', $this->getFile()->getClientOriginalName()) . '-' . time());
            if ($this->getFile()->move($this->getUploadRootDir(), $fileName . $ext)) {
                $path = $this->getUploadRootDir() . $fileName;

                if (!\is_null($this->xCrop) && !\is_null($this->yCrop) && !\is_null($this->widthCrop) && !\is_null($this->heightCrop))
                    ImgUtil::crop($path . $ext, $this->xCrop, $this->yCrop, $this->widthCrop, $this->heightCrop, 450, 250);

                ImgUtil::resizeFile($path . $ext, 1024);
                $this->cover = $fileName . $ext;
                $this->file = null;

                $event->getObjectManager()->persist($this);
                $event->getObjectManager()->flush();
            }
        } catch (IOException $e) {
            ExceptionUtil::logException($e, 'KEntityImage::upload');
        } catch (\Exception $e) {
            ExceptionUtil::logException($e, 'KEntityImage::upload');
        }
    }

    private function getGalleryFullUrl()
    {
        if (!empty($this->gallery) && \count($this->gallery) > 0) {
            $gallery = array();
            /*foreach ($this->gallery as $g) {
                $gallery[] = URL . '/' . $g;
            }*/

            return $gallery;
        }

        return null;
    }

    /**
     * Set cover
     *
     * @param string $cover
     */
    public function setCover($cover)
    {
        $this->cover = $cover;

        return $this;
    }

    /**
     * Get cover
     *
     * @return string
     */
    public function getCover()
    {
        return $this->cover;
    }

    /**
     * Set gallery
     *
     * @param array $gallery
     */
    public function setGallery($gallery)
    {
        $this->gallery = $gallery;

        return $this;
    }

    /**
     * Get gallery
     *
     * @return array
     */
    public function getGallery()
    {
        return $this->gallery;
    }

    public function jsonFormatCover()
    {
        return $this->getWebPath();
    }
}