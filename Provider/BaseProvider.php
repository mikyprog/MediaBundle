<?php



namespace Miky\Bundle\MediaBundle\Provider;

use Miky\Bundle\MediaBundle\CDN\CDNInterface;
use Miky\Bundle\MediaBundle\Generator\GeneratorInterface;
use Miky\Component\Media\Model\MediaInterface;
use Miky\Bundle\MediaBundle\Resizer\ResizerInterface;
use Miky\Bundle\MediaBundle\Thumbnail\ThumbnailInterface;
use Gaufrette\Filesystem;
use Sonata\CoreBundle\Model\Metadata;
use Sonata\CoreBundle\Validator\ErrorElement;

abstract class BaseProvider implements MediaProviderInterface
{
    /**
     * @var array
     */
    protected $formats = array();

    /**
     * @var string[]
     */
    protected $templates = array();

    /**
     * @var ResizerInterface
     */
    protected $resizer;

    /**
     * @var Filesystem
     */
    protected $filesystem;

    /**
     * @var GeneratorInterface
     */
    protected $pathGenerator;

    /**
     * @var CDNInterface
     */
    protected $cdn;

    /**
     * @var ThumbnailInterface
     */
    protected $thumbnail;

    /**
     * @var string
     */
    protected $name;

    /**
     * @param string             $name
     * @param Filesystem         $filesystem
     * @param CDNInterface       $cdn
     * @param GeneratorInterface $pathGenerator
     * @param ThumbnailInterface $thumbnail
     */
    public function __construct($name, Filesystem $filesystem, CDNInterface $cdn, GeneratorInterface $pathGenerator, ThumbnailInterface $thumbnail)
    {
        $this->name = $name;
        $this->filesystem = $filesystem;
        $this->cdn = $cdn;
        $this->pathGenerator = $pathGenerator;
        $this->thumbnail = $thumbnail;
    }

    /**
     * {@inheritdoc}
     */
    final public function transform(MediaInterface $media)
    {
        if (null === $media->getBinaryContent()) {
            return;
        }

        $this->doTransform($media);
        $this->flushCdn($media);
    }

    /**
     * @param MediaInterface $media
     */
    public function flushCdn(MediaInterface $media)
    {
        if ($media->getId() && $this->requireThumbnails() && !$media->getCdnIsFlushable()) {
            $flushPaths = array();
            foreach ($this->getFormats() as $format => $settings) {
                if ('admin' === $format || substr($format, 0, strlen($media->getContext())) === $media->getContext()) {
                    $flushPaths[] = $this->getFilesystem()->get($this->generatePrivateUrl($media, $format), true)->getKey();
                }
            }
            if (!empty($flushPaths)) {
                $cdnFlushIdentifier = $this->getCdn()->flushPaths($flushPaths);
                $media->setCdnFlushIdentifier($cdnFlushIdentifier);
                $media->setCdnIsFlushable(true);
                $media->setCdnStatus(CDNInterface::STATUS_TO_FLUSH);
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function addFormat($name, $format)
    {
        $this->formats[$name] = $format;
    }

    /**
     * {@inheritdoc}
     */
    public function getFormat($name)
    {
        return isset($this->formats[$name]) ? $this->formats[$name] : false;
    }

    /**
     * {@inheritdoc}
     */
    public function requireThumbnails()
    {
        return $this->getResizer() !== null;
    }

    /**
     * {@inheritdoc}
     */
    public function generateThumbnails(MediaInterface $media)
    {
        $this->thumbnail->generate($this, $media);
    }

    /**
     * {@inheritdoc}
     */
    public function removeThumbnails(MediaInterface $media, $formats = null)
    {
        $this->thumbnail->delete($this, $media, $formats);
    }

    /**
     * {@inheritdoc}
     */
    public function getFormatName(MediaInterface $media, $format)
    {
        if ($format == 'admin') {
            return 'admin';
        }

        if ($format == 'reference') {
            return 'reference';
        }

        $baseName = $media->getContext().'_';
        if (substr($format, 0, strlen($baseName)) == $baseName) {
            return $format;
        }

        return $baseName.$format;
    }

    /**
     * {@inheritdoc}
     */
    public function getProviderMetadata()
    {
        return new Metadata($this->getName(), $this->getName().'.description', false, 'MikyMediaBundle', array('class' => 'fa fa-file'));
    }

    /**
     * {@inheritdoc}
     */
    public function preRemove(MediaInterface $media)
    {
        $path = $this->getReferenceImage($media);

        if ($this->getFilesystem()->has($path)) {
            $this->getFilesystem()->delete($path);
        }

        if ($this->requireThumbnails()) {
            $this->thumbnail->delete($this, $media);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function postRemove(MediaInterface $media)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function generatePath(MediaInterface $media)
    {
        return $this->pathGenerator->generatePath($media);
    }

    /**
     * {@inheritdoc}
     */
    public function getFormats()
    {
        return $this->formats;
    }

    /**
     * {@inheritdoc}
     */
    public function setName($name)
    {
        $this->name = $name;
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * {@inheritdoc}
     */
    public function setTemplates(array $templates)
    {
        $this->templates = $templates;
    }

    /**
     * {@inheritdoc}
     */
    public function getTemplates()
    {
        return $this->templates;
    }

    /**
     * {@inheritdoc}
     */
    public function getTemplate($name)
    {
        return isset($this->templates[$name]) ? $this->templates[$name] : null;
    }

    /**
     * {@inheritdoc}
     */
    public function getResizer()
    {
        return $this->resizer;
    }

    /**
     * {@inheritdoc}
     */
    public function getFilesystem()
    {
        return $this->filesystem;
    }

    /**
     * {@inheritdoc}
     */
    public function getCdn()
    {
        return $this->cdn;
    }

    /**
     * {@inheritdoc}
     */
    public function getCdnPath($relativePath, $isFlushable)
    {
        return $this->getCdn()->getPath($relativePath, $isFlushable);
    }

    /**
     * {@inheritdoc}
     */
    public function setResizer(ResizerInterface $resizer)
    {
        $this->resizer = $resizer;
    }

    /**
     * {@inheritdoc}
     */
    public function prePersist(MediaInterface $media)
    {
        $media->setCreatedAt(new \Datetime());
        $media->setUpdatedAt(new \Datetime());
    }

    /**
     * {@inheritdoc}
     */
    public function preUpdate(MediaInterface $media)
    {
        $media->setUpdatedAt(new \Datetime());
    }

    /**
     * {@inheritdoc}
     */
    public function validate(ErrorElement $errorElement, MediaInterface $media)
    {
    }

    /**
     * @param MediaInterface $media
     */
    abstract protected function doTransform(MediaInterface $media);
}
