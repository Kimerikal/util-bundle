<?php

namespace Kimerikal\UtilBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use Doctrine\Common\Annotations\AnnotationReader;
use Kimerikal\UtilBundle\Entity\TimeUtil;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

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
        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) {
            $reader = new AnnotationReader();
            $reflectionClass = new \ReflectionClass($this->class);
            $props = $reflectionClass->getProperties();
            foreach ($props as $p) {
                $reflectionProperty = new \ReflectionProperty($this->class, $p->name);
                $fd = $reader->getPropertyAnnotation($reflectionProperty, 'Kimerikal\\UtilBundle\\Annotations\\FormData');
                if ($fd) {
                    if (!empty($fd->customAttrs)) {
                        $obj = $event->getData();

                        if (isset($obj[$p->name]) && !empty($obj[$p->name])) {
                            $val = $obj[$p->name];
                            foreach ($fd->customAttrs as $key => $value) {
                                if ($key == 'date' && \is_string($val)) {
                                    $obj[$p->name] = TimeUtil::fromStrToDate($val, 'd-m-Y');
                                    $event->setData($obj);
                                    break;
                                }
                            }
                        }
                    }
                }
            }
        });

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

                    if (!empty($fd->customAttrs)) {
                        $obj = $options['data'];
                        $val = '';
                        $setMethod = '';
                        if (\method_exists($obj, $p->name)) {
                            $method = $p->name;
                            $val = $obj->$method();
                        } else if (\method_exists($obj, 'get' . \ucfirst($p->name))) {
                            $method = 'get' . \ucfirst($p->name);
                            $setMethod = 'set' . \ucfirst($p->name);
                            $val = $obj->$method();
                        }

                        if (\get_class($val) == 'DateTime') {
                            $obj->$setMethod($this->dateToStr($val));
                        }

                        foreach ($fd->customAttrs as $key => $value) {
                            if ($key == 'date') {
                                $attrs['class'] .= ' date-picker';
                            }

                            if (isset($attrs[$key]))
                                $attrs[$key] .= ' ' . $value;
                            else
                                $attrs[$key] = $value;
                        }
                    }

                    if ($fd->type == 'decimal') {
                        $fd->type = 'number';
                        $attrs['step'] = 'any';
                    } else if ($fd->type == 'imagecrop') {
                        $attrs['class'] .= ' imageCrop';
                        $fd->type = 'file';
                        $attrs['imgcrop'] = true;
                    }

                    $bParams = array('required' => $fd->required,
                        'attr' => $attrs);

                    if ($fd->label && !empty($fd->label))
                        $bParams['label'] = $fd->label;

                    if ($fd->type == 'choice' && isset($fd->choiceData) && \count($fd->choiceData) > 0) {
                        $bParams['choices'] = $fd->choiceData;
                        $bParams['choices_as_values'] = true;
                    } else if ($fd->type == 'choice' && (!isset($fd->choiceData) || \count($fd->choiceData) == 0))
                        continue;

                    $builder->add($p->name, $fd->type, $bParams);
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

    private function dateToStr(\DateTime $date, $withHours = false) {
        if (!$date)
            return null;

        $dateStr = $date->format('d-m-Y');
        $timeStr = $date->format('H:i');
        $dateArr = \explode('-', $dateStr);
        $day = $dateArr[0];
        $monthNum = $dateArr[1];
        $year = $dateArr[2];

        $return = $day . "-" . $monthNum . "-" . $year;
        if ($withHours)
            $return .= " - " . $timeStr;


        return $return;
    }

}
