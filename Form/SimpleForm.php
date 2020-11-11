<?php

namespace Kimerikal\UtilBundle\Form;

use Kimerikal\UtilBundle\Annotations\FormData;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use Doctrine\Common\Annotations\AnnotationReader;
use Kimerikal\UtilBundle\Entity\TimeUtil;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\Validator\Constraints\Date;
use Tetranz\Select2EntityBundle\Form\Type\Select2EntityType;
use Symfony\Component\Form\ResolvedFormTypeInterface;

class SimpleForm extends AbstractType
{

    private $trans;
    private $class;
    private $group;
    private $accessor;

    public function __construct($class, $trans, $group = null)
    {
        $this->class = $class;
        $this->trans = $trans;
        $this->group = $group;
    }

    /**
     * @param FormBuilderInterface $builder
     * @param array $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        /**
         * Event Listener
         */
        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) {
            $reader = new AnnotationReader();
            $reflectionClass = new \ReflectionClass($this->class);
            $props = $reflectionClass->getProperties();
            foreach ($props as $p) {
                $fd = $reader->getPropertyAnnotation($p, 'Kimerikal\\UtilBundle\\Annotations\\FormData');
                if ($fd) {
                    if ($fd->type == 'entity') {
                        $obj = $event->getData();
                    }
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
                            continue;

                        $form = $event->getForm();
                        $child = $form->get($p->name);
                        $data = $child->getData();
                        $myOptions = $child->getConfig()->getOptions();
                        $name = $child->getName();

                        $choices = array($obj[$name] => $obj[$name]);
                        if ($data instanceof \Doctrine\ORM\PersistentCollection) {
                            $data = $data->toArray();
                        }
                        if ($data != null) {
                            if (is_array($data)) {
                                foreach ($data as $entity) {
                                    $choices[$entity] = $entity;
                                }
                            } else {
                                $choices[$data] = $data;
                            }
                        }

                        $myOptions['choices'] = $choices;
                        $form->add($name, $fd->type, $myOptions);
                    }
                }
            }
        });
        /* FIN EVENT LISTENER */

        $reader = new AnnotationReader();
        $reflectionClass = new \ReflectionClass($this->class);
        $props = $reflectionClass->getProperties(\ReflectionProperty::IS_STATIC | \ReflectionProperty::IS_PUBLIC | \ReflectionProperty::IS_PROTECTED | \ReflectionProperty::IS_PRIVATE);
        $elements = [];
        foreach ($props as $p) {
            $fd = $reader->getPropertyAnnotation($p, FormData::class);
            if ($fd) {
                if (!empty($this->group) && isset($fd->groups) && count($fd->groups) > 0 && !in_array($this->group, $fd->groups) && !array_key_exists($this->group, $fd->groups))
                    continue;
                else if (isset($fd->groups) && count($fd->groups) > 0 && array_key_exists($this->group, $fd->groups)) {
                    if (isset($fd->groups[$this->group]->col))
                        $fd->col = $fd->groups[$this->group]->col;
                }

                if (isset($fd->events) && \count($fd->events) > 0) {
                    foreach ($fd->events as $event) {
                        $builder->addEventSubscriber(new $event());
                    }
                }

                if ($fd->type == 'entityCollection') {
                    /*  $r = new \ReflectionClass("Kimerikal\AccountBundle\Form\DeliveryItemType");
                      $obj = $r->newInstanceArgs(array($fd->class, $this->trans));
                      $l = \Kimerikal\AccountBundle\Entity\DeliveryItem::class;
                      $builder->add($p->name, 'collection', array(
                      'type' => \Kimerikal\AccountBundle\Form\DeliveryItemType::class,
                      'allow_add' => true
                      )); */
                } else if ($fd->type == 'customForm') {
                    $class = $fd->className;
                    $elements[] = ['name' => $p->name, 'type' => new $class(1, $this->trans), 'params' => [], 'order' => $fd->order];
                } else if ($fd->type == 'simpleForm') {
                    $elements[] = ['name' => $p->name, 'type' => new SimpleForm($fd->className, $this->trans), 'params' => [], 'order' => $fd->order];
                } else {
                    $attrs = array(
                        'class' => 'form-control' . ($fd->type == 'checkbox' ? ' make-switch' : ''),
                        'placeholder' => $fd->placeholder,
                        'nlal' => $fd->newLine,
                        'bcol' => $fd->col,
                        'separator' => $fd->separator
                    );

                    $bParams = array('required' => $fd->required, 'mapped' => $fd->mapped);

                    if (!empty($fd->customAttrs)) {
                        $obj = isset($options['data']) ? $options['data'] : null;
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

                        if ($val && $val instanceof \DateTime) {
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
                        $attrs['scale'] = 2;
                    } else if ($fd->type == 'imagecrop') {
                        $attrs['class'] .= ' imageCrop';
                        $fd->type = 'file';
                        $attrs['imgcrop'] = true;
                    } else if ($fd->type == 'ajax_select' && isset($fd->dataUrl) && isset($fd->targetObject)) {
                        $bParams['route'] = $fd->dataUrl;
                        $bParams['target_object'] = $fd->targetObject;
                        // $bParams['class'] = $fd->targetObject;
                    } else if ($fd->type == 'entity_ajax_select' && isset($fd->dataUrl) && isset($fd->targetObject)) {
                        $bParams['route'] = $fd->dataUrl;
                        $bParams['target_object'] = $fd->targetObject;
                        $bParams['class'] = $fd->targetObject;
                        $bParams['choice_label'] = 'id';
                    } else if ($fd->type == 'json_array') {
                        if (isset($fd->format))
                            $bParams['format'] = $fd->format;
                        if (isset($fd->inputType))
                            $bParams['inputType'] = $fd->inputType;
                    } else if ($fd->type == 'tree_select' && isset($fd->targetObject)) {
                        $bParams['target_object'] = $fd->targetObject;
                    }

                    if (!empty($fd->className))
                        $attrs['class'] .= ' ' . $fd->className;

                    $bParams['attr'] = $attrs;
                    if ($fd->label && !empty($fd->label))
                        $bParams['label'] = $fd->label;

                    if ($fd->type == 'choice') {
                        if (!empty($fd->choiceData))
                            $bParams['choices'] = $fd->choiceData;

                        $bParams['choices_as_values'] = true;
                    }

                    if ($fd->type == 'enum') {
                        $fd->type = 'choice';
                        $orm = $reader->getPropertyAnnotation($p, 'Doctrine\ORM\Mapping\Column');
                        $definitions = explode(',', str_replace('\'', '', str_replace('\"', '', str_replace(')', '', str_ireplace('ENUM(', '', $orm->columnDefinition)))));
                        $choices = [];
                        foreach ($definitions as $choice) {
                            $choices[trim($choice)] = trim($choice);
                        }
                        $bParams['choices'] = $choices;
                        $bParams['choices_as_values'] = true;
                    }

                    if ($fd->type == 'entity' && !empty($fd->class) && !empty($fd->choiceLabel)) {
                        $bParams['class'] = $fd->class;
                        $bParams['choice_label'] = $fd->choiceLabel;
                    }

                    if (isset($attrs['multiple'])) {
                        $bParams['multiple'] = $attrs['multiple'];
                        unset($attrs['multiple']);
                    }

                    if (isset($attrs['expanded'])) {
                        $bParams['expanded'] = $attrs['expanded'];
                        unset($attrs['expanded']);
                    }

                    if (isset($attrs['by_reference'])) {
                        $bParams['by_reference'] = $attrs['by_reference'];
                        unset($attrs['by_reference']);
                    }

                    if (isset($attrs['allow_add'])) {
                        $bParams['allow_add'] = $attrs['allow_add'];
                        unset($attrs['allow_add']);
                    }

                    if (isset($attrs['allow_delete'])) {
                        $bParams['allow_delete'] = $attrs['allow_delete'];
                        unset($attrs['allow_delete']);
                    }

                    if ($fd->type == 'date') {
                        $fd->type = 'text';
                        $bParams['attr']['date'] = true;
                        if (isset($fd->emptyValue) && $fd->emptyValue == 'now')
                            $fd->emptyValue = $this->dateToStr(new \DateTime());
                    }

                    if (isset($fd->emptyValue) && !empty($fd->emptyValue)) {
                        $bParams['empty_data'] = $fd->emptyValue;
                    }

                    $elements[] = ['name' => $p->name, 'type' => $fd->type, 'params' => $bParams, 'order' => $fd->order];
                }
            }
        }

        if (count($elements) > 0) {
            usort($elements, function ($a, $b) {
                if ($a['order'] == $b['order'])
                    return 0;
                return ($a['order'] < $b['order']) ? -1 : 1;
            });

            foreach ($elements as $element) {
                $builder->add($element['name'], $element['type'], $element['params']);
            }
        }
    }

    /**
     * @param OptionsResolverInterface $resolver
     */
    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => $this->class
        ));
    }

    /**
     * @return string
     */
    public function getName()
    {
        return 'kimerikal_utilbundle_' . mb_strtolower(str_replace('\\', '_', $this->class));
    }

    private function dateToStr(\DateTime $date, $withHours = false)
    {
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

    public function setGroup($group)
    {
        $this->group = $group;
    }

    public function getGroup()
    {
        return $this->group;
    }

    private function getPropertyAccessor()
    {
        $this->accessor = PropertyAccess::createPropertyAccessor();
    }

    private function populateAjaxChoice(FormEvent $event, $childName, $type)
    {
        $form = $event->getForm();
        $options = $form->get($childName)->getConfig()->getOptions();

        $data = $event->getData();
        if (is_array($data)) {
            $property = '[' . $childName . ']';
        } else {
            $property = $childName;
        }

        $data = $this->getValue($data, $property);
        if (!$data) {
            return;
        }
        $choices = $this->getChoices($data, $options);
        $options['choices'] = $choices;

        $form->add($childName, $type->getInnerType(), $options);
    }

    private function getValue($data, $property)
    {
        if (!$this->accessor) {
            $this->getPropertyAccessor();
        }

        return $this->accessor->getValue($data, $property);
    }

    private function getChoices($data, $options)
    {
        if (is_object($data)) {
            if ($data instanceof \Traversable) {
                return $data;
            } else {
                return array($data);
            }
        } else {
            return $options['em']->getRepository($options['class'])->findById($data);
        }
    }

    private function getListenedType(ResolvedFormTypeInterface $type)
    {
        $return = array('originalType' => get_class($type->getInnerType()));

        while ($type) {
            if (in_array(get_class($type->getInnerType()), $this->enabledTypes)) {
                $return['listenedType'] = get_class($type->getInnerType());

                return $return;
            }

            $type = $type->getParent();
        }

        return false;
    }
}
