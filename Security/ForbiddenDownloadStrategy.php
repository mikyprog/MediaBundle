<?php



namespace Miky\Bundle\MediaBundle\Security;

use Miky\Component\Media\Model\MediaInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Translation\TranslatorInterface;

class ForbiddenDownloadStrategy implements DownloadStrategyInterface
{
    /**
     * @var TranslatorInterface
     */
    protected $translator;

    /**
     * @param TranslatorInterface $translator
     */
    public function __construct(TranslatorInterface $translator)
    {
        $this->translator = $translator;
    }

    /**
     * {@inheritdoc}
     */
    public function isGranted(MediaInterface $media, Request $request)
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function getDescription()
    {
        return $this->translator->trans('description.forbidden_download_strategy', array(), 'MikyMediaBundle');
    }
}
