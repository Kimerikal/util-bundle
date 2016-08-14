<?php

namespace Kimerikal\UtilBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use Doctrine\Common\Annotations\AnnotationReader;
use Symfony\Component\PropertyAccess\PropertyAccess;

class SimpleForm extends AbstractType {

    private $trans;
    private $class;

    public function __construct($class, $trans) {
        $this->class = $class;
        $this->trans = $trans;
    }

    /**
     * @param FormBuilderInterface $builder
     * @param array $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options) {
        $reader = new AnnotationReader();
        $reflectionClass = new \ReflectionClass($this->class);
        $props = $reflectionClass->getProperties();
        foreach ($props as $p) {
            $reflectionProperty = new \ReflectionProperty($this->class, $p->name);
            $fd = $reader->getPropertyAnnotation($reflectionProperty, 'Kimerikal\\UtilBundle\\Annotations\\FormData');
            if ($fd) {
                if ($fd->type != 'customForm') {
                    $attrs = array(
                        'class' => 'form-control' . ($fd->type == 'checkbox' ? ' make-switch' : ''),
                        'placeholder' => $fd->placeholder,
                        'nlal' => $fd->newLine,
                        'bcol' => $fd->col
                    );

                    if ($fd->type == 'decimal') {
                        $fd->type = 'number';
                        $attrs['step'] = 'any';
                    }

                    $builder->add($p->name, $fd->type, array(
                        'label' => $fd->label,
                        'required' => $fd->required,
                        'attr' => $attrs
                    ));
                } else {
                    $class = $fd->className;
                    $builder->add($p->name, new $class(1, $this->trans));
                }
            }
        }
    }

    /**
     * @param OptionsResolverInterface $resolver
     */
    public function setDefaultOptions(OptionsResolverInterface $resolver) {
        $resolver->setDefaults(array(
            'data_class' => $this->class
        ));
    }

    /**
     * @return string
     */
    public function getName() {
        return 'kimerikal_utilbundle_' . mb_strtolower(str_replace('\\', '_', $this->class));
    }

}
