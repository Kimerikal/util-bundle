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
        /**
         * Event Listener
         */
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
                    } else if ($fd->type == 'ajax_select') {
                        $obj = $event->getData();
                        if (empty($obj[$p->name]))
                            return;
                        $form = $event->getForm();
                        $child = $form->get($p->name);
                        $data = $child->getData();
                        $myOptions = $child->getConfig()->getOptions();
                        $name = $child->getName();

                        $choices = array($obj[$name] => $obj[$name]);
                        if ($data instanceOf \Doctrine\ORM\PersistentCollection) {
                            $data = $data->toArray();
                        }
                        if ($data != null) {
                            if (is_array($data)) {
                                foreach ($data as $entity) {
                                    $choices[] = $entity;
                                }
                            } else {
                                $choices[] = $data;
                            }
                        }

                        $form->add($name, 'ajax_select', array('choices' => $choices, 'label' => $myOptions['label'], 'attr' => $myOptions['attr'], 'route' => $myOptions['route']));
                    }
                }
            }
        });
        /* FIN EVENT LISTENER */

        $reader = new AnnotationReader();
        $reflectionClass = new \ReflectionClass($this->class);
        $props = $reflectionClass->getProperties();
        foreach ($props as $p) {
            $reflectionProperty = new \ReflectionProperty($this->class, $p->name);
            $fd = $reader->getPropertyAnnotation($reflectionProperty, 'Kimerikal\\UtilBundle\\Annotations\\FormData');
            if ($fd) {
                if (isset($fd->events) && \count($fd->events) > 0) {
                    foreach ($fd->events as $event) {
                        $builder->addEventSubscriber(new $event());
                    }
                }

                if ($fd->type != 'customForm') {
                    $attrs = array(
                        'class' => 'form-control' . ($fd->type == 'checkbox' ? ' make-switch' : ''),
                        'placeholder' => $fd->placeholder,
                        'nlal' => $fd->newLine,
                        'bcol' => $fd->col
                    );

                    $bParams = array('required' => $fd->required, 'mapped' => $fd->mapped);

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

                        if (!is_string($val) && \get_class($val) == 'DateTime') {
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
                    } else if ($fd->type == 'ajax_select' && isset($fd->dataUrl)) {
                        $bParams['route'] = $fd->dataUrl;
                    }

                    if (!empty($fd->className))
                        $attrs['class'] .= ' ' . $fd->className;


                    $bParams['attr'] = $attrs;

                    if ($fd->label && !empty($fd->label))
                        $bParams['label'] = $fd->label;

                    if ($fd->type == 'choice') {
                        $bParams['choices'] = $fd->choiceData;
                        $bParams['choices_as_values'] = true;
                    }

                    if (isset($fd->emptyValue) && !empty($fd->emptyValue)) {
                        if ($fd->type == 'date' && $fd->emptyValue == 'now');
                            $fd->emptyValue = $this->dateToStr(new \DateTime());
                        $bParams['empty_data'] = $fd->emptyValue;
                        $bParams['data'] = $fd->emptyValue;
                    }

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
