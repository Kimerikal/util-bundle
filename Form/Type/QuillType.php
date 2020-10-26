<?php

namespace Kimerikal\UtilBundle\Form\Type;

use Kimerikal\UtilBundle\Entity\ExceptionUtil;
use Kimerikal\UtilBundle\Model\Select2FormField;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;

class QuillType extends AbstractType
{
    public function getParent()
    {
        return 'textarea';
    }

    public function getName()
    {
        return 'quill';
    }
}
