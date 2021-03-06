<?php

namespace Kimerikal\UtilBundle\Form\Type;

use Kimerikal\UtilBundle\Entity\TimeUtil;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Doctrine\ORM\EntityManager;
use Kimerikal\UtilBundle\Model\Select2FormField;

class AjaxSelectType extends AbstractType
{

    protected $router;
    protected $em;

    public function __construct($router, EntityManager $em)
    {
        $this->router = $router;
        $this->em = $em;
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->setAttribute('attr', array_merge($options['attr'], array('class' => 'form-control ajax-select', 'data-ajax-url' => $this->router->generate($options['route']))));
        /*$builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) use ($options) {
            $obj = $event->getData();
            $child = $event->getForm();
            $data = $child->getData();
            $choices = array($obj => $obj);
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

           $child->getParent()->add($child->getName(), 'ajax_select', array_merge(['choices' => $choices], $child->getConfig()->getOptions()));
        });*/
    }

    /**
     * {@inheritdoc}
     */
    public function finishView(FormView $view, FormInterface $form, array $options)
    {
        parent::finishView($view, $form, $options);
        $data = $view->vars['data'];
        $view->vars['curVal'] = null;
        if (!empty($data)) {
            try {
                $bdData = $this->em->getRepository($options['target_object'])->find($data);
                if ($bdData && $bdData instanceof Select2FormField) {
                    $val = new \stdClass();
                    $val->value = $bdData->select2id();
                    $val->text = $bdData->select2text();

                    $view->vars['curVal'] = $val;
                }
            } catch (\Exception $e) {
                error_log($e->getMessage());
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        $view->vars['attr'] = $form->getConfig()->getAttribute('attr');
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setRequired(array('route', 'target_object'));
        $resolver->setDefaults(array('choices' => array(), 'choices_as_value' => true, 'target_object' => null));
    }

    public function getParent()
    {
        return ChoiceType::class;
    }

    public function getName()
    {
        return 'ajax_select';
    }

}
